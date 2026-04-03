<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../classes/FallbackParser.php';

$text = <<<'TEXT'
****************************************
BEGET стройрегион
***************************************

Имя основного пользователя PostgreSQL
postgres
Пароль пользователя PostgreSQL
9KDis!74

VPS:
84.54.30.46
user: root
pass: l56J0Wu3ELd&

http://84.54.30.46:7474
====================
Панель управления:
====================
Адрес: https://cp.beget.com
Логин: rudakoir
Пароль: Xv5rjM5xwDBh
ID: 2566969 (Рекомендуем использовать его при обращении в службу технической поддержки)

Доступ по FTP/SSH:
Сервер: rudakoir.beget.tech
Логин: rudakoir
Пароль: Xv5rjM5xwDBh

n8n
9Lso%cYP
rudakov.s@specregion.ru
murusrifooce.beget.app
TEXT;

$parser = new FallbackParser();
$result = $parser->parse($text);

echo "Найдено entries: " . count($result['entries']) . "\n\n";

foreach ($result['entries'] as $i => $entry) {
    echo "--- Entry " . ($i + 1) . " ---\n";
    echo "Service: {$entry['service_name']}\n";
    echo "Category: {$entry['category']}\n";
    echo "Tags: " . implode(', ', $entry['tags']) . "\n";
    echo "Fields:\n";
    foreach ($entry['fields'] as $f) {
        echo "  [{$f['type']}] {$f['name']}: {$f['value']}\n";
    }
    echo "\n";
}
