-- Migration 058: Seguimiento de estado de pedidos en CarniHub
-- Agrega columnas para rastrear el estado reportado por CarniHub
-- y la última vez que se sincronizó el estado.

ALTER TABLE rest_pedidos_sugeridos
  ADD COLUMN IF NOT EXISTS estado_carnihub VARCHAR(40) NULL
    COMMENT 'Estado reportado por CarniHub: pendiente, aprobado, en_camino, entregado, cancelado'
    AFTER pedido_carnihub_id,
  ADD COLUMN IF NOT EXISTS ultima_sync_carnihub DATETIME NULL
    COMMENT 'Última consulta de estado a CarniHub'
    AFTER estado_carnihub;
