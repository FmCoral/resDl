<?php
require_once __DIR__ . '/inc/init.php';

if (check_login()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

if (getSetting('allow_registration', '1') === '0') {
    die('管理员已关闭注册。');
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $email    = trim($_POST['email'] ?? '');

    if (empty($username) || empty($password) || empty($email)) {
        $error = '请填写所有必填项。';
    } elseif ($password !== $password2) {
        $error = '两次密码输入不一致。';
    } elseif (strlen($password) < 6) {
        $error = '密码长度至少 6 个字符。';
    } else {
        $result = register_user($username, $password, $email);
        if (is_string($result)) {
            $error = $result;
        } else {
            $success = '注册成功！请<a href="login.php">登录</a>。';
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
<title>注册 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="index.php">首页</a>
<a href="login.php">登录</a>
<a href="register.php">注册</a>
</nav>
</div></header>

<main class="container" style="padding: 48px 0;">
<div class="form">
<h2 style="margin-bottom: 20px; text-align:center;">用户注册</h2>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<?php if (!$success): ?>
<form method="post">
<?= csrf_field() ?>
<div class="form-group">
<label>用户名</label>
<input type="text" name="username" class="form-control" required minlength="3" maxlength="50">
</div>
<div class="form-group">
<label>邮箱</label>
<input type="email" name="email" class="form-control" required>
</div>
<div class="form-group">
<label>密码</label>
<input type="password" name="password" class="form-control" required minlength="6">
</div>
<div class="form-group">
<label>确认密码</label>
<input type="password" name="password2" class="form-control" required minlength="6">
</div>
<button type="submit" class="btn btn-primary btn-block">注册</button>
</form>
<?php endif; ?>
<p style="text-align:center; margin-top:16px; font-size:14px;">已有账号？<a href="login.php">立即登录</a></p>
</div>
</main>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>
</body>
</html>
