ALTER TABLE notification_campaigns ADD COLUMN audience_json TEXT NULL;
CREATE TABLE IF NOT EXISTS notification_automatic_campaign_history (history_id INTEGER PRIMARY KEY AUTOINCREMENT, scope_id INTEGER NOT NULL DEFAULT 0, run_key TEXT NOT NULL, rule_key TEXT NOT NULL, source TEXT NOT NULL, title TEXT NOT NULL, notification_id INTEGER NOT NULL UNIQUE, user_id INTEGER NOT NULL, queued_at TEXT NOT NULL);
CREATE INDEX IF NOT EXISTS idx_automatic_campaign_history_scope ON notification_automatic_campaign_history (scope_id, run_key, queued_at);
