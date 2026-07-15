<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class PushToken
{
    public static function upsert(int $userId, string $token, ?string $platform = null, ?string $deviceId = null): void
    {
        $token = trim($token);
        if ($userId <= 0 || $token === '') {
            throw new \InvalidArgumentException('Token push invalido.');
        }

        $platform = self::nullableText($platform);
        $deviceId = self::nullableText($deviceId);

        self::disableStaleTokens($userId, $token, $platform, $deviceId);

        Database::rowCount(
            'INSERT INTO mobile_push_tokens
                (usuario_id, fcm_token, platform, device_id, enabled, last_seen_at)
             VALUES
                (:user_id, :token, :platform, :device_id, 1, NOW())
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                platform = VALUES(platform),
                device_id = VALUES(device_id),
                enabled = 1,
                last_seen_at = NOW(),
                updated_at = NOW()',
            [
                ':user_id' => $userId,
                ':token' => $token,
                ':platform' => $platform,
                ':device_id' => $deviceId,
            ]
        );
    }

    public static function disable(int $userId, string $token): void
    {
        $token = trim($token);
        if ($userId <= 0 || $token === '') {
            return;
        }

        Database::rowCount(
            'UPDATE mobile_push_tokens
                SET enabled = 0, updated_at = NOW()
              WHERE usuario_id = :user_id AND fcm_token = :token',
            [
                ':user_id' => $userId,
                ':token' => $token,
            ]
        );
    }

    public static function getEnabledTokensForUser(int $userId): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => (string)($row['fcm_token'] ?? ''),
            self::getEnabledTokenRowsForUser($userId)
        ))));
    }

    public static function getEnabledTokenRowsForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        return Database::query(
            'SELECT fcm_token, platform, device_id, last_seen_at
               FROM mobile_push_tokens
              WHERE usuario_id = :user_id
                AND enabled = 1
              ORDER BY last_seen_at DESC, updated_at DESC',
            [':user_id' => $userId]
        );
    }

    public static function disableTokens(array $tokens): void
    {
        $tokens = array_values(array_unique(array_filter(array_map('strval', $tokens))));
        if (empty($tokens)) {
            return;
        }

        $placeholders = [];
        $params = [];
        foreach ($tokens as $index => $token) {
            $key = ':token' . $index;
            $placeholders[] = $key;
            $params[$key] = $token;
        }

        Database::rowCount(
            'UPDATE mobile_push_tokens
                SET enabled = 0, updated_at = NOW()
              WHERE fcm_token IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    private static function nullableText(?string $value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private static function disableStaleTokens(int $userId, string $token, ?string $platform, ?string $deviceId): void
    {
        $where = ['usuario_id = :user_id', 'fcm_token <> :token', 'enabled = 1'];
        $params = [
            ':user_id' => $userId,
            ':token' => $token,
        ];

        if ($platform !== null) {
            $where[] = 'platform = :platform';
            $params[':platform'] = $platform;
        } elseif ($deviceId !== null) {
            $where[] = 'device_id = :device_id';
            $params[':device_id'] = $deviceId;
        } else {
            $where[] = 'platform IS NULL';
            $where[] = 'device_id IS NULL';
        }

        Database::rowCount(
            'UPDATE mobile_push_tokens
                SET enabled = 0, updated_at = NOW()
              WHERE ' . implode(' AND ', $where),
            $params
        );
    }
}
