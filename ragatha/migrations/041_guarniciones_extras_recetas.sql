-- ============================================================
-- Migration 041: Vínculos de guarniciones y extras a recetas
-- Fuente: rest_receta_ingredientes (1).sql (dump local idactivos_carnihubdb)
-- Usa lookups por `codigo` (G1-G21) para ser agnóstico a IDs
-- EJECUTAR UNA SOLA VEZ en producción (no tiene guard de duplicados)
-- ============================================================

SET @rest_id = 1;

-- Capturar IDs de guarniciones G1-G21 por codigo en producción
SET @g1  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G1'  LIMIT 1);
SET @g2  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G2'  LIMIT 1);
SET @g3  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G3'  LIMIT 1);
SET @g4  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G4'  LIMIT 1);
SET @g5  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G5'  LIMIT 1);
SET @g6  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G6'  LIMIT 1);
SET @g7  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G7'  LIMIT 1);
SET @g8  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G8'  LIMIT 1);
SET @g9  = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G9'  LIMIT 1);
SET @g10 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G10' LIMIT 1);
SET @g11 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G11' LIMIT 1);
SET @g12 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G12' LIMIT 1);
SET @g13 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G13' LIMIT 1);
SET @g14 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G14' LIMIT 1);
SET @g15 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G15' LIMIT 1);
SET @g16 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G16' LIMIT 1);
SET @g17 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G17' LIMIT 1);
SET @g18 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G18' LIMIT 1);
SET @g19 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G19' LIMIT 1);
SET @g20 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G20' LIMIT 1);
SET @g21 = (SELECT id FROM rest_ingredientes WHERE restaurante_id = @rest_id AND codigo = 'G21' LIMIT 1);

-- ============================================================
-- SECCIÓN A: Extras de pago
-- tipo_componente='materia_prima', es_informativo=1, precio_extra>0
-- Son guarniciones opcionales que el cliente puede agregar con costo extra
-- ============================================================

-- G1 (Tortillas de maíz) $15.00
-- Para: Barbacoa, todos los Moles, Chilorio, Chile Rellenos, Asadura, Cochinita Pibil, Pozoles
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g1, 1.000, 'porción', 1, 15.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.',
  'Mole Poblano con Carne de Puerco 250 Gr.',
  'Mole Poblano con Carne de Pollo 250 Gr.',
  'Mole Verde con Pollo 250 Gr.',
  'Mole Verde con Carne 250 Gr.',
  'Mole Oaxaqueño Rojo con Carne 250 Gr.',
  'Mole Oaxaqueño Rojo con Pollo 250 Gr.',
  'Mole Oaxaqueño Negro con Carne 250 Gr.',
  'Mole Oaxaqueño Negro con Pollo 250 Gr.',
  'Mole Oaxaqueño Amarillo con Carne 250 Gr.',
  'Mole Oaxaqueño Amarillo con Pollo 250 Gr.',
  'Chilorio 250 Gr.',
  'Chile Relleno de Queso 1 Pza.',
  'Chile Relleno de Carne 1 Pza.',
  'Asadura 250 Gr.',
  'Cochinita Pibil 250 Gr.',
  'Pozole Blanco 300 Gr.',
  'Pozole Rojo 300 Gr.'
);

-- G2 (Salsa Verde) $10.00
-- Para: Enchiladas Potosinas, Tamales Verde, Gorditas de Migajas
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g2, 1.000, 'ml', 1, 10.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Enchiladas Potosinas 3 Pzs.',
  'Tamal de Maíz Verde de Pollo 2 Pzs.',
  'Tamal de Maíz Verde de Carne 2 Pzs.',
  'Gorditas de Migajas 3 Pzs.'
);

-- G3 (Salsa Roja) $10.00
-- Para: Barbacoa, Tamales Rojo, Sopes, Huaraches, Tacos de Canasta, Asadura
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g3, 1.000, 'ml', 1, 10.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.',
  'Tamal de Maíz Rojo de Pollo 2 Pzs.',
  'Tamal de Maíz Rojo de Carne 2 Pzs.',
  'Sope de Chorizo 3 Pzs.',
  'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.',
  'Huarache de Carne 1 Pza.',
  'Tacos de Canasta Deshebrada 3 Pzs.',
  'Tacos de Canasta de Papa 3 Pzs.',
  'Tacos de Canasta de Chicharrón 3 Pzs.',
  'Tacos de Canasta de Frijol 3 Pzs.',
  'Asadura 250 Gr.'
);

-- G4 (Crema) $15.00
-- Para: Enchiladas, Tamales Rojo y Verde, Sopes, Huaraches, Gorditas
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g4, 1.000, 'ml', 1, 15.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Enchiladas Potosinas 3 Pzs.',
  'Tamal de Maíz Rojo de Pollo 2 Pzs.',
  'Tamal de Maíz Rojo de Carne 2 Pzs.',
  'Tamal de Maíz Verde de Pollo 2 Pzs.',
  'Tamal de Maíz Verde de Carne 2 Pzs.',
  'Sope de Chorizo 3 Pzs.',
  'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.',
  'Huarache de Carne 1 Pza.',
  'Gorditas de Queso 3 Pzs.',
  'Gorditas de Migajas 3 Pzs.'
);

-- G5 (Queso Rayado) $20.00
-- Para: Enchiladas, Tamales Rojo y Verde, Sopes, Huaraches, Gorditas de Queso
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g5, 1.000, 'gr', 1, 20.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Enchiladas Potosinas 3 Pzs.',
  'Tamal de Maíz Rojo de Pollo 2 Pzs.',
  'Tamal de Maíz Rojo de Carne 2 Pzs.',
  'Tamal de Maíz Verde de Pollo 2 Pzs.',
  'Tamal de Maíz Verde de Carne 2 Pzs.',
  'Sope de Chorizo 3 Pzs.',
  'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.',
  'Huarache de Carne 1 Pza.',
  'Gorditas de Queso 3 Pzs.'
);

-- G6 (Limones) $10.00
-- Para: Tacos de Canasta (todos), Pozole Blanco, Pozole Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g6, 1.000, 'pza', 1, 10.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Tacos de Canasta Deshebrada 3 Pzs.',
  'Tacos de Canasta de Papa 3 Pzs.',
  'Tacos de Canasta de Chicharrón 3 Pzs.',
  'Tacos de Canasta de Frijol 3 Pzs.',
  'Pozole Blanco 300 Gr.',
  'Pozole Rojo 300 Gr.'
);

-- G7 (Frijoles Refritos) $25.00
-- Para: Sopes, Huaraches, Chilorio, Chile Rellenos, Asadura, Cochinita Pibil
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g7, 1.000, 'paquete', 1, 25.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Sope de Chorizo 3 Pzs.',
  'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.',
  'Huarache de Carne 1 Pza.',
  'Chilorio 250 Gr.',
  'Chile Relleno de Queso 1 Pza.',
  'Chile Relleno de Carne 1 Pza.',
  'Asadura 250 Gr.',
  'Cochinita Pibil 250 Gr.'
);

-- G8 (Sal de Mar) $5.00
-- Para: Barbacoa
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g8, 1.000, 'gr', 1, 5.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.'
);

-- G9 (Guacamole) $35.00
-- Para: Barbacoa, Chilorio, Gorditas de Queso, Gorditas de Migajas
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g9, 1.000, 'gr', 1, 35.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.',
  'Chilorio 250 Gr.',
  'Gorditas de Queso 3 Pzs.',
  'Gorditas de Migajas 3 Pzs.'
);

-- G10 (Rajas) $20.00
-- Para: Barbacoa
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g10, 1.000, 'gr', 1, 20.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.'
);

-- G12 (Chorizo) $35.00
-- Para: Sope de Chorizo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g12, 1.000, 'gr', 1, 35.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Sope de Chorizo 3 Pzs.'
);

-- G13 (Arroz Blanco) $25.00
-- Para: Mole Verde con Pollo/Carne, Mole Oaxaqueño Rojo/Negro/Amarillo con Pollo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g13, 1.000, 'gr', 1, 25.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Mole Verde con Pollo 250 Gr.',
  'Mole Verde con Carne 250 Gr.',
  'Mole Oaxaqueño Rojo con Pollo 250 Gr.',
  'Mole Oaxaqueño Negro con Pollo 250 Gr.',
  'Mole Oaxaqueño Amarillo con Pollo 250 Gr.'
);

-- G14 (Arroz Rojo) $25.00
-- Para: Moles Poblano, Mole Oaxaqueño Rojo/Negro/Amarillo con Carne, Chile Rellenos
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g14, 1.000, 'paquete', 1, 25.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Mole Poblano con Carne de Puerco 250 Gr.',
  'Mole Poblano con Carne de Pollo 250 Gr.',
  'Mole Oaxaqueño Rojo con Carne 250 Gr.',
  'Mole Oaxaqueño Negro con Carne 250 Gr.',
  'Mole Oaxaqueño Amarillo con Carne 250 Gr.',
  'Chile Relleno de Queso 1 Pza.',
  'Chile Relleno de Carne 1 Pza.'
);

-- G16 (Orégano) $5.00
-- Para: Barbacoa, Pozole Blanco, Pozole Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g16, 1.000, 'gr', 1, 5.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.',
  'Pozole Blanco 300 Gr.',
  'Pozole Rojo 300 Gr.'
);

-- G17 (Cebolla Picada) $10.00
-- Para: Barbacoa, Sopes, Huaraches, Pozoles
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g17, 1.000, 'gr', 1, 10.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Barbacoa 150 Gr.',
  'Sope de Chorizo 3 Pzs.',
  'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.',
  'Huarache de Carne 1 Pza.',
  'Pozole Blanco 300 Gr.',
  'Pozole Rojo 300 Gr.'
);

-- G19 (Pollo Deshebrado) $40.00
-- Para: Sope de Pollo, Huarache de Pollo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g19, 1.000, 'gr', 1, 40.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.'
);

-- G20 (Carne Picada) $40.00
-- Para: Huarache de Carne
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g20, 1.000, 'gr', 1, 40.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Huarache de Carne 1 Pza.'
);

-- G21 (Cebolla Morada Desflmada) $10.00
-- Para: Cochinita Pibil
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, @g21, 1.000, 'gr', 1, 10.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Cochinita Pibil 250 Gr.'
);

-- ============================================================
-- SECCIÓN B: Guarniciones incluidas gratis (complementos estándar)
-- tipo_componente='guarnicion', es_informativo=1, precio_extra=0.00
-- Vienen con el platillo sin costo adicional
-- ============================================================

-- Carnitas: incluye G1, G3, G8, G9, G10, G16, G17
-- El JOIN con rest_ingredientes genera una fila por cada guarnicion
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente, codigo_display)
SELECT r.id, g.id, 1.000, 'g', 1, 0.00, 'guarnicion', g.codigo
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G3','G8','G9','G10','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Carnitas 150 Gr.';

-- ============================================================
-- SECCIÓN C: Ingredientes de stock (descuento de inventario)
-- tipo_componente='materia_prima', es_informativo=0, precio_extra=0.00
-- Materias primas que se descuentan del inventario al preparar el platillo
-- Fuente: dump local, recetas 2-22 (M1-M21)
-- ============================================================

-- M1 (Enchiladas Potosinas): G2 Salsa Verde, G4 Crema, G5 Queso Rayado
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G2' THEN 'ml' WHEN 'G4' THEN 'ml' WHEN 'G5' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G2','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Enchiladas Potosinas 3 Pzs.';

-- M2 (Carnitas): G1 Tortillas, G3 Salsa Roja, G5 Queso, G8 Sal de Mar,
--                G9 Guacamole, G10 Rajas, G16 Orégano, G17 Cebolla Picada
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo
    WHEN 'G1'  THEN 'porción'
    WHEN 'G3'  THEN 'ml'
    WHEN 'G5'  THEN 'gr'
    WHEN 'G8'  THEN 'gr'
    WHEN 'G9'  THEN 'gr'
    WHEN 'G10' THEN 'gr'
    WHEN 'G16' THEN 'gr'
    WHEN 'G17' THEN 'gr'
  END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G3','G5','G8','G9','G10','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Carnitas 150 Gr.';

-- M3 (Barbacoa): G1 Tortillas, G3 Salsa Roja, G8 Sal de Mar,
--                G9 Guacamole, G10 Rajas, G16 Orégano, G17 Cebolla Picada
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo
    WHEN 'G1'  THEN 'porción'
    WHEN 'G3'  THEN 'ml'
    WHEN 'G8'  THEN 'gr'
    WHEN 'G9'  THEN 'gr'
    WHEN 'G10' THEN 'gr'
    WHEN 'G16' THEN 'gr'
    WHEN 'G17' THEN 'gr'
  END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G3','G8','G9','G10','G16','G17')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Barbacoa 150 Gr.';

-- M4 (Tamal de Maíz Rojo de Pollo): G3 Salsa Roja, G4 Crema, G5 Queso Rayado
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G3' THEN 'ml' WHEN 'G4' THEN 'ml' WHEN 'G5' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G3','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Rojo de Pollo 2 Pzs.';

-- M5 (Tamal de Maíz Rojo de Carne): G3 Salsa Roja, G4 Crema, G5 Queso Rayado
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G3' THEN 'ml' WHEN 'G4' THEN 'ml' WHEN 'G5' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G3','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Rojo de Carne 2 Pzs.';

-- M6 (Tamal de Maíz Verde de Pollo): G2 Salsa Verde, G4 Crema, G5 Queso Rayado
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G2' THEN 'ml' WHEN 'G4' THEN 'ml' WHEN 'G5' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G2','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Verde de Pollo 2 Pzs.';

-- M7 (Tamal de Maíz Verde de Carne): G2 Salsa Verde, G4 Crema, G5 Queso Rayado
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G2' THEN 'ml' WHEN 'G4' THEN 'ml' WHEN 'G5' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G2','G4','G5')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Tamal de Maíz Verde de Carne 2 Pzs.';

-- M8 (Tamal Oaxaqueño): sin ingredientes de stock en el dump local

-- M9 (Mole Poblano con Carne de Puerco): G1 Tortillas, G14 Arroz Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G14' THEN 'paquete' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Poblano con Carne de Puerco 250 Gr.';

-- M10 (Mole Poblano con Carne de Pollo): G1 Tortillas, G14 Arroz Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G14' THEN 'paquete' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Poblano con Carne de Pollo 250 Gr.';

-- M11 (Mole Verde con Pollo): G1 Tortillas, G13 Arroz Blanco
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G13' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Verde con Pollo 250 Gr.';

-- M12 (Mole Verde con Carne): G1 Tortillas, G13 Arroz Blanco
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G13' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Verde con Carne 250 Gr.';

-- M13 (Mole Oaxaqueño Rojo con Carne): G1 Tortillas, G14 Arroz Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G14' THEN 'paquete' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Rojo con Carne 250 Gr.';

-- M14 (Mole Oaxaqueño Rojo con Pollo): G1 Tortillas, G13 Arroz Blanco
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G13' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Rojo con Pollo 250 Gr.';

-- M15 (Mole Oaxaqueño Negro con Carne): G1 Tortillas, G14 Arroz Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G14' THEN 'paquete' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Negro con Carne 250 Gr.';

-- M16 (Mole Oaxaqueño Negro con Pollo): G1 Tortillas, G13 Arroz Blanco
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G13' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Negro con Pollo 250 Gr.';

-- M17 (Mole Oaxaqueño Amarillo con Carne): G1 Tortillas, G14 Arroz Rojo
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G14' THEN 'paquete' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G14')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Amarillo con Carne 250 Gr.';

-- M18 (Mole Oaxaqueño Amarillo con Pollo): G1 Tortillas, G13 Arroz Blanco
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo WHEN 'G1' THEN 'porción' WHEN 'G13' THEN 'gr' END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G1','G13')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Mole Oaxaqueño Amarillo con Pollo 250 Gr.';

-- M19 (Sope de Chorizo): G3 Salsa Roja, G4 Crema, G5 Queso,
--                        G7 Frijoles Refritos, G12 Chorizo, G17 Cebolla Picada
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo
    WHEN 'G3'  THEN 'ml'
    WHEN 'G4'  THEN 'ml'
    WHEN 'G5'  THEN 'gr'
    WHEN 'G7'  THEN 'paquete'
    WHEN 'G12' THEN 'gr'
    WHEN 'G17' THEN 'gr'
  END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G3','G4','G5','G7','G12','G17')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Sope de Chorizo 3 Pzs.';

-- M20 (Sope de Pollo): G3 Salsa Roja, G4 Crema, G5 Queso,
--                     G7 Frijoles Refritos, G17 Cebolla Picada, G19 Pollo Deshebrado
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo
    WHEN 'G3'  THEN 'ml'
    WHEN 'G4'  THEN 'ml'
    WHEN 'G5'  THEN 'gr'
    WHEN 'G7'  THEN 'paquete'
    WHEN 'G17' THEN 'gr'
    WHEN 'G19' THEN 'gr'
  END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G3','G4','G5','G7','G17','G19')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Sope de Pollo 3 Pzs.';

-- M21 (Huarache de Pollo): G3 Salsa Roja, G4 Crema, G5 Queso,
--                          G7 Frijoles Refritos, G17 Cebolla Picada
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra, tipo_componente)
SELECT r.id, g.id, 1.000,
  CASE g.codigo
    WHEN 'G3'  THEN 'ml'
    WHEN 'G4'  THEN 'ml'
    WHEN 'G5'  THEN 'gr'
    WHEN 'G7'  THEN 'paquete'
    WHEN 'G17' THEN 'gr'
  END,
  0, 0.00, 'materia_prima'
FROM rest_recetas r
JOIN rest_platillos p ON p.id = r.platillo_id
JOIN rest_ingredientes g ON g.restaurante_id = @rest_id AND g.codigo IN ('G3','G4','G5','G7','G17')
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Huarache de Pollo 1 Pza.';

-- M22-M35: sin datos de ingredientes de stock en el dump local
