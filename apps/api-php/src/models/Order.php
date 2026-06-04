<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Order
{
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT p.id, p.folio, p.estado, p.subtotal, p.total,
                       p.tipo_pedido, p.created_at,
                       r.nombre AS restaurante_nombre
                FROM rest_pedidos p
                JOIN rest_restaurantes r ON r.id = p.restaurante_id
                WHERE p.mobile_usuario_id = :usuario_id
                ORDER BY p.created_at DESC";
        
        $orders = Database::query($sql, [':usuario_id' => $userId]);
        
        foreach ($orders as &$order) {
            $order['items'] = self::getOrderItems($order['id']);
        }
        
        return $orders;
    }

    public static function findById(int $id, ?int $userId = null): ?array
    {
        $sql = "SELECT p.*, r.nombre AS restaurante_nombre
                FROM rest_pedidos p
                JOIN rest_restaurantes r ON r.id = p.restaurante_id
                WHERE p.id = :id";
        
        $params = [':id' => $id];
        
        if ($userId !== null) {
            $sql .= " AND p.mobile_usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
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
            
            $folio = 'AM-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
            
            $sql = "INSERT INTO rest_pedidos (restaurante_id, mobile_usuario_id, folio, estado, subtotal, total, tipo_pedido, notas, created_at) 
                    VALUES (:restaurante_id, :mobile_usuario_id, :folio, 'pendiente', :subtotal, :total, :tipo_pedido, :notas, NOW())";
            
            $orderId = Database::execute($sql, [
                ':restaurante_id' => $data['restaurante_id'],
                ':mobile_usuario_id' => $data['user_id'],
                ':folio' => $folio,
                ':subtotal' => $data['subtotal'],
                ':total' => $data['total'],
                ':tipo_pedido' => $data['order_type'],
                ':notas' => $data['notes'] ?? null
            ]);
            
            if (!$orderId) {
                $pdo->rollBack();
                return 0;
            }
            
            foreach ($data['items'] as $item) {
                $itemSql = "INSERT INTO rest_pedido_items (pedido_id, platillo_id, cantidad, precio_unit, notas, estado) 
                           VALUES (:pedido_id, :platillo_id, :cantidad, :precio_unit, :notas, 'pendiente')";
                
                Database::execute($itemSql, [
                    ':pedido_id' => $orderId,
                    ':platillo_id' => $item['product_id'],
                    ':cantidad' => $item['quantity'],
                    ':precio_unit' => $item['unit_price'],
                    ':notas' => $item['options'] ?? null
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
        $sql = "SELECT pi.id, pi.platillo_id, pl.nombre AS platillo_nombre,
                       pl.imagen AS platillo_imagen,
                       pi.cantidad, pi.precio_unit, pi.notas,
                       pi.estado,
                       (pi.cantidad * pi.precio_unit) AS subtotal
                FROM rest_pedido_items pi
                JOIN rest_platillos pl ON pl.id = pi.platillo_id
                WHERE pi.pedido_id = :pedido_id";
        
        return Database::query($sql, [':pedido_id' => $orderId]);
    }
}