# Local Secrets Manager

[![Version](https://img.shields.io/badge/Version-1.1.0-success.svg)](#)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-blue.svg)](https://www.php.net/)
[![GitHub stars](https://img.shields.io/github/stars/alalapp/local-secrets?style=social)](https://github.com/alalapp/local-secrets/stargazers)
[![Last commit](https://img.shields.io/github/last-commit/alalapp/local-secrets)](https://github.com/alalapp/local-secrets/commits/main)
[![GitHub issues](https://img.shields.io/github/issues/alalapp/local-secrets)](https://github.com/alalapp/local-secrets/issues)

Локальное веб-приложение для хранения паролей, API-ключей, токенов и других учётных данных с AES-256 шифрованием.

## Возможности

- **AES-256-CBC шифрование** всех значений (пароли, ключи, токены)
- **PIN-авторизация** с защитой от перебора
- **Категории** с настраиваемыми шаблонами полей (API Keys, Hosting, Databases, SSH, ЭЦП и др.)
- **CRUD тегов** — создание, переименование, слияние, удаление неиспользуемых
- **Умное добавление** — вставьте неструктурированный текст, LLM или regex-парсер разберёт его на записи; название, категория и теги редактируются до сохранения
- **Контекст категории** — при создании/умном добавлении из категории она автоматически подставляется и передаётся в LLM-промпт
- **Полнотекстовый поиск** по названиям сервисов и полей с выпадающими подсказками в шапке
- **Дашборд** — стат-карточки с цветными градиентами, плитки часто используемых и недавно открытых секретов
- **Резервное копирование** — экспорт/импорт базы и настроек через веб-интерфейс
- **Apple/Notion-вдохновлённый UI** (v1.1.0) — собственная дизайн-система на CSS-переменных, без Bootstrap и jQuery, чистый vanilla JS
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
- Типичный путь установки: `C:\xampp`

### 2. Запустить Apache и MySQL

Откройте **XAMPP Control Panel** и нажмите **Start** для:
- **Apache**
- **MySQL**

### 3. Скопировать проект

Скопируйте папку `local_secrets` в директорию htdocs:

```
C:\xampp\htdocs\local_secrets\
```

Или клонируйте репозиторий прямо в htdocs:

```bash
cd C:\xampp\htdocs
git clone https://github.com/alalapp/local-secrets.git local_secrets
```

Или создайте junction-ссылку (если проект лежит в другом месте):

```powershell
# PowerShell (от администратора)
New-Item -ItemType Junction -Path "C:\xampp\htdocs\local_secrets" -Target "C:\path\to\local_secrets"
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
rmdir /s /q C:\xampp\htdocs\local_secrets\install
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
├── api/                    # JSON API (парсинг, шаблоны, теги, бэкап)
├── pages/                  # Страницы: дашборд, формы, просмотр, категории, теги, настройки, бэкап, умное добавление
├── templates/              # Layout (am-shell с сайдбаром), header
├── assets/
│   ├── css/app.css         # Дизайн-система am-* (CSS-переменные, ~870 строк)
│   ├── js/app.js           # Vanilla JS: модалки, тосты, поиск, копирование
│   └── favicon.svg         # Иконка приложения (щит с замком)
├── migrations/             # SQL-миграции
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

**Через веб-интерфейс:** раздел `Резервное копирование` в сайдбаре — выгрузка/загрузка дампа БД.

**Вручную — обязательно сохраните:**
1. `config.php` — содержит `ENCRYPTION_KEY` (без него данные не расшифровать!)
2. Дамп базы данных: `mysqldump -u root local_secrets > backup.sql`

---

## История изменений

### 1.1.0
- Полностью переработан UI: собственная дизайн-система в стиле Apple/Notion вместо Bootstrap
- Светлая тема по умолчанию, минималистичный сайдбар на 240px со sticky-позиционированием
- Шапка с blur-эффектом, кастомный поиск с выпадающими результатами
- Цветные градиентные стат-карточки на дашборде
- Лёгкие нейтральные градиенты на всех карточках
- Vanilla JS вместо jQuery; кастомные модалки/тосты вместо Bootstrap-овских
- SVG-иконка приложения
- Установщик переведён на новый дизайн

### 1.0.0
- Первый релиз: AES-256 шифрование, PIN-авторизация, категории/теги, умное добавление, бэкап.

---

## Лицензия

Для личного использования.
