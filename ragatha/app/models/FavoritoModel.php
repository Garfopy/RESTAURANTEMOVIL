<?php
/**
 * FavoritoModel — Productos favoritos del comprador.
 */
class FavoritoModel extends BaseModel
{
    protected string $table = 'favoritos_comprador';

    /** Marca un producto como favorito (idempotente). */
    public function agregar(int $usuarioId, int $productoId): bool
    {
        return $this->execute(
            "INSERT IGNORE INTO `favoritos_comprador` (usuario_id, producto_id) VALUES (?, ?)",
            [$usuarioId, $productoId]
        );
    }

    /** Elimina un producto de favoritos. */
    public function quitar(int $usuarioId, int $productoId): bool
    {
        return $this->execute(
            "DELETE FROM `favoritos_comprador` WHERE usuario_id = ? AND producto_id = ?",
            [$usuarioId, $productoId]
        );
    }

    /** Devuelve true si el producto está en favoritos del usuario. */
    public function esFavorito(int $usuarioId, int $productoId): bool
    {
        $row = $this->queryOne(
            "SELECT id FROM `favoritos_comprador` WHERE usuario_id = ? AND producto_id = ? LIMIT 1",
            [$usuarioId, $productoId]
        );
        return $row !== null;
    }

    /** IDs de productos favoritos del usuario (para marcar en catálogo). */
    public function idsFavoritos(int $usuarioId): array
    {
        $rows = $this->query(
            "SELECT producto_id FROM `favoritos_comprador` WHERE usuario_id = ?",
            [$usuarioId]
        );
        return array_map(fn($r) => (int)$r['producto_id'], $rows);
    }

    /** Lista productos favoritos completos del usuario, restringidos a una empresa. */
    public function listarPorComprador(int $usuarioId, int $empresaId): array
    {
        $sql = "
            SELECT p.*, c.nombre AS categoria_nombre, f.created_at AS favorito_desde
              FROM `favoritos_comprador` f
              JOIN `productos` p ON p.id = f.producto_id
         LEFT JOIN `categorias` c ON c.id = p.categoria_id
             WHERE f.usuario_id = ?
               AND p.activo = 1
               AND (p.empresa_id = ? OR p.empresa_id IS NULL)
          ORDER BY f.created_at DESC
        ";
        return $this->query($sql, [$usuarioId, $empresaId]);
    }

    public function contarPorUsuario(int $usuarioId): int
    {
        return $this->count('usuario_id = ?', [$usuarioId]);
    }
}
