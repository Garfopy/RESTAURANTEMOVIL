<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Branch
{
    public static function getAll(): array
    {
        $sql = "SELECT * FROM branches WHERE active = 1 ORDER BY name";
        return Database::query($sql);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM branches WHERE id = :id AND active = 1 LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO branches (name, address, phone, email, latitude, longitude, opening_hours, closing_hours, active, created_at) 
                VALUES (:name, :address, :phone, :email, :latitude, :longitude, :opening_hours, :closing_hours, :active, NOW())";
        
        return Database::execute($sql, [
            ':name' => $data['name'],
            ':address' => $data['address'] ?? null,
            ':phone' => $data['phone'] ?? null,
            ':email' => $data['email'] ?? null,
            ':latitude' => $data['latitude'] ?? null,
            ':longitude' => $data['longitude'] ?? null,
            ':opening_hours' => $data['opening_hours'] ?? null,
            ':closing_hours' => $data['closing_hours'] ?? null,
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

        $sql = "UPDATE branches SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE branches SET active = 0 WHERE id = :id", [':id' => $id]) > 0;
    }
}