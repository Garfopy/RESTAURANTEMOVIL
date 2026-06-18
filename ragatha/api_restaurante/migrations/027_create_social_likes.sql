-- Likes y matches persistentes del modo social.
-- MySQL 5.7 compatible e idempotente.

CREATE TABLE IF NOT EXISTS `social_likes` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `liker_user_id` INT(10) UNSIGNED NOT NULL,
    `liked_user_id` INT(10) UNSIGNED NOT NULL,
    `restaurante_id` INT(10) UNSIGNED NULL,
    `matched_at` DATETIME NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_social_likes_pair` (`liker_user_id`, `liked_user_id`),
    KEY `idx_social_likes_liker` (`liker_user_id`, `matched_at`),
    KEY `idx_social_likes_liked` (`liked_user_id`, `matched_at`),
    KEY `idx_social_likes_restaurante` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
