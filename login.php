<?php
require_once __DIR__ . '/inc/init.php';

if (check_login()) {
    header('Location: ' . SITE_URL . '/index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = '请填写用户名和密码。';
    } else {
        $user = login_user($username, $password);
        if ($user) {
            $redirect = $_SESSION['redirect_after_login'] ?? SITE_URL . '/index.php';
            unset($_SESSION['redirect_after_login']);
            header('Location: ' . $redirect);
            exit;
        }
        $error = '用户名或密码错误。';
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">
<title>登录 - <?= h(getSetting('site_name', 'resDl')) ?></title>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
</head>
<body>
<header class="site-header"><div class="container header-inner">
<a href="index.php" class="site-logo"><?= h(getSetting('site_name', 'resDl')) ?></a>
<nav class="header-nav">
<a href="index.php">首页</a>
<?php if (check_login()): ?>
<a href="profile.php">个人中心</a>
<a href="logout.php">退出</a>
<?php else: ?>
<a href="login.php">登录</a>
<a href="register.php">注册</a>
<?php endif; ?>
</nav>
</div></header>

<main class="container" style="padding: 48px 0;">
<div class="form">
<h2 style="margin-bottom: 20px; text-align:center;">用户登录</h2>

<?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>

<form method="post">
<?= csrf_field() ?>
<div class="form-group">
<label>用户名</label>
<input type="text" name="username" class="form-control" required autofocus>
</div>
<div class="form-group">
<label>密码</label>
<input type="password" name="password" class="form-control" required>
</div>
<button type="submit" class="btn btn-primary btn-block">登录</button>
</form>
<p style="text-align:center; margin-top:16px; font-size:14px;">还没有账号？<a href="register.php">立即注册</a></p>
</div>
</main>

<footer class="site-footer"><div class="container">
&copy; <?= date('Y') ?> <?= h(getSetting('site_name', 'resDl')) ?>
</div></footer>
</body>
</html>
