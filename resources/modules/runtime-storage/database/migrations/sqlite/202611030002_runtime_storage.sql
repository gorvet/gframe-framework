CREATE TABLE IF NOT EXISTS gframe_runtime_lock (
    lock_id INTEGER NOT NULL PRIMARY KEY,
    revision INTEGER NOT NULL DEFAULT 0
);
INSERT OR IGNORE INTO gframe_runtime_lock (lock_id) VALUES (1);
CREATE TABLE IF NOT EXISTS gframe_runtime_state (
    record_key TEXT NOT NULL PRIMARY KEY,
    payload TEXT NOT NULL,
    expires_at INTEGER NULL
);
CREATE INDEX IF NOT EXISTS idx_runtime_state_expiry ON gframe_runtime_state (expires_at);
CREATE TABLE IF NOT EXISTS gframe_sessions (
    session_hash TEXT NOT NULL PRIMARY KEY,
    user_id INTEGER NULL,
    role_id INTEGER NULL,
    role_version INTEGER NOT NULL DEFAULT 1,
    authorization_version INTEGER NOT NULL DEFAULT 1,
    tenant_role_id INTEGER NULL,
    tenant_role_version INTEGER NOT NULL DEFAULT 1,
    payload BLOB NOT NULL,
    last_activity INTEGER NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_gframe_sessions_user ON gframe_sessions (user_id);
CREATE INDEX IF NOT EXISTS idx_gframe_sessions_expiry ON gframe_sessions (expires_at);
