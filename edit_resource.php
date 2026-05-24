<?php
/**
 * Edit resource — users can edit their own uploads.
 */
require_once __DIR__ . '/inc/init.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$resource = getResourceById($id);

if (!$resource || $resource['status'] !== 'active') {
    http_response_code(404);
    die('资源不存在。');
}

// Only the uploader or admin can edit
if ((int)$resource['uploader_id'] !== (int)$_SESSION['user_id'] && !is_admin()) {
    http_response_code(403);
    die('无权编辑此资源。');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $title       = trim($_POST['title'] ?? $resource['title']);
    $description = trim($_POST['description'] ?? '');
    $categoryId  = (int)($_POST['category_id'] ?? $resource['category_id']);

    $db = getDB();
    $stmt = $db->prepare('UPDATE resources SET title = ?, description = ?, category_id = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$title, $description, $categoryId, $id]);

    // Admin can also update VIP status and password
    if (is_admin()) {
        $isVip      = isset($_POST['is_vip']) ? 1 : 0;
        $vipPassword = trim($_POST['vip_password'] ?? '');
        if ($isVip && !empty($vipPassword)) {
            $hash = password_hash($vipPassword, PASSWORD_DEFAULT);
            $stmt = $db->prepare('UPDATE resources SET is_vip = 1, vip_password = ? WHERE id = ?');
            $stmt->execute([$hash, $id]);
            // Invalidate VIP sessions for this resource
            unset($_SESSION['vip_pass_' . $id]);
        } elseif (!$isVip) {
            $stmt = $db->prepare('UPDATE resources SET is_vip = 0, vip_password = NULL WHERE id = ?');
            $stmt->execute([$id]);
            unset($_SESSION['vip_pass_' . $id]);
        } elseif ($isVip && empty($vipPassword)) {
            // Keep existing VIP password
        }
    }

    $success = '资源信息已更新。';
    $resource = getResourceById($id); // Refresh
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>编辑资源 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="index.php">首页</a>
<a href="profile.php">个人中心</a>
<a href="logout.php">退出</a>
</nav>
</div></header>

<main class="container" style="padding: 40px 0;">
<div class="form" style="max-width:600px;">
<h2 style="margin-bottom: 20px;">编辑资源：<?= h($resource['title']) ?></h2>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<form method="post">
<?= csrf_field() ?>
<div class="form-group">
<label>标题</label>
<input type="text" name="title" class="form-control" value="<?= h($resource['title']) ?>" required maxlength="255">
</div>
<div class="form-group">
<label>描述</label>
<textarea name="description" class="form-control"><?= h($resource['description']) ?></textarea>
</div>
<div class="form-group">
<label>分类</label>
<select name="category_id" class="form-control">
<?= getCategoryOptions(0, 0, (int)$resource['category_id']) ?>
</select>
</div>

<?php if (is_admin()): ?>
<fieldset style="border:1px solid #e0e0e0; border-radius:8px; padding:16px; margin-bottom:16px;">
<legend style="font-weight:600; font-size:14px;">VIP 设置（管理员）</legend>
<div class="form-group">
<label>
<input type="checkbox" name="is_vip" value="1" <?= $resource['is_vip'] ? 'checked' : '' ?>>
设为 VIP 资源
</label>
</div>
<div class="form-group">
<label>VIP 密码（留空则保持不变）</label>
<input type="text" name="vip_password" class="form-control" placeholder="新密码，留空则不修改">
</div>
</fieldset>
<?php endif; ?>

<button type="submit" class="btn btn-primary btn-block">保存修改</button>
</form>
<p style="text-align:center; margin-top:12px;"><a href="profile.php">返回个人中心</a></p>
</div>
</main>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>
</body>
</html>
