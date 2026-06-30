-- Turnos diarios de meseros por zona.
-- La web usa esta tabla para asignar zonas del dia; la API mobile la usa
-- para filtrar las mesas visibles y operables por cada mesero.

CREATE TABLE IF NOT EXISTS `rest_mesero_turno` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `zona_id` INT UNSIGNED NOT NULL,
  `turno_fecha` DATE NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rest_mesero_turno_dia` (`restaurante_id`, `usuario_id`, `zona_id`, `turno_fecha`),
  KEY `idx_rest_mesero_turno_lookup` (`restaurante_id`, `usuario_id`, `turno_fecha`, `activo`),
  KEY `idx_rest_mesero_turno_zona` (`zona_id`, `turno_fecha`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db_name := DATABASE();

SET @sql := IF(
    EXISTS(
        SELECT 1
          FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesero_turno'
    )
    AND NOT EXISTS(
        SELECT 1
          FROM INFORMATION_SCHEMA.STATISTICS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesero_turno'
           AND INDEX_NAME = 'idx_rest_mesero_turno_lookup'
    ),
    'ALTER TABLE `rest_mesero_turno`
        ADD INDEX `idx_rest_mesero_turno_lookup` (`restaurante_id`, `usuario_id`, `turno_fecha`, `activo`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
