-- ============================================================
-- Миграция 005: счётчик открытий секрета
-- Добавляет view_count и last_viewed_at в таблицу secrets
-- для дашборда «Часто используемые» и «Недавно открытые».
-- ============================================================

USE `local_secrets`;

ALTER TABLE `secrets`
    ADD COLUMN `view_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `is_favorite`,
    ADD COLUMN `last_viewed_at` DATETIME NULL AFTER `view_count`,
    ADD KEY `idx_view_count` (`view_count`),
    ADD KEY `idx_last_viewed` (`last_viewed_at`);
