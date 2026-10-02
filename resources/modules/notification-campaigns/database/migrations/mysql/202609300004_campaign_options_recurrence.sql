ALTER TABLE notification_campaigns ADD COLUMN recurrence VARCHAR(10) NOT NULL DEFAULT 'once';
ALTER TABLE notification_campaigns ADD COLUMN parent_id BIGINT UNSIGNED NULL;
ALTER TABLE notification_campaigns ADD COLUMN importance VARCHAR(10) NOT NULL DEFAULT 'info';
ALTER TABLE notification_campaigns ADD COLUMN action_url VARCHAR(255) NULL;
ALTER TABLE notification_campaigns ADD COLUMN expires_after_days INT NOT NULL DEFAULT 0;
CREATE UNIQUE INDEX `uq_campaign_occurrence` ON `notification_campaigns` (`parent_id`, `scheduled_at`);
CREATE TABLE IF NOT EXISTS `notification_campaign_recurrences` (`campaign_id` BIGINT UNSIGNED NOT NULL, `audience_json` JSON NOT NULL, `next_at` DATETIME NOT NULL, PRIMARY KEY (`campaign_id`)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
