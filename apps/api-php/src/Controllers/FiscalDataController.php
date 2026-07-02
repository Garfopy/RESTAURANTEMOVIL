<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\FiscalData;

class FiscalDataController
{
    public function show(): void
    {
        $user = AuthMiddleware::authenticate();
        Response::success(['fiscal_data' => FiscalData::getByUser((int)$user->id)]);
    }

    public function store(): void
    {
        $this->save();
    }

    public function update(): void
    {
        $this->save();
    }

    public function destroy(): void
    {
        $user = AuthMiddleware::authenticate();
        FiscalData::deleteByUser((int)$user->id);
        Response::success(['deleted' => true], 'Datos fiscales eliminados');
    }

    private function save(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        try {
            $fiscalData = FiscalData::upsert((int)$user->id, $input);
        } catch (\InvalidArgumentException $exception) {
            $errors = json_decode($exception->getMessage(), true);
            Response::validationError(is_array($errors) ? $errors : ['fiscal_data' => [$exception->getMessage()]]);
        }

        Response::success(['fiscal_data' => $fiscalData], 'Datos fiscales guardados');
    }
}
