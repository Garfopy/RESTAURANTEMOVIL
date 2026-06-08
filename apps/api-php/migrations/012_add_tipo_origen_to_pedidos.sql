-- ============================================
-- Migración 012: Agregar columna 'tipo_origen' a rest_pedidos
-- Permite filtrar pedidos del menú vs pedidos de la tienda
-- ============================================

ALTER TABLE rest_pedidos
ADD COLUMN tipo_origen VARCHAR(20) NOT NULL DEFAULT 'menu' AFTER tipo_pedido,
ADD INDEX idx_tipo_origen (tipo_origen);

-- Actualizar pedidos existentes: si todos sus items son 'store', marcar como 'store'
UPDATE rest_pedidos p
SET p.tipo_origen = 'store'
WHERE p.id IN (
    SELECT DISTINCT pi.pedido_id
    FROM rest_pedido_items pi
    WHERE pi.origen = 'store'
)
AND p.id NOT IN (
    SELECT DISTINCT pi2.pedido_id
    FROM rest_pedido_items pi2
    WHERE pi2.origen = 'menu'
);