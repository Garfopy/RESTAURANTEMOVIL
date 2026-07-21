-- Separa fondos comprados y promocionales. Compatible con MySQL 5.7.
SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amare_wallets' AND COLUMN_NAME = 'purchased_balance_mxn') = 0,
  'ALTER TABLE amare_wallets ADD COLUMN purchased_balance_mxn DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER balance_mxn',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amare_wallets' AND COLUMN_NAME = 'promotional_balance_mxn') = 0,
  'ALTER TABLE amare_wallets ADD COLUMN promotional_balance_mxn DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER purchased_balance_mxn',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  (SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amare_wallet_transactions' AND COLUMN_NAME = 'funding_type') = 0,
  'ALTER TABLE amare_wallet_transactions ADD COLUMN funding_type VARCHAR(24) NULL AFTER type',
  'SELECT 1'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Antes de Live todo saldo existente se considera promocional, nunca reembolsable.
UPDATE amare_wallets
   SET promotional_balance_mxn = balance_mxn
 WHERE balance_mxn > 0
   AND purchased_balance_mxn = 0
   AND promotional_balance_mxn = 0;
