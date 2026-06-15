<?php
class RestMermasModel extends BaseModel
{
    protected string $table = 'rest_movimientos_inventario';

    private function rango(string $desde, string $hasta): array
    {
        return [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
    }

    public function kpis(int $restauranteId, string $desde, string $hasta): array
    {
        [$d, $h] = $this->rango($desde, $hasta);

        $tot = $this->queryOne(
            "SELECT COALESCE(SUM(mv.cantidad),0)                                AS cantidad_total,
                    COUNT(*)                                                    AS eventos,
                    COUNT(DISTINCT mv.ingrediente_id)                           AS ingredientes_afectados,
                    COALESCE(SUM(mv.cantidad * i.costo_unitario), 0)            AS valor_estimado
             FROM rest_movimientos_inventario mv
             JOIN rest_ingredientes i ON i.id = mv.ingrediente_id
             WHERE mv.restaurante_id = ? AND mv.tipo = 'merma'
               AND mv.created_at BETWEEN ? AND ?",
            [$restauranteId, $d, $h]
        ) ?? [];

        $consumo = $this->queryOne(
            "SELECT COALESCE(SUM(cantidad),0) AS total
             FROM rest_movimientos_inventario
             WHERE restaurante_id = ? AND tipo = 'salida'
               AND created_at BETWEEN ? AND ?",
            [$restauranteId, $d, $h]
        );
        $consumoTotal = (float)($consumo['total'] ?? 0);
        $mermaTotal   = (float)($tot['cantidad_total'] ?? 0);
        $pct = ($consumoTotal + $mermaTotal) > 0
             ? round(($mermaTotal / ($consumoTotal + $mermaTotal)) * 100, 2)
             : 0;

        $topIng = $this->queryOne(
            "SELECT i.nombre, SUM(mv.cantidad) AS total
             FROM rest_movimientos_inventario mv
             JOIN rest_ingredientes i ON i.id = mv.ingrediente_id
             WHERE mv.restaurante_id = ? AND mv.tipo = 'merma'
               AND mv.created_at BETWEEN ? AND ?
             GROUP BY mv.ingrediente_id
             ORDER BY total DESC LIMIT 1",
            [$restauranteId, $d, $h]
        );

        $topMot = $this->queryOne(
            "SELECT COALESCE(NULLIF(TRIM(motivo),''),'Sin especificar') AS motivo,
                    SUM(cantidad) AS n
             FROM rest_movimientos_inventario
             WHERE restaurante_id = ? AND tipo = 'merma'
               AND created_at BETWEEN ? AND ?
             GROUP BY motivo
             ORDER BY n DESC LIMIT 1",
            [$restauranteId, $d, $h]
        );

        // Días que realmente tienen registros de merma (no días de calendario)
        $diasConData = $this->queryOne(
            "SELECT COUNT(DISTINCT DATE(created_at)) AS dias
             FROM rest_movimientos_inventario
             WHERE restaurante_id = ? AND tipo = 'merma'
               AND created_at BETWEEN ? AND ?",
            [$restauranteId, $d, $h]
        );
        $diasReales = max(1, (int)($diasConData['dias'] ?? 1));
        $promedioDiario = round($mermaTotal / $diasReales, 3);

        return [
            'cantidad_total'        => $mermaTotal,
            'eventos'               => (int)($tot['eventos'] ?? 0),
            'ingredientes_afectados'=> (int)($tot['ingredientes_afectados'] ?? 0),
            'valor_estimado'        => (float)($tot['valor_estimado'] ?? 0),
            'pct_merma'             => $pct,
            'promedio_diario'       => $promedioDiario,
            'top_ingrediente'       => $topIng['nombre'] ?? '—',
            'top_motivo'            => $topMot['motivo'] ?? '—',
        ];
    }

    public function topIngredientes(int $restauranteId, string $desde, string $hasta, string $order = 'DESC', int $limit = 10): array
    {
        [$d, $h] = $this->rango($desde, $hasta);
        $order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';
        return $this->query(
            "SELECT i.id, i.nombre, i.unidad_principal,
                    SUM(mv.cantidad)                       AS total,
                    SUM(mv.cantidad * i.costo_unitario)    AS valor
             FROM rest_movimientos_inventario mv
             JOIN rest_ingredientes i ON i.id = mv.ingrediente_id
             WHERE mv.restaurante_id = ? AND mv.tipo = 'merma'
               AND mv.created_at BETWEEN ? AND ?
             GROUP BY mv.ingrediente_id
             ORDER BY total $order
             LIMIT $limit",
            [$restauranteId, $d, $h]
        );
    }

    public function porMotivo(int $restauranteId, string $desde, string $hasta): array
    {
        [$d, $h] = $this->rango($desde, $hasta);
        return $this->query(
            "SELECT COALESCE(NULLIF(TRIM(motivo),''),'Sin especificar') AS motivo,
                    SUM(cantidad) AS total,
                    COUNT(*)      AS eventos
             FROM rest_movimientos_inventario
             WHERE restaurante_id = ? AND tipo = 'merma'
               AND created_at BETWEEN ? AND ?
             GROUP BY motivo
             ORDER BY total DESC",
            [$restauranteId, $d, $h]
        );
    }

    public function tendenciaDiaria(int $restauranteId, string $desde, string $hasta): array
    {
        [$d, $h] = $this->rango($desde, $hasta);
        return $this->query(
            "SELECT DATE(created_at) AS dia,
                    SUM(cantidad)    AS total,
                    COUNT(*)         AS eventos
             FROM rest_movimientos_inventario
             WHERE restaurante_id = ? AND tipo = 'merma'
               AND created_at BETWEEN ? AND ?
             GROUP BY DATE(created_at)
             ORDER BY dia ASC",
            [$restauranteId, $d, $h]
        );
    }

    public function detalle(int $restauranteId, string $desde, string $hasta, int $limit = 200): array
    {
        [$d, $h] = $this->rango($desde, $hasta);
        return $this->query(
            "SELECT mv.id, mv.created_at, mv.cantidad, mv.motivo,
                    i.nombre AS ingrediente_nombre, i.unidad_principal,
                    i.costo_unitario,
                    u.nombre AS usuario_nombre
             FROM rest_movimientos_inventario mv
             JOIN rest_ingredientes i ON i.id = mv.ingrediente_id
             LEFT JOIN usuarios u ON u.id = mv.usuario_id
             WHERE mv.restaurante_id = ? AND mv.tipo = 'merma'
               AND mv.created_at BETWEEN ? AND ?
             ORDER BY mv.created_at DESC
             LIMIT $limit",
            [$restauranteId, $d, $h]
        );
    }

    public function topRotacion(int $restauranteId, string $desde, string $hasta, int $limit = 5): array
    {
        [$d, $h] = $this->rango($desde, $hasta);
        return $this->query(
            "SELECT i.nombre, SUM(mv.cantidad) AS total
             FROM rest_movimientos_inventario mv
             JOIN rest_ingredientes i ON i.id = mv.ingrediente_id
             WHERE mv.restaurante_id = ? AND mv.tipo = 'salida'
               AND mv.created_at BETWEEN ? AND ?
             GROUP BY mv.ingrediente_id
             ORDER BY total DESC
             LIMIT $limit",
            [$restauranteId, $d, $h]
        );
    }
}
