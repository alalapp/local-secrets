<?php
/**
 * Исправление кириллических категорий
 */
require_once __DIR__ . '/../bootstrap.php';

$db = Database::getInstance();

// Удалить битые кириллические категории
$db->execute("DELETE FROM categories WHERE id IN (9, 13)");

// Вставить заново с правильной кодировкой
$db->execute(
    "INSERT INTO categories (id, name, icon, color, sort_order) VALUES (?, ?, ?, ?, ?)",
    [9, '1С', 'fa-cube', '#E94F37', 9]
);
$db->execute(
    "INSERT INTO categories (id, name, icon, color, sort_order) VALUES (?, ?, ?, ?, ?)",
    [13, 'Другое', 'fa-folder', '#393E41', 100]
);

// Проверка
$rows = $db->fetchAll("SELECT id, name FROM categories ORDER BY sort_order");
foreach ($rows as $row) {
    echo "{$row['id']}: {$row['name']}\n";
}
echo "\nГотово!\n";
