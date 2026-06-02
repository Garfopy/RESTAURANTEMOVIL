-- 070: Modificadores y opciones de personalización para platillos

CREATE TABLE IF NOT EXISTS rest_modificadores (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  restaurante_id INT UNSIGNED NOT NULL,
  nombre         VARCHAR(150) NOT NULL,
  tipo           ENUM('radio','checkbox') NOT NULL DEFAULT 'radio',
  requerido      TINYINT(1)   NOT NULL DEFAULT 0,
  min_selecciones TINYINT     NOT NULL DEFAULT 0,
  max_selecciones TINYINT     NOT NULL DEFAULT 1,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  created_at     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rest (restaurante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Relación many-to-many: platillo <-> modificador
CREATE TABLE IF NOT EXISTS rest_platillo_modificadores (
  platillo_id    INT UNSIGNED NOT NULL,
  modificador_id INT UNSIGNED NOT NULL,
  orden          TINYINT      NOT NULL DEFAULT 0,
  PRIMARY KEY (platillo_id, modificador_id),
  KEY idx_mod (modificador_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Opciones de cada modificador (ej: "Sin cebolla", "Extra queso")
CREATE TABLE IF NOT EXISTS rest_opciones_modificador (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  modificador_id INT UNSIGNED NOT NULL,
  nombre         VARCHAR(150) NOT NULL,
  precio_extra   DECIMAL(8,2) NOT NULL DEFAULT 0.00,
  activo         TINYINT(1)   NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  KEY idx_mod (modificador_id),
  CONSTRAINT fk_om_modificador FOREIGN KEY (modificador_id)
    REFERENCES rest_modificadores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Agregar columna tipo_pedido a rest_pedidos si no existe
ALTER TABLE rest_pedidos
  ADD COLUMN IF NOT EXISTS tipo_pedido  ENUM('delivery','pickup','eat_in') NULL DEFAULT 'eat_in',
  ADD COLUMN IF NOT EXISTS mobile_usuario_id INT UNSIGNED NULL,
  ADD COLUMN IF NOT EXISTS direccion_entrega TEXT NULL,
  ADD COLUMN IF NOT EXISTS stripe_payment_intent_id VARCHAR(100) NULL;
