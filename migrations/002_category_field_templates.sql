-- Шаблоны полей для категорий
-- При выборе категории в форме — автоматически подставляются поля

USE `local_secrets`;

CREATE TABLE IF NOT EXISTS `category_field_templates` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT UNSIGNED NOT NULL,
    `field_name` VARCHAR(100) NOT NULL COMMENT 'Название поля',
    `field_type` ENUM('text','password','url','email','token','note') NOT NULL DEFAULT 'text',
    `placeholder` VARCHAR(255) NULL COMMENT 'Подсказка для поля',
    `sort_order` INT NOT NULL DEFAULT 0,
    KEY `idx_category` (`category_id`),
    CONSTRAINT `fk_tpl_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- API Keys (1)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(1, 'API Key', 'token', 'sk-proj-...', 1),
(1, 'Secret Key', 'token', 'Секретный ключ', 2),
(1, 'Project / App', 'text', 'Название проекта', 3),
(1, 'URL', 'url', 'https://api.example.com', 4);

-- Banking (2)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(2, 'URL', 'url', 'https://lk.bank.ru', 1),
(2, 'Логин', 'text', 'Логин / номер договора', 2),
(2, 'Пароль', 'password', '', 3),
(2, 'Телефон', 'text', '+7...', 4);

-- Cloud Services (3)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(3, 'URL консоли', 'url', 'https://console.cloud...', 1),
(3, 'Логин / Email', 'email', '', 2),
(3, 'Пароль', 'password', '', 3),
(3, 'Access Key', 'token', '', 4),
(3, 'Secret Key', 'token', '', 5),
(3, 'Region', 'text', 'eu-west-1', 6);

-- Databases (4)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(4, 'Хост', 'text', 'localhost или IP', 1),
(4, 'Порт', 'text', '3306 / 5432 / 27017', 2),
(4, 'База данных', 'text', 'db_name', 3),
(4, 'Логин', 'text', 'root / postgres', 4),
(4, 'Пароль', 'password', '', 5);

-- Email (5)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(5, 'Email', 'email', 'user@example.com', 1),
(5, 'Пароль', 'password', '', 2),
(5, 'SMTP сервер', 'text', 'smtp.example.com', 3),
(5, 'SMTP порт', 'text', '587', 4),
(5, 'IMAP сервер', 'text', 'imap.example.com', 5),
(5, 'IMAP порт', 'text', '993', 6);

-- Firebase (6)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(6, 'Project ID', 'text', 'my-project-12345', 1),
(6, 'Client ID', 'text', '1234567890-abc.apps.googleusercontent.com', 2),
(6, 'Client Secret', 'token', 'GOCSPX-...', 3),
(6, 'API Key', 'token', 'AIza...', 4),
(6, 'URL консоли', 'url', 'https://console.firebase.google.com', 5);

-- Hosting (7)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(7, 'URL панели', 'url', 'https://cp.hosting.com', 1),
(7, 'Логин', 'text', '', 2),
(7, 'Пароль', 'password', '', 3),
(7, 'Сервер (IP)', 'text', '123.45.67.89', 4),
(7, 'SSH порт', 'text', '22', 5),
(7, 'FTP сервер', 'text', 'ftp.example.com', 6),
(7, 'FTP логин', 'text', '', 7),
(7, 'FTP пароль', 'password', '', 8);

-- Social Media (8)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(8, 'URL профиля', 'url', 'https://vk.com/...', 1),
(8, 'Логин / Email', 'text', '', 2),
(8, 'Пароль', 'password', '', 3),
(8, 'API Token', 'token', '', 4);

-- 1С (9)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(9, 'URL базы', 'url', 'http://server/base_name', 1),
(9, 'Строка подключения', 'text', 'Srvr="...";Ref="..."', 2),
(9, 'Логин', 'text', '', 3),
(9, 'Пароль', 'password', '', 4),
(9, 'Администратор', 'text', 'Имя пользователя 1С', 5);

-- Messengers (10)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(10, 'Bot Token', 'token', '123456:ABC-DEF...', 1),
(10, 'Bot Username', 'text', '@my_bot', 2),
(10, 'Webhook URL', 'url', 'https://...', 3),
(10, 'API Key', 'token', '', 4);

-- VPN / Proxy (11)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(11, 'Сервер', 'text', 'vpn.example.com', 1),
(11, 'Порт', 'text', '1194 / 443', 2),
(11, 'Логин', 'text', '', 3),
(11, 'Пароль', 'password', '', 4),
(11, 'Ключ / Конфиг', 'note', 'OpenVPN config или WireGuard key', 5);

-- CRM (12)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(12, 'URL', 'url', 'https://crm.example.com', 1),
(12, 'Логин', 'text', '', 2),
(12, 'Пароль', 'password', '', 3),
(12, 'API Token', 'token', '', 4),
(12, 'Webhook URL', 'url', '', 5);

-- Другое (13)
INSERT INTO `category_field_templates` (`category_id`, `field_name`, `field_type`, `placeholder`, `sort_order`) VALUES
(13, 'URL', 'url', '', 1),
(13, 'Логин', 'text', '', 2),
(13, 'Пароль', 'password', '', 3);
