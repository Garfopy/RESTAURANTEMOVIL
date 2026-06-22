-- Catalogo de modificadores por platillo y seleccion auditada por partida.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_configuracion' AND COLUMN_NAME='exclusiones_habilitadas'),
  'SELECT 1',
  'ALTER TABLE `rest_configuracion` ADD COLUMN `exclusiones_habilitadas` TINYINT(1) NOT NULL DEFAULT 1 AFTER `pedido_minimo`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_platillos' AND COLUMN_NAME='modificadores_sincronizados_at'),
  'SELECT 1',
  'ALTER TABLE `rest_platillos` ADD COLUMN `modificadores_sincronizados_at` DATETIME NULL AFTER `activo`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_configuracion' AND COLUMN_NAME='extras_habilitados'),
  'SELECT 1',
  'ALTER TABLE `rest_configuracion` ADD COLUMN `extras_habilitados` TINYINT(1) NOT NULL DEFAULT 1 AFTER `exclusiones_habilitadas`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS `rest_platillo_modificadores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `platillo_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('exclusion','extra') NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `ingrediente_id` INT UNSIGNED DEFAULT NULL,
  `cantidad_unidad` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `unidad` VARCHAR(20) DEFAULT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `max_cantidad` INT UNSIGNED NOT NULL DEFAULT 1,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rpm_platillo` (`restaurante_id`, `platillo_id`, `activo`),
  KEY `idx_rpm_ingrediente` (`ingrediente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rest_pedido_item_modificadores` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pedido_item_id` INT UNSIGNED NOT NULL,
  `modificador_id` INT UNSIGNED DEFAULT NULL,
  `tipo` ENUM('exclusion','extra') NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `ingrediente_id` INT UNSIGNED DEFAULT NULL,
  `cantidad` INT UNSIGNED NOT NULL DEFAULT 1,
  `cantidad_unidad` DECIMAL(12,3) NOT NULL DEFAULT 0,
  `unidad` VARCHAR(20) DEFAULT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rpim_item_modificador` (`pedido_item_id`, `modificador_id`),
  KEY `idx_rpim_modificador` (`modificador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
