CREATE TABLE IF NOT EXISTS `gframe_sessions` (
  `session_hash` CHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `role_id` BIGINT UNSIGNED NULL,
  `role_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `authorization_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `payload` MEDIUMBLOB NOT NULL,
  `last_activity` BIGINT UNSIGNED NOT NULL,
  `expires_at` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`session_hash`),
  KEY `idx_gframe_sessions_user` (`user_id`),
  KEY `idx_gframe_sessions_role` (`role_id`),
  KEY `idx_gframe_sessions_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
