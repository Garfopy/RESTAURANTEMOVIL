-- ============================================
-- Migración 011: Agregar columna 'extras_json' a rest_pedido_items
-- Guarda los modificadores/extras como JSON estructurado,
-- independiente del campo 'notas' (notas del cliente)
-- ============================================

ALTER TABLE rest_pedido_items
ADD COLUMN extras_json TEXT DEFAULT NULL AFTER notas;