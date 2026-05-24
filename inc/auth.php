<?php
/**
 * Authentication & Authorization Functions
 */

function login_user(string $username, string $password): array|false {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['user_role'] = $user['role'];
        return $user;
    }
    return false;
}

function logout_user(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'],
            $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function register_user(string $username, string $password, string $email): array|string {
    $db = getDB();
    // Check allow_registration
    if (getSetting('allow_registration') === '0') {
        return '管理员已关闭注册。';
    }
    // Validate username
    if (!preg_match('/^[a-zA-Z0-9_\x{4e00}-\x{9fa5}]{3,50}$/u', $username)) {
        return '用户名须为 3-50 个字符（字母、数字、下划线、中文）。';
    }
    // Check duplicate
    $stmt = $db->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        return '用户名已被占用。';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '邮箱格式不正确。';
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, ?)');
    $stmt->execute([$username, $hash, $email, 'user']);
    return ['id' => (int)$db->lastInsertId(), 'username' => $username, 'role' => 'user'];
}

function check_login(): bool {
    return isset($_SESSION['user_id']);
}

function is_admin(): bool {
    return check_login() && ($_SESSION['user_role'] ?? '') === 'admin';
}

function get_current_user(): array|false {
    if (!check_login()) return false;
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: false;
}

function require_login(): void {
    if (!check_login()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header('Location: ' . SITE_URL . '/login.php');
        exit;
    }
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('需要管理员权限。');
    }
}

// --- CSRF Protection ---

function generate_csrf_token(): string {
    $token = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_NAME] = $token;
    // Also persist in DB for session-independent validation
    if (check_login()) {
        $db = getDB();
        $stmt = $db->prepare('DELETE FROM csrf_tokens WHERE user_id = ? AND expires_at < NOW()');
        $stmt->execute([$_SESSION['user_id']]);
        $stmt = $db->prepare('INSERT INTO csrf_tokens (user_id, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR))');
        $stmt->execute([$_SESSION['user_id'], $token]);
    }
    return $token;
}

function validate_csrf_token(?string $token = null): bool {
    $token = $token ?? $_POST[CSRF_TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $session_token = $_SESSION[CSRF_TOKEN_NAME] ?? '';
    if ($token && hash_equals($session_token, $token)) {
        return true;
    }
    // Fallback: check DB token
    if (check_login() && $token) {
        $db = getDB();
        $stmt = $db->prepare('SELECT id FROM csrf_tokens WHERE user_id = ? AND token = ? AND expires_at > NOW()');
        $stmt->execute([$_SESSION['user_id'], $token]);
        if ($stmt->fetch()) {
            return true;
        }
    }
    return false;
}

function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars($token) . '">';
}

function require_csrf(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!validate_csrf_token()) {
            http_response_code(403);
            die('CSRF 验证失败，请刷新页面后重试。');
        }
    }
}
