ALTER TABLE users ADD COLUMN authorization_version INTEGER NOT NULL DEFAULT 1;
ALTER TABLE users ADD COLUMN permission_overrides_json TEXT NOT NULL DEFAULT '{}';
ALTER TABLE roles ADD COLUMN permissions_json TEXT NOT NULL DEFAULT '{}';
ALTER TABLE gframe_sessions ADD COLUMN authorization_version INTEGER NOT NULL DEFAULT 1;
ALTER TABLE gframe_sessions ADD COLUMN tenant_role_id INTEGER NULL;
ALTER TABLE gframe_sessions ADD COLUMN tenant_role_version INTEGER NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS tenant_memberships (
  user_id INTEGER NOT NULL, tenant_id INTEGER NOT NULL DEFAULT 0, role_id INTEGER NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1, permission_overrides_json TEXT NOT NULL DEFAULT '{}', PRIMARY KEY (user_id, tenant_id)
);
CREATE INDEX IF NOT EXISTS idx_tenant_memberships_role ON tenant_memberships(role_id);
CREATE INDEX IF NOT EXISTS idx_tenant_memberships_tenant ON tenant_memberships(tenant_id, user_id);
