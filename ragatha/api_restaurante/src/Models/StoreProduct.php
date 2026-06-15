<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class StoreProduct
{
    public static function getAll(?int $categoryId = null, ?string $q = null): array
    {
        $sql = "SELECT p.id, p.categoria_id, c.nombre as categoria_nombre,
                       p.nombre, p.descripcion, p.tipo_producto, p.presentacion,
                       p.precio, p.imagen,
                       p.stock, p.activo, p.created_at
                FROM store_productos p
                LEFT JOIN store_categorias c ON p.categoria_id = c.id
                WHERE p.activo = 1 AND c.activo = 1";

        $params = [];

        if ($categoryId !== null) {
            $sql .= " AND p.categoria_id = :categoria_id";
            $params[':categoria_id'] = $categoryId;
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
        $sql = "SELECT p.id, p.categoria_id, c.nombre as categoria_nombre,
                       p.nombre, p.descripcion, p.tipo_producto, p.presentacion,
                       p.precio, p.imagen,
                       p.stock, p.activo, p.created_at
                FROM store_productos p
                LEFT JOIN store_categorias c ON p.categoria_id = c.id
                WHERE p.id = :id AND p.activo = 1
                LIMIT 1";

        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO store_productos (categoria_id, nombre, descripcion, precio, imagen, stock, created_at)
                VALUES (:categoria_id, :nombre, :descripcion, :precio, :imagen, :stock, NOW())";

        return Database::execute($sql, [
            ':categoria_id' => $data['categoria_id'],
            ':nombre' => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':precio' => $data['precio'],
            ':imagen' => $data['imagen'] ?? null,
            ':stock' => $data['stock'] ?? 0,
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

        $sql = "UPDATE store_productos SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount(
            "UPDATE store_productos SET activo = 0 WHERE id = :id",
            [':id' => $id]
        ) > 0;
    }
}