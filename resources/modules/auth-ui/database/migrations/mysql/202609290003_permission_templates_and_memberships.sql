ALTER TABLE `users` ADD COLUMN `authorization_version` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `force_password_change`;
ALTER TABLE `users` ADD COLUMN `permission_overrides_json` JSON NULL AFTER `authorization_version`;
ALTER TABLE `roles` ADD COLUMN `permissions_json` JSON NULL AFTER `security_version`;
ALTER TABLE `gframe_sessions` ADD COLUMN `authorization_version` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `role_version`;
ALTER TABLE `gframe_sessions` ADD COLUMN `tenant_role_id` BIGINT UNSIGNED NULL AFTER `authorization_version`;
ALTER TABLE `gframe_sessions` ADD COLUMN `tenant_role_version` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `tenant_role_id`;

CREATE TABLE IF NOT EXISTS `tenant_memberships` (
  `user_id` BIGINT UNSIGNED NOT NULL, `tenant_id` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `role_id` BIGINT UNSIGNED NOT NULL, `is_active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
  `permission_overrides_json` JSON NULL,
  PRIMARY KEY (`user_id`, `tenant_id`), KEY `idx_tenant_memberships_role` (`role_id`),
  KEY `idx_tenant_memberships_tenant` (`tenant_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
