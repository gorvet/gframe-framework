CREATE TABLE IF NOT EXISTS content_editing_presence (
    presence_key TEXT NOT NULL PRIMARY KEY,
    resource_key TEXT NOT NULL,
    expires_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS content_presence_resource ON content_editing_presence(resource_key, expires_at);
