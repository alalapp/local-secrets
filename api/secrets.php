<?php
/**
 * API: CRUD секретов
 * POST /api/secrets.php — action: toggle_favorite, delete
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// CSRF
$csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    json_response(['success' => false, 'error' => 'CSRF token invalid'], 403);
}

$action = $_POST['action'] ?? '';
$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    json_response(['success' => false, 'error' => 'ID не указан'], 400);
}

$secretService = new SecretService();

switch ($action) {
    case 'toggle_favorite':
        $isFav = $secretService->toggleFavorite($id);
        json_response(['success' => true, 'is_favorite' => $isFav]);

    case 'delete':
        $secretService->delete($id);
        json_response(['success' => true]);

    default:
        json_response(['success' => false, 'error' => 'Неизвестное действие'], 400);
}
