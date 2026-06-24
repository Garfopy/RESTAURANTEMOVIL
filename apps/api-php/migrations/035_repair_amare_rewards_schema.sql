-- Reparacion de esquema para Recompensas Amare V1.
-- Usar si las tablas se crearon incompletas antes de aplicar 034 completo.

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

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallets' AND COLUMN_NAME='usuario_id'), 'SELECT 1', 'ALTER TABLE `amare_wallets` ADD COLUMN `usuario_id` INT UNSIGNED NULL AFTER `user_id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='wallet_id'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `wallet_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='user_id'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `user_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `wallet_id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='usuario_id'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `usuario_id` INT UNSIGNED NULL AFTER `user_id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='type'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `type` VARCHAR(40) NOT NULL DEFAULT ''wallet_payment'' AFTER `usuario_id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='context'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `context` VARCHAR(30) NULL AFTER `type`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='reference_type'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `reference_type` VARCHAR(40) NULL AFTER `context`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='reference_id'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `reference_id` INT UNSIGNED NULL AFTER `reference_type`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='amount_mxn'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `amount_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `reference_id`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='points_delta'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `points_delta` INT NOT NULL DEFAULT 0 AFTER `amount_mxn`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='balance_after_mxn'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `balance_after_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `points_delta`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='points_after'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `points_after` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `balance_after_mxn`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='description'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `description` VARCHAR(255) NULL AFTER `points_after`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='metadata_json'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `metadata_json` TEXT NULL AFTER `description`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='created_at'), 'SELECT 1', 'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `metadata_json`');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
