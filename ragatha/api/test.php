<?php
/**
 * PASO 1: Prueba simple para verificar si /api/ es accesible
 * Accede a: https://idactivos.digital/api/test.php
 * Esperado: JSON con ok: true
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => true,
    'message' => 'Endpoint /api/test.php funciona correctamente',
    'debug' => [
        'php_version' => PHP_VERSION,
        'request_uri' => $_SERVER['REQUEST_URI'] ?? 'NULL',
        'script_name' => $_SERVER['SCRIPT_NAME'] ?? 'NULL',
        'timestamp' => date('Y-m-d H:i:s')
    ]
]);
