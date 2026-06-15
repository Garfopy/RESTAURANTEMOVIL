-- ============================================================
-- 048 — CarniHub: Tabla de tokens API para integraciones
-- ============================================================
-- Ejecutar en la BD de CarniHub (idactivo_carnihub / produccion).
-- Crea la tabla `api_tokens` que permite a sistemas externos
-- (como CapiRest) autenticarse y crear pedidos B2B.
--
-- Flujo:
--   1. Admin de CarniHub genera un token para empresa X
--   2. Ese token se guarda en CapiRest en carnihub_api_config.api_key
--   3. CapiRest envía: Authorization: Bearer <token> en cada request
-- ============================================================

-- ── Tokens de API (Bearer token auth) ─────────────────────────
CREATE TABLE IF NOT EXISTS `api_tokens` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `empresa_id`   INT UNSIGNED  NOT NULL
                   COMMENT 'Empresa de CarniHub que autoriza el acceso',
  `nombre`       VARCHAR(100)  NOT NULL
                   COMMENT 'Etiqueta descriptiva, p.ej. «CapiRest - La Comalada»',
  `token`        VARCHAR(128)  NOT NULL
                   COMMENT 'Bearer token (hash seguro, generado en alta)',
  `scopes`       JSON          NULL
                   COMMENT 'Permisos habilitados: ["pedidos:crear","pedidos:leer","productos:leer"]',
  `activo`       TINYINT(1)    NOT NULL DEFAULT 1,
  `last_used_at` TIMESTAMP     NULL
                   COMMENT 'Última vez que este token fue usado con éxito',
  `created_at`   TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_token`    (`token`),
  KEY `idx_at_empresa`     (`empresa_id`),
  KEY `idx_at_activo`      (`activo`),
  CONSTRAINT `fk_at_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Tokens Bearer para acceso externo a la API REST de CarniHub';

-- ── Bitácora de accesos a la API ───────────────────────────────
CREATE TABLE IF NOT EXISTS `api_access_log` (
  `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `token_id`     INT UNSIGNED    NOT NULL,
  `empresa_id`   INT UNSIGNED    NOT NULL,
  `ip`           VARCHAR(45)     NULL,
  `metodo`       VARCHAR(10)     NULL,
  `endpoint`     VARCHAR(255)    NULL,
  `status_code`  SMALLINT        NULL,
  `duracion_ms`  SMALLINT        NULL,
  `created_at`   TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_alog_token`  (`token_id`),
  KEY `idx_alog_fecha`  (`created_at`),
  CONSTRAINT `fk_alog_token` FOREIGN KEY (`token_id`) REFERENCES `api_tokens`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  COMMENT='Registro de llamadas a la API externa para auditoría y monitoreo';
