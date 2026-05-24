<?php
/**
 * Admin Dashboard
 */
require_once __DIR__ . '/../inc/init.php';
require_admin();

$db = getDB();

// Statistics
$totalResources   = $db->query("SELECT COUNT(*) FROM resources WHERE status = 'active'")->fetchColumn();
$totalLocal       = $db->query("SELECT COUNT(*) FROM resources WHERE type = 'local' AND status = 'active'")->fetchColumn();
$totalExternal    = $db->query("SELECT COUNT(*) FROM resources WHERE type = 'external' AND status = 'active'")->fetchColumn();
$totalVip         = $db->query("SELECT COUNT(*) FROM resources WHERE is_vip = 1 AND status = 'active'")->fetchColumn();
$totalUsers       = $db->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalDownloads   = $db->query('SELECT SUM(download_count) FROM resources')->fetchColumn() ?: 0;
$totalCategories  = $db->query('SELECT COUNT(*) FROM categories')->fetchColumn();
$lastScan         = getSetting('last_scan_time', '0');
$scanMode         = getSetting('scan_mode', 'fixed');
$syncTtl          = getSetting('sync_cache_ttl', '120');

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>后台管理 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="../index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="../index.php">前台首页</a>
<a href="index.php" style="color:#1a73e8;">后台</a>
<a href="../logout.php">退出</a>
</nav>
</div></header>

<div class="admin-layout">
<aside class="admin-sidebar">
<div class="sidebar-title">管理菜单</div>
<a href="index.php" class="active">仪表盘</a>
<a href="categories.php">分类管理</a>
<a href="resources.php">资源管理</a>
<a href="scans.php">扫描目录</a>
<a href="settings.php">系统设置</a>
<a href="users.php">用户管理</a>
</aside>

<main class="admin-main">
<h1>仪表盘</h1>

<div class="stat-grid">
<div class="stat-card">
<div class="stat-number"><?= $totalResources ?></div>
<div class="stat-label">总资源数</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $totalLocal ?></div>
<div class="stat-label">本地文件</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $totalExternal ?></div>
<div class="stat-label">外链资源</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $totalVip ?></div>
<div class="stat-label">VIP 资源</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $totalUsers ?></div>
<div class="stat-label">用户数</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= number_format((int)$totalDownloads) ?></div>
<div class="stat-label">总下载次数</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $totalCategories ?></div>
<div class="stat-label">分类数</div>
</div>
</div>

<div class="detail-section">
<h2>系统状态</h2>
<div class="detail-row">
<div class="detail-label">扫描模式</div>
<div class="detail-value"><?= $scanMode === 'fixed' ? '固定目录' : '指定目录' ?></div>
</div>
<div class="detail-row">
<div class="detail-label">扫描缓存 TTL</div>
<div class="detail-value"><?= $syncTtl ?> 秒</div>
</div>
<div class="detail-row">
<div class="detail-label">上次扫描</div>
<div class="detail-value"><?= $lastScan > 0 ? date('Y-m-d H:i:s', (int)$lastScan) : '从未扫描' ?></div>
</div>
<div class="detail-row">
<div class="detail-label">PHP 版本</div>
<div class="detail-value"><?= phpversion() ?></div>
</div>
</div>
</main>
</div>
</body>
</html>
