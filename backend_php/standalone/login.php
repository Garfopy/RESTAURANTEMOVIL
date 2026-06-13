<?php
/**
 * login.php — Standalone LOGIN endpoint
 * POST /api_asocial/login.php
 * Body: { email, password }
 */

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/util.php';

// ── Manejo global de errores ─────────────────────────────────────────
set_exception_handler(function ($ex) {
    $code = $ex->getCode() ?: 500;
    if (!in_array($code, [400, 401, 403, 404, 409, 422], true)) $code = 500;
    json_response(['detail' => $ex->getMessage()], $code);
});

// ── Leer .env ─────────────────────────────────────────────────────────
$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
}

// ── Headers ──────────────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { json_response(['detail' => 'Método no permitido.'], 405); }

// ── Leer body como JSON ──────────────────────────────────────────────
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) $body = [];

$email    = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($email === '' || $password === '') { json_response(['detail' => 'email y password son requeridos.'], 422); }
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { json_response(['detail' => 'Correo electrónico inválido.'], 422); }

// ── Buscar usuario ────────────────────────────────────────────────────
try {
    $user = db_one(
        "SELECT id, nombre, email, telefono, foto_url, google_id, password_hash, activo, created_at
           FROM mobile_usuarios WHERE email = ? LIMIT 1",
        [$email]
    );
} catch (Throwable $e) {
    json_response(['detail' => 'Error al consultar la base de datos: ' . $e->getMessage()], 500);
}

if (!$user) { json_response(['detail' => 'El correo no está registrado.'], 401); }
if (empty($user['password_hash'])) { json_response(['detail' => 'Esta cuenta fue creada sin contraseña.'], 401); }
if (!(int)$user['activo']) { json_response(['detail' => 'Cuenta desactivada.'], 403); }
if (!password_verify($password, $user['password_hash'])) { json_response(['detail' => 'Contraseña incorrecta.'], 401); }

// ── Generar sesión ────────────────────────────────────────────────────
$rawToken = generate_token(32);
$tokenHash = sha256_hex($rawToken);
$now = now_ts();
$expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

try {
    db_exec(
        "INSERT INTO mobile_sesiones (usuario_id, token_hash, platform, activo, ultimo_uso, expires_at, created_at)
         VALUES (?, ?, 'android', 1, ?, ?, ?)",
        [(int)$user['id'], $tokenHash, $now, $expires, $now]
    );
} catch (Throwable $e) {
    json_response(['detail' => 'Error al crear sesión: ' . $e->getMessage()], 500);
}

// ── Respuesta exitosa ────────────────────────────────────────────────
json_response([
    'success' => true,
    'data'    => [
        'user' => [
            'id'         => (int)$user['id'],
            'nombre'     => $user['nombre'],
            'email'      => $user['email'],
            'telefono'   => $user['telefono'],
            'foto_url'   => $user['foto_url'],
            'google_id'  => $user['google_id'],
            'activo'     => (bool)$user['activo'],
            'created_at' => $user['created_at'],
        ],
        'token' => $rawToken,
    ],
]);</write_file>