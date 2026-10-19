CREATE TABLE IF NOT EXISTS content_editing_presence (
    presence_key CHAR(64) NOT NULL PRIMARY KEY,
    resource_key CHAR(64) NOT NULL,
    expires_at BIGINT NOT NULL,
    INDEX content_presence_resource (resource_key, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
