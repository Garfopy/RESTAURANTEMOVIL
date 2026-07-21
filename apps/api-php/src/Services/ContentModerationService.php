<?php

declare(strict_types=1);

namespace Amare\Api\Services;

final class ContentModerationService
{
    private const DEFAULT_BLOCKED_PHRASES = [
        'contenido sexual explicito',
        'pornografia',
        'manda nudes',
        'send nudes',
        'servicios sexuales',
        'prostitucion',
        'vendo drogas',
        'venta de drogas',
        'te voy a matar',
        'amenaza de muerte',
        'violacion',
        'sexo con menores',
    ];

    public static function violation(?string $value): ?string
    {
        $normalized = self::normalize($value);
        if ($normalized === '') {
            return null;
        }

        $configured = array_filter(array_map(
            'trim',
            explode(',', (string)($_ENV['SOCIAL_BLOCKED_TERMS'] ?? ''))
        ));

        foreach (array_merge(self::DEFAULT_BLOCKED_PHRASES, $configured) as $phrase) {
            $needle = self::normalize((string)$phrase);
            if ($needle !== '' && str_contains($normalized, $needle)) {
                return (string)$phrase;
            }
        }

        return null;
    }

    private static function normalize(?string $value): string
    {
        $trimmed = trim((string)$value);
        $normalized = function_exists('mb_strtolower')
            ? mb_strtolower($trimmed, 'UTF-8')
            : strtolower($trimmed);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $normalized = $ascii !== false ? $ascii : $normalized;
        $normalized = preg_replace('/[^a-z0-9@._\s-]+/', ' ', $normalized) ?? '';

        return preg_replace('/\s+/', ' ', trim($normalized)) ?? '';
    }
}
