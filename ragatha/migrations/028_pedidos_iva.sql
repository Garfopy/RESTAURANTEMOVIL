-- ── Migración 028: Columna IVA en pedidos ──────────────────────
-- Los precios del catálogo son SIN IVA.
-- iva   = ROUND(subtotal * 0.16, 2)
-- total = subtotal + iva  (+ costo_envio cuando aplica)
--
-- MySQL 5.7 — NO soporta ADD COLUMN IF NOT EXISTS
-- Ejecutar solo si la columna no existe en producción.

ALTER TABLE `pedidos`
  ADD COLUMN `iva` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `subtotal`;
