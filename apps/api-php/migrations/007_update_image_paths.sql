-- ============================================
-- Migración 007: Actualizar rutas de imágenes
-- ============================================
-- Antes: public/uploads/...
-- Después: uploads/...
-- ============================================

-- 1. Productos (rest_platillos)
UPDATE rest_platillos 
SET imagen = REPLACE(imagen, 'public/', '')
WHERE imagen LIKE 'public/%';

-- 2. Categorías (rest_categorias_menu)
UPDATE rest_categorias_menu 
SET imagen = REPLACE(imagen, 'public/', '')
WHERE imagen LIKE 'public/%';

-- 3. Sucursales / Banners (rest_restaurantes)
UPDATE rest_restaurantes 
SET imagen_banner = REPLACE(imagen_banner, 'public/', '')
WHERE imagen_banner LIKE 'public/%';

-- ============================================
-- Verificar resultados:
-- ============================================
-- SELECT imagen FROM rest_platillos WHERE imagen NOT LIKE 'uploads/%' AND imagen IS NOT NULL;
-- SELECT imagen FROM rest_categorias_menu WHERE imagen NOT LIKE 'uploads/%' AND imagen IS NOT NULL;
-- SELECT imagen_banner FROM rest_restaurantes WHERE imagen_banner NOT LIKE 'uploads/%' AND imagen_banner IS NOT NULL;


-- ============================================
-- 📦 TAMBIÉN DEBES COPIAR LOS ARCHIVOS FÍSICAMENTE:
-- ============================================
--
-- Copia la carpeta completa desde:
--   apps/api/public/uploads/   →   apps/api-php/uploads/
--
-- Esto moverá las subcarpetas como 'platillos/' con todas las imágenes
-- a la ubicación donde la PHP API las puede servir.
--
-- En Windows (PowerShell):
--   Copy-Item -Path "apps\api\public\uploads\*" -Destination "apps\api-php\uploads\" -Recurse -Force
--
-- En Linux/Mac:
--   cp -r apps/api/public/uploads/* apps/api-php/uploads/