<?php
require_once __DIR__ . '/../bootstrap.php';

$db = Database::getInstance();

$db->execute("DELETE FROM category_field_templates WHERE category_id = 14");

$fields = [
    ['Сервер (IP)', 'text', '123.45.67.89', 1],
    ['Порт', 'text', '22', 2],
    ['Логин', 'text', 'root', 3],
    ['Пароль', 'password', '', 4],
    ['SSH ключ', 'note', 'Приватный ключ или путь к файлу', 5],
];

foreach ($fields as $f) {
    $db->execute(
        "INSERT INTO category_field_templates (category_id, field_name, field_type, placeholder, sort_order) VALUES (14, ?, ?, ?, ?)",
        $f
    );
}

echo "SSH шаблон: " . count($fields) . " полей\n";
