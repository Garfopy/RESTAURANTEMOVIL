-- ============================================================
-- 050_ingredientes_dulces_postres_bebidas.sql
-- Agrega/actualiza ingredientes de las categorías
--   • Dulces y Postres (DP1..DP18)
--   • Bebidas         (B1..B34)
-- en la tabla `rest_ingredientes` para restaurante_id = 1.
--
-- Esta migración es IDEMPOTENTE y se puede aplicar a las DOS
-- bases de datos del proyecto:
--   • idactivo_carnihubdb (CarniHub) — añade el código a los
--     ingredientes ya existentes (que estaban con codigo = NULL).
--   • idactivo_capirest   (Capirest) — inserta los ingredientes
--     que aún no existen.
--
-- Para evitar tener que volver a hacerlo manualmente:
--   1. Se crea un índice ÚNICO sobre (restaurante_id, codigo)
--      para que `INSERT IGNORE` evite duplicados.
--   2. Cada producto se procesa con UPDATE primero (rellena
--      el codigo si la fila ya existe por nombre) y luego
--      INSERT IGNORE (crea la fila si no existía).
--   3. Re-ejecutar este script no causa cambios duplicados.
-- ============================================================

SET @rest_id = 1;

-- ── 1) Asegurar índice único (restaurante_id, codigo) ───────────────────────
-- Permite re-ejecutar INSERT IGNORE sin duplicar y permite múltiples NULL.
-- Si ya existe, lo ignora silenciosamente.
SET @idx_exists := (
  SELECT COUNT(*) FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name   = 'rest_ingredientes'
    AND index_name   = 'uk_ring_rest_codigo'
);
SET @sql := IF(@idx_exists = 0,
  'ALTER TABLE rest_ingredientes ADD UNIQUE KEY uk_ring_rest_codigo (restaurante_id, codigo)',
  'SELECT "uk_ring_rest_codigo ya existe" AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ── 2) DULCES Y POSTRES — UPDATE por nombre + INSERT IGNORE ─────────────────
-- Helper: cada bloque hace UPDATE (si el ingrediente ya existe sin codigo)
-- y luego INSERT IGNORE (si no existe).

-- DP1 — Ate de Guayaba con Queso
UPDATE rest_ingredientes SET codigo='DP1', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%ate%guayaba%queso%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP1','otro',@rest_id,'Ate de Guayaba con Queso 6 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP2 — Ate de Membrillo con Queso
UPDATE rest_ingredientes SET codigo='DP2', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%ate%membrillo%queso%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP2','otro',@rest_id,'Ate de Membrillo con Queso 6 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP3 — Glorias de Leche Quemada
UPDATE rest_ingredientes SET codigo='DP3', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%glorias%leche%quemada%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP3','otro',@rest_id,'Glorias de Leche Quemada 3 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP4 — Obleas de Cajeta
UPDATE rest_ingredientes SET codigo='DP4', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%obleas%cajeta%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP4','otro',@rest_id,'Obleas de Cajeta 3 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP5 — Camote de Puebla
UPDATE rest_ingredientes SET codigo='DP5', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%camote%puebla%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP5','otro',@rest_id,'Camote de Puebla 3 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP6 — Borrachitos de Fresa
UPDATE rest_ingredientes SET codigo='DP6', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%borrachitos%fresa%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP6','otro',@rest_id,'Borrachitos de Fresa 5 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP7 — Fruta Cristalizada Higo 150 Gr
UPDATE rest_ingredientes SET codigo='DP7', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%fruta%cristalizada%higo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP7','otro',@rest_id,'Fruta Cristalizada Higo 150 Gr.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP8 — Fruta Cristalizada Pera 150 Gr
UPDATE rest_ingredientes SET codigo='DP8', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%fruta%cristalizada%pera%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP8','otro',@rest_id,'Fruta Cristalizada Pera 150 Gr.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP9 — Fruta Cristalizada Calabazete 150 Gr
UPDATE rest_ingredientes SET codigo='DP9', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%fruta%cristalizada%calabaz%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP9','otro',@rest_id,'Fruta Cristalizada Calabazete 150 Gr.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP10 — Orejones 150 Gr
UPDATE rest_ingredientes SET codigo='DP10', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%orejones%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP10','otro',@rest_id,'Orejones 150 Gr.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP11 — Cocadas
UPDATE rest_ingredientes SET codigo='DP11', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cocadas%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP11','otro',@rest_id,'Cocadas 3 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP12 — Palanquetas
UPDATE rest_ingredientes SET codigo='DP12', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%palanquetas%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP12','otro',@rest_id,'Palanquetas 1 Pza.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP13 — Merengues
UPDATE rest_ingredientes SET codigo='DP13', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%merengues%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP13','otro',@rest_id,'Merengues 3 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP14 — Natillas Qro.
UPDATE rest_ingredientes SET codigo='DP14', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%natillas%qro%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP14','otro',@rest_id,'Natillas Qro. 150 Gr.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP15 — Natillas Bernal
UPDATE rest_ingredientes SET codigo='DP15', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%natillas%bernal%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP15','otro',@rest_id,'Natillas Bernal 4 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP16 — Ollitas de Tamarindo
UPDATE rest_ingredientes SET codigo='DP16', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%ollitas%tamarindo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP16','otro',@rest_id,'Ollitas de Tamarindo 3 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP17 — Tamal de Maíz Dulce de Piña
UPDATE rest_ingredientes SET codigo='DP17', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%tamal%dulce%pi_a%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP17','otro',@rest_id,'Tamal de Maíz Dulce de Piña 2 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- DP18 — Tamal de Dulce de Fresa
UPDATE rest_ingredientes SET codigo='DP18', categoria='Dulces y Postres', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%tamal%dulce%fresa%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('DP18','otro',@rest_id,'Tamal de Dulce de Fresa 2 Pzs.','pieza',1.0000,0.00,0.000,0.000,'Dulces y Postres',0,1);

-- ── 3) BEBIDAS — UPDATE por nombre + INSERT IGNORE ──────────────────────────

-- B1 — Mezcal Sol 2 Oz
UPDATE rest_ingredientes SET codigo='B1', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%mezcal%sol%' AND LOWER(nombre) NOT LIKE '%percheron%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B1','otro',@rest_id,'Mezcal Sol 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B2 — Mezcal Luna 2 Oz
UPDATE rest_ingredientes SET codigo='B2', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%mezcal%luna%' AND LOWER(nombre) NOT LIKE '%percheron%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B2','otro',@rest_id,'Mezcal Luna 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B3 — Mezcal Orgullo 2 Oz
UPDATE rest_ingredientes SET codigo='B3', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%mezcal%orgullo%' AND LOWER(nombre) NOT LIKE '%percheron%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B3','otro',@rest_id,'Mezcal Orgullo 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B4 — Mezcal Noche 2 Oz
UPDATE rest_ingredientes SET codigo='B4', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%mezcal%noche%' AND LOWER(nombre) NOT LIKE '%percheron%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B4','otro',@rest_id,'Mezcal Noche 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B5 — Mezcal Amor 2 Oz
UPDATE rest_ingredientes SET codigo='B5', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%mezcal%amor%' AND LOWER(nombre) NOT LIKE '%percheron%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B5','otro',@rest_id,'Mezcal Amor 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B6 — Tequila Blanco 2 Oz
UPDATE rest_ingredientes SET codigo='B6', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%tequila%blanco%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B6','otro',@rest_id,'Tequila Blanco 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B7 — Tequila Reposado 2 Oz
UPDATE rest_ingredientes SET codigo='B7', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%tequila%reposado%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B7','otro',@rest_id,'Tequila Reposado 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B8 — Tequila Añejo 2 Oz
UPDATE rest_ingredientes SET codigo='B8', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%tequila%a_ejo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B8','otro',@rest_id,'Tequila Añejo 2 Oz.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B9 — Cerveza Artesanal Clara
UPDATE rest_ingredientes SET codigo='B9', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cerveza%artesanal%clara%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B9','otro',@rest_id,'Cerveza Artesanal Clara','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B10 — Cerveza Artesanal Morena
UPDATE rest_ingredientes SET codigo='B10', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cerveza%artesanal%morena%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B10','otro',@rest_id,'Cerveza Artesanal Morena','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B11 — Cerveza Artesanal Oscura
UPDATE rest_ingredientes SET codigo='B11', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cerveza%artesanal%oscura%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B11','otro',@rest_id,'Cerveza Artesanal Oscura','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B12 — Cocktail de Mezcal con Tamarindo
UPDATE rest_ingredientes SET codigo='B12', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cock%mezcal%tamarindo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B12','otro',@rest_id,'Cocktail de Mezcal con Tamarindo','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B13 — Cocktail de Mezcal con Jamaica
UPDATE rest_ingredientes SET codigo='B13', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cock%mezcal%jamaica%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B13','otro',@rest_id,'Cocktail de Mezcal con Jamaica','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B14 — Cocktail de Mezcal Margarita
UPDATE rest_ingredientes SET codigo='B14', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cock%mezcal%margarita%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B14','otro',@rest_id,'Cocktail de Mezcal Margarita','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B15 — Cocktail de Tequila con Tamarindo
UPDATE rest_ingredientes SET codigo='B15', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cock%tequila%tamarindo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B15','otro',@rest_id,'Cocktail de Tequila con Tamarindo','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B16 — Cocktail de Tequila con Jamaica
UPDATE rest_ingredientes SET codigo='B16', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%co_tail%tequila%jamaica%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B16','otro',@rest_id,'Cocktail de Tequila con Jamaica','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B17 — Cocktail de Tequila con Margarita
UPDATE rest_ingredientes SET codigo='B17', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%cock%tequila%margarita%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B17','otro',@rest_id,'Cocktail de Tequila con Margarita','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B18 — Café de Olla
UPDATE rest_ingredientes SET codigo='B18', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%caf_%olla%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B18','otro',@rest_id,'Café de Olla','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B19 — Carajillo sin Cafeína Amanecer
UPDATE rest_ingredientes SET codigo='B19', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%carajillo%amanecer%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B19','otro',@rest_id,'Carajillo sin Cafeína Amanecer','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B20 — Carajillo sin Cafeína Anochecer
UPDATE rest_ingredientes SET codigo='B20', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%carajillo%anocher%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B20','otro',@rest_id,'Carajillo sin Cafeína Anochecer','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B21 — Agua de Horchata
UPDATE rest_ingredientes SET codigo='B21', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%agua%horchata%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B21','otro',@rest_id,'Agua de Horchata','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B22 — Agua de Jamaica
UPDATE rest_ingredientes SET codigo='B22', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%agua%jamaica%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B22','otro',@rest_id,'Agua de Jamaica','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B23 — Agua de Tamarindo
UPDATE rest_ingredientes SET codigo='B23', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%agua%tamarindo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B23','otro',@rest_id,'Agua de Tamarindo','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B24 — Chocolate con Agua
UPDATE rest_ingredientes SET codigo='B24', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%chocolate%agua%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B24','otro',@rest_id,'Chocolate con Agua','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B25 — Chocolate con Leche
UPDATE rest_ingredientes SET codigo='B25', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%chocolate%leche%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B25','otro',@rest_id,'Chocolate con Leche','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B26 — Atole de Fresa
UPDATE rest_ingredientes SET codigo='B26', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%atole%fresa%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B26','otro',@rest_id,'Atole de Fresa','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B27 — Atole de Vainilla
UPDATE rest_ingredientes SET codigo='B27', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%atole%vainilla%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B27','otro',@rest_id,'Atole de Vainilla','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B28 — Agua Mineral
UPDATE rest_ingredientes SET codigo='B28', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%agua%mineral%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B28','otro',@rest_id,'Agua Mineral','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B29 — Agua Sola
UPDATE rest_ingredientes SET codigo='B29', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%agua%sola%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B29','otro',@rest_id,'Agua Sola','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B30 — Percheron Mezcal Sol 65 Ml
UPDATE rest_ingredientes SET codigo='B30', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%percheron%mezcal%sol%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B30','otro',@rest_id,'Percheron Mezcal Sol 65 Ml.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B31 — Percheron Mezcal Luna 65 Ml
UPDATE rest_ingredientes SET codigo='B31', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%percheron%mezcal%luna%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B31','otro',@rest_id,'Percheron Mezcal Luna 65 Ml.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B32 — Percheron Mezcal Orgullo 65 Ml
UPDATE rest_ingredientes SET codigo='B32', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%percheron%mezcal%orgullo%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B32','otro',@rest_id,'Percheron Mezcal Orgullo 65 Ml.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B33 — Percheron Mezcal Amor 65 Ml
UPDATE rest_ingredientes SET codigo='B33', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%percheron%mezcal%amor%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B33','otro',@rest_id,'Percheron Mezcal Amor 65 Ml.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- B34 — Percheron Mezcal Noche 65 Ml
UPDATE rest_ingredientes SET codigo='B34', categoria='Bebidas', tipo='otro'
 WHERE restaurante_id=@rest_id AND (codigo IS NULL OR codigo='')
   AND LOWER(nombre) LIKE '%percheron%mezcal%noche%';
INSERT IGNORE INTO rest_ingredientes (codigo, tipo, restaurante_id, nombre, unidad_principal, equivalencia, costo_unitario, stock, stock_minimo, categoria, proveedor_carnihub, activo)
VALUES ('B34','otro',@rest_id,'Percheron Mezcal Noche 65 Ml.','pieza',1.0000,0.00,0.000,0.000,'Bebidas',0,1);

-- ── 4) Garantizar que TODA fila con codigo DPx o Bx tenga categoría correcta
-- (por si en CarniHub la fila ya existía con categoria=NULL).
UPDATE rest_ingredientes
   SET categoria = 'Dulces y Postres'
 WHERE restaurante_id = @rest_id
   AND codigo REGEXP '^DP[0-9]+$'
   AND (categoria IS NULL OR categoria = '');

UPDATE rest_ingredientes
   SET categoria = 'Bebidas'
 WHERE restaurante_id = @rest_id
   AND codigo REGEXP '^B[0-9]+$'
   AND (categoria IS NULL OR categoria = '');

-- ── Fin ─────────────────────────────────────────────────────────────────────
