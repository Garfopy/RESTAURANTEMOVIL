<?php
/**
 * CarniHub — Database Configuration (EXAMPLE)
 *
 * Copia este archivo a config/database.php y llena tus credenciales.
 * NUNCA subas config/database.php al repositorio.
 *
 * ── Deploy standalone restaurante (idactivos.digital/restaurante/) ──────────
 * Agrega la siguiente constante para omitir la landing pública y arrancar
 * directamente en el login:
 *
 *   define('RESTAURANTE_STANDALONE', true);
 *
 * En un deploy normal de CarniHub omite esa línea (o ponla en false).
 */

define('DB_HOST',    'localhost');
define('DB_NAME',    'nombre_de_tu_base_de_datos');
define('DB_USER',    'usuario_mysql');
define('DB_PASS',    'contraseña_mysql');
define('DB_CHARSET', 'utf8mb4');

class Database
{
    /** @var PDO|null */
    private static $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                DB_HOST, DB_NAME, DB_CHARSET
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                error_log('[CarniHub DB] ' . $e->getMessage());
                http_response_code(500);
                die(json_encode(['error' => 'Database connection failed. Check config/database.php']));
            }
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone()     {}
}
