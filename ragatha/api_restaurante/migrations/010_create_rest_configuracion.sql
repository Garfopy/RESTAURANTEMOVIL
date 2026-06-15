-- Migración 010: Tabla de configuración por restaurante
-- Permite activar/desactivar métodos de pago y tipos de entrega por sucursal

CREATE TABLE IF NOT EXISTS `rest_configuracion` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT NOT NULL UNIQUE,
  `metodos_pago` JSON NOT NULL,
  `tipos_entrega` JSON NOT NULL,
  `costo_envio` DECIMAL(10,2) DEFAULT 0.00,
  `pedido_minimo` DECIMAL(10,2) DEFAULT 0.00,
  `activo` TINYINT(1) DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración por defecto para todos los restaurantes existentes
INSERT INTO `rest_configuracion` (`restaurante_id`, `metodos_pago`, `tipos_entrega`)
SELECT `id`, '["card","cash"]', '["delivery","pickup"]'
FROM `rest_restaurantes`
WHERE `id` NOT IN (SELECT `restaurante_id` FROM `rest_configuracion`);