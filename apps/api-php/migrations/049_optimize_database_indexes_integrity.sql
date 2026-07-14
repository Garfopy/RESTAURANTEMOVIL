-- Optimizacion de indices, integridad y limpieza reversible.
-- Ejecutar despues de 048_normalize_database_cleanup.sql.
-- Compatible con MySQL 5.7.

SET @db_name = DATABASE();

DROP PROCEDURE IF EXISTS sp_049_optimize_database_indexes_integrity;

DELIMITER $$

CREATE PROCEDURE sp_049_optimize_database_indexes_integrity()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE v_exists INT DEFAULT 0;
  DECLARE v_columns INT DEFAULT 0;
  DECLARE v_orphans INT DEFAULT 0;
  DECLARE v_table_name VARCHAR(255);
  DECLARE v_legacy_name VARCHAR(255);
  DECLARE v_sql TEXT;

  DECLARE active_tables CURSOR FOR
    SELECT 'mobile_direcciones' UNION ALL
    SELECT 'mobile_favoritos' UNION ALL
    SELECT 'mobile_push_tokens' UNION ALL
    SELECT 'mobile_promociones' UNION ALL
    SELECT 'mobile_usuarios' UNION ALL
    SELECT 'mobile_datos_fiscales' UNION ALL
    SELECT 'rest_pedidos' UNION ALL
    SELECT 'rest_pedido_items' UNION ALL
    SELECT 'rest_mesas' UNION ALL
    SELECT 'social_gift_orders' UNION ALL
    SELECT 'social_account_notifications' UNION ALL
    SELECT 'social_likes' UNION ALL
    SELECT 'amare_wallets' UNION ALL
    SELECT 'amare_wallet_transactions';

  DECLARE empty_legacy_tables CURSOR FOR
    SELECT 'mobile_sesiones' UNION ALL
    SELECT 'amare_branch_menu_modifiers' UNION ALL
    SELECT 'amare_points_transactions' UNION ALL
    SELECT 'rest_zonas_delivery' UNION ALL
    SELECT 'rest_pasos_preparacion' UNION ALL
    SELECT 'rest_platillo_armado' UNION ALL
    SELECT 'rest_promociones' UNION ALL
    SELECT 'rest_promocion_comensales';

  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;

  -- 1. Unificar charset/collation en tablas activas criticas.
  SET done = 0;
  OPEN active_tables;
  active_loop: LOOP
    FETCH active_tables INTO v_table_name;
    IF done = 1 THEN
      LEAVE active_loop;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = v_table_name;

    IF v_exists > 0 THEN
      SET v_sql = CONCAT(
        'ALTER TABLE `',
        REPLACE(v_table_name, '`', '``'),
        '` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
      );
      SET @sp049_sql = v_sql;
      PREPARE stmt FROM @sp049_sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;
    END IF;
  END LOOP;
  CLOSE active_tables;

  -- 2. Normalizar tipo transaccional de amare_wallet_transactions.id.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'amare_wallet_transactions'
     AND COLUMN_NAME = 'id'
     AND COLUMN_TYPE NOT LIKE 'bigint%unsigned%';

  IF v_exists > 0 THEN
    ALTER TABLE `amare_wallet_transactions`
      MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT;
  END IF;

  -- 3. Migrar favoritos legacy hacia mobile_favoritos y archivar tabla vieja.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME IN ('favoritos', 'mobile_favoritos', 'mobile_usuarios', 'rest_platillos');

  IF v_exists = 4 THEN
    INSERT INTO `mobile_favoritos` (`usuario_id`, `platillo_id`, `created_at`)
    SELECT f.`usuario_id`, f.`platillo_id`, COALESCE(f.`created_at`, NOW())
      FROM `favoritos` f
      INNER JOIN `mobile_usuarios` u ON u.`id` = f.`usuario_id`
      INNER JOIN `rest_platillos` p ON p.`id` = f.`platillo_id`
     WHERE NOT EXISTS (
       SELECT 1
         FROM `mobile_favoritos` mf
        WHERE mf.`usuario_id` = f.`usuario_id`
          AND mf.`platillo_id` = f.`platillo_id`
     );

    SELECT COUNT(*) INTO v_orphans
      FROM `favoritos` f
      INNER JOIN `mobile_usuarios` u ON u.`id` = f.`usuario_id`
      INNER JOIN `rest_platillos` p ON p.`id` = f.`platillo_id`
     WHERE NOT EXISTS (
       SELECT 1
         FROM `mobile_favoritos` mf
        WHERE mf.`usuario_id` = f.`usuario_id`
          AND mf.`platillo_id` = f.`platillo_id`
     );

    IF v_orphans = 0 THEN
      SELECT COUNT(*) INTO v_exists
        FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = 'legacy_favoritos_20260714';

      IF v_exists = 0 THEN
        RENAME TABLE `favoritos` TO `legacy_favoritos_20260714`;
      END IF;
    END IF;
  END IF;

  -- 4. Desactivar tokens FCM duplicados y dejar un unico indice UNIQUE canonico.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_push_tokens';

  IF v_exists > 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_push_tokens'
       AND COLUMN_NAME IN ('id', 'fcm_token', 'enabled', 'updated_at');

    IF v_columns = 4 THEN
      DROP TEMPORARY TABLE IF EXISTS tmp_049_push_keep;
      CREATE TEMPORARY TABLE tmp_049_push_keep AS
      SELECT `fcm_token`, MAX(`id`) AS `keep_id`
        FROM `mobile_push_tokens`
       WHERE `fcm_token` IS NOT NULL
       GROUP BY `fcm_token`
      HAVING COUNT(*) > 1;

      UPDATE `mobile_push_tokens` t
      INNER JOIN tmp_049_push_keep k ON k.`fcm_token` = t.`fcm_token`
         SET t.`enabled` = 0,
             t.`fcm_token` = CONCAT(LEFT(t.`fcm_token`, 220), ':legacy:', t.`id`),
             t.`updated_at` = NOW()
       WHERE t.`id` <> k.`keep_id`;

      DROP TEMPORARY TABLE IF EXISTS tmp_049_push_keep;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_push_tokens'
       AND INDEX_NAME = 'uniq_mobile_push_token';

    IF v_exists = 0 THEN
      ALTER TABLE `mobile_push_tokens`
        ADD UNIQUE KEY `uniq_mobile_push_token` (`fcm_token`);
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_push_tokens'
       AND INDEX_NAME = 'uniq_mobile_push_token_fcm';

    IF v_exists > 0 THEN
      ALTER TABLE `mobile_push_tokens`
        DROP INDEX `uniq_mobile_push_token_fcm`;
    END IF;
  END IF;

  -- 5. Indices para consultas frecuentes.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_direcciones'
     AND INDEX_NAME = 'idx_mobile_direcciones_usuario_activo_principal';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'mobile_direcciones'
       AND COLUMN_NAME IN ('usuario_id', 'activo', 'es_principal', 'created_at');
    IF v_columns = 4 THEN
      ALTER TABLE `mobile_direcciones`
        ADD INDEX `idx_mobile_direcciones_usuario_activo_principal`
        (`usuario_id`, `activo`, `es_principal`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rest_pedidos'
     AND INDEX_NAME = 'idx_rest_pedidos_rest_estado_created';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rest_pedidos'
       AND COLUMN_NAME IN ('restaurante_id', 'estado', 'created_at');
    IF v_columns = 3 THEN
      ALTER TABLE `rest_pedidos`
        ADD INDEX `idx_rest_pedidos_rest_estado_created` (`restaurante_id`, `estado`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rest_pedidos'
     AND INDEX_NAME = 'idx_rest_pedidos_mobile_created';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rest_pedidos'
       AND COLUMN_NAME IN ('mobile_usuario_id', 'created_at');
    IF v_columns = 2 THEN
      ALTER TABLE `rest_pedidos`
        ADD INDEX `idx_rest_pedidos_mobile_created` (`mobile_usuario_id`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rest_pedidos'
     AND INDEX_NAME = 'idx_rest_pedidos_tipo_estado_created';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rest_pedidos'
       AND COLUMN_NAME IN ('tipo_pedido', 'estado', 'created_at');
    IF v_columns = 3 THEN
      ALTER TABLE `rest_pedidos`
        ADD INDEX `idx_rest_pedidos_tipo_estado_created` (`tipo_pedido`, `estado`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rest_pedido_items'
     AND INDEX_NAME = 'idx_rpi_pedido_estado';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rest_pedido_items'
       AND COLUMN_NAME IN ('pedido_id', 'estado');
    IF v_columns = 2 THEN
      ALTER TABLE `rest_pedido_items`
        ADD INDEX `idx_rpi_pedido_estado` (`pedido_id`, `estado`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rest_mesas'
     AND INDEX_NAME = 'idx_rest_mesas_rest_estado_activo';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'rest_mesas'
       AND COLUMN_NAME IN ('restaurante_id', 'estado', 'activo');
    IF v_columns = 3 THEN
      ALTER TABLE `rest_mesas`
        ADD INDEX `idx_rest_mesas_rest_estado_activo` (`restaurante_id`, `estado`, `activo`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'social_gift_orders'
     AND INDEX_NAME = 'idx_sgo_recipient_status_created';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'social_gift_orders'
       AND COLUMN_NAME IN ('recipient_user_id', 'status', 'created_at');
    IF v_columns = 3 THEN
      ALTER TABLE `social_gift_orders`
        ADD INDEX `idx_sgo_recipient_status_created` (`recipient_user_id`, `status`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'social_account_notifications'
     AND INDEX_NAME = 'idx_san_user_read_created';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'social_account_notifications'
       AND COLUMN_NAME IN ('user_id', 'read_at', 'created_at');
    IF v_columns = 3 THEN
      ALTER TABLE `social_account_notifications`
        ADD INDEX `idx_san_user_read_created` (`user_id`, `read_at`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amare_wallet_transactions'
     AND INDEX_NAME = 'idx_wallet_transactions_wallet';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'amare_wallet_transactions'
       AND COLUMN_NAME IN ('wallet_id', 'created_at');
    IF v_columns = 2 THEN
      ALTER TABLE `amare_wallet_transactions`
        ADD INDEX `idx_wallet_transactions_wallet` (`wallet_id`, `created_at`);
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists FROM information_schema.STATISTICS
   WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'amare_wallet_transactions'
     AND INDEX_NAME = 'idx_wallet_transactions_reference';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_columns
      FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'amare_wallet_transactions'
       AND COLUMN_NAME IN ('reference_type', 'reference_id');
    IF v_columns = 2 THEN
      ALTER TABLE `amare_wallet_transactions`
        ADD INDEX `idx_wallet_transactions_reference` (`reference_type`, `reference_id`);
    END IF;
  END IF;

  -- 6. Normalizar tipo de mobile_push_tokens.usuario_id antes de FK.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_push_tokens'
     AND COLUMN_NAME = 'usuario_id'
     AND COLUMN_TYPE NOT LIKE '%unsigned%';

  IF v_exists > 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `mobile_push_tokens`
     WHERE `usuario_id` < 0;

    IF v_orphans = 0 THEN
      ALTER TABLE `mobile_push_tokens`
        MODIFY `usuario_id` INT UNSIGNED NOT NULL;
    END IF;
  END IF;

  -- 7. Foreign keys: solo se agregan cuando no hay huerfanos.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_direcciones'
     AND COLUMN_NAME = 'usuario_id'
     AND REFERENCED_TABLE_NAME = 'mobile_usuarios';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `mobile_direcciones` c
      LEFT JOIN `mobile_usuarios` p ON p.`id` = c.`usuario_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `mobile_direcciones`
        ADD CONSTRAINT `fk_mobile_direcciones_usuario_049`
        FOREIGN KEY (`usuario_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_favoritos'
     AND COLUMN_NAME = 'usuario_id'
     AND REFERENCED_TABLE_NAME = 'mobile_usuarios';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `mobile_favoritos` c
      LEFT JOIN `mobile_usuarios` p ON p.`id` = c.`usuario_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `mobile_favoritos`
        ADD CONSTRAINT `fk_mobile_favoritos_usuario_049`
        FOREIGN KEY (`usuario_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_favoritos'
     AND COLUMN_NAME = 'platillo_id'
     AND REFERENCED_TABLE_NAME = 'rest_platillos';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `mobile_favoritos` c
      LEFT JOIN `rest_platillos` p ON p.`id` = c.`platillo_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `mobile_favoritos`
        ADD CONSTRAINT `fk_mobile_favoritos_platillo_049`
        FOREIGN KEY (`platillo_id`) REFERENCES `rest_platillos` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_push_tokens'
     AND COLUMN_NAME = 'usuario_id'
     AND REFERENCED_TABLE_NAME = 'mobile_usuarios';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `mobile_push_tokens` c
      LEFT JOIN `mobile_usuarios` p ON p.`id` = c.`usuario_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `mobile_push_tokens`
        ADD CONSTRAINT `fk_mobile_push_tokens_usuario_049`
        FOREIGN KEY (`usuario_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'rest_pedido_items'
     AND COLUMN_NAME = 'pedido_id'
     AND REFERENCED_TABLE_NAME = 'rest_pedidos';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `rest_pedido_items` c
      LEFT JOIN `rest_pedidos` p ON p.`id` = c.`pedido_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `rest_pedido_items`
        ADD CONSTRAINT `fk_rest_pedido_items_pedido_049`
        FOREIGN KEY (`pedido_id`) REFERENCES `rest_pedidos` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'rest_pedido_items'
     AND COLUMN_NAME = 'platillo_id'
     AND REFERENCED_TABLE_NAME = 'rest_platillos';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `rest_pedido_items` c
      LEFT JOIN `rest_platillos` p ON p.`id` = c.`platillo_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `rest_pedido_items`
        ADD CONSTRAINT `fk_rest_pedido_items_platillo_049`
        FOREIGN KEY (`platillo_id`) REFERENCES `rest_platillos` (`id`) ON DELETE RESTRICT;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'social_likes'
     AND COLUMN_NAME = 'liker_user_id'
     AND REFERENCED_TABLE_NAME = 'mobile_usuarios';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `social_likes` c
      LEFT JOIN `mobile_usuarios` p ON p.`id` = c.`liker_user_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `social_likes`
        ADD CONSTRAINT `fk_social_likes_liker_049`
        FOREIGN KEY (`liker_user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.KEY_COLUMN_USAGE
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'social_likes'
     AND COLUMN_NAME = 'liked_user_id'
     AND REFERENCED_TABLE_NAME = 'mobile_usuarios';
  IF v_exists = 0 THEN
    SELECT COUNT(*) INTO v_orphans
      FROM `social_likes` c
      LEFT JOIN `mobile_usuarios` p ON p.`id` = c.`liked_user_id`
     WHERE p.`id` IS NULL;
    IF v_orphans = 0 THEN
      ALTER TABLE `social_likes`
        ADD CONSTRAINT `fk_social_likes_liked_049`
        FOREIGN KEY (`liked_user_id`) REFERENCES `mobile_usuarios` (`id`) ON DELETE CASCADE;
    END IF;
  END IF;

  -- 8. Retencion de logs con archivo reversible.
  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'login_intentos';
  IF v_exists > 0 THEN
    CREATE TABLE IF NOT EXISTS `legacy_login_intentos_archive_20260714` LIKE `login_intentos`;
    INSERT IGNORE INTO `legacy_login_intentos_archive_20260714`
    SELECT * FROM `login_intentos`
     WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
    DELETE li FROM `login_intentos` li
    INNER JOIN `legacy_login_intentos_archive_20260714` a ON a.`id` = li.`id`
     WHERE li.`created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
  END IF;

  SELECT COUNT(*) INTO v_exists
    FROM information_schema.TABLES
   WHERE TABLE_SCHEMA = DATABASE()
     AND TABLE_NAME = 'mobile_notification_logs';
  IF v_exists > 0 THEN
    CREATE TABLE IF NOT EXISTS `legacy_mobile_notification_logs_archive_20260714` LIKE `mobile_notification_logs`;
    INSERT IGNORE INTO `legacy_mobile_notification_logs_archive_20260714`
    SELECT * FROM `mobile_notification_logs`
     WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 180 DAY);
    DELETE nl FROM `mobile_notification_logs` nl
    INNER JOIN `legacy_mobile_notification_logs_archive_20260714` a ON a.`id` = nl.`id`
     WHERE nl.`created_at` < DATE_SUB(NOW(), INTERVAL 180 DAY);
  END IF;

  -- 9. Archivar tablas candidatas solo si existen y estan vacias.
  SET done = 0;
  OPEN empty_legacy_tables;
  empty_loop: LOOP
    FETCH empty_legacy_tables INTO v_table_name;
    IF done = 1 THEN
      LEAVE empty_loop;
    END IF;

    SELECT COUNT(*) INTO v_exists
      FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = v_table_name;

    IF v_exists > 0 THEN
      SET @sp049_rows = 0;
      SET v_sql = CONCAT(
        'SELECT COUNT(*) INTO @sp049_rows FROM `',
        REPLACE(v_table_name, '`', '``'),
        '`'
      );
      SET @sp049_sql = v_sql;
      PREPARE stmt FROM @sp049_sql;
      EXECUTE stmt;
      DEALLOCATE PREPARE stmt;

      SET v_legacy_name = CONCAT('legacy_', v_table_name, '_20260714');

      SELECT COUNT(*) INTO v_exists
        FROM information_schema.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = v_legacy_name;

      IF @sp049_rows = 0 AND v_exists = 0 THEN
        SET v_sql = CONCAT(
          'RENAME TABLE `',
          REPLACE(v_table_name, '`', '``'),
          '` TO `',
          REPLACE(v_legacy_name, '`', '``'),
          '`'
        );
        SET @sp049_sql = v_sql;
        PREPARE stmt FROM @sp049_sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
      END IF;
    END IF;
  END LOOP;
  CLOSE empty_legacy_tables;
END$$

DELIMITER ;

CALL sp_049_optimize_database_indexes_integrity();

DROP PROCEDURE IF EXISTS sp_049_optimize_database_indexes_integrity;
