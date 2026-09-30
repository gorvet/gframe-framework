CREATE TABLE IF NOT EXISTS notification_queue (
  notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NULL,
  channel TEXT NOT NULL,
  recipient TEXT NOT NULL,
  payload_json TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL,
  available_at TEXT NOT NULL,
  created_at TEXT NOT NULL,
  sent_at TEXT NULL
  ,deduplication_key TEXT NULL UNIQUE
);
CREATE INDEX IF NOT EXISTS idx_notification_reserve ON notification_queue (status, available_at);
CREATE INDEX IF NOT EXISTS idx_notification_tenant ON notification_queue (tenant_id);

CREATE TABLE IF NOT EXISTS user_notifications (
  notification_id INTEGER PRIMARY KEY AUTOINCREMENT,
  tenant_id INTEGER NULL,
  user_id INTEGER NOT NULL,
  type TEXT NOT NULL DEFAULT 'system',
  importance TEXT NOT NULL DEFAULT 'info',
  title TEXT NOT NULL,
  message TEXT NOT NULL,
  action_url TEXT NULL,
  meta_json TEXT NULL,
  is_read INTEGER NOT NULL DEFAULT 0,
  read_at TEXT NULL,
  is_deleted INTEGER NOT NULL DEFAULT 0,
  deleted_at TEXT NULL,
  expires_at TEXT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_user_notifications_inbox ON user_notifications (tenant_id, user_id, is_deleted, notification_id);
CREATE INDEX IF NOT EXISTS idx_user_notifications_expiry ON user_notifications (expires_at);
