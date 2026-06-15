<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Models\StoreCategory;
use Amare\Api\Models\StoreProduct;

class StoreController
{
    /**
     * GET /store/categories
     * Listar todas las categorías activas de la tienda (público)
     */
    public function categories(): void
    {
        $categories = StoreCategory::getAll();
        Response::success($categories);
    }

    /**
     * GET /store/products?categoria_id=&q=
     * Listar productos de la tienda con filtros opcionales (público)
     */
    public function products(): void
    {
        $categoryId = isset($_GET['categoria_id']) ? (int)$_GET['categoria_id'] : null;
        $q = $_GET['q'] ?? null;

        $products = StoreProduct::getAll($categoryId, $q);
        Response::success($products);
    }

    /**
     * GET /store/products/:id
     * Obtener detalle de un producto de la tienda (público)
     */
    public function showProduct(int $id): void
    {
        $product = StoreProduct::findById($id);

        if (!$product) {
            Response::notFound('Producto no encontrado');
        }

        Response::success(['product' => $product]);
    }
}