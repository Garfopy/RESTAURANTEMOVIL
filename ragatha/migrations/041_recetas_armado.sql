-- ============================================================
-- 041_recetas_armado.sql
-- Crea/asegura recetas (con instrucciones de armado en `notas`)
-- y vincula las guarniciones (G1-G21) a cada platillo M1-M35.
--
-- Mapeo extraído de MENU.xlsx (columna "Instrucciones de Armado").
-- Las guarniciones se insertan con es_informativo = 0 → SÍ descuentan
-- stock, pero el descuento real lo dispara el cambio de estado a
-- 'entregado' del pedido (ver RestPedidoModel::cambiarEstadoPedido).
--
-- Requisitos previos: migración 040 aplicada (codigos M y G ya seteados,
-- MP1-MP35 ya creadas).
-- Ejecutar UNA SOLA VEZ. INSERT IGNORE y UPDATE condicional la hacen
-- segura de re-ejecutar.
-- ============================================================

SET @rest_id = 1; -- Cambiar si el restaurante_id es distinto de 1

-- ── 1. Asegurar fila en rest_recetas con instrucciones por defecto ────────

INSERT IGNORE INTO rest_recetas (platillo_id, porciones_base, notas)
SELECT p.id, 1,
  CONCAT('Calentar MP', SUBSTRING(p.codigo, 2),
         '. Montar con las guarniciones indicadas.')
FROM rest_platillos p
WHERE p.restaurante_id = @rest_id
  AND p.codigo REGEXP '^M[0-9]+$';

-- ── 2. Texto específico de "Instrucciones de Armado" por platillo ─────────
-- Sólo se actualiza si la receta no tiene notas (preserva ediciones del chef).

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP1 (Enchiladas Potosinas). Montar con G2 Salsa verde, G4 Crema y G5 Queso rayado.'
 WHERE p.codigo='M1' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP2 (Carnitas). Servir con G1 Tortillas, G3 Salsa roja, G5 Queso rayado, G8 Sal, G9 Guacamole, G10 Rajas, G16 Orégano y G17 Cebolla picada.'
 WHERE p.codigo='M2' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP3 (Barbacoa). Servir con G1 Tortillas, G3 Salsa roja, G8 Sal, G9 Guacamole, G10 Rajas, G16 Orégano y G17 Cebolla picada.'
 WHERE p.codigo='M3' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP4 (Tamal Maíz Rojo Pollo). Acompañar con G3 Salsa roja, G4 Crema y G5 Queso rayado.'
 WHERE p.codigo='M4' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP5 (Tamal Maíz Rojo Carne). Acompañar con G3 Salsa roja, G4 Crema y G5 Queso rayado.'
 WHERE p.codigo='M5' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP6 (Tamal Maíz Verde Pollo). Acompañar con G2 Salsa verde, G4 Crema y G5 Queso rayado.'
 WHERE p.codigo='M6' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP7 (Tamal Maíz Verde Carne). Acompañar con G2 Salsa verde, G4 Crema y G5 Queso rayado.'
 WHERE p.codigo='M7' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP8 (Tamal Oaxaqueño). Servir solo, sin guarnición.'
 WHERE p.codigo='M8' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP9 (Mole Poblano c/ Puerco). Acompañar con G1 Tortillas y G14 Arroz rojo.'
 WHERE p.codigo='M9' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP10 (Mole Poblano c/ Pollo). Acompañar con G1 Tortillas y G14 Arroz rojo.'
 WHERE p.codigo='M10' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP11 (Mole Verde c/ Pollo). Acompañar con G1 Tortillas y G13 Arroz blanco.'
 WHERE p.codigo='M11' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP12 (Mole Verde c/ Carne). Acompañar con G1 Tortillas y G13 Arroz blanco.'
 WHERE p.codigo='M12' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP13 (Mole Oaxaqueño Rojo c/ Carne). Acompañar con G1 Tortillas y G14 Arroz rojo.'
 WHERE p.codigo='M13' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP14 (Mole Oaxaqueño Rojo c/ Pollo). Acompañar con G1 Tortillas y G13 Arroz blanco.'
 WHERE p.codigo='M14' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP15 (Mole Oaxaqueño Negro c/ Carne). Acompañar con G1 Tortillas y G14 Arroz rojo.'
 WHERE p.codigo='M15' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP16 (Mole Oaxaqueño Negro c/ Pollo). Acompañar con G1 Tortillas y G13 Arroz blanco.'
 WHERE p.codigo='M16' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP17 (Mole Oaxaqueño Amarillo c/ Carne). Acompañar con G1 Tortillas y G14 Arroz rojo.'
 WHERE p.codigo='M17' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP18 (Mole Oaxaqueño Amarillo c/ Pollo). Acompañar con G1 Tortillas y G13 Arroz blanco.'
 WHERE p.codigo='M18' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Armar MP19 (Sope de Chorizo) con G3 Salsa roja, G4 Crema, G5 Queso rayado, G7 Frijoles refritos, G12 Chorizo y G17 Cebolla picada.'
 WHERE p.codigo='M19' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Armar MP20 (Sope de Pollo) con G3 Salsa roja, G4 Crema, G5 Queso rayado, G7 Frijoles refritos, G19 Pollo deshebrado y G17 Cebolla picada.'
 WHERE p.codigo='M20' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Armar MP21 (Huarache de Pollo) con G3 Salsa roja, G4 Crema, G5 Queso rayado, G7 Frijoles refritos, G19 Pollo deshebrado y G17 Cebolla picada.'
 WHERE p.codigo='M21' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Armar MP22 (Huarache de Carne) con G3 Salsa roja, G4 Crema, G5 Queso rayado, G7 Frijoles refritos, G20 Carne picada y G17 Cebolla picada.'
 WHERE p.codigo='M22' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP23 (Chilorio). Acompañar con G1 Tortillas, G7 Frijoles refritos y G9 Guacamole.'
 WHERE p.codigo='M23' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Servir MP24 (Tacos de Canasta Deshebrada). Acompañar con G3 Salsa roja y G6 Limones.'
 WHERE p.codigo='M24' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Servir MP25 (Tacos de Canasta de Papa). Acompañar con G3 Salsa roja y G6 Limones.'
 WHERE p.codigo='M25' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Servir MP26 (Tacos de Canasta de Chicharrón). Acompañar con G3 Salsa roja y G6 Limones.'
 WHERE p.codigo='M26' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Servir MP27 (Tacos de Canasta de Frijol). Acompañar con G3 Salsa roja y G6 Limones.'
 WHERE p.codigo='M27' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP28 (Chile Relleno de Queso). Acompañar con G1 Tortillas, G14 Arroz rojo y G7 Frijoles refritos.'
 WHERE p.codigo='M28' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP29 (Chile Relleno de Carne). Acompañar con G1 Tortillas, G14 Arroz rojo y G7 Frijoles refritos.'
 WHERE p.codigo='M29' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP30 (Asadura). Acompañar con G1 Tortillas, G3 Salsa roja y G7 Frijoles refritos.'
 WHERE p.codigo='M30' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP31 (Cochinita Pibil). Acompañar con G1 Tortillas, G7 Frijoles refritos y G21 Cebolla morada.'
 WHERE p.codigo='M31' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP32 (Gorditas de Queso). Acompañar con G3 Salsa roja, G5 Queso rayado, G4 Crema y G9 Guacamole.'
 WHERE p.codigo='M32' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Calentar MP33 (Gorditas de Migajas). Acompañar con G2 Salsa verde, G4 Crema, G5 Queso rayado y G9 Guacamole.'
 WHERE p.codigo='M33' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Servir MP34 (Pozole Blanco) caliente. Acompañar con G1 Tortillas, G6 Limones, G16 Orégano y G17 Cebolla picada.'
 WHERE p.codigo='M34' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

UPDATE rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
   SET r.notas = 'Servir MP35 (Pozole Rojo) caliente. Acompañar con G1 Tortillas, G6 Limones, G16 Orégano y G17 Cebolla picada.'
 WHERE p.codigo='M35' AND p.restaurante_id=@rest_id AND (r.notas IS NULL OR r.notas='');

-- ── 3. Vincular guarniciones a cada receta ─────────────────────────────────
-- Patrón: cada guarnición se inserta con cantidad=1 porción (es_informativo=0
-- → SÍ descuenta stock al entregar el pedido). INSERT IGNORE evita duplicar
-- si el chef ya vinculó manualmente alguna guarnición.

-- M1: G2, G4, G5
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G2','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M1';

-- M2: G1, G3, G5, G8, G9, G10, G16, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G3','G5','G8','G9','G10','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M2';

-- M3: G1, G3, G8, G9, G10, G16, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G3','G8','G9','G10','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M3';

-- M4: G3, G4, G5
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M4';

-- M5: G3, G4, G5
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M5';

-- M6: G2, G4, G5
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G2','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M6';

-- M7: G2, G4, G5
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G2','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M7';

-- M8: sin guarniciones

-- M9: G1, G14
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M9';

-- M10: G1, G14
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M10';

-- M11: G1, G13
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M11';

-- M12: G1, G13
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M12';

-- M13: G1, G14
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M13';

-- M14: G1, G13
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M14';

-- M15: G1, G14
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M15';

-- M16: G1, G13
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M16';

-- M17: G1, G14
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M17';

-- M18: G1, G13
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M18';

-- M19: G3, G4, G5, G7, G12, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G4','G5','G7','G12','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M19';

-- M20: G3, G4, G5, G7, G19, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G4','G5','G7','G19','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M20';

-- M21: G3, G4, G5, G7, G19, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G4','G5','G7','G19','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M21';

-- M22: G3, G4, G5, G7, G20, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G4','G5','G7','G20','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M22';

-- M23: G1, G7, G9
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G7','G9')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M23';

-- M24: G3, G6
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G6')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M24';

-- M25: G3, G6
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G6')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M25';

-- M26: G3, G6
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G6')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M26';

-- M27: G3, G6
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G6')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M27';

-- M28: G1, G14, G7
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14','G7')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M28';

-- M29: G1, G14, G7
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G14','G7')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M29';

-- M30: G1, G3, G7
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G3','G7')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M30';

-- M31: G1, G7, G21
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G7','G21')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M31';

-- M32: G3, G5, G4, G9
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G3','G5','G4','G9')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M32';

-- M33: G2, G4, G5, G9
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G2','G4','G5','G9')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M33';

-- M34: G1, G6, G16, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G6','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M34';

-- M35: G1, G6, G16, G17
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, g.id, 1, COALESCE(g.unidad_principal,'porcion'), 0, 0.00
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = p.restaurante_id AND g.codigo IN ('G1','G6','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.codigo = 'M35';

-- ── Fin ─────────────────────────────────────────────────────────────────────
