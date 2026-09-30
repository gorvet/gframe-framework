CREATE TABLE IF NOT EXISTS `cron_tasks` (
  `task_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_key` VARCHAR(120) NOT NULL,
  `handler_class` VARCHAR(255) NOT NULL,
  `payload_json` JSON NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `scheduled_at` DATETIME NOT NULL,
  `locked_at` DATETIME NULL,
  `last_run_at` DATETIME NULL,
  `last_error` TEXT NULL,
  `repeat_interval_seconds` INT UNSIGNED NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`task_id`),
  UNIQUE KEY `uq_cron_tasks_key` (`task_key`),
  KEY `idx_cron_tasks_due` (`is_active`, `status`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
