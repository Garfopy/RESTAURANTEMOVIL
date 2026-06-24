<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Services\RewardsService;

class RewardsController
{
    public function wallet(): void
    {
        $user = AuthMiddleware::authenticate();

        try {
            Response::success((new RewardsService())->getWallet((int)$user->id));
        } catch (\Throwable $exception) {
            error_log('RewardsController::wallet ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo cargar tu saldo Amare.');
        }
    }

    public function quote(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $context = strtolower(trim((string)($input['context'] ?? 'food')));
        $amount = (float)($input['amount'] ?? 0);
        $usePoints = !empty($input['use_points']);

        $errors = [];
        if (!in_array($context, ['food', 'gift'], true)) {
            $errors['context'] = ['Contexto de recompensa no válido'];
        }
        if ($amount <= 0) {
            $errors['amount'] = ['El monto debe ser mayor a cero'];
        }
        if ($errors) {
            Response::validationError($errors);
        }

        try {
            Response::success((new RewardsService())->quote((int)$user->id, $amount, $usePoints, $context));
        } catch (\Throwable $exception) {
            error_log('RewardsController::quote ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo cotizar tu saldo Amare.');
        }
    }
}
