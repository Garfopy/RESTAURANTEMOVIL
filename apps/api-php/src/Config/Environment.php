<?php

declare(strict_types=1);

namespace Amare\Api\Config;

final class Environment
{
    public static function value(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        if ($value === false || $value === null) {
            return $default;
        }

        return trim((string)$value);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::value($key);
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function isProduction(): bool
    {
        return strtolower(self::value('APP_ENV', 'production') ?? 'production') === 'production';
    }
}
