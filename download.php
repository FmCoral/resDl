<?php
/**
 * Download handler — increments count, checks VIP password, serves file or redirects.
 */
require_once __DIR__ . '/inc/init.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('资源不存在。');
}

$resource = getResourceById($id);
if (!$resource || $resource['status'] !== 'active') {
    http_response_code(404);
    die('资源不存在或已删除。');
}

// VIP check
if ($resource['is_vip'] && !isVipVerified((int)$resource['id'])) {
    // Not verified — show password form instead of redirecting
    // For external resources, this prevents link leaking
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>VIP密码验证 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
</div></header>
<main class="container" style="padding:60px 0; max-width:480px;">
<div class="detail-section">
<h2>&#128274; 此资源需要密码</h2>
<p style="color:#888; margin-bottom:16px;">资源：<strong><?= h($resource['title']) ?></strong></p>
<div class="alert alert-error" id="vip-modal-error" style="display:none;"></div>
<form id="vip-form-inline">
<input type="hidden" id="vip-resource-id" value="<?= $resource['id'] ?>">
<input type="hidden" id="vip-return-url" value="<?= SITE_URL . '/download.php?id=' . $resource['id'] ?>">
<div class="form-group">
<label>请输入下载密码</label>
<input type="password" id="vip-password-input" class="form-control" required autofocus>
</div>
<button type="submit" class="btn btn-primary btn-block">验证并下载</button>
</form>
<p style="text-align:center; margin-top:12px;"><a href="detail.php?id=<?= $resource['id'] ?>">返回详情页</a></p>
</div>
</main>
<footer class="site-footer"><div class="container">&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?></div></footer>
<script>var SITE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
    <?php
    exit;
}

// Increment download count (only on initial request, not Range)
if (!isset($_SERVER['HTTP_RANGE'])) {
    incrementDownloadCount((int)$resource['id']);
}

// Handle external resources
if ($resource['type'] === 'external') {
    if (!empty($resource['external_url'])) {
        // For VIP external resources, show intermediate page instead of 302 redirect
        // This prevents link leakage
        if ($resource['is_vip']) {
            ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>外链跳转 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
</div></header>
<main class="container" style="padding:60px 0; max-width:600px; text-align:center;">
<div class="detail-section">
<h2>外部资源链接</h2>
<p style="margin:16px 0;">资源：<strong><?= h($resource['title']) ?></strong></p>
<div class="notice">此链接为VIP资源，请勿将链接分享给他人。</div>
<p><a href="<?= h($resource['external_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-primary">打开外部链接</a></p>
<p style="margin-top:12px; font-size:13px; color:#888;">链接将在新窗口中打开。</p>
<p style="margin-top:12px;"><a href="detail.php?id=<?= $resource['id'] ?>">返回详情页</a></p>
</div>
</main>
<footer class="site-footer"><div class="container">&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?></div></footer>
</body>
</html>
            <?php
            exit;
        }
        header('Location: ' . $resource['external_url']);
        exit;
    }
    http_response_code(404);
    die('外链地址为空。');
}

// Handle local file download
if (empty($resource['local_path'])) {
    http_response_code(404);
    die('文件路径为空。');
}

serveFileDownload($resource);
