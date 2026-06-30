-- Guardas para cobertura de cuentas sociales y busqueda por consumo cubierto.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'social_account_covers'
    )
    AND NOT EXISTS(
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'social_account_covers'
           AND INDEX_NAME = 'idx_social_account_cover_consumo_status'
    ),
    'ALTER TABLE `social_account_covers`
        ADD INDEX `idx_social_account_cover_consumo_status`
        (`restaurante_id`, `covered_user_id`, `covered_consumo_id`, `status`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
