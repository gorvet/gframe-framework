ALTER TABLE cron_tasks ADD COLUMN repeat_interval_seconds INTEGER NULL;
ALTER TABLE cron_tasks ADD COLUMN attempts INTEGER NOT NULL DEFAULT 0;
