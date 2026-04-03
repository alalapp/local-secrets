# Local Secrets Manager

Локальное веб-приложение для хранения паролей, API-ключей, токенов и других учётных данных с AES-256 шифрованием.

## Возможности

- **AES-256-CBC шифрование** всех значений (пароли, ключи, токены)
- **PIN-авторизация** с защитой от перебора
- **16 категорий** с настраиваемыми шаблонами полей (API Keys, Hosting, Databases, SSH, ЭЦП и др.)
- **Умное добавление** — вставьте неструктурированный текст, LLM или regex-парсер разберёт его на записи
- **Полнотекстовый поиск** по названиям сервисов и полей
- **Тёмная тема** Bootstrap 5.3
- Работает **только на localhost**

## Требования

| Компонент     | Версия       |
|---------------|-------------|
| PHP           | 8.0+        |
| MySQL/MariaDB | 8.0+ / 10.4+ |
| Расширения PHP | pdo, pdo_mysql, openssl, mbstring, curl, json |
| Веб-сервер    | Apache (XAMPP, OpenServer, WAMP) |

**Опционально:** LM Studio (для умного парсинга через LLM)

---

## Установка (XAMPP)

### 1. Установить XAMPP

Скачайте и установите [XAMPP](https://www.apachefriends.org/):
- При установке выберите компоненты: **Apache**, **MySQL**, **PHP**
- Типичный путь установки: `C:\xampp` или `E:\PROGRAMS\XAMPP`

### 2. Запустить Apache и MySQL

Откройте **XAMPP Control Panel** и нажмите **Start** для:
- **Apache**
- **MySQL**

### 3. Скопировать проект

Скопируйте папку `local_secrets` в директорию htdocs:

```
C:\xampp\htdocs\local_secrets\
```

Или создайте junction-ссылку (если проект лежит в другом месте):

```powershell
# PowerShell (от администратора)
New-Item -ItemType Junction -Path "C:\xampp\htdocs\local_secrets" -Target "E:\PROJECTS\local_secrets"
```

### 4. Запустить установщик

Откройте в браузере:

```
http://localhost/local_secrets/install/
```

Установщик проведёт вас через 4 шага:
1. **Приветствие** — описание и требования
2. **Проверка** — PHP версия, расширения, права доступа
3. **Настройка** — подключение к MySQL (по умолчанию: root без пароля, порт 3306)
4. **Готово** — сохранение ключа шифрования

### 5. Установить PIN-код

После установки перейдите на `http://localhost/local_secrets/` — система предложит задать PIN-код (4-6 цифр).

### 6. Удалить установщик

Для безопасности удалите папку `install/` после установки:

```
rm -rf C:\xampp\htdocs\local_secrets\install
```

---

## Ручная установка (без установщика)

### 1. Создать базу данных

```bash
mysql -u root -p < install/schema.sql
```

Или через phpMyAdmin: откройте `http://localhost/phpmyadmin`, импортируйте файл `install/schema.sql`.

### 2. Настроить config.php

Скопируйте и отредактируйте конфигурацию:

```php
// config.php — ключевые параметры:

define('DB_HOST', 'localhost');
define('DB_NAME', 'local_secrets');
define('DB_USER', 'root');
define('DB_PASS', '');

// Сгенерируйте свой ключ: php -r "echo bin2hex(random_bytes(32));"
define('ENCRYPTION_KEY', 'ваш_64_символьный_hex_ключ');
```

### 3. Создать папку logs

```bash
mkdir logs
```

### 4. Открыть в браузере

```
http://localhost/local_secrets/
```

---

## Настройка LM Studio (опционально)

Для «умного добавления» секретов через LLM:

1. Установите [LM Studio](https://lmstudio.ai/)
2. Загрузите модель (рекомендуется: `qwen/qwen3-vl-4b` или аналогичная)
3. Запустите сервер в LM Studio (по умолчанию порт `1234`)
4. В настройках приложения (`Настройки` > `LM Studio`) укажите URL и модель

---

## Структура проекта

```
local_secrets/
├── config.php              # Конфигурация (БД, ключ шифрования, LLM)
├── bootstrap.php           # Автозагрузка, сессия, CSRF
├── index.php               # Главная — список секретов
├── login.php               # Авторизация по PIN
├── classes/                # PHP-классы
│   ├── Database.php        # PDO singleton
│   ├── Encryption.php      # AES-256-CBC
│   ├── Auth.php            # PIN + сессия + brute-force
│   ├── SecretService.php   # CRUD секретов
│   ├── CategoryService.php # CRUD категорий
│   ├── TagService.php      # Теги
│   ├── LlmParser.php       # LM Studio интеграция
│   ├── FallbackParser.php  # Regex-парсер (без LLM)
│   └── Logger.php          # Логирование
├── api/                    # JSON API
├── pages/                  # Страницы (формы, просмотр, категории, настройки)
├── templates/              # Layout, header
├── assets/                 # CSS, JS
├── install/                # Установщик (удалить после установки!)
│   ├── index.php           # Веб-установщик
│   └── schema.sql          # Полная схема БД
└── logs/                   # Логи (создаётся автоматически)
```

---

## Безопасность

- Приложение доступно **только с localhost** (127.0.0.1 / ::1)
- Все значения полей зашифрованы **AES-256-CBC** с уникальным IV
- PIN хранится как **bcrypt** хэш
- Блокировка после **5 неудачных попыток** входа на 15 минут
- Автоматический выход через **30 минут** бездействия
- **CSRF-токены** на всех формах и AJAX-запросах
- Значения **никогда не попадают в HTML** — дешифровка только по AJAX при копировании

---

## Резервное копирование

**Обязательно сохраните:**
1. `config.php` — содержит `ENCRYPTION_KEY` (без него данные не расшифровать!)
2. Дамп базы данных: `mysqldump -u root local_secrets > backup.sql`

---

## Лицензия

Для личного использования.
