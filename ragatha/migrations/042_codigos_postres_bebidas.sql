-- ============================================================
-- 042_codigos_postres_bebidas.sql
-- Reasigna los platillos DP1-DP18 (Dulces y Postres) y B1-B34
-- (Bebidas) de La Comalada a su categoría correcta en
-- rest_categorias_menu, en caso de que el SET @cat_* de la 036
-- haya apuntado a una categoría duplicada/incorrecta.
--
-- Requisito previo: migración 036 aplicada (con codigo embebido en
-- los INSERT de rest_platillos para DP1-DP18 y B1-B34).
-- Seguro de re-ejecutar.
-- ============================================================

SET @rest_id = 1; -- Cambiar si el restaurante_id es distinto de 1

-- Elegir la categoría con id más alto (la que insertó 036, normalmente
-- la que tiene `descripcion` poblada, evitando duplicados previos).
SET @cat_postres_id = (
  SELECT id FROM rest_categorias_menu
  WHERE restaurante_id = @rest_id AND nombre = 'Dulces y Postres'
  ORDER BY id DESC LIMIT 1
);

SET @cat_bebidas_id = (
  SELECT id FROM rest_categorias_menu
  WHERE restaurante_id = @rest_id AND nombre = 'Bebidas'
  ORDER BY id DESC LIMIT 1
);

-- Postres DP1..DP18 → categoría "Dulces y Postres"
UPDATE rest_platillos
   SET categoria_id = @cat_postres_id
 WHERE restaurante_id = @rest_id
   AND codigo REGEXP '^DP[0-9]+$'
   AND @cat_postres_id IS NOT NULL;

-- Bebidas B1..B34 → categoría "Bebidas"
UPDATE rest_platillos
   SET categoria_id = @cat_bebidas_id
 WHERE restaurante_id = @rest_id
   AND codigo REGEXP '^B[0-9]+$'
   AND @cat_bebidas_id IS NOT NULL;

-- ── Fin ─────────────────────────────────────────────────────────────────────
