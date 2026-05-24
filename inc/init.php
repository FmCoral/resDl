<?php
/**
 * Initialization — included by every page
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(SESSION_LIFETIME);
    session_start();
}

// Load all settings into a global cache
$GLOBALS['_settings'] = null;

function loadSettings(): array {
    if ($GLOBALS['_settings'] === null) {
        $db = getDB();
        $stmt = $db->query('SELECT setting_key, setting_value FROM settings');
        $GLOBALS['_settings'] = [];
        while ($row = $stmt->fetch()) {
            $GLOBALS['_settings'][$row['setting_key']] = $row['setting_value'];
        }
    }
    return $GLOBALS['_settings'];
}

function getSetting(string $key, string $default = ''): string {
    $settings = loadSettings();
    return $settings[$key] ?? $default;
}

function updateSetting(string $key, string $value): void {
    $db = getDB();
    $stmt = $db->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, $value]);
    $GLOBALS['_settings'][$key] = $value;
}

// Get default category ID dynamically (fixes hardcoded id=1 issue)
function getDefaultCategoryId(): int {
    $id = (int)getSetting('default_category_id', '0');
    if ($id > 0) return $id;
    // Fallback: find the first category
    $db = getDB();
    $stmt = $db->query('SELECT MIN(id) FROM categories');
    $minId = (int)$stmt->fetchColumn();
    if ($minId > 0) {
        updateSetting('default_category_id', (string)$minId);
        return $minId;
    }
    // Last resort: create default
    $db->prepare('INSERT INTO categories (name, parent_id, sort_order) VALUES (?, 0, 0)')->execute(['未分类']);
    $newId = (int)$db->lastInsertId();
    updateSetting('default_category_id', (string)$newId);
    return $newId;
}

// Load settings cache on init
loadSettings();

// --- Site URL Helper ---
// SITE_URL can be overridden via admin settings (stored in DB).
// Falls back to the config.php constant BASE_URL.
define('SITE_URL', getSetting('site_url', BASE_URL));

/**
 * Generate a full site URL for the given path.
 */
function site_url(string $path = ''): string {
    return rtrim(SITE_URL, '/') . '/' . ltrim($path, '/');
}
