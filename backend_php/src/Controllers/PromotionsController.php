<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Promotion;

class PromotionsController
{
    public function index(): void
    {
        // Autenticación opcional: si hay token, filtra por usuario
        $user = AuthMiddleware::optional();
        
        if ($user) {
            // Usuario autenticado → solo sus promos
            $promotions = Promotion::getByUser((int)$user->id);
        } else {
            // Invitado → array vacío (más adelante podrían ser promos generales)
            $promotions = [];
        }
        
        Response::success($promotions);
    }

    public function show(int $id): void
    {
        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promoción no encontrada');
        }

        Response::success(['promotion' => $promotion]);
    }

    public function validateCode(): void
    {
        $user = AuthMiddleware::authenticate();

        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'code' => 'required|string|max:50'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $promotion = Promotion::validateCode($input['code'], (int)$user->id);

        if (!$promotion) {
            Response::error('Código de promoción inválido o expirado', 404);
        }

        Response::success(['promotion' => $promotion]);
    }
}
