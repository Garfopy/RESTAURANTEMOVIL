<?php

declare(strict_types=1);

namespace Amare\Api\Services;

use Amare\Api\Config\Database;
use Stripe\PaymentIntent;
use Stripe\Refund;
use Stripe\Stripe;

class PurchasedBalanceRefundService
{
    public function refund(
        int $userId,
        float $amountMxn,
        string $requestKey,
        string $reason,
        ?int $adminUserId = null
    ): array {
        $amountMxn = round($amountMxn, 2);
        if ($userId <= 0 || $amountMxn <= 0 || !preg_match('/^[A-Za-z0-9_-]{12,120}$/', $requestKey)) {
            throw new \InvalidArgumentException('La solicitud de reembolso no es valida.');
        }

        $wallet = (new RewardsService())->getWallet($userId);
        if ($amountMxn > round((float)($wallet['purchased_balance_mxn'] ?? 0), 2)) {
            throw new \DomainException('El reembolso supera el saldo comprado disponible.');
        }

        Stripe::setApiKey(StripeConfig::secretKey());
        $this->ensureTables();
        $remainingCents = (int)round($amountMxn * 100);
        $refundedCents = 0;
        $refunds = [];
        $topups = Database::query(
            "SELECT external_reference, amount_mxn
               FROM amare_wallet_transactions
              WHERE user_id = :user_id
                AND type = 'wallet_topup'
                AND funding_type = 'purchased'
                AND external_reference IS NOT NULL
           ORDER BY created_at DESC, id DESC",
            [':user_id' => $userId]
        );

        foreach ($topups as $topup) {
            if ($remainingCents <= 0) break;
            $intentId = trim((string)($topup['external_reference'] ?? ''));
            if (!str_starts_with($intentId, 'pi_')) continue;

            $intent = PaymentIntent::retrieve(['id' => $intentId, 'expand' => ['latest_charge']]);
            if (
                (int)($intent->metadata['user_id'] ?? 0) !== $userId ||
                (string)($intent->metadata['rewards_action'] ?? '') !== 'wallet_topup' ||
                (bool)$intent->livemode !== StripeConfig::isLiveMode() ||
                (string)$intent->status !== 'succeeded'
            ) {
                continue;
            }

            $charge = $intent->latest_charge;
            $alreadyRefundedCents = is_object($charge) ? (int)($charge->amount_refunded ?? 0) : 0;
            $receivedCents = (int)($intent->amount_received ?: $intent->amount);
            $refundCents = min($remainingCents, max(0, $receivedCents - $alreadyRefundedCents));
            if ($refundCents <= 0) continue;

            $refund = Refund::create([
                'payment_intent' => $intentId,
                'amount' => $refundCents,
                'metadata' => [
                    'reason' => substr($reason, 0, 120),
                    'user_id' => (string)$userId,
                    'admin_user_id' => $adminUserId ? (string)$adminUserId : '',
                    'request_key' => $requestKey,
                ],
            ], [
                'idempotency_key' => 'amare_balance_refund_' . hash(
                    'sha256',
                    $userId . '|' . $intentId . '|' . $refundCents . '|' . $requestKey
                ),
            ]);
            if ((string)($refund->status ?? '') === 'failed') {
                throw new \RuntimeException('Stripe rechazo el reembolso ' . (string)$refund->id);
            }

            $chargeId = is_object($charge) ? (string)($charge->id ?? '') : '';
            $pdo = Database::getInstance();
            $pdo->beginTransaction();
            try {
                (new RewardsService())->applyPurchasedRefund(
                    $pdo,
                    $userId,
                    round($refundCents / 100, 2),
                    'stripe_refund_' . (string)$refund->id,
                    'Reembolso de Saldo Amare comprado'
                );
                if ($chargeId !== '') {
                    Database::rowCount(
                        "INSERT INTO stripe_charge_refund_state
                            (stripe_charge_id, payment_intent_id, user_id, refunded_cents, updated_at)
                         VALUES (:charge_id, :intent_id, :user_id, :refunded_cents, NOW())
                         ON DUPLICATE KEY UPDATE
                            refunded_cents = GREATEST(refunded_cents, VALUES(refunded_cents)), updated_at = NOW()",
                        [
                            ':charge_id' => $chargeId,
                            ':intent_id' => $intentId,
                            ':user_id' => $userId,
                            ':refunded_cents' => $alreadyRefundedCents + $refundCents,
                        ]
                    );
                }
                Database::rowCount(
                    "INSERT INTO stripe_refund_audit
                        (stripe_refund_id, payment_intent_id, user_id, admin_user_id, request_key,
                         amount_mxn, reason, status, created_at, updated_at)
                     VALUES (:refund_id, :intent_id, :user_id, :admin_user_id, :request_key,
                             :amount, :reason, :status, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE status = VALUES(status), updated_at = NOW()",
                    [
                        ':refund_id' => (string)$refund->id,
                        ':intent_id' => $intentId,
                        ':user_id' => $userId,
                        ':admin_user_id' => $adminUserId,
                        ':request_key' => $requestKey,
                        ':amount' => round($refundCents / 100, 2),
                        ':reason' => substr($reason, 0, 255),
                        ':status' => (string)($refund->status ?? 'pending'),
                    ]
                );
                $pdo->commit();
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $exception;
            }

            $remainingCents -= $refundCents;
            $refundedCents += $refundCents;
            $refunds[] = [
                'stripe_refund_id' => (string)$refund->id,
                'payment_intent_id' => $intentId,
                'amount_mxn' => round($refundCents / 100, 2),
                'status' => (string)($refund->status ?? 'pending'),
            ];
        }

        if ($remainingCents > 0) {
            throw new \RuntimeException('No hay cargos Stripe reembolsables suficientes para cubrir el saldo comprado.');
        }

        return ['refunded_mxn' => round($refundedCents / 100, 2), 'refunds' => $refunds];
    }

    private function ensureTables(): void
    {
        $pdo = Database::getInstance();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS stripe_charge_refund_state (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              stripe_charge_id VARCHAR(255) NOT NULL,
              payment_intent_id VARCHAR(255) NULL,
              user_id INT UNSIGNED NULL,
              refunded_cents BIGINT UNSIGNED NOT NULL DEFAULT 0,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id), UNIQUE KEY uq_stripe_refund_charge (stripe_charge_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS stripe_refund_audit (
              id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              stripe_refund_id VARCHAR(255) NOT NULL,
              payment_intent_id VARCHAR(255) NOT NULL,
              user_id INT UNSIGNED NOT NULL,
              admin_user_id INT UNSIGNED NULL,
              request_key VARCHAR(120) NOT NULL,
              amount_mxn DECIMAL(12,2) NOT NULL,
              reason VARCHAR(255) NOT NULL,
              status VARCHAR(40) NOT NULL,
              created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at DATETIME NULL,
              PRIMARY KEY (id), UNIQUE KEY uq_stripe_refund_audit_refund (stripe_refund_id),
              KEY idx_stripe_refund_audit_user (user_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }
}
