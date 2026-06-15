-- ============================================================
-- 060_pagos_config.sql
-- Configuración de métodos de pago para comensales,
-- cobro automático a CarniHub, y columnas de seguimiento
-- de pago en pedidos sugeridos.
-- IDEMPOTENTE — seguro de re-ejecutar.
-- ============================================================

-- ── 1) GLOBAL_SETTINGS — claves de pago ─────────────────────
INSERT INTO global_settings (clave, valor, tipo, grupo, etiqueta) VALUES
  ('stripe_public_key',         '',                                         'password', 'pagos', 'Stripe Publishable Key (pk_live_... o pk_test_...)'),
  ('stripe_secret_key',         '',                                         'password', 'pagos', 'Stripe Secret Key (sk_live_... o sk_test_...)'),
  ('metodos_pago_habilitados',  '["efectivo","tarjeta","transferencia","paypal"]', 'json', 'pagos', 'Métodos de pago habilitados para comensales'),
  ('notif_email_pago',          '0',                                        'boolean',  'pagos', 'Enviar email al restaurante cuando se pague'),
  ('notif_email_pago_destino',  '',                                         'text',     'pagos', 'Email destino para notificaciones de pago')
ON DUPLICATE KEY UPDATE etiqueta = VALUES(etiqueta);

-- ── 2) CARNIHUB_API_CONFIG — método de pago B2B ─────────────
-- Agrega columnas solo si no existen (idempotente via stored proc)
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'carnihub_api_config'
    AND column_name  = 'metodo_pago'
);
SET @sql = IF(@col_exists = 0,
  "ALTER TABLE carnihub_api_config
     ADD COLUMN metodo_pago ENUM('stripe','paypal','transferencia') NOT NULL DEFAULT 'transferencia'
       COMMENT 'Método con el que el restaurante paga a CarniHub',
     ADD COLUMN instrucciones_transferencia TEXT NULL
       COMMENT 'Datos bancarios de CarniHub para mostrar cuando método=transferencia'",
  "SELECT 'carnihub_api_config cols ya existen' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 3) REST_PEDIDOS_SUGERIDOS — columnas de pago ────────────
SET @col_monto = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'rest_pedidos_sugeridos'
    AND column_name  = 'monto_total'
);
SET @sql2 = IF(@col_monto = 0,
  "ALTER TABLE rest_pedidos_sugeridos
     ADD COLUMN monto_total          DECIMAL(10,2) NULL
       COMMENT 'Total calculado del pedido al enviar a CarniHub',
     ADD COLUMN metodo_pago          VARCHAR(30)   NULL
       COMMENT 'Método de pago usado para este pedido',
     ADD COLUMN estado_pago          ENUM('pendiente','procesando','pagado','fallido') NOT NULL DEFAULT 'pendiente'
       COMMENT 'Estado del pago al proveedor',
     ADD COLUMN pago_referencia      VARCHAR(255)  NULL
       COMMENT 'Stripe PaymentIntent ID / PayPal order ID / folio transferencia',
     ADD COLUMN pagado_at            DATETIME      NULL
       COMMENT 'Fecha y hora en que se confirmó el pago'",
  "SELECT 'rest_pedidos_sugeridos cols ya existen' AS info"
);
PREPARE stmt2 FROM @sql2; EXECUTE stmt2; DEALLOCATE PREPARE stmt2;

-- ── 4) Estado/sync CarniHub (migration 058 — por si no está aplicada) ────────
SET @col_estado_ch = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'rest_pedidos_sugeridos'
    AND column_name  = 'estado_carnihub'
);
SET @sql3 = IF(@col_estado_ch = 0,
  "ALTER TABLE rest_pedidos_sugeridos
     ADD COLUMN estado_carnihub      VARCHAR(40) NULL
       COMMENT 'Estado del pedido según CarniHub API',
     ADD COLUMN ultima_sync_carnihub DATETIME    NULL
       COMMENT 'Última vez que se consultó el estado remoto'",
  "SELECT 'cols estado_carnihub ya existen' AS info"
);
PREPARE stmt3 FROM @sql3; EXECUTE stmt3; DEALLOCATE PREPARE stmt3;

-- ── Fin ─────────────────────────────────────────────────────
