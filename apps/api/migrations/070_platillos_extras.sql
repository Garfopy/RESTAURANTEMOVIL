-- 070_platillos_extras.sql (MySQL 5.7 compatible — reescrito)
-- Ejecutar con: mysql -u<user> -p <database> < 070_platillos_extras.sql
--
-- NOTAS SOBRE CONFLICTOS CON EL SCHEMA EXISTENTE:
--   - rest_modificadores ya existe con tipo ENUM('extra','sin','opcion') ? se usan nombres app_*
--   - rest_platillo_modificador (singular) ya existe ? nueva tabla es app_platillo_modificadores
--   - ADD COLUMN IF NOT EXISTS no existe en MySQL 5.7 ? se usa stored procedure

-- -----------------------------------------------------------------------------
-- 1. Modificadores para la app móvil (nuevas tablas con prefijo app_)
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS app_modificadores (
  id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  restaurante_id  INT UNSIGNED  NOT NULL,
  nombre          VARCHAR(150)  NOT NULL,
  tipo            ENUM('radio','checkbox') NOT NULL DEFAULT 'radio',
  requerido       TINYINT(1)    NOT NULL DEFAULT 0,
  min_selecciones TINYINT       NOT NULL DEFAULT 0,
  max_selecciones TINYINT       NOT NULL DEFAULT 1,
  activo          TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_appmod_rest (restaurante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_platillo_modificadores (
  platillo_id    INT UNSIGNED NOT NULL,
  modificador_id INT UNSIGNED NOT NULL,
  orden          TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (platillo_id, modificador_id),
  KEY idx_appplatmod_mod (modificador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS app_opciones_modificador (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modificador_id INT UNSIGNED NOT NULL,
  nombre         VARCHAR(150) NOT NULL,
  precio_extra   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_appopmod_mod (modificador_id),
  CONSTRAINT fk_appopmod_mod FOREIGN KEY (modificador_id)
    REFERENCES app_modificadores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Extender ENUMs en rest_pedidos
-- -----------------------------------------------------------------------------

-- tipo_pedido: agregar pickup y eat_in (la app móvil los usa)
ALTER TABLE rest_pedidos
  MODIFY COLUMN tipo_pedido
    ENUM('dine_in','take_out','delivery','pickup','eat_in')
    NOT NULL DEFAULT 'dine_in';

-- estado: agregar en_camino (requerido por el tracking de la app)
ALTER TABLE rest_pedidos
  MODIFY COLUMN estado
    ENUM('pendiente','en_preparacion','listo','en_camino','entregado','cancelado')
    NOT NULL DEFAULT 'pendiente';

-- -----------------------------------------------------------------------------
-- 3. Agregar columnas a rest_pedidos y rest_visitas (MySQL 5.7: via procedimiento)
-- -----------------------------------------------------------------------------

DROP PROCEDURE IF EXISTS sp_070_migrate;

DELIMITER $$
CREATE PROCEDURE sp_070_migrate()
BEGIN
  -- rest_pedidos: mobile_usuario_id
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'rest_pedidos'
      AND COLUMN_NAME  = 'mobile_usuario_id'
  ) THEN
    ALTER TABLE rest_pedidos
      ADD COLUMN mobile_usuario_id INT UNSIGNED NULL
      COMMENT 'FK a mobile_usuarios (pedido desde app)';
  END IF;

  -- rest_pedidos: stripe_payment_intent_id
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'rest_pedidos'
      AND COLUMN_NAME  = 'stripe_payment_intent_id'
  ) THEN
    ALTER TABLE rest_pedidos
      ADD COLUMN stripe_payment_intent_id VARCHAR(100) NULL
      COMMENT 'pi_xxx de Stripe';
  END IF;

  -- rest_visitas: tipo (para distinguir visitas de app vs mesa QR)
  IF NOT EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'rest_visitas'
      AND COLUMN_NAME  = 'tipo'
  ) THEN
    ALTER TABLE rest_visitas
      ADD COLUMN tipo ENUM('presencial','mobile') NULL DEFAULT 'presencial'
      AFTER restaurante_id;
  END IF;
END$$
DELIMITER ;

CALL sp_070_migrate();
DROP PROCEDURE IF EXISTS sp_070_migrate;
