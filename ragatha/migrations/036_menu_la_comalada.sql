-- ============================================================
-- 036_menu_la_comalada.sql
-- Menú completo: La Comalada
-- Secciones: Comida (35) · Dulces y Postres (18) · Bebidas (34)
-- Guarniciones: 21 · Recetas vinculadas según códigos IA del XLSX
-- Precios estimados — ajustar desde el admin (rest-menu/index)
-- ============================================================

-- ── 2. Categorías del menú ──────────────────────────────────────────────────

SET @rest_id = 1;

INSERT IGNORE INTO `rest_categorias_menu`
  (restaurante_id, nombre, descripcion, orden, activo)
VALUES
  (@rest_id, 'Comida',          'Platillos tradicionales mexicanos',    1, 1),
  (@rest_id, 'Dulces y Postres','Dulces artesanales y postres regionales', 2, 1),
  (@rest_id, 'Bebidas',        'Mezcales, tequilas, cocktails y más',  3, 1);

SET @cat_comida   = (SELECT id FROM rest_categorias_menu WHERE restaurante_id = @rest_id AND nombre = 'Comida'          LIMIT 1);
SET @cat_postres  = (SELECT id FROM rest_categorias_menu WHERE restaurante_id = @rest_id AND nombre = 'Dulces y Postres' LIMIT 1);
SET @cat_bebidas  = (SELECT id FROM rest_categorias_menu WHERE restaurante_id = @rest_id AND nombre = 'Bebidas'         LIMIT 1);

-- ── 3. Guarniciones como ingredientes ──────────────────────────────────────
-- (G1–G21, unidad_principal='porción', stock alto para items de servicio)

INSERT IGNORE INTO `rest_ingredientes`
  (restaurante_id, nombre, unidad_principal, stock, stock_minimo, costo_unitario, activo)
VALUES
  (@rest_id, 'Tortillas de maíz',         'porción', 999, 0,  8.00, 1),  -- G1
  (@rest_id, 'Salsa verde',               'ml',      999, 0,  4.00, 1),  -- G2
  (@rest_id, 'Salsa roja',                'ml',      999, 0,  4.00, 1),  -- G3
  (@rest_id, 'Crema',                     'ml',      999, 0,  6.00, 1),  -- G4
  (@rest_id, 'Queso rayado',              'gr',      999, 0, 12.00, 1),  -- G5
  (@rest_id, 'Limones',                   'pza',     999, 0,  1.50, 1),  -- G6
  (@rest_id, 'Frijoles refritos',         'gr',      999, 0,  8.00, 1),  -- G7
  (@rest_id, 'Sal de mar',                'gr',      999, 0,  0.50, 1),  -- G8
  (@rest_id, 'Guacamole',                 'gr',      999, 0, 18.00, 1),  -- G9
  (@rest_id, 'Rajas',                     'gr',      999, 0,  6.00, 1),  -- G10
  (@rest_id, 'Salsa chipotle',            'gr',      999, 0,  5.00, 1),  -- G11
  (@rest_id, 'Chorizo',                   'gr',      999, 0, 22.00, 1),  -- G12
  (@rest_id, 'Arroz blanco',              'gr',      999, 0,  6.00, 1),  -- G13
  (@rest_id, 'Arroz rojo',                'gr',      999, 0,  6.00, 1),  -- G14
  (@rest_id, 'Azúcar',                    'gr',      999, 0,  0.50, 1),  -- G15
  (@rest_id, 'Orégano',                   'gr',      999, 0,  0.50, 1),  -- G16
  (@rest_id, 'Cebolla picada',            'gr',      999, 0,  1.00, 1),  -- G17
  (@rest_id, 'Cilantro picado',           'gr',      999, 0,  1.50, 1),  -- G18
  (@rest_id, 'Pollo deshebrado',          'gr',      999, 0, 20.00, 1),  -- G19
  (@rest_id, 'Carne picada',              'gr',      999, 0, 22.00, 1),  -- G20
  (@rest_id, 'Cebolla morada',            'gr',      999, 0,  2.00, 1);  -- G21

-- Capturar IDs de guarniciones para usarlos en los vínculos
SET @g1  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Tortillas de maíz'   LIMIT 1);
SET @g2  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Salsa verde'         LIMIT 1);
SET @g3  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Salsa roja'          LIMIT 1);
SET @g4  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Crema'               LIMIT 1);
SET @g5  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Queso rayado'        LIMIT 1);
SET @g6  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Limones'             LIMIT 1);
SET @g7  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Frijoles refritos'   LIMIT 1);
SET @g8  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Sal de mar'          LIMIT 1);
SET @g9  = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Guacamole'           LIMIT 1);
SET @g10 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Rajas'               LIMIT 1);
SET @g12 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Chorizo'             LIMIT 1);
SET @g13 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Arroz blanco'        LIMIT 1);
SET @g14 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Arroz rojo'          LIMIT 1);
SET @g16 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Orégano'             LIMIT 1);
SET @g17 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Cebolla picada'      LIMIT 1);
SET @g19 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Pollo deshebrado'    LIMIT 1);
SET @g20 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Carne picada'        LIMIT 1);
SET @g21 = (SELECT id FROM rest_ingredientes WHERE restaurante_id=@rest_id AND nombre='Cebolla morada'      LIMIT 1);

-- ── 4. Platillos — Comida ───────────────────────────────────────────────────

INSERT IGNORE INTO `rest_platillos`
  (restaurante_id, categoria_id, nombre, precio, tiempo_preparacion_min, disponible, activo)
VALUES
  -- M1–M8: Enchiladas, Carnitas, Barbacoa, Tamales
  (@rest_id, @cat_comida, 'Enchiladas Potosinas 3 Pzs.',              120.00, 12, 1, 1),
  (@rest_id, @cat_comida, 'Carnitas 150 Gr.',                         130.00, 10, 1, 1),
  (@rest_id, @cat_comida, 'Barbacoa 150 Gr.',                         130.00, 10, 1, 1),
  (@rest_id, @cat_comida, 'Tamal de Maíz Rojo de Pollo 2 Pzs.',       85.00, 15, 1, 1),
  (@rest_id, @cat_comida, 'Tamal de Maíz Rojo de Carne 2 Pzs.',       95.00, 15, 1, 1),
  (@rest_id, @cat_comida, 'Tamal de Maíz Verde de Pollo 2 Pzs.',      85.00, 15, 1, 1),
  (@rest_id, @cat_comida, 'Tamal de Maíz Verde de Carne 2 Pzs.',      95.00, 15, 1, 1),
  (@rest_id, @cat_comida, 'Tamal Oaxaqueño 1 Pza.',                    80.00, 15, 1, 1),
  -- M9–M18: Moles
  (@rest_id, @cat_comida, 'Mole Poblano con Carne de Puerco 250 Gr.', 175.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Poblano con Carne de Pollo 250 Gr.',  165.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Verde con Pollo 250 Gr.',             165.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Verde con Carne 250 Gr.',             175.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Oaxaqueño Rojo con Carne 250 Gr.',    185.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Oaxaqueño Rojo con Pollo 250 Gr.',    175.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Oaxaqueño Negro con Carne 250 Gr.',   185.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Oaxaqueño Negro con Pollo 250 Gr.',   175.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Oaxaqueño Amarillo con Carne 250 Gr.',185.00, 20, 1, 1),
  (@rest_id, @cat_comida, 'Mole Oaxaqueño Amarillo con Pollo 250 Gr.',175.00, 20, 1, 1),
  -- M19–M22: Sopes y Huaraches
  (@rest_id, @cat_comida, 'Sope de Chorizo 3 Pzs.',                   115.00, 12, 1, 1),
  (@rest_id, @cat_comida, 'Sope de Pollo 3 Pzs.',                     105.00, 12, 1, 1),
  (@rest_id, @cat_comida, 'Huarache de Pollo 1 Pza.',                 110.00, 15, 1, 1),
  (@rest_id, @cat_comida, 'Huarache de Carne 1 Pza.',                 120.00, 15, 1, 1),
  -- M23: Chilorio
  (@rest_id, @cat_comida, 'Chilorio 250 Gr.',                         145.00, 10, 1, 1),
  -- M24–M27: Tacos de canasta
  (@rest_id, @cat_comida, 'Tacos de Canasta Deshebrada 3 Pzs.',        85.00,  8, 1, 1),
  (@rest_id, @cat_comida, 'Tacos de Canasta de Papa 3 Pzs.',           75.00,  8, 1, 1),
  (@rest_id, @cat_comida, 'Tacos de Canasta de Chicharrón 3 Pzs.',     85.00,  8, 1, 1),
  (@rest_id, @cat_comida, 'Tacos de Canasta de Frijol 3 Pzs.',         70.00,  8, 1, 1),
  -- M28–M29: Chiles rellenos
  (@rest_id, @cat_comida, 'Chile Relleno de Queso 1 Pza.',             95.00, 15, 1, 1),
  (@rest_id, @cat_comida, 'Chile Relleno de Carne 1 Pza.',            105.00, 15, 1, 1),
  -- M30–M31: Asadura, Cochinita
  (@rest_id, @cat_comida, 'Asadura 250 Gr.',                          135.00, 10, 1, 1),
  (@rest_id, @cat_comida, 'Cochinita Pibil 250 Gr.',                  145.00, 10, 1, 1),
  -- M32–M33: Gorditas
  (@rest_id, @cat_comida, 'Gorditas de Queso 3 Pzs.',                  90.00, 12, 1, 1),
  (@rest_id, @cat_comida, 'Gorditas de Migajas 3 Pzs.',                90.00, 12, 1, 1),
  -- M34–M35: Pozoles
  (@rest_id, @cat_comida, 'Pozole Blanco 300 Gr.',                    155.00, 18, 1, 1),
  (@rest_id, @cat_comida, 'Pozole Rojo 300 Gr.',                      155.00, 18, 1, 1);

-- ── 5. Platillos — Dulces y Postres ────────────────────────────────────────

INSERT IGNORE INTO `rest_platillos`
  (restaurante_id, categoria_id, nombre, precio, tiempo_preparacion_min, disponible, activo)
VALUES
  (@rest_id, @cat_postres, 'Ate de Guayaba con Queso 6 Pzs.',          70.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Ate de Membrillo con Queso 6 Pzs.',        70.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Glorias de Leche Quemada 3 Pzs.',          65.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Obleas de Cajeta 3 Pzs.',                  55.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Camote de Puebla 3 Pzs.',                  60.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Borrachitos de Fresa 5 Pzs.',              65.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Fruta Cristalizada Higo 150 Gr.',          75.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Fruta Cristalizada Pera 150 Gr.',          75.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Fruta Cristalizada Calabazete 150 Gr.',    75.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Orejonas 150 Gr.',                         70.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Cocadas 3 Pzs.',                           60.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Palanquetas 1 Pza.',                       55.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Merengues 3 Pzs.',                         60.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Natillas Qro. 150 Gr.',                    65.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Natillas Bernal 4 Pzs.',                   70.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Ollitas de Tamarindo 3 Pzs.',              55.00, 3, 1, 1),
  (@rest_id, @cat_postres, 'Tamal de Maíz Dulce de Piña 2 Pzs.',       70.00, 5, 1, 1),
  (@rest_id, @cat_postres, 'Tamal de Dulce de Fresa 2 Pzs.',           70.00, 5, 1, 1);

-- ── 6. Platillos — Bebidas ─────────────────────────────────────────────────

INSERT IGNORE INTO `rest_platillos`
  (restaurante_id, categoria_id, nombre, precio, tiempo_preparacion_min, disponible, activo)
VALUES
  -- Mezcales
  (@rest_id, @cat_bebidas, 'Mezcal Sol 2 Oz.',                         90.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Mezcal Luna 2 Oz.',                        95.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Mezcal Orgullo 2 Oz.',                    100.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Mezcal Noche 2 Oz.',                      100.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Mezcal Amor 2 Oz.',                        95.00, 2, 1, 1),
  -- Tequilas
  (@rest_id, @cat_bebidas, 'Tequila Blanco 2 Oz.',                     80.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Tequila Reposado 2 Oz.',                   90.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Tequila Añejo 2 Oz.',                     100.00, 2, 1, 1),
  -- Cervezas artesanales
  (@rest_id, @cat_bebidas, 'Cerveza Artesanal Clara',                   75.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Cerveza Artesanal Morena',                  75.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Cerveza Artesanal Oscura',                  80.00, 2, 1, 1),
  -- Cocktails Mezcal
  (@rest_id, @cat_bebidas, 'Cocktail de Mezcal con Tamarindo',         120.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Cocktail de Mezcal con Jamaica',           120.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Cocktail de Mezcal Margarita',             125.00, 5, 1, 1),
  -- Cocktails Tequila
  (@rest_id, @cat_bebidas, 'Cocktail de Tequila con Tamarindo',        110.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Coctel de Tequila con Jamaica',            110.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Cocktail de Tequila Margarita',            115.00, 5, 1, 1),
  -- Cafés y calientes
  (@rest_id, @cat_bebidas, 'Café de Olla',                              45.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Carajillo sin Cafeína Amanecer',            55.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Carajillo sin Cafeína Anochecer',           55.00, 5, 1, 1),
  -- Aguas
  (@rest_id, @cat_bebidas, 'Agua de Horchata',                          40.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Agua de Jamaica',                           40.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Agua de Tamarindo',                         40.00, 2, 1, 1),
  -- Chocolates y Atoles
  (@rest_id, @cat_bebidas, 'Chocolate con Agua',                        50.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Chocolate con Leche',                       55.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Atole de Fresa',                            50.00, 5, 1, 1),
  (@rest_id, @cat_bebidas, 'Atole de Vainilla',                         50.00, 5, 1, 1),
  -- Aguas naturales
  (@rest_id, @cat_bebidas, 'Agua Mineral',                              35.00, 1, 1, 1),
  (@rest_id, @cat_bebidas, 'Agua Sola',                                 25.00, 1, 1, 1),
  -- Percherones (mezcal 65 ml)
  (@rest_id, @cat_bebidas, 'Percherón Mezcal Sol 65 Ml.',              160.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Percherón Mezcal Luna 65 Ml.',             170.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Percherón Mezcal Orgullo 65 Ml.',          180.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Percherón Mezcal Amor 65 Ml.',             170.00, 2, 1, 1),
  (@rest_id, @cat_bebidas, 'Percherón Mezcal Noche 65 Ml.',            180.00, 2, 1, 1);

-- ── 7. Recetas — una por cada platillo recién insertado ─────────────────────
-- (solo platillos sin receta previa, evita duplicados)

INSERT IGNORE INTO `rest_recetas` (platillo_id, porciones_base, notas)
SELECT p.id, 1, NULL
FROM rest_platillos p
WHERE p.restaurante_id = @rest_id
  AND NOT EXISTS (SELECT 1 FROM rest_recetas r WHERE r.platillo_id = p.id);

-- ── 8. Vínculos receta ↔ guarnición ────────────────────────────────────────
-- Estilo: es_informativo=1 (no descuenta stock), precio_extra = costo porción extra

-- ─── G1 · Tortillas de maíz · +$15 ────────────────────────────────────────
-- M2,M3,M9–M18,M23,M28,M29,M30,M31,M34,M35
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g1, 1, 'porción', 1, 15.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.',
  'Mole Poblano con Carne de Puerco 250 Gr.', 'Mole Poblano con Carne de Pollo 250 Gr.',
  'Mole Verde con Pollo 250 Gr.', 'Mole Verde con Carne 250 Gr.',
  'Mole Oaxaqueño Rojo con Carne 250 Gr.', 'Mole Oaxaqueño Rojo con Pollo 250 Gr.',
  'Mole Oaxaqueño Negro con Carne 250 Gr.', 'Mole Oaxaqueño Negro con Pollo 250 Gr.',
  'Mole Oaxaqueño Amarillo con Carne 250 Gr.', 'Mole Oaxaqueño Amarillo con Pollo 250 Gr.',
  'Chilorio 250 Gr.',
  'Chile Relleno de Queso 1 Pza.', 'Chile Relleno de Carne 1 Pza.',
  'Asadura 250 Gr.', 'Cochinita Pibil 250 Gr.',
  'Pozole Blanco 300 Gr.', 'Pozole Rojo 300 Gr.'
);

-- ─── G2 · Salsa verde · +$10 ───────────────────────────────────────────────
-- M1,M6,M7,M33
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g2, 1, 'porción', 1, 10.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Enchiladas Potosinas 3 Pzs.',
  'Tamal de Maíz Verde de Pollo 2 Pzs.', 'Tamal de Maíz Verde de Carne 2 Pzs.',
  'Gorditas de Migajas 3 Pzs.'
);

-- ─── G3 · Salsa roja · +$10 ────────────────────────────────────────────────
-- M2,M3,M4,M5,M19,M20,M21,M22,M24,M25,M26,M27,M30
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g3, 1, 'porción', 1, 10.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.',
  'Tamal de Maíz Rojo de Pollo 2 Pzs.', 'Tamal de Maíz Rojo de Carne 2 Pzs.',
  'Sope de Chorizo 3 Pzs.', 'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.', 'Huarache de Carne 1 Pza.',
  'Tacos de Canasta Deshebrada 3 Pzs.', 'Tacos de Canasta de Papa 3 Pzs.',
  'Tacos de Canasta de Chicharrón 3 Pzs.', 'Tacos de Canasta de Frijol 3 Pzs.',
  'Asadura 250 Gr.'
);

-- ─── G4 · Crema · +$15 ─────────────────────────────────────────────────────
-- M1,M4,M5,M6,M7,M19,M20,M21,M22,M32,M33
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g4, 1, 'porción', 1, 15.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Enchiladas Potosinas 3 Pzs.',
  'Tamal de Maíz Rojo de Pollo 2 Pzs.', 'Tamal de Maíz Rojo de Carne 2 Pzs.',
  'Tamal de Maíz Verde de Pollo 2 Pzs.', 'Tamal de Maíz Verde de Carne 2 Pzs.',
  'Sope de Chorizo 3 Pzs.', 'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.', 'Huarache de Carne 1 Pza.',
  'Gorditas de Queso 3 Pzs.', 'Gorditas de Migajas 3 Pzs.'
);

-- ─── G5 · Queso rayado · +$20 ──────────────────────────────────────────────
-- M1,M4,M5,M6,M7,M19,M20,M21,M22,M32
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g5, 1, 'porción', 1, 20.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Enchiladas Potosinas 3 Pzs.',
  'Tamal de Maíz Rojo de Pollo 2 Pzs.', 'Tamal de Maíz Rojo de Carne 2 Pzs.',
  'Tamal de Maíz Verde de Pollo 2 Pzs.', 'Tamal de Maíz Verde de Carne 2 Pzs.',
  'Sope de Chorizo 3 Pzs.', 'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.', 'Huarache de Carne 1 Pza.',
  'Gorditas de Queso 3 Pzs.'
);

-- ─── G6 · Limones · +$10 ───────────────────────────────────────────────────
-- M24,M25,M26,M27,M34,M35
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g6, 1, 'porción', 1, 10.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Tacos de Canasta Deshebrada 3 Pzs.', 'Tacos de Canasta de Papa 3 Pzs.',
  'Tacos de Canasta de Chicharrón 3 Pzs.', 'Tacos de Canasta de Frijol 3 Pzs.',
  'Pozole Blanco 300 Gr.', 'Pozole Rojo 300 Gr.'
);

-- ─── G7 · Frijoles refritos · +$25 ─────────────────────────────────────────
-- M19,M20,M21,M22,M23,M28,M29,M30,M31
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g7, 1, 'porción', 1, 25.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Sope de Chorizo 3 Pzs.', 'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.', 'Huarache de Carne 1 Pza.',
  'Chilorio 250 Gr.',
  'Chile Relleno de Queso 1 Pza.', 'Chile Relleno de Carne 1 Pza.',
  'Asadura 250 Gr.', 'Cochinita Pibil 250 Gr.'
);

-- ─── G8 · Sal de mar · +$5 ─────────────────────────────────────────────────
-- M2, M3
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g8, 1, 'porción', 1, 5.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.'
);

-- ─── G9 · Guacamole · +$35 ─────────────────────────────────────────────────
-- M2,M3,M23,M32,M33
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g9, 1, 'porción', 1, 35.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.',
  'Chilorio 250 Gr.',
  'Gorditas de Queso 3 Pzs.', 'Gorditas de Migajas 3 Pzs.'
);

-- ─── G10 · Rajas · +$20 ────────────────────────────────────────────────────
-- M2, M3
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g10, 1, 'porción', 1, 20.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.'
);

-- ─── G12 · Chorizo · +$35 ──────────────────────────────────────────────────
-- M19
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g12, 1, 'porción', 1, 35.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Sope de Chorizo 3 Pzs.';

-- ─── G13 · Arroz blanco · +$25 ─────────────────────────────────────────────
-- M11,M12,M14,M16,M18
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g13, 1, 'porción', 1, 25.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Mole Verde con Pollo 250 Gr.', 'Mole Verde con Carne 250 Gr.',
  'Mole Oaxaqueño Rojo con Pollo 250 Gr.',
  'Mole Oaxaqueño Negro con Pollo 250 Gr.',
  'Mole Oaxaqueño Amarillo con Pollo 250 Gr.'
);

-- ─── G14 · Arroz rojo · +$25 ───────────────────────────────────────────────
-- M9,M10,M13,M15,M17,M28,M29
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g14, 1, 'porción', 1, 25.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Mole Poblano con Carne de Puerco 250 Gr.', 'Mole Poblano con Carne de Pollo 250 Gr.',
  'Mole Oaxaqueño Rojo con Carne 250 Gr.',
  'Mole Oaxaqueño Negro con Carne 250 Gr.',
  'Mole Oaxaqueño Amarillo con Carne 250 Gr.',
  'Chile Relleno de Queso 1 Pza.', 'Chile Relleno de Carne 1 Pza.'
);

-- ─── G16 · Orégano · +$5 ───────────────────────────────────────────────────
-- M2,M3,M34,M35
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g16, 1, 'porción', 1, 5.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.',
  'Pozole Blanco 300 Gr.', 'Pozole Rojo 300 Gr.'
);

-- ─── G17 · Cebolla picada · +$10 ───────────────────────────────────────────
-- M2,M3,M19,M20,M21,M22,M34,M35
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g17, 1, 'porción', 1, 10.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Carnitas 150 Gr.', 'Barbacoa 150 Gr.',
  'Sope de Chorizo 3 Pzs.', 'Sope de Pollo 3 Pzs.',
  'Huarache de Pollo 1 Pza.', 'Huarache de Carne 1 Pza.',
  'Pozole Blanco 300 Gr.', 'Pozole Rojo 300 Gr.'
);

-- ─── G19 · Pollo deshebrado · +$40 ─────────────────────────────────────────
-- M20, M21
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g19, 1, 'porción', 1, 40.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre IN (
  'Sope de Pollo 3 Pzs.', 'Huarache de Pollo 1 Pza.'
);

-- ─── G20 · Carne picada · +$40 ─────────────────────────────────────────────
-- M22
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g20, 1, 'porción', 1, 40.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Huarache de Carne 1 Pza.';

-- ─── G21 · Cebolla morada · +$10 ───────────────────────────────────────────
-- M31
INSERT IGNORE INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, es_informativo, precio_extra)
SELECT r.id, @g21, 1, 'porción', 1, 10.00
FROM rest_recetas r JOIN rest_platillos p ON p.id = r.platillo_id
WHERE p.restaurante_id = @rest_id AND p.nombre = 'Cochinita Pibil 250 Gr.';

-- ── Fin de migración ─────────────────────────────────────────────────────────
