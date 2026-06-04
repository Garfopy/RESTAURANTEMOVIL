<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Order
{
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT o.*, b.name as branch_name 
                FROM orders o
                LEFT JOIN branches b ON o.branch_id = b.id
                WHERE o.user_id = :user_id
                ORDER BY o.created_at DESC";
        
        $orders = Database::query($sql, [':user_id' => $userId]);
        
        foreach ($orders as &$order) {
            $order['items'] = self::getOrderItems($order['id']);
        }
        
        return $orders;
    }

    public static function findById(int $id, ?int $userId = null): ?array
    {
        $sql = "SELECT o.*, b.name as branch_name 
                FROM orders o
                LEFT JOIN branches b ON o.branch_id = b.id
                WHERE o.id = :id";
        
        $params = [':id' => $id];
        
        if ($userId !== null) {
            $sql .= " AND o.user_id = :user_id";
            $params[':user_id'] = $userId;
        }
        
        $order = Database::queryOne($sql, $params);
        
        if ($order) {
            $order['items'] = self::getOrderItems($order['id']);
        }
        
        return $order;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance();
        
        try {
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO orders (user_id, branch_id, order_type, status, subtotal, tax, total, notes, payment_status, created_at) 
                    VALUES (:user_id, :branch_id, :order_type, 'pending', :subtotal, :tax, :total, :notes, 'pending', NOW())";
            
            $orderId = Database::execute($sql, [
                ':user_id' => $data['user_id'],
                ':branch_id' => $data['branch_id'],
                ':order_type' => $data['order_type'],
                ':subtotal' => $data['subtotal'],
                ':tax' => $data['tax'] ?? 0,
                ':total' => $data['total'],
                ':notes' => $data['notes'] ?? null
            ]);
            
            if (!$orderId) {
                $pdo->rollBack();
                return 0;
            }
            
            foreach ($data['items'] as $item) {
                $itemSql = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, total_price, options) 
                           VALUES (:order_id, :product_id, :quantity, :unit_price, :total_price, :options)";
                
                Database::execute($itemSql, [
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':quantity' => $item['quantity'],
                    ':unit_price' => $item['unit_price'],
                    ':total_price' => $item['total_price'],
                    ':options' => $item['options'] ?? null
                ]);
            }
            
            $pdo->commit();
            return $orderId;
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return 0;
        }
    }

    private static function getOrderItems(int $orderId): array
    {
        $sql = "SELECT oi.*, p.name as product_name, p.price
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id";
        
        return Database::query($sql, [':order_id' => $orderId]);
    }
}