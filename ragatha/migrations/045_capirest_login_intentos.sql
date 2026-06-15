-- ============================================================
-- Migration 045: Tabla login_intentos para idactivo_capirest
-- AuthController la usa para anti brute-force (5 intentos / 2 min).
-- Esquema copiado de idactivo_carnihubdb (line 2275 del dump).
-- ============================================================

CREATE TABLE IF NOT EXISTS `login_intentos` (
  `id`         int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         varchar(45)  NOT NULL,
  `email`      varchar(150) DEFAULT NULL,
  `created_at` timestamp    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip`         (`ip`),
  KEY `idx_email`      (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
