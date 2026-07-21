<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Services\RewardsService;
use Amare\Api\Services\StripeConfig;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class RewardsController
{
    private function isValidTopupAmount(float $amount): bool
    {
        $rounded = (int)round($amount);
        return (float)$rounded === $amount && in_array($rounded, RewardsService::quickTopupAmounts(), true);
    }

    private function getStripeSecret(): string
    {
        return StripeConfig::secretKey();
    }

    public function wallet(): void
    {
        $user = AuthMiddleware::authenticate();

        try {
            Response::success((new RewardsService())->getWallet((int)$user->id));
        } catch (\Throwable $exception) {
            error_log('RewardsController::wallet ERROR: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
            Response::serverError('No se pudo cargar tu saldo Amare.');
        }
    }

    public function quote(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $context = strtolower(trim((string)($input['context'] ?? 'food')));
        $amount = (float)($input['amount'] ?? 0);
        $usePoints = !empty($input['use_points']);
        $paymentMode = strtolower(trim((string)($input['payment_mode'] ?? 'wallet')));
        $items = is_array($input['items'] ?? null) ? $input['items'] : [];

        $errors = [];
        if (!in_array($context, ['food', 'gift'], true)) {
            $errors['context'] = ['Contexto de recompensa no valido'];
        }
        if (!in_array($paymentMode, ['wallet', 'external'], true)) {
            $errors['payment_mode'] = ['Modo de pago no valido'];
        }
        if ($amount <= 0) {
            $errors['amount'] = ['El monto debe ser mayor a cero'];
        }
        if ($errors) {
            Response::validationError($errors);
        }

        try {
            Response::success((new RewardsService())->quote((int)$user->id, $amount, $usePoints, $context, $items, $paymentMode));
        } catch (\Throwable $exception) {
            error_log('RewardsController::quote ERROR: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
            Response::serverError('No se pudo cotizar tu saldo Amare.');
        }
    }

    public function createTopupIntent(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();
        $amount = (float)($input['amount'] ?? 0);
        $requestKey = trim((string)($input['request_key'] ?? ''));

        if (!$this->isValidTopupAmount($amount)) {
            Response::validationError(['amount' => ['Selecciona uno de los montos de recarga disponibles']]);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{12,100}$/', $requestKey)) {
            Response::validationError(['request_key' => ['La referencia de recarga no es valida']]);
        }

        try {
            Stripe::setApiKey($this->getStripeSecret());

            $paymentIntent = PaymentIntent::create([
                'amount' => (int)round($amount * 100),
                'currency' => 'mxn',
                'metadata' => [
                    'user_id' => (string)$user->id,
                    'rewards_action' => 'wallet_topup',
                    'wallet_topup_amount' => (string)((int)round($amount)),
                    'request_key' => $requestKey,
                ],
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ], [
                'idempotency_key' => 'amare_wallet_topup_' . hash('sha256', (string)$user->id . '|' . $requestKey),
            ]);

            Response::success([
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'amount_mxn' => (int)round($amount),
            ], 'Recarga preparada con Stripe');
        } catch (\Throwable $exception) {
            error_log('RewardsController::createTopupIntent ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo iniciar la recarga de Saldo Amare.');
        }
    }

    public function confirmTopup(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();
        $intentId = trim((string)($input['payment_intent_id'] ?? ''));

        if ($intentId === '') {
            Response::validationError(['payment_intent_id' => ['El payment intent es obligatorio']]);
        }

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intent = PaymentIntent::retrieve($intentId);

            $metadataUserId = (int)($intent->metadata['user_id'] ?? 0);
            $action = (string)($intent->metadata['rewards_action'] ?? '');
            $amountMxn = round(((int)($intent->amount_received ?: $intent->amount)) / 100, 2);

            if ($metadataUserId !== (int)$user->id || $action !== 'wallet_topup') {
                Response::forbidden('Esta recarga no pertenece a tu cuenta.');
            }
            if ($intent->status !== 'succeeded') {
                Response::error('Stripe aun no confirma el pago de esta recarga.', 409);
            }
            if ((bool)$intent->livemode !== StripeConfig::isLiveMode()) {
                Response::error('El entorno de la recarga no coincide con el servidor.', 409);
            }
            if (!$this->isValidTopupAmount($amountMxn)) {
                Response::error('El importe no corresponde a una opcion de recarga vigente.', 409);
            }

            $rewards = new RewardsService();
            $rewards->getWallet((int)$user->id);
            $pdo = Database::getInstance();
            $pdo->beginTransaction();
            $wallet = $rewards->applyTopup(
                $pdo,
                (int)$user->id,
                $amountMxn,
                $intent->id,
                'Recarga de Saldo Amare con Stripe'
            );
            $pdo->commit();

            Response::success([
                'wallet' => $wallet,
                'payment_intent_id' => $intent->id,
                'amount_mxn' => $amountMxn,
            ], 'Saldo Amare recargado correctamente.');
        } catch (\Throwable $exception) {
            if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RewardsController::confirmTopup ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo acreditar la recarga de Saldo Amare.');
        }
    }

    public function redeem(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $pointsCost = (int)($input['points_cost'] ?? 0);
        $balanceCreditMxn = (int)($input['balance_credit_mxn'] ?? 0);

        if ($pointsCost <= 0 || $balanceCreditMxn <= 0) {
            Response::validationError(['redeem' => ['Selecciona un canje valido']]);
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();
            $wallet = (new RewardsService())->redeemToBalance(
                $pdo,
                (int)$user->id,
                $pointsCost,
                $balanceCreditMxn
            );
            $pdo->commit();

            Response::success([
                'wallet' => $wallet,
                'points_cost' => $pointsCost,
                'balance_credit_mxn' => $balanceCreditMxn,
            ], 'Tus puntos ya se canjearon por Saldo Amare.');
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('RewardsController::redeem ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo canjear tus puntos por Saldo Amare.');
        }
    }
}
