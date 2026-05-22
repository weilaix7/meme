<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$error = '';
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barkKey = trim($_POST['bark_key'] ?? '');
    $tgChatId = trim($_POST['tg_chat_id'] ?? '');
    
    $result = updateUserPushConfig($_SESSION['user_id'], $barkKey, $tgChatId);
    if ($result) {
        $success = '推送配置已保存';
    } else {
        $error = '保存失败，请重试';
    }
}

// 获取当前配置
$pushConfig = getUserPushConfig();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人资料 - MEME 币市值提醒</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">🐸 MEME 币市值提醒</a>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">首页</a>
                <a href="add_watch.php" class="nav-link">添加监听</a>
                <a href="profile.php" class="nav-link active">个人资料</a>
                <a href="change_password.php" class="nav-link">修改密码</a>
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
            <h2>个人资料</h2>
            <div class="header-actions">
                <a href="dashboard.php" class="btn btn-secondary">返回首页</a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <!-- 用户信息 -->
        <div class="card" style="margin-bottom:20px;">
            <h3 style="margin-bottom:16px;">账号信息</h3>
            <div style="display:flex;gap:40px;flex-wrap:wrap;">
                <div>
                    <span style="color:#888;font-size:13px;">用户名</span>
                    <div style="font-size:16px;font-weight:500;margin-top:4px;"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
                </div>
                <div>
                    <span style="color:#888;font-size:13px;">角色</span>
                    <div style="margin-top:4px;">
                        <span class="badge <?php echo getRoleBadgeClass(); ?>"><?php echo getRoleName(); ?></span>
                    </div>
                </div>
                <div>
                    <span style="color:#888;font-size:13px;">监听数量</span>
                    <div style="font-size:16px;font-weight:500;margin-top:4px;"><?php echo getUserWatchCount(); ?> 个</div>
                </div>
            </div>
        </div>

        <!-- 推送配置 -->
        <div class="card">
            <h3 style="margin-bottom:8px;">推送配置</h3>
            <p style="color:#888;font-size:13px;margin-bottom:24px;">
                在这里预设你的推送 Key，添加监听时系统会自动填充，无需每次手动输入。
            </p>

            <form method="POST" action="" class="watch-form">
                <div class="form-group">
                    <label for="bark_key">Bark Key</label>
                    <input type="text" id="bark_key" name="bark_key" 
                           placeholder="输入你的 Bark Token（选填）"
                           value="<?php echo htmlspecialchars($pushConfig['bark_key']); ?>">
                    <small class="form-text">
                        Bark 推送的 Token，例如：<code>xxxxxxxxxxxx</code>。
                        在 <a href="https://api.day.app" target="_blank" style="color:#667eea;">Bark App</a> 中获取。
                    </small>
                </div>

                <div class="form-group">
                    <label for="tg_chat_id">Telegram Chat ID</label>
                    <input type="text" id="tg_chat_id" name="tg_chat_id" 
                           placeholder="输入你的 Telegram Chat ID（选填）"
                           value="<?php echo htmlspecialchars($pushConfig['tg_chat_id']); ?>">
                    <small class="form-text">
                        Telegram 的 Chat ID，例如：<code>123456789</code>。
                        向 <a href="https://t.me/userinfobot" target="_blank" style="color:#667eea;">@userinfobot</a> 发送 /start 获取。
                    </small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">保存配置</button>
                </div>
            </form>

            <!-- 测试推送按钮 -->
            <div style="margin-top: 24px; padding-top: 20px; border-top: 1px solid #eee;">
                <h4 style="margin-bottom: 12px; font-size: 14px; color: #333;">🔔 测试推送</h4>
                <p style="color: #888; font-size: 13px; margin-bottom: 12px;">
                    点击下方按钮，向你的设备发送一条测试消息，确认推送配置是否正确。
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-info" onclick="testPush('bark')" id="testBarkBtn">
                        📱 测试 Bark 推送
                    </button>
                    <button type="button" class="btn btn-info" onclick="testPush('tg')" id="testTgBtn">
                        💬 测试 Telegram 推送
                    </button>
                </div>
                <div id="testPushResult" style="margin-top: 10px; display: none;"></div>
            </div>
        </div>

        <!-- 使用说明 -->
        <div class="card" style="margin-top:20px;">
            <h3 style="margin-bottom:12px;">💡 使用说明</h3>
            <div style="color:#666;font-size:14px;line-height:1.8;">
                <p>1. 在 <strong>个人资料</strong> 中预设你的 Bark Key 和 Telegram Chat ID</p>
                <p>2. 去 <strong>添加监听</strong> 页面，选择推送类型后，Key 会自动填充</p>
                <p>3. 如果预设了两种推送方式，选择类型时自动填入对应的 Key</p>
                <p>4. 也可以手动修改，不影响预设值</p>
            </div>
        </div>
    </div>
    <script>
    function testPush(type) {
        var btn = document.getElementById(type === 'bark' ? 'testBarkBtn' : 'testTgBtn');
        var resultDiv = document.getElementById('testPushResult');
        var keyInput = document.getElementById(type === 'bark' ? 'bark_key' : 'tg_chat_id');
        var pushKey = keyInput.value.trim();

        if (!pushKey) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-error';
            resultDiv.textContent = type === 'bark' ? '请先填写 Bark Key' : '请先填写 Telegram Chat ID';
            return;
        }

        // 禁用按钮，显示加载
        btn.disabled = true;
        btn.textContent = '发送中...';
        resultDiv.style.display = 'none';

        fetch('api/test_push.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                push_type: type,
                push_key: pushKey
            })
        })
        .then(function(resp) { return resp.json(); })
        .then(function(data) {
            resultDiv.style.display = 'block';
            if (data.success) {
                resultDiv.className = 'alert alert-success';
            } else {
                resultDiv.className = 'alert alert-error';
            }
            resultDiv.textContent = data.message;
        })
        .catch(function(err) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'alert alert-error';
            resultDiv.textContent = '网络错误，请重试';
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = type === 'bark' ? '📱 测试 Bark 推送' : '💬 测试 Telegram 推送';
        });
    }
    </script>
</body>
</html>
