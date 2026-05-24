<?php
/**
 * Homepage — resource listing with search, category filter, and pagination.
 */
require_once __DIR__ . '/inc/init.php';

$page       = max(1, (int)($_GET['page'] ?? 1));
$search     = trim($_GET['q'] ?? '');
$categoryId = isset($_GET['cat']) ? (int)$_GET['cat'] : null;

$data = getResourcesPage($page, $categoryId, $search);
$items       = $data['items'];
$total       = $data['total'];
$totalPages  = $data['total_pages'];

// Category tree for filter sidebar
$categoryTree = getCategoryTree();

// Build base URL for pagination
$baseParams = [];
if ($search) $baseParams['q'] = $search;
if ($categoryId) $baseParams['cat'] = $categoryId;
$basePaginationUrl = SITE_URL . '/index.php';
if ($baseParams) {
    $basePaginationUrl .= '?' . http_build_query($baseParams);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title><?= $search ? '搜索：' . h($search) . ' - ' : '' ?><?= h(getSetting('site_name', 'resDl')) ?></title>
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

<!-- Search Bar -->
<form method="get" action="index.php" class="search-bar">
<input type="text" name="q" value="<?= h($search) ?>" placeholder="搜索资源名称、描述、分类...">
<button type="submit" class="btn btn-primary">搜索</button>
<?php if ($categoryId): ?><input type="hidden" name="cat" value="<?= $categoryId ?>"><?php endif; ?>
</form>

<!-- Category Filter -->
<?php if ($categoryTree): ?>
<div style="margin-bottom: 16px; display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
<strong style="font-size:14px;">分类：</strong>
<a href="index.php<?= $search ? '?q=' . urlencode($search) : '' ?>" class="btn btn-sm <?= !$categoryId ? 'btn-primary' : 'btn-outline' ?>">全部</a>
<?php
function renderCatFilterLinks(array $cats, int $depth, string $search, ?int $activeId): string {
    $html = '';
    foreach ($cats as $cat) {
        $isActive = ($activeId === (int)$cat['id']);
        $url = 'index.php?cat=' . $cat['id'];
        if ($search) $url .= '&q=' . urlencode($search);
        $prefix = $depth > 0 ? str_repeat('&nbsp;', $depth * 2) : '';
        $html .= '<a href="' . $url . '" class="btn btn-sm ' . ($isActive ? 'btn-primary' : 'btn-outline') . '" style="font-size:12px;">' . $prefix . h($cat['name']) . '</a>';
        if (!empty($cat['children'])) {
            $html .= renderCatFilterLinks($cat['children'], $depth + 1, $search, $activeId);
        }
    }
    return $html;
}
echo renderCatFilterLinks($categoryTree, 0, $search, $categoryId);
?>
</div>
<?php endif; ?>

<!-- Stats -->
<div style="font-size:14px; color:#888; margin-bottom:16px;">
共 <?= $total ?> 个资源
<?php if ($search): ?> — 搜索 "<strong><?= h($search) ?></strong>"<?php endif; ?>
<?php if ($categoryId): ?> — 分类筛选<?php endif; ?>
</div>

<!-- Resource List -->
<?php if (empty($items)): ?>
<div class="empty-state">
<div class="empty-icon">&#128194;</div>
<p>暂无资源。</p>
<?php if ($search): ?><p>没有找到匹配 "<strong><?= h($search) ?></strong>" 的资源。</p><?php endif; ?>
</div>
<?php else: ?>
<div class="resource-list">
<?php foreach ($items as $res): ?>
<div class="resource-card">
<div class="resource-icon">
<?php if ($res['type'] === 'external'): ?>
&#128279;
<?php else: ?>
&#128196;
<?php endif; ?>
</div>
<div class="resource-info">
<h3>
<a href="detail.php?id=<?= $res['id'] ?>"><?= h($res['title']) ?></a>
<?php if ($res['is_vip']): ?>
<span class="vip-badge" title="VIP资源，需要密码下载">&#128274;</span>
<?php endif; ?>
<?php if ($res['type'] === 'external'): ?>
<span class="external-badge">外链</span>
<?php endif; ?>
</h3>
<div class="resource-meta">
<?php if ($res['type'] === 'local' && $res['file_size'] > 0): ?>
<span>&#128190; <?= formatFileSize((int)$res['file_size']) ?></span>
<?php endif; ?>
<span>&#128229; <?= (int)$res['download_count'] ?> 次下载</span>
<?php if ($res['category_name']): ?>
<span>&#128193; <?= h($res['category_name']) ?></span>
<?php endif; ?>
<?php if ($res['uploader_name']): ?>
<span>&#128100; <?= h($res['uploader_name']) ?></span>
<?php endif; ?>
<span>&#128197; <?= date('Y-m-d', strtotime($res['created_at'])) ?></span>
</div>
</div>
<div class="resource-actions">
<?php if ($res['is_vip'] && !isVipVerified((int)$res['id'])): ?>
<button class="btn btn-sm btn-outline vip-download-btn"
data-resource-id="<?= $res['id'] ?>"
data-return-url="<?= SITE_URL . '/download.php?id=' . $res['id'] ?>">
&#128274; VIP下载
</button>
<?php else: ?>
<a href="download.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-primary">
<?= $res['type'] === 'external' ? '前往' : '下载' ?>
</a>
<?php endif; ?>
<a href="detail.php?id=<?= $res['id'] ?>" class="btn btn-sm btn-outline">详情</a>
</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?= paginationLinks($page, $totalPages, $basePaginationUrl) ?>
</main>

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
<input type="password" id="vip-password-input" class="form-control" required autofocus placeholder="输入密码...">
</div>
<button type="submit" class="btn btn-primary btn-block">验证并下载</button>
</form>
</div>
</div>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>

<script>var SITE_URL = '<?= SITE_URL ?>';</script>
<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
