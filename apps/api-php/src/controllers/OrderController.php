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
        $input = ValidationMiddleware::getAllInput();
        $tipo = $input['tipo'] ?? null;
        $orders = Order::getByUser($user->id, $tipo);
        
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
            'restaurante_id' => 'required|integer',
            'tipo_pedido' => 'required|in:delivery,pickup,eat_in',
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

        // Mapear items del payload (product_id, quantity, unit_price, options)
        // a lo que espera la DB (platillo_id, cantidad, precio_unit, notas)
        $items = [];
        foreach ($input['items'] as $item) {
            $items[] = [
                'platillo_id' => $item['product_id'] ?? $item['platillo_id'],
                'cantidad' => $item['quantity'] ?? $item['cantidad'],
                'precio_unit' => $item['unit_price'] ?? $item['precio_unit'],
                'notas' => $item['options'] ?? $item['notas'] ?? null,
                'modificadores' => $item['modificadores'] ?? [],
                'origen' => $item['origen'] ?? 'menu'
            ];
        }

        try {
            $orderId = Order::create([
                'restaurante_id' => $input['restaurante_id'],
                'user_id' => $user->id,
                'order_type' => $input['tipo_pedido'],
                'subtotal' => $input['subtotal'],
                'total' => $input['total'],
                'notes' => $input['notas'] ?? null,
                'items' => $items
            ]);
        } catch (\RuntimeException $e) {
            // Error de stock insuficiente (u otro error de negocio)
            $message = $e->getMessage();
            // Si el mensaje contiene "Stock insuficiente", devolver 409 Conflict
            if (str_contains($message, 'Stock insuficiente')) {
                http_response_code(409);
                echo json_encode([
                    'ok' => false,
                    'message' => $message,
                    'code' => 'STOCK_INSUFFICIENT'
                ]);
                exit;
            }
            Response::serverError($message);
        }

        if (!$orderId) {
            Response::serverError('No se pudo crear el pedido');
        }

        $order = Order::findById($orderId);
        
        Response::success(['order' => $order], 'Pedido creado exitosamente', 201);
    }
}