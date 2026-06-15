<?php
class RestauranteModel extends BaseModel
{
    protected string $table = 'rest_restaurantes';

    public function getByComprador(int $compradorId): array
    {
        return $this->query(
            "SELECT * FROM rest_restaurantes WHERE comprador_id = ? ORDER BY nombre",
            [$compradorId]
        );
    }

    public function getByEmpresa(int $empresaId): array
    {
        return $this->query(
            "SELECT * FROM rest_restaurantes WHERE empresa_id = ? ORDER BY nombre",
            [$empresaId]
        );
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->queryOne(
            "SELECT * FROM rest_restaurantes WHERE slug = ? AND activo = 1",
            [$slug]
        );
    }

    public function verificarAcceso(int $restauranteId, int $compradorId): bool
    {
        $r = $this->queryOne(
            "SELECT id FROM rest_restaurantes WHERE id = ? AND comprador_id = ?",
            [$restauranteId, $compradorId]
        );
        return $r !== null;
    }

    public function getConStats(int $restauranteId): ?array
    {
        return $this->queryOne(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM rest_mesas WHERE restaurante_id = r.id AND activo = 1) AS total_mesas,
                    (SELECT COUNT(*) FROM rest_mesas WHERE restaurante_id = r.id AND estado = 'ocupada') AS mesas_ocupadas,
                    (SELECT COUNT(*) FROM rest_pedidos WHERE restaurante_id = r.id AND estado IN ('pendiente','en_preparacion')) AS pedidos_activos,
                    (SELECT COUNT(*) FROM rest_platillos WHERE restaurante_id = r.id AND activo = 1) AS total_platillos,
                    (SELECT COUNT(*) FROM rest_staff WHERE restaurante_id = r.id AND activo = 1) AS total_staff
             FROM rest_restaurantes r WHERE r.id = ?",
            [$restauranteId]
        );
    }

    public function slugExiste(string $slug, int $excludeId = 0): bool
    {
        $r = $this->queryOne(
            "SELECT id FROM rest_restaurantes WHERE slug = ? AND id != ?",
            [$slug, $excludeId]
        );
        return $r !== null;
    }

    public function generarSlugUnico(string $nombre): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $nombre)), '-'));
        $slug = $base;
        $i = 1;
        while ($this->slugExiste($slug)) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** Devuelve email y nombre de la empresa dueña del restaurante (para notificaciones). */
    public function getAdminEmail(int $restauranteId): ?array
    {
        return $this->queryOne(
            "SELECT e.email, e.razon_social AS nombre
             FROM rest_restaurantes r
             JOIN empresas e ON e.id = r.empresa_id
             WHERE r.id = ? LIMIT 1",
            [$restauranteId]
        );
    }
}
