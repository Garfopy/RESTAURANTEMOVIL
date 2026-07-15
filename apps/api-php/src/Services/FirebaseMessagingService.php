<?php

declare(strict_types=1);

namespace Amare\Api\Services;

use Amare\Api\Models\PushToken;
use Firebase\JWT\JWT;

class FirebaseMessagingService
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private static ?string $accessToken = null;
    private static int $accessTokenExpiresAt = 0;

    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        $tokenRows = PushToken::getEnabledTokenRowsForUser($userId);
        if (empty($tokenRows)) {
            error_log('FirebaseMessagingService INFO: user_id=' . $userId . ' sin tokens push activos.');
            return;
        }

        foreach ($tokenRows as $tokenRow) {
            $token = (string)($tokenRow['fcm_token'] ?? '');
            if ($token === '') {
                continue;
            }

            try {
                $this->sendToToken($token, $title, $body, $data);
            } catch (\Throwable $exception) {
                $message = $exception->getMessage();
                $platform = (string)($tokenRow['platform'] ?? 'unknown');
                error_log(sprintf(
                    'FirebaseMessagingService ERROR: user_id=%d platform=%s token=%s message=%s',
                    $userId,
                    $platform !== '' ? $platform : 'unknown',
                    $this->tokenPreview($token),
                    $message
                ));
                if (str_contains($message, 'UNREGISTERED') || str_contains($message, 'INVALID_ARGUMENT')) {
                    PushToken::disableTokens([$token]);
                }
            }
        }
    }

    private function sendToToken(string $token, string $title, string $body, array $data): void
    {
        $projectId = $this->projectId();
        $url = 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($projectId) . '/messages:send';

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $this->stringData($data),
                'android' => [
                    'priority' => 'HIGH',
                    'notification' => [
                        'channel_id' => 'promotions',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'alert',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            'sound' => 'default',
                        ],
                    ],
                ],
            ],
        ];

        $this->postJson($url, $payload, [
            'Authorization: Bearer ' . $this->accessToken(),
            'Content-Type: application/json; charset=UTF-8',
        ]);
    }

    private function accessToken(): string
    {
        if (self::$accessToken !== null && self::$accessTokenExpiresAt > time() + 60) {
            return self::$accessToken;
        }

        $credentials = $this->credentials();
        $now = time();
        $jwtPayload = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $assertion = JWT::encode($jwtPayload, (string)$credentials['private_key'], 'RS256');
        $response = $this->postForm(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $assertion,
        ]);

        $token = (string)($response['access_token'] ?? '');
        if ($token === '') {
            throw new \RuntimeException('Firebase no regreso access_token.');
        }

        self::$accessToken = $token;
        self::$accessTokenExpiresAt = $now + (int)($response['expires_in'] ?? 3600);

        return $token;
    }

    private function credentials(): array
    {
        $json = trim((string)($_ENV['FIREBASE_SERVICE_ACCOUNT_JSON'] ?? $_SERVER['FIREBASE_SERVICE_ACCOUNT_JSON'] ?? getenv('FIREBASE_SERVICE_ACCOUNT_JSON') ?: ''));

        if ($json === '') {
            $path = trim((string)($_ENV['GOOGLE_APPLICATION_CREDENTIALS'] ?? $_SERVER['GOOGLE_APPLICATION_CREDENTIALS'] ?? getenv('GOOGLE_APPLICATION_CREDENTIALS') ?: ''));
            if ($path === '') {
                throw new \RuntimeException('Configura GOOGLE_APPLICATION_CREDENTIALS o FIREBASE_SERVICE_ACCOUNT_JSON.');
            }
            if (!is_file($path) || !is_readable($path)) {
                throw new \RuntimeException('No se puede leer el archivo de credenciales Firebase.');
            }
            $json = (string)file_get_contents($path);
        }

        $credentials = json_decode($json, true);
        if (!is_array($credentials) || empty($credentials['client_email']) || empty($credentials['private_key'])) {
            throw new \RuntimeException('Credenciales Firebase invalidas.');
        }

        return $credentials;
    }

    private function projectId(): string
    {
        $projectId = trim((string)($_ENV['FIREBASE_PROJECT_ID'] ?? $_SERVER['FIREBASE_PROJECT_ID'] ?? getenv('FIREBASE_PROJECT_ID') ?: ''));
        if ($projectId === '') {
            $credentials = $this->credentials();
            $projectId = trim((string)($credentials['project_id'] ?? ''));
        }
        if ($projectId === '') {
            throw new \RuntimeException('Configura FIREBASE_PROJECT_ID.');
        }

        return $projectId;
    }

    private function stringData(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            if ($value === null) {
                continue;
            }
            $result[(string)$key] = is_scalar($value) ? (string)$value : (string)json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return $result;
    }

    private function tokenPreview(string $token): string
    {
        if (strlen($token) <= 16) {
            return $token;
        }

        return substr($token, 0, 12) . '...' . substr($token, -4);
    }

    private function postForm(string $url, array $data): array
    {
        return $this->request($url, http_build_query($data), [
            'Content-Type: application/x-www-form-urlencoded',
        ]);
    }

    private function postJson(string $url, array $data, array $headers): array
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('No se pudo preparar la notificacion Firebase.');
        }

        return $this->request($url, $json, $headers);
    }

    private function request(string $url, string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('La extension cURL de PHP es requerida para Firebase.');
        }

        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => array_merge(['Accept: application/json'], $headers),
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($curl);
        if ($response === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('No se pudo conectar con Firebase: ' . $error);
        }

        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        $decoded = json_decode((string)$response, true);
        if ($status < 200 || $status >= 300) {
            $message = is_array($decoded) ? (string)($decoded['error']['message'] ?? '') : '';
            throw new \RuntimeException($message !== '' ? $message : 'Firebase rechazo la notificacion.');
        }

        return is_array($decoded) ? $decoded : [];
    }
}
