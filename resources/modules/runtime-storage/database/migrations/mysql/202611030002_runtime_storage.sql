CREATE TABLE IF NOT EXISTS gframe_runtime_lock (
    lock_id INT NOT NULL PRIMARY KEY,
    revision BIGINT NOT NULL DEFAULT 0
) ENGINE=InnoDB;
INSERT IGNORE INTO gframe_runtime_lock (lock_id) VALUES (1);
CREATE TABLE IF NOT EXISTS gframe_runtime_state (
    record_key CHAR(64) NOT NULL PRIMARY KEY,
    payload LONGTEXT NOT NULL,
    expires_at BIGINT NULL,
    INDEX idx_runtime_state_expiry (expires_at)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS gframe_sessions (
    session_hash CHAR(64) NOT NULL PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    role_id BIGINT UNSIGNED NULL,
    role_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    authorization_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    tenant_role_id BIGINT UNSIGNED NULL,
    tenant_role_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    payload LONGBLOB NOT NULL,
    last_activity BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX idx_gframe_sessions_user (user_id),
    INDEX idx_gframe_sessions_expiry (expires_at)
) ENGINE=InnoDB;
