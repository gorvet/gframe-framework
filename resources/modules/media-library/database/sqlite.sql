CREATE TABLE IF NOT EXISTS media (
  media_id INTEGER PRIMARY KEY AUTOINCREMENT,
  scope_type TEXT NOT NULL DEFAULT 'global',
  scope_id INTEGER NULL,
  source TEXT NOT NULL DEFAULT 'library',
  kind TEXT NOT NULL,
  name TEXT NOT NULL,
  original_name TEXT NOT NULL,
  path TEXT NOT NULL UNIQUE,
  mime_type TEXT NOT NULL,
  size_bytes INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_media_scope_kind ON media (scope_type, scope_id, kind);
CREATE INDEX IF NOT EXISTS idx_media_source ON media (source);

CREATE TABLE IF NOT EXISTS media_relations (
  media_id INTEGER NOT NULL,
  related_type TEXT NOT NULL,
  related_id INTEGER NOT NULL,
  field TEXT NOT NULL DEFAULT 'content',
  sort_order INTEGER NOT NULL DEFAULT 0,
  PRIMARY KEY (media_id, related_type, related_id, field),
  FOREIGN KEY (media_id) REFERENCES media(media_id) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_media_relation_target
  ON media_relations (related_type, related_id, field, sort_order);
