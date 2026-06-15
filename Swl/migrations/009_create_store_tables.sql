-- ============================================
-- Migración 009: Crear tablas para la Tienda (Store)
-- ============================================
-- Catálogo de productos físicos del restaurante
-- (pinturas, botellas de licor, artesanías, mobiliario, etc.)
-- Independiente del menú de platillos (rest_platillos)
-- ============================================

-- Tabla de categorías de la tienda
CREATE TABLE IF NOT EXISTS store_categorias (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    imagen VARCHAR(500) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla de productos de la tienda
CREATE TABLE IF NOT EXISTS store_productos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoria_id INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    precio DECIMAL(10, 2) NOT NULL,
    imagen VARCHAR(500) DEFAULT NULL,
    stock INT NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (categoria_id) REFERENCES store_categorias(id) ON DELETE RESTRICT,
    INDEX idx_categoria (categoria_id),
    INDEX idx_activo (activo),
    INDEX idx_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Datos demo (seed)
-- ============================================

-- Categorías demo
INSERT INTO store_categorias (nombre, descripcion, imagen) VALUES
('Pinturas', 'Obras de arte originales exhibidas en el restaurante', 'uploads/store/categorias/pinturas.jpg'),
('Licores', 'Botellas de licor premium y ediciones especiales', 'uploads/store/categorias/licores.jpg'),
('Artesanías', 'Piezas artesanales únicas de la región', 'uploads/store/categorias/artesanias.jpg'),
('Mobiliario', 'Muebles y decoración del restaurante', 'uploads/store/categorias/mobiliario.jpg');

-- Productos demo
INSERT INTO store_productos (categoria_id, nombre, descripcion, precio, imagen, stock) VALUES
-- Pinturas
(1, 'Atardecer en el Valle', 'Óleo sobre lienzo 60x80cm, paisaje del valle al atardecer', 3500.00, 'uploads/store/productos/atardecer-valle.jpg', 1),
(1, 'Naturaleza Muerta', 'Acrílico sobre lienzo 50x70cm, bodegón estilo clásico', 2800.00, 'uploads/store/productos/naturaleza-muerta.jpg', 1),
(1, 'Abstracto Fusión', 'Técnica mixta sobre lienzo 100x120cm, arte contemporáneo', 4200.00, 'uploads/store/productos/abstracto-fusion.jpg', 1),

-- Licores
(2, 'Tequila Reserva Especial', 'Tequila añejo reserva de la casa, botella 750ml', 890.00, 'uploads/store/productos/tequila-reserva.jpg', 5),
(2, 'Mezcal Artesanal', 'Mezcal artesanal de agave espadín, edición limitada 750ml', 750.00, 'uploads/store/productos/mezcal-artesanal.jpg', 3),
(2, 'Vino Tinto Gran Reserva', 'Vino tinto reserva 2019, 750ml, denominación de origen', 1200.00, 'uploads/store/productos/vino-gran-reserva.jpg', 8),

-- Artesanías
(3, 'Catrina de Barro', 'Catrina artesanal de barro pintada a mano, 30cm de altura', 450.00, 'uploads/store/productos/catrina-barro.jpg', 4),
(3, 'Alebrije Jaguar', 'Alebrije tradicional de madera tallada y pintada, 25cm', 680.00, 'uploads/store/productos/alebrije-jaguar.jpg', 2),
(3, 'Textil Bordado', 'Mantel bordado a mano con diseños tradicionales, 150x150cm', 550.00, 'uploads/store/productos/textil-bordado.jpg', 6),

-- Mobiliario
(4, 'Silla Artesanal', 'Silla de madera de mezquite tallada a mano con asiento de piel', 2200.00, 'uploads/store/productos/silla-artesanal.jpg', 2),
(4, 'Mesa de Centro', 'Mesa de centro de parota con acabado natural, 120x60cm', 4800.00, 'uploads/store/productos/mesa-centro.jpg', 1),
(4, 'Lámpara de Forja', 'Lámpara de pie de hierro forjado con pantalla de vidrio soplado', 3200.00, 'uploads/store/productos/lampara-forja.jpg', 3);