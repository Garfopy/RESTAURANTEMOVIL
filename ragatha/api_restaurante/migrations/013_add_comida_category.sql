-- ============================================
-- Migración 013: Agregar categoría "Comida" a la tienda
-- y campos tipo_producto + presentacion a store_productos
-- ============================================

-- Agregar columnas para tipo de producto y presentación
ALTER TABLE store_productos
ADD COLUMN tipo_producto VARCHAR(20) NOT NULL DEFAULT 'fisico' AFTER descripcion,
ADD COLUMN presentacion VARCHAR(100) DEFAULT NULL AFTER tipo_producto,
ADD INDEX idx_tipo_producto (tipo_producto);

-- Insertar categoría "Comida"
INSERT INTO store_categorias (nombre, descripcion, imagen) VALUES
('Comida', 'Comida preparada en presentaciones grandes para llevar', 'uploads/store/categorias/comida.jpg');

-- Productos de comida demo
INSERT INTO store_productos (categoria_id, nombre, descripcion, tipo_producto, presentacion, precio, imagen, stock) VALUES
-- El ID de la categoría Comida será el último insertado (último ID autoincrement)
((SELECT MAX(id) FROM store_categorias), 'Barbacoa de Res', 'Barbacoa de res estilo Hidalgo, cocida en horno de tierra', 'comida', '1 kg', 380.00, 'uploads/store/productos/barbacoa-res.jpg', 10),
((SELECT MAX(id) FROM store_categorias), 'Carnitas', 'Carnitas de cerdo estilo Michoacán, doradas en su propia grasa', 'comida', '1 kg', 320.00, 'uploads/store/productos/carnitas.jpg', 8),
((SELECT MAX(id) FROM store_categorias), 'Chicharrón Prensado', 'Chicharrón prensado en salsa verde, listo para calentar', 'comida', '500 g', 180.00, 'uploads/store/productos/chicharron.jpg', 15),
((SELECT MAX(id) FROM store_categorias), 'Mixiote de Pollo', 'Mixiotes de pollo en salsa de chile guajillo, 4 piezas', 'comida', '4 piezas', 250.00, 'uploads/store/productos/mixiote-pollo.jpg', 12),
((SELECT MAX(id) FROM store_categorias), 'Consomé de Barbacoa', 'Consomé concentrado de barbacoa, para 4 personas', 'comida', '1 litro', 120.00, 'uploads/store/productos/consome.jpg', 20),
((SELECT MAX(id) FROM store_categorias), 'Salsa Casera', 'Salsa roja molcajeteada, picor medio', 'comida', '500 ml', 95.00, 'uploads/store/productos/salsa-casera.jpg', 25);