-- Operacion de meseros para cuentas en mesa.
-- Adaptada a amareres_amare_club.sql:
-- - La BD ya tiene rest_staff con rol_slug = 'mesero'.
-- - mobile_usuarios.rol originalmente solo permite user/admin.
-- - rest_mesas.estado usa disponible/ocupada/reservada/pagando.
-- MySQL 5.7 compatible e idempotente.

SET @db_name = DATABASE();

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'mobile_usuarios'
           AND COLUMN_NAME = 'rol'
           AND COLUMN_TYPE NOT LIKE '%mesero%'
    ),
    'ALTER TABLE `mobile_usuarios` MODIFY COLUMN `rol` ENUM(''user'',''admin'',''mesero'') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''user''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Tabla de compatibilidad para instalaciones que no tengan rest_staff.
-- En esta BD se rellena desde rest_staff y el backend prefiere rest_staff.
CREATE TABLE IF NOT EXISTS `rest_staff_restaurantes` (
  `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` INT(10) UNSIGNED NOT NULL,
  `restaurante_id` INT(10) UNSIGNED NOT NULL,
  `rol_operativo` VARCHAR(40) NOT NULL DEFAULT 'mesero',
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_rsr_usuario_restaurante_rol` (`usuario_id`, `restaurante_id`, `rol_operativo`),
  KEY `idx_rsr_usuario_activo` (`usuario_id`, `activo`),
  KEY `idx_rsr_restaurante_activo` (`restaurante_id`, `activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_staff'
    )
    AND NOT EXISTS(
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_staff'
           AND INDEX_NAME = 'idx_rest_staff_mesero_lookup'
    ),
    'ALTER TABLE `rest_staff` ADD INDEX `idx_rest_staff_mesero_lookup` (`usuario_id`, `restaurante_id`, `rol_slug`, `activo`)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_staff'
    ),
    'INSERT INTO `rest_staff_restaurantes` (`usuario_id`, `restaurante_id`, `rol_operativo`, `activo`, `created_at`)
     SELECT `usuario_id`, `restaurante_id`, ''mesero'', `activo`, `created_at`
       FROM `rest_staff`
      WHERE `rol_slug` = ''mesero''
     ON DUPLICATE KEY UPDATE `activo` = VALUES(`activo`), `updated_at` = NOW()',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Los usuarios que ya estan registrados como meseros en rest_staff entran al panel de mesero.
SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_staff'
    ),
    'UPDATE `mobile_usuarios` mu
       JOIN `rest_staff` rs ON rs.`usuario_id` = mu.`id`
        AND rs.`rol_slug` = ''mesero''
        AND rs.`activo` = 1
        SET mu.`rol` = ''mesero''
      WHERE mu.`rol` <> ''admin''',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesas'
           AND COLUMN_NAME = 'mesero_usuario_id'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_mesas` ADD COLUMN `mesero_usuario_id` INT(10) UNSIGNED NULL AFTER `created_at`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesas'
           AND COLUMN_NAME = 'mesero_nombre'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_mesas` ADD COLUMN `mesero_nombre` VARCHAR(120) NULL AFTER `mesero_usuario_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesas'
           AND COLUMN_NAME = 'cliente_nombre'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_mesas` ADD COLUMN `cliente_nombre` VARCHAR(120) NULL AFTER `mesero_nombre`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesas'
           AND COLUMN_NAME = 'reclamada_at'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_mesas` ADD COLUMN `reclamada_at` DATETIME NULL AFTER `cliente_nombre`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'pedido_origen'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `pedido_origen` VARCHAR(20) NOT NULL DEFAULT ''cliente'' AFTER `tipo_pedido`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'mesero_usuario_id'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `mesero_usuario_id` INT(10) UNSIGNED NULL AFTER `pedido_origen`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'mesero_nombre'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `mesero_nombre` VARCHAR(120) NULL AFTER `mesero_usuario_id`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND COLUMN_NAME = 'cliente_nombre'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD COLUMN `cliente_nombre` VARCHAR(120) NULL AFTER `mesero_nombre`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_mesas'
           AND INDEX_NAME = 'idx_rest_mesas_mesero'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_mesas` ADD INDEX `idx_rest_mesas_mesero` (`mesero_usuario_id`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(
    EXISTS(
        SELECT 1
          FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = @db_name
           AND TABLE_NAME = 'rest_pedidos'
           AND INDEX_NAME = 'idx_rest_pedidos_mesero'
    ),
    'SELECT 1',
    'ALTER TABLE `rest_pedidos` ADD INDEX `idx_rest_pedidos_mesero` (`mesero_usuario_id`, `pedido_origen`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
