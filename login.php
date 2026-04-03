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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: rgba(255,255,255,0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 20px;
            padding: 3rem;
            max-width: 400px;
            width: 100%;
            color: #fff;
        }
        .pin-input {
            font-size: 2rem;
            text-align: center;
            letter-spacing: 1rem;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.2);
            color: #fff;
            border-radius: 12px;
            padding: 0.8rem;
        }
        .pin-input:focus {
            background: rgba(255,255,255,0.12);
            border-color: #4fc3f7;
            box-shadow: 0 0 0 0.2rem rgba(79,195,247,0.25);
            color: #fff;
        }
        .pin-input::placeholder { color: rgba(255,255,255,0.3); }
        .btn-enter {
            background: linear-gradient(135deg, #4fc3f7, #0288d1);
            border: none;
            padding: 0.8rem;
            font-size: 1.1rem;
            border-radius: 12px;
            color: #fff;
        }
        .btn-enter:hover { background: linear-gradient(135deg, #29b6f6, #0277bd); color: #fff; }
        .lock-icon { font-size: 3rem; color: #4fc3f7; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="text-center mb-4">
            <i class="fas fa-shield-halved lock-icon"></i>
            <h3 class="mt-3"><?= $isSetup ? 'Установка PIN' : APP_NAME ?></h3>
            <?php if ($isSetup): ?>
                <p class="text-muted small">Придумайте PIN-код (<?= PIN_MIN_LENGTH ?>-<?= PIN_MAX_LENGTH ?> цифр)</p>
            <?php endif; ?>
        </div>

        <?php if ($timeout): ?>
            <div class="alert alert-warning py-2 small">Сессия истекла. Войдите снова.</div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST" id="pinForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

            <div class="mb-3">
                <input type="password" name="pin" class="form-control pin-input"
                       inputmode="numeric" pattern="[0-9]*"
                       minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                       placeholder="<?= str_repeat('*', PIN_MIN_LENGTH) ?>"
                       autofocus required>
            </div>

            <?php if ($isSetup): ?>
                <div class="mb-3">
                    <input type="password" name="pin_confirm" class="form-control pin-input"
                           inputmode="numeric" pattern="[0-9]*"
                           minlength="<?= PIN_MIN_LENGTH ?>" maxlength="<?= PIN_MAX_LENGTH ?>"
                           placeholder="<?= str_repeat('*', PIN_MIN_LENGTH) ?>"
                           required>
                    <div class="form-text text-muted small mt-1">Повторите PIN</div>
                </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-enter w-100">
                <i class="fas fa-<?= $isSetup ? 'check' : 'lock-open' ?> me-2"></i>
                <?= $isSetup ? 'Установить PIN' : 'Войти' ?>
            </button>
        </form>
    </div>
</body>
</html>
