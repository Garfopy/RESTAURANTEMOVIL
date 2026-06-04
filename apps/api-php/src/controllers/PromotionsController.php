<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Promotion;

class PromotionsController
{
    public function index(): void
    {
        $promotions = Promotion::getAll();
        Response::success(['promotions' => $promotions]);
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
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'code' => 'required|string|max:50'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $promotion = Promotion::validateCode($input['code']);

        if (!$promotion) {
            Response::error('Código de promoción inválido o expirado', 404);
        }

        Response::success(['promotion' => $promotion]);
    }
}