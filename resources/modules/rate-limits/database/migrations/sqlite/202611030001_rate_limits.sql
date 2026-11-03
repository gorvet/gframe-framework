CREATE TABLE IF NOT EXISTS gframe_rate_locks (
    limiter TEXT NOT NULL PRIMARY KEY,
    revision INTEGER NOT NULL DEFAULT 0
);
INSERT OR IGNORE INTO gframe_rate_locks (limiter) VALUES ('auth'), ('mail');

CREATE TABLE IF NOT EXISTS gframe_rate_buckets (
    bucket_key TEXT NOT NULL PRIMARY KEY,
    attempts INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rate_buckets_expiry ON gframe_rate_buckets (expires_at);

CREATE TABLE IF NOT EXISTS gframe_rate_reservations (
    reservation_id TEXT NOT NULL PRIMARY KEY,
    bucket_key TEXT NOT NULL,
    reserved_at INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_rate_reservations_bucket ON gframe_rate_reservations (bucket_key, reserved_at);
CREATE INDEX IF NOT EXISTS idx_rate_reservations_expiry ON gframe_rate_reservations (expires_at);
