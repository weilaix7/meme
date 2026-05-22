<?php
// tg_webhook.php

// 1. 你的机器人 Token
define('BOT_TOKEN', '8761383326:AAG4p1c72qjBaBJdq-LdYitLssHpb5TZCvE');
define('TG_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/');

/**
 * 🔒 隐藏功能：一键设置 / 注册 Webhook
 * 访问方式: https://你的域名.com/tg_webhook.php?action=set_webhook
 */
if (isset($_GET['action']) && $_GET['action'] === 'set_webhook') {
    // 自动动态组装当前文件的公网 HTTPS URL
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $script = strtok($_SERVER['REQUEST_URI'], '?'); // 去掉 ?action=set_webhook 后面的参数
    
    $currentUrl = $protocol . $host . $script;
    
    // 调用 TG 官方的 setWebhook 接口
    $targetUrl = TG_API_URL . 'setWebhook?url=' . urlencode($currentUrl);
    
    $response = file_get_contents($targetUrl);
    
    // 漂亮地输出结果给浏览器
    header('Content-Type: application/json; charset=utf-8');
    echo $response;
    exit; // 必须中断，不要往下走接收逻辑
}

// ==========================================
// 2. 下面是原本的 Webhook 接收消息逻辑 (保持不变)
// ==========================================
$content = file_get_contents("php://input");
$update = json_decode($content, true);

// 记录日志
file_put_contents('tg_debug.log', $content . PHP_EOL, FILE_APPEND);

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = isset($update['message']['text']) ? trim($update['message']['text']) : '';
    $firstName = isset($update['message']['from']['first_name']) ? $update['message']['from']['first_name'] : '用户';

    if ($text === '/start' || str_starts_with($text, '/start')) {
        $replyMessage = "👋 嗨，{$firstName}！欢迎使用代币监控雷达。\n\n";
        $replyMessage .= "您的 Telegram User ID 是：`{$chatId}`\n\n";
        $replyMessage .= "请复制上方单行代码框内的 ID 数字，返回网站后台进行绑定即可。";

        $payload = [
            'chat_id' => $chatId,
            'text' => $replyMessage,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, TG_API_URL . 'sendMessage');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_exec($ch);
        curl_close($ch);
    }
}

http_response_code(200);
echo "OK";