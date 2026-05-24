<?php
/**
 * Installer — handles first-time setup including proper password hashing.
 * DELETE THIS FILE after installation for security.
 */

require_once __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/db.php';
require_once __DIR__ . '/inc/functions.php';

$step = $_GET['step'] ?? '1';
$error = '';
$success = '';

// Check if already installed
if ($step !== 'complete') {
    try {
        $db = getDB();
        $stmt = $db->query("SELECT COUNT(*) FROM settings");
        if ((int)$stmt->fetchColumn() >= 5) {
            $step = 'already';
        }
    } catch (Exception $e) {
        // Not installed yet — proceed
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $step = $_POST['step'] ?? '1';

    if ($step === '1') {
        // Test DB connection and import SQL
        $dsn = sprintf('mysql:host=%s;charset=utf8mb4', $_POST['db_host'] ?? 'localhost');
        $dbUser = $_POST['db_user'] ?? '';
        $dbPass = $_POST['db_pass'] ?? '';
        $dbName = $_POST['db_name'] ?? '';

        try {
            $pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbName`");
            // Import SQL
            $sql = file_get_contents(__DIR__ . '/install.sql');
            // Remove CREATE DATABASE / USE lines since we handle them
            $sql = preg_replace('/^CREATE DATABASE.*?;\s*[\r\n]+/mi', '', $sql);
            $sql = preg_replace('/^USE.*?;\s*[\r\n]+/mi', '', $sql);
            // Split by semicolon followed by newlines
            $statements = preg_split('/;\s*[\r\n]+\s*/', $sql);
            foreach ($statements as $stmt) {
                // Strip comment lines from each statement
                $lines = explode("\n", $stmt);
                $lines = array_filter($lines, function($line) {
                    $trimmed = trim($line);
                    return $trimmed !== '' && !str_starts_with($trimmed, '--');
                });
                $clean = trim(implode("\n", $lines));
                if ($clean !== '') {
                    $pdo->exec($clean);
                }
            }

            $baseUrl = rtrim($_POST['base_url'] ?? 'http://localhost/resDl', '/');

            // Write config
            $configContent = "<?php
define('DB_HOST', '" . addslashes($_POST['db_host']) . "');
define('DB_NAME', '" . addslashes($dbName) . "');
define('DB_USER', '" . addslashes($dbUser) . "');
define('DB_PASS', '" . addslashes($dbPass) . "');
define('DB_CHARSET', 'utf8mb4');
define('BASE_URL', '" . addslashes($baseUrl) . "');
define('SITE_ROOT', rtrim(dirname(__DIR__), '/'));
define('CSRF_TOKEN_NAME', 'csrf_token');
define('SESSION_LIFETIME', 86400);
define('DOWNLOAD_CHUNK_SIZE', 1048576);
";
            file_put_contents(__DIR__ . '/inc/config.php', $configContent);

            // Also write site_url to settings table
            $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('site_url', ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)")
                ->execute([$baseUrl]);

            $_SESSION['install_db_done'] = true;
            $step = '2';
        } catch (Exception $e) {
            $error = '数据库连接失败：' . $e->getMessage();
        }
    } elseif ($step === '2') {
        // Create admin user
        $username = $_POST['admin_username'] ?? 'admin';
        $password = $_POST['admin_password'] ?? '';
        $email    = $_POST['admin_email'] ?? 'admin@example.com';

        if (strlen($password) < 6) {
            $error = '密码长度至少 6 个字符。';
        } else {
            try {
                $db = getDB();
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');
                $stmt->execute([$username, $hash, $email, 'admin']);
                $step = 'complete';
                $success = '安装成功！管理员账号 <strong>' . htmlspecialchars($username) . '</strong> 已创建。';
            } catch (Exception $e) {
                $error = '创建管理员失败：' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>resDl 安装向导</title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font:16px/1.6 -apple-system,system-ui,sans-serif; background:#f0f2f5; color:#333; display:flex; justify-content:center; align-items:center; min-height:100vh; }
.container { background:#fff; padding:40px; border-radius:12px; box-shadow:0 2px 16px rgba(0,0,0,.1); max-width:520px; width:90%; }
h1 { font-size:22px; margin-bottom:24px; text-align:center; color:#1a73e8; }
.form-group { margin-bottom:18px; }
label { display:block; margin-bottom:6px; font-weight:600; font-size:14px; }
input[type="text"], input[type="password"], input[type="email"], input[type="url"] { width:100%; padding:10px 12px; border:1px solid #d0d5dd; border-radius:8px; font-size:15px; transition:border-color .2s; }
input:focus { outline:none; border-color:#1a73e8; box-shadow:0 0 0 3px rgba(26,115,232,.15); }
button { width:100%; padding:12px; background:#1a73e8; color:#fff; border:none; border-radius:8px; font-size:16px; font-weight:600; cursor:pointer; transition:background .2s; }
button:hover { background:#1557b0; }
.error { background:#fef2f2; color:#991b1b; padding:12px; border-radius:8px; margin-bottom:18px; font-size:14px; }
.success { background:#f0fdf4; color:#166534; padding:12px; border-radius:8px; margin-bottom:18px; font-size:14px; }
.already { text-align:center; padding:20px; }
.already p { margin-bottom:16px; color:#666; }
.already a { color:#1a73e8; }
</style>
</head>
<body>
<div class="container">
<h1>resDl 安装向导</h1>

<?php if ($error): ?>
<div class="error"><?= $error ?></div>
<?php endif; ?>

<?php if ($success): ?>
<div class="success"><?= $success ?></div>
<?php endif; ?>

<?php if ($step === 'already'): ?>
<div class="already">
<p>系统已安装完成。</p>
<p><a href="<?= BASE_URL ?>">前往首页</a></p>
</div>

<?php elseif ($step === '1'): ?>
<form method="post">
<input type="hidden" name="step" value="1">
<div class="form-group">
<label>数据库主机</label>
<input type="text" name="db_host" value="localhost" required>
</div>
<div class="form-group">
<label>数据库名称</label>
<input type="text" name="db_name" value="resdl" required>
</div>
<div class="form-group">
<label>数据库用户名</label>
<input type="text" name="db_user" value="root" required>
</div>
<div class="form-group">
<label>数据库密码</label>
<input type="password" name="db_pass">
</div>
<div class="form-group">
<label>站点 URL</label>
<input type="url" name="base_url" value="http://localhost/resDl" required>
</div>
<button type="submit">下一步：创建管理员</button>
</form>

<?php elseif ($step === '2'): ?>
<form method="post">
<input type="hidden" name="step" value="2">
<div class="form-group">
<label>管理员用户名</label>
<input type="text" name="admin_username" value="admin" required>
</div>
<div class="form-group">
<label>管理员密码</label>
<input type="password" name="admin_password" required minlength="6">
</div>
<div class="form-group">
<label>管理员邮箱</label>
<input type="email" name="admin_email" value="admin@example.com" required>
</div>
<button type="submit">完成安装</button>
</form>

<?php elseif ($step === 'complete'): ?>
<div class="already">
<p>安装成功！</p>
<p style="color:#991b1b;font-weight:600;">请立即删除 <code>install.php</code> 文件以保证安全。</p>
<p><a href="<?= BASE_URL ?>">前往首页</a></p>
</div>
<?php endif; ?>
</div>
</body>
</html>
