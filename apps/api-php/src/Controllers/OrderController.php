<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\InvoiceRequest;
use Amare\Api\Models\Order;
use Amare\Api\Models\Product;
use Amare\Api\Models\Promotion;
use Amare\Api\Models\User;
use Amare\Api\Services\RewardsService;
use Amare\Api\Services\StripeConfig;
use Stripe\PaymentIntent;
use Stripe\Charge;
use Stripe\Stripe;

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
        $allowed = ['card', 'cash', 'apple_pay', 'google_pay', 'amare_wallet'];
        if (!in_array($metodo, $allowed, true)) {
            Response::validationError(['metodo' => ["Método de pago no válido: {$metodo}"]]);
        }

        $promoCode = trim((string)($input['promo_code'] ?? $input['coupon_code'] ?? ''));
        if ($promoCode !== '') {
            try {
                $order = Order::applyPromotionCode($id, (int)$user->id, $promoCode) ?? $order;
            } catch (\InvalidArgumentException $exception) {
                Response::validationError(['promo_code' => [$exception->getMessage()]]);
            }
        }

        try {
            InvoiceRequest::validateForPayment((int)($order['restaurante_id'] ?? 0), $input['invoice_request'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            $errors = json_decode($exception->getMessage(), true);
            Response::validationError(is_array($errors) ? $errors : ['invoice_request' => [$exception->getMessage()]]);
        }

        $paymentIntentId = $input['payment_intent_id'] ?? null;

        if ($metodo === 'amare_wallet') {
            $pdo = Database::getInstance();
            $amount = (float)($order['total'] ?? $order['subtotal'] ?? 0);
            $rewards = new RewardsService();
            try {
                // Prepara wallet/esquema fuera de la transaccion. DDL en MySQL hace
                // commit implicito y no debe ejecutarse dentro del cobro.
                $rewards->quote((int)$user->id, $amount, !empty($input['use_points']), 'food');

                $pdo->beginTransaction();
                $reward = $rewards->charge(
                    $pdo,
                    (int)$user->id,
                    $amount,
                    !empty($input['use_points']),
                    'food',
                    'order',
                    $id,
                    'Pago de alimentos con Saldo Amare',
                    is_array($order['items'] ?? null) ? $order['items'] : []
                );
                Order::applyRewardsPayment($id, $reward);
                Promotion::recordUsageForOrder($pdo, (int)$user->id, $order);
                $pdo->commit();
            } catch (\DomainException $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                Response::error($exception->getMessage(), 409);
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('OrderController::confirmPayment wallet ERROR: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
                Response::serverError('No se pudo pagar con Saldo Amare.');
            }

            $order = Order::findById($id, $user->id);
            $exitPass = null;
            if (($order['tipo_pedido'] ?? null) === 'eat_in') {
                try {
                    $exitPass = Order::ensureExitPass($id, $user->id);
                } catch (\Throwable $exception) {
                    error_log('OrderController::confirmPayment exit pass ERROR: ' . $exception->getMessage());
                    $exitPass = null;
                }
            }

            $invoiceRequest = $this->createInvoiceRequestForOrder($order ?? [], $input, (int)$user->id, $metodo);

            Response::success([
                'ok' => true,
                'pedido_id' => $order['id'],
                'folio' => $order['folio'],
                'metodo_pago' => $metodo,
                'reward' => $reward,
                'exit_pass' => $exitPass,
                'invoice_request' => $invoiceRequest,
                'invoice_request_id' => $invoiceRequest['id'] ?? null,
            ], 'Pago con Saldo Amare confirmado');
        }

        // Actualizar pedido con método de pago
        if (in_array($metodo, ['card', 'apple_pay', 'google_pay'], true)) {
            $metodo = $this->assertStripePaymentSucceeded(
                trim((string)$paymentIntentId),
                (int)$user->id,
                $order,
                !empty($input['use_points'])
            );
        }

        $reward = null;
        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            if (!Order::updatePaymentMethod($id, $metodo, $paymentIntentId)) {
                throw new \RuntimeException('No se pudo actualizar el estado de pago del pedido.');
            }
            $reward = (new RewardsService())->awardPoints(
                $pdo,
                (int)$user->id,
                (float)($order['total'] ?? $order['subtotal'] ?? 0),
                !empty($input['use_points']),
                'food',
                'order',
                $id,
                'Puntos generados por compra de alimentos'
            );
            if (empty($reward['already_applied'])) {
                Order::applyExternalRewardsSummary($id, $reward, $metodo !== 'cash');
            }
            Promotion::recordUsageForOrder($pdo, (int)$user->id, $order);
            $pdo->commit();
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('OrderController::confirmPayment reward ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo confirmar el pago del pedido.');
        }

        $order = Order::findById($id, $user->id);
        $exitPass = null;
        if (($order['tipo_pedido'] ?? null) === 'eat_in') {
            try {
                $exitPass = Order::ensureExitPass($id, $user->id);
            } catch (\Throwable $exception) {
                error_log('OrderController::confirmPayment exit pass ERROR: ' . $exception->getMessage());
                $exitPass = null;
            }
        }

        if (in_array($metodo, ['card', 'apple_pay', 'google_pay'], true)) {
            try {
                (new PaymentController())->fulfillPendingInvoiceRequest($order ?? [], (int)$user->id, $metodo);
                $invoiceRequest = InvoiceRequest::findByOrder($id);
            } catch (\Throwable $exception) {
                error_log('OrderController::confirmPayment invoice ERROR: ' . $exception->getMessage());
                $invoiceRequest = null;
            }
        } else {
            $invoiceRequest = $this->createInvoiceRequestForOrder($order ?? [], $input, (int)$user->id, $metodo);
        }

        Response::success([
            'ok' => true,
            'pedido_id' => $order['id'],
            'folio' => $order['folio'],
            'metodo_pago' => $metodo,
            'reward' => $reward,
            'exit_pass' => $exitPass,
            'invoice_request' => $invoiceRequest,
            'invoice_request_id' => $invoiceRequest['id'] ?? null,
        ], 'Pago confirmado exitosamente');
    }

    private function createInvoiceRequestForOrder(array $order, array $input, int $userId, string $paymentMethod): ?array
    {
        $payload = $input['invoice_request'] ?? null;
        if (!is_array($payload) || !InvoiceRequest::isRequired($payload)) {
            return null;
        }

        try {
            return InvoiceRequest::createFromPayment([
                'restaurante_id' => (int)($order['restaurante_id'] ?? 0),
                'pedido_id' => (int)($order['id'] ?? 0),
                'consumo_id' => $order['consumo_id'] ?? null,
                'mesa_id' => $order['mesa_id'] ?? null,
                'mobile_usuario_id' => $userId,
                'solicitado_por_usuario_id' => $userId,
                'origen' => 'cliente',
                'scope' => 'pedido',
                'monto' => (float)($order['total'] ?? $order['subtotal'] ?? 0),
                'metodo_pago' => $paymentMethod,
            ], $payload);
        } catch (\InvalidArgumentException $exception) {
            $errors = json_decode($exception->getMessage(), true);
            Response::validationError(is_array($errors) ? $errors : ['invoice_request' => [$exception->getMessage()]]);
        }

        return null;
    }

    public function exitPass(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $exitPass = Order::getExitPass($id, $user->id) ?? Order::ensureExitPass($id, $user->id);

        if (!$exitPass) {
            Response::error('Aun tienes saldo pendiente antes de generar el QR de salida.', 409);
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

        try {
            $exitPass = Order::validateExitPass($payload, $user->id);
        } catch (\DomainException $exception) {
            Response::error($exception->getMessage(), 409);
        }
        if (!$exitPass) {
            Response::notFound('QR de salida inválido o expirado');
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

    public function timeline(int $id): void
    {
        $user = AuthMiddleware::authenticate();
        $order = Order::findById($id, $user->id);

        if (!$order) {
            Response::notFound('Pedido no encontrado');
        }

        Response::success([
            'timeline' => $this->buildTimeline($order),
        ]);
    }

    private function buildTimeline(array $order): array
    {
        $isEatIn = ($order['tipo_pedido'] ?? null) === 'eat_in';
        $isConsumption = $isEatIn && (
            !empty($order['es_consumo']) ||
            !empty($order['consumo_id']) ||
            (int)($order['pedidos_count'] ?? 0) > 1 ||
            (int)($order['cuenta_abierta'] ?? 0) === 1
        );

        if ($isConsumption) {
            $hasExitQr = !empty($order['salida_qr_generado_at']);
            $isValidated = !empty($order['salida_validado_at']);
            return [
                [
                    'estado' => 'pendiente',
                    'label' => 'Pedido recibido',
                    'descripcion' => 'La cuenta quedo abierta para esta mesa.',
                    'completado' => true,
                    'en_curso' => false,
                    'timestamp' => $order['created_at'] ?? null,
                ],
                [
                    'estado' => 'en_preparacion',
                    'label' => 'Cuenta abierta',
                    'descripcion' => 'Puedes seguir pidiendo antes de pagar.',
                    'completado' => $hasExitQr || $isValidated,
                    'en_curso' => !$hasExitQr && !$isValidated,
                    'timestamp' => $order['updated_at'] ?? $order['created_at'] ?? null,
                ],
                [
                    'estado' => 'listo',
                    'label' => 'QR de salida',
                    'descripcion' => 'Se genera al pagar la cuenta.',
                    'completado' => $hasExitQr || $isValidated,
                    'en_curso' => $hasExitQr && !$isValidated,
                    'timestamp' => $order['salida_qr_generado_at'] ?? null,
                ],
                [
                    'estado' => 'entregado',
                    'label' => 'Salida validada',
                    'descripcion' => 'Hostess cierra la visita al escanear el QR.',
                    'completado' => $isValidated,
                    'en_curso' => false,
                    'timestamp' => $order['salida_validado_at'] ?? null,
                ],
            ];
        }

        $statusOrder = ['pendiente', 'en_preparacion', 'listo', 'entregado'];
        $status = (string)($order['estado'] ?? 'pendiente');
        $currentIndex = array_search($status, $statusOrder, true);
        $currentIndex = $currentIndex === false ? 0 : (int)$currentIndex;

        return [
            [
                'estado' => 'pendiente',
                'label' => 'Pedido recibido',
                'descripcion' => 'Tu orden entro al restaurante.',
                'completado' => $currentIndex > 0,
                'en_curso' => $currentIndex === 0,
                'timestamp' => $order['created_at'] ?? null,
            ],
            [
                'estado' => 'en_preparacion',
                'label' => 'En preparacion',
                'descripcion' => 'Cocina esta preparando tus alimentos.',
                'completado' => $currentIndex > 1,
                'en_curso' => $currentIndex === 1,
                'timestamp' => $order['updated_at'] ?? null,
            ],
            [
                'estado' => 'listo',
                'label' => ($order['tipo_pedido'] ?? null) === 'delivery' ? 'Listo para envio' : 'Listo',
                'descripcion' => ($order['tipo_pedido'] ?? null) === 'delivery' ? 'Tu pedido esta listo para salir.' : 'Tu pedido esta listo para entrega.',
                'completado' => $currentIndex > 2,
                'en_curso' => $currentIndex === 2,
                'timestamp' => $order['updated_at'] ?? null,
            ],
            [
                'estado' => 'entregado',
                'label' => 'Entregado',
                'descripcion' => 'El pedido fue completado.',
                'completado' => $status === 'entregado',
                'en_curso' => false,
                'timestamp' => $status === 'entregado' ? ($order['updated_at'] ?? null) : null,
            ],
        ];
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

        $customerName = trim((string)($user->nombre ?? ''));
        if ($customerName === '') {
            $profile = User::findById((int)$user->id);
            $customerName = trim((string)($profile['nombre'] ?? ''));
        }

        try {
            $orderId = Order::create([
                'restaurante_id' => $input['restaurante_id'],
                'user_id' => $user->id,
                'order_type' => $input['tipo_pedido'],
                'subtotal' => $input['subtotal'],
                'total' => $input['total'],
                'cliente_nombre' => $input['cliente_nombre'] ?? ($customerName !== '' ? $customerName : 'Cliente app'),
                'notes' => $input['notas'] ?? null,
                'direccion_id' => $input['direccion_id'] ?? null,
                'direccion_entrega' => $input['direccion_entrega'] ?? null,
                'mesa_id' => $input['mesa_id'] ?? null,
                'consumo_por_mesa' => !empty($input['consumo_por_mesa']),
                'payment_intent_id' => $input['payment_intent_id'] ?? null,
                'promo_code' => $input['promo_code'] ?? $input['coupon_code'] ?? null,
                'items' => $items
            ]);
        } catch (\InvalidArgumentException $e) {
            Response::validationError(['items' => [$e->getMessage()]]);
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
            if (str_contains($message, 'cuentas separadas')) {
                Response::error($message, 409, 'SPLIT_ACCOUNT_ACTIVE');
            }
            Response::serverError($message);
        }

        if (!$orderId) {
            Response::serverError('No se pudo crear el pedido');
        }

        $order = Order::findById($orderId, $user->id);
        
        Response::success(['order' => $order], 'Pedido creado exitosamente', 201);
    }

    private function assertStripePaymentSucceeded(
        string $paymentIntentId,
        int $userId,
        array $order,
        bool $usePoints
    ): string {
        if ($paymentIntentId === '') {
            Response::validationError(['payment_intent_id' => ['El intento de pago de Stripe es obligatorio']]);
        }

        $storedIntentId = trim((string)($order['payment_intent_id'] ?? $order['stripe_payment_intent_id'] ?? ''));
        if ($storedIntentId !== '' && !hash_equals($storedIntentId, $paymentIntentId)) {
            Response::error('El intento de pago no pertenece a este pedido.', 409, 'PAYMENT_INTENT_MISMATCH');
        }

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intent = PaymentIntent::retrieve($paymentIntentId);
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('OrderController::assertStripePaymentSucceeded STRIPE ERROR: ' . $exception->getMessage());
            Response::error('Stripe no pudo verificar este pago.', 409, 'PAYMENT_NOT_VERIFIED');
        }

        $metadataUserId = (int)($intent->metadata['user_id'] ?? 0);
        $metadataOrderId = (int)($intent->metadata['order_id'] ?? 0);
        $metadataUsePoints = (string)($intent->metadata['use_points'] ?? '0') === '1';
        $orderId = (int)($order['id'] ?? 0);
        $baseAmount = (float)($order['total'] ?? $order['subtotal'] ?? 0);
        $rewardsQuote = (new RewardsService())->quote(
            $userId,
            $baseAmount,
            $usePoints,
            'food',
            is_array($order['items'] ?? null) ? $order['items'] : [],
            'external'
        );
        $expectedCents = (int)round((float)$rewardsQuote['wallet_total'] * 100);
        $receivedCents = (int)($intent->amount_received ?: $intent->amount);

        if ($metadataUserId !== $userId || ($metadataOrderId > 0 && $metadataOrderId !== $orderId)) {
            Response::forbidden('Este pago de Stripe no pertenece a tu cuenta o pedido.');
        }
        if ($metadataUsePoints !== $usePoints) {
            Response::error('La seleccion de puntos no coincide con el importe autorizado.', 409, 'PAYMENT_POINTS_MISMATCH');
        }
        if (strtolower((string)$intent->currency) !== 'mxn' || $receivedCents !== $expectedCents) {
            Response::error('El importe confirmado por Stripe no coincide con el pedido.', 409, 'PAYMENT_AMOUNT_MISMATCH');
        }
        if ((string)$intent->status !== 'succeeded') {
            Response::error('Stripe aun no confirma el pago.', 409, 'PAYMENT_NOT_SUCCEEDED');
        }

        if ((bool)$intent->livemode !== StripeConfig::isLiveMode()) {
            Response::error('El entorno del pago no coincide con el servidor.', 409, 'PAYMENT_MODE_MISMATCH');
        }

        return $this->stripePaymentMethod($intent);
    }

    private function stripePaymentMethod(object $intent): string
    {
        try {
            $charge = $intent->latest_charge ?? null;
            if (is_string($charge) && $charge !== '') {
                $charge = Charge::retrieve($charge);
            }
            $walletType = strtolower((string)($charge?->payment_method_details?->card?->wallet?->type ?? ''));
            if ($walletType === 'apple_pay') return 'apple_pay';
            if ($walletType === 'google_pay') return 'google_pay';
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('OrderController::stripePaymentMethod STRIPE ERROR: ' . $exception->getMessage());
        }

        return 'card';
    }

    private function getStripeSecret(): string
    {
        return StripeConfig::secretKey();
    }
}
