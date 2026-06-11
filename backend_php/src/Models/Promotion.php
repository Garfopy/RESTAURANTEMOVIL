<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Promotion
{
    /**
     * Obtener promociones. Si se pasa usuario_id, filtra por ese usuario.
     * Si no, devuelve todas las activas (útil para futuro admin).
     */
    public static function getAll(?int $userId = null): array
    {
        $sql = "SELECT id, usuario_id, titulo, descripcion, imagen, deep_link, code, activo, created_at
                FROM mobile_promociones
                WHERE activo = 1";
        
        $params = [];

        if ($userId !== null) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
        }

        $sql .= " ORDER BY created_at DESC";

        return Database::query($sql, $params);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT id, usuario_id, titulo, descripcion, imagen, deep_link, code, activo, created_at
                FROM mobile_promociones
                WHERE id = :id AND activo = 1
                LIMIT 1";
        
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function getByUser(int $userId): array
    {
        return self::getAll($userId);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO mobile_promociones (usuario_id, titulo, descripcion, imagen, deep_link, code, activo, created_at)
                VALUES (:usuario_id, :titulo, :descripcion, :imagen, :deep_link, :code, :activo, NOW())";
        
        return Database::execute($sql, [
            ':usuario_id' => $data['usuario_id'],
            ':titulo' => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':imagen' => $data['imagen'] ?? null,
            ':deep_link' => $data['deep_link'] ?? null,
            ':code' => $data['code'] ?? null,
            ':activo' => $data['activo'] ?? 1,
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $setClause = [];
        $params = [':id' => $id];

        foreach (['titulo', 'descripcion', 'imagen', 'deep_link', 'code', 'activo'] as $key) {
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

    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM mobile_promociones WHERE id = :id";
        return Database::rowCount($sql, [':id' => $id]) > 0;
    }

    /**
     * Validar un código promocional para un usuario específico.
     */
    public static function validateCode(string $code, ?int $userId = null): ?array
    {
        $sql = "SELECT id, usuario_id, titulo, descripcion, imagen, deep_link, code, activo, created_at
                FROM mobile_promociones
                WHERE code = :code AND activo = 1";
        
        $params = [':code' => $code];

        if ($userId !== null) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
        }

        $sql .= " LIMIT 1";

        $result = Database::queryOne($sql, $params);
        return $result ?: null;
    }
}
