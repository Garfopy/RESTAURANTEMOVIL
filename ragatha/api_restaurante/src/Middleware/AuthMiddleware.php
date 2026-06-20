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

            // Compatibilidad con tokens que guardan la información en "data"
            if (isset($decoded->data)) {

                $user = $decoded->data;

                // Compatibilidad: usar sub como id
                if (isset($user->sub) && !isset($user->id)) {
                    $user->id = (int)$user->sub;
                }

                return $user;
            }

            // Compatibilidad con tokens planos
            if (isset($decoded->sub) && !isset($decoded->id)) {
                $decoded->id = (int)$decoded->sub;
            }

            return $decoded;

        } catch (\Exception $e) {

            error_log('JWT ERROR: ' . get_class($e));
            error_log('JWT ERROR MSG: ' . $e->getMessage());

            Response::unauthorized('Token inválido o expirado');
        }
    }

    /**
     * Requiere permisos de administrador
     */
    public static function requireAdmin(): object
    {
        $user = self::authenticate();

        error_log('USER=' . json_encode($user));

        $rol = $user->rol ?? '';

        if ($rol !== 'admin' && $rol !== 'admin_restaurante') {
            Response::error(
                'Acceso denegado. Se requieren permisos de administrador.',
                403
            );
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
        $secret = self::getSecret();

        try {

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            if (isset($decoded->data)) {

                $user = $decoded->data;

                if (isset($user->sub) && !isset($user->id)) {
                    $user->id = (int)$user->sub;
                }

                return $user;
            }

            if (isset($decoded->sub) && !isset($decoded->id)) {
                $decoded->id = (int)$decoded->sub;
            }

            return $decoded;

        } catch (\Exception $e) {

            return null;
        }
    }

    public static function generateToken(array $data): string
    {
        $secret = self::getSecret();
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
