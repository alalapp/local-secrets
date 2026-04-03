<?php
/**
 * API: Дешифровка значения поля для копирования в буфер обмена
 * GET /api/clipboard.php?id=fieldId
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../bootstrap.php';
Auth::requireAuth();

$fieldId = (int)($_GET['id'] ?? 0);

if (!$fieldId) {
    json_response(['success' => false, 'error' => 'Не указан ID поля'], 400);
}

$secretService = new SecretService();
$value = $secretService->decryptFieldValue($fieldId);

if ($value === null) {
    json_response(['success' => false, 'error' => 'Поле не найдено'], 404);
}

json_response(['success' => true, 'value' => $value]);
