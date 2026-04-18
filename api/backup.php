<?php
/**
 * API резервного копирования — экспорт/импорт БД и настроек
 */
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

if (!verify_csrf()) {
    http_response_code(403);
    die('CSRF validation failed');
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'export_sql':
        handleExportSql();
        break;
    case 'export_settings':
        handleExportSettings();
        break;
    case 'import_sql':
        handleImportSql();
        break;
    case 'import_settings':
        handleImportSettings();
        break;
    default:
        http_response_code(400);
        echo 'Unknown action';
}

// ---------------------------------------------------------------------------

function handleExportSql(): void {
    $db = Database::getInstance();

    $tables = [
        'categories',
        'category_field_templates',
        'tags',
        'secrets',
        'secret_fields',
        'secret_tags',
        'auth_pin',
        'activity_log',
    ];

    $lines = [];
    $lines[] = '-- Local Secrets Manager — Дамп базы данных';
    $lines[] = '-- Создан: ' . date('Y-m-d H:i:s');
    $lines[] = '-- Версия: ' . APP_VERSION;
    $lines[] = '';
    $lines[] = 'SET NAMES utf8mb4;';
    $lines[] = 'SET FOREIGN_KEY_CHECKS = 0;';
    $lines[] = '';

    foreach ($tables as $table) {
        $exists = $db->fetchOne("SHOW TABLES LIKE '{$table}'");
        if (!$exists) {
            continue;
        }

        $createRow = $db->fetchOne("SHOW CREATE TABLE `{$table}`");
        $createSql  = $createRow['Create Table'] ?? '';

        $lines[] = "-- Таблица: `{$table}`";
        $lines[] = "DROP TABLE IF EXISTS `{$table}`;";
        $lines[] = $createSql . ';';
        $lines[] = '';

        $rows = $db->fetchAll("SELECT * FROM `{$table}`");
        if (!empty($rows)) {
            $cols = '`' . implode('`, `', array_keys($rows[0])) . '`';
            $valueLines = [];
            foreach ($rows as $row) {
                $vals = array_map(
                    fn($v) => $v === null
                        ? 'NULL'
                        : "'" . str_replace(
                            ['\\',  "'",   "\n",  "\r",  "\0"],
                            ['\\\\', "\\'", '\\n', '\\r', '\\0'],
                            (string)$v
                        ) . "'",
                    array_values($row)
                );
                $valueLines[] = '(' . implode(', ', $vals) . ')';
            }
            $last = count($valueLines) - 1;
            $lines[] = "INSERT INTO `{$table}` ({$cols}) VALUES";
            foreach ($valueLines as $i => $vl) {
                $lines[] = $vl . ($i < $last ? ',' : ';');
            }
            $lines[] = '';
        }
    }

    $lines[] = 'SET FOREIGN_KEY_CHECKS = 1;';

    $sql      = implode("\n", $lines);
    $filename = 'local_secrets_db_' . date('Y-m-d_H-i-s') . '.sql';

    Logger::log('export', 'backup', 0, 'Экспорт базы данных SQL');

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($sql));
    header('Cache-Control: no-cache, no-store');
    echo $sql;
    exit;
}

// ---------------------------------------------------------------------------

function handleExportSettings(): void {
    $settings = [
        '_meta' => [
            'app'         => APP_NAME,
            'version'     => APP_VERSION,
            'exported_at' => date('c'),
            'note'        => 'Ключ шифрования и параметры БД не включены в этот файл',
        ],
        'display' => [
            'PER_PAGE' => PER_PAGE,
        ],
        'session' => [
            'SESSION_TIMEOUT'    => SESSION_TIMEOUT,
            'PIN_MIN_LENGTH'     => PIN_MIN_LENGTH,
            'PIN_MAX_LENGTH'     => PIN_MAX_LENGTH,
            'MAX_LOGIN_ATTEMPTS' => MAX_LOGIN_ATTEMPTS,
            'LOCKOUT_MINUTES'    => LOCKOUT_MINUTES,
        ],
        'llm' => [
            'LLM_API_URL'     => LLM_API_URL,
            'LLM_MODEL'       => LLM_MODEL,
            'LLM_TIMEOUT'     => LLM_TIMEOUT,
            'LLM_MAX_TOKENS'  => LLM_MAX_TOKENS,
            'LLM_TEMPERATURE' => LLM_TEMPERATURE,
        ],
    ];

    $json     = json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $filename = 'local_secrets_settings_' . date('Y-m-d_H-i-s') . '.json';

    Logger::log('export', 'backup', 0, 'Экспорт настроек JSON');

    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($json));
    header('Cache-Control: no-cache, no-store');
    echo $json;
    exit;
}

// ---------------------------------------------------------------------------

function handleImportSql(): void {
    $file = $_FILES['sql_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        redirectBack('import_error', 'Файл не выбран или ошибка загрузки');
        return;
    }

    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'sql') {
        redirectBack('import_error', 'Допустимы только .sql файлы');
        return;
    }

    $sql = file_get_contents($file['tmp_name']);
    if ($sql === false) {
        redirectBack('import_error', 'Не удалось прочитать файл');
        return;
    }

    // Базовая проверка что это наш дамп
    if (
        strpos($sql, 'Local Secrets') === false &&
        strpos($sql, 'FOREIGN_KEY_CHECKS') === false
    ) {
        redirectBack('import_error', 'Файл не похож на резервную копию Local Secrets');
        return;
    }

    $statements = parseSqlStatements($sql);

    $db = Database::getInstance();
    try {
        $executed = 0;
        foreach ($statements as $stmt) {
            // Пропускаем CREATE DATABASE и USE
            if (preg_match('/^(CREATE\s+DATABASE|USE\s+)/i', $stmt)) {
                continue;
            }
            $db->execute($stmt);
            $executed++;
        }

        Logger::log('import', 'backup', 0, "Импорт БД: {$file['name']}, запросов: {$executed}");
        redirectBack('import_success', "Импорт завершён. Выполнено запросов: {$executed}");

    } catch (Exception $e) {
        Logger::log('error', 'backup', 0, 'Ошибка импорта SQL: ' . $e->getMessage());
        redirectBack('import_error', 'Ошибка импорта: ' . htmlspecialchars($e->getMessage()));
    }
}

// ---------------------------------------------------------------------------

function handleImportSettings(): void {
    $file = $_FILES['settings_file'] ?? null;

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        redirectBack('import_error', 'Файл не выбран или ошибка загрузки');
        return;
    }

    if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'json') {
        redirectBack('import_error', 'Допустимы только .json файлы');
        return;
    }

    $json = file_get_contents($file['tmp_name']);
    $data = json_decode($json, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['_meta'])) {
        redirectBack('import_error', 'Неверный формат файла настроек');
        return;
    }

    $defines = [];

    $intMap = [
        'display.PER_PAGE'              => 'PER_PAGE',
        'session.SESSION_TIMEOUT'       => 'SESSION_TIMEOUT',
        'session.PIN_MIN_LENGTH'        => 'PIN_MIN_LENGTH',
        'session.PIN_MAX_LENGTH'        => 'PIN_MAX_LENGTH',
        'session.MAX_LOGIN_ATTEMPTS'    => 'MAX_LOGIN_ATTEMPTS',
        'session.LOCKOUT_MINUTES'       => 'LOCKOUT_MINUTES',
        'llm.LLM_TIMEOUT'              => 'LLM_TIMEOUT',
        'llm.LLM_MAX_TOKENS'           => 'LLM_MAX_TOKENS',
    ];

    foreach ($intMap as $path => $constName) {
        [$section, $key] = explode('.', $path);
        if (isset($data[$section][$key])) {
            $defines[$constName] = (int)$data[$section][$key];
        }
    }

    $strMap = [
        'llm.LLM_API_URL' => 'LLM_API_URL',
        'llm.LLM_MODEL'   => 'LLM_MODEL',
    ];

    foreach ($strMap as $path => $constName) {
        [$section, $key] = explode('.', $path);
        if (isset($data[$section][$key])) {
            $defines[$constName] = (string)$data[$section][$key];
        }
    }

    if (isset($data['llm']['LLM_TEMPERATURE'])) {
        $defines['LLM_TEMPERATURE'] = (float)$data['llm']['LLM_TEMPERATURE'];
    }

    $configPath = APP_ROOT . '/config.php';
    $content    = file_get_contents($configPath);

    foreach ($defines as $key => $value) {
        $exported = is_string($value) ? var_export($value, true) : $value;
        $newLine  = "define('{$key}', {$exported});";
        $pattern  = "/(?:if\s*\(!defined\('{$key}'\)\)\s*)?define\('{$key}',.+?\);/";
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, $newLine, $content);
        }
    }

    file_put_contents($configPath, $content);

    Logger::log('import', 'backup', 0, "Импорт настроек: {$file['name']}");
    redirectBack('import_success', 'Настройки импортированы. Перезагрузите страницу для применения.');
}

// ---------------------------------------------------------------------------

/**
 * Разбивает SQL-дамп на отдельные выражения, корректно обрабатывая строки.
 * @return string[]
 */
function parseSqlStatements(string $sql): array {
    $statements = [];
    $current    = '';
    $inString   = false;
    $strChar    = '';
    $len        = strlen($sql);

    for ($i = 0; $i < $len; $i++) {
        $ch = $sql[$i];

        // Обработка экранирования внутри строки
        if ($inString && $ch === '\\') {
            $current .= $ch . ($sql[$i + 1] ?? '');
            $i++;
            continue;
        }

        // Открытие/закрытие строки
        if ($ch === "'" || $ch === '"') {
            if ($inString && $strChar === $ch) {
                $inString = false;
            } elseif (!$inString) {
                $inString = true;
                $strChar  = $ch;
            }
        }

        // Разделитель выражений
        if ($ch === ';' && !$inString) {
            $stmt = trim($current);
            // Пропускаем пустые и комментарии
            $noComments = preg_replace('/^--[^\n]*$/m', '', $stmt);
            if (trim($noComments) !== '') {
                $statements[] = $stmt;
            }
            $current = '';
            continue;
        }

        $current .= $ch;
    }

    $last = trim($current);
    if ($last !== '') {
        $statements[] = $last;
    }

    return $statements;
}

// ---------------------------------------------------------------------------

function redirectBack(string $param, string $message): void {
    header('Location: /local_secrets/pages/backup.php?' . $param . '=' . urlencode($message));
    exit;
}
