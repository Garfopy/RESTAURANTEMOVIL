<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class User
{
    public static function create(array $data): int
    {
        $sql = "INSERT INTO mobile_usuarios (nombre, email, password_hash, telefono, foto_url, google_id, created_at) 
                VALUES (:nombre, :email, :password_hash, :telefono, :foto_url, :google_id, NOW())";
        
        return Database::execute($sql, [
            ':nombre' => $data['nombre'],
            ':email' => $data['email'],
            ':password_hash' => $data['password_hash'] ?? null,
            ':telefono' => $data['telefono'] ?? null,
            ':foto_url' => $data['foto_url'] ?? null,
            ':google_id' => $data['google_id'] ?? null
        ]);
    }

    public static function findByEmail(string $email): ?array
    {
        $sql = "SELECT * FROM mobile_usuarios WHERE email = :email LIMIT 1";
        return Database::queryOne($sql, [':email' => $email]);
    }

    public static function findById(int $id): ?array
    {
        $sql = "SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at, updated_at 
                FROM mobile_usuarios WHERE id = :id LIMIT 1";
        return Database::queryOne($sql, [':id' => $id]);
    }

    public static function findByGoogleId(string $googleId): ?array
    {
        $sql = "SELECT * FROM mobile_usuarios WHERE google_id = :google_id LIMIT 1";
        return Database::queryOne($sql, [':google_id' => $googleId]);
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

        $setClause[] = "updated_at = NOW()";
        $sql = "UPDATE mobile_usuarios SET " . implode(', ', $setClause) . " WHERE id = :id";
        
        return Database::rowCount($sql, $params) > 0;
    }

    public static function updatePassword(int $id, string $password): bool
    {
        $sql = "UPDATE mobile_usuarios SET password_hash = :password_hash, updated_at = NOW() WHERE id = :id";
        return Database::rowCount($sql, [
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $id
        ]) > 0;
    }

    public static function updateGoogleId(int $id, string $googleId): bool
    {
        $sql = "UPDATE mobile_usuarios SET google_id = :google_id, updated_at = NOW() WHERE id = :id";
        return Database::rowCount($sql, [
            ':google_id' => $googleId,
            ':id' => $id
        ]) > 0;
    }

    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function existsByEmail(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM mobile_usuarios WHERE email = :email";
        $params = [':email' => $email];
        
        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $result = Database::queryOne($sql, $params);
        return ($result['count'] ?? 0) > 0;
    }
}