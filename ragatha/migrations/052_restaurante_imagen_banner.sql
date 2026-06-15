-- Compatibilidad MySQL 5.7: agrega imagen_banner solo si no existe
SET @col_exists := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_restaurantes'
    AND COLUMN_NAME = 'imagen_banner'
);

SET @ddl := IF(
  @col_exists = 0,
  'ALTER TABLE rest_restaurantes ADD COLUMN imagen_banner VARCHAR(255) NULL AFTER logo',
  'SELECT 1'
);

PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;