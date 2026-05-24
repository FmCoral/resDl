-- resDl Database Installation Script
-- For MySQL 5.7+ / MariaDB 10.2+ with utf8mb4

CREATE DATABASE IF NOT EXISTS `resdl` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `resdl`;

-- Users table
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table
CREATE TABLE `categories` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT 0,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Resources table
CREATE TABLE `resources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text,
  `type` enum('local','external') NOT NULL,
  `local_path` varchar(500) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT 0,
  `uploader_id` int(11) DEFAULT NULL,
  `category_id` int(11) DEFAULT NULL,
  `is_vip` tinyint(1) DEFAULT 0,
  `vip_password` varchar(255) DEFAULT NULL,
  `download_count` int(11) DEFAULT 0,
  `status` enum('active','deleted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `uploader_id` (`uploader_id`),
  KEY `category_id` (`category_id`),
  KEY `idx_type_status` (`type`, `status`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_local_path` (`local_path`(255)),
  KEY `idx_category_status` (`category_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table
CREATE TABLE `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- CSRF tokens table (for session-independent token validation)
CREATE TABLE `csrf_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` timestamp NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_user_token` (`user_id`, `token`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default category (do NOT rely on id=1; use setting to track default)
INSERT INTO `categories` (`name`, `parent_id`, `sort_order`) VALUES ('未分类', 0, 0);

-- Settings
INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('scan_mode', 'fixed'),
('fixed_scan_dir', 'uploads'),
('specified_scan_dirs', '[]'),
('user_upload_dir', 'uploads'),
('items_per_page', '10'),
('sync_cache_ttl', '120'),
('allowed_extensions', 'zip,rar,7z,pdf,exe,msi,iso,tar,gz'),
('allow_registration', '1'),
('allow_upload', '1'),
('last_scan_time', '0'),
('default_category_id', '1'),
('site_name', 'resDl 资源下载站'),
('scan_batch_size', '200'),
('site_url', '');

-- Note: Admin user is created via install.php with proper password_hash()
