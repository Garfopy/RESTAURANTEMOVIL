-- Agrega columna logo_path a la tabla empresas
ALTER TABLE empresas ADD COLUMN IF NOT EXISTS logo_path VARCHAR(255) NULL AFTER rfc;
