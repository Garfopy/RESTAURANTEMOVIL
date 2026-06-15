-- ============================================================
-- 066 — CarniHub: Promociones para comensales
-- ============================================================
-- Permite al administrador del restaurante crear promociones
-- especiales dirigidas a comensales específicos.
-- ============================================================

CREATE TABLE IF NOT EXISTS `rest_promociones` (
  `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `restaurante_id`  INT UNSIGNED NOT NULL,
  `titulo`          VARCHAR(200)  NOT NULL,
  `descripcion`     TEXT          NULL,
  `tipo`            ENUM('porcentaje','monto_fijo','envio_gratis') NOT NULL DEFAULT 'porcentaje',
  `valor_descuento` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `fecha_inicio`    DATE          NOT NULL,
  `fecha_fin`       DATE          NOT NULL,
  `activo`          TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_rest_prom_rest` (`restaurante_id`),
  INDEX `idx_rest_prom_activo` (`restaurante_id`, `activo`, `fecha_inicio`, `fecha_fin`),
  CONSTRAINT `fk_rest_prom_rest` FOREIGN KEY (`restaurante_id`) REFERENCES `rest_restaurantes`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rest_promocion_comensales` (
  `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `promocion_id`  INT UNSIGNED NOT NULL,
  `comensal_id`   INT UNSIGNED NOT NULL,
  `usado`         TINYINT(1)   NOT NULL DEFAULT 0,
  `fecha_uso`     TIMESTAMP    NULL DEFAULT NULL,
  `created_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_prom_comensal` (`promocion_id`, `comensal_id`),
  INDEX `idx_prom_com_com` (`comensal_id`),
  CONSTRAINT `fk_prom_com_prom` FOREIGN KEY (`promocion_id`) REFERENCES `rest_promociones`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_prom_com_com`  FOREIGN KEY (`comensal_id`) REFERENCES `rest_comensales`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;