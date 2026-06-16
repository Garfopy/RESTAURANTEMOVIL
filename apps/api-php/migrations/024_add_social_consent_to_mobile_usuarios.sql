-- Consentimiento versionado para activar el perfil social.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'mobile_usuarios'
           AND COLUMN_NAME = 'social_consent_accepted_at'
    ),
    'SELECT 1',
    'ALTER TABLE `mobile_usuarios` ADD COLUMN `social_consent_accepted_at` DATETIME NULL AFTER `social_updated_at`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'mobile_usuarios'
           AND COLUMN_NAME = 'social_consent_version'
    ),
    'SELECT 1',
    'ALTER TABLE `mobile_usuarios` ADD COLUMN `social_consent_version` VARCHAR(40) NULL AFTER `social_consent_accepted_at`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
