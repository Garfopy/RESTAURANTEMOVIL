-- ============================================================
-- 062 — CarniHub API: Agregar webhook_url a carnihub_api_config
--
-- La URL a la que CarniHub enviará notificaciones (POST) cuando
-- cambia el estado de un pedido B2B. El restaurante debe copiar
-- esta URL en la configuración del token en CarniHub.
--
-- Ejemplo: https://mirestaurante.mx/carnihub/webhook
--
-- IDEMPOTENTE — seguro de re-ejecutar.
-- ============================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'carnihub_api_config'
    AND column_name  = 'webhook_url'
);

SET @sql = IF(@col_exists = 0,
  "ALTER TABLE carnihub_api_config
     ADD COLUMN webhook_url VARCHAR(255) NULL
       COMMENT 'URL que CapiRest expone para recibir webhooks de CarniHub (POST /carnihub/webhook)'
       AFTER webhook_secret",
  "SELECT 'webhook_url ya existe' AS info"
);

PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
