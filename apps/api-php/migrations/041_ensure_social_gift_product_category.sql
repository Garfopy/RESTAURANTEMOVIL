-- Asegura categoria en el catalogo de regalos sociales.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @gift_products_table = (
  SELECT TABLE_NAME
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA=@db_name
     AND TABLE_NAME IN ('social_gift_products', 'social_gifts_products', 'gift_products')
   ORDER BY FIELD(TABLE_NAME, 'social_gift_products', 'social_gifts_products', 'gift_products')
   LIMIT 1
);

SET @sql = IF(
  @gift_products_table IS NULL,
  'SELECT 1',
  IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME=@gift_products_table AND COLUMN_NAME='categoria'),
    'SELECT 1',
    CONCAT('ALTER TABLE `', @gift_products_table, '` ADD COLUMN `categoria` VARCHAR(100) NULL AFTER `es_regalo`')
  )
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
