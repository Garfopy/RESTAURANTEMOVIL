<?php
/**
 * auth_me.php — Standalone endpoint
 * GET /api_asocial/auth_me.php
 * Headers: Authorization: Bearer <token>
 */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/auth.php';

set_exception_handler(function ($ex) {
    $code = $ex->getCode() ?: 500;
    if (!in_array($code, [400, 401, 403, 404, 409, 422], true)) $code = 500;
    json_response(['detail' => $ex->getMessage()], $code);
});

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { json_response(['detail' => 'Método no permitido.'], 405); }

$userId = require_auth();

try {
    $user = db_one(
        "SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at
           FROM mobile_usuarios WHERE id = ?",
        [$userId]
    );
} catch (Throwable $e) {
    json_response(['detail' => 'Error de base de datos: ' . $e->getMessage()], 500);
}
if (!$user) json_response(['detail' => 'Usuario no encontrado.'], 404);

$user['id'] = (int)$user['id'];
$user['activo'] = (bool)$user['activo'];

json_response([
    'success' => true,
    'data'    => ['user' => $user],
]);</write_file>