<?php
/**
 * Страница входа по PIN-коду
 * Первый запуск — установка PIN
 */
require_once __DIR__ . '/bootstrap.php';

// Уже авторизован
if (!empty($_SESSION['authenticated'])) {
    header('Location: /local_secrets/index.php');
    exit;
}

$error = '';
$success = '';
$isSetup = !Auth::isPinConfigured();
$timeout = isset($_GET['timeout']);

// Обработка POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verify_csrf()) {
    $pin = $_POST['pin'] ?? '';

    if ($isSetup) {
        // Установка PIN
        $pinConfirm = $_POST['pin_confirm'] ?? '';
        if ($pin !== $pinConfirm) {
            $error = 'PIN-коды не совпадают';
        } else {
            try {
                Auth::setPin($pin);
                $success = 'PIN установлен! Войдите с новым PIN.';
                $isSetup = false;
            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }
    } else {
        // Авторизация
        $result = Auth::verifyPin($pin);
        if ($result['success']) {
            header('Location: /local_secrets/index.php');
            exit;
        }
        $error = $result['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isSetup ? 'Установка PIN' : 'Вход' ?> — <?= APP_NAME ?></title>
    <link rel="icon" type="image/svg+xml" href="/local_secrets/assets/favicon.svg">
    <script>
        (function(){var t=localStorage.getItem('theme')||'light';document.documentElement.setAttribute('data-am-theme',t);})();
    </script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <link href="/local_secrets/assets/css/app.css?v=<?= APP_VERSION ?>" rel="stylesheet">
</head>
<body>
    <div class="am-login">
        <div class="am-login-card">
            <div class="am-login-brand">
                <img class="am-brand-mark" src="/local_secrets/assets/favicon.svg" alt="<?= APP_NAME ?>">
                <div>
                    <div class="am-brand-name"><?= APP_NAME ?></div>
                    <div class="am-brand-sub">Локальное хранилище секретов</div>
                </div>
            </div>

            <h1 class="am-login-title">
                <?= $isSetup ? 'Установка PIN-кода' : 'Введите PIN' ?>
            </h1>
            <p class="am-login-sub">
                <?php if ($isSetup): ?>
                    Придумайте PIN-код длиной от <?= PIN_MIN_LENGTH ?> до <?= PIN_MAX_LENGTH ?> цифр.
                    Он понадобится для каждого входа.
                <?php else: ?>
                    Данные хранятся локально и зашифрованы AES-256.
                <?php endif; ?>
            </p>

            <?php if ($timeout): ?>
                <div class="am-alert am-alert-warning">
                    <i class="fas fa-clock"></i>
                    <span>Сессия истекла. Войдите снова.</span>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="am-alert am-alert-danger">
                    <i class="fas fa-circle-exclamation"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="am-alert am-alert-success">
                    <i class="fas fa-circle-check"></i>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" id="pinForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <div class="am-field">
                    <label class="am-label" for="pinInput">PIN</label>
                    <input type="password" name="pin" id="pinInput"
                           class="am-input am-pin-input"
                           inputmode="numeric" pattern="[0-9]*"
                           minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                           placeholder="<?= str_repeat('•', PIN_MIN_LENGTH) ?>"
                           autofocus required>
                </div>

                <?php if ($isSetup): ?>
                    <div class="am-field">
                        <label class="am-label" for="pinConfirm">Повторите PIN</label>
                        <input type="password" name="pin_confirm" id="pinConfirm"
                               class="am-input am-pin-input"
                               inputmode="numeric" pattern="[0-9]*"
                               minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                               placeholder="<?= str_repeat('•', PIN_MIN_LENGTH) ?>"
                               required>
                    </div>
                <?php endif; ?>

                <button type="submit" class="am-btn am-btn-primary am-btn-block am-mt-3">
                    <i class="fas fa-<?= $isSetup ? 'check' : 'lock-open' ?>"></i>
                    <?= $isSetup ? 'Установить PIN' : 'Войти' ?>
                </button>
            </form>
        </div>
    </div>

    <?php if (!$isSetup): ?>
    <script>
    (function () {
        const input = document.getElementById('pinInput');
        const form  = document.getElementById('pinForm');
        const MIN   = <?= PIN_MIN_LENGTH ?>;
        const MAX   = <?= PIN_MAX_LENGTH ?>;
        let timer   = null;

        input.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
            clearTimeout(timer);

            if (this.value.length >= MAX) {
                form.submit();
                return;
            }
            if (this.value.length >= MIN) {
                timer = setTimeout(function () { form.submit(); }, 500);
            }
        });
    })();
    </script>
    <?php endif; ?>
</body>
</html>
