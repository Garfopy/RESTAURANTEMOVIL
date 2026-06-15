-- ============================================================
-- 063 — Token API de CapiRest en CarniHub
-- Ejecutar en la BD de CarniHub (carnihub.digital)
-- Requiere que 050_api_tokens.sql y 051_api_tokens_webhook.sql
-- ya estén aplicados.
-- ============================================================

-- 1. Insertar (o actualizar) el token de CapiRest en api_tokens.
--    · empresa_id  → ID de la empresa distribuidora en CarniHub que atiende a CapiRest
--    · comprador_id → ID del usuario "comprador" vinculado a ese restaurante en CarniHub
--    · token       → SHA2 del raw token que está en carnihub_api_config.api_key de CapiRest
--    · webhook_url → URL del endpoint receptor de webhooks en CapiRest
--    · webhook_secret → Mismo valor que carnihub_api_config.webhook_secret en CapiRest
--
-- AJUSTA los valores de empresa_id y comprador_id antes de ejecutar.
-- ============================================================

INSERT INTO `api_tokens`
  (empresa_id, comprador_id, nombre, token, scopes, webhook_url, webhook_secret, activo)
VALUES (
  1,          -- ← Reemplaza con el empresa_id correcto en CarniHub
  1,          -- ← Reemplaza con el comprador_id correcto en CarniHub
  'CapiRest - La Comalada',
  SHA2('A1KUqu2WkRrzdseI6lD8xb3PJMiZ4F7HCEthgLmOvc0Tpawn', 256),
  '["pedidos:crear","pedidos:leer","productos:leer"]',
  'https://idactivos.digital/restaurante/carnihub/webhook',
  -- webhook_secret: pega aquí el valor de carnihub_api_config.webhook_secret en CapiRest
  'REEMPLAZA_CON_EL_WEBHOOK_SECRET',
  1
)
ON DUPLICATE KEY UPDATE
  nombre         = VALUES(nombre),
  scopes         = VALUES(scopes),
  webhook_url    = VALUES(webhook_url),
  webhook_secret = VALUES(webhook_secret),
  activo         = 1;

-- ============================================================
-- 2. Verificar que el token quedó correcto
-- ============================================================
SELECT id, empresa_id, comprador_id, nombre,
       LEFT(token,16) AS token_prefix,
       scopes, webhook_url,
       LEFT(webhook_secret,8) AS secret_prefix,
       activo, ultimo_uso
  FROM api_tokens
 WHERE token = SHA2('A1KUqu2WkRrzdseI6lD8xb3PJMiZ4F7HCEthgLmOvc0Tpawn', 256);

-- ============================================================
-- 3. En CapiRest: actualizar carnihub_url al dominio base (sin /api/v1)
--    Ejecutar en la BD de CapiRest (idactivos.digital)
-- ============================================================
-- UPDATE carnihub_api_config
--    SET carnihub_url = 'https://carnihub.digital'
--  WHERE activo = 1;

-- Verificar:
-- SELECT restaurante_id, carnihub_url, api_key, webhook_url, webhook_secret
--   FROM carnihub_api_config
--  WHERE activo = 1;
