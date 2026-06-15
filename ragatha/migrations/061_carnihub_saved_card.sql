-- ============================================================
-- 061 — CarniHub: Tarjeta guardada para cobro automático off-session
-- Agrega columnas para almacenar el Stripe Customer y PaymentMethod
-- del restaurante, permitiendo cobros automáticos sin modal.
-- IDEMPOTENTE — seguro de re-ejecutar.
-- ============================================================

SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name   = 'carnihub_api_config'
    AND column_name  = 'stripe_customer_id'
);
SET @sql = IF(@col_exists = 0,
  "ALTER TABLE carnihub_api_config
     ADD COLUMN stripe_customer_id       VARCHAR(64) NULL
       COMMENT 'Stripe Customer ID (cus_...) del restaurante',
     ADD COLUMN stripe_payment_method_id VARCHAR(64) NULL
       COMMENT 'Stripe PaymentMethod ID guardado para cobros off-session (pm_...)',
     ADD COLUMN stripe_card_last4        VARCHAR(4)  NULL
       COMMENT 'Últimos 4 dígitos de la tarjeta guardada (para mostrar en UI)'",
  "SELECT 'cols tarjeta CarniHub ya existen' AS info"
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
