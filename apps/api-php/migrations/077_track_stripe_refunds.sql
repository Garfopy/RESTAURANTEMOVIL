-- Seguimiento acumulado de reembolsos Stripe. Compatible con MySQL 5.7.
CREATE TABLE IF NOT EXISTS `stripe_charge_refund_state` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stripe_charge_id` VARCHAR(255) NOT NULL,
  `payment_intent_id` VARCHAR(255) NULL,
  `user_id` INT UNSIGNED NULL,
  `refunded_cents` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stripe_refund_charge` (`stripe_charge_id`),
  KEY `idx_stripe_refund_user` (`user_id`, `updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

