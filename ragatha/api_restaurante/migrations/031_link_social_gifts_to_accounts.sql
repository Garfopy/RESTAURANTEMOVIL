-- Vincula regalos sociales con la cuenta eat_in mas reciente.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='pedido_id'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `pedido_id` INT UNSIGNED NULL AFTER `recipient_user_id`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='consumo_id'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `consumo_id` VARCHAR(40) NULL AFTER `pedido_id`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND INDEX_NAME='idx_sgo_pedido'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD KEY `idx_sgo_pedido` (`pedido_id`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND INDEX_NAME='idx_sgo_consumo'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD KEY `idx_sgo_consumo` (`consumo_id`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
