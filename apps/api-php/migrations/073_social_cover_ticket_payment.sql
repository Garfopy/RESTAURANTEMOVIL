-- Pagos sociales de cuenta.
-- Permite registrar rest_tickets.metodo_pago = 'social_cover' sin crear tablas nuevas.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_tickets'
           AND COLUMN_NAME = 'metodo_pago'
           AND COLUMN_TYPE LIKE 'enum(%'
           AND COLUMN_TYPE NOT LIKE '%social_cover%'
    ),
    'ALTER TABLE `rest_tickets`
       MODIFY COLUMN `metodo_pago`
       ENUM(''paypal'',''tarjeta'',''transferencia'',''efectivo'',''social_cover'') NULL DEFAULT NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_tickets'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_tickets' AND INDEX_NAME = 'idx_tickets_visita_estado'
    ),
    'ALTER TABLE `rest_tickets` ADD INDEX `idx_tickets_visita_estado` (`visita_id`, `estado`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos'
    )
    AND NOT EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND INDEX_NAME = 'idx_pedidos_visita_usuario'
    ),
    'ALTER TABLE `rest_pedidos` ADD INDEX `idx_pedidos_visita_usuario` (`visita_id`, `mobile_usuario_id`, `tipo_pedido`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
