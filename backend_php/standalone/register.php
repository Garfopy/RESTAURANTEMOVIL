<?php
/**
 * register.php — Standalone REGISTER endpoint
 * POST /api_asocial/register.php
 * Body: { nombre, email, password }
 */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/util.php';

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
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['detail' => 'Método no permitido.'], 405); }

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) $body = [];

$nombre   = trim((string)($body['nombre'] ?? ''));
$email    = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($nombre === '' || $email === '' || $password === '') {
    json_response(['detail' => 'nombre, email y password son requeridos.'], 422);
}
if (strlen($password) < 6) { json_response(['detail' => 'La contraseña debe tener al menos 6 caracteres.'], 422); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { json_response(['detail' => 'Correo electrónico inválido.'], 422); }

try {
    $existing = db_one("SELECT id FROM mobile_usuarios WHERE email = ? LIMIT 1", [$email]);
} catch (Throwable $e) {
    json_response(['detail' => 'Error de base de datos: ' . $e->getMessage()], 500);
}
if ($existing) { json_response(['detail' => 'Ya existe una cuenta con este correo.'], 409); }

$now = now_ts();
$hash = password_hash($password, PASSWORD_BCRYPT);
try {
    $userId = db_insert(
        "INSERT INTO mobile_usuarios (nombre, email, password_hash, activo, is_social_active, created_at, updated_at)
         VALUES (?, ?, ?, 1, 0, ?, ?)",
        [$nombre, $email, $hash, $now, $now]
    );
} catch (Throwable $e) {
    json_response(['detail' => 'Error al crear usuario: ' . $e->getMessage()], 500);
}

$rawToken = generate_token(32);
$tokenHash = sha256_hex($rawToken);
$expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');
try {
    db_exec(
        "INSERT INTO mobile_sesiones (usuario_id, token_hash, platform, activo, ultimo_uso, expires_at, created_at)
         VALUES (?, ?, 'android', 1, ?, ?, ?)",
        [$userId, $tokenHash, $now, $expires, $now]
    );
} catch (Throwable $e) {
    json_response(['detail' => 'Error al crear sesión: ' . $e->getMessage()], 500);
}

json_response([
    'success' => true,
    'data'    => [
        'user'  => ['id' => $userId, 'nombre' => $nombre, 'email' => $email],
        'token' => $rawToken,
    ],
], 201);</write_file>