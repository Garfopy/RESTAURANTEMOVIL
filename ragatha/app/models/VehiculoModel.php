<?php
class VehiculoModel extends BaseModel
{
    protected string $table = 'vehiculos';

    public function getByEmpresa(int $empresaId): array
    {
        return $this->query(
            "SELECT v.*,
                    u.nombre AS repartidor_nombre, u.apellido_paterno AS repartidor_apellido,
                    u.id AS repartidor_id
               FROM vehiculos v
          LEFT JOIN repartidor_vehiculo rv ON rv.vehiculo_id = v.id AND rv.activo = 1
          LEFT JOIN usuarios u ON u.id = rv.repartidor_id
              WHERE v.empresa_id = ?
           ORDER BY v.activo DESC, v.placa ASC",
            [$empresaId]
        );
    }

    public function crear(array $data): int
    {
        return $this->insert([
            'empresa_id' => $data['empresa_id'],
            'placa'      => strtoupper(trim($data['placa'])),
            'modelo'     => $data['modelo'] ?? null,
            'capacidad'  => $data['capacidad'] ?: null,
            'activo'     => 1,
        ]);
    }

    public function actualizar(int $id, array $data): void
    {
        $this->execute(
            'UPDATE vehiculos SET placa=?, modelo=?, capacidad=? WHERE id=?',
            [
                strtoupper(trim($data['placa'])),
                $data['modelo'] ?? null,
                $data['capacidad'] ?: null,
                $id,
            ]
        );
    }

    public function toggleActivo(int $id, int $empresaId): void
    {
        $this->execute(
            'UPDATE vehiculos SET activo = NOT activo WHERE id = ? AND empresa_id = ?',
            [$id, $empresaId]
        );
    }

    public function asignarRepartidor(int $vehiculoId, int $repartidorId): void
    {
        $db = Database::getInstance();
        // Desactivar asignaciones anteriores del vehículo
        $db->prepare('UPDATE repartidor_vehiculo SET activo=0 WHERE vehiculo_id=?')->execute([$vehiculoId]);
        // Insertar o reactivar
        $exists = $db->prepare(
            'SELECT 1 FROM repartidor_vehiculo WHERE vehiculo_id=? AND repartidor_id=?'
        );
        $exists->execute([$vehiculoId, $repartidorId]);
        if ($exists->fetch()) {
            $db->prepare(
                'UPDATE repartidor_vehiculo SET activo=1 WHERE vehiculo_id=? AND repartidor_id=?'
            )->execute([$vehiculoId, $repartidorId]);
        } else {
            $db->prepare(
                'INSERT INTO repartidor_vehiculo (vehiculo_id, repartidor_id, activo) VALUES (?,?,1)'
            )->execute([$vehiculoId, $repartidorId]);
        }
    }

    public function desasignarRepartidor(int $vehiculoId): void
    {
        $this->execute(
            'UPDATE repartidor_vehiculo SET activo=0 WHERE vehiculo_id=?',
            [$vehiculoId]
        );
    }

    public function pertenece(int $id, int $empresaId): bool
    {
        return (bool)$this->queryOne(
            'SELECT id FROM vehiculos WHERE id=? AND empresa_id=?',
            [$id, $empresaId]
        );
    }
}
