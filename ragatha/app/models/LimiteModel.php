<?php
class LimiteModel extends BaseModel
{
    protected string $table = 'limites_compra';

    public function getByEmpresa(int $empresaId): array
    {
        return $this->query(
            "SELECT lc.*,
                    p.nombre AS producto_nombre, p.presentacion AS unidad
               FROM limites_compra lc
          LEFT JOIN productos p ON p.id = lc.producto_id
              WHERE lc.empresa_id = ?
           ORDER BY p.nombre ASC",
            [$empresaId]
        );
    }

    public function crear(array $data): int
    {
        return $this->insert([
            'empresa_id'  => $data['empresa_id'],
            'sucursal_id' => null,
            'producto_id' => $data['producto_id'] ?: null,
            'limite_kg'   => $data['limite_kg']   ?: null,
            'periodo'     => $data['periodo'],
            'activo'      => 1,
            'created_by'  => $data['created_by'],
        ]);
    }

    public function actualizar(int $id, array $data): void
    {
        $this->execute(
            "UPDATE limites_compra
                SET producto_id=?, limite_kg=?, periodo=?
              WHERE id=? AND empresa_id=?",
            [
                $data['producto_id'] ?: null,
                $data['limite_kg']   ?: null,
                $data['periodo'],
                $id,
                $data['empresa_id'],
            ]
        );
    }

    public function toggleActivo(int $id, int $empresaId): void
    {
        $this->execute(
            'UPDATE limites_compra SET activo = NOT activo WHERE id=? AND empresa_id=?',
            [$id, $empresaId]
        );
    }

    public function eliminar(int $id, int $empresaId): void
    {
        $this->execute(
            'DELETE FROM limites_compra WHERE id=? AND empresa_id=?',
            [$id, $empresaId]
        );
    }

    public function findConDetalle(int $id): ?array
    {
        return $this->queryOne(
            'SELECT * FROM limites_compra WHERE id=?',
            [$id]
        );
    }
}
