<?php
/**
 * Fallback-парсер учётных данных без LLM
 * Разбирает неструктурированный текст по паттернам и разделителям
 */
class FallbackParser {

    // Паттерны для определения типа поля
    private const URL_PATTERN = '~^https?://~i';
    private const EMAIL_PATTERN = '~^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$~';
    private const IP_PATTERN = '~^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}(:\d+)?$~';
    private const TOKEN_PATTERN = '~^(sk-|sk_|ghp_|gho_|AIza|AQVN|MDE|ey[A-Za-z0-9])[A-Za-z0-9\-_+/=.]{20,}$~';

    // Ключевые слова для распознавания полей
    private const LOGIN_KEYWORDS = [
        'логин', 'login', 'user', 'username', 'пользователь', 'имя пользователя',
        'логин (имя пользователя)', 'аккаунт', 'account', 'email', 'e-mail',
        'имя основного пользователя', 'client_id', 'client id', 'clientid',
        'идентификатор', 'id клиента'
    ];
    private const PASSWORD_KEYWORDS = [
        'пароль', 'password', 'pass', 'pwd', 'secret', 'секрет', 'секретный ключ',
        'secret key', 'client_secret', 'client secret', 'ваш секретный ключ'
    ];
    private const URL_KEYWORDS = [
        'url', 'адрес', 'ссылка', 'link', 'сервер', 'server', 'host', 'endpoint',
        'панель управления', 'доступ', 'http'
    ];
    private const KEY_KEYWORDS = [
        'key', 'ключ', 'token', 'токен', 'api_key', 'api key', 'apikey',
        'access_token', 'access token', 'secret key', 'gigachat_api_key'
    ];

    // Разделители блоков (между сервисами)
    private const BLOCK_SEPARATORS = [
        '~^\*{5,}~m',           // *****
        '~^={5,}~m',            // =====
        '~^-{5,}~m',            // -----
        '~^#{3,}~m',            // ###
        '~^\/{3,}~m',           // ///
    ];

    /**
     * Парсить текст в структурированные entries
     * @return array{entries: array}
     */
    public function parse(string $text): array {
        // Шаг 1: извлечь общий заголовок (из строк-разделителей)
        $mainTitle = $this->extractMainTitle($text);

        // Шаг 2: разбить на секции по пустым строкам
        $sections = $this->splitIntoSections($text);

        // Шаг 3: каждую секцию парсим в entry
        $entries = [];
        foreach ($sections as $section) {
            $entry = $this->parseBlock($section);
            if (!empty($entry['fields'])) {
                // Если у entry нет имени — подставить главный заголовок + подзаголовок
                if ($entry['service_name'] === 'Без названия' && $mainTitle) {
                    $entry['service_name'] = $mainTitle;
                }
                // Добавить подзаголовок секции к названию если у нас один mainTitle
                $subHeader = $this->extractSubHeader($section);
                if ($subHeader && $mainTitle && $entry['service_name'] === $mainTitle) {
                    $entry['service_name'] = $mainTitle . ' — ' . $subHeader;
                } elseif ($subHeader && $entry['service_name'] === 'Без названия') {
                    $entry['service_name'] = $subHeader;
                }
                $entries[] = $entry;
            }
        }

        // Если ничего не нашли по секциям — весь текст как один блок
        if (empty($entries)) {
            $entry = $this->parseBlock($text);
            if (!empty($entry['fields'])) {
                if ($entry['service_name'] === 'Без названия' && $mainTitle) {
                    $entry['service_name'] = $mainTitle;
                }
                $entries[] = $entry;
            }
        }

        // Слияние: entry с 1 полем (одиночный URL/IP) — присоединить к предыдущему
        $merged = [];
        foreach ($entries as $entry) {
            if (count($entry['fields']) === 1 && !empty($merged)
                && in_array($entry['fields'][0]['type'], ['url', 'text'])
                && $entry['service_name'] === ($mainTitle ?: 'Без названия')) {
                // Добавить поле к предыдущему entry
                $merged[count($merged) - 1]['fields'][] = $entry['fields'][0];
            } else {
                $merged[] = $entry;
            }
        }
        $entries = $merged;

        // Дедупликация имён
        $names = array_column($entries, 'service_name');
        $counts = array_count_values($names);
        $counters = [];
        foreach ($entries as &$e) {
            if ($counts[$e['service_name']] > 1) {
                $counters[$e['service_name']] = ($counters[$e['service_name']] ?? 0) + 1;
                $e['service_name'] .= ' #' . $counters[$e['service_name']];
            }
        }

        return ['entries' => $entries];
    }

    /**
     * Извлечь общий заголовок из строк-разделителей
     * Ищет паттерн: *** ТЕКСТ *** или строку между двумя рядами *****
     */
    private function extractMainTitle(string $text): string {
        // Паттерн: ***** НАЗВАНИЕ *****
        if (preg_match('~[\*=\-#/]{3,}\s*\n\s*(.+?)\s*\n\s*[\*=\-#/]{3,}~u', $text, $m)) {
            return trim($m[1]);
        }
        // Паттерн: ***** НАЗВАНИЕ ******** (в одной строке)
        if (preg_match('~^[\*=\-#/]{3,}\s*(.+?)\s*[\*=\-#/]{3,}\s*$~mu', $text, $m)) {
            $candidate = trim($m[1]);
            if (mb_strlen($candidate) >= 2 && mb_strlen($candidate) <= 80) {
                return $candidate;
            }
        }
        return '';
    }

    /**
     * Извлечь подзаголовок секции (VPS:, Панель управления:, n8n и т.п.)
     */
    private function extractSubHeader(string $section): string {
        $lines = preg_split('~\r?\n~', trim($section));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('~^[\*=\-#/\s]{3,}$~', $line)) continue;

            // Пропустить строки-поля "Ключ: Значение" где значение — реальные данные
            if (preg_match('~^(.+?)\s*:\s*(.+)$~u', $line, $m)) {
                $afterColon = trim($m[2]);
                // Если после : есть URL, IP, email — это не заголовок, а поле
                if (preg_match(self::URL_PATTERN, $afterColon) || preg_match(self::IP_PATTERN, $afterColon)
                    || preg_match(self::EMAIL_PATTERN, $afterColon) || mb_strlen($afterColon) > 3) {
                    continue; // Это поле, не заголовок
                }
            }

            // Строки типа "VPS:", "Доступ по FTP/SSH:", "n8n"
            $clean = rtrim($line, ':');
            if (mb_strlen($clean) <= 40 && !str_contains($line, '=')
                && !preg_match(self::URL_PATTERN, $line)
                && !preg_match(self::EMAIL_PATTERN, $line)
                && !preg_match(self::IP_PATTERN, $line)) {
                if (!$this->looksLikeValue($line) && !$this->isFieldLabel(mb_strtolower($clean))) {
                    return $clean;
                }
            }
            break;
        }
        return '';
    }

    /**
     * Проверить, похожа ли строка на значение (пароль, токен, URL и т.п.)
     */
    private function looksLikeValue(string $s): bool {
        if (preg_match(self::URL_PATTERN, $s)) return true;
        if (preg_match(self::EMAIL_PATTERN, $s)) return true;
        if (preg_match(self::IP_PATTERN, $s)) return true;
        if (preg_match(self::TOKEN_PATTERN, $s)) return true;
        // Спецсимволы, характерные для паролей
        return (bool)preg_match('~[!@#$%^&*]{2,}|^[A-Za-z0-9+/=\-_]{30,}$~', $s);
    }

    /**
     * Разбить текст на секции по пустым строкам
     */
    private function splitIntoSections(string $text): array {
        // Убрать строки-разделители из спецсимволов, но сохранить заголовки внутри них
        $text = preg_replace('~^[\*=\-#/]{5,}\s*$~mu', '', $text);

        // Разбить по одной+ пустой строке
        $sections = preg_split('~\n\s*\n~u', $text);

        return array_filter($sections, fn($s) => mb_strlen(trim($s)) >= 3);
    }

    /**
     * Парсить один блок текста в entry
     */
    private function parseBlock(string $block): array {
        $lines = preg_split('~\r?\n~', $block);
        $lines = array_map('trim', $lines);
        $lines = array_filter($lines, fn($l) => $l !== '');
        $lines = array_values($lines);

        $serviceName = $this->detectServiceName($lines);
        $fields = [];
        $description = '';

        $i = 0;
        $count = count($lines);

        while ($i < $count) {
            $line = $lines[$i];

            // Пропустить строки-разделители
            if (preg_match('~^[\*=\-#/\s]{3,}$~', $line)) {
                $i++;
                continue;
            }

            // Формат "ключ: значение" или "ключ = значение"
            if (preg_match('~^(.+?)\s*[:=]\s*(.+)$~u', $line, $m)) {
                $key = trim($m[1]);
                $value = trim($m[2]);

                // Это заголовок сервиса, не поле
                if ($this->isServiceHeader($key)) {
                    $i++;
                    continue;
                }

                $field = $this->classifyField($key, $value);
                if ($field) {
                    $fields[] = $field;
                }
                $i++;
                continue;
            }

            // Формат: строка-метка, затем значение на следующей строке
            if ($i + 1 < $count) {
                $nextLine = $lines[$i + 1];
                $keyLower = mb_strtolower($line);

                // Текущая строка — метка (без значения), следующая — значение
                if ($this->isFieldLabel($keyLower) && !$this->isFieldLabel(mb_strtolower($nextLine))) {
                    $field = $this->classifyField($line, $nextLine);
                    if ($field) {
                        $fields[] = $field;
                        $i += 2;
                        continue;
                    }
                }
            }

            // Одиночная строка — URL, email, IP или длинный токен
            $singleField = $this->classifySingleValue($line);
            if ($singleField) {
                $fields[] = $singleField;
                $i++;
                continue;
            }

            $i++;
        }

        // Определить категорию по содержимому
        $category = $this->detectCategory($serviceName, $fields, $block);

        // Теги из названия
        $tags = $this->generateTags($serviceName, $category);

        return [
            'service_name' => $serviceName,
            'category'     => $category,
            'description'  => $description,
            'fields'       => $fields,
            'tags'         => $tags,
        ];
    }

    /**
     * Определить название сервиса из блока
     */
    private function detectServiceName(array $lines): string {
        foreach ($lines as $line) {
            // Пропустить разделители
            if (preg_match('~^[\*=\-#/\s]+$~', $line)) continue;

            // Строка в разделителях: *** НАЗВАНИЕ *** или === НАЗВАНИЕ ===
            if (preg_match('~^[\*=#\-/]+\s*(.+?)\s*[\*=#\-/]*$~u', $line, $m)) {
                $name = trim($m[1]);
                if (mb_strlen($name) >= 2 && !$this->isFieldLabel(mb_strtolower($name))) {
                    return $name;
                }
            }

            // Первая непустая короткая строка без ":" — вероятно заголовок
            if (mb_strlen($line) <= 80 && !str_contains($line, ':') && !str_contains($line, '=')
                && !$this->isFieldLabel(mb_strtolower($line))
                && !preg_match(self::URL_PATTERN, $line)
                && !preg_match(self::EMAIL_PATTERN, $line)) {
                return $line;
            }

            break; // Берём только первую подходящую строку
        }

        return 'Без названия';
    }

    /**
     * Классифицировать поле по ключу и значению
     */
    private function classifyField(string $key, string $value): ?array {
        $keyLower = mb_strtolower(trim($key));

        // Убрать мусор из ключа
        $key = preg_replace('~^[\-\*#\s]+~u', '', $key);
        $key = trim($key);
        if ($key === '' || $value === '') return null;

        $type = 'text';

        if ($this->matchesKeywords($keyLower, self::PASSWORD_KEYWORDS)) {
            $type = 'password';
        } elseif ($this->matchesKeywords($keyLower, self::KEY_KEYWORDS)) {
            $type = 'token';
        } elseif ($this->matchesKeywords($keyLower, self::URL_KEYWORDS) || preg_match(self::URL_PATTERN, $value)) {
            $type = 'url';
        } elseif (preg_match(self::EMAIL_PATTERN, $value)) {
            $type = 'email';
        } elseif (preg_match(self::TOKEN_PATTERN, $value)) {
            $type = 'token';
        } elseif ($this->matchesKeywords($keyLower, self::LOGIN_KEYWORDS)) {
            $type = 'text';
        }

        // Определить читабельное имя поля
        $fieldName = $this->normalizeFieldName($key);

        return ['name' => $fieldName, 'value' => $value, 'type' => $type];
    }

    /**
     * Классифицировать одиночное значение без метки
     */
    private function classifySingleValue(string $value): ?array {
        if (preg_match(self::URL_PATTERN, $value)) {
            return ['name' => 'url', 'value' => $value, 'type' => 'url'];
        }
        if (preg_match(self::EMAIL_PATTERN, $value)) {
            return ['name' => 'email', 'value' => $value, 'type' => 'email'];
        }
        if (preg_match(self::IP_PATTERN, $value)) {
            return ['name' => 'server', 'value' => $value, 'type' => 'text'];
        }
        if (preg_match(self::TOKEN_PATTERN, $value)) {
            return ['name' => 'token', 'value' => $value, 'type' => 'token'];
        }
        return null;
    }

    /**
     * Нормализовать имя поля
     */
    private function normalizeFieldName(string $key): string {
        $keyLower = mb_strtolower($key);

        if ($this->matchesKeywords($keyLower, self::PASSWORD_KEYWORDS)) return 'password';
        if ($this->matchesKeywords($keyLower, self::LOGIN_KEYWORDS)) return 'login';
        if ($this->matchesKeywords($keyLower, self::URL_KEYWORDS)) return 'url';
        if ($this->matchesKeywords($keyLower, self::KEY_KEYWORDS)) return 'api_key';

        // Оставить как есть, но обрезать длинные
        $key = mb_substr($key, 0, 80);
        return $key;
    }

    /**
     * Определить категорию по содержимому
     */
    private function detectCategory(string $serviceName, array $fields, string $block): string {
        $text = mb_strtolower($serviceName . ' ' . $block);

        $map = [
            'API Keys'       => ['api', 'openai', 'anthropic', 'claude', 'gigachat', 'gpt'],
            'Banking'        => ['сбер', 'sber', 'альфа', 'alfa', 'банк', 'bank', 'bnpl', 'плати частями'],
            'Cloud Services' => ['aws', 'azure', 'gcloud', 'google cloud', 'yandex cloud', 'облако'],
            'Databases'      => ['postgres', 'mysql', 'mongodb', 'redis', 'database', 'база данных', 'бд'],
            'Email'          => ['smtp', 'imap', 'mail', 'почта', 'email'],
            'Firebase'       => ['firebase', 'fcm', 'firestore'],
            'Hosting'        => ['beget', 'hosting', 'хостинг', 'vps', 'vds', 'сервер', 'cpanel', 'ssh', 'ftp'],
            'Social Media'   => ['vk', 'telegram', 'instagram', 'facebook', 'twitter', 'youtube'],
            'Messengers'     => ['whatsapp', 'viber', 'bot', 'бот'],
            '1С'             => ['1с', '1c', 'рарус', 'unf', 'бухгалтерия'],
            'VPN / Proxy'    => ['vpn', 'proxy', 'прокси'],
            'CRM'            => ['crm', 'bitrix', 'битрикс', 'amocrm', 'amo'],
        ];

        foreach ($map as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($text, $kw) !== false) {
                    return $category;
                }
            }
        }

        return 'Другое';
    }

    /**
     * Сгенерировать теги
     */
    private function generateTags(string $serviceName, string $category): array {
        $tags = [];

        // Первое слово из названия
        $words = preg_split('~[\s\-_/\\\\]+~u', $serviceName);
        foreach ($words as $word) {
            $word = trim($word, ' *=#');
            if (mb_strlen($word) >= 2 && mb_strlen($word) <= 20) {
                $tags[] = mb_strtolower($word);
                if (count($tags) >= 3) break;
            }
        }

        return array_unique($tags);
    }

    private function matchesKeywords(string $text, array $keywords): bool {
        foreach ($keywords as $kw) {
            if (mb_strpos($text, $kw) !== false) {
                return true;
            }
        }
        return false;
    }

    private function isFieldLabel(string $text): bool {
        return $this->matchesKeywords($text, array_merge(
            self::LOGIN_KEYWORDS, self::PASSWORD_KEYWORDS,
            self::URL_KEYWORDS, self::KEY_KEYWORDS
        ));
    }

    private function isServiceHeader(string $text): bool {
        // Строки типа "Панель управления:", "Доступ по FTP/SSH:" — это подзаголовки, не поля
        $headerPatterns = ['панель управления', 'доступ по', 'ссылки для', 'логины и пароли',
                          'имя основного пользователя'];
        $textLower = mb_strtolower($text);
        foreach ($headerPatterns as $p) {
            if (mb_strpos($textLower, $p) !== false) return true;
        }
        return false;
    }
}
