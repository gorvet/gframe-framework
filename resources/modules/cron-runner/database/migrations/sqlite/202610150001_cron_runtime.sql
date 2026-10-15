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
