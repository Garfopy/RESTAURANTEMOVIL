-- ============================================
-- Migracion 016: Normalizar rutas de promociones
-- ============================================
-- Carpeta fisica en cPanel:
--   public_html/public/uploads/promociones/
--
-- Valor esperado en mobile_promociones.imagen:
--   public/uploads/promociones/nombre-archivo.jpg
-- ============================================

UPDATE mobile_promociones
SET imagen = REPLACE(imagen, 'uploads/promos/', 'public/uploads/promociones/')
WHERE imagen LIKE 'uploads/promos/%';

UPDATE mobile_promociones
SET imagen = REPLACE(imagen, 'uploads/promotions/', 'public/uploads/promociones/')
WHERE imagen LIKE 'uploads/promotions/%';

UPDATE mobile_promociones
SET imagen = REPLACE(imagen, 'uploads/promociones/', 'public/uploads/promociones/')
WHERE imagen LIKE 'uploads/promociones/%';

-- Verificacion:
-- SELECT id, imagen
-- FROM mobile_promociones
-- WHERE imagen IS NOT NULL
--   AND imagen NOT LIKE 'public/uploads/promociones/%'
--   AND imagen NOT LIKE 'http%';
