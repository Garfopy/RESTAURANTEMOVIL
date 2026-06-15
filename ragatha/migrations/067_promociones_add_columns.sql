-- ============================================================
-- 067 — CarniHub: Agregar columnas a rest_promociones para Admin API
-- ============================================================
-- Agrega las columnas necesarias que el Admin API espera:
--   usuario_id, code, expires_at, imagen, deep_link
-- ============================================================

ALTER TABLE `rest_promociones`
  ADD COLUMN `usuario_id`  INT UNSIGNED NULL AFTER `restaurante_id`,
  ADD COLUMN `code`        VARCHAR(50)  NULL AFTER `descripcion`,
  ADD COLUMN `expires_at`  DATETIME     NULL AFTER `activo`,
  ADD COLUMN `imagen`      VARCHAR(500) NULL AFTER `expires_at`,
  ADD COLUMN `deep_link`   VARCHAR(500) NULL AFTER `imagen`,
  ADD INDEX  `idx_rest_prom_code`  (`code`),
  ADD UNIQUE INDEX `uq_rest_prom_code` (`code`),
  ADD CONSTRAINT `fk_rest_prom_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios`(`id`) ON DELETE SET NULL;