ALTER TABLE `media` ADD COLUMN `alt_text` VARCHAR(255) NOT NULL DEFAULT '' AFTER `size_bytes`;
ALTER TABLE `media` ADD COLUMN `metadata_json` JSON NULL AFTER `alt_text`;
ALTER TABLE `media` ADD COLUMN `variants_json` JSON NULL AFTER `metadata_json`;
ALTER TABLE `media` ADD COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'ready' AFTER `variants_json`;
