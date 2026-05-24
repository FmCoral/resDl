<?php
/**
 * File upload page — requires login.
 */
require_once __DIR__ . '/inc/init.php';
require_login();

// Check if upload is enabled for non-admins
if (!is_admin() && getSetting('allow_upload', '1') === '0') {
    die('管理员已关闭普通用户上传。');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
        $error = '请选择要上传的文件。';
    } else {
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId  = (int)($_POST['category_id'] ?? getDefaultCategoryId());

        $result = handleUpload($_FILES['file'], (int)$_SESSION['user_id'], $title, $description, $categoryId);
        if (is_string($result)) {
            $error = $result;
        } else {
            $success = '文件 "<strong>' . h($result['title']) . '</strong>" 上传成功！';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>上传文件 - <?= h(getSetting('site_name', 'resDl')) ?></title>
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

<main class="container" style="padding: 40px 0;">
<div class="form" style="max-width:600px;">
<h2 style="margin-bottom: 20px; text-align:center;">上传文件</h2>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="notice">
允许的文件类型：<strong><?= h(getSetting('allowed_extensions', 'zip,rar,7z,pdf,exe,msi,iso,tar,gz')) ?></strong>
<br>上传目录：<strong><?= h(getSetting('user_upload_dir', 'uploads')) ?></strong>
</div>

<form method="post" enctype="multipart/form-data">
<?= csrf_field() ?>
<div class="form-group">
<label>选择文件 *</label>
<input type="file" name="file" class="form-control" required>
</div>
<div class="form-group">
<label>标题（可选，留空则使用文件名）</label>
<input type="text" name="title" class="form-control" maxlength="255">
</div>
<div class="form-group">
<label>描述（可选）</label>
<textarea name="description" class="form-control"></textarea>
</div>
<div class="form-group">
<label>分类</label>
<select name="category_id" class="form-control">
<?= getCategoryOptions(0, 0, getDefaultCategoryId()) ?>
</select>
</div>
<button type="submit" class="btn btn-primary btn-block">上传文件</button>
</form>
</div>
</main>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>

<script>var BASE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
