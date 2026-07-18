-- Reservaciones desde app movil.
-- La DB actual ya tiene rest_reservaciones y la columna origen
-- enum('restaurante','comensal'). Este archivo solo agrega indices
-- opcionales para acelerar la consulta de disponibilidad y el guardado.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_reservaciones'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_reservaciones' AND INDEX_NAME = 'idx_reservaciones_mesa_fecha_hora'
    ),
    'ALTER TABLE `rest_reservaciones` ADD INDEX `idx_reservaciones_mesa_fecha_hora` (`restaurante_id`, `mesa_id`, `fecha`, `hora`, `estado`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_reservaciones'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_reservaciones' AND INDEX_NAME = 'idx_reservaciones_rest_fecha_hora'
    ),
    'ALTER TABLE `rest_reservaciones` ADD INDEX `idx_reservaciones_rest_fecha_hora` (`restaurante_id`, `fecha`, `hora`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
