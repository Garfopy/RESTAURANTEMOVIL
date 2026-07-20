<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Favorite
{
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT p.id, p.nombre, p.precio, p.imagen, p.descripcion,
                       c.nombre AS categoria_nombre
                FROM mobile_favoritos f
                JOIN rest_platillos p ON p.id = f.platillo_id
                LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id
                WHERE f.usuario_id = :usuario_id AND p.activo = 1
                ORDER BY f.created_at DESC";
        
        return Database::query($sql, [':usuario_id' => $userId]);
    }

    public static function add(int $userId, int $productId): bool
    {
        $sql = "INSERT INTO mobile_favoritos (usuario_id, platillo_id, created_at)
                VALUES (:usuario_id, :platillo_id, NOW())";

        return Database::rowCount($sql, [
            ':usuario_id' => $userId,
            ':platillo_id' => $productId
        ]) > 0;
    }

    public static function remove(int $userId, int $productId): bool
    {
        $sql = "DELETE FROM mobile_favoritos WHERE usuario_id = :usuario_id AND platillo_id = :platillo_id";
        
        return Database::rowCount($sql, [
            ':usuario_id' => $userId,
            ':platillo_id' => $productId
        ]) > 0;
    }

    public static function isFavorite(int $userId, int $productId): bool
    {
        $sql = "SELECT COUNT(*) as count FROM mobile_favoritos WHERE usuario_id = :usuario_id AND platillo_id = :platillo_id";
        $result = Database::queryOne($sql, [
            ':usuario_id' => $userId,
            ':platillo_id' => $productId
        ]);
        
        return ($result['count'] ?? 0) > 0;
    }

    public static function toggle(int $userId, int $productId): array
{
    $exists = self::isFavorite($userId, $productId);

    if ($exists) {
        self::remove($userId, $productId);

        return [
            'ok' => true,
            'favorito' => false
        ];
    }

    self::add($userId, $productId);

    return [
        'ok' => true,
        'favorito' => true
    ];
}

    // Métodos relacionados a direcciones (si decides incluirlos aquí)
    public static function getAddressesByUser(int $userId): array
    {
        $sql = "SELECT id, usuario_id, alias, calle, numero, colonia, ciudad,
                       estado_provincia, cp, lat, lng, instrucciones,
                       es_principal, activo, created_at, updated_at
                FROM mobile_direcciones WHERE usuario_id = :usuario_id AND activo = 1
                ORDER BY es_principal DESC, created_at DESC";
        return Database::query($sql, [':usuario_id' => $userId]);
    }

}