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
use Stripe\Charge;
use Amare\Api\Models\InvoiceRequest;
use Amare\Api\Models\Order;
use Amare\Api\Models\Promotion;
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
            'order_id' => 'required|integer'
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
                $this->persistPendingInvoiceRequest(
                    $order,
                    (int)$user->id,
                    is_array($input['invoice_request'] ?? null) ? $input['invoice_request'] : null
                );
            } else {
                Response::validationError([
                    'order_id' => ['Envia un pedido valido para calcular el total en el servidor'],
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
            if (StripeConfig::isBelowMinimumPaymentMxn($amount)) {
                Response::error(
                    'El pago con tarjeta debe ser de al menos $10.00 MXN. Ajusta tu compra o elige otro método de pago.',
                    422,
                    'PAYMENT_AMOUNT_BELOW_MINIMUM'
                );
            }

            // 🛠️ Cambiado a método seguro protegido contra fallas de lectura de .env
            Stripe::setApiKey($this->getStripeSecret());

            if ($previousIntentId !== '') {
                try {
                    $existingIntent = PaymentIntent::retrieve($previousIntentId);
                    $existingUserId = (int)($existingIntent->metadata['user_id'] ?? 0);
                    $existingOrderId = (int)($existingIntent->metadata['order_id'] ?? 0);
                    $existingUsePoints = (string)($existingIntent->metadata['use_points'] ?? '0') === '1';
                    $existingPromoCode = strtoupper(trim((string)($existingIntent->metadata['promo_code'] ?? '')));
                    $expectedCents = (int)round($amount * 100);
                    $matchesCurrentPayment =
                        (string)$existingIntent->status !== 'canceled' &&
                        $existingUserId === (int)$user->id &&
                        $existingOrderId === $orderId &&
                        strtolower((string)$existingIntent->currency) === 'mxn' &&
                        (int)$existingIntent->amount === $expectedCents &&
                        $existingUsePoints === $usePoints &&
                        $existingPromoCode === strtoupper($promoCode) &&
                        (bool)$existingIntent->livemode === StripeConfig::isLiveMode();

                    if ($matchesCurrentPayment) {
                        Response::success([
                            'client_secret' => $existingIntent->client_secret,
                            'payment_intent_id' => $existingIntent->id,
                            'amount_mxn' => round((int)$existingIntent->amount / 100, 2),
                            'status' => (string)$existingIntent->status,
                            'use_points' => (string)($existingIntent->metadata['use_points'] ?? '0') === '1',
                        ], 'Payment intent recuperado');
                    }

                    if ((string)$existingIntent->status === 'succeeded') {
                        Response::error(
                            'El pedido ya tiene un pago exitoso con condiciones distintas. Contacta a soporte.',
                            409,
                            'PAID_INTENT_MISMATCH'
                        );
                    }

                    if (in_array((string)$existingIntent->status, ['requires_payment_method', 'requires_confirmation', 'requires_action', 'processing'], true)) {
                        $existingIntent->cancel();
                    }
                } catch (\Stripe\Exception\ApiErrorException $exception) {
                    error_log('PaymentController::createPaymentIntent existing intent ERROR: ' . $exception->getMessage());
                    Response::error(
                        'No se pudo validar el intento anterior. No se genero un segundo cobro; contacta a soporte.',
                        409,
                        'PAYMENT_INTENT_REVIEW_REQUIRED'
                    );
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
                    (int)($order['restaurante_id'] ?? 0),
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

    public function adminReconcilePayment(): void
    {
        $admin = AuthMiddleware::requireAdmin();
        $input = ValidationMiddleware::getAllInput();
        $intentId = trim((string)($input['payment_intent_id'] ?? ''));
        if (!preg_match('/^pi_[A-Za-z0-9_]+$/', $intentId)) {
            Response::validationError(['payment_intent_id' => ['El PaymentIntent no es valido.']]);
        }

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intent = PaymentIntent::retrieve($intentId);
            if ((bool)$intent->livemode !== StripeConfig::isLiveMode()) {
                Response::error('El modo live/test no coincide con el servidor.', 409);
            }
            if ((string)$intent->status !== 'succeeded') {
                Response::error('Stripe todavia no confirma este pago como exitoso.', 409);
            }

            $result = $this->reconcileSucceededPaymentIntent($intent);
            error_log('Stripe reconciliacion manual admin=' . (int)($admin->id ?? 0) . ' intent=' . $intentId);
            Response::success($result, 'Pago reconciliado correctamente.');
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('PaymentController::adminReconcilePayment STRIPE ERROR: ' . $exception->getMessage());
            Response::error('Stripe no pudo recuperar el pago.', 409);
        } catch (\Throwable $exception) {
            error_log('PaymentController::adminReconcilePayment ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo reconciliar el pago.');
        }
    }

    public function webhook(): void
    {
        $payload = file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $eventId = '';
        
        try {
            // 🛠️ Cambiado a método seguro
            Stripe::setApiKey($this->getStripeSecret());
            
            // 🛠️ Cambiado a método seguro para el webhook secret
            $event = Webhook::constructEvent(
                $payload,
                $sig_header,
                $this->getStripeWebhookSecret()
            );

            $eventId = (string)$event->id;
            $objectId = (string)($event->data->object->id ?? '');
            if (!$this->beginWebhookEvent($eventId, (string)$event->type, $objectId)) {
                Response::success(null, 'Webhook ya procesado', 200);
            }

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    if ((bool)$paymentIntent->livemode !== StripeConfig::isLiveMode()) {
                        throw new \RuntimeException('El modo live/test del PaymentIntent no coincide con el servidor');
                    }
                    $this->reconcileSucceededPaymentIntent($paymentIntent);
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
                        $this->markOrderPaymentInterrupted($paymentIntent, 'failed');
                    }
                    if ($giftOrderId > 0) {
                        Database::rowCount(
                            "UPDATE social_gift_orders SET status = 'pago_fallido', updated_at = NOW()
                              WHERE id = :id AND stripe_payment_intent_id = :intent_id AND status = 'pendiente_pago'",
                            [':id' => $giftOrderId, ':intent_id' => $paymentIntent->id]
                        );
                    }
                    $this->recordPaymentIncident(
                        'payment_failed',
                        (string)$paymentIntent->id,
                        (string)$paymentIntent->id,
                        ['message' => (string)($paymentIntent->last_payment_error?->message ?? '')]
                    );
                    break;

                case 'payment_intent.canceled':
                    $paymentIntent = $event->data->object;
                    $this->markOrderPaymentInterrupted($paymentIntent, 'cancelled');
                    $this->recordPaymentIncident('payment_canceled', (string)$paymentIntent->id, (string)$paymentIntent->id);
                    break;

                case 'charge.refunded':
                    $charge = $event->data->object;
                    $this->handleChargeRefunded($charge);
                    $this->recordPaymentIncident(
                        'charge_refunded',
                        (string)$charge->id,
                        (string)($charge->payment_intent ?? ''),
                        ['amount_refunded' => (int)($charge->amount_refunded ?? 0)]
                    );
                    break;

                case 'charge.dispute.created':
                    $chargeId = (string)($event->data->object->charge ?? '');
                    if ($chargeId !== '') {
                        $charge = Charge::retrieve($chargeId);
                        $this->markOrderDisputed((string)($charge->payment_intent ?? ''));
                    }
                    error_log('Stripe disputa recibida para charge ' . $chargeId);
                    $this->recordPaymentIncident(
                        'charge_dispute_created',
                        (string)($event->data->object->id ?? $chargeId),
                        '',
                        ['charge_id' => $chargeId, 'amount' => (int)($event->data->object->amount ?? 0)]
                    );
                    break;
            }

            $this->completeWebhookEvent($eventId);
            Response::success(null, 'Webhook procesado', 200);
        } catch (\UnexpectedValueException $e) {
            Response::error('Webhook inválido', 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Response::error('Firma inválida', 400);
        } catch (\Throwable $e) {
            if ($eventId !== '') {
                $this->failWebhookEvent($eventId, $e->getMessage());
            }
            error_log('PaymentController::webhook ERROR: ' . $e->getMessage());
            Response::serverError('No se pudo procesar el webhook.');
        }
    }

    private function reconcileSucceededPaymentIntent(object $paymentIntent): array
    {
        if ((bool)($paymentIntent->livemode ?? false) !== StripeConfig::isLiveMode()) {
            throw new \RuntimeException('El modo live/test del PaymentIntent no coincide con el servidor.');
        }
        if ((string)($paymentIntent->status ?? '') !== 'succeeded') {
            throw new \RuntimeException('Stripe todavia no confirma este pago.');
        }

        $rewardsAction = (string)($paymentIntent->metadata['rewards_action'] ?? '');
        $orderId = (int)($paymentIntent->metadata['order_id'] ?? 0);
        $storedOrderId = $this->findOrderIdByPaymentIntent((string)$paymentIntent->id);
        if ($storedOrderId > 0) $orderId = $storedOrderId;
        $giftOrderId = (int)($paymentIntent->metadata['gift_order_id'] ?? 0);
        $coverId = (int)($paymentIntent->metadata['social_account_cover_id'] ?? 0);
        $coverPayerId = (int)($paymentIntent->metadata['payer_user_id'] ?? 0);

        if ($rewardsAction === 'wallet_topup') {
            $topupUserId = (int)($paymentIntent->metadata['user_id'] ?? 0);
            $topupAmount = round(((int)($paymentIntent->amount_received ?: $paymentIntent->amount)) / 100, 2);
            if ($topupUserId <= 0) throw new \RuntimeException('La recarga no contiene usuario valido.');
            $rewards = new RewardsService();
            $rewards->getWallet($topupUserId);
            $pdo = Database::getInstance();
            $pdo->beginTransaction();
            try {
                $rewards->applyTopup(
                    $pdo,
                    $topupUserId,
                    $topupAmount,
                    (string)$paymentIntent->id,
                    'Recarga de Saldo Amare con Stripe'
                );
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }
        }

        if ($orderId > 0 && !$this->markOrderPaidFromWebhook($orderId, $paymentIntent)) {
            throw new \RuntimeException("No se pudo conciliar el pedido #{$orderId}.");
        }
        if ($giftOrderId > 0) {
            $gift = Database::queryOne(
                'SELECT gift_precio, moneda, sender_user_id FROM social_gift_orders WHERE id = :id AND stripe_payment_intent_id = :intent_id',
                [':id' => $giftOrderId, ':intent_id' => $paymentIntent->id]
            );
            $expectedCents = $gift ? (int)round((float)$gift['gift_precio'] * 100) : -1;
            if (!$gift || $expectedCents !== (int)$paymentIntent->amount || strtolower((string)$paymentIntent->currency) !== strtolower((string)$gift['moneda'])) {
                throw new \RuntimeException("El pago del regalo #{$giftOrderId} no coincide con la operacion guardada.");
            }
            (new SocialController())->reconcileGiftFromWebhook(
                $giftOrderId,
                (int)$gift['sender_user_id'],
                (string)$paymentIntent->id
            );
        }
        if ($coverId > 0 && $coverPayerId > 0) {
            (new SocialController())->reconcileAccountCoverFromWebhook($coverId, $coverPayerId);
        }

        return [
            'payment_intent_id' => (string)$paymentIntent->id,
            'order_id' => $orderId ?: null,
            'gift_id' => $giftOrderId ?: null,
            'cover_id' => $coverId ?: null,
            'wallet_topup' => $rewardsAction === 'wallet_topup',
        ];
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

    private function markOrderPaidFromWebhook(int $orderId, object $paymentIntent): bool
    {
        $userId = (int)($paymentIntent->metadata['user_id'] ?? 0);
        if ($userId <= 0) {
            error_log("Stripe webhook omitido para pedido #{$orderId}: falta user_id");
            return false;
        }

        $order = Order::findById($orderId, $userId);
        if (!$order) {
            error_log("Stripe webhook omitido para pedido #{$orderId}: usuario o pedido no coinciden");
            return false;
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
            return false;
        }

        if ((bool)($paymentIntent->livemode ?? false) !== StripeConfig::isLiveMode()) {
            error_log("Stripe webhook omitido para pedido #{$orderId}: modo live/test inconsistente");
            return false;
        }

        $method = $this->stripePaymentMethod($paymentIntent);
        $rewards = new RewardsService();
        $rewards->getWallet($userId);
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            if (!Order::updatePaymentMethod($orderId, $method, $intentId)) {
                throw new \RuntimeException("No se pudo marcar pagado el pedido #{$orderId}.");
            }
            $this->updateOrderStripeState($orderId, 'succeeded');
            $reward = $rewards->awardPoints(
                $pdo,
                $userId,
                $baseAmount,
                $usePoints,
                'food',
                'order',
                $orderId,
                'Puntos generados por compra de alimentos'
            );
            if (empty($reward['already_applied'])) {
                Order::applyExternalRewardsSummary($orderId, $reward, true);
            }
            Promotion::recordUsageForOrder($pdo, $userId, $order);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }

        if (($order['tipo_pedido'] ?? null) === 'eat_in') {
            try {
                Order::ensureExitPass($orderId, $userId);
            } catch (\Throwable $exception) {
                error_log("Stripe webhook no pudo generar QR para pedido #{$orderId}: " . $exception->getMessage());
                throw $exception;
            }
        }

        $this->fulfillPendingInvoiceRequest($order, $userId, $method);
        return true;
    }

    private function ensurePendingInvoiceTable(): void
    {
        Database::getInstance()->exec(
            "CREATE TABLE IF NOT EXISTS stripe_pending_invoice_requests (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              order_id INT UNSIGNED NOT NULL,
              user_id INT UNSIGNED NOT NULL,
              request_json JSON NOT NULL,
              status VARCHAR(24) NOT NULL DEFAULT 'pending',
              invoice_request_id INT UNSIGNED NULL,
              last_error TEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NULL,
              processed_at DATETIME NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_stripe_pending_invoice_order (order_id),
              KEY idx_stripe_pending_invoice_status (status, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function persistPendingInvoiceRequest(array $order, int $userId, ?array $invoiceRequest): void
    {
        if (!InvoiceRequest::isRequired($invoiceRequest)) {
            return;
        }

        $orderId = (int)($order['id'] ?? 0);
        $restaurantId = (int)($order['restaurante_id'] ?? 0);
        if ($orderId <= 0 || $userId <= 0) {
            throw new \InvalidArgumentException('No se pudo asociar la solicitud de factura al pedido.');
        }

        InvoiceRequest::validateForPayment($restaurantId, $invoiceRequest);
        $encoded = json_encode($invoiceRequest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            throw new \InvalidArgumentException('Los datos de facturacion no se pudieron guardar.');
        }

        $this->ensurePendingInvoiceTable();
        Database::rowCount(
            "INSERT INTO stripe_pending_invoice_requests
                (order_id, user_id, request_json, status, created_at, updated_at)
             VALUES (:order_id, :user_id, :request_json, 'pending', NOW(), NOW())
             ON DUPLICATE KEY UPDATE
                user_id = VALUES(user_id),
                request_json = IF(status = 'processed', request_json, VALUES(request_json)),
                status = IF(status = 'processed', status, 'pending'),
                last_error = IF(status = 'processed', last_error, NULL),
                updated_at = NOW()",
            [
                ':order_id' => $orderId,
                ':user_id' => $userId,
                ':request_json' => $encoded,
            ]
        );
    }

    public function fulfillPendingInvoiceRequest(array $order, int $userId, string $paymentMethod): void
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId <= 0 || $userId <= 0) {
            return;
        }

        $this->ensurePendingInvoiceTable();
        $pending = Database::queryOne(
            'SELECT * FROM stripe_pending_invoice_requests
              WHERE order_id = :order_id AND user_id = :user_id
              LIMIT 1',
            [':order_id' => $orderId, ':user_id' => $userId]
        );
        if (!$pending || ($pending['status'] ?? '') === 'processed') {
            return;
        }

        $claimed = Database::rowCount(
            "UPDATE stripe_pending_invoice_requests
                SET status = 'processing', last_error = NULL, updated_at = NOW()
              WHERE order_id = :order_id AND user_id = :user_id
                AND status IN ('pending', 'failed')",
            [':order_id' => $orderId, ':user_id' => $userId]
        );
        if ($claimed === 0) {
            return;
        }

        try {
            $request = json_decode((string)$pending['request_json'], true, 512, JSON_THROW_ON_ERROR);
            $invoice = InvoiceRequest::createFromPayment([
                'restaurante_id' => (int)($order['restaurante_id'] ?? 0),
                'pedido_id' => $orderId,
                'consumo_id' => $order['consumo_id'] ?? null,
                'mesa_id' => $order['mesa_id'] ?? null,
                'mobile_usuario_id' => $userId,
                'solicitado_por_usuario_id' => $userId,
                'origen' => 'cliente',
                'scope' => 'pedido',
                'monto' => (float)($order['total'] ?? $order['subtotal'] ?? 0),
                'metodo_pago' => $paymentMethod,
            ], is_array($request) ? $request : null);

            Database::rowCount(
                "UPDATE stripe_pending_invoice_requests
                    SET status = 'processed', invoice_request_id = :invoice_id,
                        last_error = NULL, processed_at = NOW(), updated_at = NOW()
                  WHERE order_id = :order_id",
                [':invoice_id' => $invoice['id'] ?? null, ':order_id' => $orderId]
            );
        } catch (\Throwable $exception) {
            Database::rowCount(
                "UPDATE stripe_pending_invoice_requests
                    SET status = 'failed', last_error = :error, updated_at = NOW()
                  WHERE order_id = :order_id",
                [':error' => substr($exception->getMessage(), 0, 2000), ':order_id' => $orderId]
            );
            throw $exception;
        }
    }

    private function stripePaymentMethod(object $paymentIntent): string
    {
        try {
            $charge = $paymentIntent->latest_charge ?? null;
            if (is_string($charge) && $charge !== '') {
                $charge = Charge::retrieve($charge);
            }
            $walletType = strtolower((string)($charge?->payment_method_details?->card?->wallet?->type ?? ''));
            if ($walletType === 'apple_pay') return 'apple_pay';
            if ($walletType === 'google_pay') return 'google_pay';
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('PaymentController::stripePaymentMethod ERROR: ' . $exception->getMessage());
        }

        return 'card';
    }

    private function recordPaymentIncident(string $type, string $objectId, string $paymentIntentId, array $details = []): void
    {
        Database::getInstance()->exec(
            "CREATE TABLE IF NOT EXISTS stripe_payment_incidents (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              incident_type VARCHAR(80) NOT NULL,
              stripe_object_id VARCHAR(255) NOT NULL,
              payment_intent_id VARCHAR(255) NULL,
              details_json TEXT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_stripe_payment_incident (incident_type, stripe_object_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        Database::rowCount(
            "INSERT INTO stripe_payment_incidents
                (incident_type, stripe_object_id, payment_intent_id, details_json, created_at, updated_at)
             VALUES (:type, :object_id, :intent_id, :details, NOW(), NOW())
             ON DUPLICATE KEY UPDATE details_json = VALUES(details_json), updated_at = NOW()",
            [
                ':type' => $type,
                ':object_id' => $objectId,
                ':intent_id' => $paymentIntentId ?: null,
                ':details' => json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]
        );
    }

    private function ensureWebhookEventsTable(): void
    {
        Database::getInstance()->exec(
            "CREATE TABLE IF NOT EXISTS stripe_webhook_events (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              stripe_event_id VARCHAR(255) NOT NULL,
              event_type VARCHAR(120) NOT NULL,
              object_id VARCHAR(255) NULL,
              status VARCHAR(24) NOT NULL DEFAULT 'processing',
              attempts INT UNSIGNED NOT NULL DEFAULT 1,
              last_error TEXT NULL,
              received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              processed_at DATETIME NULL,
              updated_at DATETIME NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_stripe_webhook_event (stripe_event_id),
              KEY idx_stripe_webhook_status (status, updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    private function beginWebhookEvent(string $eventId, string $eventType, string $objectId): bool
    {
        $this->ensureWebhookEventsTable();
        $existing = Database::queryOne(
            'SELECT status FROM stripe_webhook_events WHERE stripe_event_id = :event_id LIMIT 1',
            [':event_id' => $eventId]
        );
        if (($existing['status'] ?? '') === 'processed') return false;

        if ($existing) {
            Database::rowCount(
                "UPDATE stripe_webhook_events
                    SET status = 'processing', attempts = attempts + 1, last_error = NULL, updated_at = NOW()
                  WHERE stripe_event_id = :event_id",
                [':event_id' => $eventId]
            );
            return true;
        }

        Database::rowCount(
            "INSERT INTO stripe_webhook_events
                (stripe_event_id, event_type, object_id, status, attempts, received_at, updated_at)
             VALUES (:event_id, :event_type, :object_id, 'processing', 1, NOW(), NOW())",
            [':event_id' => $eventId, ':event_type' => $eventType, ':object_id' => $objectId ?: null]
        );
        return true;
    }

    private function completeWebhookEvent(string $eventId): void
    {
        Database::rowCount(
            "UPDATE stripe_webhook_events
                SET status = 'processed', processed_at = NOW(), last_error = NULL, updated_at = NOW()
              WHERE stripe_event_id = :event_id",
            [':event_id' => $eventId]
        );
    }

    private function failWebhookEvent(string $eventId, string $message): void
    {
        try {
            Database::rowCount(
                "UPDATE stripe_webhook_events
                    SET status = 'failed', last_error = :error, updated_at = NOW()
                  WHERE stripe_event_id = :event_id",
                [':event_id' => $eventId, ':error' => substr($message, 0, 2000)]
            );
        } catch (\Throwable $ignored) {
        }
    }

    private function markOrderPaymentInterrupted(object $paymentIntent, string $reason): void
    {
        $orderId = (int)($paymentIntent->metadata['order_id'] ?? 0);
        if ($orderId <= 0) $orderId = $this->findOrderIdByPaymentIntent((string)($paymentIntent->id ?? ''));
        if ($orderId > 0) {
            $message = (string)($paymentIntent->last_payment_error?->message ?? '');
            $this->updateOrderStripeState($orderId, $reason, $message);
            error_log("Stripe pago {$reason} para pedido #{$orderId}");
        }
    }

    private function handleChargeRefunded(object $charge): void
    {
        $paymentIntentId = (string)($charge->payment_intent ?? '');
        if ($paymentIntentId === '') return;
        $intent = PaymentIntent::retrieve($paymentIntentId);
        $orderId = $this->findOrderIdByPaymentIntent($paymentIntentId);
        if ($orderId > 0) {
            $refundedCents = max(0, (int)($charge->amount_refunded ?? 0));
            $chargedCents = max(0, (int)($charge->amount ?? $intent->amount_received ?? $intent->amount ?? 0));
            $status = $chargedCents > 0 && $refundedCents < $chargedCents
                ? 'partially_refunded'
                : 'refunded';
            $this->updateOrderStripeState($orderId, $status, null, $refundedCents);
        }
        if ((string)($intent->metadata['rewards_action'] ?? '') !== 'wallet_topup') {
            error_log('Stripe reembolso confirmado para PaymentIntent ' . $paymentIntentId);
            return;
        }

        $userId = (int)($intent->metadata['user_id'] ?? 0);
        $chargeId = (string)($charge->id ?? '');
        $cumulativeCents = (int)($charge->amount_refunded ?? 0);
        if ($userId <= 0 || $chargeId === '' || $cumulativeCents <= 0) return;

        $rewards = new RewardsService();
        $rewards->getWallet($userId);
        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $statement = $pdo->prepare(
                'SELECT refunded_cents FROM stripe_charge_refund_state WHERE stripe_charge_id = :charge_id LIMIT 1 FOR UPDATE'
            );
            $statement->execute([':charge_id' => $chargeId]);
            $previousCents = (int)($statement->fetchColumn() ?: 0);
            $deltaCents = max(0, $cumulativeCents - $previousCents);

            if ($deltaCents > 0) {
                $refundResult = $rewards->applyPurchasedRefund(
                    $pdo,
                    $userId,
                    round($deltaCents / 100, 2),
                    'stripe_refund_' . $chargeId . '_' . $cumulativeCents,
                    'Reembolso de recarga de Saldo Amare',
                    true
                );
                if ((float)($refundResult['unapplied_refund_mxn'] ?? 0) > 0) {
                    error_log(
                        'Stripe reembolso excede saldo comprado disponible user=' . $userId .
                        ' amount=' . (float)$refundResult['unapplied_refund_mxn']
                    );
                }
            }

            Database::rowCount(
                "INSERT INTO stripe_charge_refund_state
                    (stripe_charge_id, payment_intent_id, user_id, refunded_cents, updated_at)
                 VALUES (:charge_id, :intent_id, :user_id, :refunded_cents, NOW())
                 ON DUPLICATE KEY UPDATE
                    payment_intent_id = VALUES(payment_intent_id),
                    user_id = VALUES(user_id),
                    refunded_cents = GREATEST(refunded_cents, VALUES(refunded_cents)),
                    updated_at = NOW()",
                [
                    ':charge_id' => $chargeId,
                    ':intent_id' => $paymentIntentId,
                    ':user_id' => $userId,
                    ':refunded_cents' => $cumulativeCents,
                ]
            );
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    private function markOrderDisputed(string $paymentIntentId): void
    {
        $orderId = $this->findOrderIdByPaymentIntent($paymentIntentId);
        if ($orderId > 0) {
            $this->updateOrderStripeState($orderId, 'disputed', 'Stripe recibio una disputa para este pago.', null, true);
        }
    }

    private function updateOrderStripeState(
        int $orderId,
        string $status,
        ?string $error = null,
        ?int $refundedCents = null,
        bool $disputed = false
    ): void {
        $columns = Database::query(
            "SELECT COLUMN_NAME
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'rest_pedidos'
                AND COLUMN_NAME IN ('stripe_payment_status', 'stripe_payment_error', 'stripe_refunded_cents', 'stripe_disputed_at', 'updated_at')"
        );
        $available = array_column($columns, 'COLUMN_NAME');
        if (!in_array('stripe_payment_status', $available, true)) {
            error_log('Falta ejecutar la migracion 083_add_stripe_payment_state_to_orders.sql');
            return;
        }

        $fields = ['stripe_payment_status = :status'];
        $params = [':status' => substr($status, 0, 30), ':order_id' => $orderId];
        if (in_array('stripe_payment_error', $available, true)) {
            $fields[] = 'stripe_payment_error = :error';
            $params[':error'] = $error === null || $error === '' ? null : substr($error, 0, 500);
        }
        if ($refundedCents !== null && in_array('stripe_refunded_cents', $available, true)) {
            $fields[] = 'stripe_refunded_cents = GREATEST(stripe_refunded_cents, :refunded_cents)';
            $params[':refunded_cents'] = max(0, $refundedCents);
        }
        if ($disputed && in_array('stripe_disputed_at', $available, true)) {
            $fields[] = 'stripe_disputed_at = COALESCE(stripe_disputed_at, NOW())';
        }
        if (in_array('updated_at', $available, true)) {
            $fields[] = 'updated_at = NOW()';
        }

        Database::rowCount(
            'UPDATE rest_pedidos SET ' . implode(', ', $fields) . ' WHERE id = :order_id',
            $params
        );
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
