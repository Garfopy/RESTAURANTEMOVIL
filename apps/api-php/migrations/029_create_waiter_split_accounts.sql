-- Cuentas separadas y pagos parciales para el panel de mesero.
-- MySQL 5.7 compatible.

CREATE TABLE IF NOT EXISTS `rest_cuenta_divisiones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `mesa_id` INT UNSIGNED NOT NULL,
  `estado` ENUM('activa','pagada','cancelada') NOT NULL DEFAULT 'activa',
  `creado_por_usuario_id` INT UNSIGNED NOT NULL,
  `creado_por_nombre` VARCHAR(120) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_cuenta_division_mesa_estado` (`restaurante_id`, `mesa_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rest_cuenta_division_cuentas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` INT UNSIGNED NOT NULL,
  `numero` SMALLINT UNSIGNED NOT NULL,
  `nombre` VARCHAR(60) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('pendiente','pagada') NOT NULL DEFAULT 'pendiente',
  `metodo_pago` VARCHAR(30) DEFAULT NULL,
  `pagado_por_usuario_id` INT UNSIGNED DEFAULT NULL,
  `pagado_por_nombre` VARCHAR(120) DEFAULT NULL,
  `pagado_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuenta_division_numero` (`division_id`, `numero`),
  KEY `idx_cuenta_division_estado` (`division_id`, `estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rest_cuenta_division_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `cuenta_id` INT UNSIGNED NOT NULL,
  `pedido_item_id` INT UNSIGNED NOT NULL,
  `cantidad` INT UNSIGNED NOT NULL,
  `precio_unit` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cuenta_item` (`cuenta_id`, `pedido_item_id`),
  KEY `idx_division_item_origen` (`pedido_item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
