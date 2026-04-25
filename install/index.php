<?php
/**
 * Local Secrets Manager — Установщик
 * Открыть в браузере: http://localhost/local_secrets/install/
 * После установки удалите или переименуйте папку install/
 */
session_start();

// Версия — единый источник для конфига и UI
const INSTALL_APP_VERSION = '1.1.0';

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// ======================== ШАГ 2: Проверка требований ========================
function checkRequirements(): array {
    $checks = [];

    // PHP версия
    $checks[] = [
        'name' => 'PHP >= 8.0',
        'ok' => version_compare(PHP_VERSION, '8.0.0', '>='),
        'value' => PHP_VERSION,
    ];

    // Расширения
    foreach (['pdo', 'pdo_mysql', 'openssl', 'mbstring', 'json', 'curl'] as $ext) {
        $checks[] = [
            'name' => "ext-{$ext}",
            'ok' => extension_loaded($ext),
            'value' => extension_loaded($ext) ? 'Установлен' : 'Отсутствует',
        ];
    }

    // Запись в logs/
    $logsDir = dirname(__DIR__) . '/logs';
    $logsWritable = is_dir($logsDir) ? is_writable($logsDir) : is_writable(dirname(__DIR__));
    $checks[] = [
        'name' => 'Папка logs/ (запись)',
        'ok' => $logsWritable,
        'value' => $logsWritable ? 'OK' : 'Нет доступа на запись',
    ];

    // config.php существует
    $configExists = file_exists(dirname(__DIR__) . '/config.php');
    $checks[] = [
        'name' => 'config.php',
        'ok' => $configExists,
        'value' => $configExists ? 'Найден' : 'Отсутствует',
    ];

    return $checks;
}

// ======================== ШАГ 3: Установка БД ========================
function installDatabase(string $host, int $port, string $user, string $pass, string $dbName): string {
    try {
        // Подключение без выбора БД
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);

        // Создать БД
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbName}`");

        // Проверить, не установлена ли уже
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('secrets', $tables)) {
            return 'БД уже содержит таблицы. Если хотите переустановить — сначала удалите БД вручную.';
        }

        // Выполнить schema.sql
        $schemaPath = __DIR__ . '/schema.sql';
        if (!file_exists($schemaPath)) {
            return 'Файл schema.sql не найден в папке install/';
        }

        $sql = file_get_contents($schemaPath);

        // Убрать строки-комментарии, чтобы не обрезать следующий за ними оператор
        $sql = preg_replace('/^--[^\n]*/m', '', $sql);

        // Разбить на отдельные запросы (по ;)
        $statements = array_filter(
            array_map('trim', preg_split('/;\s*(?:\n|$)/', $sql)),
            fn($s) => $s !== ''
        );

        foreach ($statements as $stmt) {
            // Пропустить CREATE DATABASE и USE — мы уже подключены
            if (preg_match('/^(CREATE DATABASE|USE )/i', $stmt)) continue;
            $pdo->exec($stmt);
        }

        return '';
    } catch (PDOException $e) {
        return 'Ошибка MySQL: ' . $e->getMessage();
    }
}

// ======================== ШАГ 4: Генерация ключа и сохранение конфига ========================
function generateEncryptionKey(): string {
    return bin2hex(random_bytes(32));
}

function saveConfig(string $host, int $port, string $user, string $pass, string $dbName, string $encKey): string {
    $configPath = dirname(__DIR__) . '/config.php';

    $config = <<<'PHP'
<?php
/**
 * Local Secrets Manager — Конфигурация
 * ВНИМАНИЕ: ENCRYPTION_KEY — единственный ключ шифрования.
 * При утере все зашифрованные данные будут потеряны!
 * Сделайте резервную копию этого файла!
 */

define('APP_NAME', 'Local Secrets Manager');
define('APP_VERSION', '%APP_VERSION%');
define('APP_ROOT', __DIR__);
define('LOG_DIR', APP_ROOT . '/logs');

// База данных
define('DB_HOST', '%DB_HOST%');
define('DB_PORT', %DB_PORT%);
define('DB_NAME', '%DB_NAME%');
define('DB_USER', '%DB_USER%');
define('DB_PASS', '%DB_PASS%');

// Шифрование AES-256-CBC (64 hex = 32 байта) — НЕ ТЕРЯЙТЕ ЭТОТ КЛЮЧ!
define('ENCRYPTION_KEY', '%ENC_KEY%');

// Отображение
define('PER_PAGE', 20);

// Сессия
define('SESSION_TIMEOUT', 1800);
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
PHP;

    $config = str_replace(
        ['%APP_VERSION%', '%DB_HOST%', '%DB_PORT%', '%DB_NAME%', '%DB_USER%', '%DB_PASS%', '%ENC_KEY%'],
        [INSTALL_APP_VERSION, $host, $port, $dbName, $user, $pass, $encKey],
        $config
    );

    if (file_put_contents($configPath, $config) === false) {
        return 'Не удалось записать config.php. Проверьте права доступа.';
    }
    return '';
}

// ======================== Обработка POST ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'install') {
        $host   = trim($_POST['db_host'] ?? 'localhost');
        $port   = (int)($_POST['db_port'] ?? 3306);
        $user   = trim($_POST['db_user'] ?? 'root');
        $pass   = $_POST['db_pass'] ?? '';
        $dbName = trim($_POST['db_name'] ?? 'local_secrets');
        $encKey = trim($_POST['enc_key'] ?? '') ?: generateEncryptionKey();

        // 1) Установить БД
        $dbError = installDatabase($host, $port, $user, $pass, $dbName);
        if ($dbError) {
            $error = $dbError;
            $step = 3;
        } else {
            // 2) Сохранить конфиг
            $cfgError = saveConfig($host, $port, $user, $pass, $dbName, $encKey);
            if ($cfgError) {
                $error = $cfgError;
                $step = 3;
            } else {
                // 3) Создать logs/
                $logsDir = dirname(__DIR__) . '/logs';
                if (!is_dir($logsDir)) @mkdir($logsDir, 0755, true);

                $_SESSION['install_enc_key'] = $encKey;
                $step = 4;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка — Local Secrets Manager</title>
    <link rel="icon" type="image/svg+xml" href="/local_secrets/assets/favicon.svg">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="/local_secrets/assets/css/app.css" rel="stylesheet">
    <style>
        .install-wrap { min-height: 100vh; background: var(--am-bg-2); padding: 40px 20px; }
        .install-card { max-width: 720px; margin: 0 auto; }
        .install-steps { display: flex; justify-content: center; gap: 18px; margin-bottom: 28px; }
        .install-step { text-align: center; min-width: 80px; }
        .install-step-badge {
            width: 32px; height: 32px;
            display: inline-flex; align-items: center; justify-content: center;
            border-radius: 50%;
            font-weight: 600; font-size: 13px;
            font-variant-numeric: tabular-nums;
            transition: background .15s, color .15s;
        }
        .install-step-badge.is-active  { background: var(--am-blue);  color: #fff; }
        .install-step-badge.is-done    { background: var(--am-green); color: #fff; }
        .install-step-badge.is-pending { background: var(--am-bg-3);  color: var(--am-text-3); border: 1px solid var(--am-line-2); }
        .install-step-label { font-size: 11px; color: var(--am-text-3); margin-top: 6px; letter-spacing: 0.04em; text-transform: uppercase; }
        .install-step.is-active .install-step-label { color: var(--am-text-1); }
        .install-check {
            display: flex; align-items: center; gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--am-line);
        }
        .install-check:last-child { border-bottom: none; }
        .install-check-name { flex: 1; font-size: 14px; }
        .install-check-value { font-size: 12px; color: var(--am-text-3); }
        .install-key {
            display: block;
            padding: 12px 14px;
            background: var(--am-bg-3);
            border: 1px solid var(--am-line);
            border-radius: 8px;
            font-family: "SF Mono", ui-monospace, Menlo, Consolas, monospace;
            font-size: 12px;
            word-break: break-all;
            user-select: all;
            color: var(--am-text-1);
            margin-top: 8px;
        }
        .install-list { padding-left: 20px; line-height: 1.7; color: var(--am-text-2); margin: 8px 0 0; }
        .install-list li { margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="install-wrap">
        <div class="install-card">
            <!-- Заголовок -->
            <div class="am-text-center am-mb-4">
                <div class="am-flex am-items-center am-justify-center am-gap-2 am-mb-2">
                    <img src="/local_secrets/assets/favicon.svg" alt="" style="width: 36px; height: 36px; border-radius: 8px;">
                    <h1 class="am-h2 am-mb-0">Local Secrets Manager</h1>
                </div>
                <p class="am-muted am-mb-0">Мастер установки</p>
            </div>

            <!-- Шаги -->
            <div class="install-steps">
                <?php
                $steps = ['Приветствие', 'Требования', 'Настройка', 'Готово'];
                foreach ($steps as $i => $name):
                    $num = $i + 1;
                    $cls = $num < $step ? 'is-done' : ($num === $step ? 'is-active' : 'is-pending');
                    $stepCls = $num === $step ? 'is-active' : '';
                    $icon = $num < $step ? '<i class="fas fa-check"></i>' : $num;
                ?>
                    <div class="install-step <?= $stepCls ?>">
                        <span class="install-step-badge <?= $cls ?>"><?= $icon ?></span>
                        <div class="install-step-label"><?= $name ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="am-card">

                <?php if ($error): ?>
                    <div class="am-alert am-alert-danger">
                        <i class="fas fa-circle-exclamation"></i>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($step === 1): ?>
                    <!-- ШАГ 1: Приветствие -->
                    <h2 class="am-h2 am-mb-3">Добро пожаловать</h2>
                    <p class="am-lede am-mb-3">
                        Этот мастер установит <strong>Local Secrets Manager</strong> —
                        локальное хранилище паролей, ключей и учётных данных с AES-256 шифрованием.
                    </p>

                    <h3 class="am-h3 am-mt-4">Что будет установлено</h3>
                    <ul class="install-list">
                        <li>База данных MySQL с 16 категориями и шаблонами полей</li>
                        <li>Конфигурационный файл с уникальным ключом шифрования</li>
                        <li>PIN-код доступа (установите при первом входе)</li>
                    </ul>

                    <h3 class="am-h3 am-mt-4">Требования</h3>
                    <ul class="install-list">
                        <li>PHP 8.0+ с расширениями: PDO, pdo_mysql, openssl, mbstring, curl</li>
                        <li>MySQL 8.0+ или MariaDB 10.4+</li>
                        <li>Веб-сервер Apache (XAMPP, OpenServer, WAMP и т.д.)</li>
                    </ul>

                    <div class="am-flex am-justify-end am-mt-4">
                        <a href="?step=2" class="am-btn am-btn-primary">
                            Далее <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                <?php elseif ($step === 2): ?>
                    <!-- ШАГ 2: Проверка требований -->
                    <h2 class="am-h2 am-mb-3">Проверка системных требований</h2>
                    <?php
                    $checks = checkRequirements();
                    $allOk = true;
                    foreach ($checks as $check):
                        if (!$check['ok']) $allOk = false;
                    ?>
                        <div class="install-check">
                            <?php if ($check['ok']): ?>
                                <i class="fas fa-circle-check am-text-success"></i>
                            <?php else: ?>
                                <i class="fas fa-circle-xmark am-text-danger"></i>
                            <?php endif; ?>
                            <span class="install-check-name"><?= $check['name'] ?></span>
                            <span class="install-check-value"><?= htmlspecialchars($check['value']) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="am-flex am-justify-between am-mt-4">
                        <a href="?step=1" class="am-btn am-btn-ghost">
                            <i class="fas fa-arrow-left"></i> Назад
                        </a>
                        <?php if ($allOk): ?>
                            <a href="?step=3" class="am-btn am-btn-primary">
                                Далее <i class="fas fa-arrow-right"></i>
                            </a>
                        <?php else: ?>
                            <button class="am-btn am-btn-danger" disabled>Исправьте ошибки</button>
                        <?php endif; ?>
                    </div>

                <?php elseif ($step === 3): ?>
                    <!-- ШАГ 3: Настройка БД -->
                    <h2 class="am-h2 am-mb-3">Подключение к базе данных</h2>
                    <form method="POST">
                        <input type="hidden" name="action" value="install">

                        <div class="am-field-row cols-3" style="grid-template-columns: 2fr 1fr;">
                            <div class="am-field">
                                <label class="am-label">Хост MySQL</label>
                                <input type="text" name="db_host" class="am-input"
                                       value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                            </div>
                            <div class="am-field">
                                <label class="am-label">Порт</label>
                                <input type="number" name="db_port" class="am-input"
                                       value="<?= (int)($_POST['db_port'] ?? 3306) ?>">
                            </div>
                        </div>

                        <div class="am-field-row cols-2">
                            <div class="am-field">
                                <label class="am-label">Пользователь</label>
                                <input type="text" name="db_user" class="am-input"
                                       value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>">
                            </div>
                            <div class="am-field">
                                <label class="am-label">Пароль</label>
                                <input type="password" name="db_pass" class="am-input" value="">
                            </div>
                        </div>

                        <div class="am-field">
                            <label class="am-label">Имя базы данных</label>
                            <input type="text" name="db_name" class="am-input"
                                   value="<?= htmlspecialchars($_POST['db_name'] ?? 'local_secrets') ?>">
                        </div>

                        <div class="am-field">
                            <label class="am-label">
                                Ключ шифрования
                                <span class="am-muted">(оставьте пустым для авто-генерации)</span>
                            </label>
                            <input type="text" name="enc_key" class="am-input am-input-mono"
                                   placeholder="Будет сгенерирован автоматически (64 hex символа)"
                                   value="<?= htmlspecialchars($_POST['enc_key'] ?? '') ?>">
                            <div class="am-help am-text-warning">
                                <i class="fas fa-triangle-exclamation"></i>
                                Сохраните ключ шифрования! При его утере все данные будут потеряны.
                            </div>
                        </div>

                        <div class="am-flex am-justify-between am-mt-4">
                            <a href="?step=2" class="am-btn am-btn-ghost">
                                <i class="fas fa-arrow-left"></i> Назад
                            </a>
                            <button type="submit" class="am-btn am-btn-primary">
                                <i class="fas fa-download"></i> Установить
                            </button>
                        </div>
                    </form>

                <?php elseif ($step === 4): ?>
                    <!-- ШАГ 4: Готово -->
                    <div class="am-text-center am-mb-3">
                        <i class="fas fa-circle-check am-text-success" style="font-size: 48px;"></i>
                        <h2 class="am-h2 am-mt-3 am-mb-0">Установка завершена</h2>
                    </div>

                    <?php if (!empty($_SESSION['install_enc_key'])): ?>
                        <div class="am-alert am-alert-warning">
                            <i class="fas fa-key"></i>
                            <span class="am-flex-1">
                                <strong>Ваш ключ шифрования</strong> — сохраните его в надёжном месте!
                                Без него невозможно расшифровать данные.
                            </span>
                        </div>
                        <code class="install-key">
                            <?= htmlspecialchars($_SESSION['install_enc_key']) ?>
                        </code>
                        <?php unset($_SESSION['install_enc_key']); ?>
                    <?php endif; ?>

                    <h3 class="am-h3 am-mt-4">Что дальше</h3>
                    <ol class="install-list">
                        <li>Перейдите на <a href="/local_secrets/">главную страницу</a></li>
                        <li>Установите PIN-код (4–6 цифр) при первом входе</li>
                        <li><strong class="am-text-danger">Удалите папку <code>install/</code></strong> из проекта для безопасности</li>
                    </ol>

                    <div class="am-text-center am-mt-4">
                        <a href="/local_secrets/" class="am-btn am-btn-primary">
                            <i class="fas fa-rocket"></i> Начать работу
                        </a>
                    </div>

                <?php endif; ?>

            </div>

            <p class="am-text-center am-text-xs am-muted am-mt-3">Local Secrets Manager v<?= INSTALL_APP_VERSION ?></p>
        </div>
    </div>
</body>
</html>
