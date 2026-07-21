-- Estado operativo de Stripe por pedido. MySQL 5.7 compatible e idempotente.
SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'stripe_payment_status'),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `stripe_payment_status` VARCHAR(30) NULL AFTER `pagado_at`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'stripe_payment_error'),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `stripe_payment_error` VARCHAR(500) NULL AFTER `stripe_payment_status`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'stripe_refunded_cents'),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `stripe_refunded_cents` BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER `stripe_payment_error`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'stripe_disputed_at'),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `stripe_disputed_at` DATETIME NULL AFTER `stripe_refunded_cents`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND INDEX_NAME = 'idx_rest_pedidos_stripe_status'),
    'SELECT 1',
    IF(
        EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = @db_name AND TABLE_NAME = 'rest_pedidos' AND COLUMN_NAME = 'updated_at'),
        'ALTER TABLE `rest_pedidos` ADD INDEX `idx_rest_pedidos_stripe_status` (`stripe_payment_status`, `updated_at`)',
        'ALTER TABLE `rest_pedidos` ADD INDEX `idx_rest_pedidos_stripe_status` (`stripe_payment_status`)'
    )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
