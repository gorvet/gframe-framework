CREATE TABLE IF NOT EXISTS `notification_queue` (
  `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NULL,
  `channel` VARCHAR(50) NOT NULL,
  `recipient` VARCHAR(255) NOT NULL,
  `payload_json` LONGTEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `last_error` VARCHAR(1000) NULL,
  `available_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `sent_at` DATETIME NULL,
  `deduplication_key` VARCHAR(190) NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_notification_reserve` (`status`, `available_at`),
  KEY `idx_notification_tenant` (`tenant_id`),
  UNIQUE KEY `uq_notification_deduplication` (`deduplication_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_notifications` (
  `notification_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tenant_id` BIGINT UNSIGNED NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `type` VARCHAR(80) NOT NULL DEFAULT 'system',
  `importance` VARCHAR(20) NOT NULL DEFAULT 'info',
  `title` VARCHAR(180) NOT NULL,
  `message` MEDIUMTEXT NOT NULL,
  `action_url` VARCHAR(255) NULL,
  `meta_json` LONGTEXT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME NULL,
  `is_deleted` TINYINT(1) NOT NULL DEFAULT 0,
  `deleted_at` DATETIME NULL,
  `expires_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`notification_id`),
  KEY `idx_user_notifications_inbox` (`tenant_id`, `user_id`, `is_deleted`, `notification_id`),
  KEY `idx_user_notifications_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
