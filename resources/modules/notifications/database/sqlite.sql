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
);
CREATE INDEX IF NOT EXISTS idx_notification_reserve ON notification_queue (status, available_at);
CREATE INDEX IF NOT EXISTS idx_notification_tenant ON notification_queue (tenant_id);
