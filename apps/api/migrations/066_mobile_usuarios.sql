-- 066: Usuarios de la app móvil Amare
-- Aplica sobre la misma DB del CarniHub

CREATE TABLE IF NOT EXISTS mobile_usuarios (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  nombre         VARCHAR(200) NOT NULL,
  email          VARCHAR(200) NOT NULL,
  telefono       VARCHAR(30)  NULL,
  foto_url       VARCHAR(500) NULL,
  google_id      VARCHAR(120) NULL COMMENT 'sub del ID token de Google',
  password_hash  VARCHAR(255) NULL COMMENT 'bcrypt — solo para login email',
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_email     (email),
  UNIQUE KEY uq_google_id (google_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
