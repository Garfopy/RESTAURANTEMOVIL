<?php
/**
 * RestPedidoSugeridoModel
 *
 * Gestiona los pedidos de reabastecimiento sugeridos por el sistema
 * de forecast. Cuando el pedido es aprobado y enviado, el controlador
 * llama a CarniHubApiService::crearPedido() y luego a marcarConvertido().
 */
class RestPedidoSugeridoModel extends BaseModel
{
    protected string $table = 'rest_pedidos_sugeridos';

    // ── Consultas ─────────────────────────────────────────────────

    public function getByRestaurante(int $restauranteId, string $estado = ''): array
    {
        $params = [$restauranteId];
        $where  = '';
        if ($estado !== '') {
            $where    = 'AND ps.estado = ?';
            $params[] = $estado;
        }

        return $this->query(
            "SELECT ps.*,
                    COALESCE(cac.nombre_distribuidor, CONCAT('Proveedor #', ps.carnihub_empresa_id)) AS empresa_nombre,
                    u.nombre AS usuario_nombre,
                    (SELECT COUNT(*) FROM rest_pedido_sugerido_items WHERE pedido_sugerido_id = ps.id) AS items_count
             FROM rest_pedidos_sugeridos ps
             LEFT JOIN carnihub_api_config cac
                    ON cac.restaurante_id = ps.restaurante_id AND cac.activo = 1
             LEFT JOIN usuarios u ON u.id = ps.usuario_id
             WHERE ps.restaurante_id = ? $where
             ORDER BY ps.created_at DESC",
            $params
        );
    }

    public function getItems(int $pedidoSugeridoId): array
    {
        // Sin JOIN a `productos` ni `empresas` porque en modo standalone
        // esas tablas no existen; el catálogo vive en CarniHub remoto.
        return $this->query(
            "SELECT psi.*, i.nombre AS ingrediente_nombre, i.unidad_principal
             FROM rest_pedido_sugerido_items psi
             JOIN rest_ingredientes i ON i.id = psi.ingrediente_id
             WHERE psi.pedido_sugerido_id = ?
             ORDER BY i.nombre",
            [$pedidoSugeridoId]
        );
    }

    public function findConItems(int $id): ?array
    {
        $pedido = $this->find($id);
        if (!$pedido) return null;
        $pedido['items'] = $this->getItems($id);
        return $pedido;
    }

    public function countPendientes(int $restauranteId): int
    {
        $row = $this->queryOne(
            "SELECT COUNT(*) AS n FROM rest_pedidos_sugeridos
             WHERE restaurante_id = ? AND estado IN ('sugerido','aprobado')",
            [$restauranteId]
        );
        return (int)($row['n'] ?? 0);
    }

    /**
     * Ingredientes que ya tienen un pedido abierto para evitar re-pedirlos.
     *
     * Criterio de bloqueo:
     * - estado local en sugerido, aprobado o convertido
     * - excluye convertidos con estado CarniHub final (entregado/cancelado)
     *
     * @return int[]
     */
    public function getIngredientesConPedidoAbierto(int $restauranteId): array
    {
        $rows = $this->query(
            "SELECT DISTINCT psi.ingrediente_id
             FROM rest_pedidos_sugeridos ps
             JOIN rest_pedido_sugerido_items psi ON psi.pedido_sugerido_id = ps.id
             WHERE ps.restaurante_id = ?
               AND ps.estado IN ('sugerido','aprobado','convertido')
               AND NOT (
                   ps.estado = 'convertido'
                   AND COALESCE(ps.estado_carnihub, '') IN ('entregado','cancelado')
               )",
            [$restauranteId]
        );

        return array_values(array_map(
            static fn($r) => (int)$r['ingrediente_id'],
            $rows
        ));
    }

    // ── Crear ─────────────────────────────────────────────────────

    /**
     * Crea un pedido sugerido con sus items en una transacción.
     *
     * $data keys: restaurante_id, empresa_id, notas, usuario_id
     * $items: [['ingrediente_id', 'carnihub_producto_id', 'cantidad_sugerida',
     *           'unidad', 'precio_unit_estimado', 'subtotal_estimado'], ...]
     */
    public function crear(array $data, array $items): int
    {
        $this->db->beginTransaction();
        try {
            $total = array_sum(array_column($items, 'subtotal_estimado'));

            $id = $this->insert([
                'restaurante_id'      => $data['restaurante_id'],
                'carnihub_empresa_id' => $data['carnihub_empresa_id'],
                'estado'              => 'sugerido',
                'total_estimado'      => $total,
                'notas'               => $data['notas'] ?? null,
                'usuario_id'          => $data['usuario_id'] ?? null,
            ]);

            foreach ($items as $item) {
                $this->execute(
                    "INSERT INTO rest_pedido_sugerido_items
                     (pedido_sugerido_id, ingrediente_id, carnihub_producto_id,
                      cantidad_sugerida, cantidad_aprobada, unidad,
                      precio_unit_estimado, subtotal_estimado)
                     VALUES (?,?,?,?,?,?,?,?)",
                    [
                        $id,
                        (int)$item['ingrediente_id'],
                        (int)$item['carnihub_producto_id'],
                        (float)$item['cantidad_sugerida'],
                        (float)$item['cantidad_sugerida'],   // aprobada = sugerida por defecto
                        $item['unidad'],
                        (float)$item['precio_unit_estimado'],
                        (float)$item['subtotal_estimado'],
                    ]
                );
            }

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Estado ────────────────────────────────────────────────────

    public function cambiarEstado(int $id, string $estado, ?int $usuarioId = null): void
    {
        $sets   = ['estado = ?'];
        $params = [$estado];

        if ($estado === 'aprobado') {
            $sets[]   = 'aprobado_at = NOW()';
            $sets[]   = 'usuario_id = ?';
            $params[] = $usuarioId;
        }

        $params[] = $id;
        $this->execute(
            'UPDATE rest_pedidos_sugeridos SET ' . implode(', ', $sets) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * Actualiza las cantidades aprobadas (ajustadas manualmente) y recalcula total.
     * $cantidades: [item_id => cantidad_aprobada, ...]
     */
    public function actualizarCantidades(int $pedidoId, array $cantidades): void
    {
        $this->db->beginTransaction();
        try {
            foreach ($cantidades as $itemId => $cantidad) {
                $item = $this->queryOne(
                    'SELECT precio_unit_estimado FROM rest_pedido_sugerido_items WHERE id = ? AND pedido_sugerido_id = ?',
                    [(int)$itemId, $pedidoId]
                );
                if (!$item) continue;
                $subtotal = round((float)$cantidad * (float)$item['precio_unit_estimado'], 2);
                $this->execute(
                    'UPDATE rest_pedido_sugerido_items SET cantidad_aprobada = ?, subtotal_estimado = ? WHERE id = ?',
                    [(float)$cantidad, $subtotal, (int)$itemId]
                );
            }
            // Recalcular total del pedido
            $row = $this->queryOne(
                'SELECT SUM(subtotal_estimado) AS total FROM rest_pedido_sugerido_items WHERE pedido_sugerido_id = ?',
                [$pedidoId]
            );
            $this->execute(
                'UPDATE rest_pedidos_sugeridos SET total_estimado = ? WHERE id = ?',
                [(float)($row['total'] ?? 0), $pedidoId]
            );
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    // ── Envío a CarniHub ──────────────────────────────────────────

    /**
     * Marca el pedido sugerido como convertido tras enviarlo exitosamente
     * a la API de CarniHub. El controlador es quien llama a
     * CarniHubApiService::crearPedido() y obtiene el $pedidoExternoId.
     *
     * @param int $id              ID del pedido sugerido
     * @param int $pedidoExternoId ID retornado por CarniHub (0 si no lo devuelve)
     */
    public function marcarConvertido(int $id, int $pedidoExternoId = 0): void
    {
        $this->execute(
            "UPDATE rest_pedidos_sugeridos
             SET estado = 'convertido',
                 pedido_carnihub_id = ?,
                 aprobado_at = COALESCE(aprobado_at, NOW())
             WHERE id = ?",
            [$pedidoExternoId ?: null, $id]
        );
    }

    /**
     * Devuelve los items del pedido en el formato esperado por
     * CarniHubApiService::crearPedido() (usa cantidad_aprobada si existe).
     */
    public function prepararItemsParaApi(int $pedidoId): array
    {
        $result = [];
        foreach ($this->getItems($pedidoId) as $item) {
            $cant   = (float)($item['cantidad_aprobada'] ?? $item['cantidad_sugerida']);
            $precio = (float)$item['precio_unit_estimado'];
            $prodId = (int)($item['carnihub_producto_id'] ?? 0);
            if ($prodId <= 0 || $cant <= 0 || $precio <= 0) continue;
            $result[] = [
                'producto_id' => $prodId,
                'cantidad'    => $cant,
                'precio_unit' => $precio,
            ];
        }
        return $result;
    }

    /**
     * Actualiza el estado reportado por CarniHub y la fecha de la última sincronización.
     * Se llama desde seguimientoPedido() en el controlador.
     */
    public function syncEstadoCarnihub(int $id, string $estadoCarnihub): void
    {
        $this->execute(
            "UPDATE rest_pedidos_sugeridos
             SET estado_carnihub = ?, ultima_sync_carnihub = NOW()
             WHERE id = ?",
            [$estadoCarnihub, $id]
        );
    }

}
