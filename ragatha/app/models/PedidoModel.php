<?php
class PedidoModel extends BaseModel
{
    protected string $table = 'pedidos';

    public function generarFolio(): string
    {
        $anio = date('Y');
        $row  = $this->queryOne(
            "SELECT MAX(CAST(SUBSTRING_INDEX(folio, '-', -1) AS UNSIGNED)) AS ultimo
               FROM pedidos WHERE folio LIKE ?",
            ["CHB-{$anio}-%"]
        );
        $num = (int)($row['ultimo'] ?? 0) + 1;
        return sprintf('CHB-%s-%04d', $anio, $num);
    }

    /**
     * Crea pedido + detalle + pedido_sucursal en una transacción.
     *
     * $pedidoData: campos directos para la tabla pedidos (sin folio, subtotal, total).
     * $items: [['producto_id'=>, 'cantidad'=>, 'precio_unit'=>, 'subtotal'=>], ...]
     * $sucursalesIds: [sucursal_id, ...] — sucursales involucradas
     */
    public function crear(array $pedidoData, array $items, array $sucursalesIds = []): int
    {
        $this->db->beginTransaction();
        try {
            $subtotal = array_sum(array_column($items, 'subtotal'));
            // IVA 16 % se suma al subtotal (precios sin IVA)
            $iva      = round($subtotal * 0.16, 2);
            $pedidoData['folio']    = $this->generarFolio();
            $pedidoData['subtotal'] = $subtotal;
            $pedidoData['iva']      = $iva;
            $pedidoData['total']    = $subtotal + $iva;

            $pedidoId = $this->insert($pedidoData);

            foreach ($items as $item) {
                $this->execute(
                    'INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unit, subtotal)
                     VALUES (?, ?, ?, ?, ?)',
                    [$pedidoId, $item['producto_id'], $item['cantidad'], $item['precio_unit'], $item['subtotal']]
                );
            }

            foreach (array_unique($sucursalesIds) as $sucursalId) {
                $this->execute(
                    'INSERT INTO pedido_sucursal (pedido_id, sucursal_id) VALUES (?, ?)',
                    [$pedidoId, $sucursalId]
                );
            }

            $this->db->commit();
            return $pedidoId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function guardarDistribucion(int $pedidoId, array $distPost): void
    {
        // $distPost = ['prod_id' => ['suc_id' => cantidad, ...], ...]
        if (empty($distPost)) return;

        $psRows = $this->query(
            'SELECT id, sucursal_id FROM pedido_sucursal WHERE pedido_id = ?', [$pedidoId]
        );
        $psMap = [];
        foreach ($psRows as $r) { $psMap[(int)$r['sucursal_id']] = (int)$r['id']; }
        if (empty($psMap)) return;

        $pdRows = $this->query(
            'SELECT id, producto_id, precio_unit FROM pedido_detalle WHERE pedido_id = ?', [$pedidoId]
        );
        $pdMap = [];
        foreach ($pdRows as $r) { $pdMap[(int)$r['producto_id']] = ['id' => (int)$r['id'], 'precio' => (float)$r['precio_unit']]; }

        foreach ($distPost as $prodId => $sucDist) {
            $prodId = (int)$prodId;
            if (!isset($pdMap[$prodId])) continue;
            $pdId  = $pdMap[$prodId]['id'];
            $precio = $pdMap[$prodId]['precio'];

            foreach ($sucDist as $sucId => $cantidad) {
                $sucId   = (int)$sucId;
                $cantidad = (float)str_replace(',', '.', $cantidad);
                if ($cantidad <= 0 || !isset($psMap[$sucId])) continue;

                $this->execute(
                    'INSERT INTO pedido_sucursal_detalle
                       (pedido_sucursal_id, pedido_detalle_id, producto_id, cantidad, precio_unit, subtotal)
                     VALUES (?, ?, ?, ?, ?, ?)',
                    [$psMap[$sucId], $pdId, $prodId, $cantidad, $precio, round($precio * $cantidad, 2)]
                );
            }
        }
    }

    public function asignarEntrega(int $id, string $tipo, ?int $repartidorId, float $costoEnvio, string $notaEmpresa = ''): void
    {
        $this->execute(
            'UPDATE pedidos SET tipo_entrega = ?, repartidor_asignado_id = ?, costo_envio = ?, nota_empresa = ?
              WHERE id = ?',
            [$tipo, $repartidorId, $costoEnvio, $notaEmpresa ?: null, $id]
        );
    }

    public function aprobarPedido(int $id, int $aprobadoPorId, array $ajustes = []): void
    {
        $this->db->beginTransaction();
        try {
            // Apply price adjustments (admin can only lower prices)
            foreach ($ajustes as $detalleId => $precioNuevo) {
                $detalleId  = (int)$detalleId;
                $precioNuevo = (float)$precioNuevo;
                if ($detalleId <= 0 || $precioNuevo <= 0) continue;

                $linea = $this->queryOne(
                    'SELECT id, precio_unit, cantidad FROM pedido_detalle WHERE id = ? AND pedido_id = ?',
                    [$detalleId, $id]
                );
                if (!$linea) continue;
                $precioOriginal = (float)$linea['precio_unit'];
                if ($precioNuevo >= $precioOriginal) continue; // Only allow lowering

                $subtotalNuevo = round($precioNuevo * (float)$linea['cantidad'], 2);
                $this->execute(
                    'UPDATE pedido_detalle SET precio_original = ?, precio_unit = ?, subtotal = ? WHERE id = ?',
                    [$precioOriginal, $precioNuevo, $subtotalNuevo, $detalleId]
                );
            }

            // Recalculate subtotal
            $row = $this->queryOne(
                'SELECT SUM(subtotal) AS subtotal FROM pedido_detalle WHERE pedido_id = ?',
                [$id]
            );
            $nuevoSubtotal = (float)($row['subtotal'] ?? 0);
            $nuevoIva      = round($nuevoSubtotal * 0.16, 2);

            $this->execute(
                "UPDATE pedidos
                    SET estado = 'confirmado', aprobado_por = ?, aprobado_at = NOW(),
                        subtotal = ?, iva = ?, total = ? + ? + costo_envio
                  WHERE id = ?",
                [$aprobadoPorId, $nuevoSubtotal, $nuevoIva, $nuevoSubtotal, $nuevoIva, $id]
            );
            $this->logEstado($id, 'confirmado');

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function rechazarPedido(int $id, string $nota): void
    {
        $this->execute(
            "UPDATE pedidos SET estado = 'cancelado', nota_empresa = ? WHERE id = ?",
            [$nota ?: null, $id]
        );
        $this->logEstado($id, 'cancelado');
    }

    public function subirComprobante(int $id, string $path): void
    {
        // Solo guarda la ruta; el admin valida manualmente → en_preparacion
        $this->execute(
            "UPDATE pedidos SET foto_comprobante_path = ? WHERE id = ? AND estado = 'confirmado'",
            [$path, $id]
        );
    }

    public function subirFotoEntrega(int $id, string $path): void
    {
        $this->execute(
            "UPDATE pedidos SET foto_entrega_path = ?, estado = 'entregado' WHERE id = ?",
            [$path, $id]
        );
        $this->logEstado($id, 'entregado');
    }

    public function listadoEmpresa(int $empresaId, array $filtros = [], int $page = 1): array
    {
        $where  = ['p.empresa_id = ?'];
        $params = [$empresaId];

        if (!empty($filtros['estado'])) {
            $where[]  = 'p.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['tipo'])) {
            $where[]  = 'p.tipo = ?';
            $params[] = $filtros['tipo'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[]  = 'DATE(p.created_at) >= ?';
            $params[] = $filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[]  = 'DATE(p.created_at) <= ?';
            $params[] = $filtros['fecha_hasta'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = '(p.folio LIKE ? OR u.nombre LIKE ? OR u.apellido_paterno LIKE ?)';
            $t = '%' . $filtros['buscar'] . '%';
            array_push($params, $t, $t, $t);
        }

        $sql = 'SELECT p.*, u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido
                  FROM pedidos p
                  JOIN usuarios u ON u.id = p.comprador_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY (p.estado = "pendiente") DESC, p.created_at DESC';

        return $this->paginate($sql, $params, $page, 10);
    }

    public function crearPersonalizado(int $empresaId, int $compradorId, string $folio, string $nota, ?string $fechaEntrega, array $lineas, float $total, int $creadoPorId): int
    {
        $this->db->beginTransaction();
        try {
            $this->execute(
                'INSERT INTO pedidos (folio, empresa_id, comprador_id, estado, fecha_entrega, subtotal, total, notas, tipo, creado_por_id)
                 VALUES (?, ?, ?, "confirmado", ?, ?, ?, ?, "personalizado", ?)',
                [$folio, $empresaId, $compradorId, $fechaEntrega, $total, $total, $nota ?: null, $creadoPorId]
            );
            $pedidoId = (int)$this->db->lastInsertId();

            foreach ($lineas as $l) {
                $this->execute(
                    'INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unit, subtotal) VALUES (?, ?, ?, ?, ?)',
                    [$pedidoId, $l['producto_id'], $l['cantidad'], $l['precio_unit'], $l['subtotal']]
                );
            }
            $this->db->commit();
            return $pedidoId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function countPendientes(int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM pedidos WHERE empresa_id = ? AND estado = 'pendiente'",
            [$empresaId]
        );
        return (int)($row['n'] ?? 0);
    }

    public function countConComprobantePendiente(int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM pedidos WHERE empresa_id = ? AND estado = 'confirmado' AND foto_comprobante_path IS NOT NULL",
            [$empresaId]
        );
        return (int)($row['n'] ?? 0);
    }

    public function pendientesAprobacion(int $empresaId): array
    {
        return $this->query(
            "SELECT p.*, u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.empresa_id = ? AND p.requiere_aprobacion = 1 AND p.estado = 'pendiente'
              ORDER BY p.created_at DESC",
            [$empresaId]
        );
    }

    public function getUltimosPedidosComprador(int $compradorId, int $empresaId, int $limite = 5): array
    {
        return $this->query(
            "SELECT p.id, p.folio, p.total, p.estado, p.created_at
               FROM pedidos p
              WHERE p.comprador_id = ? AND p.empresa_id = ?
              ORDER BY p.created_at DESC LIMIT ?",
            [$compradorId, $empresaId, $limite]
        );
    }

    public function getPedidosEnRuta(int $compradorId, int $empresaId, int $limite = 3): array
    {
        return $this->query(
            "SELECT p.id, p.folio, p.estado
               FROM pedidos p
              WHERE p.comprador_id = ? AND p.empresa_id = ? AND p.estado = 'en_ruta'
              ORDER BY p.created_at DESC LIMIT ?",
            [$compradorId, $empresaId, $limite]
        );
    }

    public function conDetalle(int $id): ?array
    {
        $pedido = $this->queryOne(
            "SELECT p.*,
                    u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido,
                    ap.nombre AS aprobador_nombre,
                    e.razon_social AS empresa_nombre
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
               JOIN empresas e ON e.id = p.empresa_id
          LEFT JOIN usuarios ap ON ap.id = p.aprobado_por
              WHERE p.id = ?",
            [$id]
        );
        if (!$pedido) return null;

        $pedido['items'] = $this->query(
            'SELECT pd.*, pr.nombre AS producto_nombre, pr.presentacion
               FROM pedido_detalle pd
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE pd.pedido_id = ?',
            [$id]
        );

        $pedido['sucursales'] = $this->query(
            'SELECT ps.*, s.nombre AS sucursal_nombre, s.direccion, s.lat, s.lng
               FROM pedido_sucursal ps
               JOIN sucursales s ON s.id = ps.sucursal_id
              WHERE ps.pedido_id = ?
              ORDER BY ps.id ASC',
            [$id]
        );

        // Distribución de productos por parada
        foreach ($pedido['sucursales'] as &$ps) {
            $ps['items'] = $this->query(
                'SELECT psd.cantidad, psd.precio_unit, psd.subtotal,
                        pr.nombre AS producto_nombre, pr.presentacion
                   FROM pedido_sucursal_detalle psd
                   JOIN productos pr ON pr.id = psd.producto_id
                  WHERE psd.pedido_sucursal_id = ?
                  ORDER BY pr.nombre',
                [$ps['id']]
            );
        }
        unset($ps);

        return $pedido;
    }

    public function aprobar(int $id, int $aprobadoPor): bool
    {
        $ok = $this->execute(
            "UPDATE pedidos
                SET estado = 'confirmado', aprobado_por = ?, aprobado_at = NOW()
              WHERE id = ? AND estado = 'pendiente' AND requiere_aprobacion = 1",
            [$aprobadoPor, $id]
        );
        if ($ok) $this->logEstado($id, 'confirmado');
        return $ok;
    }

    public function rechazar(int $id, int $rechazadoPor, string $motivo): bool
    {
        $ok = $this->execute(
            "UPDATE pedidos
                SET estado = 'cancelado', aprobado_por = ?, aprobado_at = NOW(),
                    notas = CONCAT(COALESCE(notas,''), IF(notas IS NULL OR notas='','','\n'), 'Rechazado: ', ?)
              WHERE id = ? AND estado = 'pendiente'",
            [$rechazadoPor, $motivo, $id]
        );
        if ($ok) $this->logEstado($id, 'cancelado');
        return $ok;
    }

    public function getTrackingActivo(int $pedidoId): ?array
    {
        return $this->queryOne(
            "SELECT rd.lat_actual, rd.lng_actual, rd.eta_minutos, rd.estado,
                    s.nombre AS sucursal_nombre, s.lat AS sucursal_lat, s.lng AS sucursal_lng,
                    u.nombre AS repartidor_nombre, p.estado AS pedido_estado
               FROM ruta_detalle rd
               JOIN rutas r        ON r.id = rd.ruta_id
               JOIN sucursales s   ON s.id = rd.sucursal_id
               JOIN usuarios u     ON u.id = r.repartidor_id
               JOIN pedidos p      ON p.id = rd.pedido_id
              WHERE rd.pedido_id = ? AND rd.tracking_activo = 1
              LIMIT 1",
            [$pedidoId]
        );
    }

    public function verificarPertenece(int $id, int $empresaId): bool
    {
        return $this->queryOne(
            'SELECT id FROM pedidos WHERE id = ? AND empresa_id = ?',
            [$id, $empresaId]
        ) !== null;
    }

    public function getItemsPedido(int $pedidoId): array
    {
        return $this->query(
            'SELECT pd.id, pd.producto_id, pd.cantidad, pd.precio_unit, pd.precio_original, pd.subtotal,
                    pr.nombre AS producto_nombre, pr.presentacion
               FROM pedido_detalle pd
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE pd.pedido_id = ?
           ORDER BY pr.nombre',
            [$pedidoId]
        );
    }

    public function getSucursalesPedido(int $pedidoId): array
    {
        return $this->query(
            'SELECT ps.id, ps.estado, ps.foto_entrega_path, ps.firma_path, ps.fecha_llegada,
                    s.nombre AS sucursal_nombre, s.direccion, s.lat, s.lng
               FROM pedido_sucursal ps
               JOIN sucursales s ON s.id = ps.sucursal_id
              WHERE ps.pedido_id = ?
              ORDER BY ps.id ASC',
            [$pedidoId]
        );
    }

    public function cancelar(int $id, int $usuarioId): bool
    {
        return $this->execute(
            "UPDATE pedidos
                SET estado = 'cancelado'
              WHERE id = ? AND comprador_id = ? AND estado IN ('pendiente')",
            [$id, $usuarioId]
        );
    }

    public function getPedidosEnRutaEmpresa(int $empresaId, int $limit = 10): array
    {
        return $this->query(
            "SELECT p.id, p.folio, p.total, p.created_at,
                    u.nombre AS comprador_nombre
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.empresa_id = ? AND p.estado = 'en_ruta'
              ORDER BY p.created_at DESC LIMIT ?",
            [$empresaId, $limit]
        );
    }

    public function getEntregadosHoy(int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total FROM pedidos
              WHERE empresa_id = ? AND estado = 'entregado' AND DATE(updated_at) = CURDATE()",
            [$empresaId]
        );
        return (int)($row['total'] ?? 0);
    }

    // ── Panel Admin ───────────────────────────────────────────────────────────

    public function listadoGlobal(array $filtros = [], int $page = 1): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['empresa_id'])) {
            $where[]  = 'p.empresa_id = ?';
            $params[] = $filtros['empresa_id'];
        }
        if (!empty($filtros['estado'])) {
            $where[]  = 'p.estado = ?';
            $params[] = $filtros['estado'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = '(p.folio LIKE ? OR u.nombre LIKE ? OR e.razon_social LIKE ?)';
            $t = '%' . $filtros['buscar'] . '%';
            array_push($params, $t, $t, $t);
        }

        $sql = 'SELECT p.*, u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido,
                       e.razon_social AS empresa_nombre
                  FROM pedidos p
                  JOIN usuarios u ON u.id = p.comprador_id
                  JOIN empresas e ON e.id = p.empresa_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY p.created_at DESC';

        return $this->paginate($sql, $params, $page);
    }

    public function cambiarEstado(int $id, string $estado): bool
    {
        $validos = ['pendiente', 'confirmado', 'en_preparacion', 'en_ruta', 'entregado', 'cancelado'];
        if (!in_array($estado, $validos, true)) return false;

        $ok = $this->execute('UPDATE pedidos SET estado = ? WHERE id = ?', [$estado, $id]);
        if ($ok) $this->logEstado($id, $estado);
        return $ok;
    }

    private function logEstado(int $pedidoId, string $estado): void
    {
        try {
            $usuarioId = $_SESSION['usuario']['id'] ?? null;
            $this->execute(
                'INSERT INTO pedido_historial (pedido_id, estado, usuario_id) VALUES (?, ?, ?)',
                [$pedidoId, $estado, $usuarioId]
            );
        } catch (\Throwable $e) {
            // tabla aún no migrada — no bloquear la operación principal
        }
    }

    public function getHistorial(int $id): array
    {
        try {
            return $this->query(
                "SELECT ph.estado, ph.created_at,
                        CONCAT(COALESCE(u.nombre,''), ' ', COALESCE(u.apellido_paterno,'')) AS usuario_nombre
                   FROM pedido_historial ph
                   LEFT JOIN usuarios u ON u.id = ph.usuario_id
                  WHERE ph.pedido_id = ?
                  ORDER BY ph.created_at ASC",
                [$id]
            );
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function listadoConfirmadosPorEmpresa(int $empresaId): array
    {
        return $this->query(
            "SELECT p.id, p.folio, p.total,
                    u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.empresa_id = ? AND p.estado IN ('confirmado', 'aprobado')
              ORDER BY p.created_at DESC",
            [$empresaId]
        );
    }

    public function crearRuta(int $repartidorId, int $empresaId, string $fecha, array $pedidosIds): int
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO rutas (repartidor_id, empresa_id, fecha, estado) VALUES (?, ?, ?, "pendiente")'
            );
            $stmt->execute([$repartidorId, $empresaId, $fecha]);
            $rutaId = (int)$this->db->lastInsertId();

            foreach ($pedidosIds as $pedidoId) {
                $pedido = $this->conDetalle((int)$pedidoId);
                if (!$pedido) continue;
                foreach ($pedido['sucursales'] as $suc) {
                    $this->execute(
                        'INSERT INTO ruta_detalle (ruta_id, pedido_id, sucursal_id, orden, estado) VALUES (?, ?, ?, 0, "pendiente")',
                        [$rutaId, $pedidoId, $suc['sucursal_id']]
                    );
                }
                $this->update((int)$pedidoId, ['estado' => 'en_preparacion']);
            }

            $this->db->commit();
            return $rutaId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getRutasActivas(int $empresaId = 0): array
    {
        $filtroEmpresa = $empresaId > 0 ? 'AND r.empresa_id = ?' : '';
        $params = $empresaId > 0 ? [$empresaId] : [];

        return $this->query(
            "SELECT r.id, r.fecha, r.estado,
                    u.nombre AS repartidor_nombre, u.apellido_paterno AS repartidor_apellido,
                    e.razon_social AS empresa_nombre,
                    COUNT(rd.id) AS total_paradas,
                    SUM(rd.estado = 'entregado') AS entregadas
               FROM rutas r
               JOIN usuarios u ON u.id = r.repartidor_id
               JOIN empresas e ON e.id = r.empresa_id
               JOIN ruta_detalle rd ON rd.ruta_id = r.id
              WHERE r.estado IN ('planificada', 'en_curso')
                    $filtroEmpresa
              GROUP BY r.id
              ORDER BY r.fecha DESC
              LIMIT 50",
            $params
        );
    }

    public function getPosicionesActivas(int $empresaId = 0): array
    {
        $filtroEmpresa = $empresaId > 0 ? 'AND r.empresa_id = ?' : '';
        $params = $empresaId > 0 ? [$empresaId] : [];

        return $this->query(
            "SELECT DISTINCT rd.lat_actual, rd.lng_actual,
                    u.nombre AS repartidor_nombre,
                    r.id AS ruta_id
               FROM ruta_detalle rd
               JOIN rutas r ON r.id = rd.ruta_id
               JOIN usuarios u ON u.id = r.repartidor_id
              WHERE rd.tracking_activo = 1
                AND rd.lat_actual IS NOT NULL
                    $filtroEmpresa",
            $params
        );
    }

    // ── Métodos de resumen para SupervisorController ─────────────────────────

    public function countEntregadosHoy(int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM pedidos
              WHERE empresa_id = ? AND estado = 'entregado' AND DATE(created_at) = CURDATE()",
            [$empresaId]
        );
        return (int)($row['n'] ?? 0);
    }

    public function countPedidosHoy(int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM pedidos
              WHERE empresa_id = ? AND DATE(created_at) = CURDATE()",
            [$empresaId]
        );
        return (int)($row['n'] ?? 0);
    }

    public function montoMes(int $empresaId): float
    {
        $row = $this->queryOne(
            "SELECT COALESCE(SUM(total), 0) AS monto FROM pedidos
              WHERE empresa_id = ? AND estado NOT IN ('cancelado')
                AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())",
            [$empresaId]
        );
        return (float)($row['monto'] ?? 0);
    }

    // ── Analytics para dashboard de supervisión ──────────────────────────────

    public function kpisResumen(int $empresaId, string $desde, string $hasta): array
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS total_pedidos,
                    COALESCE(SUM(total), 0) AS monto_total,
                    SUM(estado = 'entregado')    AS entregados,
                    SUM(estado = 'cancelado')    AS cancelados,
                    SUM(estado = 'pendiente')    AS pendientes,
                    SUM(estado = 'en_ruta')      AS en_ruta,
                    SUM(estado = 'confirmado')   AS confirmados,
                    SUM(estado = 'en_preparacion') AS en_preparacion,
                    AVG(CASE WHEN aprobado_at IS NOT NULL
                             THEN TIMESTAMPDIFF(MINUTE, created_at, aprobado_at)
                             ELSE NULL END) AS avg_minutos_aprobacion
               FROM pedidos
              WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?",
            [$empresaId, $desde, $hasta]
        );
        return $row ?? [];
    }

    public function pedidosPorDia(int $empresaId, string $desde, string $hasta): array
    {
        return $this->query(
            "SELECT DATE(created_at) AS dia,
                    COUNT(*) AS total_pedidos,
                    COALESCE(SUM(total), 0) AS monto_total
               FROM pedidos
              WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?
                AND estado != 'cancelado'
              GROUP BY DATE(created_at)
              ORDER BY dia ASC",
            [$empresaId, $desde, $hasta]
        );
    }

    public function pedidosPorEstado(int $empresaId, string $desde, string $hasta): array
    {
        return $this->query(
            "SELECT estado, COUNT(*) AS total
               FROM pedidos
              WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?
              GROUP BY estado
              ORDER BY total DESC",
            [$empresaId, $desde, $hasta]
        );
    }

    public function topProductos(int $empresaId, string $desde, string $hasta, int $limite = 8): array
    {
        return $this->query(
            "SELECT pr.nombre, pr.presentacion,
                    SUM(pd.cantidad) AS total_cantidad,
                    SUM(pd.subtotal) AS total_monto
               FROM pedido_detalle pd
               JOIN pedidos p  ON p.id  = pd.pedido_id
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE p.empresa_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
                AND p.estado != 'cancelado'
              GROUP BY pd.producto_id
              ORDER BY total_cantidad DESC
              LIMIT $limite",
            [$empresaId, $desde, $hasta]
        );
    }

    public function pedidosRecientes(int $empresaId, int $limite = 8): array
    {
        return $this->query(
            "SELECT p.id, p.folio, p.estado, p.total, p.created_at,
                    u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.empresa_id = ?
              ORDER BY p.created_at DESC
              LIMIT $limite",
            [$empresaId]
        );
    }

    // ── Entrega por sucursal (flujo multi-parada directo) ─────────────────────

    public function confirmarSucursalEntrega(int $pedidoSucursalId, string $fotoPath, ?string $firmaPath = null, ?string $nombreReceptor = null): void
    {
        $this->execute(
            "UPDATE pedido_sucursal
                SET estado = 'entregado', foto_entrega_path = ?, firma_path = ?,
                    nombre_receptor = ?, fecha_llegada = NOW()
              WHERE id = ?",
            [$fotoPath, $firmaPath, $nombreReceptor, $pedidoSucursalId]
        );
    }

    public function allSucursalesEntregadas(int $pedidoId): bool
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM pedido_sucursal WHERE pedido_id = ? AND estado != 'entregado'"
        );
        $stmt->execute([$pedidoId]);
        return (int)$stmt->fetchColumn() === 0;
    }

    public function saveRutaPolyline(int $pedidoId, string $polylineJson): void
    {
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE pedidos
                SET ruta_polyline = ?, ruta_finalizada_at = NOW(), estado = 'entregado'
              WHERE id = ?"
        )->execute([$polylineJson, $pedidoId]);

        $db->prepare('DELETE FROM tracking_posiciones WHERE pedido_id = ?')
           ->execute([$pedidoId]);

        $this->logEstado($pedidoId, 'entregado');
    }

    public function asignarCostosEnvioParadas(int $pedidoId, array $envios): void
    {
        if (empty($envios)) return;
        $totalEnvio = 0.0;
        foreach ($envios as $psId => $costo) {
            $psId   = (int)$psId;
            $costo  = round(max(0.0, (float)$costo), 2);
            $this->execute(
                'UPDATE pedido_sucursal SET costo_envio_sucursal = ? WHERE id = ? AND pedido_id = ?',
                [$costo, $psId, $pedidoId]
            );
            $totalEnvio += $costo;
        }
        $this->execute(
            'UPDATE pedidos SET costo_envio = ?, total = subtotal + ? WHERE id = ?',
            [$totalEnvio, $totalEnvio, $pedidoId]
        );
    }

    // ── KPIs operativos del Supervisor ───────────────────────────────────────

    /**
     * Pedidos pendientes de aprobación cuyo tiempo de espera supera $minutos.
     * Indicador crítico de SLA: lo primero que el supervisor debe atender.
     */
    public function pedidosDemoradosAprobacion(int $empresaId, int $minutos = 15): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n
               FROM pedidos
              WHERE empresa_id = ?
                AND estado = 'pendiente'
                AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > ?",
            [$empresaId, $minutos]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Excepciones de límite registradas en bitácora dentro del período.
     * Cuenta intentos en los que el comprador excedió su límite de compra.
     */
    public function excepcionesLimite(int $empresaId, string $desde, string $hasta): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n
               FROM action_logs
              WHERE empresa_id = ?
                AND DATE(created_at) BETWEEN ? AND ?
                AND (
                    accion LIKE '%limite%'
                 OR accion LIKE '%límite%'
                 OR descripcion LIKE '%limite excedido%'
                 OR descripcion LIKE '%límite excedido%'
                 OR descripcion LIKE '%excede límite%'
                 OR descripcion LIKE '%excede limite%'
                )",
            [$empresaId, $desde, $hasta]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Incidencias de reparto: paradas marcadas como fallidas o pedidos en ruta
     * cuya fecha de entrega ya venció sin completarse.
     */
    public function incidenciasReparto(int $empresaId, string $desde, string $hasta): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n
               FROM ruta_detalle rd
               JOIN rutas   r ON r.id = rd.ruta_id
               JOIN pedidos p ON p.id = rd.pedido_id
              WHERE p.empresa_id = ?
                AND DATE(r.fecha) BETWEEN ? AND ?
                AND (
                    rd.estado = 'fallido'
                 OR (rd.estado = 'pendiente' AND r.fecha < CURDATE())
                )",
            [$empresaId, $desde, $hasta]
        );
        return (int)($row['n'] ?? 0);
    }

    // ── KPIs y analytics del Comprador ────────────────────────────────────

    /**
     * Kilos totales comprados en el mes en curso (productos con presentacion='kg').
     */
    public function kgTotalesMes(int $compradorId, int $empresaId): float
    {
        $row = $this->queryOne(
            "SELECT COALESCE(SUM(pd.cantidad),0) AS kg
               FROM pedido_detalle pd
               JOIN pedidos p   ON p.id = pd.pedido_id
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE p.comprador_id = ? AND p.empresa_id = ?
                AND p.estado != 'cancelado'
                AND pr.presentacion = 'kg'
                AND YEAR(p.created_at)  = YEAR(CURDATE())
                AND MONTH(p.created_at) = MONTH(CURDATE())",
            [$compradorId, $empresaId]
        );
        return (float)($row['kg'] ?? 0);
    }

    /**
     * Gasto total del mes en curso (sin cancelados).
     */
    public function gastoMesComprador(int $compradorId, int $empresaId): float
    {
        $row = $this->queryOne(
            "SELECT COALESCE(SUM(total),0) AS g
               FROM pedidos
              WHERE comprador_id = ? AND empresa_id = ?
                AND estado != 'cancelado'
                AND YEAR(created_at)  = YEAR(CURDATE())
                AND MONTH(created_at) = MONTH(CURDATE())",
            [$compradorId, $empresaId]
        );
        return (float)($row['g'] ?? 0);
    }

    /**
     * Pedidos del comprador en tránsito con tracking GPS activo.
     */
    public function pedidosEnTransitoConGps(int $compradorId, int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(DISTINCT p.id) AS n
               FROM pedidos p
          LEFT JOIN ruta_detalle rd ON rd.pedido_id = p.id
              WHERE p.comprador_id = ? AND p.empresa_id = ?
                AND p.estado = 'en_ruta'
                AND rd.tracking_activo = 1",
            [$compradorId, $empresaId]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Ahorro acumulado por motor de precios escalonados:
     * SUM((precio_original - precio_unit) * cantidad) cuando precio_original > precio_unit.
     */
    public function ahorroPorVolumen(int $compradorId, int $empresaId, string $desde, string $hasta): float
    {
        $row = $this->queryOne(
            "SELECT COALESCE(SUM((pd.precio_original - pd.precio_unit) * pd.cantidad),0) AS ahorro
               FROM pedido_detalle pd
               JOIN pedidos p ON p.id = pd.pedido_id
              WHERE p.comprador_id = ? AND p.empresa_id = ?
                AND p.estado != 'cancelado'
                AND DATE(p.created_at) BETWEEN ? AND ?
                AND pd.precio_original IS NOT NULL
                AND pd.precio_original > pd.precio_unit",
            [$compradorId, $empresaId, $desde, $hasta]
        );
        return (float)($row['ahorro'] ?? 0);
    }

    /**
     * Plantillas de pedidos recurrentes activas para la empresa del comprador.
     */
    public function recurrentesActivos(int $empresaId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM pedidos_recurrentes
              WHERE empresa_id = ? AND activo = 1",
            [$empresaId]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Próxima entrega del comprador (pedido en ruta o confirmado más cercano).
     */
    public function proximaEntregaComprador(int $compradorId, int $empresaId): ?array
    {
        return $this->queryOne(
            "SELECT p.id, p.folio, p.estado, p.fecha_entrega, p.total,
                    rd.eta_minutos
               FROM pedidos p
          LEFT JOIN ruta_detalle rd ON rd.pedido_id = p.id AND rd.tracking_activo = 1
              WHERE p.comprador_id = ? AND p.empresa_id = ?
                AND p.estado IN ('confirmado','en_preparacion','en_ruta')
              ORDER BY (p.fecha_entrega IS NULL), p.fecha_entrega ASC, p.created_at ASC
              LIMIT 1",
            [$compradorId, $empresaId]
        );
    }

    /**
     * Producto más comprado por el comprador en el período.
     */
    public function topProductoComprador(int $compradorId, int $empresaId, string $desde, string $hasta): ?array
    {
        return $this->queryOne(
            "SELECT pr.nombre, pr.presentacion,
                    SUM(pd.cantidad) AS total_cantidad
               FROM pedido_detalle pd
               JOIN pedidos   p  ON p.id  = pd.pedido_id
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE p.comprador_id = ? AND p.empresa_id = ?
                AND p.estado != 'cancelado'
                AND DATE(p.created_at) BETWEEN ? AND ?
              GROUP BY pd.producto_id
              ORDER BY total_cantidad DESC
              LIMIT 1",
            [$compradorId, $empresaId, $desde, $hasta]
        );
    }

    /**
     * Consumo del comprador agrupado por categoría (monto) en el período.
     */
    public function consumoPorCategoriaComprador(int $compradorId, int $empresaId, string $desde, string $hasta): array
    {
        return $this->query(
            "SELECT c.nombre AS categoria,
                    COALESCE(SUM(pd.subtotal),0) AS monto,
                    COALESCE(SUM(pd.cantidad),0) AS cantidad
               FROM pedido_detalle pd
               JOIN pedidos    p  ON p.id  = pd.pedido_id
               JOIN productos  pr ON pr.id = pd.producto_id
               JOIN categorias c  ON c.id  = pr.categoria_id
              WHERE p.comprador_id = ? AND p.empresa_id = ?
                AND p.estado != 'cancelado'
                AND DATE(p.created_at) BETWEEN ? AND ?
              GROUP BY c.id, c.nombre
              ORDER BY monto DESC",
            [$compradorId, $empresaId, $desde, $hasta]
        );
    }

    /**
     * Histórico de gasto semanal del comprador (últimas N semanas).
     */
    public function gastoSemanalComprador(int $compradorId, int $empresaId, int $semanas = 8): array
    {
        $semanas = max(1, min(52, $semanas));
        return $this->query(
            "SELECT YEARWEEK(created_at, 3) AS yw,
                    MIN(DATE(created_at)) AS desde,
                    COALESCE(SUM(total),0) AS gasto,
                    COUNT(*) AS pedidos
               FROM pedidos
              WHERE comprador_id = ? AND empresa_id = ?
                AND estado != 'cancelado'
                AND created_at >= DATE_SUB(CURDATE(), INTERVAL $semanas WEEK)
              GROUP BY YEARWEEK(created_at, 3)
              ORDER BY yw",
            [$compradorId, $empresaId]
        );
    }

    // ── KPIs y analytics del Repartidor ─────────────────────────────────────

    /**
     * Resumen de paradas del día para un repartidor (programadas / entregadas / pendientes / fallidas).
     */
    public function paradasHoyRepartidor(int $repartidorId): array
    {
        try {
            $row = $this->queryOne(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN rd.estado = 'entregado' THEN 1 ELSE 0 END) AS entregadas,
                        SUM(CASE WHEN rd.estado = 'pendiente' THEN 1 ELSE 0 END) AS pendientes,
                        SUM(CASE WHEN rd.estado = 'fallido'   THEN 1 ELSE 0 END) AS fallidas,
                        SUM(CASE WHEN rd.estado = 'parcial'   THEN 1 ELSE 0 END) AS parciales
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
                  WHERE r.repartidor_id = ? AND r.fecha = CURDATE()",
                [$repartidorId]
            );
        } catch (\Throwable $e) {
            error_log('paradasHoyRepartidor: ' . $e->getMessage());
            $row = null;
        }
        return [
            'total'      => (int)($row['total']      ?? 0),
            'entregadas' => (int)($row['entregadas'] ?? 0),
            'pendientes' => (int)($row['pendientes'] ?? 0),
            'fallidas'   => (int)($row['fallidas']   ?? 0),
            'parciales'  => (int)($row['parciales']  ?? 0),
        ];
    }

    /**
     * Kilos pendientes de descargar hoy: suma de cantidades (presentacion='kg')
     * de pedidos cuyas paradas siguen pendientes en la ruta del repartidor.
     */
    public function kilosPendientesHoy(int $repartidorId): float
    {
        try {
            $row = $this->queryOne(
                "SELECT COALESCE(SUM(pd.cantidad),0) AS kg
                   FROM ruta_detalle rd
                   JOIN rutas r       ON r.id = rd.ruta_id
                   JOIN pedido_detalle pd ON pd.pedido_id = rd.pedido_id
                   JOIN productos pr  ON pr.id = pd.producto_id
                  WHERE r.repartidor_id = ? AND r.fecha = CURDATE()
                    AND rd.estado = 'pendiente'
                    AND pr.presentacion = 'kg'",
                [$repartidorId]
            );
            return (float)($row['kg'] ?? 0);
        } catch (\Throwable $e) {
            error_log('kilosPendientesHoy: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Próxima parada del repartidor (siguiente pendiente por orden en la ruta de hoy).
     */
    public function proximaParadaRepartidor(int $repartidorId): ?array
    {
        try {
            $row = $this->queryOne(
                "SELECT rd.id, rd.orden, rd.eta_minutos, p.folio, p.fecha_entrega,
                        s.nombre AS sucursal_nombre
                   FROM ruta_detalle rd
                   JOIN rutas r       ON r.id = rd.ruta_id
                   JOIN pedidos p     ON p.id = rd.pedido_id
                   JOIN sucursales s  ON s.id = rd.sucursal_id
                  WHERE r.repartidor_id = ? AND r.fecha = CURDATE()
                    AND rd.estado = 'pendiente'
                  ORDER BY rd.orden ASC
                  LIMIT 1",
                [$repartidorId]
            );
            return $row ?: null;
        } catch (\Throwable $e) {
            error_log('proximaParadaRepartidor: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Cumplimiento de evidencia: % de paradas entregadas con foto + firma capturadas.
     */
    public function cumplimientoEvidencia(int $repartidorId, string $desde, string $hasta): array
    {
        $row = null;
        try {
            $row = $this->queryOne(
                "SELECT
                    COUNT(rd.id) AS entregadas,
                    SUM(CASE WHEN ev.firma_path IS NOT NULL AND ev.foto_path IS NOT NULL THEN 1 ELSE 0 END) AS completas
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
              LEFT JOIN evidencias_entrega ev ON ev.ruta_detalle_id = rd.id
                  WHERE r.repartidor_id = ?
                    AND DATE(r.fecha) BETWEEN ? AND ?
                    AND rd.estado = 'entregado'",
                [$repartidorId, $desde, $hasta]
            );
        } catch (\Throwable $e) {
            error_log('cumplimientoEvidencia: ' . $e->getMessage());
        }
        $entregadas = (int)($row['entregadas'] ?? 0);
        $completas  = (int)($row['completas'] ?? 0);
        return [
            'entregadas' => $entregadas,
            'completas'  => $completas,
            'pct'        => $entregadas > 0 ? round(($completas / $entregadas) * 100, 1) : 0.0,
        ];
    }

    /**
     * Incidencias de ruta del repartidor (paradas fallidas o parciales) en el período.
     */
    public function incidenciasRutaRepartidor(int $repartidorId, string $desde, string $hasta): int
    {
        try {
            $row = $this->queryOne(
                "SELECT COUNT(*) AS n
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
                  WHERE r.repartidor_id = ?
                    AND DATE(r.fecha) BETWEEN ? AND ?
                    AND rd.estado IN ('fallido','parcial')",
                [$repartidorId, $desde, $hasta]
            );
            return (int)($row['n'] ?? 0);
        } catch (\Throwable $e) {
            error_log('incidenciasRutaRepartidor: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Productividad semanal: entregadas vs intentos (paradas) agrupado por semana.
     */
    public function productividadSemanalRepartidor(int $repartidorId, int $semanas = 6): array
    {
        $semanas = max(1, min(52, $semanas));
        try {
            $rows = $this->query(
                "SELECT YEARWEEK(r.fecha, 3) AS yw,
                        MIN(r.fecha) AS desde,
                        COUNT(rd.id) AS intentos,
                        SUM(CASE WHEN rd.estado = 'entregado' THEN 1 ELSE 0 END) AS entregadas
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
                  WHERE r.repartidor_id = ?
                    AND r.fecha >= DATE_SUB(CURDATE(), INTERVAL $semanas WEEK)
                  GROUP BY YEARWEEK(r.fecha, 3)
                  ORDER BY yw",
                [$repartidorId]
            );
            return is_array($rows) ? $rows : [];
        } catch (\Throwable $e) {
            error_log('productividadSemanalRepartidor: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Tiempo promedio (minutos) por parada del repartidor en el período.
     * Calculado por diferencia entre hora_entrega de paradas consecutivas.
     */
    public function tiempoPromedioPorParada(int $repartidorId, string $desde, string $hasta): float
    {
        // Intento con LAG() (MySQL 8+). Fallback PHP-side si la versión no lo soporta.
        try {
            $row = $this->queryOne(
                "SELECT AVG(diff_min) AS prom FROM (
                    SELECT TIMESTAMPDIFF(MINUTE, prev_entrega, hora_entrega) AS diff_min
                      FROM (
                        SELECT rd.id, rd.ruta_id, rd.orden, rd.hora_entrega,
                               LAG(rd.hora_entrega) OVER (PARTITION BY rd.ruta_id ORDER BY rd.orden) AS prev_entrega
                          FROM ruta_detalle rd
                          JOIN rutas r ON r.id = rd.ruta_id
                         WHERE r.repartidor_id = ?
                           AND DATE(r.fecha) BETWEEN ? AND ?
                           AND rd.estado = 'entregado'
                           AND rd.hora_entrega IS NOT NULL
                      ) sub
                     WHERE prev_entrega IS NOT NULL
                  ) t",
                [$repartidorId, $desde, $hasta]
            );
            return (float)($row['prom'] ?? 0);
        } catch (\Throwable $e) {
            // Fallback compatible con MySQL 5.7: traemos las paradas y calculamos en PHP.
            try {
                $rows = $this->query(
                    "SELECT rd.ruta_id, rd.orden, rd.hora_entrega
                       FROM ruta_detalle rd
                       JOIN rutas r ON r.id = rd.ruta_id
                      WHERE r.repartidor_id = ?
                        AND DATE(r.fecha) BETWEEN ? AND ?
                        AND rd.estado = 'entregado'
                        AND rd.hora_entrega IS NOT NULL
                      ORDER BY rd.ruta_id, rd.orden",
                    [$repartidorId, $desde, $hasta]
                );
                $diffs = []; $prevRuta = null; $prevHora = null;
                foreach (($rows ?: []) as $r) {
                    if ($prevRuta === $r['ruta_id'] && $prevHora) {
                        $d = (strtotime($r['hora_entrega']) - strtotime($prevHora)) / 60;
                        if ($d > 0) $diffs[] = $d;
                    }
                    $prevRuta = $r['ruta_id'];
                    $prevHora = $r['hora_entrega'];
                }
                return $diffs ? array_sum($diffs) / count($diffs) : 0.0;
            } catch (\Throwable $e2) {
                error_log('tiempoPromedioPorParada fallback: ' . $e2->getMessage());
                return 0.0;
            }
        }
    }
}
