-- ============================================================
-- 047 — CapiRest: Schema completo autónomo (BD independiente)
-- ============================================================
-- Crea la base de datos idactivo_capirest desde cero como
-- sistema standalone, sin dependencias al schema de CarniHub.
--
-- Diferencias respecto a ejecutar las migraciones 001–046:
--   · Solo incluye tablas necesarias para el módulo restaurante
--   · Tabla `usuarios` simplificada: solo roles de staff/dueño
--   · NO incluye: productos, pedidos B2B, sucursales, vehiculos,
--     rutas, facturas, combos, ni ninguna tabla del marketplace
--   · FKs cross-BD eliminadas (carnihub_producto_id,
--     empresa_proveedor_id, sucursal_id → quedan como INT NULL)
--   · Nueva tabla `carnihub_api_config` para integración vía API
--
-- Ejecutar en BD vacía: CREATE DATABASE idactivo_capirest;
-- ============================================================

SET FOREIGN_KEY_CHECKS = 0;
SET NAMES utf8mb4;

-- ============================================================
-- BLOQUE 1 — Tablas de soporte (auth, config)
-- ============================================================

-- ── Roles (solo roles del ecosistema restaurante) ─────────────
CREATE TABLE IF NOT EXISTS `roles` (
  `id`     TINYINT UNSIGNED NOT NULL,
  `nombre` VARCHAR(50)      NOT NULL,
  `slug`   VARCHAR(50)      NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `roles` VALUES
  (5,  'Dueño / Comprador', 'comprador'),
  (7,  'Mesero',            'mesero'),
  (8,  'Chef',              'chef'),
  (9,  'Portero',           'portero'),
  (10, 'Admin Local',       'admin_local');

-- ── Empresas (solo negocios de tipo restaurante) ──────────────
CREATE TABLE IF NOT EXISTS `empresas` (
  `id`               INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `razon_social`     VARCHAR(200)     NOT NULL,
  `rfc`              VARCHAR(15)      NULL,
  `tipo_negocio`     ENUM('taqueria','carniceria','restaurante','comedor','otro') NULL,
  `email`            VARCHAR(150)     NULL,
  `telefono`         VARCHAR(20)      NULL,
  `direccion_fiscal` TEXT             NULL,
  `activo`           TINYINT(1)       NOT NULL DEFAULT 1,
  `created_at`       TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Usuarios (dueños + staff del restaurante) ─────────────────
--   Sin empresa_id como FK (puede ser null, referencia suave)
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id`                 INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `nombre`             VARCHAR(100)     NOT NULL,
  `apellido_paterno`   VARCHAR(100)     NOT NULL,
  `apellido_materno`   VARCHAR(100)     NULL,
  `email`              VARCHAR(150)     NOT NULL,
  `password`           VARCHAR(255)     NOT NULL,
  `rol_id`             TINYINT UNSIGNED NOT NULL,
  `empresa_id`         INT UNSIGNED     NULL
                         COMMENT 'FK soft a empresas.id en esta BD',
  `restaurante_id`     INT UNSIGNED     NULL
                         COMMENT 'Restaurante al que pertenece el staff (set en alta)',
  `restaurante_activo` TINYINT(1)       NOT NULL DEFAULT 0
                         COMMENT '1 = comprador con módulo restaurante activo',
  `activo`             TINYINT(1)       NOT NULL DEFAULT 1,
  `email_verificado`   TINYINT(1)       NOT NULL DEFAULT 1
                         COMMENT '1 = verificado; DEFAULT 1 porque el admin crea las cuentas directamente',
  `token_verificacion` VARCHAR(128)     NULL,
  `token_expira`       DATETIME         NULL,
  `avatar`             VARCHAR(255)     NULL,
  `telefono`           VARCHAR(20)      NULL,
  `created_by`         INT UNSIGNED     NULL,
  `created_at`         TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  CONSTRAINT `fk_usuario_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Configuración global de la app restaurante ───────────────
CREATE TABLE IF NOT EXISTS `global_settings` (
  `clave`    VARCHAR(100) NOT NULL,
  `valor`    TEXT         NULL,
  `tipo`     ENUM('text','number','boolean','json','color','password') NOT NULL DEFAULT 'text',
  `grupo`    VARCHAR(50)  NULL,
  `etiqueta` VARCHAR(150) NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `global_settings` (`clave`, `valor`, `tipo`, `grupo`, `etiqueta`) VALUES
  ('app_name',          'Mi Restaurante',  'text',     'general',  'Nombre del sitio'),
  ('app_logo',          '',                'text',     'general',  'Logo del sitio (ruta o URL)'),
  ('color_primary',     '#C8102E',         'color',    'estilos',  'Color primario'),
  ('color_secondary',   '#1f2937',         'color',    'estilos',  'Color secundario'),
  ('smtp_host',         '',                'text',     'correo',   'Servidor SMTP'),
  ('smtp_port',         '587',             'number',   'correo',   'Puerto SMTP'),
  ('smtp_user',         '',                'text',     'correo',   'Usuario SMTP'),
  ('smtp_pass',         '',                'password', 'correo',   'Contraseña SMTP'),
  ('smtp_from',         '',                'text',     'correo',   'Correo remitente'),
  ('telefono_contacto', '',                'text',     'contacto', 'Teléfono de contacto'),
  ('horarios_atencion', 'Lun-Vie 8am-6pm', 'text',     'contacto', 'Horarios de atención'),
  ('paypal_client_id',  '',                'text',     'pagos',    'PayPal Client ID'),
  ('paypal_secret',     '',                'password', 'pagos',    'PayPal Secret'),
  ('paypal_mode',       'sandbox',         'text',     'pagos',    'PayPal Mode (sandbox/live)');

-- ── Bitácora de acciones (simplificada) ───────────────────────
CREATE TABLE IF NOT EXISTS `action_logs` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED    NULL,
  `rol`         VARCHAR(30)     NULL,
  `empresa_id`  INT UNSIGNED    NULL,
  `accion`      VARCHAR(100)    NULL,
  `modulo`      VARCHAR(50)     NULL,
  `descripcion` TEXT            NULL,
  `ip`          VARCHAR(45)     NULL,
  `created_at`  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Control brute-force login ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `login_intentos` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip`         VARCHAR(45)  NOT NULL,
  `email`      VARCHAR(150) NULL,
  `created_at` TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip`      (`ip`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- BLOQUE 2 — Módulo Restaurante (tablas rest_*)
--            Equivalente a migraciones 022–046 consolidadas,
--            sin FKs que apunten a tablas del marketplace
-- ============================================================

-- ── Restaurantes / sucursales ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_restaurantes` (
  `id`                       INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empresa_id`               INT UNSIGNED  NOT NULL
                               COMMENT 'FK a empresas en esta BD',
  `comprador_id`             INT UNSIGNED  NOT NULL
                               COMMENT 'FK a usuarios en esta BD',
  -- [039] Distribuidor preferido: ID de empresa en CarniHub (referencia suave, sin FK)
  `empresa_proveedor_id`     INT UNSIGNED  NULL
                               COMMENT 'ID de la empresa en CarniHub (soft ref, sin FK)',
  -- [031] Sucursal en CarniHub para auto-importar inventario (soft ref, sin FK)
  `sucursal_carnihub_id`     INT UNSIGNED  NULL
                               COMMENT 'ID de sucursal en CarniHub (soft ref, sin FK)',
  `nombre`                   VARCHAR(200)  NOT NULL,
  `slug`                     VARCHAR(100)  NOT NULL,
  `logo`                     VARCHAR(255)  NULL,
  `imagen_banner`            VARCHAR(255)  NULL,          -- [043]
  `color_primario`           VARCHAR(7)    NOT NULL DEFAULT '#C8102E',
  `color_secundario`         VARCHAR(7)    NOT NULL DEFAULT '#1f2937',
  `descripcion`              TEXT          NULL,
  `telefono`                 VARCHAR(20)   NULL,
  `direccion`                TEXT          NULL,
  `lat`                      DECIMAL(10,8) NULL,
  `lng`                      DECIMAL(11,8) NULL,
  `horario_apertura`         TIME          NULL,
  `horario_cierre`           TIME          NULL,
  `horarios_json`            TEXT          NULL,          -- [028]
  -- [026] Toggles de operación
  `mesas_habilitadas`        TINYINT(1)    NOT NULL DEFAULT 1,
  `reservas_habilitadas`     TINYINT(1)    NOT NULL DEFAULT 1,
  `portero_habilitado`       TINYINT(1)    NOT NULL DEFAULT 1,
  `propinas_sugeridas`       VARCHAR(40)   NOT NULL DEFAULT '0,10,15,20',
  `requiere_login_comensal`  TINYINT(1)    NOT NULL DEFAULT 0,
  `activo`                   TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`               TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_rr_empresa`       (`empresa_id`),
  KEY `idx_rr_comprador`     (`comprador_id`),
  CONSTRAINT `fk_rrest_empresa`   FOREIGN KEY (`empresa_id`)   REFERENCES `empresas`(`id`),
  CONSTRAINT `fk_rrest_comprador` FOREIGN KEY (`comprador_id`) REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Zonas del restaurante ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_zonas` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED  NOT NULL,
  `nombre`         VARCHAR(100)  NOT NULL,
  `descripcion`    VARCHAR(255)  NULL,
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rzona_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Mesas ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_mesas` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED  NOT NULL,
  `zona_id`        INT UNSIGNED  NULL,
  `nombre`         VARCHAR(50)   NOT NULL,
  `capacidad`      TINYINT       NOT NULL DEFAULT 4,
  `qr_codigo`      VARCHAR(64)   NOT NULL,
  `posicion_x`     INT           NOT NULL DEFAULT 0,
  `posicion_y`     INT           NOT NULL DEFAULT 0,
  `estado`         ENUM('disponible','ocupada','reservada','pagando') NOT NULL DEFAULT 'disponible',
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mesa_qr` (`qr_codigo`),
  CONSTRAINT `fk_rmesa_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rmesa_zona` FOREIGN KEY (`zona_id`)        REFERENCES `rest_zonas`(`id`)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Categorías del menú ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_categorias_menu` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED  NOT NULL,
  `nombre`         VARCHAR(100)  NOT NULL,
  `descripcion`    VARCHAR(255)  NULL,
  `imagen`         VARCHAR(255)  NULL,
  `orden`          TINYINT       NOT NULL DEFAULT 0,
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rcat_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Platillos ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_platillos` (
  `id`                     INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `codigo`                 VARCHAR(20)    NULL,
  `es_armado`              TINYINT(1)     NOT NULL DEFAULT 0,
  `restaurante_id`         INT UNSIGNED   NOT NULL,
  `categoria_id`           INT UNSIGNED   NULL,
  `nombre`                 VARCHAR(200)   NOT NULL,
  `descripcion`            TEXT           NULL,
  `alergenos`              VARCHAR(500)   NULL,           -- [030]
  `contiene`               TEXT           NULL,           -- [030]
  `precio`                 DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `imagen`                 VARCHAR(255)   NULL,
  `tiempo_preparacion_min` TINYINT        NOT NULL DEFAULT 15,
  `disponible`             TINYINT(1)     NOT NULL DEFAULT 1,
  `activo`                 TINYINT(1)     NOT NULL DEFAULT 1,
  -- [042] Ingrediente directo para bebidas/postres sin receta
  `ingrediente_directo_id` INT UNSIGNED   NULL,
  `created_at`             TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rplat_ing_directo` (`ingrediente_directo_id`),
  CONSTRAINT `fk_rplat_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rplat_cat`  FOREIGN KEY (`categoria_id`)   REFERENCES `rest_categorias_menu`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Recetas ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_recetas` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `platillo_id`    INT UNSIGNED  NOT NULL,
  `porciones_base` TINYINT       NOT NULL DEFAULT 1,
  `notas`          TEXT          NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receta_platillo` (`platillo_id`),
  CONSTRAINT `fk_rrec_plat` FOREIGN KEY (`platillo_id`) REFERENCES `rest_platillos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Ingredientes del almacén del restaurante ─────────────────
--   carnihub_producto_id: referencia suave al catálogo del
--   distribuidor en CarniHub (sin FK, sin tabla productos local)
CREATE TABLE IF NOT EXISTS `rest_ingredientes` (
  `id`                   INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `codigo`               VARCHAR(20)    NULL,
  `tipo`                 VARCHAR(30)    NULL
                           COMMENT 'materia_prima|guarnicion|otro',
  `restaurante_id`       INT UNSIGNED   NOT NULL,
  `nombre`               VARCHAR(200)   NOT NULL,
  `unidad_principal`     VARCHAR(20)    NOT NULL DEFAULT 'kg',
  `unidad_compra`        VARCHAR(20)    NULL,
  `equivalencia`         DECIMAL(10,4)  NOT NULL DEFAULT 1.0000,
  `costo_unitario`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `stock`                DECIMAL(10,3)  NOT NULL DEFAULT 0.000,
  `stock_minimo`         DECIMAL(10,3)  NOT NULL DEFAULT 0.000,
  `categoria`            VARCHAR(100)   NULL,
  -- Integración CarniHub: referencia suave (no hay FK a tabla productos)
  `carnihub_producto_id` INT UNSIGNED   NULL
                           COMMENT 'ID del producto en el catálogo de CarniHub (soft ref, sin FK)',
  `proveedor_carnihub`   TINYINT(1)     NOT NULL DEFAULT 0
                           COMMENT '1 = se pide a través de CarniHub',
  `proveedor_nombre`     VARCHAR(100)   NULL,
  `dias_entrega`         SMALLINT UNSIGNED NULL DEFAULT 1
                           COMMENT 'Lead time del proveedor en días',  -- [038]
  `activo`               TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`           TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ring_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Columna ingrediente_directo_id en rest_platillos (migración 042)
-- Si la tabla ya existía sin esta columna, se agrega ahora
SET @col_exists = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'rest_platillos'
    AND COLUMN_NAME  = 'ingrediente_directo_id'
);
SET @sql = IF(@col_exists = 0,
  "ALTER TABLE `rest_platillos` ADD COLUMN `ingrediente_directo_id` INT UNSIGNED NULL AFTER `activo`, ADD KEY `idx_rplat_ing_directo` (`ingrediente_directo_id`)",
  "SELECT 'Columna ingrediente_directo_id ya existe, omitiendo' AS info"
);
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- FK de platillos → ingrediente_directo (solo si no existe ya)
SET @fk_exists = (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE()
    AND TABLE_NAME        = 'rest_platillos'
    AND CONSTRAINT_NAME   = 'fk_rplat_ing_directo'
    AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
  "ALTER TABLE `rest_platillos` ADD CONSTRAINT `fk_rplat_ing_directo` FOREIGN KEY (`ingrediente_directo_id`) REFERENCES `rest_ingredientes`(`id`) ON DELETE SET NULL",
  "SELECT 'FK fk_rplat_ing_directo ya existe, omitiendo' AS info"
);
PREPARE _stmt FROM @sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;

-- ── Ingredientes de receta ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_receta_ingredientes` (
  `id`              INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `receta_id`       INT UNSIGNED   NOT NULL,
  `ingrediente_id`  INT UNSIGNED   NOT NULL,
  `cantidad`        DECIMAL(10,3)  NOT NULL,
  `unidad`          VARCHAR(20)    NOT NULL DEFAULT 'kg',
  `notas`           VARCHAR(255)   NULL,
  `es_informativo`  TINYINT(1)     NOT NULL DEFAULT 0,   -- [030]
  `tipo_componente` ENUM('materia_prima','guarnicion')
                    NOT NULL DEFAULT 'materia_prima',     -- [037]
  `codigo_display`  VARCHAR(10)    NULL,                  -- [037]
  `precio_extra`    DECIMAL(10,2)  NOT NULL DEFAULT 0.00, -- [036]
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_receta_ingrediente` (`receta_id`, `ingrediente_id`), -- [043]
  CONSTRAINT `fk_rri_rec` FOREIGN KEY (`receta_id`)      REFERENCES `rest_recetas`(`id`)      ON DELETE CASCADE,
  CONSTRAINT `fk_rri_ing` FOREIGN KEY (`ingrediente_id`) REFERENCES `rest_ingredientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Movimientos de inventario ────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_movimientos_inventario` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `ingrediente_id` INT UNSIGNED   NOT NULL,
  `tipo`           ENUM('entrada','salida','merma','ajuste') NOT NULL,
  `cantidad`       DECIMAL(10,3)  NOT NULL,
  `stock_antes`    DECIMAL(10,3)  NOT NULL,
  `stock_despues`  DECIMAL(10,3)  NOT NULL,
  `motivo`         VARCHAR(255)   NULL,
  `referencia`     VARCHAR(100)   NULL,
  `usuario_id`     INT UNSIGNED   NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rmov_rest` (`restaurante_id`),
  KEY `idx_rmov_ing`  (`ingrediente_id`),
  CONSTRAINT `fk_rmov_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rmov_ing`  FOREIGN KEY (`ingrediente_id`) REFERENCES `rest_ingredientes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Comensales (CRM del restaurante) ────────────────────────
CREATE TABLE IF NOT EXISTS `rest_comensales` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `nombre`         VARCHAR(200)   NULL,
  `telefono`       VARCHAR(20)    NULL,
  `email`          VARCHAR(150)   NULL,
  `total_visitas`  INT UNSIGNED   NOT NULL DEFAULT 0,
  `total_gastado`  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `ultima_visita`  DATETIME       NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_rcom_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Visitas (sesión QR) ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_visitas` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `mesa_id`        INT UNSIGNED   NULL,
  `comensal_id`    INT UNSIGNED   NULL,
  `qr_code`        VARCHAR(128)   NOT NULL,
  `estado`         ENUM('activa','pagando','pagada','cancelada') NOT NULL DEFAULT 'activa',
  `notas`          TEXT           NULL,
  `subtotal`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `propina`        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pagada_at`      DATETIME       NULL,
  `salida_at`      DATETIME       NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_visita_qr` (`qr_code`),
  KEY `idx_visita_rest` (`restaurante_id`),
  CONSTRAINT `fk_rvis_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rvis_mesa` FOREIGN KEY (`mesa_id`)        REFERENCES `rest_mesas`(`id`)          ON DELETE SET NULL,
  CONSTRAINT `fk_rvis_com`  FOREIGN KEY (`comensal_id`)    REFERENCES `rest_comensales`(`id`)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Pedidos de mesa ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_pedidos` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `mesa_id`        INT UNSIGNED   NULL,
  `visita_id`      INT UNSIGNED   NULL,
  `mesero_id`      INT UNSIGNED   NULL,
  `reclamado_at`   TIMESTAMP      NULL DEFAULT NULL,      -- [044]
  `reclamado_por`  INT UNSIGNED   NULL,                   -- [044]
  `folio`          VARCHAR(20)    NOT NULL,
  `estado`         ENUM('pendiente','en_preparacion','listo','reclamado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `notas`          TEXT           NULL,
  `subtotal`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total`          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_at` DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rped_rest`  (`restaurante_id`),
  KEY `idx_rped_mesa`  (`mesa_id`),
  KEY `idx_rped_vis`   (`visita_id`),
  KEY `idx_rped_est`   (`estado`),
  CONSTRAINT `fk_rped_rest`         FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rped_mesa`         FOREIGN KEY (`mesa_id`)        REFERENCES `rest_mesas`(`id`)         ON DELETE SET NULL,
  CONSTRAINT `fk_rped_vis`          FOREIGN KEY (`visita_id`)      REFERENCES `rest_visitas`(`id`)        ON DELETE SET NULL,
  CONSTRAINT `fk_rped_mes`          FOREIGN KEY (`mesero_id`)      REFERENCES `usuarios`(`id`)            ON DELETE SET NULL,
  CONSTRAINT `fk_rpedido_reclamado` FOREIGN KEY (`reclamado_por`)  REFERENCES `usuarios`(`id`)            ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Items del pedido ─────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_pedido_items` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `pedido_id`   INT UNSIGNED   NOT NULL,
  `platillo_id` INT UNSIGNED   NOT NULL,
  `cantidad`    TINYINT        NOT NULL DEFAULT 1,
  `precio_unit` DECIMAL(10,2)  NOT NULL,
  `subtotal`    DECIMAL(10,2)  NOT NULL,
  `notas`       VARCHAR(255)   NULL,
  `exclusiones` TEXT           NULL,                      -- [030]
  `extras`      TEXT           NULL
                  COMMENT 'JSON: [{ingrediente_id, nombre, precio_extra, cantidad}]', -- [036]
  `estado`      ENUM('pendiente','en_preparacion','listo','reclamado','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ritem_ped`  FOREIGN KEY (`pedido_id`)   REFERENCES `rest_pedidos`(`id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_ritem_plat` FOREIGN KEY (`platillo_id`) REFERENCES `rest_platillos`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Tickets (cuenta final) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_tickets` (
  `id`               INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id`   INT UNSIGNED   NOT NULL,
  `visita_id`        INT UNSIGNED   NOT NULL,
  `mesa_id`          INT UNSIGNED   NULL,
  `folio`            VARCHAR(20)    NOT NULL,
  `subtotal`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `propina`          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `total`            DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `estado`           ENUM('pendiente','pagado','cancelado') NOT NULL DEFAULT 'pendiente',
  `metodo_pago`      ENUM('paypal','tarjeta','transferencia','efectivo') NULL,
  `paypal_order_id`  VARCHAR(100)   NULL,
  `mesero_id`        INT            NULL,                 -- [035]
  `propina_entregada` TINYINT(1)   NOT NULL DEFAULT 0,   -- [035]
  `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `pagado_at`        DATETIME       NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rtick_rest`             (`restaurante_id`),
  KEY `idx_rtick_vis`              (`visita_id`),
  KEY `idx_tickets_mesero`         (`mesero_id`),
  KEY `idx_tickets_propina_entregada` (`propina_entregada`),
  CONSTRAINT `fk_rtick_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rtick_vis`  FOREIGN KEY (`visita_id`)      REFERENCES `rest_visitas`(`id`)       ON DELETE CASCADE,
  CONSTRAINT `fk_rtick_mesa` FOREIGN KEY (`mesa_id`)        REFERENCES `rest_mesas`(`id`)          ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Staff del restaurante ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `rest_staff` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED  NOT NULL,
  `usuario_id`     INT UNSIGNED  NOT NULL,
  `codigo`         VARCHAR(10)   NOT NULL,
  `rol_slug`       VARCHAR(20)   NOT NULL,
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  `fecha_ingreso`  DATE          NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_codigo` (`restaurante_id`, `codigo`),
  CONSTRAINT `fk_rstaff_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rstaff_usr`  FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`(`id`)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Turnos mesero ↔ zona ─────────────────────────────────────  [044]
CREATE TABLE IF NOT EXISTS `rest_mesero_turno` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED  NOT NULL,
  `usuario_id`     INT UNSIGNED  NOT NULL,
  `zona_id`        INT UNSIGNED  NOT NULL,
  `turno_fecha`    DATE          NOT NULL,
  `activo`         TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_turno_rest_fecha` (`restaurante_id`, `turno_fecha`),
  KEY `idx_turno_user`       (`usuario_id`),
  CONSTRAINT `fk_mturno_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mturno_user` FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`(`id`)          ON DELETE CASCADE,
  CONSTRAINT `fk_mturno_zona` FOREIGN KEY (`zona_id`)        REFERENCES `rest_zonas`(`id`)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Finanzas ─────────────────────────────────────────────────  [023]
CREATE TABLE IF NOT EXISTS `rest_gastos` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `categoria`      ENUM('personal','suministros','mantenimiento','servicios','propinas','devolucion','marketing','otros') NOT NULL DEFAULT 'otros',
  `descripcion`    VARCHAR(255)   NOT NULL,
  `monto`          DECIMAL(10,2)  NOT NULL,
  `fecha`          DATE           NOT NULL,
  `comprobante`    VARCHAR(255)   NULL,
  `usuario_id`     INT UNSIGNED   NOT NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rgasto_rest`  (`restaurante_id`),
  KEY `idx_rgasto_fecha` (`fecha`),
  CONSTRAINT `fk_rgasto_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rgasto_usr`  FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_retiros` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `descripcion`    VARCHAR(255)   NOT NULL,
  `monto`          DECIMAL(10,2)  NOT NULL,
  `usuario_id`     INT UNSIGNED   NOT NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rret_rest` (`restaurante_id`),
  CONSTRAINT `fk_rret_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rret_usr`  FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `rest_cortes` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED   NOT NULL,
  `turno`          VARCHAR(50)    NOT NULL DEFAULT 'General',
  `usuario_id`     INT UNSIGNED   NOT NULL,
  `ingresos`       DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `gastos`         DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `retiros`        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `propinas`       DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `utilidad_neta`  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  `notas`          TEXT           NULL,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rcorte_rest` (`restaurante_id`),
  CONSTRAINT `fk_rcorte_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rcorte_usr`  FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Reservaciones ────────────────────────────────────────────  [024]
CREATE TABLE IF NOT EXISTS `rest_reservaciones` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED  NOT NULL,
  `mesa_id`        INT UNSIGNED  NULL,
  `comensal_id`    INT UNSIGNED  NULL,
  `mesero_id`      INT UNSIGNED  NULL,                   -- [045]
  `nombre`         VARCHAR(200)  NOT NULL,
  `telefono`       VARCHAR(20)   NULL,
  `email`          VARCHAR(150)  NULL,
  `fecha`          DATE          NOT NULL,
  `hora`           TIME          NOT NULL,
  `personas`       TINYINT       NOT NULL DEFAULT 2,
  `estado`         ENUM('pendiente','confirmada','cancelada','completada') NOT NULL DEFAULT 'pendiente',
  `origen`         ENUM('restaurante','comensal') NOT NULL DEFAULT 'restaurante', -- [046]
  `notas`          TEXT          NULL,
  `created_at`     TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP, -- [045]
  PRIMARY KEY (`id`),
  KEY `idx_rres_rest`  (`restaurante_id`),
  KEY `idx_rres_fecha` (`fecha`),
  CONSTRAINT `fk_rres_rest`   FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rres_mesa`   FOREIGN KEY (`mesa_id`)        REFERENCES `rest_mesas`(`id`)         ON DELETE SET NULL,
  CONSTRAINT `fk_rres_com`    FOREIGN KEY (`comensal_id`)    REFERENCES `rest_comensales`(`id`)    ON DELETE SET NULL,
  CONSTRAINT `fk_rres_mesero` FOREIGN KEY (`mesero_id`)      REFERENCES `usuarios`(`id`)           ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ── Alertas de comensales ────────────────────────────────────  [033]
CREATE TABLE IF NOT EXISTS `rest_alertas` (
  `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `tipo`           ENUM('mesero','cuenta') NOT NULL DEFAULT 'mesero',
  `mesa_id`        INT UNSIGNED NULL,
  `visita_id`      INT UNSIGNED NULL,
  `atendida`       TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_restaurante` (`restaurante_id`),
  INDEX `idx_atendida`    (`restaurante_id`, `atendida`),
  INDEX `idx_visita`      (`visita_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Armado de platillos (KDS) ────────────────────────────────  [037]
CREATE TABLE IF NOT EXISTS `rest_platillo_armado` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `platillo_id`    INT UNSIGNED NOT NULL,
  `orden_paso`     INT UNSIGNED NOT NULL DEFAULT 1,
  `tipo`           ENUM('ingrediente','guarnicion','accion') NOT NULL DEFAULT 'accion',
  `referencia_id`  INT UNSIGNED DEFAULT NULL,
  `descripcion`    VARCHAR(255) DEFAULT NULL,
  `obligatorio`    TINYINT(1)   NOT NULL DEFAULT 1,
  `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rpa_restaurante` (`restaurante_id`),
  KEY `idx_rpa_platillo`    (`platillo_id`),
  CONSTRAINT `fk_rpa_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpa_platillo`    FOREIGN KEY (`platillo_id`)    REFERENCES `rest_platillos`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_rpa_ingrediente` FOREIGN KEY (`referencia_id`)  REFERENCES `rest_ingredientes`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Modificadores de platillos ───────────────────────────────  [037]
CREATE TABLE IF NOT EXISTS `rest_modificadores` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `nombre`         VARCHAR(120)  NOT NULL,
  `descripcion`    VARCHAR(255)  DEFAULT NULL,
  `tipo`           ENUM('extra','sin','opcion') NOT NULL DEFAULT 'opcion',
  `precio_extra`   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rm_restaurante` (`restaurante_id`),
  CONSTRAINT `fk_rm_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rest_platillo_modificador` (
  `platillo_id`    INT UNSIGNED   NOT NULL,
  `modificador_id` INT UNSIGNED   NOT NULL,
  `obligatorio`    TINYINT(1)     NOT NULL DEFAULT 0,
  `max_seleccion`  SMALLINT UNSIGNED DEFAULT 1,
  PRIMARY KEY (`platillo_id`, `modificador_id`),
  CONSTRAINT `fk_rpm_platillo`    FOREIGN KEY (`platillo_id`)    REFERENCES `rest_platillos`(`id`)    ON DELETE CASCADE,
  CONSTRAINT `fk_rpm_modificador` FOREIGN KEY (`modificador_id`) REFERENCES `rest_modificadores`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rest_pedido_item_modificadores` (
  `id`             INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `pedido_item_id` INT UNSIGNED   NOT NULL,
  `modificador_id` INT UNSIGNED   NOT NULL,
  `cantidad`       SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `precio_extra`   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rpim_item` (`pedido_item_id`),
  CONSTRAINT `fk_rpim_item`        FOREIGN KEY (`pedido_item_id`) REFERENCES `rest_pedido_items`(`id`)  ON DELETE CASCADE,
  CONSTRAINT `fk_rpim_modificador` FOREIGN KEY (`modificador_id`) REFERENCES `rest_modificadores`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Pasos de preparación (KDS modal) ────────────────────────  [037]
CREATE TABLE IF NOT EXISTS `rest_pasos_preparacion` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `restaurante_id` INT UNSIGNED NOT NULL,
  `platillo_id`    INT UNSIGNED NOT NULL,
  `orden_paso`     INT UNSIGNED NOT NULL,
  `descripcion`    TEXT         NOT NULL,
  `activo`         TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_rpp_platillo` (`platillo_id`),
  CONSTRAINT `fk_rpp_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpp_platillo`    FOREIGN KEY (`platillo_id`)    REFERENCES `rest_platillos`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- BLOQUE 3 — Forecast y pedidos sugeridos [038]
--            empresa_id: referencia suave al ID de empresa
--            en CarniHub (no hay tabla empresas_carnihub local)
--            carnihub_producto_id: referencia suave sin FK
-- ============================================================

CREATE TABLE IF NOT EXISTS `rest_pedidos_sugeridos` (
  `id`                 INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `restaurante_id`     INT UNSIGNED    NOT NULL,
  -- ID de la empresa distribuidora en CarniHub (soft ref, sin FK)
  `carnihub_empresa_id` INT UNSIGNED   NOT NULL
                          COMMENT 'ID de la empresa proveedora en el sistema CarniHub',
  `estado`             ENUM('borrador','sugerido','aprobado','rechazado','convertido')
                         NOT NULL DEFAULT 'sugerido',
  `total_estimado`     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  `notas`              TEXT            NULL,
  `usuario_id`         INT UNSIGNED    NULL,
  -- ID del pedido creado en CarniHub una vez convertido (soft ref, sin FK)
  `pedido_carnihub_id` INT UNSIGNED    NULL
                          COMMENT 'ID del pedido B2B en el sistema CarniHub una vez convertido',
  `created_at`         TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aprobado_at`        DATETIME        NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rps_restaurante` (`restaurante_id`),
  KEY `idx_rps_estado`      (`estado`),
  CONSTRAINT `fk_rps_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`),
  CONSTRAINT `fk_rps_usuario`     FOREIGN KEY (`usuario_id`)     REFERENCES `usuarios`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Pedidos de reabastecimiento sugeridos por el sistema de forecast';

CREATE TABLE IF NOT EXISTS `rest_pedido_sugerido_items` (
  `id`                   INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  `pedido_sugerido_id`   INT UNSIGNED    NOT NULL,
  `ingrediente_id`       INT UNSIGNED    NOT NULL,
  -- ID del producto en el catálogo de CarniHub (soft ref, sin FK a tabla local)
  `carnihub_producto_id` INT UNSIGNED    NULL
                           COMMENT 'ID del producto en el catálogo de CarniHub (soft ref, sin FK)',
  `cantidad_sugerida`    DECIMAL(10,3)   NOT NULL,
  `cantidad_aprobada`    DECIMAL(10,3)   NULL,
  `unidad`               VARCHAR(20)     NOT NULL DEFAULT 'kg',
  `precio_unit_estimado` DECIMAL(10,4)   NOT NULL DEFAULT 0.0000,
  `subtotal_estimado`    DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `idx_rpsi_pedido`      (`pedido_sugerido_id`),
  KEY `idx_rpsi_ingrediente` (`ingrediente_id`),
  CONSTRAINT `fk_rpsi_pedido`      FOREIGN KEY (`pedido_sugerido_id`) REFERENCES `rest_pedidos_sugeridos`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rpsi_ingrediente` FOREIGN KEY (`ingrediente_id`)     REFERENCES `rest_ingredientes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Items de los pedidos sugeridos por forecast';

-- ============================================================
-- BLOQUE 4 — Integración API con CarniHub
--            Permite que cada restaurante configure su
--            conexión al sistema CarniHub externo
-- ============================================================

CREATE TABLE IF NOT EXISTS `carnihub_api_config` (
  `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `restaurante_id`        INT UNSIGNED  NOT NULL,
  -- URL base de la API de CarniHub (ej: https://app.carnihub.mx/api/v1)
  `carnihub_url`          VARCHAR(255)  NOT NULL,
  -- Token Bearer para autenticar contra CarniHub API
  `api_key`               VARCHAR(128)  NOT NULL,
  -- ID de la empresa distribuidora en CarniHub
  `carnihub_empresa_id`   INT UNSIGNED  NULL,
  -- Nombre descriptivo de la empresa (para mostrar en UI)
  `nombre_distribuidor`   VARCHAR(200)  NULL,
  -- Webhooks: URL de callback para notificaciones de CarniHub
  `webhook_secret`        VARCHAR(64)   NULL
                            COMMENT 'Secreto para verificar webhooks entrantes de CarniHub',
  `activo`                TINYINT(1)    NOT NULL DEFAULT 1,
  `ultima_sincronizacion` DATETIME      NULL,
  `created_at`            TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP     NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_api_restaurante` (`restaurante_id`),
  CONSTRAINT `fk_api_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Configuración de la conexión API con el sistema CarniHub externo';

-- ══ Columnas de auth en usuarios (idempotente para instalaciones existentes) ══
SET @_col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'email_verificado');
SET @_sql = IF(@_col = 0, 'ALTER TABLE `usuarios` ADD COLUMN `email_verificado` TINYINT(1) NOT NULL DEFAULT 1 AFTER `activo`', 'SELECT 1');
PREPARE _s FROM @_sql; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @_col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'token_verificacion');
SET @_sql = IF(@_col = 0, 'ALTER TABLE `usuarios` ADD COLUMN `token_verificacion` VARCHAR(128) NULL', 'SELECT 1');
PREPARE _s FROM @_sql; EXECUTE _s; DEALLOCATE PREPARE _s;

SET @_col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'token_expira');
SET @_sql = IF(@_col = 0, 'ALTER TABLE `usuarios` ADD COLUMN `token_expira` DATETIME NULL', 'SELECT 1');
PREPARE _s FROM @_sql; EXECUTE _s; DEALLOCATE PREPARE _s;

-- ══ empresa_id en action_logs (idempotente) ═════════════════════════════
SET @_col = (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'empresa_id');
SET @_sql = IF(@_col = 0, 'ALTER TABLE `action_logs` ADD COLUMN `empresa_id` INT UNSIGNED NULL AFTER `rol`', 'SELECT 1');
PREPARE _s FROM @_sql; EXECUTE _s; DEALLOCATE PREPARE _s;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED: Usuario admin por defecto
-- Contraseña: Admin2024! (cambiar en producción)
-- ============================================================
-- INSERT INTO `usuarios` (`nombre`, `apellido_paterno`, `email`, `password`, `rol_id`, `activo`)
-- VALUES ('Admin', 'Local', 'admin@mirestaurante.mx',
--         '$2y$10$TKh8H1.PfcaZiGPBo1JsH.o7S3nGpXWMZuWtmE5tPk.yWn2bKxkuu', 5, 1);

