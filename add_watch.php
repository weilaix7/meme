<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$isAdmin = isAdmin();

$error = '';
$success = '';

// 档位配置
$stepOptions = [
    1 => ['label' => '每 10K 提醒一次', 'value' => 10000],
    2 => ['label' => '每 50K 提醒一次', 'value' => 50000],
    3 => ['label' => '每 100K 提醒一次', 'value' => 100000],
];

// 获取用户预设的推送配置
$pushConfig = getUserPushConfig();
$presetBarkKey = htmlspecialchars($pushConfig['bark_key']);
$presetTgChatId = htmlspecialchars($pushConfig['tg_chat_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ca = trim($_POST['ca'] ?? '');
    $pushType = trim($_POST['push_type'] ?? '');
    $pushKey = trim($_POST['push_key'] ?? '');
    $stepLevel = trim($_POST['step_level'] ?? '');

    // 验证输入
    if (empty($ca) || empty($pushType) || empty($pushKey) || empty($stepLevel)) {
        $error = '请填写所有必填字段';
    } elseif (!in_array($pushType, ['bark', 'tg'])) {
        $error = '推送类型无效';
    } elseif (!isset($stepOptions[intval($stepLevel)])) {
        $error = '请选择有效的提醒档位';
    } else {
        // 检查监听数量限制
        $check = canAddWatch();
        if (!$check['can']) {
            $error = $check['message'];
        } else {
            // 验证 CA 地址
            //$caValid = validateCA($ca);
            if ($caValid === false) {
                $error = 'CA 地址无效，请检查合约地址是否正确';
            } else {
                // 根据档位获取实际 step_value
                $stepValue = $stepOptions[intval($stepLevel)]['value'];
                // 添加监听
                $result = addWatch($ca, $pushType, $pushKey, $stepValue);
                if ($result) {
                    header('Location: dashboard.php?added=1');
                    exit;
                } else {
                    $error = '添加失败，请重试';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>添加监听 - MEME 币市值提醒</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">🐸 MEME 币市值提醒</a>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">监听列表</a>
                <a href="add_watch.php" class="nav-link active">添加监听</a>
                <a href="profile.php" class="nav-link">个人资料</a>
                <a href="https://t.me/wangcaitx" target="_blank" rel="noopener" class="nav-link">💬 交流群</a>
                <a href="logout.php" class="nav-link">退出 (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>添加监听</h2>
            <a href="dashboard.php" class="btn btn-secondary">返回列表</a>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <form method="POST" action="" class="watch-form">
                <div class="form-group">
                    <label for="ca">合约地址 (CA) <span class="required">*</span></label>
                    <input type="text" id="ca" name="ca" required 
                           placeholder="请输入合约地址，例如: 7xKXtg2CW..."
                           value="<?php echo htmlspecialchars($_POST['ca'] ?? ''); ?>">
                    <small class="form-text">系统将通过 DexScreener API 验证合约地址的有效性</small>
                </div>

                <div class="form-group">
                    <label for="push_type">推送类型 <span class="required">*</span></label>
                    <select id="push_type" name="push_type" required onchange="autoFillPushKey()">
                        <option value="">请选择推送类型</option>
                        <option value="bark" <?php echo (isset($_POST['push_type']) && $_POST['push_type'] === 'bark') ? 'selected' : ''; ?>>Bark</option>
                        <option value="tg" <?php echo (isset($_POST['push_type']) && $_POST['push_type'] === 'tg') ? 'selected' : ''; ?>>Telegram</option>
                    </select>
                    <small class="form-text">选择后自动填充你在 <a href="profile.php" style="color:#667eea;">个人资料</a> 中预设的 Key</small>
                </div>

                <div class="form-group">
                    <label for="push_key">推送 Key <span class="required">*</span></label>
                    <input type="text" id="push_key" name="push_key" required 
                           placeholder="Bark 填 token，Telegram 填 chat_id"
                           value="<?php echo htmlspecialchars($_POST['push_key'] ?? ''); ?>">
                    <small class="form-text">Bark 推送填写 token，Telegram 推送填写 chat_id</small>
                </div>

                <div class="form-group">
                    <label for="step_level">提醒档位 <span class="required">*</span></label>
                    <select id="step_level" name="step_level" required>
                        <option value="">请选择提醒档位</option>
                        <?php foreach ($stepOptions as $level => $option): ?>
                            <option value="<?php echo $level; ?>" 
                                <?php echo (isset($_POST['step_level']) && intval($_POST['step_level']) === $level) ? 'selected' : ''; ?>>
                                <?php echo $option['label']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text">无论市值上涨还是下跌，每跨越一个档位都会触发提醒</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">添加监听</button>
                    <a href="dashboard.php" class="btn btn-secondary">取消</a>
                </div>
            </form>
        </div>
    </div>

    <script>
    // 预设的推送 Key（从 PHP 传入）
    var presetBarkKey = <?php echo json_encode($presetBarkKey); ?>;
    var presetTgChatId = <?php echo json_encode($presetTgChatId); ?>;

    function autoFillPushKey() {
        var type = document.getElementById('push_type').value;
        var keyInput = document.getElementById('push_key');
        
        if (type === 'bark' && presetBarkKey) {
            keyInput.value = presetBarkKey;
            keyInput.placeholder = 'Bark Token（已自动填充）';
        } else if (type === 'tg' && presetTgChatId) {
            keyInput.value = presetTgChatId;
            keyInput.placeholder = 'Telegram Chat ID（已自动填充）';
        } else {
            keyInput.placeholder = 'Bark 填 token，Telegram 填 chat_id';
        }
    }

    // 页面加载时如果有预设值，自动填充
    window.onload = function() {
        var selectedType = document.getElementById('push_type').value;
        if (selectedType) {
            autoFillPushKey();
        }
    };
    </script>
</body>
</html>
