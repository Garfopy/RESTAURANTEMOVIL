-- ============================================
-- Migración 015: Agregar auditoría a promociones
-- ============================================
-- Agrega columnas para rastrear quién creó/editó cada promoción
-- y cuándo se hicieron los cambios
-- ============================================

ALTER TABLE mobile_promociones
ADD COLUMN created_by INT DEFAULT NULL COMMENT 'Admin que creó la promoción' AFTER created_at,
ADD COLUMN updated_at DATETIME DEFAULT NULL COMMENT 'Última actualización' AFTER created_by,
ADD COLUMN updated_by INT DEFAULT NULL COMMENT 'Admin que actualizó la promoción por última vez' AFTER updated_at,
ADD FOREIGN KEY (created_by) REFERENCES mobile_usuarios(id) ON DELETE SET NULL,
ADD FOREIGN KEY (updated_by) REFERENCES mobile_usuarios(id) ON DELETE SET NULL,
ADD INDEX idx_created_by (created_by),
ADD INDEX idx_updated_by (updated_by);

-- Actualizar registros existentes: asignar un admin genérico (id=1) como creador si existe
-- Esto es una migración de datos. Si quieres que sea NULL, quita estas líneas.
UPDATE mobile_promociones 
SET created_by = 1 
WHERE created_by IS NULL 
AND EXISTS(SELECT 1 FROM mobile_usuarios WHERE id = 1);
