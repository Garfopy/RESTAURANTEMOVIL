<?php
class ProductoModel extends BaseModel
{
    protected string $table = 'productos';

    public function listadoConPrecio(array $filtros = [], int $page = 1): array
    {
        $where  = ['p.activo = 1'];
        $params = [];

        if (!empty($filtros['empresa_id'])) {
            $where[]  = 'p.empresa_id = ?';
            $params[] = (int)$filtros['empresa_id'];
        }
        if (!empty($filtros['categoria_id'])) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = $filtros['categoria_id'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = 'p.nombre LIKE ?';
            $params[] = '%' . $filtros['buscar'] . '%';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT p.*, c.nombre AS categoria_nombre,
                       inv.stock, inv.umbral_minimo
                  FROM productos p
                  JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN inventario inv ON inv.producto_id = p.id
                  $sqlWhere
              ORDER BY c.nombre, p.nombre";

        return $this->paginate($sql, $params, $page);
    }

    public function getPrecioParaCantidad(int $productoId, float $cantidad): float
    {
        $row = $this->queryOne(
            'SELECT precio FROM precios_escalonados
              WHERE producto_id = ?
                AND cantidad_min <= ?
                AND (cantidad_max IS NULL OR cantidad_max >= ?)
              ORDER BY cantidad_min DESC
              LIMIT 1',
            [$productoId, $cantidad, $cantidad]
        );
        if ($row) return (float)$row['precio'];

        // Fallback al precio_base
        $prod = $this->find($productoId);
        return $prod ? (float)$prod['precio_base'] : 0;
    }

    public function getEscalonados(int $productoId): array
    {
        return $this->query(
            'SELECT * FROM precios_escalonados WHERE producto_id = ? ORDER BY cantidad_min',
            [$productoId]
        );
    }

    public function conDetalle(int $id): ?array
    {
        $prod = $this->queryOne(
            'SELECT p.*, c.nombre AS categoria_nombre, inv.stock, inv.umbral_minimo
               FROM productos p
               JOIN categorias c ON c.id = p.categoria_id
          LEFT JOIN inventario inv ON inv.producto_id = p.id
              WHERE p.id = ?',
            [$id]
        );
        if (!$prod) return null;
        $prod['escalonados'] = $this->getEscalonados($id);
        return $prod;
    }

    // ── Panel Admin ───────────────────────────────────────────────────────────

    public function getCategorias(): array
    {
        return $this->query('SELECT * FROM categorias ORDER BY nombre');
    }

    public function listadoAdmin(array $filtros = [], int $page = 1): array
    {
        $where  = ['1=1'];
        $params = [];

        if (!empty($filtros['empresa_id'])) {
            $where[]  = 'p.empresa_id = ?';
            $params[] = (int)$filtros['empresa_id'];
        }
        if (!empty($filtros['categoria_id'])) {
            $where[]  = 'p.categoria_id = ?';
            $params[] = $filtros['categoria_id'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = 'p.nombre LIKE ?';
            $params[] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['stock_bajo'])) {
            $where[] = 'COALESCE(inv.stock, 0) <= COALESCE(inv.umbral_minimo, 0)';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT p.*, c.nombre AS categoria_nombre,
                       COALESCE(inv.stock, 0) AS stock_actual,
                       COALESCE(inv.umbral_minimo, 10) AS umbral_minimo
                  FROM productos p
                  JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN inventario inv ON inv.producto_id = p.id
                  $sqlWhere
              ORDER BY p.activo DESC, c.nombre, p.nombre";

        return $this->paginate($sql, $params, $page);
    }

    public function listadoInventario(array $filtros = [], int $page = 1): array
    {
        $where  = ['p.activo = 1'];
        $params = [];

        if (!empty($filtros['empresa_id'])) {
            $where[]  = 'p.empresa_id = ?';
            $params[] = (int)$filtros['empresa_id'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = 'p.nombre LIKE ?';
            $params[] = '%' . $filtros['buscar'] . '%';
        }
        if (!empty($filtros['stock_bajo'])) {
            $where[] = 'COALESCE(inv.stock, 0) <= COALESCE(inv.umbral_minimo, 0)';
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT p.id, p.nombre, p.presentacion, p.imagen, c.nombre AS categoria_nombre,
                       COALESCE(inv.stock, 0) AS stock_actual,
                       COALESCE(inv.umbral_minimo, 10) AS umbral_minimo,
                       inv.id AS inventario_id
                  FROM productos p
                  JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN inventario inv ON inv.producto_id = p.id
                  $sqlWhere
              ORDER BY (COALESCE(inv.stock, 0) <= COALESCE(inv.umbral_minimo, 0)) DESC, c.nombre, p.nombre";

        return $this->paginate($sql, $params, $page);
    }

    public function ajustarStock(int $productoId, string $tipo, float $cantidad): array
    {
        $existe = $this->queryOne('SELECT id, stock FROM inventario WHERE producto_id = ?', [$productoId]);

        $stockAntes = $existe ? (float)$existe['stock'] : 0.0;
        $stockNuevo = match ($tipo) {
            'entrada' => $stockAntes + $cantidad,
            'salida'  => max(0, $stockAntes - $cantidad),
            default   => $cantidad,
        };

        if (!$existe) {
            $this->execute(
                'INSERT INTO inventario (producto_id, stock, umbral_minimo) VALUES (?, ?, 10)',
                [$productoId, max(0, $stockNuevo)]
            );
        } else {
            $this->execute(
                'UPDATE inventario SET stock = ? WHERE producto_id = ?',
                [$stockNuevo, $productoId]
            );
        }

        return ['stock_antes' => $stockAntes, 'stock_despues' => $stockNuevo];
    }

    // ── Precios Especiales por Comprador ─────────────────────────────────────

    public function getPrecioEspecial(int $compradorId, int $productoId): ?float
    {
        $row = $this->queryOne(
            'SELECT precio FROM precios_especiales WHERE comprador_id = ? AND producto_id = ? AND activo = 1',
            [$compradorId, $productoId]
        );
        return $row ? (float)$row['precio'] : null;
    }

    public function getPrecioFinal(int $compradorId, int $productoId, float $cantidad): float
    {
        // Los precios especiales solo aplican para cantidades menores a 10 kg
        if ($cantidad < 10.0) {
            $especial = $this->getPrecioEspecial($compradorId, $productoId);
            if ($especial !== null) return $especial;
        }
        return $this->getPrecioParaCantidad($productoId, $cantidad);
    }

    /**
     * Carga solo los productos del carrito por sus IDs, sin paginación.
     * Incluye precio_base, imagen, categoria_nombre y tiene_escalonados.
     */
    public function getByIdsForCart(array $ids, int $empresaId): array
    {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge(array_map('intval', $ids), [$empresaId]);
        $sql = "SELECT p.*, c.nombre AS categoria_nombre,
                       inv.stock, inv.umbral_minimo,
                       (SELECT COUNT(*) FROM precios_escalonados pe WHERE pe.producto_id = p.id) > 0 AS tiene_escalonados
                  FROM productos p
                  JOIN categorias c ON c.id = p.categoria_id
             LEFT JOIN inventario inv ON inv.producto_id = p.id
                 WHERE p.id IN ($placeholders) AND p.empresa_id = ?
              ORDER BY c.nombre, p.nombre";
        return $this->query($sql, $params);
    }

    public function getPreciosEspecialesComprador(int $compradorId, int $empresaId): array
    {
        return $this->query(
            "SELECT pe.*, p.nombre AS producto_nombre, p.presentacion, p.precio_base,
                    c.nombre AS categoria_nombre
               FROM precios_especiales pe
               JOIN productos p ON p.id = pe.producto_id
               JOIN categorias c ON c.id = p.categoria_id
              WHERE pe.comprador_id = ? AND pe.empresa_id = ? AND p.activo = 1
           ORDER BY c.nombre, p.nombre",
            [$compradorId, $empresaId]
        );
    }

    public function guardarPrecioEspecial(int $empresaId, int $compradorId, int $productoId, float $precio, string $notas = ''): void
    {
        $existe = $this->queryOne(
            'SELECT id FROM precios_especiales WHERE comprador_id = ? AND producto_id = ?',
            [$compradorId, $productoId]
        );

        if ($existe) {
            $this->execute(
                'UPDATE precios_especiales SET precio = ?, notas = ?, activo = 1 WHERE comprador_id = ? AND producto_id = ?',
                [$precio, $notas, $compradorId, $productoId]
            );
        } else {
            $this->execute(
                'INSERT INTO precios_especiales (empresa_id, comprador_id, producto_id, precio, notas) VALUES (?, ?, ?, ?, ?)',
                [$empresaId, $compradorId, $productoId, $precio, $notas]
            );
        }
    }

    public function eliminarPrecioEspecial(int $compradorId, int $productoId): void
    {
        $this->execute(
            'DELETE FROM precios_especiales WHERE comprador_id = ? AND producto_id = ?',
            [$compradorId, $productoId]
        );
    }

    public function listadoParaPreciosEspeciales(int $empresaId, int $compradorId): array
    {
        return $this->query(
            "SELECT p.id, p.nombre, p.presentacion, p.precio_base, c.nombre AS categoria_nombre,
                    pe.precio AS precio_especial, pe.notas AS precio_notas, pe.id AS precio_especial_id
               FROM productos p
               JOIN categorias c ON c.id = p.categoria_id
          LEFT JOIN precios_especiales pe ON pe.producto_id = p.id AND pe.comprador_id = ? AND pe.activo = 1
              WHERE p.empresa_id = ? AND p.activo = 1
           ORDER BY c.nombre, p.nombre",
            [$compradorId, $empresaId]
        );
    }

    public function actualizarEscalonados(int $productoId, array $cantMin, array $cantMax, array $precios): void
    {
        $this->execute('DELETE FROM precios_escalonados WHERE producto_id = ?', [$productoId]);

        foreach ($cantMin as $i => $min) {
            if ($min === '' || !isset($precios[$i]) || $precios[$i] === '') continue;
            $max = ($cantMax[$i] ?? '') !== '' ? (float)$cantMax[$i] : null;
            $this->execute(
                'INSERT INTO precios_escalonados (producto_id, cantidad_min, cantidad_max, precio) VALUES (?, ?, ?, ?)',
                [$productoId, (float)$min, $max, (float)$precios[$i]]
            );
        }
    }

    public function inicializarInventario(int $productoId, int $stock, int $umbral): void
    {
        $existe = $this->queryOne('SELECT id FROM inventario WHERE producto_id = ?', [$productoId]);
        if (!$existe) {
            $this->execute(
                'INSERT INTO inventario (producto_id, stock, umbral_minimo) VALUES (?, ?, ?)',
                [$productoId, $stock, $umbral]
            );
        }
    }

    public function actualizarInventario(int $productoId, int $umbral): void
    {
        $this->execute(
            'UPDATE inventario SET umbral_minimo = ? WHERE producto_id = ?',
            [$umbral, $productoId]
        );
    }

    public function perteneceAEmpresa(int $productoId, int $empresaId): bool
    {
        return (bool)$this->queryOne(
            'SELECT id FROM productos WHERE id = ? AND empresa_id = ?',
            [$productoId, $empresaId]
        );
    }

    public function conStockDetalleEmpresa(int $id, int $empresaId): ?array
    {
        return $this->queryOne(
            'SELECT p.*, COALESCE(inv.stock, 0) AS stock_actual, COALESCE(inv.umbral_minimo, 10) AS umbral_minimo
               FROM productos p
          LEFT JOIN inventario inv ON inv.producto_id = p.id
              WHERE p.id = ? AND p.empresa_id = ?',
            [$id, $empresaId]
        );
    }

    public function ajustarInventarioDirecto(int $productoId, float $stock, float $umbral): void
    {
        $this->execute(
            'UPDATE inventario SET stock = ?, umbral_minimo = ? WHERE producto_id = ?',
            [$stock, $umbral, $productoId]
        );
    }
}
