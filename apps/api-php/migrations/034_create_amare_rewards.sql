-- Recompensas Amare V1: saldo simulado, descuento y puntos.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

CREATE TABLE IF NOT EXISTS `amare_wallets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `balance_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `points` INT UNSIGNED NOT NULL DEFAULT 0,
  `simulated_balance` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_amare_wallet_user` (`user_id`),
  KEY `idx_amare_wallet_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `amare_wallet_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `wallet_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `context` VARCHAR(30) NULL,
  `reference_type` VARCHAR(40) NULL,
  `reference_id` INT UNSIGNED NULL,
  `amount_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `points_delta` INT NOT NULL DEFAULT 0,
  `balance_after_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `points_after` INT UNSIGNED NOT NULL DEFAULT 0,
  `description` VARCHAR(255) NULL,
  `metadata_json` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_transactions_wallet` (`wallet_id`, `created_at`),
  KEY `idx_wallet_transactions_user` (`user_id`, `created_at`),
  KEY `idx_wallet_transactions_reference` (`reference_type`, `reference_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_pedidos' AND COLUMN_NAME='amare_wallet_used_mxn'),
  'SELECT 1',
  'ALTER TABLE `rest_pedidos` ADD COLUMN `amare_wallet_used_mxn` DECIMAL(12,2) NULL AFTER `total`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_pedidos' AND COLUMN_NAME='amare_discount_mxn'),
  'SELECT 1',
  'ALTER TABLE `rest_pedidos` ADD COLUMN `amare_discount_mxn` DECIMAL(12,2) NULL AFTER `amare_wallet_used_mxn`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_pedidos' AND COLUMN_NAME='amare_points_redeemed'),
  'SELECT 1',
  'ALTER TABLE `rest_pedidos` ADD COLUMN `amare_points_redeemed` INT UNSIGNED NULL AFTER `amare_discount_mxn`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_pedidos' AND COLUMN_NAME='amare_points_earned'),
  'SELECT 1',
  'ALTER TABLE `rest_pedidos` ADD COLUMN `amare_points_earned` INT UNSIGNED NULL AFTER `amare_points_redeemed`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='amare_wallet_used_mxn'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `amare_wallet_used_mxn` DECIMAL(12,2) NULL AFTER `gift_precio`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='amare_discount_mxn'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `amare_discount_mxn` DECIMAL(12,2) NULL AFTER `amare_wallet_used_mxn`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='amare_points_redeemed'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `amare_points_redeemed` INT UNSIGNED NULL AFTER `amare_discount_mxn`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='amare_points_earned'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `amare_points_earned` INT UNSIGNED NULL AFTER `amare_points_redeemed`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
