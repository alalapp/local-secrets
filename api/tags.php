<?php
/**
 * API: Теги (поиск для автодополнения)
 * GET /api/tags.php?q=query
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$query = trim($_GET['q'] ?? '');
$tagService = new TagService();

if ($query) {
    $tags = $tagService->search($query);
} else {
    $tags = $tagService->getPopular();
}

json_response(['success' => true, 'data' => $tags]);
