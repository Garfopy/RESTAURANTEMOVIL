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

    private static bool $schemaChecked = false;

    public function getWallet(int $userId, int $limit = 10): array
    {
        $pdo = Database::getInstance();
        $this->ensureSchema($pdo);
        $wallet = $this->ensureWallet($pdo, $userId);
        $transactions = $this->recentTransactions($pdo, (int)$wallet['id'], $limit);

        return $this->walletResponse($wallet, $transactions);
    }

    public function quote(int $userId, float $amount, bool $usePoints = false, string $context = 'food'): array
    {
        $pdo = Database::getInstance();
        $this->ensureSchema($pdo);
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
        $this->ensureSchema($pdo);
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

    private function ensureSchema(PDO $pdo): void
    {
        if (self::$schemaChecked) {
            return;
        }

        if ($this->tableExists($pdo, 'amare_wallets') && $this->tableExists($pdo, 'amare_wallet_transactions')) {
            $this->ensureWalletColumns($pdo);
            $this->ensureTransactionColumns($pdo);
            self::$schemaChecked = true;
            return;
        }

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `amare_wallets` (
              `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `user_id` INT UNSIGNED NOT NULL,
              `balance_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `points` INT UNSIGNED NOT NULL DEFAULT 0,
              `simulated_balance` TINYINT(1) NOT NULL DEFAULT 1,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` DATETIME DEFAULT NULL,
              PRIMARY KEY (`id`),
              UNIQUE KEY `uq_amare_wallet_user` (`user_id`),
              KEY `idx_amare_wallet_user` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS `amare_wallet_transactions` (
              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              `wallet_id` INT UNSIGNED NOT NULL,
              `user_id` INT UNSIGNED NOT NULL,
              `type` VARCHAR(40) NOT NULL,
              `context` VARCHAR(30) NULL,
              `reference_type` VARCHAR(40) NULL,
              `reference_id` INT UNSIGNED NULL,
              `amount_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `points_delta` INT NOT NULL DEFAULT 0,
              `balance_after_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `points_after` INT UNSIGNED NOT NULL DEFAULT 0,
              `description` VARCHAR(255) NULL,
              `metadata_json` TEXT NULL,
              `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `idx_wallet_transactions_wallet` (`wallet_id`, `created_at`),
              KEY `idx_wallet_transactions_user` (`user_id`, `created_at`),
              KEY `idx_wallet_transactions_reference` (`reference_type`, `reference_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->ensureWalletColumns($pdo);
        $this->ensureTransactionColumns($pdo);

        self::$schemaChecked = true;
    }

    private function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table'
        );
        $stmt->execute([':table' => $tableName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function columnExists(PDO $pdo, string $tableName, string $columnName): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*)
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table
                AND COLUMN_NAME = :column'
        );
        $stmt->execute([':table' => $tableName, ':column' => $columnName]);
        return (int)$stmt->fetchColumn() > 0;
    }

    private function addColumnIfMissing(PDO $pdo, string $tableName, string $columnName, string $definition): void
    {
        if ($this->columnExists($pdo, $tableName, $columnName)) {
            return;
        }

        $pdo->exec("ALTER TABLE `{$tableName}` ADD COLUMN {$definition}");
    }

    private function ensureWalletColumns(PDO $pdo): void
    {
        $this->addColumnIfMissing($pdo, 'amare_wallets', 'user_id', '`user_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`');
        $this->addColumnIfMissing($pdo, 'amare_wallets', 'balance_mxn', '`balance_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `user_id`');
        $this->addColumnIfMissing($pdo, 'amare_wallets', 'points', '`points` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `balance_mxn`');
        $this->addColumnIfMissing($pdo, 'amare_wallets', 'simulated_balance', '`simulated_balance` TINYINT(1) NOT NULL DEFAULT 1 AFTER `points`');
        $this->addColumnIfMissing($pdo, 'amare_wallets', 'created_at', '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `simulated_balance`');
        $this->addColumnIfMissing($pdo, 'amare_wallets', 'updated_at', '`updated_at` DATETIME DEFAULT NULL AFTER `created_at`');
    }

    private function ensureTransactionColumns(PDO $pdo): void
    {
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'wallet_id', '`wallet_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `id`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'user_id', '`user_id` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `wallet_id`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'type', '`type` VARCHAR(40) NOT NULL DEFAULT \'wallet_payment\' AFTER `user_id`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'context', '`context` VARCHAR(30) NULL AFTER `type`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'reference_type', '`reference_type` VARCHAR(40) NULL AFTER `context`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'reference_id', '`reference_id` INT UNSIGNED NULL AFTER `reference_type`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'amount_mxn', '`amount_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `reference_id`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'points_delta', '`points_delta` INT NOT NULL DEFAULT 0 AFTER `amount_mxn`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'balance_after_mxn', '`balance_after_mxn` DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER `points_delta`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'points_after', '`points_after` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `balance_after_mxn`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'description', '`description` VARCHAR(255) NULL AFTER `points_after`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'metadata_json', '`metadata_json` TEXT NULL AFTER `description`');
        $this->addColumnIfMissing($pdo, 'amare_wallet_transactions', 'created_at', '`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `metadata_json`');
    }

    private function ensureWallet(PDO $pdo, int $userId, bool $forUpdate = false): array
    {
        $suffix = $forUpdate ? ' FOR UPDATE' : '';
        $lookup = $this->userLookup($pdo, 'amare_wallets', $userId);
        $stmt = $pdo->prepare('SELECT * FROM amare_wallets WHERE ' . $lookup['where'] . ' LIMIT 1' . $suffix);
        $stmt->execute($lookup['params']);
        $wallet = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($wallet) {
            return $wallet;
        }

        $walletData = array_merge(
            $this->userColumnValues($pdo, 'amare_wallets', $userId),
            [
                'balance_mxn' => self::DEMO_BALANCE_MXN,
                'points' => 0,
                'simulated_balance' => 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]
        );
        $this->insertDynamicRow($pdo, 'amare_wallets', $walletData);

        $walletId = (int)$pdo->lastInsertId();
        $wallet = [
            'id' => $walletId,
            'user_id' => $userId,
            'usuario_id' => $userId,
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
        $row = array_merge(
            $this->userColumnValues($pdo, 'amare_wallet_transactions', (int)$data['user_id']),
            [
                'wallet_id' => $data['wallet_id'],
                'type' => $data['type'],
                'context' => $data['context'],
                'reference_type' => $data['reference_type'],
                'reference_id' => $data['reference_id'],
                'amount_mxn' => $data['amount_mxn'],
                'points_delta' => $data['points_delta'],
                'balance_after_mxn' => $data['balance_after_mxn'],
                'points_after' => $data['points_after'],
                'description' => $data['description'],
                'metadata_json' => $data['metadata_json'],
                'created_at' => date('Y-m-d H:i:s'),
            ]
        );

        $this->insertDynamicRow($pdo, 'amare_wallet_transactions', $row);
    }

    /**
     * @return array{where: string, params: array<string, int>}
     */
    private function userLookup(PDO $pdo, string $tableName, int $userId): array
    {
        $parts = [];
        $params = [];
        if ($this->columnExists($pdo, $tableName, 'user_id')) {
            $parts[] = 'user_id = :lookup_user_id';
            $params[':lookup_user_id'] = $userId;
        }
        if ($this->columnExists($pdo, $tableName, 'usuario_id')) {
            $parts[] = 'usuario_id = :lookup_usuario_id';
            $params[':lookup_usuario_id'] = $userId;
        }

        if (!$parts) {
            return [
                'where' => 'user_id = :lookup_user_id',
                'params' => [':lookup_user_id' => $userId],
            ];
        }

        return [
            'where' => '(' . implode(' OR ', $parts) . ')',
            'params' => $params,
        ];
    }

    private function userColumnValues(PDO $pdo, string $tableName, int $userId): array
    {
        $values = [];
        if ($this->columnExists($pdo, $tableName, 'user_id')) {
            $values['user_id'] = $userId;
        }
        if ($this->columnExists($pdo, $tableName, 'usuario_id')) {
            $values['usuario_id'] = $userId;
        }

        return $values ?: ['user_id' => $userId];
    }

    private function insertDynamicRow(PDO $pdo, string $tableName, array $data): void
    {
        $columns = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $column => $value) {
            if (!$this->columnExists($pdo, $tableName, (string)$column)) {
                continue;
            }
            $columns[] = "`{$column}`";
            $placeholder = ':' . $column;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $value;
        }

        if (!$columns) {
            throw new \RuntimeException("No hay columnas compatibles para insertar en {$tableName}.");
        }

        $sql = 'INSERT INTO `' . $tableName . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $pdo->prepare($sql)->execute($params);
    }
}
