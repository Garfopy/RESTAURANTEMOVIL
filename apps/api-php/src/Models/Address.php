<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Address
{
    public static function findByUserId(int $userId): array
    {
        $sql = "SELECT id, usuario_id, alias, calle, numero, colonia, ciudad,
                       estado_provincia, cp, lat, lng, instrucciones,
                       es_principal, activo, created_at, updated_at
                FROM mobile_direcciones
                WHERE usuario_id = :usuario_id AND activo = 1
                ORDER BY es_principal DESC, created_at DESC";
        return Database::query($sql, [':usuario_id' => $userId]);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT id, usuario_id, alias, calle, numero, colonia, ciudad,
                       estado_provincia, cp, lat, lng, instrucciones,
                       es_principal, activo, created_at, updated_at
                FROM mobile_direcciones WHERE id = :id AND activo = 1 LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function create(int $userId, array $data): int
    {
        // Si se marca como principal, desmarcar otras
        if (!empty($data['es_principal'])) {
            self::unsetPrincipal($userId);
        }

        $sql = "INSERT INTO mobile_direcciones (usuario_id, alias, calle, numero, colonia, ciudad,
                estado_provincia, cp, lat, lng, instrucciones, es_principal, activo, created_at)
                VALUES (:usuario_id, :alias, :calle, :numero, :colonia, :ciudad,
                :estado_provincia, :cp, :lat, :lng, :instrucciones, :es_principal, 1, NOW())";

        return Database::execute($sql, [
            ':usuario_id' => $userId,
            ':alias' => $data['alias'] ?? 'Dirección',
            ':calle' => $data['calle'] ?? '',
            ':numero' => $data['numero'] ?? null,
            ':colonia' => $data['colonia'] ?? null,
            ':ciudad' => $data['ciudad'] ?? '',
            ':estado_provincia' => $data['estado_provincia'] ?? null,
            ':cp' => $data['cp'] ?? null,
            ':lat' => $data['lat'] ?? null,
            ':lng' => $data['lng'] ?? null,
            ':instrucciones' => $data['instrucciones'] ?? null,
            ':es_principal' => !empty($data['es_principal']) ? 1 : 0,
        ]);
    }

    public static function update(int $id, int $userId, array $data): bool
    {
        // Si se marca como principal, desmarcar otras
        if (!empty($data['es_principal'])) {
            self::unsetPrincipal($userId);
        }

        $setClause = [];
        $params = [':id' => $id];

        $allowedFields = ['alias', 'calle', 'numero', 'colonia', 'ciudad',
                          'estado_provincia', 'cp', 'lat', 'lng', 'instrucciones'];

        foreach ($data as $key => $value) {
            if (in_array($key, $allowedFields, true)) {
                $setClause[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }

        if (isset($data['es_principal'])) {
            $setClause[] = "es_principal = :es_principal";
            $params[':es_principal'] = !empty($data['es_principal']) ? 1 : 0;
        }

        if (empty($setClause)) {
            return false;
        }

        $setClause[] = "updated_at = NOW()";
        $sql = "UPDATE mobile_direcciones SET " . implode(', ', $setClause) . " WHERE id = :id AND usuario_id = :uid";
        $params[':uid'] = $userId;

        return Database::rowCount($sql, $params) > 0;
    }

    public static function delete(int $id, int $userId): bool
    {
        $sql = "UPDATE mobile_direcciones SET activo = 0, updated_at = NOW() WHERE id = :id AND usuario_id = :usuario_id";
        return Database::rowCount($sql, [':id' => $id, ':usuario_id' => $userId]) > 0;
    }

    public static function getPrincipal(int $userId): ?array
    {
        $sql = "SELECT id, usuario_id, alias, calle, numero, colonia, ciudad,
                       estado_provincia, cp, lat, lng, instrucciones,
                       es_principal, activo
                FROM mobile_direcciones
                WHERE usuario_id = :usuario_id AND activo = 1 AND es_principal = 1
                LIMIT 1";
        return Database::queryOne($sql, [':usuario_id' => $userId]);
    }

    private static function unsetPrincipal(int $userId): void
    {
        $sql = "UPDATE mobile_direcciones SET es_principal = 0 WHERE usuario_id = :usuario_id AND es_principal = 1";
        Database::rowCount($sql, [':usuario_id' => $userId]);
    }
}