<?php
require_once __DIR__ . '/includes/auth.php';

// 如果已登录且是管理员，跳转到后台
if (isLoggedIn() && isAdmin()) {
    header('Location: dashboard.php');
    exit;
}
// 如果已登录但不是管理员，显示权限不足
if (isLoggedIn() && !isAdmin()) {
    requireAdmin();
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // 验证输入
    if (empty($username) || empty($password) || empty($confirmPassword)) {
        $error = '请填写所有字段';
    } elseif (strlen($username) < 3 || strlen($username) > 20) {
        $error = '用户名长度应在 3-20 个字符之间';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $error = '用户名只能包含字母、数字和下划线';
    } elseif (strlen($password) < 6) {
        $error = '密码长度不能少于 6 位';
    } elseif ($password !== $confirmPassword) {
        $error = '两次输入的密码不一致';
    } else {
        // 使用 auth.php 中的 register 函数
        $result = register($username, $password);
        if ($result['success']) {
            // 注册成功，跳转到登录页
            header('Location: index.php?registered=1');
            exit;
        } else {
            $error = $result['error'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MEME 币市值提醒 - 注册</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="login-page">
    <div class="login-container">
        <div class="login-card">
            <h1>注册账号</h1>
            <p class="login-subtitle">创建账号（默认为普通用户）</p>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required 
                           placeholder="3-20位字母、数字或下划线"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <input type="password" id="password" name="password" required 
                           placeholder="至少6位密码">
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认密码</label>
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="再次输入密码">
                </div>
                <button type="submit" class="btn btn-primary btn-block">注 册</button>
            </form>

            <div class="login-footer">
                <p>已有账号？<a href="index.php">立即登录</a></p>
            </div>
        </div>
    </div>
</body>
</html>
