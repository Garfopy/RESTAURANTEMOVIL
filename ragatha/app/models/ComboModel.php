<?php
class ComboModel extends BaseModel
{
    protected string $table = 'combos';

    public function listadoEmpresa(int $empresaId): array
    {
        return $this->query(
            "SELECT c.*, COUNT(ci.id) AS total_items, COUNT(cc.id) AS total_compradores
               FROM combos c
          LEFT JOIN combo_items ci ON ci.combo_id = c.id
          LEFT JOIN combo_compradores cc ON cc.combo_id = c.id
              WHERE c.empresa_id = ?
           GROUP BY c.id
           ORDER BY c.activo DESC, c.nombre",
            [$empresaId]
        );
    }

    public function getItems(int $comboId): array
    {
        return $this->query(
            "SELECT ci.*, p.nombre AS producto_nombre, p.presentacion, p.precio_base,
                    p.imagen, c.nombre AS categoria_nombre
               FROM combo_items ci
               JOIN productos p ON p.id = ci.producto_id
               JOIN categorias c ON c.id = p.categoria_id
              WHERE ci.combo_id = ?
           ORDER BY c.nombre, p.nombre",
            [$comboId]
        );
    }

    public function getCompradores(int $comboId): array
    {
        return $this->query(
            "SELECT cc.comprador_id, CONCAT(u.nombre, ' ', u.apellido_paterno) AS nombre_completo
               FROM combo_compradores cc
               JOIN usuarios u ON u.id = cc.comprador_id
              WHERE cc.combo_id = ?",
            [$comboId]
        );
    }

    public function getConDetalle(int $comboId): ?array
    {
        $combo = $this->find($comboId);
        if (!$combo) return null;
        $combo['items']      = $this->getItems($comboId);
        $combo['compradores'] = $this->getCompradores($comboId);
        return $combo;
    }

    public function guardarItems(int $comboId, array $productosIds, array $cantidades): void
    {
        $this->execute('DELETE FROM combo_items WHERE combo_id = ?', [$comboId]);
        foreach ($productosIds as $i => $pid) {
            $pid  = (int)$pid;
            $cant = (float)($cantidades[$i] ?? 0);
            if ($pid <= 0 || $cant <= 0) continue;
            $this->execute(
                'INSERT INTO combo_items (combo_id, producto_id, cantidad) VALUES (?, ?, ?)',
                [$comboId, $pid, $cant]
            );
        }
    }

    public function guardarCompradores(int $comboId, array $compradorIds): void
    {
        $this->execute('DELETE FROM combo_compradores WHERE combo_id = ?', [$comboId]);
        foreach (array_unique($compradorIds) as $cid) {
            $cid = (int)$cid;
            if ($cid <= 0) continue;
            $this->execute(
                'INSERT INTO combo_compradores (combo_id, comprador_id) VALUES (?, ?)',
                [$comboId, $cid]
            );
        }
    }

    public function getCombosParaComprador(int $compradorId, int $empresaId): array
    {
        return $this->query(
            "SELECT c.id, c.nombre, c.descripcion, c.precio, COUNT(ci.id) AS total_items
               FROM combos c
               JOIN combo_compradores cc ON cc.combo_id = c.id
          LEFT JOIN combo_items ci ON ci.combo_id = c.id
              WHERE cc.comprador_id = ? AND c.empresa_id = ? AND c.activo = 1
           GROUP BY c.id
           ORDER BY c.nombre",
            [$compradorId, $empresaId]
        );
    }

    public function perteneceAEmpresa(int $comboId, int $empresaId): bool
    {
        return (bool)$this->queryOne(
            'SELECT id FROM combos WHERE id = ? AND empresa_id = ?',
            [$comboId, $empresaId]
        );
    }

    public function estaAsignadoAComprador(int $comboId, int $compradorId): bool
    {
        return (bool)$this->queryOne(
            'SELECT id FROM combo_compradores WHERE combo_id = ? AND comprador_id = ?',
            [$comboId, $compradorId]
        );
    }
}
