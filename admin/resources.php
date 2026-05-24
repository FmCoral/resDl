<?php
/**
 * Admin — Resource Management
 */
require_once __DIR__ . '/../inc/init.php';
require_admin();

$error   = '';
$success = '';
$db = getDB();

$page = max(1, (int)($_GET['page'] ?? 1));
$filter = $_GET['filter'] ?? 'all'; // all, local, external, vip
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Build query
$where = ["r.status != 'deleted' OR r.status IS NULL"];
$params = [];

if ($filter === 'local') { $where[] = "r.type = 'local'"; }
elseif ($filter === 'external') { $where[] = "r.type = 'external'"; }
elseif ($filter === 'vip') { $where[] = "r.is_vip = 1"; }

$search = trim($_GET['q'] ?? '');
if ($search) {
    $where[] = 'r.title LIKE ?';
    $params[] = '%' . $search . '%';
}

$whereClause = implode(' AND ', $where);

$totalStmt = $db->prepare("SELECT COUNT(*) FROM resources r WHERE $whereClause");
$totalStmt->execute($params);
$total = (int)$totalStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));

$stmt = $db->prepare(
    "SELECT r.*, u.username AS uploader_name, c.name AS category_name
     FROM resources r
     LEFT JOIN users u ON r.uploader_id = u.id
     LEFT JOIN categories c ON r.category_id = c.id
     WHERE $whereClause
     ORDER BY r.created_at DESC
     LIMIT $perPage OFFSET $offset"
);
$stmt->execute($params);
$resources = $stmt->fetchAll();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_external') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $externalUrl = trim($_POST['external_url'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? getDefaultCategoryId());
        $isVip = isset($_POST['is_vip']) ? 1 : 0;
        $vipPwd = trim($_POST['vip_password'] ?? '');

        if (empty($title) || empty($externalUrl)) {
            $error = '标题和外链 URL 不能为空。';
        } else {
            $vipHash = ($isVip && !empty($vipPwd)) ? password_hash($vipPwd, PASSWORD_DEFAULT) : null;
            $stmt = $db->prepare(
                "INSERT INTO resources (title, description, type, external_url, file_size, uploader_id, category_id, is_vip, vip_password, download_count, status)
                 VALUES (?, ?, 'external', ?, 0, ?, ?, ?, ?, 0, 'active')"
            );
            $stmt->execute([$title, $description, $externalUrl, $_SESSION['user_id'], $categoryId, $isVip, $vipHash]);
            $success = '外链资源已添加。';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $categoryId = (int)($_POST['category_id'] ?? getDefaultCategoryId());
        $isVip = isset($_POST['is_vip']) ? 1 : 0;
        $vipPwd = trim($_POST['vip_password'] ?? '');
        $externalUrl = trim($_POST['external_url'] ?? '');

        if ($id > 0 && !empty($title)) {
            $db->prepare('UPDATE resources SET title = ?, description = ?, category_id = ?, updated_at = NOW() WHERE id = ?')
               ->execute([$title, $description, $categoryId, $id]);

            if ($isVip && !empty($vipPwd)) {
                $hash = password_hash($vipPwd, PASSWORD_DEFAULT);
                $db->prepare('UPDATE resources SET is_vip = 1, vip_password = ? WHERE id = ?')->execute([$hash, $id]);
                unset($_SESSION['vip_pass_' . $id]);
            } elseif (!$isVip) {
                $db->prepare('UPDATE resources SET is_vip = 0, vip_password = NULL WHERE id = ?')->execute([$id]);
                unset($_SESSION['vip_pass_' . $id]);
            }

            // Update external URL if applicable
            $res = getResourceById($id);
            if ($res && $res['type'] === 'external') {
                $db->prepare('UPDATE resources SET external_url = ? WHERE id = ?')->execute([$externalUrl, $id]);
            }

            $success = '资源已更新。';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $resource = getResourceById($id);
        if ($resource) {
            if ($resource['type'] === 'local') {
                // Delete file from disk
                $absPath = resolvePath($resource['local_path']);
                if (isPathWithinScanDirs($absPath) && file_exists($absPath)) {
                    @unlink($absPath);
                }
            }
            deleteResourceRecord($id);
            $success = '资源已删除。';
        }
    }

    // Refresh list after action
    header('Location: resources.php?page=' . $page . ($search ? '&q=' . urlencode($search) : '') . '&filter=' . $filter);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>资源管理 - <?= h(getSetting('site_name', 'resDl')) ?></title>
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
<a href="resources.php" class="active">资源管理</a>
<a href="scans.php">扫描目录</a>
<a href="settings.php">系统设置</a>
<a href="users.php">用户管理</a>
</aside>

<main class="admin-main">
<h1>资源管理</h1>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<div style="display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
<form method="get" class="search-bar" style="margin:0; flex:1;">
<input type="text" name="q" value="<?= h($search) ?>" placeholder="搜索资源标题..." style="flex:1;">
<button type="submit" class="btn btn-sm btn-primary">搜索</button>
</form>
<button class="btn btn-sm btn-outline" onclick="showAddExternal()">+ 添加外链资源</button>
</div>

<div style="display:flex; gap:6px; margin-bottom:16px;">
<a href="?filter=all<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $filter==='all' ? 'btn-primary' : 'btn-outline' ?>">全部</a>
<a href="?filter=local<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $filter==='local' ? 'btn-primary' : 'btn-outline' ?>">本地文件</a>
<a href="?filter=external<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $filter==='external' ? 'btn-primary' : 'btn-outline' ?>">外链资源</a>
<a href="?filter=vip<?= $search ? '&q='.urlencode($search) : '' ?>" class="btn btn-sm <?= $filter==='vip' ? 'btn-primary' : 'btn-outline' ?>">VIP 资源</a>
</div>

<?php if (empty($resources)): ?>
<div class="empty-state"><p>暂无资源。</p></div>
<?php else: ?>
<table class="table">
<thead>
<tr>
<th>ID</th>
<th>标题</th>
<th>类型</th>
<th>大小</th>
<th>VIP</th>
<th>下载</th>
<th>上传者</th>
<th>分类</th>
<th>时间</th>
<th>操作</th>
</tr>
</thead>
<tbody>
<?php foreach ($resources as $res): ?>
<tr>
<td><?= $res['id'] ?></td>
<td><a href="../detail.php?id=<?= $res['id'] ?>" title="<?= h($res['title']) ?>"><?= h(safeStrlen($res['title']) > 30 ? safeSubstr($res['title'], 0, 30) . '...' : $res['title']) ?></a></td>
<td><?= $res['type'] === 'local' ? '本地' : '外链' ?></td>
<td><?= $res['type'] === 'local' ? formatFileSize((int)$res['file_size']) : '—' ?></td>
<td><?= $res['is_vip'] ? '&#128274;' : '—' ?></td>
<td><?= $res['download_count'] ?></td>
<td><?= h($res['uploader_name'] ?? '系统') ?></td>
<td><?= h($res['category_name'] ?? '未分类') ?></td>
<td><?= date('Y-m-d', strtotime($res['created_at'])) ?></td>
<td class="actions">
<button class="btn btn-sm btn-outline" onclick="editResource(<?= $res['id'] ?>, '<?= addslashes($res['title']) ?>', '<?= addslashes($res['description']) ?>', <?= $res['category_id'] ?>, <?= $res['is_vip'] ?>, '<?= addslashes($res['external_url'] ?? '') ?>')">编辑</button>
<form method="post" class="inline-form" onsubmit="return confirm('确定永久删除此资源？')">
<?= csrf_field() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= $res['id'] ?>">
<button type="submit" class="btn btn-sm btn-danger">删除</button>
</form>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<?php
// Pagination
$baseUrl = "resources.php?filter=$filter" . ($search ? '&q=' . urlencode($search) : '');
if ($totalPages > 1): ?>
<div class="pagination">
<?php for ($i = 1; $i <= $totalPages; $i++): ?>
<a href="<?= $baseUrl ?>&page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
<?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
</main>
</div>

<!-- Add External Modal (simple inline) -->
<div class="modal-overlay" id="add-external-modal">
<div class="modal-box">
<button class="close-btn" onclick="document.getElementById('add-external-modal').classList.remove('active')">&times;</button>
<h2>添加外链资源</h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="add_external">
<div class="form-group"><label>标题 *</label><input type="text" name="title" class="form-control" required></div>
<div class="form-group"><label>外链 URL *</label><input type="url" name="external_url" class="form-control" required></div>
<div class="form-group"><label>描述</label><textarea name="description" class="form-control"></textarea></div>
<div class="form-group"><label>分类</label><select name="category_id" class="form-control"><?= getCategoryOptions(0, 0, getDefaultCategoryId()) ?></select></div>
<div class="form-group"><label><input type="checkbox" name="is_vip" value="1"> VIP 资源</label></div>
<div class="form-group"><label>VIP 密码</label><input type="text" name="vip_password" class="form-control"></div>
<button type="submit" class="btn btn-primary btn-block">添加</button>
</form>
</div>
</div>

<!-- Edit Modal -->
<div class="modal-overlay" id="edit-modal">
<div class="modal-box" style="max-width:500px;">
<button class="close-btn" onclick="document.getElementById('edit-modal').classList.remove('active')">&times;</button>
<h2>编辑资源</h2>
<form method="post">
<?= csrf_field() ?>
<input type="hidden" name="action" value="edit">
<input type="hidden" name="id" id="edit-id">
<div class="form-group"><label>标题</label><input type="text" name="title" id="edit-title" class="form-control" required></div>
<div class="form-group"><label>描述</label><textarea name="description" id="edit-desc" class="form-control"></textarea></div>
<div class="form-group"><label>分类</label><select name="category_id" id="edit-cat" class="form-control"><?= getCategoryOptions(0, 0, 0) ?></select></div>
<div class="form-group" id="edit-external-url-group" style="display:none;"><label>外链 URL</label><input type="url" name="external_url" id="edit-external-url" class="form-control"></div>
<div class="form-group"><label><input type="checkbox" name="is_vip" id="edit-is-vip" value="1"> VIP 资源</label></div>
<div class="form-group"><label>VIP 密码（留空不变）</label><input type="text" name="vip_password" id="edit-vip-pwd" class="form-control" placeholder="新密码，留空则不修改"></div>
<button type="submit" class="btn btn-primary btn-block">保存</button>
</form>
</div>
</div>

<script>
function showAddExternal() { document.getElementById('add-external-modal').classList.add('active'); }
function editResource(id, title, desc, catId, isVip, extUrl) {
document.getElementById('edit-id').value = id;
document.getElementById('edit-title').value = title;
document.getElementById('edit-desc').value = desc || '';
document.getElementById('edit-cat').value = catId;
document.getElementById('edit-is-vip').checked = isVip == 1;
document.getElementById('edit-vip-pwd').value = '';
var urlGroup = document.getElementById('edit-external-url-group');
var urlInput = document.getElementById('edit-external-url');
if (extUrl) { urlGroup.style.display = 'block'; urlInput.value = extUrl; }
else { urlGroup.style.display = 'none'; urlInput.value = ''; }
document.getElementById('edit-modal').classList.add('active');
}
</script>
</body>
</html>
