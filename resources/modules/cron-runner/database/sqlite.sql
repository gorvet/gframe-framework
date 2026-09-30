CREATE TABLE IF NOT EXISTS cron_tasks (
  task_id INTEGER PRIMARY KEY AUTOINCREMENT,
  task_key TEXT NOT NULL UNIQUE,
  handler_class TEXT NOT NULL,
  payload_json TEXT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  scheduled_at TEXT NOT NULL,
  locked_at TEXT NULL,
  last_run_at TEXT NULL,
  last_error TEXT NULL,
  repeat_interval_seconds INTEGER NULL,
  attempts INTEGER NOT NULL DEFAULT 0,
  is_active INTEGER NOT NULL DEFAULT 1
);
CREATE INDEX IF NOT EXISTS idx_cron_tasks_due ON cron_tasks (is_active, status, scheduled_at);
