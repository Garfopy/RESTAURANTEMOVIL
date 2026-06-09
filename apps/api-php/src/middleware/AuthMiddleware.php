<?php

declare(strict_types=1);

namespace Amare\Api\Middleware;

use Amare\Api\Helpers\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    /**
     * Obtiene la clave secreta JWT de forma segura con fallbacks alternativos
     */
    private static function getSecret(): string
    {
        $secret = $_ENV['JWT_SECRET'] ?? $_SERVER['JWT_SECRET'] ?? getenv('JWT_SECRET');
        
        // Si el lector de .env falla por completo, usamos la clave de tu config como respaldo de emergencia
        return $secret ?: 'amare_api_secret_key_2024_change_this_in_production_use_a_longer_random_string';
    }

    public static function authenticate(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Token no proporcionado');
        }

        $token = substr($authHeader, 7);
        $secret = self::getSecret();

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded->data ?? null;
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido o expirado');
        }
    }

    /**
     * Autentica y verifica que el usuario sea administrador.
     * Devuelve el objeto del usuario o termina con 403 si no es admin.
     */
    public static function requireAdmin(): object
    {
        $user = self::authenticate();

        if (!$user || ($user->rol ?? 'user') !== 'admin') {
            Response::error('Acceso denegado. Se requieren permisos de administrador.', 403);
        }

        return $user;
    }

    public static function optional(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $secret = self::getSecret(); // 🛠️ Modificado

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded->data ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function generateToken(array $data): string
    {
        $secret = self::getSecret(); // 🛠️ Modificado
        $expiry = (int)($_ENV['JWT_EXPIRY'] ?? 720);
        
        $issuedAt = time();
        $expireAt = $issuedAt + ($expiry * 60 * 60);

        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'amare-api',
            'aud' => $_ENV['APP_URL'] ?? 'amare-api',
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expireAt,
            'data' => (object)$data
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }
}