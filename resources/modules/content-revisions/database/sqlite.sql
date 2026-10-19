CREATE TABLE IF NOT EXISTS content_revisions (
 id INTEGER PRIMARY KEY AUTOINCREMENT,
 resource_key TEXT NOT NULL,
 revision TEXT NOT NULL,
 author_id INTEGER NULL,
 created_at TEXT NOT NULL,
 fields_json TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS content_revision_resource ON content_revisions(resource_key, id);
