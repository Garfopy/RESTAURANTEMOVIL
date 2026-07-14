-- Normalizacion y limpieza conservadora de base de datos.
-- - Consolida `direcciones` legacy hacia `mobile_direcciones`.
-- - Mantiene `mobile_direcciones` como tabla oficial para la app movil.
-- - Renombra tablas legacy `app_%` como respaldo, sin eliminarlas.
-- Compatible con MySQL 5.7.

SET @db_name = DATABASE();

DROP PROCEDURE IF EXISTS sp_048_normalize_database_cleanup;

DELIMITER $$

CREATE PROCEDURE sp_048_normalize_database_cleanup()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_orphans INT DEFAULT 0;
  DECLARE v_table_name VARCHAR(255);
  DECLARE v_legacy_name VARCHAR(255);
  DECLARE v_sql TEXT;

  DECLARE app_tables CURSOR FOR
    SELECT TABLE_NAME
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_TYPE = 'BASE TABLE'
       AND TABLE_NAME LIKE 'app\_%' ESCAPE '\\';

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  -- 1. Asegurar estructura base de mobile_direcciones.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_direcciones';

  IF v_exists > 0 THEN
    ALTER TABLE `mobile_direcciones`
      CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME = 'estado_provincia';
    IF v_exists = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD COLUMN `estado_provincia` VARCHAR(100) NULL AFTER `ciudad`;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME = 'instrucciones';
    IF v_exists = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD COLUMN `instrucciones` TEXT NULL AFTER `lng`;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME = 'es_principal';
    IF v_exists = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD COLUMN `es_principal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `instrucciones`;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME = 'activo';
    IF v_exists = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `es_principal`;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME = 'created_at';
    IF v_exists = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `activo`;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME = 'updated_at';
    IF v_exists = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD COLUMN `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`;
    END IF;
  END IF;

  -- 2. Migrar datos utiles desde `direcciones` si la tabla legacy existe.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('direcciones', 'mobile_direcciones', 'mobile_usuarios');

  IF v_exists = 3 THEN
    INSERT INTO `mobile_direcciones` (
      `usuario_id`, `alias`, `calle`, `numero`, `colonia`, `ciudad`,
      `estado_provincia`, `cp`, `lat`, `lng`, `instrucciones`,
      `es_principal`, `activo`, `created_at`, `updated_at`
    )
    SELECT
      d.`usuario_id`,
      COALESCE(NULLIF(d.`alias`, ''), 'Direccion') AS `alias`,
      COALESCE(d.`calle`, '') AS `calle`,
      NULLIF(d.`numero`, '') AS `numero`,
      NULLIF(d.`colonia`, '') AS `colonia`,
      COALESCE(d.`ciudad`, '') AS `ciudad`,
      NULL AS `estado_provincia`,
      NULLIF(d.`cp`, '') AS `cp`,
      d.`lat`,
      d.`lng`,
      NULL AS `instrucciones`,
      IFNULL(d.`es_principal`, 0) AS `es_principal`,
      1 AS `activo`,
      COALESCE(d.`created_at`, NOW()) AS `created_at`,
      NULL AS `updated_at`
    FROM `direcciones` d
    INNER JOIN `mobile_usuarios` u ON u.`id` = d.`usuario_id`
    WHERE NOT EXISTS (
      SELECT 1
        FROM `mobile_direcciones` md
       WHERE md.`usuario_id` = d.`usuario_id`
         AND COALESCE(md.`calle`, '') = COALESCE(d.`calle`, '')
         AND COALESCE(md.`numero`, '') = COALESCE(d.`numero`, '')
         AND COALESCE(md.`cp`, '') = COALESCE(d.`cp`, '')
         AND md.`activo` = 1
    );
  END IF;

  -- 3. Reparar direcciones principales: maximo una principal activa por usuario.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_direcciones';

  IF v_exists > 0 THEN
    DROP TEMPORARY TABLE IF EXISTS tmp_048_principales;
    CREATE TEMPORARY TABLE tmp_048_principales AS
    SELECT `usuario_id`, MAX(`id`) AS `keep_id`
      FROM `mobile_direcciones`
     WHERE `activo` = 1
       AND `es_principal` = 1
     GROUP BY `usuario_id`;

    UPDATE `mobile_direcciones` md
    LEFT JOIN tmp_048_principales tp ON tp.`keep_id` = md.`id`
       SET md.`es_principal` = 0,
           md.`updated_at` = NOW()
     WHERE md.`activo` = 1
       AND md.`es_principal` = 1
       AND tp.`keep_id` IS NULL;

    DROP TEMPORARY TABLE IF EXISTS tmp_048_principales;

    DROP TEMPORARY TABLE IF EXISTS tmp_048_default_principal;
    CREATE TEMPORARY TABLE tmp_048_default_principal AS
    SELECT md.`usuario_id`, MAX(md.`id`) AS `keep_id`
      FROM `mobile_direcciones` md
      LEFT JOIN (
        SELECT `usuario_id`
          FROM `mobile_direcciones`
         WHERE `activo` = 1
           AND `es_principal` = 1
         GROUP BY `usuario_id`
      ) p ON p.`usuario_id` = md.`usuario_id`
     WHERE md.`activo` = 1
       AND p.`usuario_id` IS NULL
     GROUP BY md.`usuario_id`;

    UPDATE `mobile_direcciones` md
    INNER JOIN tmp_048_default_principal tdp ON tdp.`keep_id` = md.`id`
       SET md.`es_principal` = 1,
           md.`updated_at` = NOW();

    DROP TEMPORARY TABLE IF EXISTS tmp_048_default_principal;
  END IF;

  -- 4. Crear indice compuesto para consultas de direcciones activas por usuario.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_direcciones'
     AND INDEX_NAME = 'idx_mobile_direcciones_usuario_activo_principal';

  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_exists
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones';

    IF v_exists > 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD INDEX `idx_mobile_direcciones_usuario_activo_principal`
        (`usuario_id`, `activo`, `es_principal`, `created_at`);
    END IF;
  END IF;

  -- 5. Validar/agregar FK a mobile_usuarios si no existe y no hay huerfanos.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_direcciones'
     AND COLUMN_NAME = 'usuario_id'
     AND REFERENCED_TABLE_NAME = 'mobile_usuarios'
     AND REFERENCED_COLUMN_NAME = 'id';

  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_exists
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME IN ('mobile_direcciones', 'mobile_usuarios');

    IF v_exists = 2 THEN
      SELECT COUNT(*) INTO v_orphans
        FROM `mobile_direcciones` md
        LEFT JOIN `mobile_usuarios` u ON u.`id` = md.`usuario_id`
       WHERE u.`id` IS NULL;

      SELECT COUNT(*) INTO v_exists
        FROM information_schema.TABLE_CONSTRAINTS
       WHERE CONSTRAINT_SCHEMA = DATABASE()
         AND CONSTRAINT_NAME = 'fk_mobile_direcciones_usuario'
         AND CONSTRAINT_TYPE = 'FOREIGN KEY';

      IF v_orphans = 0 AND v_exists = 0 THEN
        ALTER TABLE `mobile_direcciones`
          ADD CONSTRAINT `fk_mobile_direcciones_usuario`
          FOREIGN KEY (`usuario_id`) REFERENCES `mobile_usuarios` (`id`)
          ON DELETE CASCADE;
      END IF;
    END IF;
  END IF;

  -- 6. Respaldar tabla legacy `direcciones` si sigue existiendo.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'direcciones';

  IF v_exists > 0 THEN
    SELECT COUNT(*) INTO v_exists
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'legacy_direcciones_20260714';

    IF v_exists = 0 THEN
      RENAME TABLE `direcciones` TO `legacy_direcciones_20260714`;
    END IF;
  END IF;

  -- 7. Respaldar todas las tablas legacy con prefijo app_.
  OPEN app_tables;

  app_loop: LOOP
    FETCH app_tables INTO v_table_name;
    IF done = 1 THEN
      LEAVE app_loop;
    END IF;

    SET v_legacy_name = CONCAT('legacy_', v_table_name, '_20260714');

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = v_legacy_name;

    IF v_exists = 0 THEN
      SET v_sql = CONCAT(
        'RENAME TABLE `',
        REPLACE(v_table_name, '`', '``'),
        '` TO `',
        REPLACE(v_legacy_name, '`', '``'),
        '`'
      );
      SET @sp048_sql = v_sql;
      PREPARE stmt FROM @sp048_sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END LOOP;

  CLOSE app_tables;
END$$

DELIMITER ;

CALL sp_048_normalize_database_cleanup();

DROP PROCEDURE IF EXISTS sp_048_normalize_database_cleanup;
