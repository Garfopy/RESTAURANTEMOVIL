-- ============================================
-- Migración 010: Agregar columna 'origen' a rest_pedido_items
-- Permite distinguir items del menú (rest_platillos) 
-- de items de la tienda (store_productos)
-- ============================================

ALTER TABLE rest_pedido_items
ADD COLUMN origen VARCHAR(20) NOT NULL DEFAULT 'menu' AFTER platillo_id,
ADD INDEX idx_origen (origen);