CREATE TABLE IF NOT EXISTS `roles` (
  `role_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `slug` VARCHAR(120) NOT NULL,
  `is_system` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `security_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `permissions_json` JSON NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `roles` (`name`, `slug`, `is_system`)
VALUES
  ('Superadministrador', 'superadministrator', 1),
  ('Usuario registrado', 'registered', 1);

CREATE TABLE IF NOT EXISTS `users` (
  `user_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'unverify',
  `token` VARCHAR(255) NOT NULL DEFAULT '',
  `token_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `password_changed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `force_password_change` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
  `authorization_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `permission_overrides_json` JSON NULL,
  `last_login` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role_status` (`role_id`, `status`),
  CONSTRAINT `fk_users_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tenant_memberships` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `permission_overrides_json` JSON NULL,
  PRIMARY KEY (`user_id`, `tenant_id`),
  KEY `idx_tenant_memberships_role` (`role_id`),
  KEY `idx_tenant_memberships_tenant` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `gframe_sessions` (
  `session_hash` CHAR(64) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL,
  `role_id` BIGINT UNSIGNED NULL,
  `role_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `authorization_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `payload` MEDIUMBLOB NOT NULL,
  `tenant_role_id` BIGINT UNSIGNED NULL,
  `tenant_role_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `last_activity` BIGINT UNSIGNED NOT NULL,
  `expires_at` BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (`session_hash`),
  KEY `idx_gframe_sessions_user` (`user_id`),
  KEY `idx_gframe_sessions_role` (`role_id`),
  KEY `idx_gframe_sessions_expiry` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
