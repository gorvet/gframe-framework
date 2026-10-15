CREATE TABLE IF NOT EXISTS cron_runtime (
  runtime_id INT NOT NULL PRIMARY KEY,
  launch_token VARCHAR(48) NULL,
  pending_until BIGINT NOT NULL DEFAULT 0,
  next_launch_at BIGINT NOT NULL DEFAULT 0,
  last_launch_at BIGINT NULL,
  started_at BIGINT NULL,
  finished_at BIGINT NULL,
  processed INT NOT NULL DEFAULT 0,
  failed INT NOT NULL DEFAULT 0,
  recovered INT NOT NULL DEFAULT 0,
  last_error TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO cron_runtime (runtime_id) SELECT 1 WHERE NOT EXISTS (SELECT 1 FROM cron_runtime WHERE runtime_id = 1);
