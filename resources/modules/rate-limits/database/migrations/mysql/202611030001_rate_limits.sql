CREATE TABLE IF NOT EXISTS gframe_rate_locks (
    limiter VARCHAR(16) NOT NULL PRIMARY KEY,
    revision BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT IGNORE INTO gframe_rate_locks (limiter) VALUES ('auth'), ('mail');

CREATE TABLE IF NOT EXISTS gframe_rate_buckets (
    bucket_key CHAR(64) NOT NULL PRIMARY KEY,
    attempts BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX idx_rate_buckets_expiry (expires_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS gframe_rate_reservations (
    reservation_id CHAR(32) NOT NULL PRIMARY KEY,
    bucket_key CHAR(64) NOT NULL,
    reserved_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX idx_rate_reservations_bucket (bucket_key, reserved_at),
    INDEX idx_rate_reservations_expiry (expires_at)
) ENGINE=InnoDB;
