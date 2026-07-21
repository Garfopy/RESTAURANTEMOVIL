<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Amare\Api\Models\Order;
use Amare\Api\Services\RewardsService;
use Amare\Api\Services\StripeConfig;

class PaymentController
{
    /**
     * Obtiene de forma segura la clave secreta de Stripe con fallback
     */
    private function getStripeSecret(): string
    {
        return StripeConfig::secretKey();
    }

    /**
     * Obtiene de forma segura el secreto del Webhook de Stripe con fallback
     */
    private function getStripeWebhookSecret(): string
    {
        return StripeConfig::webhookSecret();
    }

    public function createPaymentIntent(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'amount' => 'numeric',
            'order_id' => 'integer',
            'restaurante_id' => 'integer',
            'items' => 'array'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $currency = strtolower(trim((string)($input['currency'] ?? 'mxn')));
            if ($currency !== 'mxn') {
                Response::validationError(['currency' => ['La moneda permitida es MXN']]);
            }

            $orderId = (int)($input['order_id'] ?? 0);
            $promoCode = trim((string)($input['promo_code'] ?? ''));
            $usePoints = filter_var($input['use_points'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $pricingItems = [];
            $previousIntentId = '';

            if ($orderId > 0) {
                $order = Order::findById($orderId, (int)$user->id);
                if (!$order) {
                    Response::notFound('Pedido no encontrado');
                }

                if ($promoCode !== '') {
                    $order = Order::applyPromotionCode($orderId, (int)$user->id, $promoCode) ?? $order;
                }

                $amount = (float)($order['total'] ?? $order['subtotal'] ?? 0);
                $pricingItems = is_array($order['items'] ?? null) ? $order['items'] : [];
                $previousIntentId = trim((string)($order['payment_intent_id'] ?? $order['stripe_payment_intent_id'] ?? ''));
            } elseif (!empty($input['items']) && !empty($input['restaurante_id'])) {
                $quote = Order::quote([
                    'restaurante_id' => (int)$input['restaurante_id'],
                    'items' => $input['items'],
                    'user_id' => (int)$user->id,
                    'promo_code' => $promoCode !== '' ? $promoCode : null,
                ]);
                $amount = (float)$quote['total'];
                $pricingItems = is_array($quote['items'] ?? null) ? $quote['items'] : [];
            } else {
                Response::validationError([
                    'payment' => ['Envia order_id o restaurante_id con los productos para calcular el total en el servidor'],
                ]);
            }

            $rewardsQuote = (new RewardsService())->quote(
                (int)$user->id,
                $amount,
                $usePoints,
                'food',
                $pricingItems,
                'external'
            );
            $amount = round((float)$rewardsQuote['wallet_total'], 2);
            if ($amount <= 0) {
                Response::error('El total a cobrar debe ser mayor a cero.', 409, 'PAYMENT_AMOUNT_ZERO');
            }

            // 🛠️ Cambiado a método seguro protegido contra fallas de lectura de .env
            Stripe::setApiKey($this->getStripeSecret());

            if ($previousIntentId !== '') {
                try {
                    $existingIntent = PaymentIntent::retrieve($previousIntentId);
                    $existingUserId = (int)($existingIntent->metadata['user_id'] ?? 0);
                    $existingOrderId = (int)($existingIntent->metadata['order_id'] ?? 0);
                    if (
                        (string)$existingIntent->status !== 'canceled' &&
                        $existingUserId === (int)$user->id &&
                        ($existingOrderId === 0 || $existingOrderId === $orderId) &&
                        strtolower((string)$existingIntent->currency) === 'mxn'
                    ) {
                        Response::success([
                            'client_secret' => $existingIntent->client_secret,
                            'payment_intent_id' => $existingIntent->id,
                            'amount_mxn' => round((int)$existingIntent->amount / 100, 2),
                            'status' => (string)$existingIntent->status,
                            'use_points' => (string)($existingIntent->metadata['use_points'] ?? '0') === '1',
                        ], 'Payment intent recuperado');
                    }
                } catch (\Stripe\Exception\ApiErrorException $exception) {
                    error_log('PaymentController::createPaymentIntent existing intent ERROR: ' . $exception->getMessage());
                }
            }

            $metadata = [
                'user_id' => (string)$user->id,
                'pricing_source' => $orderId > 0 ? 'order' : 'server_quote',
                'use_points' => $usePoints ? '1' : '0',
            ];

            if ($orderId > 0) {
                $metadata['order_id'] = (string)$orderId;
            }
            if ($promoCode !== '') {
                $metadata['promo_code'] = substr($promoCode, 0, 100);
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => (int)round($amount * 100),
                'currency' => 'mxn',
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true
                ]
            ], [
                'idempotency_key' => $this->paymentIntentIdempotencyKey(
                    (int)$user->id,
                    $orderId,
                    (int)round($amount * 100),
                    $usePoints,
                    $promoCode,
                    (int)($input['restaurante_id'] ?? 0),
                    $pricingItems,
                    $previousIntentId
                ),
            ]);

            if ($orderId > 0 && !Order::attachPaymentIntent($orderId, (int)$user->id, (string)$paymentIntent->id)) {
                throw new \RuntimeException('No se pudo asociar el intento de pago al pedido');
            }

            Response::success([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount_mxn' => $amount,
                'status' => (string)$paymentIntent->status,
                'use_points' => $usePoints,
            ], 'Payment intent creado exitosamente');
        } catch (\InvalidArgumentException $e) {
            Response::validationError(['items' => [$e->getMessage()]]);
        } catch (\Exception $e) {
            error_log('PaymentController::createPaymentIntent ERROR: ' . $e->getMessage());
            Response::serverError('No se pudo iniciar el pago.');
        }
    }

    public function webhook(): void
    {
        $payload = file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        
        try {
            // 🛠️ Cambiado a método seguro
            Stripe::setApiKey($this->getStripeSecret());
            
            // 🛠️ Cambiado a método seguro para el webhook secret
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->getStripeWebhookSecret()
            );

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $rewardsAction = (string)($paymentIntent->metadata['rewards_action'] ?? '');
                    $orderId = (int) ($paymentIntent->metadata['order_id'] ?? 0);
                    $storedOrderId = $this->findOrderIdByPaymentIntent((string)$paymentIntent->id);
                    if ($storedOrderId > 0) {
                        $orderId = $storedOrderId;
                    }
                    $giftOrderId = (int) ($paymentIntent->metadata['gift_order_id'] ?? 0);
                    if ($rewardsAction === 'wallet_topup') {
                        $topupUserId = (int)($paymentIntent->metadata['user_id'] ?? 0);
                        $topupAmount = round(((int)($paymentIntent->amount_received ?: $paymentIntent->amount)) / 100, 2);
                        if ($topupUserId > 0) {
                            $pdo = Database::getInstance();
                            $pdo->beginTransaction();
                            try {
                                (new RewardsService())->applyTopup(
                                    $pdo,
                                    $topupUserId,
                                    $topupAmount,
                                    $paymentIntent->id,
                                    'Recarga de Saldo Amare con Stripe'
                                );
                                $pdo->commit();
                            } catch (\Throwable $exception) {
                                if ($pdo->inTransaction()) {
                                    $pdo->rollBack();
                                }
                                throw $exception;
                            }
                        }
                    }
                    if ($orderId > 0) {
                        $this->markOrderPaidFromWebhook($orderId, $paymentIntent);
                    }
                    if ($giftOrderId > 0) {
                        $gift = Database::queryOne(
                            'SELECT gift_precio, moneda FROM social_gift_orders WHERE id = :id AND stripe_payment_intent_id = :intent_id',
                            [':id' => $giftOrderId, ':intent_id' => $paymentIntent->id]
                        );
                        $expectedCents = $gift ? (int)round((float)$gift['gift_precio'] * 100) : -1;
                        if ($gift && $expectedCents === (int)$paymentIntent->amount && strtolower((string)$paymentIntent->currency) === strtolower((string)$gift['moneda'])) {
                            Database::rowCount(
                                "UPDATE social_gift_orders
                                    SET status = IF(status IN ('pendiente_pago','pago_fallido'), 'listo', status),
                                        pagado_at = COALESCE(pagado_at, NOW()), updated_at = NOW()
                                  WHERE id = :id AND stripe_payment_intent_id = :intent_id",
                                [':id' => $giftOrderId, ':intent_id' => $paymentIntent->id]
                            );
                        } else {
                            error_log("Pago de regalo #{$giftOrderId} con importe o moneda inconsistente");
                        }
                    }
                    break;
                    
                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $orderId = (int) ($paymentIntent->metadata['order_id'] ?? 0);
                    $storedOrderId = $this->findOrderIdByPaymentIntent((string)$paymentIntent->id);
                    if ($storedOrderId > 0) {
                        $orderId = $storedOrderId;
                    }
                    $giftOrderId = (int) ($paymentIntent->metadata['gift_order_id'] ?? 0);
                    if ($orderId > 0) {
                        // Registrar el fallo sin cambiar estado
                        error_log("Pago fallido para pedido #{$orderId}: {$paymentIntent->last_payment_error?->message}");
                    }
                    if ($giftOrderId > 0) {
                        Database::rowCount(
                            "UPDATE social_gift_orders SET status = 'pago_fallido', updated_at = NOW()
                              WHERE id = :id AND stripe_payment_intent_id = :intent_id AND status = 'pendiente_pago'",
                            [':id' => $giftOrderId, ':intent_id' => $paymentIntent->id]
                        );
                    }
                    break;
            }

            Response::success(null, 'Webhook procesado', 200);
        } catch (\UnexpectedValueException $e) {
            Response::error('Webhook inválido', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Response::error('Firma inválida', 400);
        } catch (\Throwable $e) {
            error_log('PaymentController::webhook ERROR: ' . $e->getMessage());
            Response::serverError('No se pudo procesar el webhook.');
        }
    }

    private function findOrderIdByPaymentIntent(string $paymentIntentId): int
    {
        if ($paymentIntentId === '') {
            return 0;
        }

        try {
            $column = Database::queryOne(
                "SELECT COLUMN_NAME
                   FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = 'rest_pedidos'
                    AND COLUMN_NAME IN ('payment_intent_id', 'stripe_payment_intent_id')
                  ORDER BY FIELD(COLUMN_NAME, 'payment_intent_id', 'stripe_payment_intent_id')
                  LIMIT 1"
            );
            $columnName = (string)($column['COLUMN_NAME'] ?? '');
            if (!in_array($columnName, ['payment_intent_id', 'stripe_payment_intent_id'], true)) {
                return 0;
            }

            $order = Database::queryOne(
                "SELECT id FROM rest_pedidos WHERE `{$columnName}` = :intent_id LIMIT 1",
                [':intent_id' => $paymentIntentId]
            );

            return (int)($order['id'] ?? 0);
        } catch (\Throwable $exception) {
            error_log('PaymentController::findOrderIdByPaymentIntent ERROR: ' . $exception->getMessage());
            return 0;
        }
    }

    private function markOrderPaidFromWebhook(int $orderId, object $paymentIntent): void
    {
        $userId = (int)($paymentIntent->metadata['user_id'] ?? 0);
        if ($userId <= 0) {
            error_log("Stripe webhook omitido para pedido #{$orderId}: falta user_id");
            return;
        }

        $order = Order::findById($orderId, $userId);
        if (!$order) {
            error_log("Stripe webhook omitido para pedido #{$orderId}: usuario o pedido no coinciden");
            return;
        }

        $intentId = (string)($paymentIntent->id ?? '');
        $storedIntentId = trim((string)($order['payment_intent_id'] ?? $order['stripe_payment_intent_id'] ?? ''));
        $usePoints = (string)($paymentIntent->metadata['use_points'] ?? '0') === '1';
        $baseAmount = (float)($order['total'] ?? $order['subtotal'] ?? 0);
        $quote = (new RewardsService())->quote(
            $userId,
            $baseAmount,
            $usePoints,
            'food',
            is_array($order['items'] ?? null) ? $order['items'] : [],
            'external'
        );
        $expectedCents = (int)round((float)$quote['wallet_total'] * 100);
        $receivedCents = (int)($paymentIntent->amount_received ?: $paymentIntent->amount);

        if (
            $intentId === '' ||
            $storedIntentId === '' ||
            !hash_equals($storedIntentId, $intentId) ||
            strtolower((string)$paymentIntent->currency) !== 'mxn' ||
            $receivedCents !== $expectedCents ||
            (string)$paymentIntent->status !== 'succeeded'
        ) {
            error_log("Stripe webhook omitido para pedido #{$orderId}: intent, moneda, importe o estado no coinciden");
            return;
        }

        Order::updatePaymentMethod($orderId, 'card', $intentId);
    }

    private function paymentIntentIdempotencyKey(
        int $userId,
        int $orderId,
        int $amountCents,
        bool $usePoints,
        string $promoCode,
        int $restaurantId,
        array $pricingItems,
        string $previousIntentId
    ): string {
        $identity = implode('|', [
            $userId,
            $orderId,
            $amountCents,
            $usePoints ? '1' : '0',
            strtoupper($promoCode),
            $restaurantId,
            hash('sha256', json_encode($pricingItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''),
            $previousIntentId,
        ]);

        return 'amare_order_payment_' . hash('sha256', $identity);
    }
}
