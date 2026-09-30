CREATE TABLE IF NOT EXISTS gframe_sessions (
  session_hash TEXT PRIMARY KEY,
  user_id INTEGER NULL,
  role_id INTEGER NULL,
  role_version INTEGER NOT NULL DEFAULT 1,
  authorization_version INTEGER NOT NULL DEFAULT 1,
  payload BLOB NOT NULL,
  last_activity INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_gframe_sessions_user ON gframe_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_gframe_sessions_role ON gframe_sessions(role_id);
CREATE INDEX IF NOT EXISTS idx_gframe_sessions_expiry ON gframe_sessions(expires_at);
