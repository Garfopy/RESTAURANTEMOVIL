-- ============================================================
-- 065 — CarniHub: Columnas de ubicación del comprador en pedidos
-- ============================================================
-- Ejecutar en la BD de CarniHub (carnihub.digital).
-- Permite guardar dirección + coordenadas GPS del restaurante
-- (comprador) cuando el pedido llega vía API externa (CapiRest).
-- La vista de detalle del pedido ya usa estas columnas para
-- mostrar el mapa — al poblarlas, los pedidos de API se ven
-- igual que los pedidos nativos.
-- ============================================================

ALTER TABLE `pedidos`
  ADD COLUMN IF NOT EXISTS `comprador_nombre`    VARCHAR(200)   NULL AFTER `notas`,
  ADD COLUMN IF NOT EXISTS `comprador_direccion` VARCHAR(500)   NULL AFTER `comprador_nombre`,
  ADD COLUMN IF NOT EXISTS `comprador_telefono`  VARCHAR(30)    NULL AFTER `comprador_direccion`,
  ADD COLUMN IF NOT EXISTS `comprador_lat`       DECIMAL(10,7)  NULL AFTER `comprador_telefono`,
  ADD COLUMN IF NOT EXISTS `comprador_lng`       DECIMAL(10,7)  NULL AFTER `comprador_lat`;
