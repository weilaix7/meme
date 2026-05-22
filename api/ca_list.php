<?php
/**
 * API: 获取所有唯一的 CA 列表
 * GET /api/ca_list.php
 * 
 * 返回: JSON 数组
 * ["CA1", "CA2", ...]
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../includes/functions.php';

try {
    $caList = getActiveCAList();
    echo json_encode($caList, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
