<?php
/**
 * API: 获取某个 CA 的监听用户列表
 * GET /api/watchers.php?ca=xxx
 * 
 * 返回: JSON 数组
 * [
 *   {
 *     "id": 1,
 *     "push_type": "bark",
 *     "push_key": "xxx",
 *     "step_value": 50000,
 *     "last_level": 2
 *   },
 *   ...
 * ]
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

require_once __DIR__ . '/../includes/functions.php';

// 检查参数
if (!isset($_GET['ca']) || empty(trim($_GET['ca']))) {
    http_response_code(400);
    echo json_encode(['error' => '缺少 ca 参数'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $ca = trim($_GET['ca']);
    $watchers = getWatchersByCA($ca);
    
    // 只返回需要的字段
    $result = array_map(function($w) {
        return [
            'id' => intval($w['id']),
            'push_type' => $w['push_type'],
            'push_key' => $w['push_key'],
            'step_value' => intval($w['step_value']),
            'last_level' => intval($w['last_level']),
        ];
    }, $watchers);
    
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
}
