-- Migración 027: Columna firma_path en pedido_sucursal
-- Para almacenar la firma digital del receptor en entregas directas (sin ruta formal)

ALTER TABLE pedido_sucursal
    ADD COLUMN IF NOT EXISTS firma_path VARCHAR(255) NULL AFTER foto_entrega_path;
