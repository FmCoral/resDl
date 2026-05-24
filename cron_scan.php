<?php
/**
 * Cron Scan Script
 * 用于定时任务定期执行目录同步，避免用户请求时扫描大目录。
 *
 * 配置 cron（每 5 分钟）：
 *   */5 * * * * /usr/bin/php /www/wwwroot/your_site/cron_scan.php
 *
 * 或通过宝塔面板的计划任务功能添加。
 */

require_once __DIR__ . '/inc/init.php';

// Force reset batch offset for fresh scan
unset($_SESSION['scan_batch_offset']);
updateSetting('last_scan_time', '0');

$result = syncLocalFilesFromScanDirs();

$logEntry = sprintf(
    "[%s] 扫描完成 — 新增: %d, 删除: %d, 消息: %s\n",
    date('Y-m-d H:i:s'),
    $result['synced'],
    $result['deleted'],
    $result['message']
);

// Log to file
$logFile = __DIR__ . '/scan.log';
file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

echo $logEntry;
