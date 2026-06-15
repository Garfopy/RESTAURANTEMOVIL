<?php
class SucursalModel extends BaseModel
{
    protected string $table = 'sucursales';

    public function getByComprador(int $compradorId): array
    {
        return $this->query(
            'SELECT * FROM sucursales WHERE comprador_id = ? AND activo = 1 ORDER BY nombre ASC',
            [$compradorId]
        );
    }

    public function getAllByComprador(int $compradorId): array
    {
        return $this->query(
            'SELECT * FROM sucursales WHERE comprador_id = ? ORDER BY activo DESC, nombre ASC',
            [$compradorId]
        );
    }

    public function getByEmpresa(int $empresaId): array
    {
        return $this->query(
            'SELECT s.*, u.nombre AS comprador_nombre, u.apellido_paterno AS comprador_apellido
             FROM sucursales s
             LEFT JOIN usuarios u ON u.id = s.comprador_id
             WHERE s.empresa_id = ?
             ORDER BY u.nombre ASC, s.nombre ASC',
            [$empresaId]
        );
    }

    public function contarPorComprador(int $compradorId): int
    {
        $row = $this->queryOne(
            'SELECT COUNT(*) AS total FROM sucursales WHERE comprador_id = ?',
            [$compradorId]
        );
        return (int)($row['total'] ?? 0);
    }

    public function crear(array $data): int
    {
        $this->execute(
            'INSERT INTO sucursales (empresa_id, comprador_id, nombre, direccion, lat, lng, responsable, telefono, activo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)',
            [
                $data['empresa_id'],
                $data['comprador_id'],
                $data['nombre'],
                $data['direccion'],
                $data['lat'] ?: null,
                $data['lng'] ?: null,
                $data['responsable'] ?? null,
                $data['telefono'] ?? null,
            ]
        );
        return (int)$this->db->lastInsertId();
    }

    public function actualizar(int $id, array $data): void
    {
        $this->execute(
            'UPDATE sucursales SET nombre=?, direccion=?, lat=?, lng=?, responsable=?, telefono=? WHERE id=?',
            [
                $data['nombre'],
                $data['direccion'],
                $data['lat'] ?: null,
                $data['lng'] ?: null,
                $data['responsable'] ?? null,
                $data['telefono'] ?? null,
                $id,
            ]
        );
    }

    public function toggleActivo(int $id): void
    {
        $this->execute('UPDATE sucursales SET activo = NOT activo WHERE id = ?', [$id]);
    }

    public function perteneceAComprador(int $id, int $compradorId): bool
    {
        $row = $this->queryOne(
            'SELECT id FROM sucursales WHERE id = ? AND comprador_id = ?',
            [$id, $compradorId]
        );
        return (bool)$row;
    }
}
