<?php
class MovimientoInventarioModel extends BaseModel
{
    protected string $table = 'movimientos_inventario';

    public function registrar(array $datos): int
    {
        $this->execute(
            'INSERT INTO movimientos_inventario
             (empresa_id, producto_id, tipo, cantidad, stock_antes, stock_despues, motivo, referencia, usuario_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $datos['empresa_id'],
                $datos['producto_id'],
                $datos['tipo'],
                $datos['cantidad'],
                $datos['stock_antes'],
                $datos['stock_despues'],
                $datos['motivo'] ?? null,
                $datos['referencia'] ?? null,
                $datos['usuario_id'],
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function historialProducto(int $productoId, int $empresaId, int $page = 1): array
    {
        $sql = "SELECT mi.*, p.nombre AS producto_nombre,
                       CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario_nombre
                  FROM movimientos_inventario mi
                  JOIN productos p ON p.id = mi.producto_id
                  JOIN usuarios u ON u.id = mi.usuario_id
                 WHERE mi.producto_id = ? AND mi.empresa_id = ?
              ORDER BY mi.created_at DESC";
        return $this->paginate($sql, [$productoId, $empresaId], $page);
    }

    public function historialEmpresa(int $empresaId, array $filtros = [], int $page = 1): array
    {
        $where  = ['mi.empresa_id = ?'];
        $params = [$empresaId];

        if (!empty($filtros['tipo'])) {
            $where[]  = 'mi.tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['producto_id'])) {
            $where[]  = 'mi.producto_id = ?';
            $params[] = (int)$filtros['producto_id'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]  = 'DATE(mi.created_at) >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]  = 'DATE(mi.created_at) <= ?';
            $params[] = $filtros['fecha_hasta'];
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT mi.*, p.nombre AS producto_nombre, p.presentacion,
                       CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario_nombre
                  FROM movimientos_inventario mi
                  JOIN productos p ON p.id = mi.producto_id
                  JOIN usuarios u ON u.id = mi.usuario_id
                  $sqlWhere
              ORDER BY mi.created_at DESC";

        return $this->paginate($sql, $params, $page);
    }

    public function resumenStock(int $empresaId): array
    {
        return $this->query(
            "SELECT p.id, p.nombre, p.presentacion, p.imagen, c.nombre AS categoria_nombre,
                    COALESCE(inv.stock, 0) AS stock_actual,
                    COALESCE(inv.umbral_minimo, 10) AS umbral_minimo,
                    CASE
                      WHEN COALESCE(inv.stock, 0) = 0 THEN 'agotado'
                      WHEN COALESCE(inv.stock, 0) <= COALESCE(inv.umbral_minimo, 10) THEN 'critico'
                      WHEN COALESCE(inv.stock, 0) <= COALESCE(inv.umbral_minimo, 10) * 2 THEN 'bajo'
                      ELSE 'ok'
                    END AS estado_stock
               FROM productos p
               JOIN categorias c ON c.id = p.categoria_id
          LEFT JOIN inventario inv ON inv.producto_id = p.id
              WHERE p.empresa_id = ? AND p.activo = 1
           ORDER BY estado_stock DESC, p.nombre",
            [$empresaId]
        );
    }

    public function ultimosMovimientos(int $empresaId, int $limite = 10): array
    {
        return $this->query(
            "SELECT mi.tipo, mi.cantidad, mi.motivo, mi.created_at,
                    p.nombre AS producto_nombre, p.presentacion,
                    CONCAT(u.nombre, ' ', u.apellido_paterno) AS usuario_nombre
               FROM movimientos_inventario mi
               JOIN productos p ON p.id = mi.producto_id
               JOIN usuarios u ON u.id = mi.usuario_id
              WHERE mi.empresa_id = ?
           ORDER BY mi.created_at DESC
              LIMIT $limite",
            [$empresaId]
        );
    }

    public function stockActual(int $productoId): float
    {
        $row = $this->queryOne('SELECT stock FROM inventario WHERE producto_id = ?', [$productoId]);
        return $row ? (float)$row['stock'] : 0.0;
    }

    /**
     * Returns current stock as float, or null if the product is not tracked in inventario.
     * null = no inventory row → no restriction.
     * 0.0  = tracked but empty.
     */
    public function getStockActual(int $productoId): ?float
    {
        $row = $this->queryOne('SELECT stock FROM inventario WHERE producto_id = ?', [$productoId]);
        return $row !== null ? (float)$row['stock'] : null;
    }

    public function entradasVsSalidasSemanal(int $empresaId, int $semanas = 6): array
    {
        return $this->query(
            "SELECT YEARWEEK(created_at, 1) AS semana_num,
                    MIN(DATE(created_at)) AS inicio_semana,
                    SUM(CASE WHEN tipo = 'entrada'              THEN cantidad ELSE 0 END) AS entradas,
                    SUM(CASE WHEN tipo IN ('salida','merma')    THEN cantidad ELSE 0 END) AS salidas
               FROM movimientos_inventario
              WHERE empresa_id = ?
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
              GROUP BY YEARWEEK(created_at, 1)
              ORDER BY semana_num ASC",
            [$empresaId, $semanas]
        );
    }

    /**
     * Deducts stock for every item in a pedido and registers outbound movements.
     * Skips products that have no inventario row (untracked = unlimited).
     * Stock never goes below 0.
     */
    public function descontarStockPedido(int $pedidoId, int $empresaId, int $usuarioId, string $folio): void
    {
        $items = $this->query(
            'SELECT pd.producto_id, pd.cantidad FROM pedido_detalle pd WHERE pd.pedido_id = ?',
            [$pedidoId]
        );

        if (empty($items)) return;

        $this->db->beginTransaction();
        try {
            foreach ($items as $item) {
                $prodId = (int)$item['producto_id'];
                $cant   = (float)$item['cantidad'];

                $inv = $this->queryOne('SELECT stock FROM inventario WHERE producto_id = ?', [$prodId]);
                if ($inv === null) continue;

                $stockAntes   = (float)$inv['stock'];
                $stockDespues = max(0.0, $stockAntes - $cant);

                $this->execute(
                    'UPDATE inventario SET stock = ? WHERE producto_id = ?',
                    [$stockDespues, $prodId]
                );

                $this->registrar([
                    'empresa_id'    => $empresaId,
                    'producto_id'   => $prodId,
                    'tipo'          => 'salida',
                    'cantidad'      => $cant,
                    'stock_antes'   => $stockAntes,
                    'stock_despues' => $stockDespues,
                    'motivo'        => 'Venta — Pedido ' . $folio,
                    'referencia'    => 'pedido:' . $pedidoId,
                    'usuario_id'    => $usuarioId,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
