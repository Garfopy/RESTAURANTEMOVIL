-- Campos de onboarding para registro con Google y promos de cumpleanos.
-- Compatible con MySQL 5.7 e idempotente.

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN fecha_nacimiento DATE NULL AFTER telefono',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'fecha_nacimiento'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN onboarding_completed_at DATETIME NULL AFTER fecha_nacimiento',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'onboarding_completed_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN terms_accepted_at DATETIME NULL AFTER onboarding_completed_at',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'terms_accepted_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD COLUMN marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0 AFTER terms_accepted_at',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND COLUMN_NAME = 'marketing_opt_in'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_usuarios ADD INDEX idx_mobile_usuarios_fecha_nacimiento (fecha_nacimiento)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_usuarios'
      AND INDEX_NAME = 'idx_mobile_usuarios_fecha_nacimiento'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
