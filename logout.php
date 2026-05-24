<?php
require_once __DIR__ . '/inc/init.php';
logout_user();
header('Location: ' . SITE_URL . '/index.php');
exit;
