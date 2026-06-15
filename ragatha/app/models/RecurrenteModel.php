<?php
require_once ROOT_PATH . '/app/models/BaseModel.php';

class RecurrenteModel extends BaseModel
{
    protected string $table = 'pedidos';

    /** Resumen general de estadísticas de pedidos de la empresa */
    public function getResumen(int $empresaId): array
    {
        $row = $this->queryOne(
            "SELECT
                COUNT(DISTINCT p.id)           AS total_pedidos,
                COUNT(DISTINCT p.comprador_id) AS compradores_unicos,
                COUNT(DISTINCT pd.producto_id) AS productos_distintos,
                COALESCE(SUM(CASE WHEN p.estado != 'cancelado' THEN p.total ELSE 0 END), 0) AS monto_total
             FROM pedidos p
             LEFT JOIN pedido_detalle pd ON pd.pedido_id = p.id
             WHERE p.empresa_id = ?",
            [$empresaId]
        );
        return [
            'total_pedidos'       => (int)($row['total_pedidos']       ?? 0),
            'compradores_unicos'  => (int)($row['compradores_unicos']  ?? 0),
            'productos_distintos' => (int)($row['productos_distintos'] ?? 0),
            'monto_total'         => (float)($row['monto_total']       ?? 0),
        ];
    }

    /** Top productos más pedidos por número de pedidos y cantidad acumulada */
    public function getTopProductos(int $empresaId, int $limit = 10): array
    {
        return $this->query(
            "SELECT
                pr.nombre,
                pr.presentacion,
                SUM(pd.cantidad)               AS cantidad_total,
                COUNT(DISTINCT p.id)           AS veces_pedido,
                COUNT(DISTINCT p.comprador_id) AS compradores
             FROM pedido_detalle pd
             JOIN pedidos   p  ON p.id  = pd.pedido_id
             JOIN productos pr ON pr.id = pd.producto_id
             WHERE p.empresa_id = ? AND p.estado != 'cancelado'
             GROUP BY pr.id, pr.nombre, pr.presentacion
             ORDER BY veces_pedido DESC, cantidad_total DESC
             LIMIT ?",
            [$empresaId, $limit]
        );
    }

    /** Pedidos agrupados por día de la semana (todos los días, incluso con 0) */
    public function getPedidosPorDiaSemana(int $empresaId): array
    {
        $nombres = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $rows    = $this->query(
            "SELECT DAYOFWEEK(created_at) AS dia_num, COUNT(*) AS total_pedidos
             FROM pedidos
             WHERE empresa_id = ? AND estado != 'cancelado'
             GROUP BY DAYOFWEEK(created_at)
             ORDER BY dia_num ASC",
            [$empresaId]
        );
        $mapa = array_fill(1, 7, 0);
        foreach ($rows as $r) {
            $mapa[(int)$r['dia_num']] = (int)$r['total_pedidos'];
        }
        $result = [];
        foreach ($mapa as $num => $total) {
            $result[] = ['dia_nombre' => $nombres[$num - 1], 'total_pedidos' => $total];
        }
        return $result;
    }

    /** Top compradores más frecuentes */
    public function getTopCompradores(int $empresaId, int $limit = 8): array
    {
        return $this->query(
            "SELECT
                u.nombre                          AS comprador,
                COUNT(DISTINCT p.id)              AS total_pedidos,
                COALESCE(SUM(p.total), 0)         AS monto_total,
                MAX(p.created_at)                 AS ultimo_pedido
             FROM pedidos p
             JOIN usuarios u ON u.id = p.comprador_id
             WHERE p.empresa_id = ? AND p.estado != 'cancelado'
             GROUP BY u.id, u.nombre
             ORDER BY total_pedidos DESC
             LIMIT ?",
            [$empresaId, $limit]
        );
    }

    /** Productos pedidos repetidamente por el mismo comprador (patrón recurrente real) */
    public function getProductosRecurrentes(int $empresaId, int $minVeces = 2, int $limit = 15): array
    {
        return $this->query(
            "SELECT
                pr.nombre,
                pr.presentacion,
                u.nombre                          AS comprador,
                COUNT(DISTINCT p.id)              AS veces_pedido,
                SUM(pd.cantidad)                  AS cantidad_total,
                MAX(p.created_at)                 AS ultimo_pedido
             FROM pedido_detalle pd
             JOIN pedidos   p  ON p.id  = pd.pedido_id
             JOIN productos pr ON pr.id = pd.producto_id
             JOIN usuarios  u  ON u.id  = p.comprador_id
             WHERE p.empresa_id = ? AND p.estado != 'cancelado'
             GROUP BY pr.id, pr.nombre, pr.presentacion, u.id, u.nombre
             HAVING veces_pedido >= ?
             ORDER BY veces_pedido DESC, cantidad_total DESC
             LIMIT ?",
            [$empresaId, $minVeces, $limit]
        );
    }
}
