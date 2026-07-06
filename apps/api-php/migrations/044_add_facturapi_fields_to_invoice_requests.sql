-- Facturacion v2: guardar metadatos del timbrado de FacturAPI.

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE facturacion_solicitudes ADD COLUMN facturapi_invoice_id VARCHAR(80) NULL AFTER receptor_email',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'facturacion_solicitudes'
      AND COLUMN_NAME = 'facturapi_invoice_id'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE facturacion_solicitudes ADD COLUMN facturapi_status VARCHAR(40) NULL AFTER facturapi_invoice_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'facturacion_solicitudes'
      AND COLUMN_NAME = 'facturapi_status'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE facturacion_solicitudes ADD COLUMN facturapi_livemode TINYINT(1) NULL AFTER facturapi_status',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'facturacion_solicitudes'
      AND COLUMN_NAME = 'facturapi_livemode'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE facturacion_solicitudes ADD INDEX idx_facturacion_facturapi_invoice (facturapi_invoice_id)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'facturacion_solicitudes'
      AND INDEX_NAME = 'idx_facturacion_facturapi_invoice'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
