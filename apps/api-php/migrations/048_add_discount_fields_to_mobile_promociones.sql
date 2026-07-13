-- Migration 048: Campos estructurados para descuentos de promociones moviles.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='mobile_promociones' AND COLUMN_NAME='discount_type'),
  'SELECT 1',
  'ALTER TABLE `mobile_promociones` ADD COLUMN `discount_type` VARCHAR(20) NULL AFTER `code`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='mobile_promociones' AND COLUMN_NAME='discount_value'),
  'SELECT 1',
  'ALTER TABLE `mobile_promociones` ADD COLUMN `discount_value` DECIMAL(10,2) NULL AFTER `discount_type`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Promo actual de Chile Relleno de Queso 1 Pza.: precio promocional a $66.
UPDATE mobile_promociones
   SET discount_type = COALESCE(discount_type, 'fixed_price'),
       discount_value = COALESCE(discount_value, 66.00),
       platillo_id = COALESCE(platillo_id, 28)
 WHERE code = 'AMARE-66-0701';
