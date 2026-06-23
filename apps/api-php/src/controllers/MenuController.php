<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\BranchConfigEvents;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Category;
use Amare\Api\Models\DishModifier;
use Amare\Api\Models\Product;
use Amare\Api\Models\RestaurantConfig;

class MenuController
{
    public function categories(): void
    {
        self::noCache();
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $restaurantId = $branchId ? DishModifier::resolveRestaurantId($branchId) : null;
        if ($branchId && !$restaurantId) Response::notFound('Sucursal no encontrada.');
        $categories = Category::getAll($restaurantId);
        if ($branchId && empty($categories)) {
            $categories = Category::getAll(null);
        }
        Response::success(['categories' => $categories]);
    }

    public function products(): void
    {
        self::noCache();
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $restaurantId = $branchId ? DishModifier::resolveRestaurantId($branchId) : null;
        if ($branchId && !$restaurantId) Response::notFound('Sucursal no encontrada.');
        $q = isset($_GET['q']) ? trim((string)$_GET['q']) : null;
        $products = Product::getAll($categoryId, $restaurantId, $q);
        if ($branchId && empty($products)) {
            $products = Product::getAll($categoryId, null, $q);
        }
        foreach ($products as &$product) {
            [$exclusionsEnabled, $extrasEnabled] = self::modifierFlags((int)$product['restaurante_id']);
            $payload = DishModifier::payload((int)$product['restaurante_id'], (int)$product['id'], $exclusionsEnabled, $extrasEnabled);
            $product['modificadores'] = self::legacyGroups($payload['modificadores'], $exclusionsEnabled, $extrasEnabled);
            $product['selector'] = $payload['selector'];
        }
        unset($product);
        Response::success(['products' => $products]);
    }

    public function showProduct(int $id): void
    {
        self::noCache();
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $restaurantId = $branchId ? DishModifier::resolveRestaurantId($branchId) : null;
        if ($branchId && !$restaurantId) Response::notFound('Sucursal no encontrada.');
        $product = Product::findById($id, $restaurantId);
        if (!$product && $branchId) {
            $product = Product::findById($id, null);
        }
        if (!$product) Response::notFound('Producto no encontrado');

        [$exclusionsEnabled, $extrasEnabled] = self::modifierFlags((int)$product['restaurante_id']);
        $payload = DishModifier::payload((int)$product['restaurante_id'], $id, $exclusionsEnabled, $extrasEnabled);
        $product['modificadores'] = self::legacyGroups($payload['modificadores'], $exclusionsEnabled, $extrasEnabled);
        $product['selector'] = $payload['selector'];
        $product['tiene_receta'] = (bool)$payload['selector']['visible'];
        Response::success(['product' => $product]);
    }

    public function showModifiers(int $branchId, int $dishId): void
    {
        self::noCache();
        $restaurantId = self::requireDish($branchId, $dishId);
        [$exclusionsEnabled, $extrasEnabled] = self::modifierFlags($restaurantId);
        Response::success(DishModifier::payload($restaurantId, $dishId, $exclusionsEnabled, $extrasEnabled));
    }

    public function syncModifiers(int $branchId, int $dishId): void
    {
        AuthMiddleware::authenticate();
        $restaurantId = self::requireDish($branchId, $dishId);
        $input = ValidationMiddleware::getAllInput();
        if (isset($input['platillo_id']) && (int)$input['platillo_id'] !== $dishId) {
            Response::validationError(['platillo_id' => ['El platillo no coincide con la URL.']]);
        }

        $modifiers = self::extractModifiers($input);
        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $count = DishModifier::sync($pdo, $restaurantId, $dishId, $modifiers);
            if ($count > 0) RestaurantConfig::incrementVersion($restaurantId);
            $pdo->commit();

            $config = RestaurantConfig::getByRestaurant($restaurantId);
            BranchConfigEvents::publish($branchId, (int)($config['version'] ?? 0));
            [$exclusionsEnabled, $extrasEnabled] = self::modifierFlags($restaurantId);
            Response::success([
                'count' => $count,
            ] + DishModifier::payload($restaurantId, $dishId, $exclusionsEnabled, $extrasEnabled), 'Modificadores sincronizados');
        } catch (\InvalidArgumentException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::validationError(['modificadores' => [$exception->getMessage()]]);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('[MenuController::syncModifiers] ' . $exception->getMessage());
            error_log('[MenuController::syncModifiers TRACE] ' . $exception->getTraceAsString());
            $production = strtolower((string)($_ENV['APP_ENV'] ?? 'production')) === 'production';
            Response::error(
                $production ? 'No se pudieron sincronizar los modificadores.' : 'No se pudieron sincronizar los modificadores: ' . $exception->getMessage(),
                500
            );
        }
    }

    private static function requireDish(int $branchId, int $dishId): int
    {
        $restaurantId = DishModifier::resolveRestaurantId($branchId);
        if (!$restaurantId || !DishModifier::dishBelongsToRestaurant($dishId, $restaurantId)) {
            Response::notFound('El platillo no existe para esta sucursal.');
        }
        return $restaurantId;
    }

    private static function extractModifiers(array $input): array
    {
        if (array_is_list($input)) return $input;
        if (array_key_exists('modifiers', $input)) {
            if (!is_array($input['modifiers'])) Response::validationError(['modifiers' => ['Debe ser un arreglo.']]);
            return $input['modifiers'];
        }
        if (array_key_exists('modificadores', $input)) {
            if (!is_array($input['modificadores'])) Response::validationError(['modificadores' => ['Debe ser un arreglo.']]);
            return $input['modificadores'];
        }
        Response::validationError(['modificadores' => ['Envía modifiers, modificadores o un arreglo directo.']]);
    }

    private static function modifierFlags(int $restaurantId): array
    {
        $config = Database::queryOne(
            'SELECT exclusiones_habilitadas, extras_habilitados FROM rest_configuracion WHERE restaurante_id = :restaurant_id LIMIT 1',
            [':restaurant_id' => $restaurantId]
        );
        return [(bool)($config['exclusiones_habilitadas'] ?? true), (bool)($config['extras_habilitados'] ?? true)];
    }

    private static function legacyGroups(array $rows, bool $exclusionsEnabled, bool $extrasEnabled): array
    {
        $groups = [];
        foreach (['exclusion' => 'Incluidos', 'extra' => 'Extras'] as $type => $label) {
            if (($type === 'exclusion' && !$exclusionsEnabled) || ($type === 'extra' && !$extrasEnabled)) continue;
            $options = [];
            foreach ($rows as $row) {
                if ($row['tipo'] !== $type || !$row['visible']) continue;
                $options[] = [
                    'id' => $row['id'],
                    'modificador_id' => $row['id'],
                    'nombre' => $row['nombre'],
                    'precio_extra' => $type === 'exclusion' ? 0.0 : $row['precio_unitario'],
                    'activo' => true,
                    'tipo_modificador' => $type,
                    'ingrediente_id' => $row['ingrediente_id'],
                    'cantidad_unidad' => $row['cantidad_unidad'],
                    'unidad' => $row['unidad'],
                    'max_cantidad' => $row['max_cantidad'],
                    'incluida' => $type === 'exclusion',
                    'seleccionada_por_defecto' => $type === 'exclusion' && !$row['omitida_por_defecto'],
                    'omitida_por_defecto' => $row['omitida_por_defecto'],
                    'puede_omitirse' => $row['puede_omitirse'],
                    'cantidad_inicial' => 0,
                ];
            }
            if ($options) {
                $groups[] = [
                    'id' => $type === 'exclusion' ? -2 : -1,
                    'nombre' => $label,
                    'tipo' => 'checkbox',
                    'categoria' => $type,
                    'requerido' => false,
                    'min_selecciones' => 0,
                    'max_selecciones' => array_sum(array_column($options, 'max_cantidad')),
                    'opciones' => $options,
                ];
            }
        }
        return $groups;
    }

    private static function noCache(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
