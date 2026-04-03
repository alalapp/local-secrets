<?php
/**
 * API: CRUD шаблонов полей категории
 * GET  ?category_id=7 — получить шаблоны
 * POST — сохранить шаблоны (перезаписать)
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $categoryId = (int)($_GET['category_id'] ?? 0);
    if (!$categoryId) {
        json_response(['success' => true, 'data' => []]);
    }

    $templates = $db->fetchAll(
        "SELECT id, field_name, field_type, placeholder, sort_order FROM category_field_templates WHERE category_id = ? ORDER BY sort_order",
        [$categoryId]
    );
    json_response(['success' => true, 'data' => $templates]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        json_response(['success' => false, 'error' => 'CSRF token invalid'], 403);
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $categoryId = (int)($input['category_id'] ?? 0);
    $fields = $input['fields'] ?? [];

    if (!$categoryId) {
        json_response(['success' => false, 'error' => 'category_id не указан'], 400);
    }

    $db->beginTransaction();
    try {
        // Удалить старые
        $db->execute("DELETE FROM category_field_templates WHERE category_id = ?", [$categoryId]);

        // Вставить новые
        foreach ($fields as $i => $f) {
            $name = trim($f['field_name'] ?? '');
            if ($name === '') continue;

            $db->execute(
                "INSERT INTO category_field_templates (category_id, field_name, field_type, placeholder, sort_order) VALUES (?, ?, ?, ?, ?)",
                [$categoryId, $name, $f['field_type'] ?? 'text', $f['placeholder'] ?? '', $i]
            );
        }

        $db->commit();
        Logger::log('update', 'category_templates', $categoryId, 'Обновлены шаблоны полей');
        json_response(['success' => true, 'count' => count($fields)]);
    } catch (Throwable $e) {
        $db->rollback();
        json_response(['success' => false, 'error' => $e->getMessage()], 500);
    }
}
