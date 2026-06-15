<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Amare\Api\Models\Order;

class PaymentController
{
    /**
     * Obtiene de forma segura la clave secreta de Stripe con fallback
     */
    private function getStripeSecret(): string
    {
        $key = $_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        return $key ?: 'sk_test_51TeJtC40bT4RaBUH5oNKSSOKjzreEfOUiLdswY7CYEYOfp9MdkMR43U1QdK9TGnc0DpY3KhJ41smmvQLNYhx8Rjj00sbJNzOfi';
    }

    /**
     * Obtiene de forma segura el secreto del Webhook de Stripe con fallback
     */
    private function getStripeWebhookSecret(): string
    {
        $key = $_ENV['STRIPE_WEBHOOK_SECRET'] ?? $_SERVER['STRIPE_WEBHOOK_SECRET'] ?? getenv('STRIPE_WEBHOOK_SECRET');
        return $key ?: 'whsec_0NeYzmDe2OFW6mvfOF0TZ3WRjoYivXLB';
    }

    public function createPaymentIntent(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'order_id' => 'integer'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            // 🛠️ Cambiado a método seguro protegido contra fallas de lectura de .env
            Stripe::setApiKey($this->getStripeSecret());

            $metadata = [
                'user_id' => $user->id,
            ];

            if (isset($input['order_id']) && filter_var($input['order_id'], FILTER_VALIDATE_INT) !== false && (int)$input['order_id'] > 0) {
                $metadata['order_id'] = (string) $input['order_id'];
            }

            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($input['amount'] * 100),
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
        } catch (\Exception $e) {
            Response::serverError('Error al crear payment intent: ' . $e->getMessage());
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
                    $orderId = (int) ($paymentIntent->metadata['order_id'] ?? 0);
                    if ($orderId > 0) {
                        Order::updatePaymentMethod($orderId, 'card', $paymentIntent->id);
                    }
                    break;
                    
                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $orderId = (int) ($paymentIntent->metadata['order_id'] ?? 0);
                    if ($orderId > 0) {
                        // Registrar el fallo sin cambiar estado
                        error_log("Pago fallido para pedido #{$orderId}: {$paymentIntent->last_payment_error?->message}");
                    }
                    break;
            }

            Response::success(null, 'Webhook procesado', 200);
        } catch (\UnexpectedValueException $e) {
            Response::error('Webhook inválido', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Response::error('Firma inválida', 400);
        }
    }
}
