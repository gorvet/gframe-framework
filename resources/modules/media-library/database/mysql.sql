CREATE TABLE IF NOT EXISTS `media` (
  `media_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `scope_type` VARCHAR(20) NOT NULL DEFAULT 'global',
  `scope_id` BIGINT UNSIGNED NULL,
  `source` VARCHAR(80) NOT NULL DEFAULT 'library',
  `kind` VARCHAR(20) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NOT NULL,
  `path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(150) NOT NULL,
  `size_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`media_id`),
  UNIQUE KEY `uq_media_path` (`path`),
  KEY `idx_media_scope_kind` (`scope_type`, `scope_id`, `kind`),
  KEY `idx_media_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `media_relations` (
  `media_id` BIGINT UNSIGNED NOT NULL,
  `related_type` VARCHAR(100) NOT NULL,
  `related_id` BIGINT UNSIGNED NOT NULL,
  `field` VARCHAR(100) NOT NULL DEFAULT 'content',
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`media_id`, `related_type`, `related_id`, `field`),
  KEY `idx_media_relation_target` (`related_type`, `related_id`, `field`, `sort_order`),
  CONSTRAINT `fk_media_relations_media` FOREIGN KEY (`media_id`) REFERENCES `media` (`media_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
