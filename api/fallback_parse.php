<?php
/**
 * API: Парсинг текста без LLM (regex fallback)
 * POST /api/fallback_parse.php
 * Body: {"text": "..."}
 */
set_time_limit(30);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'Method not allowed'], 405);
}

$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
    json_response(['success' => false, 'error' => 'CSRF token invalid'], 403);
}

$input = json_decode(file_get_contents('php://input'), true);
$text = trim($input['text'] ?? '');

if (empty($text)) {
    json_response(['success' => false, 'error' => 'Текст не указан'], 400);
}

$parser = new FallbackParser();
$result = $parser->parse($text);

if (empty($result['entries'])) {
    json_response(['success' => false, 'error' => 'Не удалось распознать учётные данные'], 422);
}

Logger::log('parse_fallback', null, null, 'Fallback-парсинг: ' . count($result['entries']) . ' entries');

json_response(['success' => true, 'data' => $result]);
