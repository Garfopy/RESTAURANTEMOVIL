-- Registra el consumo definitivo de codigos promocionales por usuario.
-- Un codigo se considera usado solamente despues de confirmar el pago.

CREATE TABLE IF NOT EXISTS `mobile_promocion_usos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `promocion_id` INT NOT NULL,
  `usuario_id` INT NOT NULL,
  `pedido_id` INT NULL,
  `codigo` VARCHAR(50) NOT NULL,
  `descuento_mxn` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `estado` VARCHAR(20) NOT NULL DEFAULT 'usado',
  `usado_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mobile_promo_usage_user_promotion` (`usuario_id`, `promocion_id`),
  UNIQUE KEY `uq_mobile_promo_usage_order_promotion` (`pedido_id`, `promocion_id`),
  KEY `idx_mobile_promo_usage_user_date` (`usuario_id`, `usado_at`),
  KEY `idx_mobile_promo_usage_promotion` (`promocion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Guardamos el codigo en el pedido para poder auditarlo al confirmar el pago.
SET @has_promo_code = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'rest_pedidos'
     AND COLUMN_NAME = 'promo_code'
);
SET @add_promo_code = IF(
  @has_promo_code = 0,
  'ALTER TABLE `rest_pedidos` ADD COLUMN `promo_code` VARCHAR(50) NULL AFTER `descuento`',
  'SELECT 1'
);
PREPARE stmt_add_promo_code FROM @add_promo_code;
EXECUTE stmt_add_promo_code;
DEALLOCATE PREPARE stmt_add_promo_code;
