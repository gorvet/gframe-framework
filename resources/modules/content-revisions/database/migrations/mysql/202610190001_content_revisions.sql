CREATE TABLE IF NOT EXISTS content_revisions (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 resource_key CHAR(64) NOT NULL,
 revision VARCHAR(128) NOT NULL,
 author_id BIGINT NULL,
 created_at VARCHAR(19) NOT NULL,
 fields_json LONGTEXT NOT NULL,
 INDEX content_revision_resource (resource_key, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
