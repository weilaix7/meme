<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = $_POST['old_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($oldPassword) || empty($newPassword) || empty($confirmPassword)) {
        $error = '请填写所有字段';
    } elseif (strlen($newPassword) < 6) {
        $error = '新密码长度不能少于 6 位';
    } elseif ($newPassword !== $confirmPassword) {
        $error = '两次输入的新密码不一致';
    } else {
        $result = changePassword($_SESSION['user_id'], $oldPassword, $newPassword);
        if ($result['success']) {
            $success = '密码修改成功！下次登录请使用新密码。';
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
    <title>修改密码 - MEME 币市值提醒</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">🐸 MEME 币市值提醒</a>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">首页</a>
                <a href="add_watch.php" class="nav-link">添加监听</a>
                <a href="profile.php" class="nav-link">个人资料</a>
                <a href="change_password.php" class="nav-link active">修改密码</a>
                <?php if (isAdmin()): ?>
                    <a href="users.php" class="nav-link">用户管理</a>
                <?php endif; ?>
                <a href="https://t.me/wangcaitx" target="_blank" rel="noopener" class="nav-link">💬 交流群</a>
                <a href="logout.php" class="nav-link">退出 (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>修改密码</h2>
            <a href="dashboard.php" class="btn btn-secondary">返回首页</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card" style="max-width:500px;">
            <form method="POST" action="">
                <div class="form-group">
                    <label for="old_password">当前密码 <span class="required">*</span></label>
                    <input type="password" id="old_password" name="old_password" required placeholder="请输入当前密码">
                </div>
                <div class="form-group">
                    <label for="new_password">新密码 <span class="required">*</span></label>
                    <input type="password" id="new_password" name="new_password" required placeholder="至少6位新密码">
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码 <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="再次输入新密码">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">修改密码</button>
                    <a href="dashboard.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
