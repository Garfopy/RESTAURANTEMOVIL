<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Promotion
{
    public static function getAll(): array
    {
        $sql = "SELECT valor FROM global_settings WHERE clave = 'mobile_promotions' LIMIT 1";
        $settings = Database::queryOne($sql);
        
        if (!$settings || !$settings['valor']) {
            return [];
        }
        
        try {
            $promotions = json_decode($settings['valor'], true);
            return is_array($promotions) ? $promotions : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public static function findById(int $id): ?array
    {
        $promotions = self::getAll();
        
        foreach ($promotions as $promotion) {
            if (isset($promotion['id']) && (int)$promotion['id'] === $id) {
                return $promotion;
            }
        }
        
        return null;
    }

    public static function create(array $data): int
    {
        // Las promociones se gestionan como JSON en global_settings
        // Este método no está soportado en la API original
        return 0;
    }

    public static function update(int $id, array $data): bool
    {
        // Las promociones se gestionan como JSON en global_settings
        return false;
    }

    public static function delete(int $id): bool
    {
        // Las promociones se gestionan como JSON en global_settings
        return false;
    }

    public static function validateCode(string $code): ?array
    {
        $promotions = self::getAll();
        
        foreach ($promotions as $promotion) {
            if (isset($promotion['code']) && $promotion['code'] === $code) {
                return $promotion;
            }
        }
        
        return null;
    }
}