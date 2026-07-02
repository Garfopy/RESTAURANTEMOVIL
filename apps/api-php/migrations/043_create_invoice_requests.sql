-- Facturacion v1: capturar solicitudes de factura sin timbrado automatico.

CREATE TABLE IF NOT EXISTS mobile_datos_fiscales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    rfc VARCHAR(13) NOT NULL,
    nombre_fiscal VARCHAR(255) NOT NULL,
    regimen_fiscal VARCHAR(10) NOT NULL,
    codigo_postal VARCHAR(10) NOT NULL,
    uso_cfdi VARCHAR(10) NOT NULL,
    email VARCHAR(190) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_mobile_datos_fiscales_usuario (usuario_id),
    INDEX idx_mobile_datos_fiscales_usuario (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS facturacion_solicitudes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    restaurante_id INT NOT NULL,
    pedido_id INT NULL,
    consumo_id VARCHAR(64) NULL,
    mesa_id INT NULL,
    division_id INT NULL,
    division_cuenta_id INT NULL,
    mobile_usuario_id INT NULL,
    solicitado_por_usuario_id INT NULL,
    origen VARCHAR(20) NOT NULL DEFAULT 'cliente',
    scope VARCHAR(30) NOT NULL DEFAULT 'pedido',
    monto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    metodo_pago VARCHAR(40) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'pendiente',
    receptor_rfc VARCHAR(13) NOT NULL,
    receptor_nombre VARCHAR(255) NOT NULL,
    receptor_regimen_fiscal VARCHAR(10) NOT NULL,
    receptor_codigo_postal VARCHAR(10) NOT NULL,
    uso_cfdi VARCHAR(10) NOT NULL,
    receptor_email VARCHAR(190) NOT NULL,
    cfdi_uuid VARCHAR(80) NULL,
    pdf_url TEXT NULL,
    xml_url TEXT NULL,
    notas TEXT NULL,
    facturada_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_facturacion_restaurante_estado (restaurante_id, estado),
    INDEX idx_facturacion_pedido (pedido_id),
    INDEX idx_facturacion_division_cuenta (division_cuenta_id),
    INDEX idx_facturacion_mobile_usuario (mobile_usuario_id),
    INDEX idx_facturacion_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE rest_configuracion ADD COLUMN facturacion_habilitada TINYINT(1) NOT NULL DEFAULT 0 AFTER activo',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'rest_configuracion'
      AND COLUMN_NAME = 'facturacion_habilitada'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE rest_configuracion ADD COLUMN facturacion_emisor_json JSON NULL AFTER facturacion_habilitada',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'rest_configuracion'
      AND COLUMN_NAME = 'facturacion_emisor_json'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE rest_configuracion ADD COLUMN facturacion_email_notificacion VARCHAR(190) NULL AFTER facturacion_emisor_json',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'rest_configuracion'
      AND COLUMN_NAME = 'facturacion_email_notificacion'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
