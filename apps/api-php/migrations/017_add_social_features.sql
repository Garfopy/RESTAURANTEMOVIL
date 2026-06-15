-- Campos requeridos por el modulo social del movil

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'edad'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN edad INT NULL AFTER foto_url'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'sexualidad'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN sexualidad VARCHAR(100) NULL AFTER edad'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'genero'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN genero VARCHAR(100) NULL AFTER sexualidad'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'descripcion'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN descripcion TEXT NULL AFTER genero'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'intereses'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN intereses TEXT NULL AFTER descripcion'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'que_busca'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN que_busca VARCHAR(255) NULL AFTER intereses'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'redes_sociales'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN redes_sociales TEXT NULL AFTER que_busca'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'is_social_active'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN is_social_active TINYINT(1) NOT NULL DEFAULT 0 AFTER redes_sociales'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'current_restaurante_id'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN current_restaurante_id INT NULL AFTER is_social_active'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'social_updated_at'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN social_updated_at DATETIME NULL AFTER current_restaurante_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND INDEX_NAME = 'idx_social_restaurant'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD INDEX idx_social_restaurant (is_social_active, current_restaurante_id)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
