CREATE TABLE IF NOT EXISTS `notification_campaigns` (
  `campaign_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `tenant_id` BIGINT UNSIGNED NULL,
  `name` VARCHAR(160) NOT NULL, `title` VARCHAR(190) NOT NULL, `message` MEDIUMTEXT NOT NULL, `audience_json` JSON NULL,
  `recurrence` VARCHAR(10) NOT NULL DEFAULT 'once', `parent_id` BIGINT UNSIGNED NULL, `importance` VARCHAR(10) NOT NULL DEFAULT 'info', `action_url` VARCHAR(255) NULL, `expires_after_days` INT NOT NULL DEFAULT 0,
  `template_id` VARCHAR(120) NOT NULL DEFAULT 'notification', `channels_json` JSON NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft', `scheduled_at` DATETIME NULL,
  `started_at` DATETIME NULL, `completed_at` DATETIME NULL, `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL, `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`campaign_id`), KEY `idx_campaign_status` (`tenant_id`, `status`, `scheduled_at`), UNIQUE KEY `uq_campaign_occurrence` (`parent_id`, `scheduled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `notification_campaign_recipients` (
  `recipient_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `campaign_id` BIGINT UNSIGNED NOT NULL, `channel` VARCHAR(80) NOT NULL,
  `recipient` VARCHAR(255) NOT NULL, `variables_json` JSON NULL, `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `queued_jobs` INT UNSIGNED NOT NULL DEFAULT 0, `last_error` VARCHAR(1000) NULL,
  `processing_at` DATETIME NULL, `queued_at` DATETIME NULL, `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`recipient_id`), UNIQUE KEY `uq_campaign_recipient` (`campaign_id`, `channel`, `recipient`),
  KEY `idx_campaign_recipient_status` (`campaign_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `notification_campaign_rules` (`rule_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0, `rule_key` VARCHAR(80) NOT NULL, `is_active` TINYINT NOT NULL DEFAULT 0, `cooldown_days` INT NOT NULL DEFAULT 7, `title` VARCHAR(160) NOT NULL, `message` MEDIUMTEXT NOT NULL, `updated_at` DATETIME NOT NULL, PRIMARY KEY (`rule_id`), UNIQUE KEY `uq_campaign_rule_scope` (`scope_id`, `rule_key`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `notification_campaign_deliveries` (`delivery_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, `scope_id` BIGINT UNSIGNED NOT NULL DEFAULT 0, `rule_key` VARCHAR(80) NOT NULL, `user_id` BIGINT UNSIGNED NOT NULL, `event_id` VARCHAR(190) NOT NULL, `source` VARCHAR(20) NOT NULL, `notification_id` BIGINT UNSIGNED NULL, `queued_at` DATETIME NOT NULL, PRIMARY KEY (`delivery_id`), UNIQUE KEY `uq_campaign_delivery_user` (`scope_id`, `rule_key`, `user_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `notification_account_deactivations` (`user_id` BIGINT UNSIGNED NOT NULL, `deactivated_at` DATETIME NOT NULL, `delete_at` DATETIME NOT NULL, `confirmation_id` BIGINT UNSIGNED NULL, `reminder_id` BIGINT UNSIGNED NULL, `warning_id` BIGINT UNSIGNED NULL, `status` VARCHAR(20) NOT NULL DEFAULT 'pending', PRIMARY KEY (`user_id`), KEY `idx_account_deactivation_due` (`status`, `delete_at`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS `notification_campaign_recurrences` (`campaign_id` BIGINT UNSIGNED NOT NULL, `audience_json` JSON NOT NULL, `next_at` DATETIME NOT NULL, PRIMARY KEY (`campaign_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS notification_automatic_campaign_history (history_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, scope_id BIGINT UNSIGNED NOT NULL DEFAULT 0, run_key VARCHAR(64) NOT NULL, rule_key VARCHAR(80) NOT NULL, source VARCHAR(20) NOT NULL, title VARCHAR(160) NOT NULL, notification_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL, queued_at DATETIME NOT NULL, PRIMARY KEY (history_id), UNIQUE KEY uq_automatic_campaign_history_notification (notification_id), KEY idx_automatic_campaign_history_scope (scope_id, run_key, queued_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
