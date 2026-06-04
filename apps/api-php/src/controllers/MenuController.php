<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Models\Category;
use Amare\Api\Models\Product;
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
        
        $products = Product::getAll($categoryId, $branchId);
        Response::success(['products' => $products]);
    }

    public function showProduct(int $id): void
    {
        $product = Product::findById($id);

        if (!$product) {
            Response::notFound('Producto no encontrado');
        }

        Response::success(['product' => $product]);
    }
}