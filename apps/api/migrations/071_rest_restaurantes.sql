-- 071_rest_restaurantes.sql
-- Crea la tabla rest_restaurantes que el backend del API y la app móvil usan como
-- "sucursales". Se puebla con los datos de empresas (restaurante_id = 1 = La Comalada).
--
-- ¿Por qué una tabla nueva y no usamos "empresas"?
--   empresas es una tabla contable/legal (razon_social, RFC…) sin coordenadas ni
--   imagen. rest_restaurantes es el perfil operativo de la sucursal para la app.

CREATE TABLE IF NOT EXISTS rest_restaurantes (
  id                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  empresa_id           INT UNSIGNED  NULL      COMMENT 'FK opcional a empresas.id',
  nombre               VARCHAR(200)  NOT NULL,
  slug                 VARCHAR(200)  NOT NULL,
  descripcion          TEXT          NULL,
  direccion            VARCHAR(400)  NULL,
  telefono             VARCHAR(30)   NULL,
  logo                 VARCHAR(500)  NULL,
  imagen_banner        VARCHAR(500)  NULL,
  lat                  DECIMAL(10,8) NULL,
  lng                  DECIMAL(11,8) NULL,
  color_primario       VARCHAR(20)   NOT NULL DEFAULT '#E63946',
  color_secundario     VARCHAR(20)   NOT NULL DEFAULT '#1D3557',
  horario_apertura     TIME          NULL,
  horario_cierre       TIME          NULL,
  horarios_json        JSON          NULL      COMMENT 'Horarios por día {lun:{abre,cierra},…}',
  mesas_habilitadas    TINYINT(1)    NOT NULL DEFAULT 1,
  reservas_habilitadas TINYINT(1)    NOT NULL DEFAULT 0,
  activo               TINYINT(1)    NOT NULL DEFAULT 1,
  created_at           TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uk_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────────────
-- Insertar La Comalada (id=1, coincide con restaurante_id en todas las tablas)
-- Ajusta los valores de lat/lng/dirección antes de correr en producción.
-- ─────────────────────────────────────────────────────────────────────────────

INSERT INTO rest_restaurantes
  (id, empresa_id, nombre, slug, descripcion, direccion,
   telefono, mesas_habilitadas, reservas_habilitadas, activo)
VALUES
  (1, 1, 'La Comalada', 'la-comalada',
   'Cocina tradicional mexicana',
   NULL, NULL, 1, 0, 1)
ON DUPLICATE KEY UPDATE
  nombre   = VALUES(nombre),
  activo   = VALUES(activo);
