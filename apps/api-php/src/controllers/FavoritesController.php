<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Favorite;

class FavoritesController
{
    public function index(): void
    {
        $user = AuthMiddleware::authenticate();
        $favorites = Favorite::getByUser($user->id);
        
        Response::success(['favorites' => $favorites]);
    }

    public function store(int $productId): void
    {
        $user = AuthMiddleware::authenticate();

        if (Favorite::isFavorite($user->id, $productId)) {
            Response::error('El producto ya está en favoritos', 409);
        }

        if (!Favorite::add($user->id, $productId)) {
            Response::serverError('No se pudo agregar a favoritos');
        }

        Response::success(null, 'Agregado a favoritos', 201);
    }

    public function destroy(int $productId): void
    {
        $user = AuthMiddleware::authenticate();

        if (!Favorite::remove($user->id, $productId)) {
            Response::error('El producto no está en favoritos', 404);
        }

        Response::success(null, 'Eliminado de favoritos');
    }
}