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
$currentCategoryId = (int)($input['category_id'] ?? 0) ?: null;

if (empty($text)) {
    json_response(['success' => false, 'error' => 'Текст не указан'], 400);
}

$parser = new FallbackParser();
$result = $parser->parse($text);

// Оставить только существующие теги из БД
$db = Database::getInstance();
$existingTags = array_column($db->fetchAll("SELECT name FROM tags ORDER BY name"), 'name');
$tagsLowerToName = [];
foreach ($existingTags as $n) {
    $tagsLowerToName[mb_strtolower($n)] = $n;
}
if (!empty($result['entries'])) {
    foreach ($result['entries'] as &$entry) {
        $filtered = [];
        foreach ($entry['tags'] ?? [] as $t) {
            $key = mb_strtolower(trim((string)$t));
            if ($key !== '' && isset($tagsLowerToName[$key])) {
                $filtered[$tagsLowerToName[$key]] = true;
            }
        }
        $entry['tags'] = array_keys($filtered);
    }
    unset($entry);
}

if ($currentCategoryId && !empty($result['entries'])) {
    $cat = (new CategoryService())->getById($currentCategoryId);
    if ($cat) {
        foreach ($result['entries'] as &$entry) {
            if (empty($entry['category']) || $entry['category'] === 'Другое') {
                $entry['category'] = $cat['name'];
            }
        }
        unset($entry);
    }
}

if (empty($result['entries'])) {
    json_response(['success' => false, 'error' => 'Не удалось распознать учётные данные'], 422);
}

Logger::log('parse_fallback', null, null, 'Fallback-парсинг: ' . count($result['entries']) . ' entries');

json_response(['success' => true, 'data' => $result]);
