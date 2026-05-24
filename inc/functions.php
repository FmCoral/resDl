<?php
/**
 * Core Functions
 * All business logic — scanning, pagination, download, upload, categories.
 */

// --- Settings Helpers (delegates to init.php) ---
// getSetting() and updateSetting() are defined in init.php

// --- Scan Directory Management ---

function getActiveScanDirs(): array {
    $mode = getSetting('scan_mode', 'fixed');
    $dirs = [];
    if ($mode === 'fixed') {
        $fixed = getSetting('fixed_scan_dir', 'uploads');
        $dirs[] = resolvePath($fixed);
    } else {
        $specified = json_decode(getSetting('specified_scan_dirs', '[]'), true) ?: [];
        foreach ($specified as $d) {
            $dirs[] = resolvePath($d);
        }
    }
    // Filter to valid existing directories
    return array_filter($dirs, fn($d) => is_dir($d));
}

function resolvePath(string $path): string {
    // If already absolute and exists, return as-is
    if (str_starts_with($path, '/') || (PHP_OS_FAMILY === 'Windows' && preg_match('/^[A-Z]:/i', $path))) {
        return rtrim($path, '/\\');
    }
    return rtrim(SITE_ROOT . '/' . trim($path, '/\\'), '/\\');
}

function toRelativePath(string $absolutePath): string {
    $root = rtrim(SITE_ROOT, '/\\');
    $abs  = rtrim(str_replace('\\', '/', $absolutePath), '/');
    $rel  = str_replace('\\', '/', $root);
    if (str_starts_with($abs, $rel . '/')) {
        return substr($abs, strlen($rel) + 1);
    }
    return $abs; // fallback — shouldn't happen if paths are within SITE_ROOT
}

// --- Directory Scanning (with batch optimizations) ---

function recursiveScanDirectory(string $dir, int $limit = 200, int $offset = 0): array {
    $results = [];
    if (!is_dir($dir)) return $results;

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $count = 0;
    $skipped = 0;
    foreach ($iterator as $fileinfo) {
        if (!$fileinfo->isFile()) continue;
        $filename = $fileinfo->getFilename();
        // Skip hidden files
        if (str_starts_with($filename, '.')) continue;

        if ($skipped < $offset) {
            $skipped++;
            continue;
        }
        if ($count >= $limit) break;

        $path = str_replace('\\', '/', $fileinfo->getPathname());
        $results[] = [
            'path'  => $path,
            'size'  => $fileinfo->getSize(),
            'mtime' => $fileinfo->getMTime(),
        ];
        $count++;
    }
    return $results;
}

function countFilesInScanDirs(): int {
    $dirs = getActiveScanDirs();
    $total = 0;
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $f) {
            if ($f->isFile() && !str_starts_with($f->getFilename(), '.')) {
                $total++;
            }
        }
    }
    return $total;
}

function getLocalResourcePaths(): array {
    $db = getDB();
    $stmt = $db->query("SELECT id, local_path FROM resources WHERE type='local' AND status='active'");
    $map = [];
    while ($row = $stmt->fetch()) {
        $abs = resolvePath($row['local_path']);
        $map[$abs] = (int)$row['id'];
    }
    return $map;
}

function insertResourceFromScan(string $absPath, array $info): int {
    $db = getDB();
    $relPath  = toRelativePath($absPath);
    $filename = basename($absPath);
    $title    = pathinfo($filename, PATHINFO_FILENAME);
    $catId    = getDefaultCategoryId();

    $stmt = $db->prepare(
        "INSERT INTO resources (title, description, type, local_path, file_size, uploader_id, category_id, is_vip, vip_password, download_count, status)
         VALUES (?, '', 'local', ?, ?, NULL, ?, 0, NULL, 0, 'active')"
    );
    $stmt->execute([$title, $relPath, $info['size'], $catId]);
    return (int)$db->lastInsertId();
}

function updateResourceFileInfo(int $id, array $info): void {
    // Only update size — preserve user-editable fields (title, description, category, etc.)
    $db = getDB();
    $stmt = $db->prepare('UPDATE resources SET file_size = ?, updated_at = NOW() WHERE id = ?');
    $stmt->execute([$info['size'], $id]);
}

function syncLocalFilesFromScanDirs(): array {
    $ttl      = (int)getSetting('sync_cache_ttl', '120');
    $lastSync = (int)getSetting('last_scan_time', '0');

    if ($lastSync > 0 && (time() - $lastSync) < $ttl) {
        return ['synced' => 0, 'deleted' => 0, 'message' => '缓存未过期，跳过扫描'];
    }

    $batchSize = (int)getSetting('scan_batch_size', '200');
    $totalFiles = countFilesInScanDirs();

    // For large directories, process in batches
    $totalSynced = 0;
    $totalDeleted = 0;

    if ($totalFiles > $batchSize) {
        // Large scan — process one batch per request to avoid timeout
        $currentBatch = (int)($_SESSION['scan_batch_offset'] ?? 0);
        $scanDirs = getActiveScanDirs();

        $scannedFiles = [];
        $remainingOffset = $currentBatch;
        foreach ($scanDirs as $dir) {
            $dirCount = countFilesInDir($dir);
            if ($remainingOffset >= $dirCount) {
                $remainingOffset -= $dirCount;
                continue;
            }
            $batch = recursiveScanDirectory($dir, $batchSize, $remainingOffset);
            foreach ($batch as $f) {
                $scannedFiles[$f['path']] = $f;
            }
            if (count($scannedFiles) >= $batchSize) break;
            $remainingOffset = 0;
        }

        $dbFiles = getLocalResourcePaths();

        foreach ($scannedFiles as $path => $info) {
            if (!isset($dbFiles[$path])) {
                insertResourceFromScan($path, $info);
                $totalSynced++;
            } else {
                updateResourceFileInfo($dbFiles[$path], $info);
                unset($dbFiles[$path]);
            }
        }

        // Only delete if we've completed the full scan
        $nextBatch = $currentBatch + $batchSize;
        if ($nextBatch >= $totalFiles) {
            foreach ($dbFiles as $path => $id) {
                deleteResourceRecord($id);
                $totalDeleted++;
            }
            updateSetting('last_scan_time', time());
            unset($_SESSION['scan_batch_offset']);
        } else {
            $_SESSION['scan_batch_offset'] = $nextBatch;
        }

        return ['synced' => $totalSynced, 'deleted' => $totalDeleted, 'message' => "批次扫描：已处理 " . min($nextBatch, $totalFiles) . " / $totalFiles"];
    }

    // Small directory — scan all at once
    $scanDirs = getActiveScanDirs();
    $scannedFiles = [];
    foreach ($scanDirs as $dir) {
        $files = recursiveScanDirectory($dir, 0, 0); // 0 = unlimited for small dirs
        foreach ($files as $f) {
            $scannedFiles[$f['path']] = $f;
        }
    }

    $dbFiles = getLocalResourcePaths();

    // Insert new / update existing
    foreach ($scannedFiles as $path => $info) {
        if (!isset($dbFiles[$path])) {
            insertResourceFromScan($path, $info);
            $totalSynced++;
        } else {
            updateResourceFileInfo($dbFiles[$path], $info);
            unset($dbFiles[$path]);
        }
    }

    // Delete records for missing files
    foreach ($dbFiles as $path => $id) {
        deleteResourceRecord($id);
        $totalDeleted++;
    }

    updateSetting('last_scan_time', time());
    unset($_SESSION['scan_batch_offset']);

    return ['synced' => $totalSynced, 'deleted' => $totalDeleted, 'message' => '扫描完成'];
}

function countFilesInDir(string $dir): int {
    if (!is_dir($dir)) return 0;
    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );
    foreach ($iterator as $f) {
        if ($f->isFile() && !str_starts_with($f->getFilename(), '.')) $count++;
    }
    return $count;
}

function forceFullScan(): array {
    updateSetting('last_scan_time', '0');
    unset($_SESSION['scan_batch_offset']);
    return syncLocalFilesFromScanDirs();
}

// --- Resource CRUD ---

function getResourceById(int $id): array|false {
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT r.*, u.username AS uploader_name, c.name AS category_name
         FROM resources r
         LEFT JOIN users u ON r.uploader_id = u.id
         LEFT JOIN categories c ON r.category_id = c.id
         WHERE r.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: false;
}

function deleteResourceRecord(int $id): void {
    $db = getDB();
    $stmt = $db->prepare('DELETE FROM resources WHERE id = ?');
    $stmt->execute([$id]);
}

function deleteResourceById(int $id, bool $deleteFile = true): void {
    $resource = getResourceById($id);
    if (!$resource) return;

    if ($deleteFile && $resource['type'] === 'local' && !empty($resource['local_path'])) {
        $absPath = resolvePath($resource['local_path']);
        // Safety: verify path is within a scan directory
        if (isPathWithinScanDirs($absPath) && file_exists($absPath)) {
            @unlink($absPath);
        }
    }
    deleteResourceRecord($id);
}

function isPathWithinScanDirs(string $absPath): bool {
    $real = realpath($absPath);
    if (!$real) return false;
    $real = str_replace('\\', '/', $real);
    $dirs = getActiveScanDirs();
    $uploadDir = resolvePath(getSetting('user_upload_dir', 'uploads'));
    $dirs[] = $uploadDir;
    foreach ($dirs as $dir) {
        $d = str_replace('\\', '/', realpath($dir) ?: $dir);
        if (str_starts_with($real, $d . '/') || $real === $d) {
            return true;
        }
    }
    return false;
}

// --- Category Functions ---

function getCategoryTree(int $parentId = 0): array {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order, id');
    $stmt->execute([$parentId]);
    $cats = $stmt->fetchAll();
    foreach ($cats as &$cat) {
        $cat['children'] = getCategoryTree((int)$cat['id']);
    }
    return $cats;
}

function getCategoryChildren(int $categoryId): array {
    $ids = [(int)$categoryId];
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM categories WHERE parent_id = ?');
    $queue = [$categoryId];
    while (!empty($queue)) {
        $parent = array_shift($queue);
        $stmt->execute([$parent]);
        while ($row = $stmt->fetch()) {
            $id = (int)$row['id'];
            if (!in_array($id, $ids, true)) {
                $ids[] = $id;
                $queue[] = $id;
            }
        }
    }
    return $ids;
}

function getCategoryOptions(int $parentId = 0, int $depth = 0, int $selectedId = 0): string {
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM categories WHERE parent_id = ? ORDER BY sort_order, id');
    $stmt->execute([$parentId]);
    $html = '';
    while ($cat = $stmt->fetch()) {
        $prefix = str_repeat('&nbsp;&nbsp;', $depth);
        $sel = ((int)$cat['id'] === $selectedId) ? ' selected' : '';
        $html .= '<option value="' . $cat['id'] . '"' . $sel . '>' . $prefix . htmlspecialchars($cat['name']) . '</option>';
        $html .= getCategoryOptions((int)$cat['id'], $depth + 1, $selectedId);
    }
    return $html;
}

// --- Paginated Resource Queries ---

function getResourcesPage(int $page = 1, ?int $categoryId = null, ?string $search = null): array {
    $db       = getDB();
    $perPage  = (int)getSetting('items_per_page', '10');
    $offset   = ($page - 1) * $perPage;

    // Trigger sync (respects cache TTL)
    syncLocalFilesFromScanDirs();

    $where  = ["r.status = 'active'"];
    $params = [];

    if ($categoryId) {
        $childIds = getCategoryChildren($categoryId);
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        $where[] = "r.category_id IN ($placeholders)";
        $params = array_merge($params, $childIds);
    }

    if ($search && trim($search) !== '') {
        $kw = '%' . trim($search) . '%';
        $where[] = '(r.title LIKE ? OR r.description LIKE ? OR c.name LIKE ?)';
        $params[] = $kw;
        $params[] = $kw;
        $params[] = $kw;
    }

    $whereClause = implode(' AND ', $where);

    // Deferred join for efficient deep pagination
    $countSql = "SELECT COUNT(*) FROM resources r LEFT JOIN categories c ON r.category_id = c.id WHERE $whereClause";
    $stmt = $db->prepare($countSql);
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $totalPages = max(1, (int)ceil($total / $perPage));

    // Deferred join: fetch IDs first, then join
    $idSql = "SELECT r.id FROM resources r LEFT JOIN categories c ON r.category_id = c.id WHERE $whereClause ORDER BY r.created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $db->prepare($idSql);
    $stmt->execute($params);
    $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $items = [];
    if (!empty($ids)) {
        $idPlaceholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "SELECT r.*, u.username AS uploader_name, c.name AS category_name
             FROM resources r
             LEFT JOIN users u ON r.uploader_id = u.id
             LEFT JOIN categories c ON r.category_id = c.id
             WHERE r.id IN ($idPlaceholders)
             ORDER BY r.created_at DESC"
        );
        $stmt->execute($ids);
        $items = $stmt->fetchAll();
    }

    return [
        'items'       => $items,
        'total'       => $total,
        'page'        => $page,
        'per_page'    => $perPage,
        'total_pages' => $totalPages,
    ];
}

// --- Download & File Serving ---

function serveFileDownload(array $resource): never {
    $absPath = resolvePath($resource['local_path']);

    // --- Anti-hotlinking: validate Host / Referer ---
    $allowedHost = parse_url(SITE_URL, PHP_URL_HOST);
    $requestHost = $_SERVER['HTTP_HOST'] ?? '';
    $referer     = $_SERVER['HTTP_REFERER'] ?? '';

    // Validate HTTP_HOST
    if ($allowedHost && $requestHost && $requestHost !== $allowedHost) {
        // Allow common alternative hostnames (e.g., with/without www)
        $allowedVariants = [$allowedHost, 'www.' . $allowedHost];
        $allowedVariants = array_merge($allowedVariants, array_map(fn($h) => preg_replace('/^www\./', '', $h), $allowedVariants));
        if (!in_array($requestHost, array_unique($allowedVariants), true)) {
            http_response_code(403);
            die('禁止外部站点盗链下载。');
        }
    }

    // Validate Referer (if present)
    if ($referer && $allowedHost) {
        $refererHost = parse_url($referer, PHP_URL_HOST);
        if ($refererHost && $refererHost !== $allowedHost) {
            $allowedVariants = [$allowedHost, 'www.' . $allowedHost];
            $allowedVariants = array_merge($allowedVariants, array_map(fn($h) => preg_replace('/^www\./', '', $h), $allowedVariants));
            if (!in_array($refererHost, array_unique($allowedVariants), true)) {
                http_response_code(403);
                die('禁止外部站点盗链下载。');
            }
        }
    }

    // Path traversal protection
    $realPath = realpath($absPath);
    if (!$realPath || !isPathWithinScanDirs($realPath)) {
        http_response_code(403);
        die('文件路径无效。');
    }
    if (!file_exists($realPath)) {
        http_response_code(404);
        die('文件不存在。');
    }

    $fileSize = filesize($realPath);
    $fileName = basename($realPath);
    $mimeType = mime_content_type($realPath) ?: 'application/octet-stream';

    // Increment download count only on non-Range or full-file requests (fixes multi-thread inflation)
    $isRange = isset($_SERVER['HTTP_RANGE']);

    ob_clean();
    ob_end_flush();

    if ($isRange) {
        // Parse Range header
        preg_match('/bytes=(\d*)-(\d*)/i', $_SERVER['HTTP_RANGE'], $matches);
        $start = $matches[1] !== '' ? (int)$matches[1] : 0;
        $end   = $matches[2] !== '' ? (int)$matches[2] : $fileSize - 1;

        if ($start > $end || $start >= $fileSize) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes */$fileSize");
            exit;
        }

        $length = $end - $start + 1;

        header('HTTP/1.1 206 Partial Content');
        header("Content-Range: bytes $start-$end/$fileSize");
        header("Content-Length: $length");
        header("Content-Type: $mimeType");
        header("Content-Disposition: attachment; filename=\"" . addslashes($fileName) . "\"");
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache');

        $fp = fopen($realPath, 'rb');
        fseek($fp, $start);
        $bytesSent = 0;
        while (!feof($fp) && $bytesSent < $length) {
            $chunk = min(DOWNLOAD_CHUNK_SIZE, $length - $bytesSent);
            echo fread($fp, $chunk);
            $bytesSent += $chunk;
        }
        fclose($fp);
    } else {
        header("Content-Type: $mimeType");
        header("Content-Disposition: attachment; filename=\"" . addslashes($fileName) . "\"");
        header("Content-Length: $fileSize");
        header('Accept-Ranges: bytes');
        header('Cache-Control: no-cache');

        $fp = fopen($realPath, 'rb');
        while (!feof($fp)) {
            echo fread($fp, DOWNLOAD_CHUNK_SIZE);
            flush();
        }
        fclose($fp);
    }
    exit;
}

function incrementDownloadCount(int $resourceId): void {
    $db = getDB();
    $stmt = $db->prepare('UPDATE resources SET download_count = download_count + 1 WHERE id = ?');
    $stmt->execute([$resourceId]);
}

// --- VIP Password ---

function verifyVipPassword(int $resourceId, string $password): bool {
    $db = getDB();
    $stmt = $db->prepare("SELECT vip_password FROM resources WHERE id = ? AND is_vip = 1 AND status = 'active'");
    $stmt->execute([$resourceId]);
    $row = $stmt->fetch();
    if (!$row || empty($row['vip_password'])) return false;

    if (password_verify($password, $row['vip_password'])) {
        // Store hash in session so password changes invalidate old sessions (fixes stale session issue)
        $_SESSION['vip_pass_' . $resourceId] = $row['vip_password'];
        return true;
    }
    return false;
}

function isVipVerified(int $resourceId): bool {
    if (is_admin()) return true;

    $resource = getResourceById($resourceId);
    if (!$resource || !$resource['is_vip']) return true;

    $sessionHash = $_SESSION['vip_pass_' . $resourceId] ?? '';
    // Compare stored hash with current DB hash — if admin changed password, session is invalidated
    return ($sessionHash !== '' && hash_equals($resource['vip_password'], $sessionHash));
}

// --- File Upload ---

function getAllowedExtensions(): array {
    $extStr = getSetting('allowed_extensions', 'zip,rar,7z,pdf,exe,msi,iso,tar,gz');
    return array_map('trim', explode(',', strtolower($extStr)));
}

function getAllowedMimeTypes(): array {
    // Map common extensions to MIME types for validation
    return [
        'zip'  => ['application/zip', 'application/x-zip-compressed'],
        'rar'  => ['application/vnd.rar', 'application/x-rar-compressed'],
        '7z'   => ['application/x-7z-compressed'],
        'pdf'  => ['application/pdf'],
        'exe'  => ['application/x-msdownload', 'application/x-dosexec', 'application/octet-stream'],
        'msi'  => ['application/x-msi', 'application/octet-stream'],
        'iso'  => ['application/x-iso9660-image', 'application/octet-stream'],
        'tar'  => ['application/x-tar'],
        'gz'   => ['application/gzip', 'application/x-gzip'],
    ];
}

function validateUploadedFile(array $file): string|bool {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE   => '文件超过服务器允许的最大大小。',
            UPLOAD_ERR_FORM_SIZE  => '文件超过表单允许的最大大小。',
            UPLOAD_ERR_PARTIAL    => '文件上传不完整。',
            UPLOAD_ERR_NO_FILE    => '未选择文件。',
            UPLOAD_ERR_NO_TMP_DIR => '服务器临时目录缺失。',
            UPLOAD_ERR_CANT_WRITE => '无法写入磁盘。',
        ];
        return $errors[$file['error']] ?? '上传错误，代码：' . $file['error'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = getAllowedExtensions();

    if (!in_array($ext, $allowedExts, true)) {
        return '不支持的文件类型：.' . $ext;
    }

    // MIME validation using finfo
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);
        $allowedMimes = getAllowedMimeTypes();
        $validMimes = $allowedMimes[$ext] ?? [];
        if (!empty($validMimes) && !in_array($detectedMime, $validMimes, true)) {
            return '文件内容类型（' . $detectedMime . '）与扩展名（.' . $ext . '）不匹配，拒绝上传。';
        }
    }

    return true;
}

function sanitizeFilename(string $filename): string {
    // Remove dangerous characters, keep alphanumeric, dots, dashes, underscores, Chinese
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $ext  = pathinfo($filename, PATHINFO_EXTENSION);
    $name = preg_replace('/[^\w\x{4e00}-\x{9fa5}\-.]/u', '_', $name);
    $name = trim($name, " .\t\n\r\0\x0B");
    if (empty($name)) $name = 'file';
    if (!empty($ext)) $name .= '.' . $ext;
    return $name;
}

function handleUpload(array $file, int $uploaderId, string $title, string $description, int $categoryId): array|string {
    // Validate
    $validation = validateUploadedFile($file);
    if ($validation !== true) return $validation;

    $uploadDir = resolvePath(getSetting('user_upload_dir', 'uploads'));
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true)) {
            return '上传目录不可用，请联系管理员。';
        }
    }

    $safeName = sanitizeFilename($file['name']);
    // Prevent overwrites: prefix with user ID + timestamp
    $uniqueName = $uploaderId . '_' . time() . '_' . $safeName;
    $destPath   = $uploadDir . '/' . $uniqueName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return '文件保存失败。';
    }

    $relPath  = toRelativePath($destPath);
    $fileSize = filesize($destPath);

    if (empty($title)) {
        $title = pathinfo($safeName, PATHINFO_FILENAME);
    }

    $db = getDB();
    $stmt = $db->prepare(
        "INSERT INTO resources (title, description, type, local_path, file_size, uploader_id, category_id, is_vip, vip_password, download_count, status)
         VALUES (?, ?, 'local', ?, ?, ?, ?, 0, NULL, 0, 'active')"
    );
    $stmt->execute([$title, $description, $relPath, $fileSize, $uploaderId, $categoryId]);

    return ['id' => (int)$db->lastInsertId(), 'title' => $title];
}

// --- Formatting Helpers ---

function formatFileSize(int $bytes): string {
    if ($bytes === 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int)floor(log($bytes, 1024));
    return round($bytes / (1024 ** $i), 1) . ' ' . $units[$i];
}

function getFileIconClass(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'zip' => 'fa-file-zipper', 'rar' => 'fa-file-zipper', '7z' => 'fa-file-zipper',
        'gz' => 'fa-file-zipper', 'tar' => 'fa-file-zipper',
        'pdf' => 'fa-file-pdf',
        'exe' => 'fa-file-code', 'msi' => 'fa-file-code',
        'iso' => 'fa-file-image',
        'jpg' => 'fa-file-image', 'jpeg' => 'fa-file-image', 'png' => 'fa-file-image',
        'gif' => 'fa-file-image', 'bmp' => 'fa-file-image',
        'mp4' => 'fa-file-video', 'avi' => 'fa-file-video', 'mkv' => 'fa-file-video',
        'mp3' => 'fa-file-audio', 'wav' => 'fa-file-audio', 'flac' => 'fa-file-audio',
        'txt' => 'fa-file-lines', 'md' => 'fa-file-lines',
    ];
    return $icons[$ext] ?? 'fa-file';
}

function getExtension(string $filename): string {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function paginationLinks(int $page, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) return '';

    $html = '<div class="pagination">';
    $buildUrl = function(int $p) use ($baseUrl): string {
        $sep = (str_contains($baseUrl, '?')) ? '&' : '?';
        return $baseUrl . $sep . 'page=' . $p;
    };

    if ($page > 1) {
        $html .= '<a href="' . $buildUrl($page - 1) . '" class="page-link">&laquo; 上一页</a>';
    }

    $start = max(1, $page - 3);
    $end   = min($totalPages, $page + 3);

    if ($start > 1) {
        $html .= '<a href="' . $buildUrl(1) . '" class="page-link">1</a>';
        if ($start > 2) $html .= '<span class="page-ellipsis">...</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = ($i === $page) ? ' active' : '';
        $html .= '<a href="' . $buildUrl($i) . '" class="page-link' . $active . '">' . $i . '</a>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $html .= '<span class="page-ellipsis">...</span>';
        $html .= '<a href="' . $buildUrl($totalPages) . '" class="page-link">' . $totalPages . '</a>';
    }

    if ($page < $totalPages) {
        $html .= '<a href="' . $buildUrl($page + 1) . '" class="page-link">下一页 &raquo;</a>';
    }

    $html .= '</div>';
    return $html;
}

// --- Sanitize Output ---

function h(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
