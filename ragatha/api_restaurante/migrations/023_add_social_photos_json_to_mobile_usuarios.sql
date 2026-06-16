-- Galeria de hasta 6 fotos para perfil social.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'mobile_usuarios'
           AND COLUMN_NAME = 'social_photos_json'
    ),
    'SELECT 1',
    'ALTER TABLE `mobile_usuarios` ADD COLUMN `social_photos_json` TEXT NULL AFTER `foto_url`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `mobile_usuarios`
   SET `social_photos_json` = JSON_ARRAY(`foto_url`)
 WHERE `social_photos_json` IS NULL
   AND `foto_url` IS NOT NULL
   AND `foto_url` <> '';
