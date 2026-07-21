CREATE TABLE IF NOT EXISTS `api_rate_limits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `bucket_key` CHAR(64) NOT NULL,
  `scope` VARCHAR(80) NOT NULL,
  `attempts` INT UNSIGNED NOT NULL DEFAULT 1,
  `window_started_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_rate_limits_bucket` (`bucket_key`),
  KEY `idx_api_rate_limits_cleanup` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
