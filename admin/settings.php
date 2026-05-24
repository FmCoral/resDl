<?php
/**
 * Admin — System Settings
 */
require_once __DIR__ . '/../inc/init.php';
require_admin();

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $settings = [
        'site_url'           => rtrim(trim($_POST['site_url'] ?? ''), '/'),
        'items_per_page'     => max(5, (int)($_POST['items_per_page'] ?? 10)),
        'allowed_extensions' => strtolower(trim($_POST['allowed_extensions'] ?? 'zip,rar,7z,pdf,exe,msi,iso,tar,gz')),
        'allow_registration' => isset($_POST['allow_registration']) ? '1' : '0',
        'allow_upload'       => isset($_POST['allow_upload']) ? '1' : '0',
        'site_name'          => trim($_POST['site_name'] ?? 'resDl 资源下载站'),
    ];

    foreach ($settings as $key => $value) {
        updateSetting($key, (string)$value);
    }

    $success = '设置已保存。';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>系统设置 - <?= h(getSetting('site_name', 'resDl')) ?></title>
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
<a href="settings.php" class="active">系统设置</a>
<a href="users.php">用户管理</a>
</aside>

<main class="admin-main">
<h1>系统设置</h1>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<form method="post">
<?= csrf_field() ?>

<div class="detail-section" style="margin-bottom:20px;">
<h2>基本设置</h2>
<div class="form-group">
<label>站点名称</label>
<input type="text" name="site_name" class="form-control" value="<?= h(getSetting('site_name', 'resDl 资源下载站')) ?>">
</div>
<div class="form-group">
<label>站点 URL（用于生成绝对链接）</label>
<input type="url" name="site_url" class="form-control" value="<?= h(SITE_URL) ?>" placeholder="https://yourdomain.com">
<span style="font-size:12px; color:#888;">不含尾部斜杠。修改后立即生效，影响所有下载链接、详情页链接。留空则使用 config.php 中的默认值。</span>
</div>
<div class="form-group">
<label>每页显示资源数</label>
<input type="number" name="items_per_page" class="form-control" value="<?= h(getSetting('items_per_page', '10')) ?>" min="5" max="100">
</div>
</div>

<div class="detail-section" style="margin-bottom:20px;">
<h2>上传设置</h2>
<div class="form-group">
<label>允许上传的文件扩展名（逗号分隔）</label>
<input type="text" name="allowed_extensions" class="form-control" value="<?= h(getSetting('allowed_extensions', 'zip,rar,7z,pdf,exe,msi,iso,tar,gz')) ?>">
<span style="font-size:12px; color:#888;">例如：zip,rar,7z,pdf,exe,msi,iso,tar,gz</span>
</div>
<div class="form-group">
<label><input type="checkbox" name="allow_upload" value="1" <?= getSetting('allow_upload', '1') === '1' ? 'checked' : '' ?>> 允许普通用户上传文件</label>
</div>
</div>

<div class="detail-section" style="margin-bottom:20px;">
<h2>注册设置</h2>
<div class="form-group">
<label><input type="checkbox" name="allow_registration" value="1" <?= getSetting('allow_registration', '1') === '1' ? 'checked' : '' ?>> 允许新用户注册</label>
</div>
</div>

<button type="submit" class="btn btn-primary">保存所有设置</button>
</form>
</main>
</div>
</body>
</html>
