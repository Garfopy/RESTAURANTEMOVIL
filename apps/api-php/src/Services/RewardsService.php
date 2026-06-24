<?php

declare(strict_types=1);

namespace Amare\Api\Services;

use Amare\Api\Config\Database;
use PDO;

class RewardsService
{
    public const DEMO_BALANCE_MXN = 500.00;
    public const DISCOUNT_RATE = 0.10;
    public const POINTS_PER_MXN_VALUE = 10;
    public const MXN_PER_POINT_EARNED = 10;

    public function getWallet(int $userId, int $limit = 10): array
    {
        $pdo = Database::getInstance();
        $wallet = $this->ensureWallet($pdo, $userId);
        $transactions = $this->recentTransactions($pdo, (int)$wallet['id'], $limit);

        return $this->walletResponse($wallet, $transactions);
    }

    public function quote(int $userId, float $amount, bool $usePoints = false, string $context = 'food'): array
    {
        $pdo = Database::getInstance();
        $wallet = $this->ensureWallet($pdo, $userId);
        return $this->buildQuote($wallet, $amount, $usePoints, $context);
    }

    public function charge(
        PDO $pdo,
        int $userId,
        float $amount,
        bool $usePoints,
        string $context,
        string $referenceType,
        int $referenceId,
        string $description
    ): array {
        $wallet = $this->ensureWallet($pdo, $userId, true);
        $quote = $this->buildQuote($wallet, $amount, $usePoints, $context);

        if (!$quote['can_pay']) {
            throw new \DomainException('Tu saldo Amare no alcanza para cubrir este pago.');
        }

        $newBalance = round((float)$wallet['balance_mxn'] - (float)$quote['wallet_total'], 2);
        $newPoints = max(0, (int)$wallet['points'] - (int)$quote['points_redeemed'] + (int)$quote['points_earned']);

        $stmt = $pdo->prepare(
            'UPDATE amare_wallets
                SET balance_mxn = :balance, points = :points, updated_at = NOW()
              WHERE id = :id'
        );
        $stmt->execute([
            ':balance' => $newBalance,
            ':points' => $newPoints,
            ':id' => (int)$wallet['id'],
        ]);

        $this->insertTransaction($pdo, [
            'wallet_id' => (int)$wallet['id'],
            'user_id' => $userId,
            'type' => 'wallet_payment',
            'context' => $context,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'amount_mxn' => -1 * (float)$quote['wallet_total'],
            'points_delta' => (int)$quote['points_earned'] - (int)$quote['points_redeemed'],
            'balance_after_mxn' => $newBalance,
            'points_after' => $newPoints,
            'description' => $description,
            'metadata_json' => json_encode($quote, JSON_UNESCAPED_UNICODE),
        ]);

        $quote['balance_after_mxn'] = $newBalance;
        $quote['points_after'] = $newPoints;

        return $quote;
    }

    private function ensureWallet(PDO $pdo, int $userId, bool $forUpdate = false): array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $stmt = $pdo->prepare('SELECT * FROM amare_wallets WHERE user_id = :user_id LIMIT 1' . $suffix);
        $stmt->execute([':user_id' => $userId]);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($wallet) {
            return $wallet;
        }

        $insert = $pdo->prepare(
            'INSERT INTO amare_wallets (user_id, balance_mxn, points, simulated_balance, created_at, updated_at)
             VALUES (:user_id, :balance, 0, 1, NOW(), NOW())'
        );
        $insert->execute([
            ':user_id' => $userId,
            ':balance' => self::DEMO_BALANCE_MXN,
        ]);

        $walletId = (int)$pdo->lastInsertId();
        $wallet = [
            'id' => $walletId,
            'user_id' => $userId,
            'balance_mxn' => self::DEMO_BALANCE_MXN,
            'points' => 0,
            'simulated_balance' => 1,
        ];

        $this->insertTransaction($pdo, [
            'wallet_id' => $walletId,
            'user_id' => $userId,
            'type' => 'demo_credit',
            'context' => 'demo',
            'reference_type' => null,
            'reference_id' => null,
            'amount_mxn' => self::DEMO_BALANCE_MXN,
            'points_delta' => 0,
            'balance_after_mxn' => self::DEMO_BALANCE_MXN,
            'points_after' => 0,
            'description' => 'Saldo simulado inicial',
            'metadata_json' => null,
        ]);

        return $wallet;
    }

    private function buildQuote(array $wallet, float $amount, bool $usePoints, string $context): array
    {
        $original = round(max(0, $amount), 2);
        $discount = round($original * self::DISCOUNT_RATE, 2);
        $afterDiscount = max(0, round($original - $discount, 2));
        $availablePoints = (int)($wallet['points'] ?? 0);
        $maxPointValue = $usePoints ? floor($availablePoints / self::POINTS_PER_MXN_VALUE) : 0;
        $pointsDiscount = round(min($afterDiscount, $maxPointValue), 2);
        $pointsRedeemed = (int)($pointsDiscount * self::POINTS_PER_MXN_VALUE);
        $walletTotal = max(0, round($afterDiscount - $pointsDiscount, 2));
        $pointsEarned = (int)floor($walletTotal / self::MXN_PER_POINT_EARNED);
        $balance = round((float)($wallet['balance_mxn'] ?? 0), 2);

        return [
            'context' => $context,
            'original_total' => $original,
            'discount_rate' => self::DISCOUNT_RATE,
            'discount_amount' => $discount,
            'points_redeemed' => $pointsRedeemed,
            'points_discount' => $pointsDiscount,
            'wallet_total' => $walletTotal,
            'points_earned' => $pointsEarned,
            'can_pay' => $balance >= $walletTotal,
            'balance_mxn' => $balance,
            'points' => $availablePoints,
            'points_value_mxn' => floor($availablePoints / self::POINTS_PER_MXN_VALUE),
            'simulated' => (bool)($wallet['simulated_balance'] ?? true),
        ];
    }

    private function walletResponse(array $wallet, array $transactions): array
    {
        $points = (int)($wallet['points'] ?? 0);

        return [
            'balance_mxn' => round((float)($wallet['balance_mxn'] ?? 0), 2),
            'points' => $points,
            'points_value_mxn' => floor($points / self::POINTS_PER_MXN_VALUE),
            'discount_rate' => self::DISCOUNT_RATE,
            'simulated' => (bool)($wallet['simulated_balance'] ?? true),
            'transactions' => $transactions,
        ];
    }

    private function recentTransactions(PDO $pdo, int $walletId, int $limit): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $pdo->prepare(
            "SELECT type, context, reference_type, reference_id, amount_mxn, points_delta,
                    balance_after_mxn, points_after, description, created_at
               FROM amare_wallet_transactions
              WHERE wallet_id = :wallet_id
           ORDER BY created_at DESC, id DESC
              LIMIT {$limit}"
        );
        $stmt->execute([':wallet_id' => $walletId]);

        return array_map(static function (array $row): array {
            $row['amount_mxn'] = round((float)$row['amount_mxn'], 2);
            $row['points_delta'] = (int)$row['points_delta'];
            $row['balance_after_mxn'] = round((float)$row['balance_after_mxn'], 2);
            $row['points_after'] = (int)$row['points_after'];
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function insertTransaction(PDO $pdo, array $data): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO amare_wallet_transactions
                (wallet_id, user_id, type, context, reference_type, reference_id, amount_mxn,
                 points_delta, balance_after_mxn, points_after, description, metadata_json, created_at)
             VALUES
                (:wallet_id, :user_id, :type, :context, :reference_type, :reference_id, :amount_mxn,
                 :points_delta, :balance_after_mxn, :points_after, :description, :metadata_json, NOW())'
        );
        $stmt->execute([
            ':wallet_id' => $data['wallet_id'],
            ':user_id' => $data['user_id'],
            ':type' => $data['type'],
            ':context' => $data['context'],
            ':reference_type' => $data['reference_type'],
            ':reference_id' => $data['reference_id'],
            ':amount_mxn' => $data['amount_mxn'],
            ':points_delta' => $data['points_delta'],
            ':balance_after_mxn' => $data['balance_after_mxn'],
            ':points_after' => $data['points_after'],
            ':description' => $data['description'],
            ':metadata_json' => $data['metadata_json'],
        ]);
    }
}
