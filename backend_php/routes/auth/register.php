<?php
/**
 * POST /auth/register
 * Body: { nombre, email, password }
 */
$body = get_json_body();
$nombre   = trim((string)($body['nombre'] ?? ''));
$email    = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

if ($nombre === '' || $email === '' || $password === '') {
    throw new RuntimeException('nombre, email y password son requeridos.', 422);
}
if (strlen($password) < 6) {
    throw new RuntimeException('La contrasena debe tener al menos 6 caracteres.', 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    throw new RuntimeException('Correo electronico invalido.', 422);
}

$existing = db_one(
    "SELECT id FROM mobile_usuarios WHERE email = ? LIMIT 1",
    [$email]
);
if ($existing) {
    throw new RuntimeException('Ya existe una cuenta con este correo electronico.', 409);
}

$now = now_ts();
$hash = password_hash($password, PASSWORD_BCRYPT);

$userId = db_insert(
    "INSERT INTO mobile_usuarios
        (nombre, email, password_hash, activo, is_social_active, created_at, updated_at)
     VALUES (?, ?, ?, 1, 0, ?, ?)",
    [$nombre, $email, $hash, $now, $now]
);

$rawToken = generate_token(32);
$tokenHash = sha256_hex($rawToken);
$expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

db_exec(
    "INSERT INTO mobile_sesiones
        (usuario_id, token_hash, platform, activo, ultimo_uso, expires_at, created_at)
     VALUES (?, ?, 'android', 1, ?, ?, ?)",
    [$userId, $tokenHash, $now, $expires, $now]
);

json_response([
    'user_id' => $userId,
    'nombre'  => $nombre,
    'email'   => $email,
    'token'   => $rawToken,
], 201);