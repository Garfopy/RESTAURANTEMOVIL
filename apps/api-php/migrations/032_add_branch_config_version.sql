-- Version reactiva de configuracion por sucursal.
-- MySQL 5.7 compatible. Los triggers cubren cambios directos desde CarniHub.

SET @db_name = DATABASE();

SET @sql = IF(
  EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='rest_configuracion' AND COLUMN_NAME='config_version'),
  'SELECT 1',
  'ALTER TABLE `rest_configuracion` ADD COLUMN `config_version` BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER `extras_habilitados`'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

DROP TRIGGER IF EXISTS `trg_config_version_recipe_insert`;
DROP TRIGGER IF EXISTS `trg_config_version_recipe_update`;
DROP TRIGGER IF EXISTS `trg_config_version_recipe_delete`;
DROP TRIGGER IF EXISTS `trg_config_version_recipe_item_insert`;
DROP TRIGGER IF EXISTS `trg_config_version_recipe_item_update`;
DROP TRIGGER IF EXISTS `trg_config_version_recipe_item_delete`;
DROP TRIGGER IF EXISTS `trg_config_version_modifier_insert`;
DROP TRIGGER IF EXISTS `trg_config_version_modifier_update`;
DROP TRIGGER IF EXISTS `trg_config_version_modifier_delete`;

DELIMITER $$

CREATE TRIGGER `trg_config_version_recipe_insert` AFTER INSERT ON `rest_recetas`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion c JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE p.id=NEW.platillo_id;
END$$

CREATE TRIGGER `trg_config_version_recipe_update` AFTER UPDATE ON `rest_recetas`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion c JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE p.id=NEW.platillo_id;
END$$

CREATE TRIGGER `trg_config_version_recipe_delete` AFTER DELETE ON `rest_recetas`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion c JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE p.id=OLD.platillo_id;
END$$

CREATE TRIGGER `trg_config_version_recipe_item_insert` AFTER INSERT ON `rest_receta_ingredientes`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion c
  JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  JOIN rest_recetas r ON r.platillo_id=p.id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE r.id=NEW.receta_id;
END$$

CREATE TRIGGER `trg_config_version_recipe_item_update` AFTER UPDATE ON `rest_receta_ingredientes`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion c
  JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  JOIN rest_recetas r ON r.platillo_id=p.id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE r.id=NEW.receta_id;
END$$

CREATE TRIGGER `trg_config_version_recipe_item_delete` AFTER DELETE ON `rest_receta_ingredientes`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion c
  JOIN rest_platillos p ON p.restaurante_id=c.restaurante_id
  JOIN rest_recetas r ON r.platillo_id=p.id
  SET c.config_version=c.config_version+1, c.updated_at=NOW() WHERE r.id=OLD.receta_id;
END$$

CREATE TRIGGER `trg_config_version_modifier_insert` AFTER INSERT ON `rest_platillo_modificadores`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion SET config_version=config_version+1, updated_at=NOW() WHERE restaurante_id=NEW.restaurante_id;
END$$

CREATE TRIGGER `trg_config_version_modifier_update` AFTER UPDATE ON `rest_platillo_modificadores`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion SET config_version=config_version+1, updated_at=NOW() WHERE restaurante_id=NEW.restaurante_id;
END$$

CREATE TRIGGER `trg_config_version_modifier_delete` AFTER DELETE ON `rest_platillo_modificadores`
FOR EACH ROW BEGIN
  UPDATE rest_configuracion SET config_version=config_version+1, updated_at=NOW() WHERE restaurante_id=OLD.restaurante_id;
END$$

DELIMITER ;
