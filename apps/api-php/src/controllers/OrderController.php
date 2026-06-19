<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Order;
use Amare\Api\Models\Product;

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

    public function confirmPayment(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $order = Order::findById($id, $user->id);

        if (!$order) {
            Response::notFound('Pedido no encontrado');
        }

        $metodo = $input['metodo'] ?? 'card';
        $allowed = ['card', 'cash', 'apple_pay', 'google_pay'];
        if (!in_array($metodo, $allowed, true)) {
            Response::validationError(['metodo' => ["Método de pago no válido: {$metodo}"]]);
        }

        $paymentIntentId = $input['payment_intent_id'] ?? null;

        // Actualizar pedido con método de pago
        Order::updatePaymentMethod($id, $metodo, $paymentIntentId);

        $order = Order::findById($id, $user->id);
        $exitPass = null;
        if (($order['tipo_pedido'] ?? null) === 'eat_in') {
            $exitPass = Order::ensureExitPass($id, $user->id);
        }

        Response::success([
            'ok' => true,
            'pedido_id' => $order['id'],
            'folio' => $order['folio'],
            'metodo_pago' => $metodo,
            'exit_pass' => $exitPass,
        ], 'Pago confirmado exitosamente');
    }

    public function exitPass(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $exitPass = Order::getExitPass($id, $user->id);

        if (!$exitPass) {
            Response::notFound('Pase de salida no encontrado');
        }

        Response::success(['exit_pass' => $exitPass]);
    }

    public function scanExitPass(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();
        $payload = trim((string)($input['payload'] ?? $input['token'] ?? ''));

        if ($payload === '') {
            Response::validationError(['payload' => ['El QR de salida es obligatorio']]);
        }

        $exitPass = Order::validateExitPass($payload, $user->id);
        if (!$exitPass) {
            Response::notFound('QR de salida invalido o expirado');
        }

        Response::success([
            'ok' => true,
            'exit_pass' => $exitPass,
        ], 'Salida validada y mesa liberada');
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
            $platilloId = (int)($item['product_id'] ?? $item['platillo_id'] ?? 0);
            $origen = $item['origen'] ?? 'menu';

            if ($origen === 'menu' && !Product::belongsToRestaurant($platilloId, (int)$input['restaurante_id'])) {
                Response::validationError([
                    'items' => ["El platillo {$platilloId} no pertenece a la sucursal seleccionada"]
                ]);
            }

            $items[] = [
                'platillo_id' => $platilloId,
                'cantidad' => $item['quantity'] ?? $item['cantidad'],
                'precio_unit' => $item['unit_price'] ?? $item['precio_unit'],
                'notas' => $item['options'] ?? $item['notas'] ?? null,
                'modificadores' => $item['modificadores'] ?? [],
                'origen' => $origen
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
                'direccion_id' => $input['direccion_id'] ?? null,
                'direccion_entrega' => $input['direccion_entrega'] ?? null,
                'mesa_id' => $input['mesa_id'] ?? null,
                'consumo_por_mesa' => !empty($input['consumo_por_mesa']),
                'payment_intent_id' => $input['payment_intent_id'] ?? null,
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

        $order = Order::findById($orderId, $user->id);
        
        Response::success(['order' => $order], 'Pedido creado exitosamente', 201);
    }
}
