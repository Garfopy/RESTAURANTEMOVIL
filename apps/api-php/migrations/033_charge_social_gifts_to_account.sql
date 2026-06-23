-- Carga regalos sociales a la cuenta de la mesa remitente.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='sender_mesa_id'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `sender_mesa_id` INT UNSIGNED NULL AFTER `mesa_id`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='pedido_id'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `pedido_id` INT UNSIGNED NULL AFTER `sender_mesa_id`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='pedido_item_id'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `pedido_item_id` INT UNSIGNED NULL AFTER `pedido_id`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='cargado_cuenta_at'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `cargado_cuenta_at` DATETIME NULL AFTER `pagado_at`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `social_gift_account_products` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `gift_product_id` INT UNSIGNED NOT NULL,
  `platillo_id` INT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social_gift_account_product` (`restaurante_id`, `gift_product_id`),
  UNIQUE KEY `uq_social_gift_account_dish` (`platillo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND INDEX_NAME='idx_sgo_sender_account'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD KEY `idx_sgo_sender_account` (`sender_mesa_id`, `pedido_id`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
