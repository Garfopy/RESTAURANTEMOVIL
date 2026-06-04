<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Stripe\Stripe;
use Stripe\PaymentIntent;
use Stripe\Webhook;
use Stripe\Endpoint;

class PaymentController
{
    public function createPaymentIntent(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'amount' => 'required|numeric',
            'currency' => 'required|string',
            'order_id' => 'required|integer'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

            $paymentIntent = PaymentIntent::create([
                'amount' => (int)($input['amount'] * 100),
                'currency' => $input['currency'],
                'metadata' => [
                    'user_id' => $user->id,
                    'order_id' => $input['order_id']
                ],
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
            Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);
            
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $_ENV['STRIPE_WEBHOOK_SECRET']
            );

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    // Actualizar estado del pedido a pagado
                    // Aquí iría la lógica para actualizar la orden
                    break;
                    
                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    // Manejar fallo de pago
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