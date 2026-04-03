<?php
/**
 * Local Secrets Manager — Конфигурация (ШАБЛОН)
 *
 * Скопируйте этот файл в config.php и заполните значения:
 *   copy config.example.php config.php
 *
 * Либо запустите установщик: http://localhost/local_secrets/install/
 */

define('APP_NAME', 'Local Secrets Manager');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);
define('LOG_DIR', APP_ROOT . '/logs');

// База данных
define('DB_HOST', 'localhost');
define('DB_NAME', 'local_secrets');
define('DB_USER', 'root');
define('DB_PASS', '');

// Шифрование AES-256-CBC (64 hex символа = 32 байта)
// Сгенерируйте свой ключ: php -r "echo bin2hex(random_bytes(32));"
// ⚠️ НЕ ТЕРЯЙТЕ ЭТОТ КЛЮЧ — без него данные не расшифровать!
define('ENCRYPTION_KEY', 'СГЕНЕРИРУЙТЕ_СВОЙ_КЛЮЧ_64_HEX_СИМВОЛА');

// Отображение
define('PER_PAGE', 10);

// Сессия
define('SESSION_TIMEOUT', 1800); // 30 минут бездействия
define('PIN_MIN_LENGTH', 4);
define('PIN_MAX_LENGTH', 6);
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_MINUTES', 15);

// LM Studio (OpenAI-совместимый API)
define('LLM_API_URL', 'http://localhost:1234/v1/chat/completions');
define('LLM_MODEL', 'qwen/qwen3-vl-4b');
define('LLM_TIMEOUT', 180);
define('LLM_MAX_TOKENS', 4000);
define('LLM_TEMPERATURE', 0.1);
