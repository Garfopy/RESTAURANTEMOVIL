<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Branch
{
    private const DEFAULT_LOGO = 'public/uploads/restaurantes/rest_logo_1_1781280185.png';

    public static function getAll(): array
    {
        $sql = self::baseSelect() . " WHERE r.activo = 1 ORDER BY r.nombre";
        return array_map([self::class, 'normalize'], Database::query($sql));
    }

    public static function findById(int $id): ?array
    {
        $sql = self::baseSelect() . " WHERE r.id = :id AND r.activo = 1 LIMIT 1";
        $branch = Database::queryOne($sql, [':id' => $id]);
        return $branch ? self::normalize($branch) : null;
    }

    public static function nearest(float $lat, float $lng, ?string $tipoPedido = null): array
    {
        $sql = self::baseSelect() . "
                WHERE r.activo = 1
                  AND r.lat IS NOT NULL
                  AND r.lng IS NOT NULL";

        $params = [];

        if ($tipoPedido !== null) {
            $sql .= " AND JSON_CONTAINS(COALESCE(rc.tipos_entrega, '[\"delivery\",\"pickup\"]'), JSON_QUOTE(:tipo_pedido))";
            $params[':tipo_pedido'] = $tipoPedido;
        }

        $sql .= " ORDER BY r.nombre";

        $branches = array_map([self::class, 'normalize'], Database::query($sql, $params));

        foreach ($branches as &$branch) {
            $branch['distancia_km'] = round(self::haversine(
                $lat,
                $lng,
                (float)$branch['lat'],
                (float)$branch['lng']
            ), 2);
        }
        unset($branch);

        usort($branches, fn($a, $b) => $a['distancia_km'] <=> $b['distancia_km']);

        return $branches;
    }

    private static function baseSelect(): string
    {
        return "SELECT r.id, r.nombre, r.slug, r.descripcion, r.direccion, r.lat, r.lng,
                       r.logo, r.imagen_banner, r.telefono, r.color_primario, r.color_secundario,
                       r.horario_apertura, r.horario_cierre, r.horarios_json, r.activo,
                       COALESCE(rc.tipos_entrega, '[\"delivery\",\"pickup\"]') AS tipos_entrega
                FROM rest_restaurantes r
                LEFT JOIN rest_configuracion rc ON rc.restaurante_id = r.id AND rc.activo = 1";
    }

    private static function normalize(array $branch): array
    {
        $branch['id'] = (int)$branch['id'];
        $branch['lat'] = $branch['lat'] !== null ? (float)$branch['lat'] : null;
        $branch['lng'] = $branch['lng'] !== null ? (float)$branch['lng'] : null;
        $branch['activo'] = (bool)$branch['activo'];
        $branch['tipos_entrega'] = json_decode((string)$branch['tipos_entrega'], true) ?: ['delivery', 'pickup'];
        $branch['logo'] = !empty($branch['logo']) ? $branch['logo'] : self::DEFAULT_LOGO;

        return $branch;
    }

    private static function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
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
