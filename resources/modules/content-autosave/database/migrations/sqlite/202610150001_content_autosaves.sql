CREATE TABLE IF NOT EXISTS content_autosaves (
    draft_key TEXT PRIMARY KEY,
    version TEXT NOT NULL,
    content TEXT NOT NULL,
    base_version TEXT NOT NULL,
    discarded INTEGER NOT NULL DEFAULT 0,
    updated_at TEXT NOT NULL,
    expires_at TEXT NOT NULL
);
