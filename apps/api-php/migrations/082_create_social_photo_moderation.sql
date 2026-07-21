CREATE TABLE IF NOT EXISTS `social_photo_moderation` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT UNSIGNED NOT NULL,
  `photo_url` VARCHAR(600) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `review_notes` VARCHAR(500) NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_social_photo_url` (`photo_url`),
  KEY `idx_social_photo_queue` (`status`, `created_at`),
  KEY `idx_social_photo_user` (`user_id`, `status`),
  CONSTRAINT `fk_social_photo_user` FOREIGN KEY (`user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
