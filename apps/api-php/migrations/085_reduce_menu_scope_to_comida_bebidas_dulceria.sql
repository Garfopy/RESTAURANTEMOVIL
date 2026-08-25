-- ============================================
-- Migración 085: Reducir el alcance del menú a
-- Comida, Bebidas y Dulcería (antes Postres)
-- ============================================
--
-- No borra categorías: solo las desactiva (activo = 0),
-- para poder reactivarlas más adelante sin perder datos
-- ni platillos asociados.
--
-- IMPORTANTE: revisa el resultado del PASO 1 antes de correr
-- los UPDATE de abajo, para confirmar los nombres reales de
-- tus categorías en producción (pueden no coincidir 100% con
-- los que se asumen aquí).

-- PASO 1: Ver categorías actuales (ejecutar primero, solo lectura)
SELECT id, restaurante_id, nombre, activo, orden
FROM rest_categorias_menu
ORDER BY restaurante_id, orden, nombre;

-- PASO 2: Renombrar "Postres" -> "Dulcería"
-- (ajusta el patrón LIKE si tu categoría se llama distinto,
--  p.ej. 'Postre', 'Repostería', etc.)
UPDATE rest_categorias_menu
SET nombre = 'Dulcería'
WHERE nombre LIKE '%postre%';

-- PASO 3: Desactivar cualquier categoría que NO sea
-- Comida, Bebidas o Dulcería (case-insensitive)
UPDATE rest_categorias_menu
SET activo = 0
WHERE LOWER(nombre) NOT IN ('comida', 'bebidas', 'dulcería', 'dulceria');

-- PASO 4 (verificación): confirmar que solo queden 3 categorías activas
-- SELECT id, restaurante_id, nombre, activo FROM rest_categorias_menu WHERE activo = 1 ORDER BY restaurante_id, nombre;

-- Para revertir (reactivar todo) más adelante:
-- UPDATE rest_categorias_menu SET activo = 1 WHERE activo = 0;
