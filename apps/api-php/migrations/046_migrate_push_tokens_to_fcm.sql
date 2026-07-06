-- Compatibilidad: si se ejecuto la version previa con expo_push_token,
-- agrega fcm_token y permite insertar tokens Firebase directos.

SET @db_name = DATABASE();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_push_tokens ADD COLUMN fcm_token VARCHAR(255) NULL AFTER usuario_id',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_push_tokens'
      AND COLUMN_NAME = 'fcm_token'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'UPDATE mobile_push_tokens SET fcm_token = COALESCE(fcm_token, expo_push_token) WHERE fcm_token IS NULL',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_push_tokens'
      AND COLUMN_NAME = 'expo_push_token'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) > 0,
        'ALTER TABLE mobile_push_tokens MODIFY expo_push_token VARCHAR(255) NULL',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_push_tokens'
      AND COLUMN_NAME = 'expo_push_token'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_push_tokens ADD UNIQUE KEY uniq_mobile_push_token_fcm (fcm_token)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_push_tokens'
      AND INDEX_NAME = 'uniq_mobile_push_token_fcm'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

