-- 069: Favoritos del usuario móvil

CREATE TABLE IF NOT EXISTS mobile_favoritos (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED NOT NULL,
  platillo_id  INT UNSIGNED NOT NULL,
  created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_fav (usuario_id, platillo_id),
  KEY idx_usuario (usuario_id),
  KEY idx_platillo (platillo_id),
  CONSTRAINT fk_mf_usuario FOREIGN KEY (usuario_id)
    REFERENCES mobile_usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
