<?php
class RestMenuModel extends BaseModel
{
    protected string $table = 'rest_platillos';

    // ── Categorías ────────────────────────────────────────────────

    public function getCategorias(int $restauranteId, bool $soloActivas = false): array
    {
        $where = $soloActivas ? 'AND activo = 1' : '';
        return $this->query(
            "SELECT * FROM rest_categorias_menu WHERE restaurante_id = ? $where ORDER BY orden, nombre",
            [$restauranteId]
        );
    }

    public function findCategoria(int $id): ?array
    {
        return $this->queryOne("SELECT * FROM rest_categorias_menu WHERE id = ?", [$id]);
    }

    public function insertCategoria(array $data): int
    {
        $this->execute(
            "INSERT INTO rest_categorias_menu (restaurante_id, nombre, descripcion, imagen, orden) VALUES (?,?,?,?,?)",
            [$data['restaurante_id'], $data['nombre'], $data['descripcion'] ?? null, $data['imagen'] ?? null, $data['orden'] ?? 0]
        );
        return (int) $this->db->lastInsertId();
    }

    public function updateCategoria(int $id, array $data): bool
    {
        $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $vals = array_values($data);
        $vals[] = $id;
        return $this->execute("UPDATE rest_categorias_menu SET $sets WHERE id = ?", $vals);
    }

    // ── Platillos ─────────────────────────────────────────────────

    public function getByRestaurante(int $restauranteId, bool $soloActivos = false): array
    {
        $where = $soloActivos ? 'AND p.activo = 1' : '';
        return $this->query(
            "SELECT p.*,
                    c.nombre AS categoria_nombre,
                    CASE WHEN EXISTS(
                        SELECT 1 FROM rest_recetas r
                        JOIN rest_receta_ingredientes ri ON ri.receta_id = r.id AND ri.es_informativo = 0
                        WHERE r.platillo_id = p.id
                    ) THEN 1 ELSE 0 END AS tiene_receta
             FROM rest_platillos p
             LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
             WHERE p.restaurante_id = ? $where
             ORDER BY c.orden, c.nombre, p.nombre",
            [$restauranteId]
        );
    }

    public function getPlatillosDisponibles(int $restauranteId): array
    {
        return $this->query(
            "SELECT p.*, c.nombre AS categoria_nombre
             FROM rest_platillos p
             LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
             WHERE p.restaurante_id = ? AND p.disponible = 1 AND p.activo = 1
             ORDER BY c.orden, p.nombre",
            [$restauranteId]
        );
    }

    // ── Recetas ───────────────────────────────────────────────────

    public function getReceta(int $platilloId): ?array
    {
        return $this->queryOne(
            "SELECT * FROM rest_recetas WHERE platillo_id = ?",
            [$platilloId]
        );
    }

    public function getIngredientesReceta(int $recetaId): array
    {
        return $this->query(
            "SELECT ri.*, i.nombre AS ingrediente_nombre, i.unidad_principal, i.costo_unitario,
                    COALESCE(ri.precio_extra, 0) AS precio_extra,
                    ri.tipo_componente, ri.codigo_display
             FROM rest_receta_ingredientes ri
             JOIN rest_ingredientes i ON i.id = ri.ingrediente_id
             WHERE ri.receta_id = ?",
            [$recetaId]
        );
    }

    /** Returns [platillo_id => [ingredients]] for all platillos of a restaurant. */
    public function getIngredientesPorRestaurante(int $restauranteId): array
    {
        $rows = $this->query(
            "SELECT rec.platillo_id, ri.ingrediente_id, i.nombre AS ingrediente_nombre,
                    ri.cantidad, ri.unidad, ri.es_informativo,
                    COALESCE(ri.precio_extra, 0) AS precio_extra,
                    ri.tipo_componente
             FROM rest_recetas rec
             JOIN rest_receta_ingredientes ri ON ri.receta_id = rec.id
             JOIN rest_ingredientes i ON i.id = ri.ingrediente_id
             JOIN rest_platillos p ON p.id = rec.platillo_id
             WHERE p.restaurante_id = ? AND p.activo = 1",
            [$restauranteId]
        );
        $result = [];
        foreach ($rows as $row) {
            $result[$row['platillo_id']][] = $row;
        }
        return $result;
    }

    public function upsertReceta(int $platilloId, int $porciones, ?string $notas): int
    {
        $existing = $this->getReceta($platilloId);
        if ($existing) {
            $this->execute(
                "UPDATE rest_recetas SET porciones_base = ?, notas = ? WHERE platillo_id = ?",
                [$porciones, $notas, $platilloId]
            );
            return $existing['id'];
        }
        $this->execute(
            "INSERT INTO rest_recetas (platillo_id, porciones_base, notas) VALUES (?,?,?)",
            [$platilloId, $porciones, $notas]
        );
        return (int) $this->db->lastInsertId();
    }

    public function syncIngredientesReceta(int $recetaId, array $ingredientes): void
    {
        $this->execute("DELETE FROM rest_receta_ingredientes WHERE receta_id = ?", [$recetaId]);
        foreach ($ingredientes as $ing) {
            $this->execute(
                "INSERT INTO rest_receta_ingredientes (receta_id, ingrediente_id, cantidad, unidad, notas, es_informativo, precio_extra, tipo_componente, codigo_display) VALUES (?,?,?,?,?,?,?,?,?)",
                [$recetaId, $ing['ingrediente_id'], $ing['cantidad'], $ing['unidad'], $ing['notas'] ?? null, $ing['es_informativo'] ?? 0, (float)($ing['precio_extra'] ?? 0), $ing['tipo_componente'] ?? 'materia_prima', $ing['codigo_display'] ?? null]
            );
        }
    }

    public function getPlatilloConReceta(int $platilloId): ?array
    {
        $platillo = $this->queryOne("SELECT * FROM rest_platillos WHERE id = ?", [$platilloId]);
        if (!$platillo) return null;
        $receta = $this->getReceta($platilloId);
        $platillo['receta'] = $receta;
        $platillo['ingredientes'] = $receta ? $this->getIngredientesReceta($receta['id']) : [];
        return $platillo;
    }

    // ── Estadísticas de ventas ────────────────────────────────────

    /**
     * Platillos más vendidos del restaurante (último año, ignora ítems cancelados).
     * Devuelve nombre, precio actual, unidades vendidas y revenue.
     */
    public function getTopVendidos(int $restauranteId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return $this->query(
            "SELECT p.id, p.nombre, p.precio,
                    SUM(pi.cantidad)         AS unidades_vendidas,
                    SUM(pi.subtotal)         AS revenue
             FROM rest_pedido_items pi
             JOIN rest_pedidos ped ON ped.id = pi.pedido_id
             JOIN rest_platillos p ON p.id = pi.platillo_id
             WHERE ped.restaurante_id = ?
               AND pi.estado <> 'cancelado'
               AND ped.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
             GROUP BY p.id, p.nombre, p.precio
             ORDER BY unidades_vendidas DESC
             LIMIT $limit",
            [$restauranteId]
        );
    }

    /**
     * Platillos menos vendidos entre los que SÍ están activos en menú,
     * incluye los que no se han vendido nunca (LEFT JOIN).
     */
    public function getMenosVendidos(int $restauranteId, int $limit = 5): array
    {
        $limit = max(1, min(20, $limit));
        return $this->query(
            "SELECT p.id, p.nombre, p.precio,
                    COALESCE(SUM(CASE WHEN pi.estado <> 'cancelado' THEN pi.cantidad ELSE 0 END), 0) AS unidades_vendidas
             FROM rest_platillos p
             LEFT JOIN rest_pedido_items pi ON pi.platillo_id = p.id
             LEFT JOIN rest_pedidos ped ON ped.id = pi.pedido_id
                  AND ped.created_at >= DATE_SUB(NOW(), INTERVAL 365 DAY)
                  AND ped.restaurante_id = p.restaurante_id
             WHERE p.restaurante_id = ? AND p.activo = 1
             GROUP BY p.id, p.nombre, p.precio
             ORDER BY unidades_vendidas ASC, p.nombre ASC
             LIMIT $limit",
            [$restauranteId]
        );
    }
}
