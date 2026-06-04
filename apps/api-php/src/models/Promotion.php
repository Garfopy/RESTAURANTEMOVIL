<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Promotion
{
    public static function getAll(): array
    {
        $sql = "SELECT * FROM promotions 
                WHERE active = 1 
                AND (start_date IS NULL OR start_date <= NOW()) 
                AND (end_date IS NULL OR end_date >= NOW())
                ORDER BY created_at DESC";
        
        return Database::query($sql);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT * FROM promotions 
                WHERE id = :id AND active = 1 
                AND (start_date IS NULL OR start_date <= NOW()) 
                AND (end_date IS NULL OR end_date >= NOW())
                LIMIT 1";
        
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO promotions (name, description, discount_type, discount_value, code, min_order_amount, max_uses, start_date, end_date, active, created_at) 
                VALUES (:name, :description, :discount_type, :discount_value, :code, :min_order_amount, :max_uses, :start_date, :end_date, :active, NOW())";
        
        return Database::execute($sql, [
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':discount_type' => $data['discount_type'],
            ':discount_value' => $data['discount_value'],
            ':code' => $data['code'] ?? null,
            ':min_order_amount' => $data['min_order_amount'] ?? null,
            ':max_uses' => $data['max_uses'] ?? null,
            ':start_date' => $data['start_date'] ?? null,
            ':end_date' => $data['end_date'] ?? null,
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

        $sql = "UPDATE promotions SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE promotions SET active = 0 WHERE id = :id", [':id' => $id]) > 0;
    }

    public static function validateCode(string $code): ?array
    {
        $sql = "SELECT * FROM promotions 
                WHERE code = :code 
                AND active = 1 
                AND (start_date IS NULL OR start_date <= NOW()) 
                AND (end_date IS NULL OR end_date >= NOW())
                LIMIT 1";
        
        return Database::queryOne($sql, [':code' => $code]);
    }
}