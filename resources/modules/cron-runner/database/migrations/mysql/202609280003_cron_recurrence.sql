ALTER TABLE `cron_tasks` ADD COLUMN `repeat_interval_seconds` INT UNSIGNED NULL AFTER `last_error`;
ALTER TABLE `cron_tasks` ADD COLUMN `attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `repeat_interval_seconds`;
