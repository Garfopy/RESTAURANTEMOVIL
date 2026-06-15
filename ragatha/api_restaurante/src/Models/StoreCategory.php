<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class StoreCategory
{
    public static function getAll(): array
    {
        $sql = "SELECT id, nombre, descripcion, imagen, activo, created_at
                FROM store_categorias
                WHERE activo = 1
                ORDER BY nombre";

        return Database::query($sql);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT id, nombre, descripcion, imagen, activo, created_at
                FROM store_categorias
                WHERE id = :id AND activo = 1
                LIMIT 1";

        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO store_categorias (nombre, descripcion, imagen, created_at)
                VALUES (:nombre, :descripcion, :imagen, NOW())";

        return Database::execute($sql, [
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':imagen' => $data['imagen'] ?? null,
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $setClause = [];
        $params = [':id' => $id];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $setClause[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (empty($setClause)) {
            return false;
        }

        $sql = "UPDATE store_categorias SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount(
            "UPDATE store_categorias SET activo = 0 WHERE id = :id",
            [':id' => $id]
        ) > 0;
    }
}