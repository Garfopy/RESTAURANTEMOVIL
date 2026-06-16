-- Cuenta abierta agrupada para pedidos eat_in/dine_in en la BD actual.
-- Adaptada a amareres_amare_club.sql:
-- rest_pedidos ya tiene mesa_id, visita_id, mobile_usuario_id,
-- stripe_payment_intent_id y actualizado_at, pero no tiene campos de cuenta abierta.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'consumo_id'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `consumo_id` VARCHAR(40) NULL AFTER `visita_id`'
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
           AND COLUMN_NAME = 'cuenta_abierta'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `cuenta_abierta` TINYINT(1) NOT NULL DEFAULT 0 AFTER `consumo_id`'
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
           AND COLUMN_NAME = 'salida_token'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `salida_token` VARCHAR(96) NULL AFTER `stripe_payment_intent_id`'
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
           AND COLUMN_NAME = 'salida_qr_generado_at'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `salida_qr_generado_at` DATETIME NULL AFTER `salida_token`'
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
           AND COLUMN_NAME = 'salida_validado_at'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `salida_validado_at` DATETIME NULL AFTER `salida_qr_generado_at`'
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
           AND COLUMN_NAME = 'salida_validado_por'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `salida_validado_por` INT(10) UNSIGNED NULL AFTER `salida_validado_at`'
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
           AND INDEX_NAME = 'idx_rest_pedidos_consumo'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD INDEX `idx_rest_pedidos_consumo` (`consumo_id`)'
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
           AND INDEX_NAME = 'idx_rest_pedidos_cuenta_abierta'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD INDEX `idx_rest_pedidos_cuenta_abierta` (`restaurante_id`, `mesa_id`, `mobile_usuario_id`, `tipo_pedido`, `cuenta_abierta`)'
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
           AND INDEX_NAME = 'idx_rest_pedidos_salida_token'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD UNIQUE KEY `idx_rest_pedidos_salida_token` (`salida_token`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Backfill conservador para pedidos dine_in/eat_in aun no entregados.
-- No toca pedidos ya cerrados ni cancelados.
UPDATE `rest_pedidos`
   SET `consumo_id` = CONCAT('CON-', DATE_FORMAT(`created_at`, '%Y%m%d'), '-', LPAD(`id`, 10, '0')),
       `cuenta_abierta` = 1
 WHERE `consumo_id` IS NULL
   AND `tipo_pedido` IN ('dine_in', 'eat_in')
   AND `estado` NOT IN ('entregado', 'cancelado');
