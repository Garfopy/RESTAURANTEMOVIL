<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Category
{
    public static function getAll(): array
    {
        $sql = "SELECT * FROM categories WHERE active = 1 ORDER BY sort_order, name";
        return Database::query($sql);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM categories WHERE id = :id AND active = 1 LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO categories (name, description, image, sort_order, active, created_at) 
                VALUES (:name, :description, :image, :sort_order, :active, NOW())";
        
        return Database::execute($sql, [
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':image' => $data['image'] ?? null,
            ':sort_order' => $data['sort_order'] ?? 0,
            ':active' => $data['active'] ?? 1
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

        $sql = "UPDATE categories SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE categories SET active = 0 WHERE id = :id", [':id' => $id]) > 0;
    }
}