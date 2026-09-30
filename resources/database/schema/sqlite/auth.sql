PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS roles (
  role_id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  is_system INTEGER NOT NULL DEFAULT 0,
  security_version INTEGER NOT NULL DEFAULT 1,
  permissions_json TEXT NOT NULL DEFAULT '{}',
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

INSERT OR IGNORE INTO roles (name, slug, is_system)
VALUES
  ('Superadministrador', 'superadministrator', 1),
  ('Usuario registrado', 'registered', 1);

CREATE TABLE IF NOT EXISTS users (
  user_id INTEGER PRIMARY KEY AUTOINCREMENT,
  email TEXT NOT NULL UNIQUE,
  password TEXT NOT NULL,
  role_id INTEGER NOT NULL,
  status TEXT NOT NULL DEFAULT 'unverify',
  token TEXT NOT NULL DEFAULT '',
  token_updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  password_changed_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  force_password_change INTEGER NOT NULL DEFAULT 0,
  authorization_version INTEGER NOT NULL DEFAULT 1,
  permission_overrides_json TEXT NOT NULL DEFAULT '{}',
  last_login TEXT NULL,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(role_id)
);

CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role_id, status);

CREATE TABLE IF NOT EXISTS tenant_memberships (
  user_id INTEGER NOT NULL,
  tenant_id INTEGER NOT NULL DEFAULT 0,
  role_id INTEGER NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  permission_overrides_json TEXT NOT NULL DEFAULT '{}',
  PRIMARY KEY (user_id, tenant_id)
);

CREATE INDEX IF NOT EXISTS idx_tenant_memberships_role ON tenant_memberships(role_id);
CREATE INDEX IF NOT EXISTS idx_tenant_memberships_tenant ON tenant_memberships(tenant_id, user_id);

CREATE TABLE IF NOT EXISTS gframe_sessions (
  session_hash TEXT PRIMARY KEY,
  user_id INTEGER NULL,
  role_id INTEGER NULL,
  role_version INTEGER NOT NULL DEFAULT 1,
  authorization_version INTEGER NOT NULL DEFAULT 1,
  payload BLOB NOT NULL,
  tenant_role_id INTEGER NULL,
  tenant_role_version INTEGER NOT NULL DEFAULT 1,
  last_activity INTEGER NOT NULL,
  expires_at INTEGER NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_gframe_sessions_user ON gframe_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_gframe_sessions_role ON gframe_sessions(role_id);
CREATE INDEX IF NOT EXISTS idx_gframe_sessions_expiry ON gframe_sessions(expires_at);
