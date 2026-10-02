CREATE TABLE IF NOT EXISTS notification_account_deactivations (user_id INTEGER PRIMARY KEY, deactivated_at TEXT NOT NULL, delete_at TEXT NOT NULL, confirmation_id INTEGER NULL, reminder_id INTEGER NULL, warning_id INTEGER NULL, status TEXT NOT NULL DEFAULT 'pending');
CREATE INDEX IF NOT EXISTS idx_account_deactivation_due ON notification_account_deactivations (status, delete_at);
