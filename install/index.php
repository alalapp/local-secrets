<?php
/**
 * Local Secrets Manager — Установщик
 * Открыть в браузере: http://localhost/local_secrets/install/
 * После установки удалите или переименуйте папку install/
 */
session_start();

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
define('APP_VERSION', '1.0.0');
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
        ['%DB_HOST%', '%DB_PORT%', '%DB_NAME%', '%DB_USER%', '%DB_PASS%', '%ENC_KEY%'],
        [$host, $port, $dbName, $user, $pass, $encKey],
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
<html lang="ru" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Установка — Local Secrets Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #0f0c29, #302b63, #24243e); min-height: 100vh; }
        .install-card { max-width: 700px; margin: 40px auto; }
        .step-badge { width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; font-weight: bold; font-size: 0.85rem; }
        .step-active { background-color: #0d6efd; color: white; }
        .step-done { background-color: #198754; color: white; }
        .step-pending { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <div class="install-card">
        <!-- Заголовок -->
        <div class="text-center mb-4">
            <h2><i class="fas fa-shield-halved text-info me-2"></i> Local Secrets Manager</h2>
            <p class="text-muted">Мастер установки</p>
        </div>

        <!-- Шаги -->
        <div class="d-flex justify-content-center gap-3 mb-4">
            <?php
            $steps = ['Приветствие', 'Требования', 'Настройка', 'Готово'];
            foreach ($steps as $i => $name):
                $num = $i + 1;
                $cls = $num < $step ? 'step-done' : ($num === $step ? 'step-active' : 'step-pending');
                $icon = $num < $step ? '<i class="fas fa-check"></i>' : $num;
            ?>
                <div class="text-center">
                    <span class="step-badge <?= $cls ?>"><?= $icon ?></span>
                    <div class="small text-muted mt-1"><?= $name ?></div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-body p-4">

            <?php if ($error): ?>
                <div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-1"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($step === 1): ?>
                <!-- ШАГ 1: Приветствие -->
                <h5 class="mb-3">Добро пожаловать!</h5>
                <p>Этот мастер установит <strong>Local Secrets Manager</strong> — локальное хранилище паролей, ключей и учётных данных с AES-256 шифрованием.</p>
                <h6 class="mt-3">Что будет установлено:</h6>
                <ul>
                    <li>База данных MySQL с 16 категориями и шаблонами полей</li>
                    <li>Конфигурационный файл с уникальным ключом шифрования</li>
                    <li>PIN-код доступа (установите при первом входе)</li>
                </ul>
                <h6 class="mt-3">Требования:</h6>
                <ul>
                    <li>PHP 8.0+ с расширениями: PDO, pdo_mysql, openssl, mbstring, curl</li>
                    <li>MySQL 8.0+ или MariaDB 10.4+</li>
                    <li>Веб-сервер Apache (XAMPP, OpenServer, WAMP и т.д.)</li>
                </ul>
                <div class="text-end mt-4">
                    <a href="?step=2" class="btn btn-primary">Далее <i class="fas fa-arrow-right ms-1"></i></a>
                </div>

            <?php elseif ($step === 2): ?>
                <!-- ШАГ 2: Проверка требований -->
                <h5 class="mb-3">Проверка системных требований</h5>
                <?php
                $checks = checkRequirements();
                $allOk = true;
                foreach ($checks as $check):
                    if (!$check['ok']) $allOk = false;
                ?>
                    <div class="d-flex align-items-center py-2 border-bottom">
                        <?php if ($check['ok']): ?>
                            <i class="fas fa-check-circle text-success me-2"></i>
                        <?php else: ?>
                            <i class="fas fa-times-circle text-danger me-2"></i>
                        <?php endif; ?>
                        <span class="flex-grow-1"><?= $check['name'] ?></span>
                        <span class="text-muted small"><?= htmlspecialchars($check['value']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="d-flex justify-content-between mt-4">
                    <a href="?step=1" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Назад</a>
                    <?php if ($allOk): ?>
                        <a href="?step=3" class="btn btn-primary">Далее <i class="fas fa-arrow-right ms-1"></i></a>
                    <?php else: ?>
                        <button class="btn btn-danger" disabled>Исправьте ошибки</button>
                    <?php endif; ?>
                </div>

            <?php elseif ($step === 3): ?>
                <!-- ШАГ 3: Настройка БД -->
                <h5 class="mb-3">Подключение к базе данных</h5>
                <form method="POST">
                    <input type="hidden" name="action" value="install">
                    <div class="row g-3">
                        <div class="col-8">
                            <label class="form-label">Хост MySQL</label>
                            <input type="text" name="db_host" class="form-control" value="<?= htmlspecialchars($_POST['db_host'] ?? 'localhost') ?>">
                        </div>
                        <div class="col-4">
                            <label class="form-label">Порт</label>
                            <input type="number" name="db_port" class="form-control" value="<?= (int)($_POST['db_port'] ?? 3306) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Пользователь</label>
                            <input type="text" name="db_user" class="form-control" value="<?= htmlspecialchars($_POST['db_user'] ?? 'root') ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Пароль</label>
                            <input type="password" name="db_pass" class="form-control" value="">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Имя базы данных</label>
                            <input type="text" name="db_name" class="form-control" value="<?= htmlspecialchars($_POST['db_name'] ?? 'local_secrets') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">
                                Ключ шифрования <span class="text-muted small">(оставьте пустым для авто-генерации)</span>
                            </label>
                            <input type="text" name="enc_key" class="form-control font-monospace"
                                   placeholder="Будет сгенерирован автоматически (64 hex символа)"
                                   value="<?= htmlspecialchars($_POST['enc_key'] ?? '') ?>">
                            <div class="form-text text-warning">
                                <i class="fas fa-exclamation-triangle me-1"></i>
                                Сохраните ключ шифрования! При его утере все данные будут потеряны.
                            </div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between mt-4">
                        <a href="?step=2" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Назад</a>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-download me-1"></i> Установить
                        </button>
                    </div>
                </form>

            <?php elseif ($step === 4): ?>
                <!-- ШАГ 4: Готово -->
                <div class="text-center">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h5>Установка завершена!</h5>
                </div>

                <?php if (!empty($_SESSION['install_enc_key'])): ?>
                    <div class="alert alert-warning mt-3">
                        <h6><i class="fas fa-key me-1"></i> Ваш ключ шифрования:</h6>
                        <code class="user-select-all d-block p-2 bg-dark rounded mt-2" style="word-break:break-all;">
                            <?= htmlspecialchars($_SESSION['install_enc_key']) ?>
                        </code>
                        <small class="d-block mt-2 text-dark">
                            <strong>Сохраните этот ключ в надёжном месте!</strong> Без него невозможно расшифровать данные.
                        </small>
                    </div>
                    <?php unset($_SESSION['install_enc_key']); ?>
                <?php endif; ?>

                <div class="mt-3">
                    <h6>Что дальше:</h6>
                    <ol>
                        <li>Перейдите на <a href="/local_secrets/" class="text-info">главную страницу</a></li>
                        <li>Установите PIN-код (4-6 цифр) при первом входе</li>
                        <li><strong class="text-danger">Удалите папку <code>install/</code></strong> из проекта для безопасности</li>
                    </ol>
                </div>

                <div class="text-center mt-4">
                    <a href="/local_secrets/" class="btn btn-primary btn-lg">
                        <i class="fas fa-rocket me-2"></i> Начать работу
                    </a>
                </div>

            <?php endif; ?>

            </div>
        </div>

        <p class="text-center text-muted small mt-3">Local Secrets Manager v1.0.0</p>
    </div>
</body>
</html>
