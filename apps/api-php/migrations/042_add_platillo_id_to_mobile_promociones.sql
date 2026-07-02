-- Migration 042: Asociar promociones a un platillo especifico.
-- Esto permite aplicar descuentos solo sobre los productos elegibles.

ALTER TABLE mobile_promociones
  ADD COLUMN platillo_id INT NULL AFTER usuario_id,
  ADD INDEX idx_platillo_id (platillo_id);

-- Backfill opcional para promos existentes que usan /product/:id en deep_link.
UPDATE mobile_promociones
   SET platillo_id = CAST(SUBSTRING_INDEX(deep_link, '/product/', -1) AS UNSIGNED)
 WHERE platillo_id IS NULL
   AND deep_link REGEXP '/product/[0-9]+';

-- Promo actual de Chile Relleno de Queso 1 Pza. (platillo_id 28).
UPDATE mobile_promociones
   SET platillo_id = 28
 WHERE code = 'AMARE-66-0701';
