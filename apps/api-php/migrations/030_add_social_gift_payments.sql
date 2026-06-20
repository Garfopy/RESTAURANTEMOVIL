-- Pago verificado de regalos sociales.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

ALTER TABLE `social_gift_orders`
  MODIFY COLUMN `status` ENUM(
    'pendiente_pago','pago_fallido','listo','reclamado','entregado','cancelado'
  ) NOT NULL DEFAULT 'pendiente_pago';

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='stripe_payment_intent_id'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `stripe_payment_intent_id` VARCHAR(255) NULL AFTER `gift_precio`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='moneda'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `moneda` CHAR(3) NOT NULL DEFAULT ''MXN'' AFTER `stripe_payment_intent_id`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='payment_request_key'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `payment_request_key` VARCHAR(64) NULL AFTER `moneda`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND COLUMN_NAME='pagado_at'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD COLUMN `pagado_at` DATETIME NULL AFTER `payment_request_key`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND INDEX_NAME='uq_sgo_payment_request'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD UNIQUE KEY `uq_sgo_payment_request` (`sender_user_id`, `payment_request_key`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='social_gift_orders' AND INDEX_NAME='uq_sgo_payment_intent'),
  'SELECT 1',
  'ALTER TABLE `social_gift_orders` ADD UNIQUE KEY `uq_sgo_payment_intent` (`stripe_payment_intent_id`)'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
