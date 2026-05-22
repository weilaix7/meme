<?php
/**
 * API: 关闭某个 CA 的所有监听（标记为死盘）
 * POST /api/disable_ca.php
 * 
 * 请求体 (JSON):
 * {
 *   "ca": "xxxx"
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
if (!$data || !isset($data['ca']) || empty(trim($data['ca']))) {
    http_response_code(400);
    echo json_encode(['error' => '缺少 ca 参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

$ca = trim($data['ca']);

try {
    $result = disableCA($ca);
    
    if ($result) {
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode(['error' => '禁用失败'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
