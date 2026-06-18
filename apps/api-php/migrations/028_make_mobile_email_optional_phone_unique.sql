-- 028: Registro movil con telefono obligatorio y email opcional

UPDATE `mobile_usuarios`
   SET `email` = NULL
 WHERE `email` = '';

UPDATE `mobile_usuarios`
   SET `telefono` = NULL
 WHERE `telefono` IS NOT NULL
   AND TRIM(`telefono`) = '';

ALTER TABLE `mobile_usuarios`
  MODIFY COLUMN `email` VARCHAR(200) NULL;

SET @db_name = DATABASE();

SET @has_phone_unique := (
  SELECT COUNT(*)
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = @db_name
     AND TABLE_NAME = 'mobile_usuarios'
     AND INDEX_NAME = 'uq_mobile_usuarios_telefono'
);

SET @sql := IF(
  @has_phone_unique = 0,
  'ALTER TABLE `mobile_usuarios` ADD UNIQUE KEY `uq_mobile_usuarios_telefono` (`telefono`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
