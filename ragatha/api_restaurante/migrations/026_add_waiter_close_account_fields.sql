-- Cierre operativo de cuentas por mesero.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'metodo_pago'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `metodo_pago` VARCHAR(30) NULL AFTER `stripe_payment_intent_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'pagado_at'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `pagado_at` DATETIME NULL AFTER `metodo_pago`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'cerrado_por_mesero_usuario_id'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `cerrado_por_mesero_usuario_id` INT(10) UNSIGNED NULL AFTER `pagado_at`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'cerrado_por_mesero_nombre'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `cerrado_por_mesero_nombre` VARCHAR(120) NULL AFTER `cerrado_por_mesero_usuario_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'cerrado_at'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `cerrado_at` DATETIME NULL AFTER `cerrado_por_mesero_nombre`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND INDEX_NAME = 'idx_rest_pedidos_cierre_mesero'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD INDEX `idx_rest_pedidos_cierre_mesero` (`cerrado_por_mesero_usuario_id`, `cerrado_at`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
