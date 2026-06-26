-- Permite usuarios operativos con rol hostess en la app mobile.
-- Mantiene compatibilidad con roles existentes: user, admin y mesero.

SET @db_name := DATABASE();

SET @has_rol := (
    SELECT COUNT(*)
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db_name
       AND TABLE_NAME = 'mobile_usuarios'
       AND COLUMN_NAME = 'rol'
);

SET @sql := IF(
    @has_rol = 0,
    'ALTER TABLE `mobile_usuarios`
        ADD COLUMN `rol` ENUM(''user'',''admin'',''mesero'',''hostess'')
        COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''user''
        AFTER `email`',
    'ALTER TABLE `mobile_usuarios`
        MODIFY COLUMN `rol` ENUM(''user'',''admin'',''mesero'',''hostess'')
        COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''user'''
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
