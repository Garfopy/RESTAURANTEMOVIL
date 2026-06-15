-- Agrega la columna mesa para el modo social (MySQL 5.7 compatible)

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'mobile_usuarios' AND COLUMN_NAME = 'mesa'
    ),
    'SELECT 1',
    'ALTER TABLE mobile_usuarios ADD COLUMN mesa VARCHAR(50) NULL AFTER current_restaurante_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
