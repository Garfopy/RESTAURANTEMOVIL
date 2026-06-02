-- 067: Sesiones / tokens para la app móvil

CREATE TABLE IF NOT EXISTS mobile_sesiones (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED NOT NULL,
  token_hash   CHAR(64)     NOT NULL COMMENT 'SHA-256 del raw token',
  device_info  VARCHAR(500) NULL,
  platform     ENUM('ios','android','web') NULL,
  activo       TINYINT(1)   NOT NULL DEFAULT 1,
  ultimo_uso   TIMESTAMP    NULL,
  expires_at   DATETIME     NOT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_token_hash (token_hash),
  KEY idx_usuario (usuario_id),
  CONSTRAINT fk_ms_usuario FOREIGN KEY (usuario_id)
    REFERENCES mobile_usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
