<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Models\Category;
use Amare\Api\Models\Product;
use Amare\Api\Config\Database;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Helpers\BranchConfigEvents;
use Amare\Api\Models\RestaurantConfig;

class MenuController
{
    public function categories(): void
    {
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $categories = Category::getAll($branchId);
        Response::success(['categories' => $categories]);
    }

    public function products(): void
    {
        $categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : null;
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $q = isset($_GET['q']) ? trim($_GET['q']) : null;
        
        $products = Product::getAll($categoryId, $branchId, $q);
        Response::success(['products' => $products]);
    }

    public function showProduct(int $id): void
    {
        $branchId = isset($_GET['branch_id']) ? (int)$_GET['branch_id'] : null;
        $product = Product::findById($id, $branchId);

        if (!$product) {
            Response::notFound('Producto no encontrado');
        }

        // 🔥 Fetch modifiers/extras like the old Node.js API did
        $product['modificadores'] = self::getModificadores($id, (int)$product['restaurante_id']);
        $product['tiene_receta'] = !empty($product['modificadores']);

        Response::success(['product' => $product]);
    }

    /**
     * Obtiene los modificadores (extras) de un platillo.
     * Replica la lógica de Node.js: busca receta → ingredientes con precio_extra > 0
     */
    private static function getModificadores(int $platilloId, int $restaurantId): array
    {
        if (self::tableExists('rest_platillo_modificadores')) {
            $config = Database::queryOne(
                'SELECT exclusiones_habilitadas, extras_habilitados
                   FROM rest_configuracion WHERE restaurante_id = :restaurant_id LIMIT 1',
                [':restaurant_id' => $restaurantId]
            );
            $exclusionsEnabled = (bool)($config['exclusiones_habilitadas'] ?? true);
            $extrasEnabled = (bool)($config['extras_habilitados'] ?? true);
            $rows = Database::query(
                "SELECT id, tipo, nombre, ingrediente_id, cantidad_unidad, unidad,
                        precio_unitario, max_cantidad
                   FROM rest_platillo_modificadores
                  WHERE restaurante_id = :restaurant_id AND platillo_id = :dish_id AND activo = 1
               ORDER BY tipo ASC, nombre ASC",
                [':restaurant_id' => $restaurantId, ':dish_id' => $platilloId]
            );
            $groups = [];
            foreach (['exclusion' => 'Exclusiones', 'extra' => 'Extras'] as $type => $label) {
                if (($type === 'exclusion' && !$exclusionsEnabled) || ($type === 'extra' && !$extrasEnabled)) continue;
                $options = [];
                foreach ($rows as $row) {
                    if ($row['tipo'] !== $type) continue;
                    $options[] = [
                        'id' => (int)$row['id'],
                        'modificador_id' => (int)$row['id'],
                        'nombre' => $row['nombre'],
                        'precio_extra' => $type === 'exclusion' ? 0.0 : (float)$row['precio_unitario'],
                        'activo' => true,
                        'tipo_modificador' => $type,
                        'ingrediente_id' => $row['ingrediente_id'] !== null ? (int)$row['ingrediente_id'] : null,
                        'cantidad_unidad' => (float)$row['cantidad_unidad'],
                        'unidad' => $row['unidad'],
                        'max_cantidad' => $type === 'exclusion' ? 1 : max(1, (int)$row['max_cantidad']),
                    ];
                }
                if (!empty($options)) {
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
            $product = Database::queryOne(
                'SELECT modificadores_sincronizados_at FROM rest_platillos WHERE id = :id',
                [':id' => $platilloId]
            );
            if (!empty($rows) || !empty($product['modificadores_sincronizados_at'])) return $groups;
        }

        // Paso 1: Obtener la receta del platillo
        $receta = Database::queryOne(
            "SELECT id FROM rest_recetas WHERE platillo_id = :platillo_id LIMIT 1",
            [':platillo_id' => $platilloId]
        );

        if (!$receta) {
            return [];
        }

        // Paso 2: Ingredientes con precio_extra > 0 → extras seleccionables
            $ingredientes = Database::query(
                "SELECT ri.id,
                        i.nombre AS ingrediente_nombre,
                        COALESCE(ri.precio_extra, 0) AS precio_extra,
                        ri.codigo_display
                FROM rest_receta_ingredientes ri
                JOIN rest_ingredientes i ON i.id = ri.ingrediente_id
                WHERE ri.receta_id = :receta_id
                AND ri.precio_extra > 0
                ORDER BY i.nombre",
                [':receta_id' => $receta['id']]
            );

        if (empty($ingredientes)) {
            return [];
        }

        // Construir el modificador tipo checkbox como lo hacía Node.js
        $opciones = [];
        foreach ($ingredientes as $ri) {
            $opciones[] = [
                'id' => (int)$ri['id'],
                'modificador_id' => (int)$receta['id'],
                'nombre' => $ri['codigo_display'] ?? $ri['ingrediente_nombre'],
                'precio_extra' => (float)$ri['precio_extra'],
                'activo' => true,
            ];
        }

        return [
            [
                'id' => (int)$receta['id'],
                'nombre' => 'Extras',
                'tipo' => 'checkbox',
                'requerido' => false,
                'min_selecciones' => 0,
                'max_selecciones' => count($opciones),
                'opciones' => $opciones,
            ]
        ];
    }

    public function syncModifiers(int $branchId, int $dishId): void
    {
        AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();
        if (!Product::belongsToRestaurant($dishId, $branchId)) {
            Response::notFound('Platillo no encontrado en esta sucursal');
        }
        if (isset($input['platillo_id']) && (int)$input['platillo_id'] !== $dishId) {
            Response::validationError(['platillo_id' => ['El platillo no coincide con la URL']]);
        }
        if (!isset($input['modificadores']) || !is_array($input['modificadores'])) {
            Response::validationError(['modificadores' => ['Envia un arreglo de modificadores']]);
        }
        if (!self::tableExists('rest_platillo_modificadores')) {
            Response::serverError('Ejecuta la migracion 031 antes de sincronizar modificadores.');
        }

        $normalized = [];
        foreach ($input['modificadores'] as $modifier) {
            $id = (int)($modifier['id'] ?? 0);
            $type = strtolower(trim((string)($modifier['tipo'] ?? '')));
            $name = trim((string)($modifier['nombre'] ?? ''));
            if ($id <= 0 || !in_array($type, ['exclusion', 'extra'], true) || $name === '') {
                Response::validationError(['modificadores' => ['Cada modificador requiere id, tipo y nombre validos']]);
            }
            $normalized[] = [
                'id' => $id,
                'tipo' => $type,
                'nombre' => substr($name, 0, 150),
                'ingrediente_id' => !empty($modifier['ingrediente_id']) ? (int)$modifier['ingrediente_id'] : null,
                'cantidad_unidad' => $type === 'exclusion' ? 0.0 : max(0.0, (float)($modifier['cantidad_unidad'] ?? 0)),
                'unidad' => isset($modifier['unidad']) ? substr(trim((string)$modifier['unidad']), 0, 20) : null,
                'precio_unitario' => $type === 'exclusion' ? 0.0 : max(0.0, (float)($modifier['precio_unitario'] ?? 0)),
                'max_cantidad' => $type === 'exclusion' ? 1 : max(1, (int)($modifier['max_cantidad'] ?? 1)),
            ];
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $delete = $pdo->prepare('DELETE FROM rest_platillo_modificadores WHERE restaurante_id = :restaurant_id AND platillo_id = :dish_id');
            $delete->execute([':restaurant_id' => $branchId, ':dish_id' => $dishId]);
            $insert = $pdo->prepare(
                'INSERT INTO rest_platillo_modificadores
                    (id, restaurante_id, platillo_id, tipo, nombre, ingrediente_id, cantidad_unidad,
                     unidad, precio_unitario, max_cantidad, activo, created_at, updated_at)
                 VALUES (:id, :restaurant_id, :dish_id, :type, :name, :ingredient_id, :unit_quantity,
                         :unit, :unit_price, :max_quantity, 1, NOW(), NOW())'
            );
            foreach ($normalized as $modifier) {
                $insert->execute([
                    ':id' => $modifier['id'], ':restaurant_id' => $branchId, ':dish_id' => $dishId,
                    ':type' => $modifier['tipo'], ':name' => $modifier['nombre'],
                    ':ingredient_id' => $modifier['ingrediente_id'], ':unit_quantity' => $modifier['cantidad_unidad'],
                    ':unit' => $modifier['unidad'], ':unit_price' => $modifier['precio_unitario'],
                    ':max_quantity' => $modifier['max_cantidad'],
                ]);
            }
            $touch = $pdo->prepare('UPDATE rest_platillos SET modificadores_sincronizados_at = NOW() WHERE id = :id AND restaurante_id = :restaurant_id');
            $touch->execute([':id' => $dishId, ':restaurant_id' => $branchId]);
            RestaurantConfig::incrementVersion($branchId);
            $pdo->commit();
            $config = RestaurantConfig::getByRestaurant($branchId);
            BranchConfigEvents::publish($branchId, (int)($config['version'] ?? 0));
            Response::success(['platillo_id' => $dishId, 'modificadores' => $normalized], 'Modificadores sincronizados');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('MenuController::syncModifiers ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudieron sincronizar los modificadores.');
        }
    }

    private static function tableExists(string $tableName): bool
    {
        return !empty(Database::query("SHOW TABLES LIKE '{$tableName}'"));
    }
}
