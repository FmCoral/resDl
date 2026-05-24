<?php
/**
 * Admin — User Management
 */
require_once __DIR__ . '/../inc/init.php';
require_admin();

$error   = '';
$success = '';
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'change_role') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newRole = $_POST['role'] ?? 'user';
        if ($userId > 0 && in_array($newRole, ['user', 'admin'], true)) {
            if ($userId === (int)$_SESSION['user_id']) {
                $error = '不能修改自己的角色。';
            } else {
                $db->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$newRole, $userId]);
                $success = '用户角色已更新。';
            }
        }
    } elseif ($action === 'reset_password') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $newPassword = trim($_POST['new_password'] ?? '');
        if ($userId > 0 && strlen($newPassword) >= 6) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $db->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$hash, $userId]);
            $success = '密码已重置。';
        } elseif (strlen($newPassword) < 6) {
            $error = '密码长度至少 6 个字符。';
        }
    } elseif ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId === (int)$_SESSION['user_id']) {
            $error = '不能删除自己的账号。';
        } elseif ($userId > 0) {
            // Reassign resources to admin or set uploader to null
            $db->prepare('UPDATE resources SET uploader_id = ? WHERE uploader_id = ?')->execute([$_SESSION['user_id'], $userId]);
            $db->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
            $success = '用户已删除，其上传的资源已转移给当前管理员。';
        }
    }
}

$users = $db->query('SELECT * FROM users ORDER BY created_at DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>用户管理 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="../index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="../index.php">前台首页</a>
<a href="index.php">后台</a>
<a href="../logout.php">退出</a>
</nav>
</div></header>

<div class="admin-layout">
<aside class="admin-sidebar">
<div class="sidebar-title">管理菜单</div>
<a href="index.php">仪表盘</a>
<a href="categories.php">分类管理</a>
<a href="resources.php">资源管理</a>
<a href="scans.php">扫描目录</a>
<a href="settings.php">系统设置</a>
<a href="users.php" class="active">用户管理</a>
</aside>

<main class="admin-main">
<h1>用户管理</h1>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<?php if (empty($users)): ?>
<div class="empty-state"><p>暂无用户。</p></div>
<?php else: ?>
<table class="table">
<thead>
<tr>
<th>ID</th>
<th>用户名</th>
<th>邮箱</th>
<th>角色</th>
<th>注册时间</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php foreach ($users as $user): ?>
<tr>
<td><?= $user['id'] ?></td>
<td><?= h($user['username']) ?></td>
<td><?= h($user['email']) ?></td>
<td>
<form method="post" class="inline-form" id="role-form-<?= $user['id'] ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="change_role">
<input type="hidden" name="user_id" value="<?= $user['id'] ?>">
<select name="role" onchange="if(confirm('确定修改角色？')){document.getElementById('role-form-<?= $user['id'] ?>').submit();}" <?= (int)$user['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
<option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>普通用户</option>
<option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>管理员</option>
</select>
</form>
</td>
<td><?= date('Y-m-d', strtotime($user['created_at'])) ?></td>
<td class="actions">
<!-- Reset Password -->
<button class="btn btn-sm btn-outline" onclick="resetPwd(<?= $user['id'] ?>, '<?= h($user['username']) ?>')">重置密码</button>
<!-- Delete (not self) -->
<?php if ((int)$user['id'] !== (int)$_SESSION['user_id']): ?>
<form method="post" class="inline-form" onsubmit="return confirm('确定删除用户 <?= h($user['username']) ?>？其上传的资源将转移给当前管理员。')">
<?= csrf_field() ?>
<input type="hidden" name="action" value="delete_user">
<input type="hidden" name="user_id" value="<?= $user['id'] ?>">
<button type="submit" class="btn btn-sm btn-danger">删除</button>
</form>
<?php endif; ?>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</main>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="reset-pwd-modal">
<div class="modal-box">
<button class="close-btn" onclick="document.getElementById('reset-pwd-modal').classList.remove('active')">&times;</button>
<h2>重置密码</h2>
<p id="reset-pwd-user" style="margin-bottom:12px; color:#888;"></p>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="reset_password">
<input type="hidden" name="user_id" id="reset-pwd-user-id">
<div class="form-group">
<label>新密码</label>
<input type="text" name="new_password" class="form-control" required minlength="6">
</div>
<button type="submit" class="btn btn-primary btn-block">重置</button>
</form>
</div>
</div>

<script>
function resetPwd(userId, username) {
document.getElementById('reset-pwd-user-id').value = userId;
document.getElementById('reset-pwd-user').textContent = '用户：' + username;
document.getElementById('reset-pwd-modal').classList.add('active');
}
</script>
</body>
</html>
