ALTER TABLE notification_campaigns ADD COLUMN recurrence TEXT NOT NULL DEFAULT 'once';
ALTER TABLE notification_campaigns ADD COLUMN parent_id INTEGER NULL;
ALTER TABLE notification_campaigns ADD COLUMN importance TEXT NOT NULL DEFAULT 'info';
ALTER TABLE notification_campaigns ADD COLUMN action_url TEXT NULL;
ALTER TABLE notification_campaigns ADD COLUMN expires_after_days INTEGER NOT NULL DEFAULT 0;
CREATE UNIQUE INDEX IF NOT EXISTS uq_campaign_occurrence ON notification_campaigns (parent_id, scheduled_at);
CREATE TABLE IF NOT EXISTS notification_campaign_recurrences (campaign_id INTEGER PRIMARY KEY, audience_json TEXT NOT NULL, next_at TEXT NOT NULL);
