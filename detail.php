<?php
/**
 * Resource detail page.
 */
require_once __DIR__ . '/inc/init.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('资源不存在。');
}

$resource = getResourceById($id);
if (!$resource || ($resource['status'] !== 'active' && !is_admin())) {
    http_response_code(404);
    die('资源不存在或已删除。');
}

$uploader = null;
if ($resource['uploader_id']) {
    $db = getDB();
    $stmt = $db->prepare('SELECT username FROM users WHERE id = ?');
    $stmt->execute([$resource['uploader_id']]);
    $uploader = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title><?= h($resource['title']) ?> - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="index.php">首页</a>
<?php if (check_login()): ?>
<a href="upload.php">上传文件</a>
<a href="profile.php">个人中心</a>
<?php if (is_admin()): ?>
<a href="admin/">后台管理</a>
<?php endif; ?>
<a href="logout.php">退出</a>
<?php else: ?>
<a href="login.php">登录</a>
<a href="register.php">注册</a>
<?php endif; ?>
</nav>
</div></header>

<main class="container">

<div class="detail-section">
<h2><?= h($resource['title']) ?>
<?php if ($resource['is_vip']): ?>
<span class="vip-badge" title="VIP资源">&#128274;</span>
<?php endif; ?>
<?php if ($resource['type'] === 'external'): ?>
<span class="external-badge">外链资源</span>
<?php endif; ?>
</h2>

<div class="detail-row">
<div class="detail-label">文件类型</div>
<div class="detail-value"><?= $resource['type'] === 'local' ? '本地文件' : '外链资源' ?></div>
</div>

<?php if ($resource['type'] === 'local' && $resource['file_size'] > 0): ?>
<div class="detail-row">
<div class="detail-label">文件大小</div>
<div class="detail-value"><?= formatFileSize((int)$resource['file_size']) ?></div>
</div>
<?php endif; ?>

<?php if ($resource['type'] === 'local'): ?>
<div class="detail-row">
<div class="detail-label">扩展名</div>
<div class="detail-value"><?= getExtension($resource['local_path'] ?? '') ?: '—' ?></div>
</div>
<?php endif; ?>

<div class="detail-row">
<div class="detail-label">下载次数</div>
<div class="detail-value"><?= (int)$resource['download_count'] ?> 次</div>
</div>

<?php if ($uploader): ?>
<div class="detail-row">
<div class="detail-label">上传者</div>
<div class="detail-value"><?= h($uploader['username']) ?></div>
</div>
<?php endif; ?>

<div class="detail-row">
<div class="detail-label">分类</div>
<div class="detail-value"><?= h($resource['category_name'] ?? '未分类') ?></div>
</div>

<div class="detail-row">
<div class="detail-label">上传时间</div>
<div class="detail-value"><?= date('Y-m-d H:i:s', strtotime($resource['created_at'])) ?></div>
</div>

<?php if (!empty($resource['description'])): ?>
<div class="detail-row">
<div class="detail-label">描述</div>
<div class="detail-value"><?= nl2br(h($resource['description'])) ?></div>
</div>
<?php endif; ?>

<?php if ($resource['is_vip']): ?>
<div class="detail-row">
<div class="detail-label">状态</div>
<div class="detail-value">
<span style="color:#e67e22;">此资源为VIP资源，需要独立密码才能下载。</span>
<?php if (isVipVerified((int)$resource['id'])): ?>
<span style="color:green;">（已通过验证）</span>
<?php endif; ?>
</div>
</div>
<?php endif; ?>
</div>

<div style="margin-top: 20px; display: flex; gap: 12px;">
<?php if ($resource['is_vip'] && !isVipVerified((int)$resource['id'])): ?>
<button class="btn btn-primary vip-download-btn"
data-resource-id="<?= $resource['id'] ?>"
data-return-url="<?= SITE_URL . '/download.php?id=' . $resource['id'] ?>">
&#128274; 输入密码下载
</button>
<?php else: ?>
<a href="download.php?id=<?= $resource['id'] ?>" class="btn btn-primary">
<?= $resource['type'] === 'external' ? '前往外部链接' : '下载文件' ?>
</a>
<?php endif; ?>
<a href="index.php" class="btn btn-outline">返回列表</a>
</div>

<!-- VIP Password Modal -->
<div class="modal-overlay" id="vip-modal">
<div class="modal-box">
<button class="close-btn" id="vip-modal-close">&times;</button>
<h2>&#128274; VIP 资源密码验证</h2>
<div class="alert alert-error" id="vip-modal-error" style="display:none;"></div>
<form id="vip-form">
<input type="hidden" id="vip-resource-id">
<input type="hidden" id="vip-return-url">
<div class="form-group">
<label>请输入该资源的下载密码</label>
<input type="password" id="vip-password-input" class="form-control" required autofocus>
</div>
<button type="submit" class="btn btn-primary btn-block">验证并下载</button>
</form>
</div>
</div>

</main>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>

<script>var SITE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
