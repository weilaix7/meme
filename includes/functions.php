<?php
require_once __DIR__ . '/../config/database.php';

/**
 * 验证 CA 地址是否有效（通过 DexScreener API）
 */
function validateCA($ca) {
    $url = "https://api.dexscreener.com/latest/dex/tokens/" . urlencode($ca);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        return false;
    }

    $data = json_decode($response, true);
    
    // 检查是否有返回 pairs
    if (!isset($data['pairs']) || empty($data['pairs'])) {
        return false;
    }

    return true;
}

/**
 * 获取 CA 的当前市值（从 DexScreener）
 */
function getMarketCap($ca) {
    $url = "https://api.dexscreener.com/latest/dex/tokens/" . urlencode($ca);
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return 0;
    }

    $data = json_decode($response, true);
    
    if (!isset($data['pairs']) || empty($data['pairs'])) {
        return 0;
    }

    // 取第一个 pair 的市值（通常是最具流动性的）
    $marketCap = $data['pairs'][0]['marketCap'] ?? 0;
    return intval($marketCap);
}

/**
 * 格式化市值显示
 */
function formatMarketCap($value) {
    if ($value >= 1000000) {
        return number_format($value / 1000000, 2) . 'M';
    } elseif ($value >= 1000) {
        return number_format($value / 1000, 2) . 'K';
    }
    return number_format($value);
}

/**
 * 获取所有唯一的 CA 列表（状态为正常的）
 */
function getActiveCAList() {
    $db = getDB();
    $stmt = $db->query("SELECT DISTINCT ca FROM watchlist WHERE status = 1");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * 获取某个 CA 的所有监听用户
 */
function getWatchersByCA($ca) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM watchlist WHERE ca = ? AND status = 1");
    $stmt->execute([$ca]);
    return $stmt->fetchAll();
}

/**
 * 更新监听记录的 last_level 和 marketcap
 */
function updateWatchLevel($id, $lastLevel, $marketcap) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE watchlist SET last_level = ?, current_marketcap = ? WHERE id = ?");
    return $stmt->execute([$lastLevel, $marketcap, $id]);
}

/**
 * 禁用某个 CA 的所有监听
 */
function disableCA($ca) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE watchlist SET status = 0 WHERE ca = ?");
    return $stmt->execute([$ca]);
}

/**
 * 添加监听记录
 */
function addWatch($ca, $pushType, $pushKey, $stepValue) {
    $db = getDB();
    $userId = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare("INSERT INTO watchlist (user_id, ca, push_type, push_key, step_value) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$userId, $ca, $pushType, $pushKey, $stepValue]);
}

/**
 * 获取所有监听记录（带分页）
 */
function getAllWatches($page = 1, $perPage = 20) {
    $db = getDB();
    $offset = ($page - 1) * $perPage;
    
    $stmt = $db->prepare("SELECT * FROM watchlist ORDER BY created_at DESC LIMIT ? OFFSET ?");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

/**
 * 获取监听记录总数
 */
function getWatchCount() {
    $db = getDB();
    return $db->query("SELECT COUNT(*) FROM watchlist")->fetchColumn();
}

/**
 * 删除监听记录
 */
function deleteWatch($id) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM watchlist WHERE id = ?");
    return $stmt->execute([$id]);
}

// ============================================
// 用户管理函数
// ============================================

/**
 * 获取所有用户列表
 */
function getAllUsers() {
    $db = getDB();
    return $db->query("SELECT id, username, role, created_at FROM users ORDER BY created_at DESC")->fetchAll();
}

/**
 * 获取用户的监听数量
 */
function getUserWatchCount($userId = null) {
    $db = getDB();
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? 0;
    }
    $stmt = $db->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * 检查用户是否可以添加监听
 * 普通用户最多3个，VIP和管理员不限
 */
function canAddWatch($userId = null) {
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? 0;
    }
    $role = $_SESSION['role'] ?? 'user';
    
    // 管理员和VIP不限
    if ($role === 'admin' || $role === 'vip') {
        return ['can' => true];
    }
    
    // 普通用户限制3个
    $count = getUserWatchCount($userId);
    if ($count >= 3) {
        return ['can' => false, 'message' => '普通用户最多只能添加 3 个监听，当前已使用 ' . $count . ' 个。升级 VIP 可解除限制。'];
    }
    
    return ['can' => true];
}

/**
 * 更新用户角色（支持 admin/vip/user）
 */
function updateUserRole($userId, $newRole) {
    if (!in_array($newRole, ['admin', 'vip', 'user'])) {
        return false;
    }
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
    return $stmt->execute([$newRole, $userId]);
}

/**
 * 删除用户
 */
function deleteUser($userId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND username != 'admin'");
    return $stmt->execute([$userId]);
}

// ============================================
// 用户推送配置函数
// ============================================

/**
 * 获取用户的推送配置
 */
function getUserPushConfig($userId = null) {
    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? 0;
    }
    $db = getDB();
    $stmt = $db->prepare("SELECT bark_key, tg_chat_id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    return [
        'bark_key' => $user['bark_key'] ?? '',
        'tg_chat_id' => $user['tg_chat_id'] ?? '',
    ];
}

/**
 * 更新用户的推送配置
 */
function updateUserPushConfig($userId, $barkKey, $tgChatId) {
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET bark_key = ?, tg_chat_id = ? WHERE id = ?");
    $result = $stmt->execute([$barkKey, $tgChatId, $userId]);
    
    if ($result) {
        // 同步更新 session
        $_SESSION['bark_key'] = $barkKey;
        $_SESSION['tg_chat_id'] = $tgChatId;
    }
    
    return $result;
}

/**
 * 发送测试推送
 * 用于个人资料页的"测试推送"按钮
 */
function sendTestPush($pushType, $pushKey) {
    $username = $_SESSION['username'] ?? '用户';
    $now = date('Y-m-d H:i:s');
    
    if ($pushType === 'bark') {
        $title = '🔔 MEME 币市值提醒 - 测试推送';
        $body = implode("\n", [
            "你好 {$username}！",
            "",
            "这是一条测试推送消息。",
            "如果你的手机收到了这条消息，",
            "说明推送配置正确 ✅",
            "",
            "⏰ {$now}",
        ]);
        
        $url = "https://api.day.app/" . urlencode($pushKey) . "/" . urlencode($title) . "/" . urlencode($body);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return ['success' => true, 'message' => 'Bark 测试推送发送成功！请检查手机通知'];
        } else {
            return ['success' => false, 'message' => 'Bark 推送失败，请检查 Token 是否正确'];
        }
    }
    
    if ($pushType === 'tg') {
        $message = implode("\n", [
            "<b>🔔 MEME 币市值提醒 - 测试推送</b>",
            "",
            "你好 {$username}！",
            "",
            "这是一条测试推送消息。",
            "如果你收到了这条消息，",
            "说明推送配置正确 ✅",
            "",
            "⏰ {$now}",
        ]);
        
        $botToken = '8761383326:AAG4p1c72qjBaBJdq-LdYitLssHpb5TZCvE';
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'chat_id' => $pushKey,
                'text' => $message,
                'parse_mode' => 'HTML',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $responseData = json_decode($response, true);
        curl_close($ch);
        
        if ($httpCode === 200 && isset($responseData['ok']) && $responseData['ok']) {
            return ['success' => true, 'message' => 'Telegram 测试推送发送成功！请检查 Telegram 消息'];
        } else {
            $errorMsg = isset($responseData['description']) ? $responseData['description'] : '未知错误';
            return ['success' => false, 'message' => 'Telegram 推送失败：' . $errorMsg];
        }
    }
    
    return ['success' => false, 'message' => '不支持的推送类型'];
}
