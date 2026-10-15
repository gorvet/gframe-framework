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

CREATE TABLE IF NOT EXISTS cron_runtime (
  runtime_id INT NOT NULL PRIMARY KEY,
  launch_token VARCHAR(48) NULL,
  pending_until BIGINT NOT NULL DEFAULT 0,
  next_launch_at BIGINT NOT NULL DEFAULT 0,
  last_launch_at BIGINT NULL,
  started_at BIGINT NULL,
  finished_at BIGINT NULL,
  processed INT NOT NULL DEFAULT 0,
  failed INT NOT NULL DEFAULT 0,
  recovered INT NOT NULL DEFAULT 0,
  last_error TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO cron_runtime (runtime_id) SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM cron_runtime WHERE runtime_id = 1);
