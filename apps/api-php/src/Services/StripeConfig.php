<?php

declare(strict_types=1);

namespace Amare\Api\Services;

use Amare\Api\Config\Database;

final class StripeConfig
{
    public const MINIMUM_PAYMENT_MXN_CENTS = 1000;

    public static function isBelowMinimumPaymentMxn(float $amount): bool
    {
        $amountCents = (int)round($amount * 100);
        return $amountCents > 0 && $amountCents < self::MINIMUM_PAYMENT_MXN_CENTS;
    }

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

        $liveMode = self::isLiveMode();

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

    public static function isLiveMode(): bool
    {
        $liveSetting = self::environmentValue('STRIPE_LIVE_MODE');
        if ($liveSetting !== '') {
            return filter_var($liveSetting, FILTER_VALIDATE_BOOLEAN);
        }

        return strtolower(self::environmentValue('APP_ENV')) === 'production';
    }

    private static function environmentValue(string $key): string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return is_string($value) ? trim($value) : '';
    }
}
