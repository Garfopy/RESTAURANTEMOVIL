<?php

declare(strict_types=1);

namespace Amare\Api\Config;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    /**
     * Obtener instancia única de PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            try {
                $host = $_ENV['DB_HOST'] ?? 'localhost';
                $port = $_ENV['DB_PORT'] ?? '3306';
                $dbname = $_ENV['DB_NAME'] ?? '';
                $username = $_ENV['DB_USER'] ?? '';
                $password = $_ENV['DB_PASS'] ?? '';

                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                ];

                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'Error de conexión a la base de datos',
                    'error' => $_ENV['APP_DEBUG'] ? $e->getMessage() : null
                ]);
                exit;
            }
        }

        return self::$instance;
    }


    public static function query(string $sql, array $params = []): array
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            if ($_ENV['APP_DEBUG']) {
                throw $e;
            }
            return [];
        }
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            if ($_ENV['APP_DEBUG']) {
                throw $e;
            }
            return null;
        }
    }

    public static function execute(string $sql, array $params = []): int
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return self::getInstance()->lastInsertId();
        } catch (PDOException $e) {
            if ($_ENV['APP_DEBUG']) {
                throw $e;
            }
            return 0;
        }
    }

    public static function rowCount(string $sql, array $params = []): int
    {
        try {
            $stmt = self::getInstance()->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            return 0;
        }
    }
}