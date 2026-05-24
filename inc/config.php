<?php
/**
 * Site Configuration
 * Modify these values to match your environment.
 */

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'resdl');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Site URL (no trailing slash)
define('BASE_URL', 'http://localhost/resDl');

// Server root path (no trailing slash) — used to convert relative paths to absolute
define('SITE_ROOT', rtrim(dirname(__DIR__), '/'));

// CSRF token name
define('CSRF_TOKEN_NAME', 'csrf_token');

// Session lifetime (seconds)
define('SESSION_LIFETIME', 86400);

// Upload chunk size for large file streaming (bytes)
define('DOWNLOAD_CHUNK_SIZE', 1048576); // 1MB
