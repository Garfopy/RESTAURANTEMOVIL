-- Migración 025: Credenciales PayPal separadas por modo (sandbox / live)
-- y columnas de plan IDs de PayPal por modo en planes_saas

-- ── 1. Columnas nuevas en planes_saas ───────────────────────────────────────
ALTER TABLE planes_saas
    ADD COLUMN IF NOT EXISTS paypal_plan_id_live       VARCHAR(50) NULL AFTER paypal_plan_id,
    ADD COLUMN IF NOT EXISTS paypal_plan_id_anual_live VARCHAR(50) NULL AFTER paypal_plan_id_anual;

-- ── 2. Nuevas claves de configuración en global_settings ────────────────────
INSERT INTO global_settings (clave, valor, tipo, grupo, etiqueta) VALUES
    ('paypal_client_id_sandbox', '', 'text',     'pagos', 'PayPal Client ID — Sandbox'),
    ('paypal_secret_sandbox',    '', 'password',  'pagos', 'PayPal Secret — Sandbox'),
    ('paypal_client_id_live',    '', 'text',     'pagos', 'PayPal Client ID — Live'),
    ('paypal_secret_live',       '', 'password',  'pagos', 'PayPal Secret — Live')
ON DUPLICATE KEY UPDATE clave = clave;

-- ── 3. Migrar valores existentes al campo sandbox (valor por defecto) ────────
UPDATE global_settings SET valor = (SELECT valor FROM (SELECT valor FROM global_settings WHERE clave = 'paypal_client_id') AS t)
WHERE clave = 'paypal_client_id_sandbox' AND valor = '';

UPDATE global_settings SET valor = (SELECT valor FROM (SELECT valor FROM global_settings WHERE clave = 'paypal_secret') AS t)
WHERE clave = 'paypal_secret_sandbox' AND valor = '';

-- ── 4. Migrar paypal_product_id a campo específico de sandbox ────────────────
INSERT INTO global_settings (clave, valor, tipo, grupo, etiqueta) VALUES
    ('paypal_product_id_sandbox', '', 'text', 'pagos', 'PayPal Product ID — Sandbox'),
    ('paypal_product_id_live',    '', 'text', 'pagos', 'PayPal Product ID — Live')
ON DUPLICATE KEY UPDATE clave = clave;

UPDATE global_settings SET valor = (SELECT valor FROM (SELECT valor FROM global_settings WHERE clave = 'paypal_product_id') AS t)
WHERE clave = 'paypal_product_id_sandbox' AND valor = '';
