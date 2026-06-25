<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Category
{
    public static function getAll(?int $branchId = null): array
    {
        // 🔥 Replicando la query de Node.js que incluye total_platillos
        $sql = "SELECT c.id, c.nombre, c.descripcion, c.imagen, c.orden, c.activo,
                       COUNT(p.id) as total_platillos
                FROM rest_categorias_menu c
                LEFT JOIN rest_platillos p ON p.categoria_id = c.id
                  AND p.disponible = 1 AND p.activo = 1
                WHERE c.activo = 1";
        $params = [];

        if ($branchId !== null) {
            $sql .= " AND c.restaurante_id = :restaurante_id";
            $params[':restaurante_id'] = $branchId;
        }

        $sql .= "
                GROUP BY c.id
                ORDER BY c.orden, c.nombre";
        return Database::query($sql, $params);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT c.id, c.nombre, c.descripcion, c.imagen, c.orden, c.activo,
                       COUNT(p.id) as total_platillos
                FROM rest_categorias_menu c
                LEFT JOIN rest_platillos p ON p.categoria_id = c.id
                  AND p.disponible = 1 AND p.activo = 1
                WHERE c.id = :id AND c.activo = 1
                GROUP BY c.id
                LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO rest_categorias_menu (nombre, descripcion, imagen, orden, activo, created_at) 
                VALUES (:nombre, :descripcion, :imagen, :orden, :activo, NOW())";
        
        return Database::execute($sql, [
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':imagen' => $data['imagen'] ?? null,
            ':orden' => $data['orden'] ?? 0,
            ':activo' => $data['activo'] ?? 1
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

        $sql = "UPDATE rest_categorias_menu SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE rest_categorias_menu SET activo = 0 WHERE id = :id", [':id' => $id]) > 0;
    }
}
