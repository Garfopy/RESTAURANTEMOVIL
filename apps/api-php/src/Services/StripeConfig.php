<?php

declare(strict_types=1);

namespace Amare\Api\Services;

use Amare\Api\Config\Database;

final class StripeConfig
{
    public static function secretKey(): string
    {
        $key = self::environmentValue('STRIPE_SECRET_KEY');

        if ($key === '') {
            try {
                $setting = Database::queryOne(
                    'SELECT valor FROM global_settings WHERE clave = :key LIMIT 1',
                    [':key' => 'stripe_secret_key']
                );
                $key = trim((string)($setting['valor'] ?? ''));
            } catch (\Throwable $exception) {
                $key = '';
            }
        }

        if (!preg_match('/^sk_(test|live)_/', $key)) {
            throw new \RuntimeException('STRIPE_SECRET_KEY no configurada o invalida');
        }

        $appEnvironment = strtolower(self::environmentValue('APP_ENV'));
        $liveSetting = self::environmentValue('STRIPE_LIVE_MODE');
        $liveMode = $liveSetting !== ''
            ? filter_var($liveSetting, FILTER_VALIDATE_BOOLEAN)
            : $appEnvironment === 'production';

        if ($liveMode && !str_starts_with($key, 'sk_live_')) {
            throw new \RuntimeException('Stripe esta en modo produccion pero la Secret Key no es live');
        }

        return $key;
    }

    public static function webhookSecret(): string
    {
        $secret = self::environmentValue('STRIPE_WEBHOOK_SECRET');
        if (!str_starts_with($secret, 'whsec_')) {
            throw new \RuntimeException('STRIPE_WEBHOOK_SECRET no configurada o invalida');
        }

        return $secret;
    }

    private static function environmentValue(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return is_string($value) ? trim($value) : '';
    }
}
