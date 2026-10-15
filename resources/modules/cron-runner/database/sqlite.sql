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

CREATE TABLE IF NOT EXISTS cron_runtime (
  runtime_id INTEGER NOT NULL PRIMARY KEY,
  launch_token TEXT NULL,
  pending_until INTEGER NOT NULL DEFAULT 0,
  next_launch_at INTEGER NOT NULL DEFAULT 0,
  last_launch_at INTEGER NULL,
  started_at INTEGER NULL,
  finished_at INTEGER NULL,
  processed INTEGER NOT NULL DEFAULT 0,
  failed INTEGER NOT NULL DEFAULT 0,
  recovered INTEGER NOT NULL DEFAULT 0,
  last_error TEXT NULL
);
INSERT INTO cron_runtime (runtime_id) SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM cron_runtime WHERE runtime_id = 1);
