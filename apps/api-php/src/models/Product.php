<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Product
{
    public static function getAll(?int $categoryId = null, ?int $branchId = null): array
    {
        $sql = "SELECT p.*, c.name as category_name, GROUP_CONCAT(i.url) as images
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN product_images i ON p.id = i.product_id
                WHERE p.active = 1";
        
        $params = [];
        
        if ($categoryId !== null) {
            $sql .= " AND p.category_id = :category_id";
            $params[':category_id'] = $categoryId;
        }
        
        if ($branchId !== null) {
            $sql .= " AND (p.branch_id IS NULL OR p.branch_id = :branch_id)";
            $params[':branch_id'] = $branchId;
        }
        
        $sql .= " GROUP BY p.id ORDER BY p.name";
        
        $products = Database::query($sql, $params);
        
        foreach ($products as &$product) {
            $product['images'] = $product['images'] ? explode(',', $product['images']) : [];
        }
        
        return $products;
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT p.*, c.name as category_name, GROUP_CONCAT(i.url) as images
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN product_images i ON p.id = i.product_id
                WHERE p.id = :id AND p.active = 1
                GROUP BY p.id";
        
        $product = Database::queryOne($sql, [':id' => $id]);
        
        if ($product) {
            $product['images'] = $product['images'] ? explode(',', $product['images']) : [];
        }
        
        return $product;
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO products (name, description, price, category_id, branch_id, image, preparation_time, active, created_at) 
                VALUES (:name, :description, :price, :category_id, :branch_id, :image, :preparation_time, :active, NOW())";
        
        return Database::execute($sql, [
            ':name' => $data['name'],
            ':description' => $data['description'] ?? null,
            ':price' => $data['price'],
            ':category_id' => $data['category_id'] ?? null,
            ':branch_id' => $data['branch_id'] ?? null,
            ':image' => $data['image'] ?? null,
            ':preparation_time' => $data['preparation_time'] ?? null,
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

        $sql = "UPDATE products SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE products SET active = 0 WHERE id = :id", [':id' => $id]) > 0;
    }
}