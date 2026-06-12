-- 068: Direcciones de entrega guardadas por el usuario

CREATE TABLE IF NOT EXISTS mobile_direcciones (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id       INT UNSIGNED NOT NULL,
  alias            VARCHAR(80)  NOT NULL DEFAULT 'Casa',
  calle            VARCHAR(300) NOT NULL,
  numero           VARCHAR(20)  NULL,
  colonia          VARCHAR(150) NULL,
  ciudad           VARCHAR(150) NOT NULL,
  estado_provincia VARCHAR(100) NULL,
  cp               VARCHAR(10)  NULL,
  lat              DECIMAL(10,8) NULL,
  lng              DECIMAL(11,8) NULL,
  instrucciones    TEXT         NULL,
  es_principal     TINYINT(1)   NOT NULL DEFAULT 0,
  activo           TINYINT(1)   NOT NULL DEFAULT 1,
  created_at       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_usuario (usuario_id),
  CONSTRAINT fk_md_usuario FOREIGN KEY (usuario_id)
    REFERENCES mobile_usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
