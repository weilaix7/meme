<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
requireAdmin(); // 仅管理员可管理用户

$db = getDB();

// 处理角色变更
if (isset($_POST['action']) && isset($_POST['user_id'])) {
    $userId = intval($_POST['user_id']);
    
    if ($_POST['action'] === 'set_admin') {
        updateUserRole($userId, 'admin');
        header('Location: users.php?updated=1');
        exit;
    } elseif ($_POST['action'] === 'set_vip') {
        updateUserRole($userId, 'vip');
        header('Location: users.php?updated=1');
        exit;
    } elseif ($_POST['action'] === 'set_user') {
        updateUserRole($userId, 'user');
        header('Location: users.php?updated=1');
        exit;
    } elseif ($_POST['action'] === 'delete') {
        deleteUser($userId);
        header('Location: users.php?deleted=1');
        exit;
    }
}

// 处理添加用户（内联）
$addError = '';
$addSuccess = '';
if (isset($_POST['add_user'])) {
    $newUsername = trim($_POST['new_username'] ?? '');
    $newPassword = $_POST['new_password'] ?? '';
    $newRole = $_POST['new_role'] ?? 'user';
    
    if (empty($newUsername) || empty($newPassword)) {
        $addError = '请填写用户名和密码';
    } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 20) {
        $addError = '用户名长度应在 3-20 个字符之间';
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
        $addError = '用户名只能包含字母、数字和下划线';
    } elseif (strlen($newPassword) < 6) {
        $addError = '密码长度不能少于 6 位';
    } elseif (!in_array($newRole, ['user', 'vip', 'admin'])) {
        $addError = '角色无效';
    } else {
        $result = register($newUsername, $newPassword);
        if ($result['success']) {
            // 注册成功后更新角色
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$newUsername]);
            $newUserId = $stmt->fetchColumn();
            if ($newUserId && $newRole !== 'user') {
                updateUserRole($newUserId, $newRole);
            }
            $addSuccess = '用户 ' . htmlspecialchars($newUsername) . ' 添加成功！';
        } else {
            $addError = $result['error'];
        }
    }
}

// 搜索和分页
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';

$where = [];
$params = [];

if ($search) {
    $where[] = "(username LIKE ?)";
    $params[] = "%{$search}%";
}

if ($roleFilter !== '') {
    $where[] = "role = ?";
    $params[] = $roleFilter;
}

$whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";

// 分页
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 获取总数
$countStmt = $db->prepare("SELECT COUNT(*) FROM users {$whereClause}");
$countStmt->execute($params);
$totalCount = $countStmt->fetchColumn();
$totalPages = ceil($totalCount / $perPage);

// 获取用户列表（带分页）
$sql = "SELECT id, username, role, created_at FROM users {$whereClause} ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - MEME 币市值提醒</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="nav-brand">🐸 MEME 币市值提醒</a>
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-link">后台首页</a>
                <a href="add_watch.php" class="nav-link">添加监听</a>
                <a href="profile.php" class="nav-link">个人资料</a>
                <a href="users.php" class="nav-link active">用户管理</a>
                <a href="logout.php" class="nav-link">退出 (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h2>用户管理</h2>
            <button class="btn btn-primary" onclick="toggleAddForm()">+ 添加用户</button>
        </div>

        <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success">用户角色已更新</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-success">用户已删除</div>
        <?php endif; ?>

        <!-- 内联添加用户表单 -->
        <div id="addUserForm" class="card" style="display:none; margin-bottom: 16px;">
            <h3 style="margin-bottom: 16px; font-size: 16px; color: #333;">添加新用户</h3>
            <?php if ($addError): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($addError); ?></div>
            <?php endif; ?>
            <?php if ($addSuccess): ?>
                <div class="alert alert-success"><?php echo $addSuccess; ?></div>
            <?php endif; ?>
            <form method="POST" action="" class="inline-form">
                <div class="inline-form-row">
                    <div class="form-group" style="flex:2;">
                        <input type="text" name="new_username" placeholder="用户名（3-20位字母数字下划线）" required
                               value="<?php echo htmlspecialchars($_POST['new_username'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex:2;">
                        <input type="password" name="new_password" placeholder="密码（至少6位）" required>
                    </div>
                    <div class="form-group" style="flex:1;">
                        <select name="new_role">
                            <option value="user">普通用户</option>
                            <option value="vip">VIP用户</option>
                            <option value="admin">管理员</option>
                        </select>
                    </div>
                    <div class="form-group" style="flex:0 0 auto;">
                        <button type="submit" name="add_user" class="btn btn-primary">添加</button>
                        <button type="button" class="btn btn-secondary" onclick="toggleAddForm()">取消</button>
                    </div>
                </div>
            </form>
        </div>

        <!-- 搜索和筛选 -->
        <div class="filter-bar">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <input type="text" name="search" placeholder="搜索用户名..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="filter-input">
                </div>
                <div class="filter-group" style="flex:0 0 150px;">
                    <select name="role" class="filter-select">
                        <option value="">全部角色</option>
                        <option value="admin" <?php echo $roleFilter === 'admin' ? 'selected' : ''; ?>>管理员</option>
                        <option value="vip" <?php echo $roleFilter === 'vip' ? 'selected' : ''; ?>>VIP用户</option>
                        <option value="user" <?php echo $roleFilter === 'user' ? 'selected' : ''; ?>>普通用户</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">搜索</button>
                <a href="users.php" class="btn btn-secondary">重置</a>
            </form>
        </div>

        <!-- 用户列表 -->
        <div class="table-container">
            <div class="table-toolbar">
                <span class="table-info">共 <?php echo $totalCount; ?> 个用户</span>
            </div>
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>用户名</th>
                        <th>角色</th>
                        <th>监听限制</th>
                        <th>监听数量</th>
                        <th>创建时间</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                        <tr>
                            <td colspan="7" class="text-center">没有找到匹配的用户</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($user['username'] === 'admin'): ?>
                                        <span class="badge badge-info">默认</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo getRoleBadgeClass($user['role']); ?>">
                                        <?php echo getRoleName($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['role'] === 'admin' || $user['role'] === 'vip'): ?>
                                        <span style="color:#2ed573;">不限</span>
                                    <?php else: ?>
                                        <span style="color:#ffa502;">最多 3 个</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                    $watchCount = getUserWatchCount($user['id']);
                                    echo $watchCount;
                                    ?>
                                </td>
                                <td><?php echo $user['created_at']; ?></td>
                                <td class="action-cell">
                                    <?php if ($user['username'] !== 'admin'): ?>
                                        <?php if ($user['role'] === 'user'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="set_vip">
                                                <button type="submit" class="btn btn-sm btn-info" 
                                                        onclick="return confirm('确定要将 <?php echo htmlspecialchars($user['username']); ?> 升级为 VIP 吗？')">
                                                    设为VIP
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="set_admin">
                                                <button type="submit" class="btn btn-sm btn-warning" 
                                                        onclick="return confirm('确定要将 <?php echo htmlspecialchars($user['username']); ?> 提升为管理员吗？')">
                                                    设为管理员
                                                </button>
                                            </form>
                                        <?php elseif ($user['role'] === 'vip'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="set_admin">
                                                <button type="submit" class="btn btn-sm btn-warning" 
                                                        onclick="return confirm('确定要将 <?php echo htmlspecialchars($user['username']); ?> 提升为管理员吗？')">
                                                    设为管理员
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="set_user">
                                                <button type="submit" class="btn btn-sm btn-secondary"
                                                        onclick="return confirm('确定要将 <?php echo htmlspecialchars($user['username']); ?> 降级为普通用户吗？')">
                                                    取消VIP
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="set_vip">
                                                <button type="submit" class="btn btn-sm btn-info" 
                                                        onclick="return confirm('确定要将 <?php echo htmlspecialchars($user['username']); ?> 降级为 VIP 吗？')">
                                                    设为VIP
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="action" value="set_user">
                                                <button type="submit" class="btn btn-sm btn-secondary"
                                                        onclick="return confirm('确定要将 <?php echo htmlspecialchars($user['username']); ?> 降级为普通用户吗？')">
                                                    取消管理员
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-sm btn-danger"
                                                    onclick="return confirm('确定要删除用户 <?php echo htmlspecialchars($user['username']); ?> 吗？')">
                                                删除
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- 分页 -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter !== '' ? '&role=' . $roleFilter : ''; ?>" class="btn btn-sm">上一页</a>
                <?php endif; ?>
                
                <div class="page-numbers">
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter !== '' ? '&role=' . $roleFilter : ''; ?>" 
                           class="page-number <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $roleFilter !== '' ? '&role=' . $roleFilter : ''; ?>" class="btn btn-sm">下一页</a>
                <?php endif; ?>
                
                <span class="page-info">第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
            </div>
        <?php endif; ?>
    </div>

    <script>
    function toggleAddForm() {
        var form = document.getElementById('addUserForm');
        if (form.style.display === 'none' || form.style.display === '') {
            form.style.display = 'block';
        } else {
            form.style.display = 'none';
        }
    }

    // 如果添加表单有错误，自动显示
    <?php if ($addError || $addSuccess): ?>
    document.getElementById('addUserForm').style.display = 'block';
    <?php endif; ?>
    </script>
</body>
</html>
