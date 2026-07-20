<?php

declare(strict_types=1);

namespace Amare\Api\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;

class AppleIdentityService
{
    private const APPLE_ISSUER = 'https://appleid.apple.com';
    private const APPLE_KEYS_URL = 'https://appleid.apple.com/auth/keys';

    /** @return array{sub: string, email: string|null, email_verified: bool} */
    public static function verifyIdentityToken(string $identityToken): array
    {
        $identityToken = trim($identityToken);
        if ($identityToken === '') {
            throw new \InvalidArgumentException('Apple no devolvio un token de identidad.');
        }

        $clientId = trim((string)($_ENV['APPLE_CLIENT_ID'] ?? 'com.amare.app'));
        $jwks = self::fetchAppleKeys();
        $decoded = (array)JWT::decode($identityToken, JWK::parseKeySet($jwks, 'RS256'));

        if (($decoded['iss'] ?? null) !== self::APPLE_ISSUER) {
            throw new \RuntimeException('El emisor del token de Apple no es valido.');
        }

        $audience = $decoded['aud'] ?? null;
        $validAudience = is_array($audience)
            ? in_array($clientId, $audience, true)
            : hash_equals($clientId, (string)$audience);
        if (!$validAudience) {
            throw new \RuntimeException('El token de Apple no corresponde a esta app.');
        }

        $subject = trim((string)($decoded['sub'] ?? ''));
        if ($subject === '') {
            throw new \RuntimeException('Apple no devolvio un identificador de usuario.');
        }

        $email = strtolower(trim((string)($decoded['email'] ?? '')));
        $verifiedValue = $decoded['email_verified'] ?? false;

        return [
            'sub' => $subject,
            'email' => $email !== '' ? $email : null,
            'email_verified' => $verifiedValue === true || $verifiedValue === 'true' || $verifiedValue === 1,
        ];
    }

    private static function fetchAppleKeys(): array
    {
        $context = stream_context_create([
            'http' => ['timeout' => 8, 'ignore_errors' => true],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $json = @file_get_contents(self::APPLE_KEYS_URL, false, $context);

        if ((!is_string($json) || trim($json) === '') && function_exists('curl_init')) {
            $curl = curl_init(self::APPLE_KEYS_URL);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $json = curl_exec($curl);
            curl_close($curl);
        }

        $keys = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($keys) || !isset($keys['keys']) || !is_array($keys['keys'])) {
            throw new \RuntimeException('No se pudieron consultar las claves publicas de Apple.');
        }

        return $keys;
    }
}
