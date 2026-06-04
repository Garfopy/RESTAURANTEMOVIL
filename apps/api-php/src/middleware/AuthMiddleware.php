<?php

declare(strict_types=1);

namespace Amare\Api\Middleware;

use Amare\Api\Helpers\Response;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    public static function authenticate(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Token no proporcionado');
        }

        $token = substr($authHeader, 7);
        $secret = $_ENV['JWT_SECRET'];

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded->data ?? null;
        } catch (\Exception $e) {
            Response::unauthorized('Token inválido o expirado');
        }
    }

    public static function optional(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $secret = $_ENV['JWT_SECRET'];

        try {
            $decoded = JWT::decode($token, new Key($secret, 'HS256'));
            return $decoded->data ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function generateToken(array $data): string
    {
        $secret = $_ENV['JWT_SECRET'];
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