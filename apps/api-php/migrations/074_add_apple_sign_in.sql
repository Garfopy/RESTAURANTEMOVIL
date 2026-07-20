ALTER TABLE `mobile_usuarios`
  ADD COLUMN IF NOT EXISTS `apple_id` VARCHAR(191) NULL AFTER `google_id`;

SET @apple_index_exists := (
  SELECT COUNT(*)
  FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'mobile_usuarios'
    AND INDEX_NAME = 'uq_mobile_usuarios_apple_id'
);

SET @apple_index_sql := IF(
  @apple_index_exists = 0,
  'CREATE UNIQUE INDEX `uq_mobile_usuarios_apple_id` ON `mobile_usuarios` (`apple_id`)',
  'SELECT 1'
);

PREPARE apple_index_statement FROM @apple_index_sql;
EXECUTE apple_index_statement;
DEALLOCATE PREPARE apple_index_statement;
