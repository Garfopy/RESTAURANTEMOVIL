<?php
class EmpresaModel extends BaseModel
{
    protected string $table = 'empresas';

    public function listado(array $filtros = [], int $page = 1): array
    {
        $where  = ['1=1'];
        $params = [];

        if (isset($filtros['activo'])) {
            $where[]  = 'e.activo = ?';
            $params[] = (int)$filtros['activo'];
        }
        if (!empty($filtros['buscar'])) {
            $where[]  = '(e.razon_social LIKE ? OR e.rfc LIKE ? OR e.email LIKE ?)';
            $t = '%' . $filtros['buscar'] . '%';
            $params = array_merge($params, [$t, $t, $t]);
        }

        $sqlWhere = 'WHERE ' . implode(' AND ', $where);
        $sql = "SELECT e.*,
                       COUNT(DISTINCT u.id)  AS total_usuarios,
                       COUNT(DISTINCT sc.id) AS total_sucursales,
                       sus.estado            AS sus_estado,
                       pl.slug               AS plan_slug,
                       pl.nombre             AS plan_nombre
                  FROM empresas e
             LEFT JOIN usuarios u    ON u.empresa_id  = e.id AND u.activo = 1
             LEFT JOIN sucursales sc ON sc.empresa_id = e.id AND sc.activo = 1
             LEFT JOIN suscripciones sus ON sus.empresa_id = e.id
             LEFT JOIN planes_saas pl    ON pl.id = sus.plan_id
                  $sqlWhere
              GROUP BY e.id
              ORDER BY e.created_at DESC";

        return $this->paginate($sql, $params, $page);
    }

    public function conEstadisticas(int $id): ?array
    {
        return $this->queryOne(
            'SELECT e.*,
                    COUNT(DISTINCT p.id) AS total_pedidos,
                    COALESCE(SUM(p.total),0) AS venta_total
               FROM empresas e
          LEFT JOIN pedidos p ON p.empresa_id = e.id AND p.estado != "cancelado"
              WHERE e.id = ?
           GROUP BY e.id',
            [$id]
        );
    }

    public function listadoSimple(): array
    {
        return $this->query(
            'SELECT id, razon_social FROM empresas WHERE activo = 1 ORDER BY razon_social'
        );
    }

    public function existeRFCValor(string $rfc, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM empresas WHERE rfc = ?';
        $params = [$rfc];
        if ($excluirId) { $sql .= ' AND id != ?'; $params[] = $excluirId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function existeEmailValor(string $email, ?int $excluirId = null): bool
    {
        $sql    = 'SELECT COUNT(*) FROM empresas WHERE email = ?';
        $params = [$email];
        if ($excluirId) { $sql .= ' AND id != ?'; $params[] = $excluirId; }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn() > 0;
    }
}
