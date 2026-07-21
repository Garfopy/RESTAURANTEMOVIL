<?php

declare(strict_types=1);

namespace Amare\Api\Middleware;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use PDO;

final class RateLimiter
{
    public static function enforce(string $scope, string $identifier, int $maxAttempts, int $windowSeconds): void
    {
        $maxAttempts = max(1, min(500, $maxAttempts));
        $windowSeconds = max(60, min(86400, $windowSeconds));
        $identity = mb_strtolower(trim($identifier), 'UTF-8');
        $ip = self::clientIp();
        $keys = array_unique([
            hash('sha256', $scope . '|ip|' . $ip),
            hash('sha256', $scope . '|identity|' . $identity),
            hash('sha256', $scope . '|combined|' . $ip . '|' . $identity),
        ]);

        try {
            $pdo = Database::getInstance();
            $pdo->beginTransaction();
            $sql = "INSERT INTO api_rate_limits (bucket_key, scope, attempts, window_started_at, updated_at)
                    VALUES (:bucket_key, :scope, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                      attempts = IF(window_started_at < DATE_SUB(NOW(), INTERVAL {$windowSeconds} SECOND), 1, attempts + 1),
                      window_started_at = IF(window_started_at < DATE_SUB(NOW(), INTERVAL {$windowSeconds} SECOND), NOW(), window_started_at),
                      updated_at = NOW()";
            $write = $pdo->prepare($sql);
            $read = $pdo->prepare(
                'SELECT attempts, TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(window_started_at, INTERVAL ' . $windowSeconds . ' SECOND)) AS retry_after
                   FROM api_rate_limits WHERE bucket_key = :bucket_key LIMIT 1'
            );

            $blockedFor = 0;
            foreach ($keys as $key) {
                $write->execute([':bucket_key' => $key, ':scope' => substr($scope, 0, 80)]);
                $read->execute([':bucket_key' => $key]);
                $bucket = $read->fetch(PDO::FETCH_ASSOC) ?: [];
                if ((int)($bucket['attempts'] ?? 0) > $maxAttempts) {
                    $blockedFor = max($blockedFor, (int)($bucket['retry_after'] ?? $windowSeconds));
                }
            }

            $pdo->commit();

            if (random_int(1, 100) === 1) {
                $pdo->exec('DELETE FROM api_rate_limits WHERE updated_at < DATE_SUB(NOW(), INTERVAL 2 DAY)');
            }

            if ($blockedFor > 0) {
                header('Retry-After: ' . max(1, $blockedFor));
                Response::error('Demasiados intentos. Espera un momento antes de volver a intentar.', 429, 'RATE_LIMITED');
            }
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // Si la migracion no se ha aplicado, no derribamos el inicio de sesion.
            error_log('RateLimiter unavailable: ' . $exception->getMessage());
        }
    }

    private static function clientIp(): string
    {
        $cloudflareIp = trim((string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
        if ($cloudflareIp !== '' && filter_var($cloudflareIp, FILTER_VALIDATE_IP)) {
            return $cloudflareIp;
        }

        $remoteIp = trim((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        return filter_var($remoteIp, FILTER_VALIDATE_IP) ? $remoteIp : 'unknown';
    }
}
