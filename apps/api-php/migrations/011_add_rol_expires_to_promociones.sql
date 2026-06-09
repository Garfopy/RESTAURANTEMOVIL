-- ============================================
-- Migración 011: Agregar rol a usuarios y expires_at a promociones
-- ============================================
-- 1. Agrega columna 'rol' a mobile_usuarios para distinguir admins de users normales
-- 2. Agrega columna 'expires_at' a mobile_promociones para vigencia de promos
-- ============================================

-- Paso 1: Agregar columna rol a usuarios
ALTER TABLE mobile_usuarios
    ADD COLUMN rol ENUM('user', 'admin') NOT NULL DEFAULT 'user'
    AFTER email;

-- Paso 2: Agregar columna expires_at a promociones (NULL = sin expiración)
ALTER TABLE mobile_promociones
    ADD COLUMN expires_at DATETIME DEFAULT NULL
    AFTER activo;

-- Paso 3: Actualizar índice para filtrar promos activas y vigentes
ALTER TABLE mobile_promociones
    ADD INDEX idx_activo_expires (activo, expires_at);

-- ============================================
-- Crear primer admin (ajusta el email según el usuario real)
-- ============================================
-- UPDATE mobile_usuarios SET rol = 'admin' WHERE email = 'admin@amare.com';
