-- ============================================================
-- Esquema base — UTEQ Cafetería (base de datos nueva y vacía)
-- ============================================================
--
-- QUÉ ES ESTO:
-- Un esquema completo (sin datos de otro cliente) para levantar
-- la base de datos de UTEQ desde cero. No es una migración
-- incremental como las demás en esta carpeta (001, 002, ...) —
-- es la foto inicial. Córrelo UNA sola vez sobre una base vacía
-- recién creada.
--
-- DE DÓNDE SALE:
-- Se construyó a partir del dump de referencia que compartiste
-- (junglepi_junglepizza, un cliente distinto sobre la misma
-- plataforma), quitando todo lo de mesas/QR/dine-in, mesero,
-- anfitrión, modo social y sucursales (ver docs/PLAN_RECORTE_UTEQ.md).
--
-- OJO — UNA ADVERTENCIA IMPORTANTE:
-- Ese dump de referencia es de OTRO cliente (Jungle Pizza), no de
-- este proyecto (Amare/UTEQ). Los nombres de casi todas las tablas
-- coinciden 1:1 con lo que usa el código de apps/api-php, PERO el
-- sistema de saldo/puntos tenía nombres distintos en ese dump
-- (jungle_wallets, jungle_points_transactions, columnas
-- jungle_saldo/jungle_puntos...). Verifiqué contra
-- apps/api-php/src/Services/RewardsService.php y RewardsController.php
-- que el código de ESTE proyecto realmente usa `amare_wallets` y
-- `amare_wallet_transactions`, así que aquí ya vienen renombradas y
-- ajustadas a lo que el código actual espera. Todo lo demás (menú,
-- pedidos, direcciones, promociones, Stripe, etc.) es una
-- transcripción fiel del dump de referencia, con las tablas y
-- columnas de mesas/QR/social/sucursales ya quitadas.
--
-- Si tienes acceso a un dump real de la base actual de Amare, mejor
-- úsalo como referencia en vez de este archivo — este es la mejor
-- reconstrucción posible sin ese dump real en la mano.
--
-- QUÉ NO INCLUYE:
-- Cero filas de Jungle Pizza. Trae los 2 roles base (necesarios por
-- llave foránea) y, al final, un primer registro de
-- empresa/restaurante/usuario admin con datos INVENTADOS para poder
-- correr el script de una sola vez — revísalos y corrígelos antes de
-- producción (ver el bloque "Datos iniciales" al final del archivo).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Cuentas de plataforma / staff administrativo
-- ------------------------------------------------------------

CREATE TABLE `roles` (
  `id` TINYINT(3) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(50) NOT NULL,
  `slug` VARCHAR(50) NOT NULL,
  UNIQUE KEY `uq_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`id`, `nombre`, `slug`) VALUES
(1, 'Superadministrador', 'superadmin'),
(2, 'Admin Restaurante', 'admin_restaurante');

CREATE TABLE `empresas` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `razon_social` VARCHAR(200) NOT NULL,
  `rfc` VARCHAR(15) DEFAULT NULL,
  `tipo_negocio` ENUM('taqueria','carniceria','restaurante','comedor','otro') DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `direccion_fiscal` TEXT,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_rfc` (`rfc`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `usuarios` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `apellido_paterno` VARCHAR(100) NOT NULL,
  `apellido_materno` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(150) NOT NULL,
  `email_verificado` TINYINT(1) NOT NULL DEFAULT '0',
  `token_verificacion` VARCHAR(64) DEFAULT NULL,
  `primer_login_completado` TINYINT(1) NOT NULL DEFAULT '0',
  `password` VARCHAR(255) NOT NULL,
  `rol_id` TINYINT(3) UNSIGNED NOT NULL,
  `empresa_id` INT(10) UNSIGNED DEFAULT NULL,
  `restaurante_id` INT(10) UNSIGNED DEFAULT NULL,
  `restaurante_activo` TINYINT(1) NOT NULL DEFAULT '0',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `avatar` VARCHAR(255) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `token_expira` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_email` (`email`),
  KEY `fk_usuario_rol` (`rol_id`),
  KEY `fk_usuario_empresa` (`empresa_id`),
  KEY `idx_usuario_restaurante` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `action_logs` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `rol` VARCHAR(30) DEFAULT NULL,
  `empresa_id` INT(10) UNSIGNED DEFAULT NULL,
  `accion` VARCHAR(100) DEFAULT NULL,
  `modulo` VARCHAR(50) DEFAULT NULL,
  `descripcion` TEXT,
  `ip` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `login_intentos` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `ip` VARCHAR(45) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ip` (`ip`),
  KEY `idx_email` (`email`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `api_rate_limits` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `bucket_key` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` INT(10) UNSIGNED NOT NULL DEFAULT '1',
  `window_started_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL,
  UNIQUE KEY `uq_api_rate_limits_bucket` (`bucket_key`),
  KEY `idx_api_rate_limits_cleanup` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `moderation_actions` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `action` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `target_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `photo_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `photo_url` VARCHAR(600) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `decision` VARCHAR(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `moderator_id` BIGINT(20) UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_moderation_actions_target` (`target_type`,`target_id`,`created_at`),
  KEY `idx_moderation_actions_user` (`user_id`,`created_at`),
  KEY `idx_moderation_actions_photo` (`photo_id`,`created_at`),
  KEY `idx_moderation_actions_moderator` (`moderator_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- El restaurante (una sola sede — sin tabla `sucursales`)
-- ------------------------------------------------------------

CREATE TABLE `rest_restaurantes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `empresa_id` INT(10) UNSIGNED NOT NULL,
  `comprador_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(200) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `logo` VARCHAR(255) DEFAULT NULL,
  `imagen_banner` VARCHAR(255) DEFAULT NULL,
  `color_primario` VARCHAR(7) NOT NULL DEFAULT '#A97C3F',
  `color_secundario` VARCHAR(7) NOT NULL DEFAULT '#2B1B12',
  `descripcion` TEXT,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `direccion` TEXT,
  `lat` DECIMAL(10,8) DEFAULT NULL,
  `lng` DECIMAL(11,8) DEFAULT NULL,
  `horario_apertura` TIME DEFAULT NULL,
  `horario_cierre` TIME DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `horarios_json` TEXT,
  `menu_principal` TINYINT(1) NOT NULL DEFAULT '0',
  `app_movil_habilitada` TINYINT(1) NOT NULL DEFAULT '0',
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `fk_rrest_empresa` (`empresa_id`),
  KEY `fk_rrest_comprador` (`comprador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Columnas quitadas a propósito (mesas/QR, reservaciones, anfitrión,
-- login de comensal en mesa, multi-sucursal): mesas_habilitadas,
-- reservas_habilitadas, portero_habilitado, requiere_login_comensal,
-- exclusiones_app_habilitadas, extras_app_habilitados, sucursal_id.

CREATE TABLE `rest_configuracion` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(11) NOT NULL,
  `metodos_pago` JSON NOT NULL,
  `tipos_entrega` JSON NOT NULL,
  `costo_envio` DECIMAL(10,2) DEFAULT '0.00',
  `pedido_minimo` DECIMAL(10,2) DEFAULT '0.00',
  `exclusiones_habilitadas` TINYINT(1) NOT NULL DEFAULT '1',
  `extras_habilitados` TINYINT(1) NOT NULL DEFAULT '1',
  `config_version` BIGINT(20) UNSIGNED NOT NULL DEFAULT '1',
  `activo` TINYINT(1) DEFAULT '1',
  `facturacion_habilitada` TINYINT(1) NOT NULL DEFAULT '0',
  `facturacion_emisor_json` JSON DEFAULT NULL,
  `facturacion_email_notificacion` VARCHAR(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `restaurante_id` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_visibilidad_financiera` (
  `restaurante_id` INT(10) UNSIGNED NOT NULL PRIMARY KEY,
  `activo` TINYINT(1) NOT NULL DEFAULT '0',
  `ocultar_hasta` DATE DEFAULT NULL,
  `actualizado_por` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_rvf_activo_fecha` (`activo`,`ocultar_hasta`),
  KEY `idx_rvf_usuario` (`actualizado_por`),
  CONSTRAINT `fk_rvf_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rvf_usuario` FOREIGN KEY (`actualizado_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_visibilidad_financiera_historial` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `accion` ENUM('ocultar','restaurar') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ocultar_hasta` DATE DEFAULT NULL,
  `usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rvfh_rest_fecha` (`restaurante_id`,`created_at`),
  KEY `idx_rvfh_usuario` (`usuario_id`),
  CONSTRAINT `fk_rvfh_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rvfh_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_zonas_delivery` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `radio_km` DECIMAL(5,2) NOT NULL DEFAULT '5.00',
  `costo_envio` DECIMAL(8,2) NOT NULL DEFAULT '0.00',
  `activa` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rest_activa` (`restaurante_id`,`activa`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_comensales` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(200) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `total_visitas` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `total_gastado` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `ultima_visita` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_rcom_rest` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Caja / gastos (back-office, sin cambios de alcance)
-- ------------------------------------------------------------

CREATE TABLE `rest_gastos` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `categoria` ENUM('personal','suministros','mantenimiento','servicios','propinas','devolucion','marketing','otros') NOT NULL DEFAULT 'otros',
  `descripcion` VARCHAR(255) NOT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `fecha` DATE NOT NULL,
  `comprobante` VARCHAR(255) DEFAULT NULL,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rgasto_rest` (`restaurante_id`),
  KEY `idx_rgasto_fecha` (`fecha`),
  KEY `fk_rgasto_usr` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_retiros` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `descripcion` VARCHAR(255) NOT NULL,
  `monto` DECIMAL(10,2) NOT NULL,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rret_rest` (`restaurante_id`),
  KEY `fk_rret_usr` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_regularizaciones_adeudo` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `tipo_registro` ENUM('ticket','pedido_app') COLLATE utf8mb4_unicode_ci NOT NULL,
  `registro_id` INT(10) UNSIGNED NOT NULL,
  `folio` VARCHAR(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cliente_referencia` VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `estado_anterior` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metodo_pago` ENUM('paypal','tarjeta','transferencia','efectivo') COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` VARCHAR(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rra_rest_fecha` (`restaurante_id`,`created_at`),
  KEY `idx_rra_registro` (`tipo_registro`,`registro_id`),
  KEY `idx_rra_usuario` (`usuario_id`),
  CONSTRAINT `fk_rra_restaurante` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rra_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Menú, ingredientes, recetas
-- ------------------------------------------------------------

CREATE TABLE `rest_categorias_menu` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `imagen` VARCHAR(255) DEFAULT NULL,
  `orden` TINYINT(4) NOT NULL DEFAULT '0',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  KEY `fk_rcat_rest` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_ingredientes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'otro',
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `unidad_principal` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `unidad_compra` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `equivalencia` DECIMAL(10,4) NOT NULL DEFAULT '1.0000',
  `costo_unitario` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `stock` DECIMAL(10,3) NOT NULL DEFAULT '0.000',
  `stock_minimo` DECIMAL(10,3) NOT NULL DEFAULT '0.000',
  `categoria` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `carnihub_producto_id` INT(10) UNSIGNED DEFAULT NULL,
  `proveedor_carnihub` TINYINT(1) NOT NULL DEFAULT '0',
  `proveedor_nombre` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uk_ring_rest_codigo` (`restaurante_id`,`codigo`),
  KEY `idx_ring_carnihub_producto` (`carnihub_producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_platillos` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(20) DEFAULT NULL,
  `es_armado` TINYINT(1) NOT NULL DEFAULT '0',
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `categoria_id` INT(10) UNSIGNED DEFAULT NULL,
  `nombre` VARCHAR(200) NOT NULL,
  `descripcion` TEXT,
  `alergenos` VARCHAR(500) DEFAULT NULL,
  `contiene` TEXT,
  `precio` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `imagen` VARCHAR(255) DEFAULT NULL,
  `tiempo_preparacion_min` TINYINT(4) NOT NULL DEFAULT '15',
  `disponible` TINYINT(1) NOT NULL DEFAULT '1',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `modificadores_sincronizados_at` DATETIME DEFAULT NULL,
  `ingrediente_directo_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `fk_rplat_rest` (`restaurante_id`),
  KEY `fk_rplat_cat` (`categoria_id`),
  KEY `idx_rplat_ing_directo` (`ingrediente_directo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_platillo_armado` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `orden_paso` INT(10) UNSIGNED NOT NULL DEFAULT '1',
  `tipo` ENUM('ingrediente','guarnicion','accion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'accion',
  `referencia_id` INT(10) UNSIGNED DEFAULT NULL,
  `descripcion` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `obligatorio` TINYINT(1) NOT NULL DEFAULT '1',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rpa_restaurante` (`restaurante_id`),
  KEY `idx_rpa_platillo` (`platillo_id`),
  KEY `fk_rpa_ingrediente` (`referencia_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_pasos_preparacion` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `orden_paso` INT(10) UNSIGNED NOT NULL,
  `descripcion` TEXT COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rpp_platillo` (`platillo_id`),
  KEY `fk_rpp_restaurante` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_recetas` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `porciones_base` TINYINT(4) NOT NULL DEFAULT '1',
  `notas` TEXT,
  UNIQUE KEY `uq_receta_platillo` (`platillo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_receta_ingredientes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `receta_id` INT(10) UNSIGNED NOT NULL,
  `ingrediente_id` INT(10) UNSIGNED NOT NULL,
  `cantidad` DECIMAL(10,3) NOT NULL,
  `unidad` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'kg',
  `notas` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `es_informativo` TINYINT(1) NOT NULL DEFAULT '0',
  `precio_extra` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `tipo_componente` VARCHAR(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `codigo_display` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `fk_rri_rec` (`receta_id`),
  KEY `fk_rri_ing` (`ingrediente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_movimientos_inventario` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `ingrediente_id` INT(10) UNSIGNED NOT NULL,
  `tipo` ENUM('entrada','salida','merma','ajuste') NOT NULL,
  `cantidad` DECIMAL(10,3) NOT NULL,
  `stock_antes` DECIMAL(10,3) NOT NULL,
  `stock_despues` DECIMAL(10,3) NOT NULL,
  `motivo` VARCHAR(255) DEFAULT NULL,
  `referencia` VARCHAR(100) DEFAULT NULL,
  `usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rmov_rest` (`restaurante_id`),
  KEY `idx_rmov_ing` (`ingrediente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_modificadores` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `ingrediente_id` INT(10) UNSIGNED DEFAULT NULL,
  `nombre` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` ENUM('extra','sin','opcion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'opcion',
  `alcance` ENUM('platillo','restaurante') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platillo',
  `precio_extra` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `cantidad_unidad` DECIMAL(12,3) NOT NULL DEFAULT '1.000',
  `unidad` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pza',
  `max_seleccion_global` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '1',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rm_restaurante` (`restaurante_id`),
  KEY `idx_rm_ingrediente` (`ingrediente_id`),
  KEY `idx_rm_catalogo` (`restaurante_id`,`alcance`,`tipo`,`activo`),
  CONSTRAINT `fk_rm_ingrediente` FOREIGN KEY (`ingrediente_id`) REFERENCES `rest_ingredientes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_platillo_modificador` (
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `modificador_id` INT(10) UNSIGNED NOT NULL,
  `obligatorio` TINYINT(1) NOT NULL DEFAULT '0',
  `max_seleccion` SMALLINT(5) UNSIGNED DEFAULT '1',
  PRIMARY KEY (`platillo_id`,`modificador_id`),
  KEY `fk_rpm_modificador` (`modificador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_platillo_modificadores` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `tipo` ENUM('exclusion','extra') COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ingrediente_id` INT(10) UNSIGNED DEFAULT NULL,
  `cantidad_unidad` DECIMAL(12,3) NOT NULL DEFAULT '0.000',
  `unidad` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `max_cantidad` INT(10) UNSIGNED NOT NULL DEFAULT '1',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  KEY `idx_rpm_platillo` (`restaurante_id`,`platillo_id`,`activo`),
  KEY `idx_rpm_ingrediente` (`ingrediente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Pedidos (sin mesa/visita/mesero — solo pickup / delivery)
-- ------------------------------------------------------------

CREATE TABLE `rest_pedidos` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `folio` VARCHAR(20) NOT NULL,
  `estado` ENUM('pendiente','en_preparacion','listo','en_camino','entregado','cancelado') NOT NULL DEFAULT 'pendiente',
  `notas` TEXT,
  `subtotal` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `descuento` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `promo_code` VARCHAR(50) DEFAULT NULL,
  `total` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `amare_wallet_used_mxn` DECIMAL(12,2) DEFAULT NULL,
  `amare_discount_mxn` DECIMAL(12,2) DEFAULT NULL,
  `amare_points_redeemed` INT(10) UNSIGNED DEFAULT NULL,
  `amare_points_earned` INT(11) NOT NULL DEFAULT '0',
  `tipo_pedido` ENUM('take_out','delivery','pickup') NOT NULL DEFAULT 'pickup',
  `tipo_entrega` VARCHAR(30) DEFAULT NULL,
  `pedido_origen` VARCHAR(20) NOT NULL DEFAULT 'cliente',
  `cliente_nombre` VARCHAR(120) DEFAULT NULL,
  `comprador_telefono` VARCHAR(30) DEFAULT NULL,
  `tipo_origen` VARCHAR(20) NOT NULL DEFAULT 'menu',
  `direccion_entrega` TEXT,
  `pickup_at` DATETIME DEFAULT NULL,
  `app_cliente_id` INT(10) UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_at` DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `mobile_usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `stripe_payment_intent_id` VARCHAR(100) DEFAULT NULL,
  `metodo_pago` VARCHAR(30) DEFAULT NULL,
  `pagado_at` DATETIME DEFAULT NULL,
  `stripe_payment_status` VARCHAR(30) DEFAULT NULL,
  `stripe_payment_error` VARCHAR(500) DEFAULT NULL,
  `stripe_refunded_cents` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  `stripe_disputed_at` DATETIME DEFAULT NULL,
  `app_order_id` VARCHAR(80) DEFAULT NULL,
  UNIQUE KEY `uniq_rest_pedidos_app_order` (`restaurante_id`,`app_order_id`),
  KEY `idx_rped_rest` (`restaurante_id`),
  KEY `idx_rped_est` (`estado`),
  KEY `idx_app_cliente` (`app_cliente_id`),
  KEY `idx_tipo_origen` (`tipo_origen`),
  KEY `idx_rest_pedidos_mobile` (`restaurante_id`,`mobile_usuario_id`,`created_at`),
  KEY `idx_rest_pedidos_tipo_app` (`restaurante_id`,`tipo_origen`,`tipo_pedido`,`estado`),
  KEY `idx_rest_pedidos_stripe_status` (`stripe_payment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- `estado` es lo que alimenta el KDS de cocina — no se toca.
-- Columnas quitadas (mesa/visita/mesero/cuenta-abierta/salida QR):
-- mesa_id, visita_id, consumo_id, cuenta_abierta, mesero_id,
-- reclamado_por, reclamado_at, mesero_usuario_id, mesero_nombre,
-- salida_token, salida_qr_generado_at, salida_validado_at,
-- salida_validado_por, cerrado_por_mesero_usuario_id,
-- cerrado_por_mesero_nombre, cerrado_at.

CREATE TABLE `rest_pedido_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `pedido_id` INT(10) UNSIGNED NOT NULL,
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `origen` VARCHAR(20) NOT NULL DEFAULT 'menu',
  `cantidad` TINYINT(4) NOT NULL DEFAULT '1',
  `precio_unit` DECIMAL(10,2) NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `notas` VARCHAR(255) DEFAULT NULL,
  `extras_json` TEXT,
  `exclusiones` TEXT,
  `estado` ENUM('pendiente','en_preparacion','listo','entregado') NOT NULL DEFAULT 'pendiente',
  `extras` TEXT,
  KEY `fk_ritem_ped` (`pedido_id`),
  KEY `fk_ritem_plat` (`platillo_id`),
  KEY `idx_origen` (`origen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_pedido_item_modificadores` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `pedido_item_id` INT(10) UNSIGNED NOT NULL,
  `modificador_id` INT(10) UNSIGNED NOT NULL,
  `cantidad` SMALLINT(5) UNSIGNED NOT NULL DEFAULT '1',
  `precio_extra` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rpim_item` (`pedido_item_id`),
  KEY `fk_rpim_modificador` (`modificador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_pedidos_sugeridos` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `carnihub_empresa_id` INT(10) UNSIGNED NOT NULL,
  `estado` ENUM('borrador','sugerido','aprobado','rechazado','convertido') NOT NULL DEFAULT 'sugerido',
  `total_estimado` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `notas` TEXT,
  `usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `pedido_carnihub_id` INT(10) UNSIGNED DEFAULT NULL,
  `estado_carnihub` VARCHAR(40) DEFAULT NULL,
  `ultima_sync_carnihub` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `aprobado_at` DATETIME DEFAULT NULL,
  `monto_total` DECIMAL(10,2) DEFAULT NULL,
  `metodo_pago` VARCHAR(30) DEFAULT NULL,
  `estado_pago` ENUM('pendiente','procesando','pagado','fallido') NOT NULL DEFAULT 'pendiente',
  `pago_referencia` VARCHAR(255) DEFAULT NULL,
  `pagado_at` DATETIME DEFAULT NULL,
  KEY `idx_rps_restaurante` (`restaurante_id`),
  KEY `idx_rps_estado` (`estado`),
  KEY `fk_rps_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `rest_pedido_sugerido_items` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `pedido_sugerido_id` INT(10) UNSIGNED NOT NULL,
  `ingrediente_id` INT(10) UNSIGNED NOT NULL,
  `carnihub_producto_id` INT(10) UNSIGNED DEFAULT NULL,
  `cantidad_sugerida` DECIMAL(10,3) NOT NULL,
  `cantidad_aprobada` DECIMAL(10,3) DEFAULT NULL,
  `unidad` VARCHAR(20) NOT NULL DEFAULT 'kg',
  `precio_unit_estimado` DECIMAL(10,4) NOT NULL DEFAULT '0.0000',
  `subtotal_estimado` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  KEY `idx_rpsi_pedido` (`pedido_sugerido_id`),
  KEY `idx_rpsi_ingrediente` (`ingrediente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `carnihub_api_config` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `carnihub_url` VARCHAR(255) NOT NULL,
  `api_key` VARCHAR(128) NOT NULL,
  `carnihub_empresa_id` INT(10) UNSIGNED DEFAULT NULL,
  `nombre_distribuidor` VARCHAR(200) DEFAULT NULL,
  `webhook_secret` VARCHAR(64) DEFAULT NULL,
  `webhook_url` VARCHAR(255) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `ultima_sincronizacion` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `metodo_pago` ENUM('stripe','paypal','transferencia') NOT NULL DEFAULT 'transferencia',
  `instrucciones_transferencia` TEXT,
  `stripe_customer_id` VARCHAR(64) DEFAULT NULL,
  `stripe_payment_method_id` VARCHAR(64) DEFAULT NULL,
  `stripe_card_last4` VARCHAR(4) DEFAULT NULL,
  UNIQUE KEY `uq_api_restaurante` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Promociones / facturación
-- ------------------------------------------------------------

CREATE TABLE `rest_promociones` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `usuario_id` INT(10) UNSIGNED DEFAULT NULL,
  `titulo` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` TEXT COLLATE utf8mb4_unicode_ci,
  `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tipo` ENUM('porcentaje','monto_fijo','envio_gratis') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'porcentaje',
  `valor_descuento` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `expires_at` DATETIME DEFAULT NULL,
  `imagen` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deep_link` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_rest_prom_code` (`code`),
  KEY `idx_rest_prom_rest` (`restaurante_id`),
  KEY `idx_rest_prom_activo` (`restaurante_id`,`activo`,`fecha_inicio`,`fecha_fin`),
  KEY `fk_rest_prom_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_promocion_comensales` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `promocion_id` INT(10) UNSIGNED NOT NULL,
  `comensal_id` INT(10) UNSIGNED NOT NULL,
  `usado` TINYINT(1) NOT NULL DEFAULT '0',
  `fecha_uso` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_prom_comensal` (`promocion_id`,`comensal_id`),
  KEY `idx_prom_com_com` (`comensal_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rest_promocion_envios` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `mobile_usuario_id` INT(10) UNSIGNED NOT NULL,
  `comensal_id` INT(10) UNSIGNED DEFAULT NULL,
  `promocion_remota_id` INT(10) UNSIGNED DEFAULT NULL,
  `code` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `motivo` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'manual',
  `periodo` CHAR(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload_json` TEXT COLLATE utf8mb4_unicode_ci,
  `enviado_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_envio_periodo` (`restaurante_id`,`mobile_usuario_id`,`motivo`,`periodo`),
  KEY `idx_envio_mobile` (`mobile_usuario_id`),
  KEY `idx_envio_restaurante` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `facturacion_solicitudes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(11) NOT NULL,
  `pedido_id` INT(11) DEFAULT NULL,
  `mobile_usuario_id` INT(11) DEFAULT NULL,
  `solicitado_por_usuario_id` INT(11) DEFAULT NULL,
  `origen` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'cliente',
  `scope` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pedido',
  `monto` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `metodo_pago` VARCHAR(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `estado` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `receptor_rfc` VARCHAR(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receptor_nombre` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receptor_regimen_fiscal` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receptor_codigo_postal` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uso_cfdi` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `receptor_email` VARCHAR(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `facturapi_invoice_id` VARCHAR(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facturapi_status` VARCHAR(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `facturapi_livemode` TINYINT(1) DEFAULT NULL,
  `cfdi_uuid` VARCHAR(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pdf_url` TEXT COLLATE utf8mb4_unicode_ci,
  `xml_url` TEXT COLLATE utf8mb4_unicode_ci,
  `notas` TEXT COLLATE utf8mb4_unicode_ci,
  `facturada_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_facturacion_restaurante_estado` (`restaurante_id`,`estado`),
  KEY `idx_facturacion_pedido` (`pedido_id`),
  KEY `idx_facturacion_mobile_usuario` (`mobile_usuario_id`),
  KEY `idx_facturacion_created_at` (`created_at`),
  KEY `idx_facturacion_facturapi_invoice` (`facturapi_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Se quitaron mesa_id, consumo_id, division_id, division_cuenta_id
-- (facturación de cuenta dividida en mesa — ya no aplica).

-- ------------------------------------------------------------
-- Tienda de mercancía (store_*, aparte del menú)
-- ------------------------------------------------------------

CREATE TABLE `store_categorias` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` TEXT COLLATE utf8mb4_unicode_ci,
  `imagen` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `store_productos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `categoria_id` INT(11) NOT NULL,
  `nombre` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` TEXT COLLATE utf8mb4_unicode_ci,
  `tipo_producto` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fisico',
  `presentacion` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `precio` DECIMAL(10,2) NOT NULL,
  `imagen` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stock` INT(11) NOT NULL DEFAULT '0',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_nombre` (`nombre`),
  KEY `idx_tipo_producto` (`tipo_producto`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Cuenta del cliente en la app móvil
-- ------------------------------------------------------------

CREATE TABLE `mobile_usuarios` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` VARCHAR(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rol` ENUM('user','admin') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user',
  `telefono` VARCHAR(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fecha_nacimiento` DATE DEFAULT NULL,
  `onboarding_completed_at` DATETIME DEFAULT NULL,
  `terms_accepted_at` DATETIME DEFAULT NULL,
  `marketing_opt_in` TINYINT(1) NOT NULL DEFAULT '0',
  `foto_url` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `google_id` VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apple_id` VARCHAR(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stripe_customer_id` VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_code_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_reset_expires_at` DATETIME DEFAULT NULL,
  `password_reset_requested_at` DATETIME DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_email` (`email`),
  UNIQUE KEY `uq_google_id` (`google_id`),
  UNIQUE KEY `uq_mobile_usuarios_telefono` (`telefono`),
  UNIQUE KEY `uq_mobile_usuarios_apple_id` (`apple_id`),
  KEY `idx_mobile_usuarios_fecha_nacimiento` (`fecha_nacimiento`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- `rol` se recortó a user/admin (sin mesero/hostess — la app ya no
-- tiene esos roles). Se quitaron todas las columnas del modo social
-- (is_social_active, current_restaurante_id, mesa, social_updated_at,
-- social_consent_accepted_at, social_consent_version, edad,
-- sexualidad, genero, descripcion, intereses, que_busca,
-- redes_sociales) y el saldo/puntos denormalizados del dump de
-- referencia (jungle_saldo, jungle_puntos) — Amare los maneja aparte
-- en `amare_wallets`, ver abajo.

CREATE TABLE `mobile_direcciones` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `alias` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Casa',
  `calle` VARCHAR(300) COLLATE utf8mb4_unicode_ci NOT NULL,
  `numero` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `colonia` VARCHAR(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ciudad` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `estado_provincia` VARCHAR(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cp` VARCHAR(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lat` DECIMAL(10,8) DEFAULT NULL,
  `lng` DECIMAL(11,8) DEFAULT NULL,
  `instrucciones` TEXT COLLATE utf8mb4_unicode_ci,
  `es_principal` TINYINT(1) NOT NULL DEFAULT '0',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_usuario` (`usuario_id`),
  CONSTRAINT `fk_mobile_direcciones_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mobile_datos_fiscales` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(11) NOT NULL,
  `rfc` VARCHAR(13) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nombre_fiscal` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `regimen_fiscal` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo_postal` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uso_cfdi` VARCHAR(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` VARCHAR(190) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_mobile_datos_fiscales_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mobile_favoritos` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_fav` (`usuario_id`,`platillo_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_platillo` (`platillo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `favoritos` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(11) NOT NULL,
  `platillo_id` INT(11) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `mobile_promociones` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `producto_id` INT(10) UNSIGNED DEFAULT NULL,
  `platillo_id` INT(11) DEFAULT NULL,
  `titulo` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descripcion` TEXT COLLATE utf8mb4_unicode_ci,
  `imagen` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deep_link` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `code` VARCHAR(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_type` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_value` DECIMAL(10,2) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `expires_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` INT(11) DEFAULT NULL,
  `updated_by` INT(11) DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  `tipo_descuento` VARCHAR(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'porcentaje',
  `valor_descuento` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `scope_tipo` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'all',
  `scope_ids` TEXT COLLATE utf8mb4_unicode_ci,
  `buy_qty` INT(10) UNSIGNED DEFAULT NULL,
  `pay_qty` INT(10) UNSIGNED DEFAULT NULL,
  `min_subtotal` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `max_uses` INT(10) UNSIGNED DEFAULT NULL,
  `combinable` TINYINT(1) NOT NULL DEFAULT '0',
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_activo` (`activo`),
  KEY `idx_activo_expires` (`activo`,`expires_at`),
  KEY `idx_platillo_id` (`platillo_id`),
  KEY `idx_producto_id` (`producto_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mobile_promocion_usos` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `promocion_id` INT(11) NOT NULL,
  `usuario_id` INT(11) NOT NULL,
  `pedido_id` INT(11) DEFAULT NULL,
  `codigo` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descuento_mxn` DECIMAL(10,2) NOT NULL DEFAULT '0.00',
  `estado` VARCHAR(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'usado',
  `usado_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_mobile_promo_usage_user_promotion` (`usuario_id`,`promocion_id`),
  UNIQUE KEY `uq_mobile_promo_usage_order_promotion` (`pedido_id`,`promocion_id`),
  KEY `idx_mobile_promo_usage_user_date` (`usuario_id`,`usado_at`),
  KEY `idx_mobile_promo_usage_promotion` (`promocion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mobile_push_tokens` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(11) NOT NULL,
  `fcm_token` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `platform` VARCHAR(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `device_id` VARCHAR(190) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `enabled` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
  UNIQUE KEY `uniq_mobile_push_token_fcm` (`fcm_token`),
  UNIQUE KEY `uniq_mobile_push_device` (`device_id`),
  KEY `idx_mobile_push_tokens_usuario` (`usuario_id`,`enabled`),
  KEY `idx_mobile_push_tokens_device` (`device_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Nota: las notificaciones push están desactivadas en la app por
-- ahora (ver docs/PLAN_RECORTE_UTEQ.md) — la tabla se deja lista
-- por si se reactivan más adelante.

CREATE TABLE `mobile_notification_logs` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `promotion_id` INT(10) UNSIGNED DEFAULT NULL,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `fcm_token_id` INT(10) UNSIGNED DEFAULT NULL,
  `fcm_token` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'fcm',
  `status` ENUM('pending','sent','failed','skipped') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `title` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` TEXT COLLATE utf8mb4_unicode_ci,
  `response` TEXT COLLATE utf8mb4_unicode_ci,
  `error` TEXT COLLATE utf8mb4_unicode_ci,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_mobile_notification_promotion` (`promotion_id`),
  KEY `idx_mobile_notification_usuario` (`usuario_id`),
  KEY `idx_mobile_notification_status` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `mobile_sesiones` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `token_hash` CHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `device_info` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` ENUM('ios','android','web') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `ultimo_uso` TIMESTAMP NULL DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_token_hash` (`token_hash`),
  KEY `idx_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `direcciones` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `usuario_id` INT(11) NOT NULL,
  `alias` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `calle` VARCHAR(200) COLLATE utf8_unicode_ci DEFAULT NULL,
  `numero` VARCHAR(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `colonia` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ciudad` VARCHAR(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `lat` DECIMAL(10,7) DEFAULT NULL,
  `lng` DECIMAL(10,7) DEFAULT NULL,
  `cp` VARCHAR(10) COLLATE utf8_unicode_ci DEFAULT NULL,
  `es_principal` TINYINT(4) DEFAULT '0',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

CREATE TABLE `app_clientes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `google_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apple_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nombre` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefono` VARCHAR(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_url` VARCHAR(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_google_id` (`google_id`),
  UNIQUE KEY `uq_apple_id` (`apple_id`),
  KEY `idx_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_tokens` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `cliente_id` INT(10) UNSIGNED NOT NULL,
  `token_hash` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `refresh_hash` VARCHAR(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_token_hash` (`token_hash`),
  KEY `idx_refresh_hash` (`refresh_hash`),
  KEY `idx_expires` (`expires_at`),
  KEY `fk_app_tokens_cliente` (`cliente_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_modificadores` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` ENUM('radio','checkbox') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'radio',
  `requerido` TINYINT(1) NOT NULL DEFAULT '0',
  `min_selecciones` TINYINT(4) NOT NULL DEFAULT '0',
  `max_selecciones` TINYINT(4) NOT NULL DEFAULT '1',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_appmod_rest` (`restaurante_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_opciones_modificador` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `modificador_id` INT(10) UNSIGNED NOT NULL,
  `nombre` VARCHAR(150) COLLATE utf8mb4_unicode_ci NOT NULL,
  `precio_extra` DECIMAL(8,2) NOT NULL DEFAULT '0.00',
  `activo` TINYINT(1) NOT NULL DEFAULT '1',
  KEY `idx_appopmod_mod` (`modificador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `app_platillo_modificadores` (
  `platillo_id` INT(10) UNSIGNED NOT NULL,
  `modificador_id` INT(10) UNSIGNED NOT NULL,
  `orden` TINYINT(4) NOT NULL DEFAULT '0',
  PRIMARY KEY (`platillo_id`,`modificador_id`),
  KEY `idx_appplatmod_mod` (`modificador_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Saldo y puntos (Saldo Amare) — oculto en la app, tablas listas
-- ------------------------------------------------------------
-- Nombres verificados contra apps/api-php/src/Services/RewardsService.php
-- (no vienen del dump de Jungle Pizza — ese usaba jungle_wallets /
-- jungle_points_transactions, con nombres distintos).

CREATE TABLE `amare_wallets` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `balance_mxn` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `purchased_balance_mxn` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `promotional_balance_mxn` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `points` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `simulated_balance` TINYINT(1) NOT NULL DEFAULT '0',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_amare_wallet_user` (`user_id`),
  CONSTRAINT `fk_amare_wallet_user` FOREIGN KEY (`user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `amare_wallet_transactions` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `wallet_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `user_id` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `type` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'wallet_payment',
  `funding_type` VARCHAR(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `context` VARCHAR(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_type` VARCHAR(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` INT(10) UNSIGNED DEFAULT NULL,
  `amount_mxn` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `points_delta` INT(11) NOT NULL DEFAULT '0',
  `balance_after_mxn` DECIMAL(12,2) NOT NULL DEFAULT '0.00',
  `points_after` INT(10) UNSIGNED NOT NULL DEFAULT '0',
  `description` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata_json` TEXT COLLATE utf8mb4_unicode_ci,
  `external_reference` VARCHAR(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_wallet_transactions_wallet` (`wallet_id`,`created_at`),
  KEY `idx_wallet_transactions_user` (`user_id`,`created_at`),
  KEY `idx_wallet_transactions_reference` (`reference_type`,`reference_id`),
  KEY `idx_wallet_transactions_external_reference` (`external_reference`),
  CONSTRAINT `fk_amare_wallet_tx_user` FOREIGN KEY (`user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- No se incluyen jungle_points_transactions ni jungle_wallet_topups:
-- no aparecen referenciadas en el código actual de apps/api-php: el
-- ledger de puntos vive dentro de amare_wallet_transactions
-- (points_delta/points_after) y no hay flujo de "recarga" de saldo
-- confirmado en el código. Si tu backend real sí tiene un flujo de
-- recarga con Stripe, avísame y te preparo esa tabla también.

-- ------------------------------------------------------------
-- Stripe (auditoría, reembolsos, webhooks)
-- ------------------------------------------------------------

CREATE TABLE `stripe_charge_refund_state` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `stripe_charge_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_intent_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `refunded_cents` BIGINT(20) UNSIGNED NOT NULL DEFAULT '0',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_stripe_refund_charge` (`stripe_charge_id`),
  KEY `idx_stripe_refund_user` (`user_id`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stripe_payment_incidents` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `incident_type` VARCHAR(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stripe_object_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_intent_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `details_json` TEXT COLLATE utf8mb4_unicode_ci,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_stripe_payment_incident` (`incident_type`,`stripe_object_id`),
  KEY `idx_stripe_payment_incident_intent` (`payment_intent_id`),
  KEY `idx_stripe_payment_incident_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stripe_pending_invoice_requests` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT(10) UNSIGNED NOT NULL,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `request_json` JSON NOT NULL,
  `status` VARCHAR(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `invoice_request_id` INT(10) UNSIGNED DEFAULT NULL,
  `last_error` TEXT COLLATE utf8mb4_unicode_ci,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  `processed_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_stripe_pending_invoice_order` (`order_id`),
  KEY `idx_stripe_pending_invoice_status` (`status`,`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stripe_refund_audit` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `stripe_refund_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payment_intent_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` INT(10) UNSIGNED NOT NULL,
  `admin_user_id` INT(10) UNSIGNED DEFAULT NULL,
  `request_key` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount_mxn` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` VARCHAR(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_stripe_refund_audit_refund` (`stripe_refund_id`),
  KEY `idx_stripe_refund_audit_user` (`user_id`,`created_at`),
  KEY `idx_stripe_refund_audit_request` (`request_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stripe_webhook_events` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `stripe_event_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `event_type` VARCHAR(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `object_id` VARCHAR(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` VARCHAR(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'processing',
  `attempts` INT(10) UNSIGNED NOT NULL DEFAULT '1',
  `last_error` TEXT COLLATE utf8mb4_unicode_ci,
  `received_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME DEFAULT NULL,
  `updated_at` DATETIME DEFAULT NULL,
  UNIQUE KEY `uq_stripe_webhook_event` (`stripe_event_id`),
  KEY `idx_stripe_webhook_status` (`status`,`updated_at`),
  KEY `idx_stripe_webhook_object` (`object_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Configuración global (valores por defecto para UTEQ)
-- ------------------------------------------------------------

CREATE TABLE `global_settings` (
  `clave` VARCHAR(100) NOT NULL PRIMARY KEY,
  `valor` TEXT,
  `tipo` ENUM('text','number','boolean','json','color','password') NOT NULL DEFAULT 'text',
  `grupo` VARCHAR(50) DEFAULT NULL,
  `etiqueta` VARCHAR(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `global_settings` (`clave`, `valor`, `tipo`, `grupo`, `etiqueta`) VALUES
('app_background_color', '#FAECE0', 'color', 'estilos', 'Fondo de app'),
('app_button_color', '#A97C3F', 'color', 'estilos', 'Botones de app'),
('app_button_text_color', '#2B1B12', 'color', 'estilos', 'Texto de botones de app'),
('color_primario', '#A97C3F', 'color', 'estilos', 'Color primario'),
('color_secundario', '#2B1B12', 'color', 'estilos', 'Color secundario'),
('costo_envio_app', '0.00', 'text', 'pagos', NULL),
('metodos_pago_app_habilitados', '["card","cash"]', 'text', 'pagos', NULL),
('metodos_pago_habilitados', '["efectivo","tarjeta"]', 'text', 'pagos', NULL),
('notif_email_pago', '0', 'text', 'pagos', NULL),
('notif_email_pago_destino', '', 'text', 'pagos', NULL),
('pedido_minimo_app', '0.00', 'text', 'pagos', NULL),
('stripe_public_key', '', 'text', 'pagos', NULL),
('tipos_entrega_habilitados', '["delivery","pickup"]', 'text', 'pagos', NULL);
-- Colores tomados del logo de UTEQ (crema/espresso/dorado). Ajusta
-- a gusto — son solo valores por defecto para que la app no arranque
-- en blanco.

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Triggers — mantienen rest_configuracion.config_version al día
-- cuando cambian modificadores o recetas (la app lo usa para saber
-- cuándo refrescar el menú en el dispositivo).
-- ============================================================

DELIMITER $$
CREATE TRIGGER `trg_config_version_modifier_delete` AFTER DELETE ON `rest_platillo_modificadores` FOR EACH ROW BEGIN
  UPDATE rest_configuracion SET config_version=config_version+1, updated_at=NOW() WHERE restaurante_id=OLD.restaurante_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_config_version_modifier_insert` AFTER INSERT ON `rest_platillo_modificadores` FOR EACH ROW BEGIN
  UPDATE rest_configuracion SET config_version=config_version+1, updated_at=NOW() WHERE restaurante_id=NEW.restaurante_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_config_version_modifier_update` AFTER UPDATE ON `rest_platillo_modificadores` FOR EACH ROW BEGIN
  UPDATE rest_configuracion SET config_version=config_version+1, updated_at=NOW() WHERE restaurante_id=NEW.restaurante_id;
END
$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `trg_config_version_recipe_delete` AFTER DELETE ON `rest_recetas` FOR EACH ROW BEGIN
  UPDATE rest_configuracion c JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE p.id=OLD.platillo_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_config_version_recipe_insert` AFTER INSERT ON `rest_recetas` FOR EACH ROW BEGIN
  UPDATE rest_configuracion c JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE p.id=NEW.platillo_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_config_version_recipe_update` AFTER UPDATE ON `rest_recetas` FOR EACH ROW BEGIN
  UPDATE rest_configuracion c JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE p.id=NEW.platillo_id;
END
$$
DELIMITER ;

DELIMITER $$
CREATE TRIGGER `trg_config_version_recipe_item_delete` AFTER DELETE ON `rest_receta_ingredientes` FOR EACH ROW BEGIN
  UPDATE rest_configuracion c
  JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  JOIN rest_recetas r ON r.platillo_id=p.id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE r.id=OLD.receta_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_config_version_recipe_item_insert` AFTER INSERT ON `rest_receta_ingredientes` FOR EACH ROW BEGIN
  UPDATE rest_configuracion c
  JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  JOIN rest_recetas r ON r.platillo_id=p.id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE r.id=NEW.receta_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `trg_config_version_recipe_item_update` AFTER UPDATE ON `rest_receta_ingredientes` FOR EACH ROW BEGIN
  UPDATE rest_configuracion c
  JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  JOIN rest_recetas r ON r.platillo_id=p.id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE r.id=NEW.receta_id;
END
$$
DELIMITER ;

-- ============================================================
-- Datos iniciales — UTEQ Cafetería
-- ============================================================
-- OJO: los valores marcados "INVENTADO" abajo son placeholders
-- plausibles para poder correr esto de una vez, NO datos reales de
-- UTEQ. Antes de dejarlo en producción, como mínimo cambia:
--   1) empresas.email / telefono / rfc  → datos fiscales reales
--   2) rest_restaurantes.direccion/lat/lng → ubicación real del campus
--   3) usuarios.email del admin → un correo que exista de verdad
--   4) La contraseña del admin (ver abajo) → es temporal, cámbiala
--      en el primer login.

INSERT INTO `empresas` (`razon_social`, `rfc`, `tipo_negocio`, `email`, `telefono`, `direccion_fiscal`, `activo`) VALUES
('UTEQ Cafetería', NULL, 'comedor', 'contacto@uteqcafeteria.com', '4421234567', 'Universidad Tecnológica de Querétaro, Av. Pie de la Cuesta 2501, Col. Nacional, 76148 Santiago de Querétaro, Qro.', 1);
-- INVENTADO: rfc, email, telefono, direccion_fiscal.

INSERT INTO `rest_restaurantes` (`empresa_id`, `comprador_id`, `nombre`, `slug`, `descripcion`, `telefono`, `direccion`, `horario_apertura`, `horario_cierre`, `activo`, `menu_principal`, `app_movil_habilitada`) VALUES
(1, 1, 'UTEQ Cafetería', 'uteq-cafeteria', 'Cafetería del campus de la Universidad Tecnológica de Querétaro.', '4421234567', 'Universidad Tecnológica de Querétaro, Av. Pie de la Cuesta 2501, Col. Nacional, 76148 Santiago de Querétaro, Qro.', '07:00:00', '19:00:00', 1, 1, 1);
-- `comprador_id` = 1 asume que el INSERT de `usuarios` de abajo corre
-- después y le toca id 1; si tu base no está vacía, ajusta este valor
-- al id real del usuario dueño. INVENTADO: telefono, direccion (sin
-- lat/lng — agrégalas si vas a usar mapas/zonas de delivery).

INSERT INTO `rest_configuracion` (`restaurante_id`, `metodos_pago`, `tipos_entrega`, `costo_envio`, `pedido_minimo`) VALUES
(1, '["card","cash"]', '["pickup","delivery"]', 0.00, 0.00);

-- Password temporal: "CambiaEstaClave2026!" — cámbiala en cuanto
-- entres por primera vez. Hash bcrypt real (compatible con
-- password_verify() de PHP), no un placeholder de relleno.
INSERT INTO `usuarios` (`nombre`, `apellido_paterno`, `apellido_materno`, `email`, `email_verificado`, `primer_login_completado`, `password`, `rol_id`, `empresa_id`, `restaurante_id`, `restaurante_activo`, `activo`) VALUES
('Admin', 'UTEQ', NULL, 'admin@uteqcafeteria.com', 1, 0, '$2b$10$fwldmCagKlVlrdwqWSj1l.C.QO706K4nJPr1TMY62YGuMgtp2hWzy', 2, 1, 1, 1, 1);
-- INVENTADO: email — reemplázalo por el correo real del admin antes
-- de correr esto. `primer_login_completado` = 0 a propósito, para
-- que el flujo de "cambiar contraseña en primer acceso" (si tu app
-- lo tiene) se dispare.
