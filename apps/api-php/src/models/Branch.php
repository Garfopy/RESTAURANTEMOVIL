<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Branch
{
    public static function getAll(): array
    {
        $sql = "SELECT id, nombre, slug, descripcion, lat, lng,
                       imagen_banner, telefono, horarios_json,
                       mesas_habilitadas, reservas_habilitadas, activo
                FROM rest_restaurantes WHERE activo = 1 ORDER BY nombre";
        return Database::query($sql);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT id, nombre, slug, descripcion, lat, lng,
                       imagen_banner, telefono, horarios_json,
                       mesas_habilitadas, reservas_habilitadas, activo
                FROM rest_restaurantes WHERE id = :id AND activo = 1 LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(array $data): int
    {
        $sql = "INSERT INTO rest_restaurantes (nombre, slug, descripcion, direccion, telefono, lat, lng, horario_apertura, horario_cierre, horarios_json, activo, created_at) 
                VALUES (:nombre, :slug, :descripcion, :direccion, :telefono, :lat, :lng, :horario_apertura, :horario_cierre, :horarios_json, :activo, NOW())";
        
        return Database::execute($sql, [
            ':nombre' => $data['nombre'],
            ':slug' => $data['slug'] ?? strtolower(str_replace(' ', '-', $data['nombre'])),
            ':descripcion' => $data['descripcion'] ?? null,
            ':direccion' => $data['direccion'] ?? null,
            ':telefono' => $data['telefono'] ?? null,
            ':lat' => $data['lat'] ?? null,
            ':lng' => $data['lng'] ?? null,
            ':horario_apertura' => $data['horario_apertura'] ?? null,
            ':horario_cierre' => $data['horario_cierre'] ?? null,
            ':horarios_json' => $data['horarios_json'] ?? null,
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

        $sql = "UPDATE rest_restaurantes SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id): bool
    {
        return Database::rowCount("UPDATE rest_restaurantes SET activo = 0 WHERE id = :id", [':id' => $id]) > 0;
    }
}