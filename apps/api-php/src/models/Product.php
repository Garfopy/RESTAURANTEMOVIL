<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Product
{
    public static function getAll(?int $categoryId = null, ?int $branchId = null, ?string $q = null): array
    {
        $sql = "SELECT p.id, p.restaurante_id, p.categoria_id,
                       c.nombre as categoria_nombre,
                       p.nombre, p.descripcion,
                       p.precio, p.imagen, p.tiempo_preparacion_min,
                       p.disponible, p.activo
                FROM rest_platillos p
                LEFT JOIN rest_categorias_menu c ON p.categoria_id = c.id
                WHERE p.activo = 1";
        
        $params = [];
        
        if ($categoryId !== null) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoryId;
        }
        
        if ($branchId !== null) {
            $sql .= " AND (p.restaurante_id IS NULL OR p.restaurante_id = :restaurante_id)";
            $params[':restaurante_id'] = $branchId;
        }
        
        if ($q !== null && $q !== '') {
            $sql .= " AND (p.nombre LIKE :q_nombre OR p.descripcion LIKE :q_desc)";
            $params[':q_nombre'] = '%' . $q . '%';
            $params[':q_desc'] = '%' . $q . '%';
        }
        
        $sql .= " ORDER BY p.nombre";
        
        return Database::query($sql, $params);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT p.id, p.restaurante_id, p.categoria_id,
                       c.nombre as categoria_nombre,
                       p.nombre, p.descripcion,
                       p.precio, p.imagen, p.tiempo_preparacion_min,
                       p.disponible, p.activo
                FROM rest_platillos p
                LEFT JOIN rest_categorias_menu c ON p.categoria_id = c.id
                WHERE p.id = :id AND p.activo = 1
                LIMIT 1";
        
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO rest_platillos (nombre, descripcion, precio, categoria_id, restaurante_id, imagen, tiempo_preparacion_min, disponible, activo, created_at) 
                VALUES (:nombre, :descripcion, :precio, :categoria_id, :restaurante_id, :imagen, :tiempo_preparacion_min, :disponible, :activo, NOW())";
        
        return Database::execute($sql, [
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':precio' => $data['precio'],
            ':categoria_id' => $data['categoria_id'] ?? null,
            ':restaurante_id' => $data['restaurante_id'] ?? null,
            ':imagen' => $data['imagen'] ?? null,
            ':tiempo_preparacion_min' => $data['tiempo_preparacion_min'] ?? null,
            ':disponible' => $data['disponible'] ?? 1,
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

        $sql = "UPDATE rest_platillos SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE rest_platillos SET activo = 0 WHERE id = :id", [':id' => $id]) > 0;
    }
}