-- ============================================================
-- 049 — rest_reservaciones: columnas añadidas en [045] y [046]
-- ============================================================
-- Ejecutar si la tabla fue creada antes de las migraciones
-- 045 (mesero_id, updated_at) y 046 (origen).
-- Usa IF NOT EXISTS via INFORMATION_SCHEMA para ser idempotente.
-- ============================================================

-- ── mesero_id (asignación de mesero a la reservación) ─────────
ALTER TABLE `rest_reservaciones`
  ADD COLUMN IF NOT EXISTS `mesero_id` INT UNSIGNED NULL
    AFTER `comensal_id`;

-- ── updated_at (timestamp de última modificación) ─────────────
ALTER TABLE `rest_reservaciones`
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NULL DEFAULT NULL
    ON UPDATE CURRENT_TIMESTAMP
    AFTER `created_at`;

-- ── origen (distingue si vino del restaurante o vía QR) ───────
ALTER TABLE `rest_reservaciones`
  ADD COLUMN IF NOT EXISTS `origen`
    ENUM('restaurante','comensal') NOT NULL DEFAULT 'restaurante'
    AFTER `estado`;

-- ── FK mesero_id → usuarios ───────────────────────────────────
-- Solo aplica si la BD usa la tabla usuarios de este esquema.
-- Comenta esta línea si genera error por referencias cruzadas.
ALTER TABLE `rest_reservaciones`
  ADD CONSTRAINT IF NOT EXISTS `fk_rres_mesero`
    FOREIGN KEY (`mesero_id`) REFERENCES `usuarios`(`id`)
    ON DELETE SET NULL;
