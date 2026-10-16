CREATE TABLE IF NOT EXISTS content_autosaves (
    draft_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    version CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    content MEDIUMTEXT NOT NULL,
    base_version VARCHAR(128) NOT NULL,
    discarded TINYINT NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
