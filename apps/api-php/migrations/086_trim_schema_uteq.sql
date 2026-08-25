-- ============================================
-- Migración 086: Recorte de esquema para UTEQ Cafetería
-- Elimina tablas y columnas de mesas/QR/dine-in, mesero,
-- anfitrión, modo social y selección de sucursal.
-- ============================================
--
-- IMPORTANTE:
-- - Corre esto sobre una COPIA nueva del esquema de referencia,
--   NO sobre una base en producción con datos que te importen.
-- - Revisa nombres de tabla/columna contra tu copia real antes
--   de ejecutar — vienen leídos directo del dump que se usó como
--   referencia y pueden no coincidir 100% si tu esquema difiere.
-- - Ver docs/PLAN_RECORTE_UTEQ.md para el contexto completo.
--
-- Lo que NO se toca: rest_pedidos.estado (pendiente/en_preparacion/
-- listo/entregado) sigue igual, es lo que alimenta el KDS de cocina.

-- PASO 1: desactivar temporalmente el chequeo de FK para no pelear
-- con el orden de borrado entre tablas relacionadas.
SET FOREIGN_KEY_CHECKS = 0;

-- PASO 2: eliminar tablas de mesas, QR, dine-in, mesero, anfitrión,
-- reservaciones y sucursales.
DROP TABLE IF EXISTS `sucursales`;
DROP TABLE IF EXISTS `rest_mesas`;
DROP TABLE IF EXISTS `rest_zonas`;
DROP TABLE IF EXISTS `rest_mesero_turno`;
DROP TABLE IF EXISTS `rest_alertas`;
DROP TABLE IF EXISTS `rest_cuenta_divisiones`;
DROP TABLE IF EXISTS `rest_cuenta_division_cuentas`;
DROP TABLE IF EXISTS `rest_cuenta_division_items`;
DROP TABLE IF EXISTS `rest_staff`;
DROP TABLE IF EXISTS `rest_staff_restaurantes`;
DROP TABLE IF EXISTS `rest_tickets`;
DROP TABLE IF EXISTS `rest_visitas`;
DROP TABLE IF EXISTS `rest_reservaciones`;
DROP TABLE IF EXISTS `rest_validaciones_salida_programador`;

-- PASO 3: eliminar tablas del modo social.
DROP TABLE IF EXISTS `social_account_covers`;
DROP TABLE IF EXISTS `social_account_notifications`;
DROP TABLE IF EXISTS `social_blocks`;
DROP TABLE IF EXISTS `social_gift_account_products`;
DROP TABLE IF EXISTS `social_gift_orders`;
DROP TABLE IF EXISTS `social_gift_products`;
DROP TABLE IF EXISTS `social_likes`;
DROP TABLE IF EXISTS `social_photo_moderation`;
DROP TABLE IF EXISTS `social_reports`;

SET FOREIGN_KEY_CHECKS = 1;

-- PASO 4: columnas muertas en tablas que SÍ se quedan.
-- Es opcional y sin prisa (no rompe nada dejarlas), pero limpia el
-- esquema para la base nueva de UTEQ.
ALTER TABLE `rest_restaurantes`
  DROP COLUMN `mesas_habilitadas`,
  DROP COLUMN `reservas_habilitadas`,
  DROP COLUMN `portero_habilitado`,
  DROP COLUMN `requiere_login_comensal`,
  DROP COLUMN `sucursal_id`;

ALTER TABLE `rest_pedidos`
  DROP COLUMN `mesa_id`,
  DROP COLUMN `visita_id`,
  DROP COLUMN `consumo_id`,
  DROP COLUMN `cuenta_abierta`,
  DROP COLUMN `mesero_id`,
  DROP COLUMN `reclamado_por`,
  DROP COLUMN `reclamado_at`,
  DROP COLUMN `mesero_usuario_id`,
  DROP COLUMN `mesero_nombre`,
  DROP COLUMN `salida_token`,
  DROP COLUMN `salida_qr_generado_at`,
  DROP COLUMN `salida_validado_at`,
  DROP COLUMN `salida_validado_por`,
  DROP COLUMN `cerrado_por_mesero_usuario_id`,
  DROP COLUMN `cerrado_por_mesero_nombre`,
  DROP COLUMN `cerrado_at`;

ALTER TABLE `mobile_usuarios`
  DROP COLUMN `is_social_active`,
  DROP COLUMN `current_restaurante_id`,
  DROP COLUMN `mesa`,
  DROP COLUMN `social_updated_at`,
  DROP COLUMN `social_consent_accepted_at`,
  DROP COLUMN `social_consent_version`,
  DROP COLUMN `edad`,
  DROP COLUMN `sexualidad`,
  DROP COLUMN `genero`,
  DROP COLUMN `descripcion`,
  DROP COLUMN `intereses`,
  DROP COLUMN `que_busca`,
  DROP COLUMN `redes_sociales`;

-- PASO 5 (verificación, opcional):
-- SHOW TABLES; -- confirma que las tablas del PASO 2 y 3 ya no existen
-- DESCRIBE rest_pedidos; -- confirma que las columnas del PASO 4 ya no existen
