<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Models\Category;
use Amare\Api\Models\Product;
use Amare\Api\Config\Database;
use Amare\Api\Middleware\ValidationMiddleware;

class MenuController
{
    public function categories(): void
    {
        $categories = Category::getAll();
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
        $product = Product::findById($id);

        if (!$product) {
            Response::notFound('Producto no encontrado');
        }

        // 🔥 Fetch modifiers/extras like the old Node.js API did
        $product['modificadores'] = self::getModificadores($id);
        $product['tiene_receta'] = !empty($product['modificadores']);

        Response::success(['product' => $product]);
    }

    /**
     * Obtiene los modificadores (extras) de un platillo.
     * Replica la lógica de Node.js: busca receta → ingredientes con precio_extra > 0
     */
    private static function getModificadores(int $platilloId): array
    {
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
}