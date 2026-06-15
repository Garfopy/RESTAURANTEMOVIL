<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Address;

class AddressController
{
    public function index(): void
    {
        $user = AuthMiddleware::authenticate();
        $addresses = Address::findByUserId($user->id);

        // Devolver array directamente en data para compatibilidad con app móvil
        Response::success($addresses, 'Direcciones obtenidas exitosamente');
    }

    public function show(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $address = Address::findById($id);

        if (!$address || $address['usuario_id'] !== $user->id) {
            Response::notFound('Dirección no encontrada');
        }

        Response::success($address);
    }

    public function store(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'alias' => 'max:100',
            'calle' => 'max:255',
            'numero' => 'max:20',
            'colonia' => 'max:150',
            'ciudad' => 'max:100',
            'cp' => 'max:10',
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $addressId = Address::create($user->id, $input);

        if (!$addressId) {
            Response::serverError('No se pudo guardar la dirección');
        }

        $address = Address::findById($addressId);

        Response::success($address, 'Dirección guardada exitosamente', 201);
    }

    public function update(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $address = Address::findById($id);

        if (!$address || $address['usuario_id'] !== $user->id) {
            Response::notFound('Dirección no encontrada');
        }

        $rules = [
            'alias' => 'max:100',
            'calle' => 'max:255',
            'numero' => 'max:20',
            'colonia' => 'max:150',
            'ciudad' => 'max:100',
            'cp' => 'max:10',
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        if (!Address::update($id, $user->id, $input)) {
            Response::serverError('No se pudo actualizar la dirección');
        }

        $updated = Address::findById($id);

        Response::success($updated, 'Dirección actualizada exitosamente');
    }

    public function destroy(int $id): void
    {
        $user = AuthMiddleware::authenticate();

        $address = Address::findById($id);

        if (!$address || $address['usuario_id'] !== $user->id) {
            Response::notFound('Dirección no encontrada');
        }

        if (!Address::delete($id, $user->id)) {
            Response::serverError('No se pudo eliminar la dirección');
        }

        Response::success(null, 'Dirección eliminada exitosamente');
    }
}