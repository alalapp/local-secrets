<?php
/**
 * Local Secrets Manager — Bootstrap
 * Автозагрузка, сессия, безопасность
 */

require_once __DIR__ . '/config.php';

session_start();

// Автозагрузка классов
spl_autoload_register(function ($className) {
    $file = __DIR__ . '/classes/' . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Директория логов
if (!file_exists(LOG_DIR)) {
    mkdir(LOG_DIR, 0755, true);
}

// Настройки PHP
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', 'On');
ini_set('error_log', LOG_DIR . '/php_errors.log');
ini_set('default_charset', 'UTF-8');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Moscow');

// Только localhost (пропускаем для CLI)
if (php_sapi_name() !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'])) {
    http_response_code(403);
    die('Доступ только с localhost');
}

// CSRF-токен
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Проверка CSRF-токена
 */
function verify_csrf(): bool {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

/**
 * JSON-ответ
 */
function json_response(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
