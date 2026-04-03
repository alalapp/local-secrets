-- ============================================================
-- Local Secrets Manager — Начальная схема БД
-- База данных: local_secrets
-- ============================================================

CREATE DATABASE IF NOT EXISTS `local_secrets`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `local_secrets`;

-- ------------------------------------------------------------
-- PIN-авторизация (один пользователь)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_pin` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `pin_hash` VARCHAR(255) NOT NULL COMMENT 'bcrypt хэш PIN-кода',
    `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Счётчик неудачных попыток',
    `locked_until` DATETIME NULL COMMENT 'Блокировка до (после N неудач)',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Категории
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL COMMENT 'Название категории',
    `icon` VARCHAR(50) NULL COMMENT 'Font Awesome класс иконки',
    `color` VARCHAR(7) NULL COMMENT 'HEX цвет (#FF5733)',
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Предзаполненные категории
INSERT INTO `categories` (`name`, `icon`, `color`, `sort_order`) VALUES
('API Keys',       'fa-key',              '#FF6B35', 1),
('Banking',        'fa-building-columns', '#2E86AB', 2),
('Cloud Services', 'fa-cloud',            '#A23B72', 3),
('Databases',      'fa-database',         '#F18F01', 4),
('Email',          'fa-envelope',         '#C73E1D', 5),
('Firebase',       'fa-fire',             '#FFBA08', 6),
('Hosting',        'fa-server',           '#3B1F2B', 7),
('Social Media',   'fa-share-nodes',      '#44BBA4', 8),
('1С',             'fa-cube',             '#E94F37', 9),
('Messengers',     'fa-comments',         '#7209B7', 10),
('VPN / Proxy',    'fa-shield-halved',    '#06D6A0', 11),
('CRM',            'fa-users-gear',       '#118AB2', 12),
('Другое',         'fa-folder',           '#393E41', 100);

-- ------------------------------------------------------------
-- Секреты (основная таблица)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secrets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(255) NOT NULL COMMENT 'Название сервиса',
    `category_id` INT UNSIGNED NULL COMMENT 'FK на категорию',
    `description` TEXT NULL COMMENT 'Описание (зашифровано AES-256)',
    `is_favorite` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `idx_category` (`category_id`),
    KEY `idx_favorite` (`is_favorite`),
    FULLTEXT KEY `ft_service` (`service_name`),
    CONSTRAINT `fk_secrets_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Поля секрета (ключ-значение, значения зашифрованы)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secret_fields` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `secret_id` INT UNSIGNED NOT NULL,
    `field_name` VARCHAR(100) NOT NULL COMMENT 'Название поля (login, password, api_key, url...)',
    `field_value` TEXT NOT NULL COMMENT 'AES-256 зашифрованное значение',
    `field_type` ENUM('text','password','url','email','token','note') NOT NULL DEFAULT 'text',
    `sort_order` INT NOT NULL DEFAULT 0,
    KEY `idx_secret` (`secret_id`),
    CONSTRAINT `fk_fields_secret` FOREIGN KEY (`secret_id`)
        REFERENCES `secrets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `secret_fields` ADD FULLTEXT KEY `ft_field_name` (`field_name`);

-- ------------------------------------------------------------
-- Теги
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `tags` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    UNIQUE KEY `uk_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Связь секрет <-> теги (M:N)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secret_tags` (
    `secret_id` INT UNSIGNED NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`secret_id`, `tag_id`),
    CONSTRAINT `fk_st_secret` FOREIGN KEY (`secret_id`)
        REFERENCES `secrets` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_st_tag` FOREIGN KEY (`tag_id`)
        REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Лог действий (аудит)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL COMMENT 'login, logout, create, update, delete, view, search',
    `entity_type` VARCHAR(50) NULL COMMENT 'secret, category, tag',
    `entity_id` INT UNSIGNED NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_action` (`action`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
