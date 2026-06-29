-- Social account coverage: lets one diner cover another diner's open consumption.

CREATE TABLE IF NOT EXISTS `social_account_covers` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` BIGINT UNSIGNED NOT NULL,
  `payer_user_id` BIGINT UNSIGNED NOT NULL,
  `covered_user_id` BIGINT UNSIGNED NOT NULL,
  `payer_mesa_id` BIGINT UNSIGNED NULL,
  `covered_mesa_id` BIGINT UNSIGNED NULL,
  `payer_mesa` VARCHAR(80) NULL,
  `covered_mesa` VARCHAR(80) NULL,
  `covered_consumo_id` VARCHAR(80) NULL,
  `payer_pedido_id` BIGINT UNSIGNED NULL,
  `payment_mode` VARCHAR(30) NOT NULL DEFAULT 'account',
  `status` VARCHAR(30) NOT NULL DEFAULT 'pending',
  `amount_mxn` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `items_count` INT NOT NULL DEFAULT 0,
  `payment_request_key` VARCHAR(80) NOT NULL,
  `stripe_payment_intent_id` VARCHAR(255) NULL,
  `message` VARCHAR(255) NULL,
  `paid_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social_account_cover_request` (`payer_user_id`, `payment_request_key`),
  KEY `idx_social_account_cover_covered` (`covered_user_id`, `status`),
  KEY `idx_social_account_cover_restaurant` (`restaurante_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `social_account_notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `actor_user_id` BIGINT UNSIGNED NULL,
  `type` VARCHAR(60) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `body` VARCHAR(255) NOT NULL,
  `payload_json` TEXT NULL,
  `read_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_social_account_notifications_user` (`user_id`, `read_at`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
