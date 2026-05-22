<?php
/**
 * 推送测试 API
 * POST /api/test_push.php
 * 
 * 请求参数：
 *   push_type: bark | tg
 *   push_key: 推送 Key
 * 
 * 返回：
 *   {"success": true, "message": "测试推送发送成功"}
 *   {"success": false, "message": "错误信息"}
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// 只允许 POST 请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '仅支持 POST 请求']);
    exit;
}

// 必须登录
if (!isLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '请先登录']);
    exit;
}

// 获取参数
$input = json_decode(file_get_contents('php://input'), true);
$pushType = $input['push_type'] ?? '';
$pushKey = $input['push_key'] ?? '';

// 验证参数
if (empty($pushType) || empty($pushKey)) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '推送类型和 Key 不能为空']);
    exit;
}

if (!in_array($pushType, ['bark', 'tg'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => '推送类型无效，仅支持 bark 和 tg']);
    exit;
}

// 发送测试推送
$result = sendTestPush($pushType, $pushKey);

header('Content-Type: application/json');
echo json_encode($result);
