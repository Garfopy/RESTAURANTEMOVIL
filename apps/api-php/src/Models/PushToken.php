<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class PushToken
{
    public static function upsert(int $userId, string $token, ?string $platform = null, ?string $deviceId = null): array
    {
        $token = trim($token);
        if ($userId <= 0 || $token === '') {
            throw new \InvalidArgumentException('Token push invalido.');
        }

        $platform = self::nullableText($platform);
        $deviceId = self::nullableText($deviceId);

        $pdo = Database::getInstance();
        $recentRegistration = self::findRecentMatchingRegistration($pdo, $userId, $token, $platform, $deviceId);
        if ($recentRegistration !== null) {
            $recentRegistration['_unchanged'] = true;
            return $recentRegistration;
        }

        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }

        try {
            $tokenId = self::findTokenOrDeviceId($pdo, $token, $deviceId);
            if ($tokenId === null) {
                $tokenId = self::findLegacyInstallationId($pdo, $userId, $platform);
            }

            if ($tokenId !== null) {
                self::releaseDeviceIdFromOtherRegistrations($tokenId, $deviceId);
                self::updateRegistration($tokenId, $userId, $token, $platform, $deviceId);
            } else {
                self::insertRegistration($userId, $token, $platform, $deviceId);
                $tokenId = self::findTokenOrDeviceId($pdo, $token, $deviceId);
            }

            self::disableConflictingRegistrations($tokenId, $token, $deviceId);

            if ($tokenId === null) {
                throw new \RuntimeException('No se pudo identificar el registro push actualizado.');
            }

            $statement = $pdo->prepare(
                'SELECT id, usuario_id, fcm_token, platform, device_id, enabled, last_seen_at
                   FROM mobile_push_tokens
                  WHERE id = :id
                    AND usuario_id = :user_id
                    AND fcm_token = :token
                    AND enabled = 1
                  LIMIT 1'
            );
            $statement->execute([
                ':id' => $tokenId,
                ':user_id' => $userId,
                ':token' => $token,
            ]);
            $registration = $statement->fetch();
            if (!$registration) {
                throw new \RuntimeException('El registro push no quedo activo despues del upsert.');
            }

            if ($ownsTransaction) {
                $pdo->commit();
            }

            return $registration;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
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

    private static function findRecentMatchingRegistration(
        \PDO $pdo,
        int $userId,
        string $token,
        ?string $platform,
        ?string $deviceId
    ): ?array {
        $where = [
            'usuario_id = :user_id',
            'fcm_token = :token',
            'enabled = 1',
            'COALESCE(last_seen_at, updated_at, created_at) >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)',
        ];
        $params = [':user_id' => $userId, ':token' => $token];

        if ($platform === null) {
            $where[] = 'platform IS NULL';
        } else {
            $where[] = 'platform = :platform';
            $params[':platform'] = $platform;
        }

        if ($deviceId === null) {
            $where[] = 'device_id IS NULL';
        } else {
            $where[] = 'device_id = :device_id';
            $params[':device_id'] = $deviceId;
        }

        $statement = $pdo->prepare(
            'SELECT id, usuario_id, fcm_token, platform, device_id, enabled, last_seen_at
               FROM mobile_push_tokens
              WHERE ' . implode("\n                AND ", $where) . '
              LIMIT 1'
        );
        $statement->execute($params);
        $registration = $statement->fetch();

        return $registration ?: null;
    }

    private static function findTokenOrDeviceId(\PDO $pdo, string $token, ?string $deviceId): ?int
    {
        $params = [':token_match' => $token, ':token_order' => $token];
        $deviceCondition = '';
        if ($deviceId !== null) {
            $deviceCondition = ' OR device_id = :device_id';
            $params[':device_id'] = $deviceId;
        }

        $statement = $pdo->prepare(
            'SELECT id
               FROM mobile_push_tokens
              WHERE fcm_token = :token_match' . $deviceCondition . '
              ORDER BY CASE WHEN fcm_token = :token_order THEN 0 ELSE 1 END,
                       enabled DESC,
                       COALESCE(last_seen_at, updated_at, created_at) DESC,
                       id DESC
              LIMIT 1
              FOR UPDATE'
        );
        $statement->execute($params);
        $row = $statement->fetch();

        return isset($row['id']) ? (int)$row['id'] : null;
    }

    private static function findLegacyInstallationId(\PDO $pdo, int $userId, ?string $platform): ?int
    {
        if ($platform === null) {
            return null;
        }

        $statement = $pdo->prepare(
            "SELECT id
               FROM mobile_push_tokens
              WHERE usuario_id = :user_id
                AND platform = :platform
                AND (device_id IS NULL OR (device_id NOT LIKE 'android:%' AND device_id NOT LIKE 'ios:%'))
              ORDER BY COALESCE(last_seen_at, updated_at, created_at) DESC, id DESC
              LIMIT 2
              FOR UPDATE"
        );
        $statement->execute([':user_id' => $userId, ':platform' => $platform]);
        $rows = $statement->fetchAll();

        return count($rows) === 1 ? (int)$rows[0]['id'] : null;
    }

    private static function updateRegistration(
        int $id,
        int $userId,
        string $token,
        ?string $platform,
        ?string $deviceId
    ): void {
        Database::rowCount(
            'UPDATE mobile_push_tokens
                SET usuario_id = :user_id,
                    fcm_token = :token,
                    platform = :platform,
                    device_id = :device_id,
                    enabled = 1,
                    last_seen_at = NOW(),
                    updated_at = NOW()
              WHERE id = :id',
            [
                ':id' => $id,
                ':user_id' => $userId,
                ':token' => $token,
                ':platform' => $platform,
                ':device_id' => $deviceId,
            ]
        );
    }

    private static function insertRegistration(
        int $userId,
        string $token,
        ?string $platform,
        ?string $deviceId
    ): void {
        Database::rowCount(
            'INSERT INTO mobile_push_tokens
                (usuario_id, fcm_token, platform, device_id, enabled, last_seen_at)
             VALUES
                (:user_id, :token, :platform, :device_id, 1, NOW())
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                fcm_token = VALUES(fcm_token),
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

    private static function releaseDeviceIdFromOtherRegistrations(int $currentId, ?string $deviceId): void
    {
        if ($deviceId === null) {
            return;
        }

        Database::rowCount(
            'UPDATE mobile_push_tokens
                SET enabled = 0, device_id = NULL, updated_at = NOW()
              WHERE device_id = :device_id
                AND id <> :current_id',
            [':device_id' => $deviceId, ':current_id' => $currentId]
        );
    }

    private static function disableConflictingRegistrations(?int $currentId, string $token, ?string $deviceId): void
    {
        $where = ['fcm_token = :token'];
        $params = [':token' => $token];
        if ($deviceId !== null) {
            $where[] = 'device_id = :device_id';
            $params[':device_id'] = $deviceId;
        }

        $currentCondition = '';
        if ($currentId !== null) {
            $currentCondition = ' AND id <> :current_id';
            $params[':current_id'] = $currentId;
        }

        Database::rowCount(
            'UPDATE mobile_push_tokens
                SET enabled = 0, updated_at = NOW()
              WHERE (' . implode(' OR ', $where) . ')' . $currentCondition . '
                AND enabled = 1',
            $params
        );
    }
}
