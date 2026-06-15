-- Cuenta abierta para comer aqui y pase QR de salida.
-- MySQL 5.7 compatible.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'cuenta_abierta'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD COLUMN cuenta_abierta TINYINT(1) NOT NULL DEFAULT 0 AFTER tipo_pedido'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'mesa_id'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD COLUMN mesa_id INT NULL AFTER cuenta_abierta'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'salida_token'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD COLUMN salida_token VARCHAR(128) NULL AFTER payment_intent_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'salida_qr_generado_at'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD COLUMN salida_qr_generado_at DATETIME NULL AFTER salida_token'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'salida_validado_at'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD COLUMN salida_validado_at DATETIME NULL AFTER salida_qr_generado_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'salida_validado_por'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD COLUMN salida_validado_por INT NULL AFTER salida_validado_at'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1 FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND INDEX_NAME = 'idx_rest_pedidos_salida_token'
    ),
    'SELECT 1',
    'ALTER TABLE rest_pedidos ADD INDEX idx_rest_pedidos_salida_token (salida_token)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
