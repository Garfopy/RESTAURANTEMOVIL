<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Promotion
{
    /**
     * Obtener promociones activas de un usuario específico (app móvil).
     * Filtra por activo = 1 y que no hayan expirado.
     */
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT id, usuario_id, titulo, descripcion, imagen, deep_link, code, activo, expires_at, created_at
                FROM mobile_promociones
                WHERE activo = 1
                  AND usuario_id = :usuario_id
                  AND (expires_at IS NULL OR expires_at > NOW())
                ORDER BY created_at DESC";

        return Database::query($sql, [':usuario_id' => $userId]);
    }

    /**
     * Obtener todas las promociones activas (sin filtro de usuario).
     * Útil para promos globales o vista admin pública.
     */
    public static function getAll(?int $userId = null): array
    {
        $sql = "SELECT id, usuario_id, titulo, descripcion, imagen, deep_link, code, activo, expires_at, created_at
                FROM mobile_promociones
                WHERE activo = 1
                  AND (expires_at IS NULL OR expires_at > NOW())";

        $params = [];

        if ($userId !== null) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
        }

        $sql .= " ORDER BY created_at DESC";

        return Database::query($sql, $params);
    }

    /**
     * Obtener TODAS las promociones para el panel admin (incluyendo inactivas / expiradas).
     * Incluye JOIN con el usuario para mostrar nombre y email.
     */
    public static function getAllForAdmin(int $limit = 50, int $offset = 0, ?int $usuarioId = null): array
    {
        $where = '1=1';
        $params = [];

        if ($usuarioId !== null) {
            $where .= ' AND p.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $sql = "SELECT
                    p.id,
                    p.usuario_id,
                    p.titulo,
                    p.descripcion,
                    p.imagen,
                    p.deep_link,
                    p.code,
                    p.activo,
                    p.expires_at,
                    p.created_at,
                    u.nombre  AS usuario_nombre,
                    u.email   AS usuario_email
                FROM mobile_promociones p
                LEFT JOIN mobile_usuarios u ON u.id = p.usuario_id
                WHERE {$where}
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        return Database::query($sql, $params);
    }

    /**
     * Contar total de promociones para paginación en admin.
     */
    public static function countForAdmin(?int $usuarioId = null): int
    {
        $where = '1=1';
        $params = [];

        if ($usuarioId !== null) {
            $where .= ' AND usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $sql = "SELECT COUNT(*) as total FROM mobile_promociones WHERE {$where}";
        $result = Database::queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Buscar una promoción por ID (incluye datos del usuario asignado).
     */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT
                    p.id,
                    p.usuario_id,
                    p.titulo,
                    p.descripcion,
                    p.imagen,
                    p.deep_link,
                    p.code,
                    p.activo,
                    p.expires_at,
                    p.created_at,
                    u.nombre  AS usuario_nombre,
                    u.email   AS usuario_email
                FROM mobile_promociones p
                LEFT JOIN mobile_usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id
                LIMIT 1";

        return Database::queryOne($sql, [':id' => $id]);
    }

    /**
     * Crear una nueva promoción (lo usa el admin para asignarla a un usuario).
     * Retorna el ID insertado.
     */
    public static function create(array $data): int
    {
        $sql = "INSERT INTO mobile_promociones
                    (usuario_id, titulo, descripcion, imagen, deep_link, code, activo, expires_at, created_at)
                VALUES
                    (:usuario_id, :titulo, :descripcion, :imagen, :deep_link, :code, :activo, :expires_at, NOW())";

        return Database::execute($sql, [
            ':usuario_id'  => $data['usuario_id'],
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':imagen'      => $data['imagen'] ?? null,
            ':deep_link'   => $data['deep_link'] ?? null,
            ':code'        => $data['code'] ?? null,
            ':activo'      => $data['activo'] ?? 1,
            ':expires_at'  => $data['expires_at'] ?? null,
        ]);
    }

    /**
     * Actualizar una promoción existente (admin).
     */
    public static function update(int $id, array $data): bool
    {
        $setClause = [];
        $params = [':id' => $id];

        $allowed = ['titulo', 'descripcion', 'imagen', 'deep_link', 'code', 'activo', 'expires_at', 'usuario_id'];

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $setClause[] = "{$key} = :{$key}";
                $params[":{$key}"] = $data[$key];
            }
        }

        if (empty($setClause)) {
            return false;
        }

        $sql = "UPDATE mobile_promociones SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    /**
     * Eliminar una promoción (hard delete – admin).
     */
    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM mobile_promociones WHERE id = :id";
        return Database::rowCount($sql, [':id' => $id]) > 0;
    }

    /**
     * Desactivar (soft-delete) una promoción.
     */
    public static function deactivate(int $id): bool
    {
        $sql = "UPDATE mobile_promociones SET activo = 0 WHERE id = :id";
        return Database::rowCount($sql, [':id' => $id]) > 0;
    }

    /**
     * Validar un código promocional para un usuario específico (app móvil).
     */
    public static function validateCode(string $code, ?int $userId = null): ?array
    {
        $sql = "SELECT id, usuario_id, titulo, descripcion, imagen, deep_link, code, activo, expires_at, created_at
                FROM mobile_promociones
                WHERE code = :code
                  AND activo = 1
                  AND (expires_at IS NULL OR expires_at > NOW())";

        $params = [':code' => $code];

        if ($userId !== null) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
        }

        $sql .= " LIMIT 1";

        $result = Database::queryOne($sql, $params);
        return $result ?: null;
    }

    /**
     * Verificar si un código ya existe (para evitar duplicados al crear admin).
     */
    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM mobile_promociones WHERE code = :code";
        $params = [':code' => $code];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $result = Database::queryOne($sql, $params);
        return (int)($result['cnt'] ?? 0) > 0;
    }
}
