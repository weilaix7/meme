<?php
require_once __DIR__ . '/includes/auth.php';

// 如果已登录，跳转到后台（普通用户和管理员都可以访问 dashboard）
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$registered = isset($_GET['registered']) && $_GET['registered'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($username, $password)) {
        header('Location: dashboard.php');
        exit;
    } else {
        $error = '用户名或密码错误';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEME 币市值提醒 - 登录</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <h1>🐸 MEME 币市值提醒</h1>
            <p class="login-subtitle">请登录以继续</p>
            
            <?php if ($registered): ?>
                <div class="alert alert-success">注册成功！请使用新账号登录</div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required placeholder="请输入用户名">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required placeholder="请输入密码">
                </div>
                <button type="submit" class="btn btn-primary btn-block">登 录</button>
            </form>

            <div class="login-footer">
                <p>没有账号？<a href="register.php">立即注册</a></p>
                <p style="margin-top: 8px;">
                    💬 加入 <a href="https://t.me/wangcaitx" target="_blank" rel="noopener">Telegram 交流群</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
