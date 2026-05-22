<?php
/**
 * API: Python 更新 last_level 和 marketcap
 * POST /api/update_level.php
 * 
 * 请求体 (JSON):
 * {
 *   "id": 1,
 *   "last_level": 3,
 *   "marketcap": 180000
 * }
 * 
 * 返回:
 * {
 *   "success": true
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

// 处理 OPTIONS 预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => '仅支持 POST 请求'], JSON_UNESCAPED_UNICODE);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';

// 获取请求体
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 验证数据
if (!$data || !isset($data['id']) || !isset($data['last_level']) || !isset($data['marketcap'])) {
    http_response_code(400);
    echo json_encode(['error' => '缺少必要参数 (id, last_level, marketcap)'], JSON_UNESCAPED_UNICODE);
    exit;
}

$id = intval($data['id']);
$lastLevel = intval($data['last_level']);
$marketcap = intval($data['marketcap']);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => '无效的 id'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $result = updateWatchLevel($id, $lastLevel, $marketcap);
    
    if ($result) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '更新失败'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
