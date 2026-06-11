<?php
/**
 * lib/db.php — Conexión PDO a MySQL
 *
 * Lee DATABASE_URL estilo SQLAlchemy:
 *   mysql+aiomysql://user:pass@host:port/dbname
 * y la convierte a un DSN PDO estándar.
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $url = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
    if (!$url) {
        throw new RuntimeException('DATABASE_URL no está definida en el .env', 500);
    }

    // Parsear SQLAlchemy URL
    $parsed = parse_url($url);
    if (!$parsed || !isset($parsed['host'])) {
        throw new RuntimeException('DATABASE_URL inválida', 500);
    }

    $host = $parsed['host'];
    $port = $parsed['port'] ?? 3306;
    $name = ltrim($parsed['path'] ?? '', '/');
    $user = $parsed['user'] ?? '';
    $pass = $parsed['pass'] ?? '';

    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $ex) {
        throw new RuntimeException('Error de conexión a la BD: ' . $ex->getMessage(), 500);
    }

    return $pdo;
}

/** Helper: ejecuta SELECT y devuelve todas las filas como array de assoc */
function db_all(string $sql, array $params = []): array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** Helper: ejecuta SELECT y devuelve la primera fila o null */
function db_one(string $sql, array $params = []): ?array {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/** Helper: ejecuta INSERT y devuelve el id insertado */
function db_insert(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int) db()->lastInsertId();
}

/** Helper: ejecuta UPDATE/DELETE y devuelve filas afectadas */
function db_exec(string $sql, array $params = []): int {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}