<?php
/**
 * Миграция: категория «ЭЦП / Сертификаты» + шаблоны полей
 */
require_once __DIR__ . '/../bootstrap.php';

$db = Database::getInstance();

// Создать категорию
$db->execute(
    "INSERT INTO categories (name, icon, color, sort_order) VALUES (?, ?, ?, ?)",
    ['ЭЦП / Сертификаты', 'fa-fingerprint', '#e74c3c', 15]
);
$catId = $db->lastInsertId();

// Шаблоны полей
$fields = [
    ['Ключ приложения', 'token', '', 1],
    ['Серийный номер', 'text', 'Серийный номер сертификата', 2],
    ['Владелец', 'text', 'ФИО или организация', 3],
    ['Срок действия', 'text', 'до ДД.ММ.ГГГГ', 4],
    ['Контейнер', 'text', 'Имя контейнера ключа', 5],
    ['PIN контейнера', 'password', '', 6],
];

foreach ($fields as $f) {
    $db->execute(
        "INSERT INTO category_field_templates (category_id, field_name, field_type, placeholder, sort_order) VALUES (?, ?, ?, ?, ?)",
        [$catId, $f[0], $f[1], $f[2], $f[3]]
    );
}

echo "Категория «ЭЦП / Сертификаты» (id={$catId}): " . count($fields) . " полей\n";
