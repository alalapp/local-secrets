<?php
/**
 * API: Полнотекстовый поиск секретов
 * GET /api/search.php?q=query
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$query = trim($_GET['q'] ?? '');

if (strlen($query) < 2) {
    json_response(['success' => true, 'data' => []]);
}

$secretService = new SecretService();
$results = $secretService->search($query);

json_response(['success' => true, 'data' => $results]);
