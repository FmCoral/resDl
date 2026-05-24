<?php
/**
 * Admin — Category Management
 */
require_once __DIR__ . '/../inc/init.php';
require_admin();

$error   = '';
$success = '';
$db = getDB();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name     = trim($_POST['name'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if (!empty($name)) {
            $stmt = $db->prepare('INSERT INTO categories (name, parent_id, sort_order) VALUES (?, ?, ?)');
            $stmt->execute([$name, $parentId, $sortOrder]);
            $success = '分类已添加。';
        } else {
            $error = '分类名称不能为空。';
        }
    } elseif ($action === 'edit') {
        $id       = (int)($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $parentId = (int)($_POST['parent_id'] ?? 0);
        $sortOrder = (int)($_POST['sort_order'] ?? 0);
        if ($id > 0 && !empty($name)) {
            $stmt = $db->prepare('UPDATE categories SET name = ?, parent_id = ?, sort_order = ? WHERE id = ?');
            $stmt->execute([$name, $parentId, $sortOrder, $id]);
            $success = '分类已更新。';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $defaultCatId = getDefaultCategoryId();
        if ($id === $defaultCatId) {
            $error = '不能删除默认分类。';
        } elseif ($id > 0) {
            // Move resources to default category
            $db->prepare('UPDATE resources SET category_id = ? WHERE category_id = ?')->execute([$defaultCatId, $id]);
            // Move subcategories to parent
            $cat = $db->prepare('SELECT parent_id FROM categories WHERE id = ?');
            $cat->execute([$id]);
            $catRow = $cat->fetch();
            $parentId = $catRow ? (int)$catRow['parent_id'] : 0;
            $db->prepare('UPDATE categories SET parent_id = ? WHERE parent_id = ?')->execute([$parentId, $id]);
            // Delete
            $db->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            $success = '分类已删除，相关资源已移至默认分类。';
        }
    }
}

$categories = getCategoryTree();

// Build flat list for parent select
function flatCategoryOptions(array $cats, int $depth, int $excludeId, int $selectedId): string {
    $html = '';
    foreach ($cats as $cat) {
        if ((int)$cat['id'] === $excludeId) continue;
        $prefix = str_repeat('&nbsp;&nbsp;', $depth);
        $sel = ((int)$cat['id'] === $selectedId) ? ' selected' : '';
        $html .= '<option value="' . $cat['id'] . '"' . $sel . '>' . $prefix . h($cat['name']) . '</option>';
        if (!empty($cat['children'])) {
            $html .= flatCategoryOptions($cat['children'], $depth + 1, $excludeId, $selectedId);
        }
    }
    return $html;
}

function renderCategoryList(array $cats, int $depth): string {
    $html = '';
    foreach ($cats as $cat) {
        $levelClass = 'level-' . min($depth, 3);
        $html .= '<li>';
        $html .= '<span class="cat-name ' . $levelClass . '">' . h($cat['name']) . ' <small style="color:#999;">(排序: ' . (int)$cat['sort_order'] . ')</small></span>';
        $html .= '<span class="cat-actions">';
        $html .= '<button class="btn btn-sm btn-outline" onclick="editCat(' . $cat['id'] . ', \'' . addslashes($cat['name']) . '\', ' . $cat['parent_id'] . ', ' . $cat['sort_order'] . ')">编辑</button>';
        $html .= '<form method="post" class="inline-form" onsubmit="return confirm(\'确定删除此分类？\')"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $cat['id'] . '">' . csrf_field() . '<button type="submit" class="btn btn-sm btn-danger">删除</button></form>';
        $html .= '</span>';
        $html .= '</li>';
        if (!empty($cat['children'])) {
            $html .= renderCategoryList($cat['children'], $depth + 1);
        }
    }
    return $html;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>分类管理 - <?= h(getSetting('site_name', 'resDl')) ?></title>
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
<a href="categories.php" class="active">分类管理</a>
<a href="resources.php">资源管理</a>
<a href="scans.php">扫描目录</a>
<a href="settings.php">系统设置</a>
<a href="users.php">用户管理</a>
</aside>

<main class="admin-main">
<h1>分类管理</h1>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

<div class="row">
<!-- Category Tree -->
<div style="flex:1; min-width:300px;">
<h2 style="margin-bottom:12px;">分类列表</h2>
<?php if (empty($categories)): ?>
<div class="empty-state"><p>暂无分类。</p></div>
<?php else: ?>
<ul class="cat-tree"><?= renderCategoryList($categories, 0) ?></ul>
<?php endif; ?>
</div>

<!-- Add / Edit Form -->
<div style="flex:0 0 360px;">
<div class="detail-section">
<h2 id="form-title">添加分类</h2>
<form method="post" id="cat-form">
<?= csrf_field() ?>
<input type="hidden" name="action" id="cat-action" value="add">
<input type="hidden" name="id" id="cat-id" value="0">
<div class="form-group">
<label>分类名称</label>
<input type="text" name="name" id="cat-name" class="form-control" required maxlength="100">
</div>
<div class="form-group">
<label>父分类</label>
<select name="parent_id" id="cat-parent" class="form-control">
<option value="0">顶级分类</option>
<?= flatCategoryOptions($categories, 0, 0, 0) ?>
</select>
</div>
<div class="form-group">
<label>排序</label>
<input type="number" name="sort_order" id="cat-sort" class="form-control" value="0">
</div>
<button type="submit" class="btn btn-primary btn-block">保存</button>
<button type="button" id="cancel-edit" class="btn btn-outline btn-block" style="display:none; margin-top:6px;" onclick="resetForm()">取消编辑</button>
</form>
</div>
</div>
</div>
</main>
</div>

<script>
function editCat(id, name, parentId, sortOrder) {
document.getElementById('form-title').textContent = '编辑分类';
document.getElementById('cat-action').value = 'edit';
document.getElementById('cat-id').value = id;
document.getElementById('cat-name').value = name;
document.getElementById('cat-parent').value = parentId;
document.getElementById('cat-sort').value = sortOrder;
document.getElementById('cancel-edit').style.display = 'block';
}

function resetForm() {
document.getElementById('form-title').textContent = '添加分类';
document.getElementById('cat-action').value = 'add';
document.getElementById('cat-id').value = 0;
document.getElementById('cat-name').value = '';
document.getElementById('cat-parent').value = 0;
document.getElementById('cat-sort').value = 0;
document.getElementById('cancel-edit').style.display = 'none';
}
</script>
</body>
</html>
