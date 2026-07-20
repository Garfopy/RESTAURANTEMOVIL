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
        
        // La Express API devuelve array directamente en data, no { favorites: [...] }
        Response::success($favorites);
    }

    public function store(int $productId): void
    {
        $user = AuthMiddleware::authenticate();

        // Comportamiento toggle: si ya existe, lo elimina; si no, lo agrega
        if (Favorite::isFavorite($user->id, $productId)) {
            Favorite::remove($user->id, $productId);
            Response::success(['favorito' => false], 'Eliminado de favoritos');
        } else {
            Favorite::add($user->id, $productId);
            Response::success(['favorito' => true], 'Agregado a favoritos', 201);
        }
    }

    public function destroy(int $productId): void
    {
        $user = AuthMiddleware::authenticate();

        Favorite::remove($user->id, $productId);

        Response::success(null, 'Eliminado de favoritos');
    }

    public function toggle()
    {
        $user = AuthMiddleware::authenticate();

        $data = json_decode(file_get_contents("php://input"), true);
        $productId = (int) ($data['product_id'] ?? 0);

        if ($productId <= 0) {
            http_response_code(400);
            echo json_encode([
                'ok' => false,
                'message' => 'ID inválido'
            ]);
            return;
        }

        $result = Favorite::toggle($user->id, $productId);

        header('Content-Type: application/json');
        echo json_encode($result);
    }
}
