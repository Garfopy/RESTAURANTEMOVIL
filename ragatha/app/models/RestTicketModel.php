<?php
class RestTicketModel extends BaseModel
{
    protected string $table = 'rest_tickets';

    public function generarFolio(int $restauranteId): string
    {
        $count = $this->queryOne(
            "SELECT COUNT(*) AS c FROM rest_tickets WHERE restaurante_id = ?",
            [$restauranteId]
        );
        return 'T-' . str_pad((int)($count['c'] ?? 0) + 1, 5, '0', STR_PAD_LEFT);
    }

    public function consolidar(int $visitaId, float $propina = 0): int
    {
        $existing = $this->queryOne(
            "SELECT id FROM rest_tickets WHERE visita_id = ? AND estado = 'pendiente'",
            [$visitaId]
        );
        if ($existing) return (int)$existing['id'];

        $visita = $this->queryOne(
            "SELECT v.*, r.id AS rest_id
             FROM rest_visitas v
             JOIN rest_restaurantes r ON r.id = v.restaurante_id
             WHERE v.id = ?",
            [$visitaId]
        );
        if (!$visita) throw new \RuntimeException('Visita no encontrada');

        $subtotal = (float) $this->queryOne(
            "SELECT COALESCE(SUM(subtotal),0) AS s FROM rest_pedidos WHERE visita_id = ? AND estado != 'cancelado'",
            [$visitaId]
        )['s'];

        $folio = $this->generarFolio((int)$visita['rest_id']);
        $total = $subtotal + $propina;

        // Obtener el mesero del primer pedido de la visita (para asignar propina)
        $meseroRow = $this->queryOne(
            "SELECT mesero_id FROM rest_pedidos WHERE visita_id = ? AND mesero_id IS NOT NULL ORDER BY created_at ASC LIMIT 1",
            [$visitaId]
        );
        $meseroId = $meseroRow ? (int)$meseroRow['mesero_id'] : null;

        $this->execute(
            "INSERT INTO rest_tickets (restaurante_id, visita_id, mesa_id, folio, subtotal, propina, total, mesero_id)
             VALUES (?,?,?,?,?,?,?,?)",
            [$visita['rest_id'], $visitaId, $visita['mesa_id'], $folio, $subtotal, $propina, $total, $meseroId]
        );
        $ticketId = (int) $this->db->lastInsertId();

        $this->execute(
            "UPDATE rest_visitas SET estado = 'pagando' WHERE id = ?",
            [$visitaId]
        );
        return $ticketId;
    }

    public function getByVisita(int $visitaId): ?array
    {
        return $this->queryOne(
            "SELECT t.*, m.nombre AS mesa_nombre
             FROM rest_tickets t
             LEFT JOIN rest_mesas m ON m.id = t.mesa_id
             WHERE t.visita_id = ?
             ORDER BY t.created_at DESC LIMIT 1",
            [$visitaId]
        );
    }

    public function marcarPagado(int $ticketId, string $metodoPago, ?string $paypalOrderId = null): bool
    {
        return $this->execute(
            "UPDATE rest_tickets SET estado='pagado', metodo_pago=?, paypal_order_id=?, pagado_at=NOW() WHERE id=?",
            [$metodoPago, $paypalOrderId, $ticketId]
        );
    }

    public function actualizarPropina(int $ticketId, float $propina): bool
    {
        return $this->execute(
            "UPDATE rest_tickets SET propina = ?, total = subtotal + ? WHERE id = ?",
            [$propina, $propina, $ticketId]
        );
    }

    /**
     * Recalcula el subtotal y total del ticket sumando todos los pedidos activos
     * de la visita. Preserva la propina ya elegida en BD.
     * Necesario cuando el comensal agrega más pedidos después de generar el ticket.
     */
    public function recalcularSubtotal(int $ticketId, int $visitaId): void
    {
        $row = $this->queryOne(
            "SELECT COALESCE(SUM(subtotal),0) AS s FROM rest_pedidos WHERE visita_id = ? AND estado != 'cancelado'",
            [$visitaId]
        );
        $subtotal = (float)($row['s'] ?? 0);
        $this->execute(
            "UPDATE rest_tickets SET subtotal = ?, total = ? + propina WHERE id = ?",
            [$subtotal, $subtotal, $ticketId]
        );
    }

    public function listar(int $restauranteId, int $page = 1): array
    {
        $sql = "SELECT t.*, m.nombre AS mesa_nombre, v.qr_code
                FROM rest_tickets t
                LEFT JOIN rest_mesas m ON m.id = t.mesa_id
                LEFT JOIN rest_visitas v ON v.id = t.visita_id
                WHERE t.restaurante_id = ?
                ORDER BY t.created_at DESC";
        return $this->paginate($sql, [$restauranteId], $page);
    }
}
