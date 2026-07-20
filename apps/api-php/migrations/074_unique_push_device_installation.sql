-- Un dispositivo/app instalado debe tener un solo registro push.
-- Conserva el mas reciente y permite que el upsert reasigne cuenta o token.

SET @db_name = DATABASE();

UPDATE mobile_push_tokens
   SET device_id = NULL,
       updated_at = NOW()
 WHERE device_id = '';

UPDATE mobile_push_tokens stale
JOIN mobile_push_tokens fresh
  ON fresh.device_id = stale.device_id
 AND fresh.device_id IS NOT NULL
 AND fresh.device_id <> ''
 AND (
      COALESCE(fresh.last_seen_at, fresh.updated_at, fresh.created_at) > COALESCE(stale.last_seen_at, stale.updated_at, stale.created_at)
      OR (
          COALESCE(fresh.last_seen_at, fresh.updated_at, fresh.created_at) = COALESCE(stale.last_seen_at, stale.updated_at, stale.created_at)
          AND fresh.id > stale.id
      )
 )
   SET stale.enabled = 0,
       stale.device_id = NULL,
       stale.updated_at = NOW();

SET @sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE mobile_push_tokens ADD UNIQUE KEY uniq_mobile_push_device (device_id)',
        'SELECT 1'
    )
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'mobile_push_tokens'
      AND INDEX_NAME = 'uniq_mobile_push_device'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
