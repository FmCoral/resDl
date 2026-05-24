<?php
/**
 * User profile — shows user info, uploaded resources, password change.
 */
require_once __DIR__ . '/inc/init.php';
require_login();

$user    = get_session_user();
$error   = '';
$success = '';

// Handle resource deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    require_csrf();

    if ($_POST['action'] === 'delete_resource') {
        $resId = (int)($_POST['resource_id'] ?? 0);
        $resource = getResourceById($resId);
        if ($resource && (int)$resource['uploader_id'] === (int)$_SESSION['user_id']) {
            deleteResourceById($resId, true);
            $success = '资源已删除。';
        } else {
            $error = '无权删除此资源。';
        }
    } elseif ($_POST['action'] === 'change_password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $newPass2 = $_POST['new_password2'] ?? '';

        if (!password_verify($oldPass, $user['password'])) {
            $error = '当前密码错误。';
        } elseif ($newPass !== $newPass2) {
            $error = '两次新密码不一致。';
        } elseif (strlen($newPass) < 6) {
            $error = '新密码长度至少 6 个字符。';
        } else {
            $db = getDB();
            $stmt = $db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['user_id']]);
            $success = '密码修改成功。';
        }
    }
}

// Get user's uploaded resources
$db = getDB();
$stmt = $db->prepare(
    "SELECT r.*, c.name AS category_name FROM resources r
     LEFT JOIN categories c ON r.category_id = c.id
     WHERE r.uploader_id = ? AND r.status = 'active'
     ORDER BY r.created_at DESC"
);
$stmt->execute([$_SESSION['user_id']]);
$myResources = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>个人中心 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="index.php">首页</a>
<a href="upload.php">上传文件</a>
<a href="profile.php">个人中心</a>
<?php if (is_admin()): ?><a href="admin/">后台管理</a><?php endif; ?>
<a href="logout.php">退出</a>
</nav>
</div></header>

<main class="container" style="padding: 32px 0;">

<?php if ($error): ?><div class="alert alert-error alert-dismissible"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-dismissible"><?= h($success) ?></div><?php endif; ?>

<div class="row">
<!-- User Info -->
<div style="flex: 0 0 320px;">
<div class="detail-section">
<h2>账号信息</h2>
<div class="detail-row">
<div class="detail-label">用户名</div>
<div class="detail-value"><?= h($user['username']) ?></div>
</div>
<div class="detail-row">
<div class="detail-label">邮箱</div>
<div class="detail-value"><?= h($user['email']) ?></div>
</div>
<div class="detail-row">
<div class="detail-label">角色</div>
<div class="detail-value"><?= $user['role'] === 'admin' ? '管理员' : '普通用户' ?></div>
</div>
<div class="detail-row">
<div class="detail-label">注册时间</div>
<div class="detail-value"><?= $user['created_at'] ?></div>
</div>
</div>

<div class="detail-section">
<h2>修改密码</h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="change_password">
<div class="form-group">
<label>当前密码</label>
<input type="password" name="old_password" class="form-control" required>
</div>
<div class="form-group">
<label>新密码</label>
<input type="password" name="new_password" class="form-control" required minlength="6">
</div>
<div class="form-group">
<label>确认新密码</label>
<input type="password" name="new_password2" class="form-control" required minlength="6">
</div>
<button type="submit" class="btn btn-primary">修改密码</button>
</form>
</div>
</div>

<!-- My Resources -->
<div style="flex: 1; min-width: 300px;">
<h2 style="margin-bottom: 16px;">我上传的资源 (<?= count($myResources) ?>)</h2>

<?php if (empty($myResources)): ?>
<div class="empty-state">
<div class="empty-icon">&#128194;</div>
<p>您还没有上传过资源。</p>
<p><a href="upload.php">上传第一个文件</a></p>
</div>
<?php else: ?>
<div class="resource-list">
<?php foreach ($myResources as $res): ?>
<div class="resource-card">
<div class="resource-info">
<h3>
<a href="detail.php?id=<?= $res['id'] ?>"><?= h($res['title']) ?></a>
<?php if ($res['is_vip']): ?><span class="vip-badge">&#128274;</span><?php endif; ?>
</h3>
<div class="resource-meta">
<?php if ($res['type'] === 'local'): ?>
<span>&#128190; <?= formatFileSize((int)$res['file_size']) ?></span>
<?php endif; ?>
<span>&#128229; <?= (int)$res['download_count'] ?> 次</span>
<span>&#128197; <?= date('Y-m-d', strtotime($res['created_at'])) ?></span>
</div>
</div>
<div class="resource-actions">
<a href="edit_resource.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-outline">编辑</a>
<form method="post" class="inline-form" onsubmit="return confirm('确定删除此资源？文件将从磁盘中移除。')">
<?= csrf_field() ?>
<input type="hidden" name="action" value="delete_resource">
<input type="hidden" name="resource_id" value="<?= $res['id'] ?>">
<button type="submit" class="btn btn-sm btn-danger">删除</button>
</form>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
</main>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>

<script>var BASE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
