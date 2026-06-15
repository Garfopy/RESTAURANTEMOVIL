-- ============================================================
-- 059_codigos_platillos_bebidas_postres.sql
-- Asigna el campo `codigo` en rest_platillos para los platillos
-- de las categorías Dulces y Postres (DP1-DP18) y Bebidas (B1-B34).
--
-- Contexto: la migración 036 insertó estos platillos sin código.
-- La migración 040 sólo asignó códigos M1-M35 (comida).
-- Sin este campo, el descuento de stock en descontarPorOrden()
-- no puede vincular el platillo con su ingrediente en rest_ingredientes.
--
-- Seguro de re-ejecutar (WHERE codigo IS NULL OR codigo = '').
-- ============================================================

SET @rest_id = 1;

-- ── Dulces y Postres ─────────────────────────────────────────────────────────
UPDATE rest_platillos SET codigo = 'DP1'  WHERE restaurante_id = @rest_id AND nombre = 'Ate de Guayaba con Queso 6 Pzs.'        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP2'  WHERE restaurante_id = @rest_id AND nombre = 'Ate de Membrillo con Queso 6 Pzs.'      AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP3'  WHERE restaurante_id = @rest_id AND nombre = 'Glorias de Leche Quemada 3 Pzs.'        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP4'  WHERE restaurante_id = @rest_id AND nombre = 'Obleas de Cajeta 3 Pzs.'                AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP5'  WHERE restaurante_id = @rest_id AND nombre = 'Camote de Puebla 3 Pzs.'                AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP6'  WHERE restaurante_id = @rest_id AND nombre = 'Borrachitos de Fresa 5 Pzs.'            AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP7'  WHERE restaurante_id = @rest_id AND nombre = 'Fruta Cristalizada Higo 150 Gr.'        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP8'  WHERE restaurante_id = @rest_id AND nombre = 'Fruta Cristalizada Pera 150 Gr.'        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP9'  WHERE restaurante_id = @rest_id AND nombre = 'Fruta Cristalizada Calabazete 150 Gr.'  AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP10' WHERE restaurante_id = @rest_id AND nombre = 'Orejonas 150 Gr.'                       AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP11' WHERE restaurante_id = @rest_id AND nombre = 'Cocadas 3 Pzs.'                         AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP12' WHERE restaurante_id = @rest_id AND nombre = 'Palanquetas 1 Pza.'                     AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP13' WHERE restaurante_id = @rest_id AND nombre = 'Merengues 3 Pzs.'                       AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP14' WHERE restaurante_id = @rest_id AND nombre = 'Natillas Qro. 150 Gr.'                  AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP15' WHERE restaurante_id = @rest_id AND nombre = 'Natillas Bernal 4 Pzs.'                 AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP16' WHERE restaurante_id = @rest_id AND nombre = 'Ollitas de Tamarindo 3 Pzs.'            AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP17' WHERE restaurante_id = @rest_id AND nombre = 'Tamal de Maíz Dulce de Piña 2 Pzs.'    AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'DP18' WHERE restaurante_id = @rest_id AND nombre = 'Tamal de Dulce de Fresa 2 Pzs.'        AND (codigo IS NULL OR codigo = '');

-- ── Bebidas ──────────────────────────────────────────────────────────────────
UPDATE rest_platillos SET codigo = 'B1'  WHERE restaurante_id = @rest_id AND nombre = 'Mezcal Sol 2 Oz.'                       AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B2'  WHERE restaurante_id = @rest_id AND nombre = 'Mezcal Luna 2 Oz.'                      AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B3'  WHERE restaurante_id = @rest_id AND nombre = 'Mezcal Orgullo 2 Oz.'                   AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B4'  WHERE restaurante_id = @rest_id AND nombre = 'Mezcal Noche 2 Oz.'                     AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B5'  WHERE restaurante_id = @rest_id AND nombre = 'Mezcal Amor 2 Oz.'                      AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B6'  WHERE restaurante_id = @rest_id AND nombre = 'Tequila Blanco 2 Oz.'                   AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B7'  WHERE restaurante_id = @rest_id AND nombre = 'Tequila Reposado 2 Oz.'                 AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B8'  WHERE restaurante_id = @rest_id AND nombre = 'Tequila Añejo 2 Oz.'                    AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B9'  WHERE restaurante_id = @rest_id AND nombre = 'Cerveza Artesanal Clara'                AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B10' WHERE restaurante_id = @rest_id AND nombre = 'Cerveza Artesanal Morena'               AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B11' WHERE restaurante_id = @rest_id AND nombre = 'Cerveza Artesanal Oscura'               AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B12' WHERE restaurante_id = @rest_id AND nombre = 'Cocktail de Mezcal con Tamarindo'       AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B13' WHERE restaurante_id = @rest_id AND nombre = 'Cocktail de Mezcal con Jamaica'         AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B14' WHERE restaurante_id = @rest_id AND nombre = 'Cocktail de Mezcal Margarita'           AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B15' WHERE restaurante_id = @rest_id AND nombre = 'Cocktail de Tequila con Tamarindo'      AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B16' WHERE restaurante_id = @rest_id AND nombre = 'Coctel de Tequila con Jamaica'          AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B17' WHERE restaurante_id = @rest_id AND nombre = 'Cocktail de Tequila Margarita'          AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B18' WHERE restaurante_id = @rest_id AND nombre = 'Café de Olla'                           AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B19' WHERE restaurante_id = @rest_id AND nombre = 'Carajillo sin Cafeína Amanecer'         AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B20' WHERE restaurante_id = @rest_id AND nombre = 'Carajillo sin Cafeína Anochecer'        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B21' WHERE restaurante_id = @rest_id AND nombre = 'Agua de Horchata'                       AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B22' WHERE restaurante_id = @rest_id AND nombre = 'Agua de Jamaica'                        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B23' WHERE restaurante_id = @rest_id AND nombre = 'Agua de Tamarindo'                      AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B24' WHERE restaurante_id = @rest_id AND nombre = 'Chocolate con Agua'                     AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B25' WHERE restaurante_id = @rest_id AND nombre = 'Chocolate con Leche'                    AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B26' WHERE restaurante_id = @rest_id AND nombre = 'Atole de Fresa'                         AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B27' WHERE restaurante_id = @rest_id AND nombre = 'Atole de Vainilla'                      AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B28' WHERE restaurante_id = @rest_id AND nombre = 'Agua Mineral'                           AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B29' WHERE restaurante_id = @rest_id AND nombre = 'Agua Sola'                              AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B30' WHERE restaurante_id = @rest_id AND nombre = 'Percherón Mezcal Sol 65 Ml.'            AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B31' WHERE restaurante_id = @rest_id AND nombre = 'Percherón Mezcal Luna 65 Ml.'           AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B32' WHERE restaurante_id = @rest_id AND nombre = 'Percherón Mezcal Orgullo 65 Ml.'        AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B33' WHERE restaurante_id = @rest_id AND nombre = 'Percherón Mezcal Amor 65 Ml.'           AND (codigo IS NULL OR codigo = '');
UPDATE rest_platillos SET codigo = 'B34' WHERE restaurante_id = @rest_id AND nombre = 'Percherón Mezcal Noche 65 Ml.'          AND (codigo IS NULL OR codigo = '');

-- ── Verificación ─────────────────────────────────────────────────────────────
SELECT codigo, nombre FROM rest_platillos
WHERE restaurante_id = @rest_id AND codigo REGEXP '^(B|DP)[0-9]+$'
ORDER BY codigo;
