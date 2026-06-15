<?php
class RestMesaModel extends BaseModel
{
    protected string $table = 'rest_mesas';

    public function getByRestaurante(int $restauranteId, ?bool $activo = null): array
    {
        $where = '';
        if ($activo === true)  $where = 'AND m.activo = 1';
        if ($activo === false) $where = 'AND m.activo = 0';
        return $this->query(
            "SELECT m.*, z.nombre AS zona_nombre
             FROM rest_mesas m
             LEFT JOIN rest_zonas z ON z.id = m.zona_id
             WHERE m.restaurante_id = ? $where
             ORDER BY z.nombre, m.nombre",
            [$restauranteId]
        );
    }

    public function getByQr(string $qrCodigo): ?array
    {
        return $this->queryOne(
            "SELECT m.*, r.nombre AS restaurante_nombre, r.slug AS restaurante_slug
             FROM rest_mesas m
             JOIN rest_restaurantes r ON r.id = m.restaurante_id
             WHERE m.qr_codigo = ? AND m.activo = 1",
            [$qrCodigo]
        );
    }

    public function cambiarEstado(int $mesaId, string $estado): bool
    {
        return $this->execute(
            "UPDATE rest_mesas SET estado = ? WHERE id = ?",
            [$estado, $mesaId]
        );
    }

    public function actualizarPosicion(int $mesaId, int $x, int $y): bool
    {
        return $this->execute(
            "UPDATE rest_mesas SET posicion_x = ?, posicion_y = ? WHERE id = ?",
            [$x, $y, $mesaId]
        );
    }

    public function generarQr(int $restauranteId, int $mesaId): string
    {
        return 'MESA-' . $restauranteId . '-' . $mesaId . '-' . strtoupper(bin2hex(random_bytes(4)));
    }

    public function getZonas(int $restauranteId): array
    {
        return $this->query(
            "SELECT * FROM rest_zonas WHERE restaurante_id = ? AND activo = 1 ORDER BY nombre",
            [$restauranteId]
        );
    }

    public function crearZona(array $data): int
    {
        $this->execute(
            "INSERT INTO rest_zonas (restaurante_id, nombre, descripcion) VALUES (?,?,?)",
            [$data['restaurante_id'], $data['nombre'], $data['descripcion'] ?? null]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Devuelve [mesa_id => ['fecha'=>…, 'hora'=>…]] para reservaciones
     * confirmadas/pendientes de HOY, indexadas por mesa_id.
     */
    public function reservacionesActivasHoy(int $restauranteId): array
    {
        $rows = $this->query(
            "SELECT r.mesa_id, r.fecha, r.hora
             FROM rest_reservaciones r
             WHERE r.restaurante_id = ? AND r.estado IN ('confirmada','pendiente')
               AND r.fecha = CURDATE()
             ORDER BY r.hora ASC",
            [$restauranteId]
        );
        $map = [];
        foreach ($rows as $row) {
            if ($row['mesa_id'] && !isset($map[$row['mesa_id']])) {
                $map[$row['mesa_id']] = ['fecha' => $row['fecha'], 'hora' => $row['hora']];
            }
        }
        return $map;
    }
}
