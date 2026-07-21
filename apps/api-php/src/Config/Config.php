<?php

declare(strict_types=1);

// Cargar autoloader de Composer si existe
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

// Cargar variables de entorno
$envFile = __DIR__ . '/../../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value, '"\'');
        $_SERVER[trim($key)] = trim($value, '"\'');
    }
}

// Configuración de zona horaria
date_default_timezone_set('America/Mexico_City');

// CORS solo aplica a clientes web. Las apps nativas normalmente no envian Origin.
$appEnv = strtolower(trim((string)($_ENV['APP_ENV'] ?? 'production')));
$requestOrigin = trim((string)($_SERVER['HTTP_ORIGIN'] ?? ''));
$configuredOrigins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string)($_ENV['CORS_ORIGIN'] ?? ''))
)));
$allowsWildcard = $appEnv !== 'production' && in_array('*', $configuredOrigins, true);
$originAllowed = $requestOrigin === '' || $allowsWildcard || in_array($requestOrigin, $configuredOrigins, true);

if ($requestOrigin !== '' && $originAllowed) {
    header('Access-Control-Allow-Origin: ' . ($allowsWildcard ? '*' : $requestOrigin));
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, If-None-Match, Cache-Control, Pragma');
header('Access-Control-Expose-Headers: ETag, Cache-Control');
if ($requestOrigin !== '' && $originAllowed && !$allowsWildcard) {
    header('Access-Control-Allow-Credentials: true');
}
header('Content-Type: application/json; charset=utf-8');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code($originAllowed ? 204 : 403);
    exit;
}
