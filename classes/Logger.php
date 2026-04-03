<?php
/**
 * Логирование действий в activity_log и файлы
 */
class Logger {

    /**
     * Записать действие в activity_log
     */
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void {
        try {
            $db = Database::getInstance();
            $db->execute(
                "INSERT INTO activity_log (action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?)",
                [$action, $entityType, $entityId, $details, $_SERVER['REMOTE_ADDR'] ?? null]
            );
        } catch (Throwable $e) {
            self::file("Ошибка записи в activity_log: " . $e->getMessage());
        }
    }

    /**
     * Записать в файл лога
     */
    public static function file(string $message, string $level = 'INFO'): void {
        $date = date('Y-m-d');
        $time = date('Y-m-d H:i:s');
        $logFile = LOG_DIR . "/app_{$date}.log";
        $line = "[{$time}] [{$level}] {$message}" . PHP_EOL;
        @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Ошибка
     */
    public static function error(string $message): void {
        self::file($message, 'ERROR');
    }
}
