-- ============================================================
-- 040_codigos_menu_y_materia_prima.sql
-- Agrega columnas codigo/tipo a rest_platillos y rest_ingredientes,
-- inserta MP1-MP35 (Materia Prima) y los vincula a cada receta.
-- Ejecutar UNA SOLA VEZ en produccion.
-- ============================================================

SET @rest_id = 1; -- Cambiar si el restaurante_id es distinto de 1

-- ── 1. Nuevas columnas (compatible MySQL 5.7, sin IF NOT EXISTS) ──────────

-- rest_receta_ingredientes.precio_extra
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `rest_receta_ingredientes` ADD COLUMN `precio_extra` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `es_informativo`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_receta_ingredientes'
    AND COLUMN_NAME = 'precio_extra'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rest_receta_ingredientes.tipo_componente
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `rest_receta_ingredientes` ADD COLUMN `tipo_componente` VARCHAR(30) NULL DEFAULT NULL AFTER `precio_extra`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_receta_ingredientes'
    AND COLUMN_NAME = 'tipo_componente'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rest_receta_ingredientes.codigo_display
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `rest_receta_ingredientes` ADD COLUMN `codigo_display` VARCHAR(20) NULL DEFAULT NULL AFTER `tipo_componente`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_receta_ingredientes'
    AND COLUMN_NAME = 'codigo_display'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rest_platillos.codigo
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `rest_platillos` ADD COLUMN `codigo` VARCHAR(20) NULL AFTER `id`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_platillos'
    AND COLUMN_NAME = 'codigo'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rest_platillos.es_armado
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `rest_platillos` ADD COLUMN `es_armado` TINYINT(1) NOT NULL DEFAULT 0 AFTER `codigo`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_platillos'
    AND COLUMN_NAME = 'es_armado'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rest_ingredientes.codigo
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `rest_ingredientes` ADD COLUMN `codigo` VARCHAR(20) NULL AFTER `id`',
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_ingredientes'
    AND COLUMN_NAME = 'codigo'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- rest_ingredientes.tipo
SET @sql := (
  SELECT IF(
    COUNT(*) = 0,
    "ALTER TABLE `rest_ingredientes` ADD COLUMN `tipo` ENUM('materia_prima','guarnicion','bebida','otro') NOT NULL DEFAULT 'otro' AFTER `codigo`",
    'SELECT 1'
  )
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'rest_ingredientes'
    AND COLUMN_NAME = 'tipo'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2. Codigos M1-M35 en rest_platillos ────────────────────────────────────

UPDATE `rest_platillos` SET codigo = 'M1', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Enchiladas Potosinas 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M2', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Carnitas 150 Gr.';
UPDATE `rest_platillos` SET codigo = 'M3', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Barbacoa 150 Gr.';
UPDATE `rest_platillos` SET codigo = 'M4', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tamal de Maíz Rojo de Pollo 2 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M5', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tamal de Maíz Rojo de Carne 2 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M6', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tamal de Maíz Verde de Pollo 2 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M7', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tamal de Maíz Verde de Carne 2 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M8', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tamal Oaxaqueño 1 Pza.';
UPDATE `rest_platillos` SET codigo = 'M9', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Poblano con Carne de Puerco 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M10', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Poblano con Carne de Pollo 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M11', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Verde con Pollo 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M12', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Verde con Carne 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M13', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Oaxaqueño Rojo con Carne 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M14', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Oaxaqueño Rojo con Pollo 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M15', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Oaxaqueño Negro con Carne 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M16', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Oaxaqueño Negro con Pollo 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M17', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Oaxaqueño Amarillo con Carne 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M18', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Mole Oaxaqueño Amarillo con Pollo 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M19', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Sope de Chorizo 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M20', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Sope de Pollo 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M21', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Huarache de Pollo 1 Pza.';
UPDATE `rest_platillos` SET codigo = 'M22', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Huarache de Carne 1 Pza.';
UPDATE `rest_platillos` SET codigo = 'M23', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Chilorio 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M24', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tacos de Canasta Deshebrada 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M25', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tacos de Canasta de Papa 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M26', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tacos de Canasta de Chicharrón 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M27', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Tacos de Canasta de Frijol 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M28', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Chile Relleno de Queso 1 Pza.';
UPDATE `rest_platillos` SET codigo = 'M29', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Chile Relleno de Carne 1 Pza.';
UPDATE `rest_platillos` SET codigo = 'M30', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Asadura 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M31', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Cochinita Pibil 250 Gr.';
UPDATE `rest_platillos` SET codigo = 'M32', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Gorditas de Queso 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M33', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Gorditas de Migajas 3 Pzs.';
UPDATE `rest_platillos` SET codigo = 'M34', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Pozole Blanco 300 Gr.';
UPDATE `rest_platillos` SET codigo = 'M35', es_armado = 1
  WHERE restaurante_id = @rest_id AND nombre = 'Pozole Rojo 300 Gr.';

-- ── 3. Codigos G1-G21 en rest_ingredientes (guarniciones ya existentes) ─────

UPDATE `rest_ingredientes` SET codigo = 'G1', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Tortillas de maíz 4 piezas';
UPDATE `rest_ingredientes` SET codigo = 'G2', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Salsa verde 50ml';
UPDATE `rest_ingredientes` SET codigo = 'G3', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Salsa roja 50ml';
UPDATE `rest_ingredientes` SET codigo = 'G4', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Crema 50ml';
UPDATE `rest_ingredientes` SET codigo = 'G5', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Queso rayado 50gr';
UPDATE `rest_ingredientes` SET codigo = 'G6', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Limones 2 piezas';
UPDATE `rest_ingredientes` SET codigo = 'G7', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Frijoles refritos 150gr';
UPDATE `rest_ingredientes` SET codigo = 'G8', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Sal de mar';
UPDATE `rest_ingredientes` SET codigo = 'G9', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Guacamole 200gr';
UPDATE `rest_ingredientes` SET codigo = 'G10', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Rajas 50gr';
UPDATE `rest_ingredientes` SET codigo = 'G11', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Salsa chipotle 50gr';
UPDATE `rest_ingredientes` SET codigo = 'G12', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Chorizo 150gr';
UPDATE `rest_ingredientes` SET codigo = 'G13', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Arroz blanco 200gr';
UPDATE `rest_ingredientes` SET codigo = 'G14', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Arroz rojo 200gr';
UPDATE `rest_ingredientes` SET codigo = 'G15', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Azúcar 5gr';
UPDATE `rest_ingredientes` SET codigo = 'G16', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Orégano 5gr';
UPDATE `rest_ingredientes` SET codigo = 'G17', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Cebolla picada 25gr';
UPDATE `rest_ingredientes` SET codigo = 'G18', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Cilantro picado 5gr';
UPDATE `rest_ingredientes` SET codigo = 'G19', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Pollo deshebrado 250gr';
UPDATE `rest_ingredientes` SET codigo = 'G20', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Carne picada 250gr';
UPDATE `rest_ingredientes` SET codigo = 'G21', tipo = 'guarnicion'
  WHERE restaurante_id = @rest_id AND nombre = 'Cebolla morada 25gr';

-- ── 4. Insertar Materia Prima MP1-MP35 ─────────────────────────────────────
-- INSERT IGNORE: seguro de re-ejecutar, no genera duplicados.

INSERT IGNORE INTO `rest_ingredientes`
  (restaurante_id, codigo, tipo, nombre, unidad_principal, costo_unitario, stock, stock_minimo, activo)
VALUES
  (@rest_id, 'MP1', 'materia_prima', 'Enchiladas Potosinas 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP2', 'materia_prima', 'Carnitas 150 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP3', 'materia_prima', 'Barbacoa 150 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP4', 'materia_prima', 'Tamal de Maíz Rojo de Pollo 2 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP5', 'materia_prima', 'Tamal de Maíz Rojo de Carne 2 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP6', 'materia_prima', 'Tamal de Maíz Verde de Pollo 2 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP7', 'materia_prima', 'Tamal de Maíz Verde de Carne 2 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP8', 'materia_prima', 'Tamal Oaxaqueño 1 Pza.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP9', 'materia_prima', 'Mole Poblano con Carne de Puerco 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP10', 'materia_prima', 'Mole Poblano con Carne de Pollo 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP11', 'materia_prima', 'Mole Verde con Pollo 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP12', 'materia_prima', 'Mole Verde con Carne 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP13', 'materia_prima', 'Mole Oaxaqueño Rojo con Carne 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP14', 'materia_prima', 'Mole Oaxaqueño Rojo con Pollo 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP15', 'materia_prima', 'Mole Oaxaqueño Negro con Carne 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP16', 'materia_prima', 'Mole Oaxaqueño Negro con Pollo 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP17', 'materia_prima', 'Mole Oaxaqueño Amarillo con Carne 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP18', 'materia_prima', 'Mole Oaxaqueño Amarillo con Pollo 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP19', 'materia_prima', 'Sope de Chorizo 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP20', 'materia_prima', 'Sope de Pollo 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP21', 'materia_prima', 'Huarache de Pollo 1 Pza.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP22', 'materia_prima', 'Huarache de Carne 1 Pza.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP23', 'materia_prima', 'Chilorio 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP24', 'materia_prima', 'Tacos de Canasta Deshebrada 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP25', 'materia_prima', 'Tacos de Canasta de Papa 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP26', 'materia_prima', 'Tacos de Canasta de Chicharrón 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP27', 'materia_prima', 'Tacos de Canasta de Frijol 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP28', 'materia_prima', 'Chile Relleno de Queso 1 Pza.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP29', 'materia_prima', 'Chile Relleno de Carne 1 Pza.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP30', 'materia_prima', 'Asadura 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP31', 'materia_prima', 'Cochinita Pibil 250 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP32', 'materia_prima', 'Gorditas de Queso 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP33', 'materia_prima', 'Gorditas de Migajas 3 Pzs.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP34', 'materia_prima', 'Pozole Blanco 300 Gr.', 'porcion', 0.00, 999.000, 0.000, 1),
  (@rest_id, 'MP35', 'materia_prima', 'Pozole Rojo 300 Gr.', 'porcion', 0.00, 999.000, 0.000, 1);

-- ── 5. Capturar IDs de MP ──────────────────────────────────────────────────

SET @mp1 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP1' LIMIT 1);
SET @mp2 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP2' LIMIT 1);
SET @mp3 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP3' LIMIT 1);
SET @mp4 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP4' LIMIT 1);
SET @mp5 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP5' LIMIT 1);
SET @mp6 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP6' LIMIT 1);
SET @mp7 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP7' LIMIT 1);
SET @mp8 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP8' LIMIT 1);
SET @mp9 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP9' LIMIT 1);
SET @mp10 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP10' LIMIT 1);
SET @mp11 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP11' LIMIT 1);
SET @mp12 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP12' LIMIT 1);
SET @mp13 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP13' LIMIT 1);
SET @mp14 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP14' LIMIT 1);
SET @mp15 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP15' LIMIT 1);
SET @mp16 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP16' LIMIT 1);
SET @mp17 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP17' LIMIT 1);
SET @mp18 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP18' LIMIT 1);
SET @mp19 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP19' LIMIT 1);
SET @mp20 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP20' LIMIT 1);
SET @mp21 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP21' LIMIT 1);
SET @mp22 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP22' LIMIT 1);
SET @mp23 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP23' LIMIT 1);
SET @mp24 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP24' LIMIT 1);
SET @mp25 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP25' LIMIT 1);
SET @mp26 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP26' LIMIT 1);
SET @mp27 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP27' LIMIT 1);
SET @mp28 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP28' LIMIT 1);
SET @mp29 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP29' LIMIT 1);
SET @mp30 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP30' LIMIT 1);
SET @mp31 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP31' LIMIT 1);
SET @mp32 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP32' LIMIT 1);
SET @mp33 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP33' LIMIT 1);
SET @mp34 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP34' LIMIT 1);
SET @mp35 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND codigo='MP35' LIMIT 1);

-- ── 6. Capturar IDs de guarniciones ────────────────────────────────────────

SET @g1 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Tortillas de maíz' LIMIT 1);
SET @g2 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Salsa verde' LIMIT 1);
SET @g3 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Salsa roja' LIMIT 1);
SET @g4 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Crema' LIMIT 1);
SET @g5 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Queso rayado' LIMIT 1);
SET @g6 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Limones' LIMIT 1);
SET @g7 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Frijoles refritos' LIMIT 1);
SET @g8 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Sal de mar' LIMIT 1);
SET @g9 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Guacamole' LIMIT 1);
SET @g10 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Rajas' LIMIT 1);
SET @g11 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Salsa chipotle' LIMIT 1);
SET @g12 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Chorizo' LIMIT 1);
SET @g13 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Arroz blanco' LIMIT 1);
SET @g14 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Arroz rojo' LIMIT 1);
SET @g15 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Azúcar' LIMIT 1);
SET @g16 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Orégano' LIMIT 1);
SET @g17 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Cebolla picada' LIMIT 1);
SET @g18 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Cilantro picado' LIMIT 1);
SET @g19 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Pollo deshebrado' LIMIT 1);
SET @g20 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Carne picada' LIMIT 1);
SET @g21 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Cebolla morada' LIMIT 1);

-- ── 7. Vincular MP (componente principal) a cada receta ────────────────────
-- es_informativo=0: descuenta stock al preparar.
-- INSERT IGNORE: seguro de re-ejecutar.

-- M1 (MP1) -- Enchiladas Potosinas 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp1, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Enchiladas Potosinas 3 Pzs.';

-- M2 (MP2) -- Carnitas 150 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp2, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Carnitas 150 Gr.';

-- M3 (MP3) -- Barbacoa 150 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp3, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Barbacoa 150 Gr.';

-- M4 (MP4) -- Tamal de Maíz Rojo de Pollo 2 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp4, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Rojo de Pollo 2 Pzs.';

-- M5 (MP5) -- Tamal de Maíz Rojo de Carne 2 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp5, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Rojo de Carne 2 Pzs.';

-- M6 (MP6) -- Tamal de Maíz Verde de Pollo 2 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp6, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Verde de Pollo 2 Pzs.';

-- M7 (MP7) -- Tamal de Maíz Verde de Carne 2 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp7, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Verde de Carne 2 Pzs.';

-- M8 (MP8) -- Tamal Oaxaqueño 1 Pza.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp8, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal Oaxaqueño 1 Pza.';

-- M9 (MP9) -- Mole Poblano con Carne de Puerco 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp9, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Poblano con Carne de Puerco 250 Gr.';

-- M10 (MP10) -- Mole Poblano con Carne de Pollo 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp10, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Poblano con Carne de Pollo 250 Gr.';

-- M11 (MP11) -- Mole Verde con Pollo 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp11, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Verde con Pollo 250 Gr.';

-- M12 (MP12) -- Mole Verde con Carne 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp12, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Verde con Carne 250 Gr.';

-- M13 (MP13) -- Mole Oaxaqueño Rojo con Carne 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp13, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Rojo con Carne 250 Gr.';

-- M14 (MP14) -- Mole Oaxaqueño Rojo con Pollo 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp14, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Rojo con Pollo 250 Gr.';

-- M15 (MP15) -- Mole Oaxaqueño Negro con Carne 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp15, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Negro con Carne 250 Gr.';

-- M16 (MP16) -- Mole Oaxaqueño Negro con Pollo 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp16, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Negro con Pollo 250 Gr.';

-- M17 (MP17) -- Mole Oaxaqueño Amarillo con Carne 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp17, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Amarillo con Carne 250 Gr.';

-- M18 (MP18) -- Mole Oaxaqueño Amarillo con Pollo 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp18, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Amarillo con Pollo 250 Gr.';

-- M19 (MP19) -- Sope de Chorizo 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp19, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Sope de Chorizo 3 Pzs.';

-- M20 (MP20) -- Sope de Pollo 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp20, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Sope de Pollo 3 Pzs.';

-- M21 (MP21) -- Huarache de Pollo 1 Pza.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp21, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Huarache de Pollo 1 Pza.';

-- M22 (MP22) -- Huarache de Carne 1 Pza.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp22, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Huarache de Carne 1 Pza.';

-- M23 (MP23) -- Chilorio 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp23, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Chilorio 250 Gr.';

-- M24 (MP24) -- Tacos de Canasta Deshebrada 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp24, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tacos de Canasta Deshebrada 3 Pzs.';

-- M25 (MP25) -- Tacos de Canasta de Papa 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp25, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tacos de Canasta de Papa 3 Pzs.';

-- M26 (MP26) -- Tacos de Canasta de Chicharrón 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp26, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tacos de Canasta de Chicharrón 3 Pzs.';

-- M27 (MP27) -- Tacos de Canasta de Frijol 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp27, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tacos de Canasta de Frijol 3 Pzs.';

-- M28 (MP28) -- Chile Relleno de Queso 1 Pza.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp28, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Chile Relleno de Queso 1 Pza.';

-- M29 (MP29) -- Chile Relleno de Carne 1 Pza.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp29, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Chile Relleno de Carne 1 Pza.';

-- M30 (MP30) -- Asadura 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp30, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Asadura 250 Gr.';

-- M31 (MP31) -- Cochinita Pibil 250 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp31, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Cochinita Pibil 250 Gr.';

-- M32 (MP32) -- Gorditas de Queso 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp32, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Gorditas de Queso 3 Pzs.';

-- M33 (MP33) -- Gorditas de Migajas 3 Pzs.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp33, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Gorditas de Migajas 3 Pzs.';

-- M34 (MP34) -- Pozole Blanco 300 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp34, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Pozole Blanco 300 Gr.';

-- M35 (MP35) -- Pozole Rojo 300 Gr.
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
  SELECT r.id, @mp35, 1, 'porcion', 0, 0.00
  FROM rest_recetas r
  JOIN rest_platillos p ON p.id = r.platillo_id
  WHERE p.restaurante_id = @rest_id AND p.nombre = 'Pozole Rojo 300 Gr.';

-- ── Fin ─────────────────────────────────────────────────────────────────────