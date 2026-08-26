<?php
// Diagnóstico standalone. Súbelo a public_html/cafeuteq/api-php/health.php
// y ábrelo en el navegador. Bórralo cuando termines, no lo dejes público
// (muestra si las credenciales de tu .env están bien o mal, sin exponer
// la contraseña real).

header('Content-Type: application/json; charset=utf-8');

$autoloadPath = __DIR__ . '/vendor/autoload.php';
$envPath = __DIR__ . '/.env';
$composerJsonPath = __DIR__ . '/composer.json';

$requiredPhp = null;
if (file_exists($composerJsonPath)) {
    $composerJson = json_decode((string)file_get_contents($composerJsonPath), true);
    $requiredPhp = $composerJson['require']['php'] ?? null;
}

$base = [
    'php_version_running' => PHP_VERSION,
    'php_version_required_by_composer' => $requiredPhp,
    'vendor_autoload_exists' => file_exists($autoloadPath),
    'env_file_exists' => file_exists($envPath),
];

register_shutdown_function(function () use ($base) {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR];
    if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
        http_response_code(200);
        echo json_encode(array_merge($base, [
            'ok' => false,
            'autoload_status' => 'FATAL_ERROR',
            'fatal_error' => [
                'message' => $error['message'],
                'file' => $error['file'],
                'line' => $error['line'],
            ],
        ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
});

if (!$base['vendor_autoload_exists']) {
    echo json_encode(array_merge($base, [
        'ok' => false,
        'autoload_status' => 'MISSING',
        'message' => 'vendor/autoload.php no existe.',
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

require $autoloadPath;

// Cargar el .env igual que Config.php, sin depender de nada mas del proyecto.
$envValues = [];
if ($base['env_file_exists']) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        [$key, $value] = explode('=', $line, 2);
        $envValues[trim($key)] = trim($value, '"\'');
    }
}

function mask(string $value): string
{
    if ($value === '') return '(vacio)';
    $len = strlen($value);
    if ($len <= 2) return str_repeat('*', $len);
    return $value[0] . str_repeat('*', $len - 2) . $value[$len - 1] . " (largo: {$len})";
}

$dbHost = $envValues['DB_HOST'] ?? 'localhost';
$dbPort = $envValues['DB_PORT'] ?? '3306';
$dbName = $envValues['DB_NAME'] ?? '';
$dbUser = $envValues['DB_USER'] ?? '';
$dbPass = $envValues['DB_PASS'] ?? '';

$dbCheck = [
    'DB_HOST' => $dbHost,
    'DB_PORT' => $dbPort,
    'DB_NAME' => $dbName !== '' ? $dbName : '(vacio)',
    'DB_USER' => $dbUser !== '' ? $dbUser : '(vacio)',
    'DB_PASS' => mask($dbPass),
];

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
    ]);
    $tableCount = $pdo->query("SELECT COUNT(*) AS total FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")->fetch()['total'] ?? null;

    echo json_encode(array_merge($base, [
        'ok' => true,
        'autoload_status' => 'OK',
        'db_check' => $dbCheck,
        'db_connection' => 'OK',
        'tables_found_in_db' => (int)$tableCount,
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    echo json_encode(array_merge($base, [
        'ok' => false,
        'autoload_status' => 'OK',
        'db_check' => $dbCheck,
        'db_connection' => 'FAILED',
        'db_error' => $e->getMessage(),
    ]), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
