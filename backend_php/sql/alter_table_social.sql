-- ============================================================
-- ALTER TABLE: Agregar campos sociales a mobile_usuarios
-- ============================================================
-- Compatible con MySQL 5.7+
-- Ejecutar en: idactivo_amare_app

ALTER TABLE mobile_usuarios
    ADD COLUMN gustos      VARCHAR(500) DEFAULT NULL AFTER descripcion,
    ADD COLUMN ocupacion   VARCHAR(100) DEFAULT NULL AFTER gustos,
    ADD COLUMN biografia   TEXT         DEFAULT NULL AFTER ocupacion,
    ADD COLUMN instagram   VARCHAR(100) DEFAULT NULL AFTER biografia,
    ADD COLUMN tiktok      VARCHAR(100) DEFAULT NULL AFTER instagram,
    ADD COLUMN modo_social TINYINT(1)   DEFAULT 0  AFTER is_social_active;

-- Si la columna ya existe (para evitar error en ejecución múltiple):
-- Ejecuta cada ALTER por separado si alguna columna ya existe.