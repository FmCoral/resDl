<?php
/**
 * AJAX endpoint — verify VIP resource password
 */
require_once __DIR__ . '/inc/init.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效请求。']);
    exit;
}

require_csrf();

$resourceId = (int)($_POST['resource_id'] ?? 0);
$password   = $_POST['password'] ?? '';

if ($resourceId <= 0 || empty($password)) {
    echo json_encode(['success' => false, 'message' => '请提供资源 ID 和密码。']);
    exit;
}

$resource = getResourceById($resourceId);
if (!$resource || $resource['status'] !== 'active') {
    echo json_encode(['success' => false, 'message' => '资源不存在。']);
    exit;
}

if (!$resource['is_vip']) {
    echo json_encode(['success' => true, 'message' => '无需密码。']);
    exit;
}

if (verifyVipPassword($resourceId, $password)) {
    echo json_encode(['success' => true, 'message' => '密码验证成功。']);
} else {
    echo json_encode(['success' => false, 'message' => '密码错误。']);
}
