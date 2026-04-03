-- ============================================================
-- Local Secrets Manager — Полная схема БД (дистрибутив)
-- Версия: 1.0.0
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
    `failed_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Категории
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `icon` VARCHAR(50) NULL,
    `color` VARCHAR(7) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`, `name`, `icon`, `color`, `sort_order`) VALUES
(1,  'API Keys',            'fa-key',              '#FF6B35', 1),
(2,  'Banking',             'fa-building-columns', '#2E86AB', 2),
(3,  'Cloud Services',      'fa-cloud',            '#A23B72', 3),
(4,  'Databases',           'fa-database',         '#F18F01', 4),
(5,  'Email',               'fa-envelope',         '#C73E1D', 5),
(6,  'Firebase',            'fa-fire',             '#FFBA08', 6),
(7,  'Hosting',             'fa-server',           '#3B1F2B', 7),
(8,  'Social Media',        'fa-share-nodes',      '#44BBA4', 8),
(9,  '1С',                  'fa-cube',             '#E94F37', 9),
(10, 'Messengers',          'fa-comments',         '#7209B7', 10),
(11, 'VPN / Proxy',         'fa-shield-halved',    '#06D6A0', 11),
(12, 'CRM',                 'fa-users-gear',       '#118AB2', 12),
(13, 'Другое',              'fa-folder',           '#393E41', 100),
(14, 'SSH',                 'fa-terminal',         '#666666', 13),
(15, 'n8n',                 'fa-link',             '#b62525', 14),
(16, 'ЭЦП / Сертификаты',  'fa-fingerprint',      '#e74c3c', 15);

-- ------------------------------------------------------------
-- Секреты
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secrets` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `service_name` VARCHAR(255) NOT NULL,
    `category_id` INT UNSIGNED NULL,
    `description` TEXT NULL COMMENT 'AES-256 зашифровано',
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
-- Поля секрета (значения зашифрованы AES-256)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `secret_fields` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `secret_id` INT UNSIGNED NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `field_value` TEXT NOT NULL COMMENT 'AES-256 зашифровано',
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
-- Лог действий
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `activity_log` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(50) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` INT UNSIGNED NULL,
    `details` TEXT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_action` (`action`),
    KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Шаблоны полей категорий
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `category_field_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `field_type` ENUM('text','password','url','email','token','note') NOT NULL DEFAULT 'text',
    `placeholder` VARCHAR(255) NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    KEY `idx_category` (`category_id`),
    CONSTRAINT `fk_tpl_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Keys
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(1, 'API Key',        'token', 'sk-proj-...',             1),
(1, 'Secret Key',     'token', 'Секретный ключ',          2),
(1, 'Project / App',  'text',  'Название проекта',        3),
(1, 'URL',            'url',   'https://api.example.com', 4);

-- Banking
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(2, 'URL',      'url',      'https://lk.bank.ru',      1),
(2, 'Логин',    'text',     'Логин / номер договора',   2),
(2, 'Пароль',   'password', '',                         3),
(2, 'Телефон',  'text',     '+7...',                    4);

-- Cloud Services
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(3, 'URL консоли',    'url',   'https://console.cloud...', 1),
(3, 'Логин / Email',  'email', '',                         2),
(3, 'Пароль',         'password', '',                      3),
(3, 'Access Key',     'token', '',                         4),
(3, 'Secret Key',     'token', '',                         5),
(3, 'Region',         'text',  'eu-west-1',                6);

-- Databases
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(4, 'Хост',         'text',     'localhost или IP',      1),
(4, 'Порт',         'text',     '3306 / 5432 / 27017',  2),
(4, 'База данных',  'text',     'db_name',               3),
(4, 'Логин',        'text',     'root / postgres',       4),
(4, 'Пароль',       'password', '',                      5);

-- Email
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(5, 'Email',        'email',    'user@example.com',     1),
(5, 'Пароль',       'password', '',                     2),
(5, 'SMTP сервер',  'text',     'smtp.example.com',     3),
(5, 'SMTP порт',    'text',     '587',                  4),
(5, 'IMAP сервер',  'text',     'imap.example.com',     5),
(5, 'IMAP порт',    'text',     '993',                  6);

-- Firebase
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(6, 'Project ID',     'text',  'my-project-12345',                              1),
(6, 'Client ID',      'text',  '1234567890-abc.apps.googleusercontent.com',     2),
(6, 'Client Secret',  'token', 'GOCSPX-...',                                    3),
(6, 'API Key',        'token', 'AIza...',                                        4),
(6, 'URL консоли',    'url',   'https://console.firebase.google.com',           5);

-- Hosting
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(7, 'URL панели',   'url',      'https://cp.hosting.com', 1),
(7, 'Логин',        'text',     '',                       2),
(7, 'Пароль',       'password', '',                       3),
(7, 'Сервер (IP)',  'text',     '123.45.67.89',           4),
(7, 'SSH порт',     'text',     '22',                     5),
(7, 'FTP сервер',   'text',     'ftp.example.com',        6),
(7, 'FTP логин',    'text',     '',                       7),
(7, 'FTP пароль',   'password', '',                       8);

-- Social Media
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(8, 'URL профиля',   'url',   'https://vk.com/...', 1),
(8, 'Логин / Email', 'text',  '',                    2),
(8, 'Пароль',        'password', '',                 3),
(8, 'API Token',     'token', '',                    4);

-- 1С
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(9, 'URL базы',              'url',      'http://server/base_name',  1),
(9, 'Строка подключения',   'text',     'Srvr="...";Ref="..."',    2),
(9, 'Логин',                'text',     '',                         3),
(9, 'Пароль',               'password', '',                         4),
(9, 'Администратор',        'text',     'Имя пользователя 1С',     5);

-- Messengers
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(10, 'Bot Token',     'token', '123456:ABC-DEF...', 1),
(10, 'Bot Username',  'text',  '@my_bot',           2),
(10, 'Webhook URL',   'url',   'https://...',       3),
(10, 'API Key',       'token', '',                  4);

-- VPN / Proxy
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(11, 'Сервер',         'text',     'vpn.example.com',                  1),
(11, 'Порт',           'text',     '1194 / 443',                       2),
(11, 'Логин',          'text',     '',                                 3),
(11, 'Пароль',         'password', '',                                 4),
(11, 'Ключ / Конфиг',  'note',    'OpenVPN config или WireGuard key', 5);

-- CRM
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(12, 'URL',          'url',   'https://crm.example.com', 1),
(12, 'Логин',        'text',  '',                        2),
(12, 'Пароль',       'password', '',                     3),
(12, 'API Token',    'token', '',                        4),
(12, 'Webhook URL',  'url',   '',                        5);

-- Другое
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(13, 'URL',    'url',      '', 1),
(13, 'Логин',  'text',     '', 2),
(13, 'Пароль', 'password', '', 3);

-- SSH
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(14, 'Сервер (IP)', 'text',     '123.45.67.89',                     1),
(14, 'Порт',        'text',     '22',                                2),
(14, 'Логин',       'text',     'root',                              3),
(14, 'Пароль',      'password', '',                                  4),
(14, 'SSH ключ',    'note',     'Приватный ключ или путь к файлу',  5);

-- n8n
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(15, 'URL',    'url',      '', 1),
(15, 'Логин',  'text',     '', 2),
(15, 'Пароль', 'password', '', 3);

-- ЭЦП / Сертификаты
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(16, 'Ключ приложения',  'token',    '',                           1),
(16, 'Серийный номер',   'text',     'Серийный номер сертификата', 2),
(16, 'Владелец',         'text',     'ФИО или организация',        3),
(16, 'Срок действия',    'text',     'до ДД.ММ.ГГГГ',             4),
(16, 'Контейнер',        'text',     'Имя контейнера ключа',       5),
(16, 'PIN контейнера',   'password', '',                           6);
