<?php
session_start();

require_once __DIR__ . '/../config/database.php';

/**
 * 检查用户是否已登录
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * 要求登录，未登录则跳转到登录页
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/**
 * 检查当前用户是否为管理员
 */
function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

/**
 * 检查当前用户是否为 VIP
 */
function isVIP() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'vip';
}

/**
 * 检查当前用户是否为管理员或VIP（可以无限监听）
 */
function isAdminOrVIP() {
    return isAdmin() || isVIP();
}

/**
 * 获取当前用户角色名称（中文）
 */
function getRoleName($role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? 'user';
    }
    $names = [
        'admin' => '管理员',
        'vip' => 'VIP用户',
        'user' => '普通用户',
    ];
    return $names[$role] ?? '未知';
}

/**
 * 获取当前用户角色对应的 badge class
 */
function getRoleBadgeClass($role = null) {
    if ($role === null) {
        $role = $_SESSION['role'] ?? 'user';
    }
    $classes = [
        'admin' => 'badge-warning',
        'vip' => 'badge-info',
        'user' => 'badge-secondary',
    ];
    return $classes[$role] ?? 'badge-secondary';
}

/**
 * 要求管理员权限，非管理员则显示错误并退出
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        http_response_code(403);
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>权限不足 - 监听管理系统</title>
            <link rel="stylesheet" href="assets/style.css">
        </head>
        <body class="login-page">
            <div class="login-container">
                <div class="login-card" style="text-align:center;">
                    <div style="font-size:64px;margin-bottom:20px;">🚫</div>
                    <h1>权限不足</h1>
                    <p class="login-subtitle">此页面需要管理员权限</p>
                    <p style="color:#888;margin-bottom:24px;">当前角色：<span class="badge <?php echo getRoleBadgeClass(); ?>"><?php echo getRoleName(); ?></span></p>
                    <a href="dashboard.php" class="btn btn-primary">返回首页</a>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

/**
 * 用户登录验证
 */
function login($username, $password) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['bark_key'] = $user['bark_key'] ?? '';
        $_SESSION['tg_chat_id'] = $user['tg_chat_id'] ?? '';
        return true;
    }

    return false;
}

/**
 * 用户登出
 */
function logout() {
    session_destroy();
    header('Location: index.php');
    exit;
}

/**
 * 注册新用户
 */
function register($username, $password) {
    $db = getDB();
    
    // 检查用户名是否已存在
    $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetchColumn() > 0) {
        return ['success' => false, 'error' => '用户名已存在'];
    }

    // 创建用户（默认为普通用户）
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'user')");
    $result = $stmt->execute([$username, $hashedPassword]);
    
    if ($result) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => '注册失败，请重试'];
    }
}

/**
 * 修改密码
 */
function changePassword($userId, $oldPassword, $newPassword) {
    $db = getDB();
    
    // 验证旧密码
    $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user || !password_verify($oldPassword, $user['password'])) {
        return ['success' => false, 'error' => '旧密码错误'];
    }
    
    // 更新密码
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
    $result = $stmt->execute([$hashedPassword, $userId]);
    
    if ($result) {
        return ['success' => true];
    } else {
        return ['success' => false, 'error' => '密码修改失败'];
    }
}
