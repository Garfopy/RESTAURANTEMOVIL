-- ============================================================
-- Migration 044: Crea global_settings en idactivo_capirest
-- Permite que la landing pública de /restaurante/ cargue sin error.
-- Extraído de migrations/001_schema_completo.sql (líneas 329-360)
-- ============================================================

CREATE TABLE IF NOT EXISTS `global_settings` (
  `clave`    VARCHAR(100) NOT NULL,
  `valor`    TEXT         NULL,
  `tipo`     ENUM('text','number','boolean','json','color','password') NOT NULL DEFAULT 'text',
  `grupo`    VARCHAR(50)  NULL,
  `etiqueta` VARCHAR(150) NULL,
  PRIMARY KEY (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `global_settings` (`clave`, `valor`, `tipo`, `grupo`, `etiqueta`) VALUES
  ('app_name',           'La Comalada',         'text',     'general',  'Nombre del sitio'),
  ('app_logo',           '',                    'text',     'general',  'Logo del sitio (ruta o URL)'),
  ('color_primary',      '#C8102E',             'color',    'estilos',  'Color primario'),
  ('color_secondary',    '#1f2937',             'color',    'estilos',  'Color secundario'),
  ('smtp_host',          '',                    'text',     'correo',   'Servidor SMTP'),
  ('smtp_port',          '587',                 'number',   'correo',   'Puerto SMTP'),
  ('smtp_user',          '',                    'text',     'correo',   'Usuario SMTP'),
  ('smtp_pass',          '',                    'password', 'correo',   'Contraseña SMTP'),
  ('smtp_from',          '',                    'text',     'correo',   'Correo remitente'),
  ('telefono_contacto',  '',                    'text',     'contacto', 'Teléfono de contacto'),
  ('horarios_atencion',  'Lun-Vie 8am-6pm',     'text',     'contacto', 'Horarios de atención');
