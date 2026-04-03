<?php
/**
 * API: Парсинг неструктурированного текста через LLM
 * POST /api/llm_parse.php
 * Body: {"text": "..."}
 */
// Увеличиваем лимиты — LLM на маленьких моделях генерирует долго
set_time_limit(300);
ini_set('max_execution_time', '300');

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

// CSRF проверка
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    json_response(['success' => false, 'error' => 'CSRF token invalid'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');

if (empty($text)) {
    json_response(['success' => false, 'error' => 'Текст не указан'], 400);
}

$llm = new LlmParser();

if (!$llm->isAvailable()) {
    json_response(['success' => false, 'error' => 'LM Studio недоступен. Запустите LM Studio и загрузите модель.'], 503);
}

$categoryService = new CategoryService();
$categories = $categoryService->getAll();

// Загрузить шаблоны полей для каждой категории
$db = Database::getInstance();
$templates = $db->fetchAll(
    "SELECT category_id, field_name, field_type FROM category_field_templates ORDER BY category_id, sort_order"
);
$templatesByCategory = [];
foreach ($templates as $t) {
    $templatesByCategory[(int)$t['category_id']][] = $t;
}
foreach ($categories as &$cat) {
    $cat['field_templates'] = $templatesByCategory[(int)$cat['id']] ?? [];
}
unset($cat);

$result = $llm->parseCredentials($text, $categories);

if (isset($result['error']) && empty($result['entries'])) {
    json_response(['success' => false, 'error' => $result['error']], 422);
}

json_response(['success' => true, 'data' => $result]);
