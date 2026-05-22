<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireLogin();

$isAdmin = isAdmin();
$isVIP = isVIP();
$isAdminOrVIP = isAdminOrVIP();
$currentUserId = $_SESSION['user_id'];

// 档位配置（与 add_watch.php 保持一致）
$stepOptions = [
    10000 => '10K',
    50000 => '50K',
    100000 => '100K',
];

$db = getDB();

// 获取统计数据
// 管理员看到全局统计，普通用户看到自己的统计
if ($isAdmin) {
    $totalWatches = $db->query("SELECT COUNT(*) FROM watchlist")->fetchColumn();
    $activeWatches = $db->query("SELECT COUNT(*) FROM watchlist WHERE status = 1")->fetchColumn();
    $uniqueCA = $db->query("SELECT COUNT(DISTINCT ca) FROM watchlist WHERE status = 1")->fetchColumn();
    $totalUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} else {
    // 普通用户和VIP只能看到自己的监听统计
    $stmt = $db->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ?");
    $stmt->execute([$currentUserId]);
    $totalWatches = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(*) FROM watchlist WHERE user_id = ? AND status = 1");
    $stmt->execute([$currentUserId]);
    $activeWatches = $stmt->fetchColumn();
    
    $stmt = $db->prepare("SELECT COUNT(DISTINCT ca) FROM watchlist WHERE user_id = ? AND status = 1");
    $stmt->execute([$currentUserId]);
    $uniqueCA = $stmt->fetchColumn();
}

// 搜索功能
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';

// 分页
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 构建查询 - 权限控制：非管理员只能看自己的数据
$where = [];
$params = [];

if (!$isAdmin) {
    $where[] = "user_id = ?";
    $params[] = $currentUserId;
}

if ($search) {
    $where[] = "(ca LIKE ? OR push_type LIKE ? OR push_key LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($statusFilter !== '') {
    $where[] = "status = ?";
    $params[] = intval($statusFilter);
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// 获取总数
$countStmt = $db->prepare("SELECT COUNT(*) FROM watchlist {$whereClause}");
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// 获取数据
$sql = "SELECT * FROM watchlist {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$watches = $stmt->fetchAll();

// ===== 处理操作（用户只能操作自己的，管理员可以操作所有） =====

// 删除操作
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = intval($_GET['delete']);
    if ($isAdmin) {
        // 管理员可以直接删除
        deleteWatch($id);
        header('Location: dashboard.php?deleted=1');
        exit;
    } else {
        // 普通用户只能删除自己的
        $stmt = $db->prepare("DELETE FROM watchlist WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $currentUserId]);
        if ($stmt->rowCount() > 0) {
            header('Location: dashboard.php?deleted=1');
        } else {
            header('Location: dashboard.php?error=permission');
        }
        exit;
    }
}

// 切换状态操作（开启/关闭）
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    if ($isAdmin) {
        $stmt = $db->prepare("UPDATE watchlist SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: dashboard.php?toggled=1');
        exit;
    } else {
        // 普通用户只能切换自己的
        $stmt = $db->prepare("UPDATE watchlist SET status = CASE WHEN status = 1 THEN 0 ELSE 1 END WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $currentUserId]);
        if ($stmt->rowCount() > 0) {
            header('Location: dashboard.php?toggled=1');
        } else {
            header('Location: dashboard.php?error=permission');
        }
        exit;
    }
}

// 按 CA 关闭所有监听（管理员可关闭任意 CA，普通用户只能关闭自己的）
if (isset($_GET['disable_ca']) && !empty($_GET['disable_ca'])) {
    $targetCa = trim($_GET['disable_ca']);
    if ($isAdmin) {
        disableCA($targetCa);
        header('Location: dashboard.php?disabled_ca=1');
        exit;
    } else {
        // 普通用户只能关闭自己名下该 CA 的监听
        $stmt = $db->prepare("UPDATE watchlist SET status = 0 WHERE ca = ? AND user_id = ?");
        $stmt->execute([$targetCa, $currentUserId]);
        header('Location: dashboard.php?disabled_ca=1');
        exit;
    }
}

// 按 CA 开启所有监听（管理员可开启任意 CA，普通用户只能开启自己的）
if (isset($_GET['enable_ca']) && !empty($_GET['enable_ca'])) {
    $targetCa = trim($_GET['enable_ca']);
    if ($isAdmin) {
        $stmt = $db->prepare("UPDATE watchlist SET status = 1 WHERE ca = ?");
        $stmt->execute([$targetCa]);
        header('Location: dashboard.php?enabled_ca=1');
        exit;
    } else {
        // 普通用户只能开启自己名下该 CA 的监听
        $stmt = $db->prepare("UPDATE watchlist SET status = 1 WHERE ca = ? AND user_id = ?");
        $stmt->execute([$targetCa, $currentUserId]);
        header('Location: dashboard.php?enabled_ca=1');
        exit;
    }
}

// 批量操作（仅管理员）
if ($isAdmin && isset($_POST['batch_action']) && isset($_POST['selected_ids'])) {
    $ids = array_map('intval', $_POST['selected_ids']);
    $idsPlaceholder = implode(',', array_fill(0, count($ids), '?'));
    
    if ($_POST['batch_action'] === 'enable') {
        $stmt = $db->prepare("UPDATE watchlist SET status = 1 WHERE id IN ({$idsPlaceholder})");
        $stmt->execute($ids);
        header('Location: dashboard.php?batch=1&action=enable');
        exit;
    } elseif ($_POST['batch_action'] === 'disable') {
        $stmt = $db->prepare("UPDATE watchlist SET status = 0 WHERE id IN ({$idsPlaceholder})");
        $stmt->execute($ids);
        header('Location: dashboard.php?batch=1&action=disable');
        exit;
    } elseif ($_POST['batch_action'] === 'delete') {
        $stmt = $db->prepare("DELETE FROM watchlist WHERE id IN ({$idsPlaceholder})");
        $stmt->execute($ids);
        header('Location: dashboard.php?batch=1&action=delete');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理 - MEME 币市值提醒</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">🐸 MEME 币市值提醒</a>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link active">首页</a>
                <a href="add_watch.php" class="nav-link">添加监听</a>
                <a href="profile.php" class="nav-link">个人资料</a>
                <a href="change_password.php" class="nav-link">修改密码</a>
                <?php if ($isAdmin): ?>
                    <a href="users.php" class="nav-link">用户管理</a>
                <?php endif; ?>
                <a href="https://t.me/wangcaitx" target="_blank" rel="noopener" class="nav-link">💬 交流群</a>
                <a href="logout.php" class="nav-link">退出 (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- 用户信息栏 -->
        <div class="user-info-bar">
            <span>当前角色：<span class="badge <?php echo getRoleBadgeClass(); ?>"><?php echo getRoleName(); ?></span></span>
            <?php if (!$isAdminOrVIP): ?>
                <span class="user-limit-info">监听限制：最多 3 个（当前已使用 <?php echo getUserWatchCount(); ?> 个）</span>
            <?php endif; ?>
        </div>

        <!-- 统计概览 -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-blue">📊</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $totalWatches; ?></span>
                    <span class="stat-label"><?php echo $isAdmin ? '总监听数' : '我的监听数'; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-green">✅</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $activeWatches; ?></span>
                    <span class="stat-label"><?php echo $isAdmin ? '正常监听' : '我的正常监听'; ?></span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon stat-icon-purple">🔗</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $uniqueCA; ?></span>
                    <span class="stat-label"><?php echo $isAdmin ? '监控币种' : '我的监控币种'; ?></span>
                </div>
            </div>
            <?php if ($isAdmin): ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon-orange">👤</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo $totalUsers; ?></span>
                    <span class="stat-label">用户数</span>
                </div>
            </div>
            <?php else: ?>
            <div class="stat-card">
                <div class="stat-icon stat-icon-orange">📈</div>
                <div class="stat-info">
                    <span class="stat-number"><?php echo getUserWatchCount(); ?></span>
                    <span class="stat-label">我的监听</span>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- 页面标题和操作 -->
        <div class="page-header">
            <h2><?php echo $isAdmin ? '全部监听列表' : '我的监听列表'; ?></h2>
            <div class="header-actions">
                <a href="add_watch.php" class="btn btn-primary">+ 添加监听</a>
            </div>
        </div>

        <!-- 提示消息 -->
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">监听记录已删除</div>
        <?php endif; ?>
        <?php if (isset($_GET['toggled'])): ?>
            <div class="alert alert-success">状态已切换</div>
        <?php endif; ?>
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success">监听添加成功</div>
        <?php endif; ?>
        <?php if (isset($_GET['batch'])): ?>
            <div class="alert alert-success">批量操作成功</div>
        <?php endif; ?>
        <?php if (isset($_GET['error']) && $_GET['error'] === 'permission'): ?>
            <div class="alert alert-error">权限不足，无法操作此记录</div>
        <?php endif; ?>
        <?php if (isset($_GET['disabled_ca'])): ?>
            <div class="alert alert-success">已关闭该 CA 的所有监听</div>
        <?php endif; ?>
        <?php if (isset($_GET['enabled_ca'])): ?>
            <div class="alert alert-success">已开启该 CA 的所有监听</div>
        <?php endif; ?>

        <!-- 搜索和筛选 -->
        <div class="filter-bar">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="搜索 CA、推送类型、Key..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="filter-input">
                </div>
                <div class="filter-group">
                    <select name="status" class="filter-select">
                        <option value="">全部状态</option>
                        <option value="1" <?php echo $statusFilter === '1' ? 'selected' : ''; ?>>正常</option>
                        <option value="0" <?php echo $statusFilter === '0' ? 'selected' : ''; ?>>已关闭</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="dashboard.php" class="btn btn-secondary">重置</a>
            </form>
        </div>

        <!-- 数据表格 -->
        <form method="POST" action="" id="batchForm">
            <div class="table-container">
                <?php if ($isAdmin): ?>
                <div class="table-toolbar">
                    <div class="batch-actions">
                        <select name="batch_action" class="filter-select" id="batchAction">
                            <option value="">批量操作</option>
                            <option value="enable">批量开启</option>
                            <option value="disable">批量关闭</option>
                            <option value="delete">批量删除</option>
                        </select>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="doBatchAction()">执行</button>
                    </div>
                    <span class="table-info">共 <?php echo $totalCount; ?> 条记录</span>
                </div>
                <?php endif; ?>
                <table class="table">
                    <thead>
                        <tr>
                            <?php if ($isAdmin): ?>
                                <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <?php endif; ?>
                            <th>ID</th>
                            <th>合约地址 (CA)</th>
                            <th>推送类型</th>
                            <th>推送Key</th>
                            <th>提醒阶梯</th>
                            <th>上次档位</th>
                            <th>当前市值</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($watches)): ?>
                            <tr>
                                <td colspan="11" class="text-center">
                                    <?php if ($search || $statusFilter !== ''): ?>
                                        没有找到匹配的记录
                                    <?php else: ?>
                                        暂无监听记录，<a href="add_watch.php" style="color:#667eea;">立即添加</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($watches as $watch): ?>
                                <tr>
                                    <?php if ($isAdmin): ?>
                                        <td><input type="checkbox" name="selected_ids[]" value="<?php echo $watch['id']; ?>" class="row-checkbox"></td>
                                    <?php endif; ?>
                                    <td><?php echo $watch['id']; ?></td>
                                    <td class="ca-cell" title="<?php echo htmlspecialchars($watch['ca']); ?>">
                                        <?php echo htmlspecialchars(substr($watch['ca'], 0, 12)) . '...'; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $watch['push_type'] === 'bark' ? 'info' : 'success'; ?>">
                                            <?php echo strtoupper(htmlspecialchars($watch['push_type'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars(substr($watch['push_key'], 0, 10)) . '...'; ?></td>
                                    <td>
                                        <span class="badge badge-info">
                                            <?php echo isset($stepOptions[$watch['step_value']]) ? $stepOptions[$watch['step_value']] : number_format($watch['step_value']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $watch['last_level']; ?></td>
                                    <td><?php echo $watch['current_marketcap'] > 0 ? formatMarketCap($watch['current_marketcap']) : '-'; ?></td>
                                    <td>
                                        <span class="badge badge-<?php echo $watch['status'] ? 'success' : 'danger'; ?>">
                                            <?php echo $watch['status'] ? '正常' : '已关闭'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $watch['created_at']; ?></td>
                                    <td class="action-cell">
                                        <a href="?toggle=<?php echo $watch['id']; ?>" class="btn btn-sm btn-<?php echo $watch['status'] ? 'warning' : 'success'; ?>" 
                                           onclick="return confirm('确定要<?php echo $watch['status'] ? '关闭' : '开启'; ?>此监听吗？')">
                                            <?php echo $watch['status'] ? '关闭' : '开启'; ?>
                                        </a>
                                        <a href="?delete=<?php echo $watch['id']; ?>" class="btn btn-sm btn-danger" 
                                           onclick="return confirm('确定要删除此监听记录吗？此操作不可恢复！')">删除</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </form>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>" class="btn btn-sm">上一页</a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>" 
                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $statusFilter !== '' ? '&status=' . $statusFilter : ''; ?>" class="btn btn-sm">下一页</a>
                <?php endif; ?>
                
                <span class="page-info">第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleSelectAll() {
        const selectAll = document.getElementById('selectAll');
        const checkboxes = document.getElementsByClassName('row-checkbox');
        for (let cb of checkboxes) {
            cb.checked = selectAll.checked;
        }
    }

    function doBatchAction() {
        const action = document.getElementById('batchAction').value;
        if (!action) {
            alert('请选择要执行的操作');
            return;
        }
        
        const checked = document.querySelectorAll('.row-checkbox:checked');
        if (checked.length === 0) {
            alert('请先选择要操作的记录');
            return;
        }

        const actionNames = {
            'enable': '开启',
            'disable': '关闭',
            'delete': '删除'
        };

        if (!confirm(`确定要${actionNames[action]}选中的 ${checked.length} 条记录吗？${action === 'delete' ? '此操作不可恢复！' : ''}`)) {
            return;
        }

        document.getElementById('batchForm').submit();
    }
    </script>
</body>
</html>
