<?php
/**
 * PIN-авторизация и управление сессией
 */
class Auth {

    /**
     * Проверить, установлен ли PIN (первый запуск)
     */
    public static function isPinConfigured(): bool {
        $db = Database::getInstance();
        $count = $db->fetchColumn("SELECT COUNT(*) FROM auth_pin");
        return $count > 0;
    }

    /**
     * Установить PIN (первый запуск или смена)
     */
    public static function setPin(string $pin): void {
        self::validatePinFormat($pin);
        $hash = password_hash($pin, PASSWORD_BCRYPT);
        $db = Database::getInstance();

        if (self::isPinConfigured()) {
            $db->execute("UPDATE auth_pin SET pin_hash = ?, failed_attempts = 0, locked_until = NULL", [$hash]);
        } else {
            $db->execute("INSERT INTO auth_pin (pin_hash) VALUES (?)", [$hash]);
        }
    }

    /**
     * Проверить PIN при входе
     * @return array{success: bool, error?: string}
     */
    public static function verifyPin(string $pin): array {
        $db = Database::getInstance();
        $row = $db->fetchOne("SELECT * FROM auth_pin LIMIT 1");

        if (!$row) {
            return ['success' => false, 'error' => 'PIN не установлен'];
        }

        // Проверка блокировки
        if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
            $remaining = ceil((strtotime($row['locked_until']) - time()) / 60);
            return ['success' => false, 'error' => "Аккаунт заблокирован. Попробуйте через {$remaining} мин."];
        }

        if (password_verify($pin, $row['pin_hash'])) {
            // Успешный вход — сбросить счётчик
            $db->execute("UPDATE auth_pin SET failed_attempts = 0, locked_until = NULL");
            $_SESSION['authenticated'] = true;
            $_SESSION['last_activity'] = time();
            Logger::log('login', null, null, 'Успешный вход');
            return ['success' => true];
        }

        // Неудачная попытка
        $attempts = $row['failed_attempts'] + 1;
        $lockUntil = null;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOCKOUT_MINUTES * 60);
            $db->execute("UPDATE auth_pin SET failed_attempts = ?, locked_until = ?", [$attempts, $lockUntil]);
            Logger::log('login_failed', null, null, "Блокировка после {$attempts} попыток");
            return ['success' => false, 'error' => "Превышено кол-во попыток. Блокировка на " . LOCKOUT_MINUTES . " мин."];
        }

        $db->execute("UPDATE auth_pin SET failed_attempts = ?", [$attempts]);
        $left = MAX_LOGIN_ATTEMPTS - $attempts;
        Logger::log('login_failed', null, null, "Неверный PIN, осталось попыток: {$left}");
        return ['success' => false, 'error' => "Неверный PIN. Осталось попыток: {$left}"];
    }

    /**
     * Проверить авторизацию (вызывается на каждой защищённой странице)
     */
    public static function requireAuth(): void {
        if (empty($_SESSION['authenticated'])) {
            header('Location: /local_secrets/login.php');
            exit;
        }

        // Проверка таймаута
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
            self::logout();
            header('Location: /local_secrets/login.php?timeout=1');
            exit;
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Выход
     */
    public static function logout(): void {
        Logger::log('logout', null, null, 'Выход из системы');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'],
                $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    /**
     * Проверить формат PIN
     */
    private static function validatePinFormat(string $pin): void {
        $len = strlen($pin);
        if ($len < PIN_MIN_LENGTH || $len > PIN_MAX_LENGTH || !ctype_digit($pin)) {
            throw new InvalidArgumentException(
                "PIN должен содержать от " . PIN_MIN_LENGTH . " до " . PIN_MAX_LENGTH . " цифр"
            );
        }
    }
}
