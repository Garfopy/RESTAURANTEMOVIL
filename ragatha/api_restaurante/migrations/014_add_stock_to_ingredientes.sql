-- ============================================
-- Migración 014: Agregar stock a ingredientes y 
-- columna cantidad a rest_receta_ingredientes
-- para descontar inventario al crear pedidos
-- ============================================

-- Agregar stock a rest_ingredientes
ALTER TABLE rest_ingredientes
ADD COLUMN stock DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER nombre,
ADD INDEX idx_stock (stock);

-- Agregar cantidad y unidad a rest_receta_ingredientes
ALTER TABLE rest_receta_ingredientes
ADD COLUMN cantidad DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER precio_extra,
ADD COLUMN unidad VARCHAR(20) DEFAULT 'g' AFTER cantidad;