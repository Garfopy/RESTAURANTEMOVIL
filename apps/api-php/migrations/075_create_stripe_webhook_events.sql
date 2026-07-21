-- Stripe webhook inbox. Compatible con MySQL 5.7 e idempotente.
CREATE TABLE IF NOT EXISTS `stripe_webhook_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `stripe_event_id` VARCHAR(255) NOT NULL,
  `event_type` VARCHAR(120) NOT NULL,
  `object_id` VARCHAR(255) NULL,
  `status` VARCHAR(24) NOT NULL DEFAULT 'processing',
  `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
  `last_error` TEXT NULL,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME NULL,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_stripe_webhook_event` (`stripe_event_id`),
  KEY `idx_stripe_webhook_status` (`status`, `updated_at`),
  KEY `idx_stripe_webhook_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
