<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class FiscalData
{
    public static function getByUser(int $userId): ?array
    {
        $row = Database::queryOne(
            'SELECT * FROM mobile_datos_fiscales WHERE usuario_id = :user_id LIMIT 1',
            [':user_id' => $userId]
        );

        return $row ? self::normalize($row) : null;
    }

    public static function upsert(int $userId, array $data): array
    {
        $normalized = self::validate($data);
        Database::rowCount(
            'INSERT INTO mobile_datos_fiscales
                (usuario_id, rfc, nombre_fiscal, regimen_fiscal, codigo_postal, uso_cfdi, email)
             VALUES
                (:user_id, :rfc, :nombre_fiscal, :regimen_fiscal, :codigo_postal, :uso_cfdi, :email)
             ON DUPLICATE KEY UPDATE
                rfc = VALUES(rfc),
                nombre_fiscal = VALUES(nombre_fiscal),
                regimen_fiscal = VALUES(regimen_fiscal),
                codigo_postal = VALUES(codigo_postal),
                uso_cfdi = VALUES(uso_cfdi),
                email = VALUES(email),
                updated_at = NOW()',
            [
                ':user_id' => $userId,
                ':rfc' => $normalized['rfc'],
                ':nombre_fiscal' => $normalized['nombre_fiscal'],
                ':regimen_fiscal' => $normalized['regimen_fiscal'],
                ':codigo_postal' => $normalized['codigo_postal'],
                ':uso_cfdi' => $normalized['uso_cfdi'],
                ':email' => $normalized['email'],
            ]
        );

        return self::getByUser($userId) ?? $normalized;
    }

    public static function deleteByUser(int $userId): void
    {
        Database::rowCount(
            'DELETE FROM mobile_datos_fiscales WHERE usuario_id = :user_id',
            [':user_id' => $userId]
        );
    }

    public static function validate(array $data): array
    {
        $rfc = strtoupper(trim((string)($data['rfc'] ?? '')));
        $name = trim((string)($data['nombre_fiscal'] ?? $data['razon_social'] ?? $data['name'] ?? ''));
        $regime = strtoupper(trim((string)($data['regimen_fiscal'] ?? $data['tax_regime'] ?? '')));
        $postalCode = trim((string)($data['codigo_postal'] ?? $data['cp'] ?? $data['postal_code'] ?? ''));
        $cfdiUse = strtoupper(trim((string)($data['uso_cfdi'] ?? $data['cfdi_use'] ?? '')));
        $email = strtolower(trim((string)($data['email'] ?? '')));

        $errors = [];
        if (!preg_match('/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/u', $rfc)) {
            $errors['rfc'][] = 'Ingresa un RFC valido.';
        }
        if ($name === '' || strlen($name) > 255) {
            $errors['nombre_fiscal'][] = 'Ingresa la razon social o nombre fiscal.';
        }
        if ($regime === '' || strlen($regime) > 10) {
            $errors['regimen_fiscal'][] = 'Ingresa el regimen fiscal.';
        }
        if (!preg_match('/^[0-9]{5}$/', $postalCode)) {
            $errors['codigo_postal'][] = 'Ingresa un codigo postal fiscal de 5 digitos.';
        }
        if ($cfdiUse === '' || strlen($cfdiUse) > 10) {
            $errors['uso_cfdi'][] = 'Ingresa el uso CFDI.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'][] = 'Ingresa un email valido.';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(json_encode($errors, JSON_UNESCAPED_UNICODE));
        }

        return [
            'rfc' => $rfc,
            'nombre_fiscal' => $name,
            'regimen_fiscal' => $regime,
            'codigo_postal' => $postalCode,
            'uso_cfdi' => $cfdiUse,
            'email' => $email,
        ];
    }

    public static function normalize(array $row): array
    {
        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'usuario_id' => isset($row['usuario_id']) ? (int)$row['usuario_id'] : null,
            'rfc' => $row['rfc'] ?? '',
            'nombre_fiscal' => $row['nombre_fiscal'] ?? '',
            'regimen_fiscal' => $row['regimen_fiscal'] ?? '',
            'codigo_postal' => $row['codigo_postal'] ?? '',
            'uso_cfdi' => $row['uso_cfdi'] ?? '',
            'email' => $row['email'] ?? '',
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }
}
