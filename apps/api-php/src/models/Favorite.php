<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Favorite
{
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT f.*, p.name as product_name, p.price, p.image, c.name as category_name
                FROM favorites f
                LEFT JOIN products p ON f.product_id = p.id
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE f.user_id = :user_id AND p.active = 1
                ORDER BY f.created_at DESC";
        
        return Database::query($sql, [':user_id' => $userId]);
    }

    public static function add(int $userId, int $productId): bool
    {
        $sql = "INSERT IGNORE INTO favorites (user_id, product_id, created_at) 
                VALUES (:user_id, :product_id, NOW())";
        
        return Database::rowCount($sql, [
            ':user_id' => $userId,
            ':product_id' => $productId
        ]) > 0;
    }

    public static function remove(int $userId, int $productId): bool
    {
        $sql = "DELETE FROM favorites WHERE user_id = :user_id AND product_id = :product_id";
        
        return Database::rowCount($sql, [
            ':user_id' => $userId,
            ':product_id' => $productId
        ]) > 0;
    }

    public static function isFavorite(int $userId, int $productId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM favorites WHERE user_id = :user_id AND product_id = :product_id";
        $result = Database::queryOne($sql, [
            ':user_id' => $userId,
            ':product_id' => $productId
        ]);
        
        return ($result['count'] ?? 0) > 0;
    }
}