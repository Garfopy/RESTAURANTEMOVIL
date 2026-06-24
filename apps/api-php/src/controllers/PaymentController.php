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

class PaymentController
{
    /**
     * Obtiene de forma segura la clave secreta de Stripe con fallback
     */
    private function getStripeSecret(): string
    {
        $key = $_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('STRIPE_SECRET_KEY no configurada');
        }
        return trim($key);
    }

    /**
     * Obtiene de forma segura el secreto del Webhook de Stripe con fallback
     */
    private function getStripeWebhookSecret(): string
    {
        $key = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET');
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('STRIPE_WEBHOOK_SECRET no configurada');
        }
        return trim($key);
    }

    public function createPaymentIntent(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'order_id' => 'integer',
            'restaurante_id' => 'integer',
            'items' => 'array'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $amount = (float)$input['amount'];
            if (!empty($input['items']) && !empty($input['restaurante_id'])) {
                $quote = Order::quote([
                    'restaurante_id' => (int)$input['restaurante_id'],
                    'items' => $input['items'],
                ]);
                $amount = (float)$quote['total'];
            }

            // 🛠️ Cambiado a método seguro protegido contra fallas de lectura de .env
            Stripe::setApiKey($this->getStripeSecret());

            $metadata = [
                'user_id' => $user->id,
            ];

            if (isset($input['order_id']) && filter_var($input['order_id'], FILTER_VALIDATE_INT) !== false && (int)$input['order_id'] > 0) {
                $metadata['order_id'] = (string) $input['order_id'];
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => (int)round($amount * 100),
                'currency' => $input['currency'],
                'metadata' => $metadata,
                'automatic_payment_methods' => [
                    'enabled' => true
                ]
            ]);

            Response::success([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id
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
                        Order::updatePaymentMethod($orderId, 'card', $paymentIntent->id);
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
}
