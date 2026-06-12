-- Migration: Crear tabla de direcciones de usuarios
-- Tabla: mobile_direcciones (Coincide con la tabla usada por la Express API)
-- Depende de: mobile_usuarios

CREATE TABLE IF NOT EXISTS mobile_direcciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    alias VARCHAR(100) DEFAULT 'Dirección',
    calle VARCHAR(255) NOT NULL DEFAULT '',
    numero VARCHAR(20) DEFAULT NULL,
    colonia VARCHAR(150) DEFAULT NULL,
    ciudad VARCHAR(100) NOT NULL DEFAULT '',
    estado_provincia VARCHAR(100) DEFAULT NULL,
    cp VARCHAR(10) DEFAULT NULL,
    lat DECIMAL(10, 8) DEFAULT NULL,
    lng DECIMAL(11, 8) DEFAULT NULL,
    instrucciones TEXT DEFAULT NULL,
    es_principal TINYINT(1) NOT NULL DEFAULT 0,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES mobile_usuarios(id) ON DELETE CASCADE,
    INDEX idx_usuario_id (usuario_id),
    INDEX idx_activo (activo),
    INDEX idx_es_principal (usuario_id, es_principal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;