-- Recompensas Amare V2: saldo real, recargas Stripe y canje de puntos.

SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND COLUMN_NAME='external_reference'),
  'SELECT 1',
  'ALTER TABLE `amare_wallet_transactions` ADD COLUMN `external_reference` VARCHAR(120) NULL AFTER `metadata_json`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='amare_wallet_transactions' AND INDEX_NAME='idx_wallet_transactions_external_reference'),
  'SELECT 1',
  'ALTER TABLE `amare_wallet_transactions` ADD INDEX `idx_wallet_transactions_external_reference` (`external_reference`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE `amare_wallets`
   SET `simulated_balance` = 0
 WHERE `simulated_balance` IS NULL;
