<?php
/**
 * Admin — Scan Directory Management
 */
require_once __DIR__ . '/../inc/init.php';
require_admin();

$error   = '';
$success = '';
$db = getDB();

$scanMode = getSetting('scan_mode', 'fixed');
$fixedDir = getSetting('fixed_scan_dir', 'uploads');
$specifiedDirs = json_decode(getSetting('specified_scan_dirs', '[]'), true) ?: [];
$userUploadDir = getSetting('user_upload_dir', 'uploads');
$lastScan = (int)getSetting('last_scan_time', '0');
$syncTtl = (int)getSetting('sync_cache_ttl', '120');
$scanBatchSize = (int)getSetting('scan_batch_size', '200');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'save_mode') {
        $mode = $_POST['scan_mode'] ?? 'fixed';
        updateSetting('scan_mode', $mode);
        $success = '扫描模式已更新。';
        $scanMode = $mode;
    } elseif ($action === 'save_fixed') {
        $dir = trim($_POST['fixed_scan_dir'] ?? 'uploads');
        updateSetting('fixed_scan_dir', $dir);
        $success = '固定扫描目录已更新。';
        $fixedDir = $dir;
    } elseif ($action === 'add_specified') {
        $dir = trim($_POST['specified_dir'] ?? '');
        if (!empty($dir)) {
            $absDir = resolvePath($dir);
            if (!is_dir($absDir)) {
                $error = '目录不存在：' . $absDir;
            } else {
                $dirs = json_decode(getSetting('specified_scan_dirs', '[]'), true) ?: [];
                if (!in_array($dir, $dirs, true)) {
                    $dirs[] = $dir;
                    updateSetting('specified_scan_dirs', json_encode($dirs, JSON_UNESCAPED_UNICODE));
                    $specifiedDirs = $dirs;
                    $success = '目录已添加。';
                } else {
                    $error = '该目录已在列表中。';
                }
            }
        }
    } elseif ($action === 'remove_specified') {
        $dir = $_POST['dir'] ?? '';
        $dirs = json_decode(getSetting('specified_scan_dirs', '[]'), true) ?: [];
        $dirs = array_values(array_filter($dirs, fn($d) => $d !== $dir));
        updateSetting('specified_scan_dirs', json_encode($dirs, JSON_UNESCAPED_UNICODE));
        $specifiedDirs = $dirs;
        $success = '目录已移除。';
    } elseif ($action === 'force_scan') {
        // Force full scan (reset cache time)
        $result = forceFullScan();
        $success = $result['message'] . ' — 新增: ' . $result['synced'] . ' 删除: ' . $result['deleted'];
        $lastScan = (int)getSetting('last_scan_time', '0');
    } elseif ($action === 'save_advanced') {
        $ttl = max(0, (int)($_POST['sync_cache_ttl'] ?? 120));
        $batchSize = max(50, (int)($_POST['scan_batch_size'] ?? 200));
        $uploadDir = trim($_POST['user_upload_dir'] ?? 'uploads');
        updateSetting('sync_cache_ttl', (string)$ttl);
        updateSetting('scan_batch_size', (string)$batchSize);
        updateSetting('user_upload_dir', $uploadDir);
        $success = '高级设置已更新。';
        $syncTtl = $ttl;
        $scanBatchSize = $batchSize;
        $userUploadDir = $uploadDir;
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>扫描目录管理 - <?= h(getSetting('site_name', 'resDl')) ?></title>
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
<a href="scans.php" class="active">扫描目录</a>
<a href="settings.php">系统设置</a>
<a href="users.php">用户管理</a>
</aside>

<main class="admin-main">
<h1>扫描目录管理</h1>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<!-- Scan Status -->
<div class="stat-grid" style="margin-bottom:24px;">
<div class="stat-card">
<div class="stat-number"><?= getSetting('scan_mode') === 'fixed' ? '固定' : '指定' ?></div>
<div class="stat-label">当前扫描模式</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $syncTtl ?>s</div>
<div class="stat-label">缓存间隔</div>
</div>
<div class="stat-card">
<div class="stat-number"><?= $lastScan > 0 ? date('H:i:s', $lastScan) : '—' ?></div>
<div class="stat-label">上次扫描</div>
</div>
</div>

<!-- Scan Mode -->
<div class="detail-section" style="margin-bottom:20px;">
<h2>扫描模式</h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save_mode">
<div class="form-group">
<label><input type="radio" name="scan_mode" value="fixed" <?= $scanMode === 'fixed' ? 'checked' : '' ?> onchange="this.form.submit()"> 固定目录模式</label>
&nbsp;&nbsp;
<label><input type="radio" name="scan_mode" value="specified" <?= $scanMode === 'specified' ? 'checked' : '' ?> onchange="this.form.submit()"> 指定目录模式</label>
</div>
</form>
</div>

<?php if ($scanMode === 'fixed'): ?>
<!-- Fixed Directory -->
<div class="detail-section" style="margin-bottom:20px;">
<h2>固定扫描目录</h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save_fixed">
<div class="form-group">
<label>目录路径（相对于站点根目录或绝对路径）</label>
<input type="text" name="fixed_scan_dir" class="form-control" value="<?= h($fixedDir) ?>">
</div>
<button type="submit" class="btn btn-primary">保存</button>
</form>
<p style="font-size:13px; color:#888; margin-top:8px;">解析后路径：<code><?= h(resolvePath($fixedDir)) ?></code></p>
</div>
<?php else: ?>
<!-- Specified Directories -->
<div class="detail-section" style="margin-bottom:20px;">
<h2>指定扫描目录</h2>
<?php if (!empty($specifiedDirs)): ?>
<table class="table" style="margin-bottom:16px;">
<thead><tr><th>路径</th><th>解析后</th><th>存在</th><th>操作</th></tr></thead>
<tbody>
<?php foreach ($specifiedDirs as $dir): ?>
<?php $abs = resolvePath($dir); ?>
<tr>
<td><?= h($dir) ?></td>
<td><code style="font-size:12px;"><?= h($abs) ?></code></td>
<td><?= is_dir($abs) ? '&#9989;' : '&#10060; 不存在' ?></td>
<td>
<form method="post" class="inline-form">
<?= csrf_field() ?>
<input type="hidden" name="action" value="remove_specified">
<input type="hidden" name="dir" value="<?= h($dir) ?>">
<button type="submit" class="btn btn-sm btn-danger">移除</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php else: ?>
<div class="empty-state"><p>未添加任何指定目录。</p></div>
<?php endif; ?>

<form method="post" style="display:flex; gap:8px; align-items:flex-end;">
<?= csrf_field() ?>
<input type="hidden" name="action" value="add_specified">
<div class="form-group" style="flex:1; margin:0;">
<label>添加目录</label>
<input type="text" name="specified_dir" class="form-control" placeholder="上传到 /www/wwwroot/site/downloads">
</div>
<button type="submit" class="btn btn-primary">添加</button>
</form>
</div>
<?php endif; ?>

<!-- Force Scan -->
<div class="detail-section" style="margin-bottom:20px;">
<h2>手动扫描</h2>
<p style="color:#888; margin-bottom:12px; font-size:14px;">立即执行一次完整目录扫描，忽略缓存 TTL。</p>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="force_scan">
<button type="submit" class="btn btn-primary" onclick="return confirm('扫描可能耗时较长，确认执行？')">立即扫描</button>
</form>
<p style="font-size:13px; color:#888; margin-top:8px;">
上次扫描时间：<?= $lastScan > 0 ? date('Y-m-d H:i:s', $lastScan) : '从未扫描' ?><br>
建议每页扫描文件数：<?= $scanBatchSize ?>（大目录分批扫描，避免超时）
</p>
</div>

<!-- Advanced Settings -->
<div class="detail-section">
<h2>高级设置</h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save_advanced">
<div class="form-group">
<label>扫描缓存时间（秒）</label>
<input type="number" name="sync_cache_ttl" class="form-control" value="<?= $syncTtl ?>" min="0">
<span style="font-size:12px; color:#888;">设为 0 则每次请求都扫描。建议 120-3600 秒。</span>
</div>
<div class="form-group">
<label>每批扫描文件数</label>
<input type="number" name="scan_batch_size" class="form-control" value="<?= $scanBatchSize ?>" min="50" max="1000">
<span style="font-size:12px; color:#888;">大目录分批处理，避免 PHP 超时。默认 200。</span>
</div>
<div class="form-group">
<label>用户上传存储目录</label>
<input type="text" name="user_upload_dir" class="form-control" value="<?= h($userUploadDir) ?>">
</div>
<button type="submit" class="btn btn-primary">保存</button>
</form>
</div>
</main>
</div>
</body>
</html>
