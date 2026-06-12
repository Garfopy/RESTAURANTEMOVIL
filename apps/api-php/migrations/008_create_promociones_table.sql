-- ============================================
-- Migración 008: Crear tabla de promociones por usuario
-- ============================================
-- Permite asignar promociones individuales a cada usuario
-- ============================================

CREATE TABLE IF NOT EXISTS mobile_promociones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    titulo VARCHAR(255) NOT NULL,
    descripcion TEXT DEFAULT NULL,
    imagen VARCHAR(500) DEFAULT NULL,
    deep_link VARCHAR(500) DEFAULT NULL,
    code VARCHAR(50) DEFAULT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES mobile_usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario (usuario_id),
    INDEX idx_activo (activo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Datos demo (seed) para 3 usuarios existentes
-- ============================================
-- Ajusta los IDs según los usuarios reales en tu BD

-- Usuario ID 1: Promos de cafetería
INSERT INTO mobile_promociones (usuario_id, titulo, descripcion, imagen, deep_link, code)
VALUES 
(1, 'Café + Pan dulce', 'Combo matutino: café latte + pan de yema a solo $49', 'uploads/promos/combo-cafe.jpg', '/product/5', 'COMBO49'),
(1, '2x1 en Frappés', 'Válido todos los miércoles en frappés de vainilla y chocolate', 'uploads/promos/frappes.jpg', '/product/8', 'FRAPP2X1');

-- Usuario ID 2: Promos de postres
INSERT INTO mobile_promociones (usuario_id, titulo, descripcion, imagen, deep_link, code)
VALUES 
(2, 'Pay de queso gratis', 'En tu segunda compra llévate un pay de queso de regalo', 'uploads/promos/pay-queso.jpg', '/product/12', 'PAYREGALO'),
(2, 'Descuento en Smoothie', '15% OFF en smoothies de frutos rojos', 'uploads/promos/smoothie.jpg', '/product/15', 'SMOOTHIE15');

-- Usuario ID 3: Promos generales
INSERT INTO mobile_promociones (usuario_id, titulo, descripcion, imagen, deep_link, code)
VALUES 
(3, 'Benvenuto', 'Como nuevo usuario obtén $30 de descuento en tu primer pedido', 'uploads/promos/bienvenida.jpg', '/checkout/order-type', 'BIENVENIDO30');