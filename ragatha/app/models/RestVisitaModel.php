<?php
class RestVisitaModel extends BaseModel
{
    protected string $table = 'rest_visitas';

    public function crear(int $restauranteId, ?int $mesaId, ?int $comensalId = null): int
    {
        $qr = bin2hex(random_bytes(16));
        $this->execute(
            "INSERT INTO rest_visitas (restaurante_id, mesa_id, comensal_id, qr_code)
             VALUES (?,?,?,?)",
            [$restauranteId, $mesaId, $comensalId, $qr]
        );
        return (int) $this->db->lastInsertId();
    }

    public function getByQr(string $qrCode): ?array
    {
        return $this->queryOne(
            "SELECT v.*, m.nombre AS mesa_nombre, r.nombre AS restaurante_nombre, r.slug AS restaurante_slug,
                    c.nombre AS comensal_nombre, c.telefono AS comensal_telefono
             FROM rest_visitas v
             JOIN rest_restaurantes r ON r.id = v.restaurante_id
             LEFT JOIN rest_mesas m ON m.id = v.mesa_id
             LEFT JOIN rest_comensales c ON c.id = v.comensal_id
             WHERE v.qr_code LIKE CONCAT(?, '%')",
            [strtolower($qrCode)]
        );
    }

    public function verificarPago(int $visitaId): bool
    {
        $v = $this->queryOne(
            "SELECT estado FROM rest_visitas WHERE id = ?",
            [$visitaId]
        );
        return $v && $v['estado'] === 'pagada';
    }

    public function marcarPagada(int $visitaId): bool
    {
        return $this->execute(
            "UPDATE rest_visitas SET estado = 'pagada', pagada_at = NOW(), total = (
                SELECT COALESCE(SUM(total),0) FROM rest_tickets WHERE visita_id = ? AND estado = 'pagado'
             ) WHERE id = ?",
            [$visitaId, $visitaId]
        );
    }

    public function marcarSalida(int $visitaId): bool
    {
        return $this->execute(
            "UPDATE rest_visitas SET salida_at = NOW() WHERE id = ?",
            [$visitaId]
        );
    }

    public function getActivas(int $restauranteId): array
    {
        return $this->query(
            "SELECT v.*, m.nombre AS mesa_nombre, c.nombre AS comensal_nombre
             FROM rest_visitas v
             LEFT JOIN rest_mesas m ON m.id = v.mesa_id
             LEFT JOIN rest_comensales c ON c.id = v.comensal_id
             WHERE v.restaurante_id = ? AND v.estado IN ('activa','pagando')
             ORDER BY v.created_at DESC",
            [$restauranteId]
        );
    }

    public function actualizarTotales(int $visitaId): void
    {
        $this->execute(
            "UPDATE rest_visitas v SET
                subtotal = (SELECT COALESCE(SUM(subtotal),0) FROM rest_pedidos WHERE visita_id = ? AND estado != 'cancelado'),
                total    = (SELECT COALESCE(SUM(subtotal),0) FROM rest_pedidos WHERE visita_id = ? AND estado != 'cancelado')
             WHERE v.id = ?",
            [$visitaId, $visitaId, $visitaId]
        );
    }
}
