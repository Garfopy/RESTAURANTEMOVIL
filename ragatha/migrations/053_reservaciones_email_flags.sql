-- ============================================================
-- 053 — rest_reservaciones: flags de email enviado
-- ============================================================
-- Necesarios para evitar reenviar el correo de confirmación
-- al comensal y para que el cron de recordatorios (24h antes)
-- no duplique avisos.
-- ============================================================

ALTER TABLE `rest_reservaciones`
  ADD COLUMN IF NOT EXISTS `confirmacion_enviada` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `origen`,
  ADD COLUMN IF NOT EXISTS `recordatorio_enviado` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `confirmacion_enviada`;

-- Índice para el cron (busca reservas confirmadas mañana sin recordatorio).
ALTER TABLE `rest_reservaciones`
  ADD INDEX IF NOT EXISTS `idx_recordatorio` (`fecha`, `estado`, `recordatorio_enviado`);
