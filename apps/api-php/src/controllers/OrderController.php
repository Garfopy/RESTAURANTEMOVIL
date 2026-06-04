<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Order;

class OrderController
{
    public function index(): void
    {
        $user = AuthMiddleware::authenticate();
        $orders = Order::getByUser($user->id);
        
        Response::success(['orders' => $orders]);
    }

    public function show(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $order = Order::findById($id, $user->id);

        if (!$order) {
            Response::notFound('Pedido no encontrado');
        }

        Response::success(['order' => $order]);
    }

    public function store(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'branch_id' => 'required|integer',
            'order_type' => 'required|in:pickup,delivery',
            'subtotal' => 'required|numeric',
            'total' => 'required|numeric',
            'items' => 'required|array'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        if (empty($input['items']) || !is_array($input['items'])) {
            Response::validationError(['items' => ['Debe incluir al menos un producto']]);
        }

        $orderId = Order::create([
            'user_id' => $user->id,
            'branch_id' => $input['branch_id'],
            'order_type' => $input['order_type'],
            'subtotal' => $input['subtotal'],
            'tax' => $input['tax'] ?? 0,
            'total' => $input['total'],
            'notes' => $input['notes'] ?? null,
            'items' => $input['items']
        ]);

        if (!$orderId) {
            Response::serverError('No se pudo crear el pedido');
        }

        $order = Order::findById($orderId);
        
        Response::success(['order' => $order], 'Pedido creado exitosamente', 201);
    }
}