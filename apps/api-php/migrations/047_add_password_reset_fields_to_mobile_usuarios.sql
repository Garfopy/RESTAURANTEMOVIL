-- Recuperacion de contrasena para usuarios moviles.

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN password_reset_code_hash VARCHAR(255) NULL AFTER password_hash',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'password_reset_code_hash'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN password_reset_expires_at DATETIME NULL AFTER password_reset_code_hash',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'password_reset_expires_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN password_reset_requested_at DATETIME NULL AFTER password_reset_expires_at',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'password_reset_requested_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
