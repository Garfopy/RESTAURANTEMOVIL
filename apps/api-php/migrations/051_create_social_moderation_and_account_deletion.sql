-- Moderacion social y soporte para eliminacion/anonimizacion de cuenta.
-- Ejecutar despues de 050_add_google_onboarding_fields_to_mobile_usuarios.sql.
-- Compatible con MySQL 5.7.

CREATE TABLE IF NOT EXISTS `social_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blocker_user_id` INT UNSIGNED NOT NULL,
  `blocked_user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(80) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social_blocks_pair` (`blocker_user_id`, `blocked_user_id`),
  KEY `idx_social_blocks_blocker` (`blocker_user_id`, `created_at`),
  KEY `idx_social_blocks_blocked` (`blocked_user_id`, `created_at`),
  CONSTRAINT `fk_social_blocks_blocker`
    FOREIGN KEY (`blocker_user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_social_blocks_blocked`
    FOREIGN KEY (`blocked_user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `social_reports` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `reporter_user_id` INT UNSIGNED NOT NULL,
  `reported_user_id` INT UNSIGNED NOT NULL,
  `reason` VARCHAR(80) NOT NULL,
  `details` TEXT NULL,
  `status` VARCHAR(30) NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  `reviewed_by` INT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_social_reports_status` (`status`, `created_at`),
  KEY `idx_social_reports_reported` (`reported_user_id`, `created_at`),
  KEY `idx_social_reports_reporter` (`reporter_user_id`, `created_at`),
  CONSTRAINT `fk_social_reports_reporter`
    FOREIGN KEY (`reporter_user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_social_reports_reported`
    FOREIGN KEY (`reported_user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
