CREATE TABLE IF NOT EXISTS media (
  media_id INTEGER PRIMARY KEY AUTOINCREMENT,
  scope_type TEXT NOT NULL DEFAULT 'global',
  scope_id INTEGER NULL,
  source TEXT NOT NULL DEFAULT 'library',
  kind TEXT NOT NULL,
  name TEXT NOT NULL,
  original_name TEXT NOT NULL,
  path TEXT NOT NULL UNIQUE,
  remote_url TEXT NULL,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL DEFAULT 0,
  alt_text TEXT NOT NULL DEFAULT '',
  metadata_json TEXT NULL,
  variants_json TEXT NULL,
  status TEXT NOT NULL DEFAULT 'ready',
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_media_scope_kind ON media (scope_type, scope_id, kind);
CREATE INDEX IF NOT EXISTS idx_media_source ON media (source);
