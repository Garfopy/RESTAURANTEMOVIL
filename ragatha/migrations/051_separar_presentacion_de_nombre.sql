-- ============================================================
-- 051_separar_presentacion_de_nombre.sql
--
-- Mueve el sufijo de presentación (cantidad + unidad) del
-- nombre a la columna `presentacion` (en `productos`) y a
-- `unidad_principal` (en `rest_ingredientes`).
--
-- Ejemplos:
--   "Mezcal Sol 2 Oz."           → nombre="Mezcal Sol",           presentacion="2 Oz."
--   "Merengues 3 Pzs."           → nombre="Merengues",            presentacion="3 Pzs."
--   "Fruta Cristalizada 150 Gr." → nombre="Fruta Cristalizada",   presentacion="150 Gr."
--   "Percheron Mezcal Sol 65 Ml."→ nombre="Percheron Mezcal Sol", presentacion="65 Ml."
--   "Palanquetas 1 Pza."         → nombre="Palanquetas",          presentacion="1 Pza."
--   "Tortillas de maíz 4 pzas"   → nombre="Tortillas de maíz",    presentacion="4 pzas"
--
-- IDEMPOTENTE: detecta sólo nombres cuyo sufijo aún coincide
-- con el patrón. Re-ejecutarlo no hace cambios adicionales.
--
-- ┌─────────────────────────────────────────────────────────┐
-- │  INSTRUCCIONES DE EJECUCIÓN                             │
-- │                                                         │
-- │  idactivo_carnihubdb  → ejecutar el BLOQUE COMPLETO     │
-- │                         (secciones 1 y 2)               │
-- │                                                         │
-- │  idactivo_capirest    → ejecutar SOLO la SECCIÓN 2      │
-- │                         (el bloque UPDATE rest_...      │
-- │                          que está al final)             │
-- │                                                         │
-- │  La tabla `productos` NO existe en Capirest,            │
-- │  solo en idactivo_carnihubdb.                           │
-- └─────────────────────────────────────────────────────────┘
-- ============================================================


-- ══════════════════════════════════════════════════════════════
-- SECCIÓN 1 — Solo en idactivo_carnihubdb
-- Patrón: termina con "<dígitos> <unidad>" donde unidad ∈
--   Pzs. / Pza. / Pzas. / pzas / Oz. / Oz / Gr. / Gr / Ml. / Ml
-- ══════════════════════════════════════════════════════════════
UPDATE productos
   SET presentacion = TRIM(SUBSTRING_INDEX(nombre, ' ', -2)),
       nombre       = TRIM(SUBSTRING(
                            nombre,
                            1,
                            CHAR_LENGTH(nombre)
                              - CHAR_LENGTH(SUBSTRING_INDEX(nombre, ' ', -2)) - 1
                          ))
 WHERE nombre REGEXP ' [0-9]+ (Pzs|Pza|Pzas|pzas|Oz|Gr|Ml)[.]?$';


-- ══════════════════════════════════════════════════════════════
-- SECCIÓN 2 — En idactivo_carnihubdb  Y  en idactivo_capirest
-- (pegar solo esta parte cuando estés en Capirest)
-- ══════════════════════════════════════════════════════════════
UPDATE rest_ingredientes
   SET unidad_principal = TRIM(SUBSTRING_INDEX(nombre, ' ', -2)),
       nombre           = TRIM(SUBSTRING(
                                nombre,
                                1,
                                CHAR_LENGTH(nombre)
                                  - CHAR_LENGTH(SUBSTRING_INDEX(nombre, ' ', -2)) - 1
                              ))
 WHERE nombre REGEXP ' [0-9]+ (Pzs|Pza|Pzas|pzas|Oz|Gr|Ml)[.]?$';


-- ── Verificación (opcional) ───────────────────────────────────
-- Debe devolver 0 filas después de ejecutar:
--   SELECT id, nombre, presentacion FROM productos
--     WHERE nombre REGEXP ' [0-9]+ (Pzs|Pza|Pzas|pzas|Oz|Gr|Ml)[.]?$';
--
--   SELECT id, nombre, unidad_principal FROM rest_ingredientes
--     WHERE nombre REGEXP ' [0-9]+ (Pzs|Pza|Pzas|pzas|Oz|Gr|Ml)[.]?$';
-- ── Fin ──────────────────────────────────────────────────────
