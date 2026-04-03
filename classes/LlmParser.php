<?php
/**
 * Интеграция с LM Studio для парсинга неструктурированных данных
 * LM Studio предоставляет OpenAI-совместимый API на localhost:1234
 */
class LlmParser {

    /**
     * Парсить неструктурированный текст с учётными данными
     * @return array{entries: array, error?: string}
     */
    public function parseCredentials(string $rawText, array $categories = []): array {
        $systemPrompt = $this->buildSystemPrompt($categories);

        try {
            $response = $this->callLlm($systemPrompt, $rawText);
            return $this->parseResponse($response);
        } catch (Throwable $e) {
            Logger::error("LLM парсинг ошибка: " . $e->getMessage());
            return ['entries' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Проверить доступность LM Studio
     */
    public function isAvailable(): bool {
        $info = $this->getServerInfo();
        return $info['available'];
    }

    /**
     * Получить информацию о сервере и загруженных моделях
     * @return array{available: bool, models: string[], error?: string}
     */
    public function getServerInfo(): array {
        try {
            $url = str_replace('/chat/completions', '/models', LLM_API_URL);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            $result = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error || $code !== 200) {
                return ['available' => false, 'models' => [], 'error' => $error ?: "HTTP {$code}"];
            }

            $data = json_decode($result, true);
            $models = [];
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $m) {
                    $models[] = $m['id'] ?? 'unknown';
                }
            }

            return ['available' => true, 'models' => $models];
        } catch (Throwable $e) {
            return ['available' => false, 'models' => [], 'error' => $e->getMessage()];
        }
    }

    private function buildSystemPrompt(array $categories): string {
        $categoryList = !empty($categories)
            ? implode(', ', array_column($categories, 'name'))
            : 'API Keys, Banking, Cloud Services, Databases, Email, Firebase, Hosting, Social Media, 1С, Messengers, VPN / Proxy, CRM, Другое';

        // Сформировать описание шаблонов полей для каждой категории
        $templateDesc = '';
        if (!empty($categories)) {
            $lines = [];
            foreach ($categories as $cat) {
                if (!empty($cat['field_templates'])) {
                    $fieldNames = array_map(fn($t) => $t['field_name'], $cat['field_templates']);
                    $lines[] = "- {$cat['name']}: " . implode(', ', $fieldNames);
                }
            }
            if ($lines) {
                $templateDesc = "\n\nШаблоны полей по категориям (используй эти названия полей при совпадении категории):\n" . implode("\n", $lines);
            }
        }

        return <<<PROMPT
Ты — парсер учётных данных. Пользователь вставляет неструктурированный текст, содержащий логины, пароли, API-ключи, URL и другие секретные данные для РАЗНЫХ сервисов.

Твоя задача — извлечь ВСЕ сервисы из текста и вернуть JSON-массив.

Формат ответа (ТОЛЬКО JSON, без markdown, без пояснений):
{
  "entries": [
    {
      "service_name": "Название сервиса (например: OpenAI, Firebase, Sberbank)",
      "category": "Одна из категорий",
      "description": "Краткое описание или заметки из текста (если есть)",
      "fields": [
        {"name": "login", "value": "значение", "type": "text"},
        {"name": "password", "value": "значение", "type": "password"},
        {"name": "api_key", "value": "значение", "type": "token"},
        {"name": "url", "value": "https://...", "type": "url"}
      ],
      "tags": ["тег1", "тег2"]
    }
  ]
}

Правила:
1. Каждый отдельный сервис/система — отдельный элемент в массиве entries
2. Каждое найденное значение внутри сервиса — отдельный элемент в массиве fields
3. Тип поля определяй по содержимому: URL → "url", email → "email", пароль → "password", ключи/токены → "token", заметки → "note", остальное → "text"
4. Название поля (name) — используй названия из шаблона категории, если категория совпадает. Для полей, не описанных в шаблоне — придумай понятное название на русском или английском
5. Если категория не подходит ни под одну — используй "Другое"
6. Теги — короткие ключевые слова, описывающие сервис
7. Если текст не содержит учётных данных — верни {"entries": [], "error": "Не найдены учётные данные"}
8. Разделяй текст по сервисам — каждый блок с учётными данными = отдельный entry

Доступные категории: {$categoryList}{$templateDesc}
PROMPT;
    }

    private function callLlm(string $system, string $user): string {
        $payload = [
            'model'       => LLM_MODEL,
            'messages'    => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature' => LLM_TEMPERATURE,
            'max_tokens'  => LLM_MAX_TOKENS,
        ];

        $ch = curl_init(LLM_API_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => LLM_TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
            ],
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new RuntimeException("LM Studio недоступен: {$error}");
        }
        if ($httpCode !== 200) {
            throw new RuntimeException("LM Studio вернул HTTP {$httpCode}: {$result}");
        }

        $data = json_decode($result, true);
        $content = $data['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new RuntimeException("Пустой ответ от LLM");
        }

        return $content;
    }

    private function parseResponse(string $response): array {
        // Убрать markdown-обёртку если есть
        $response = preg_replace('/^```(?:json)?\s*/i', '', $response);
        $response = preg_replace('/\s*```$/i', '', $response);
        $response = trim($response);

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Попытка восстановить обрезанный JSON — взять завершённые entries
            $data = $this->tryRepairJson($response);
            if ($data === null) {
                throw new RuntimeException("LLM вернул невалидный JSON: " . json_last_error_msg());
            }
            Logger::file("LLM JSON обрезан, восстановлено entries: " . count($data['entries'] ?? []));
        }

        // Если LLM вернул одиночный объект вместо массива
        if (isset($data['service_name'])) {
            $data = ['entries' => [$data]];
        }

        if (!isset($data['entries']) || !is_array($data['entries'])) {
            throw new RuntimeException("Неверный формат ответа LLM: отсутствует entries");
        }

        // Валидация каждого entry
        foreach ($data['entries'] as &$entry) {
            $entry['service_name'] = $entry['service_name'] ?? 'Без названия';
            $entry['category'] = $entry['category'] ?? 'Другое';
            $entry['description'] = $entry['description'] ?? '';
            $entry['fields'] = $entry['fields'] ?? [];
            $entry['tags'] = $entry['tags'] ?? [];

            // Валидация полей
            $entry['fields'] = array_filter($entry['fields'], function($f) {
                return !empty($f['name']) && isset($f['value']) && $f['value'] !== '';
            });
            $entry['fields'] = array_values($entry['fields']);
        }

        return $data;
    }

    /**
     * Попытка восстановить обрезанный JSON — извлечь завершённые entries
     */
    private function tryRepairJson(string $json): ?array {
        // Ищем все завершённые объекты entry через regex
        // Паттерн: {..., "service_name": "...", ... "tags": [...]}
        $entries = [];
        $offset = 0;

        // Найти начало массива entries
        $entriesStart = strpos($json, '"entries"');
        if ($entriesStart === false) {
            return null;
        }

        // Извлечь каждый завершённый объект {...} внутри entries
        $bracketStart = strpos($json, '[', $entriesStart);
        if ($bracketStart === false) {
            return null;
        }

        $pos = $bracketStart + 1;
        $len = strlen($json);

        while ($pos < $len) {
            // Найти начало объекта
            $objStart = strpos($json, '{', $pos);
            if ($objStart === false) break;

            // Найти соответствующую закрывающую }
            $depth = 0;
            $objEnd = null;
            for ($i = $objStart; $i < $len; $i++) {
                if ($json[$i] === '{') $depth++;
                if ($json[$i] === '}') $depth--;
                if ($depth === 0) {
                    $objEnd = $i;
                    break;
                }
            }

            if ($objEnd === null) break; // Объект обрезан — пропускаем

            $objJson = substr($json, $objStart, $objEnd - $objStart + 1);
            $obj = json_decode($objJson, true);
            if ($obj !== null && isset($obj['service_name'])) {
                $entries[] = $obj;
            }

            $pos = $objEnd + 1;
        }

        if (empty($entries)) {
            return null;
        }

        return ['entries' => $entries];
    }
}
