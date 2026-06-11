<?php
/**
 * POST /auth/login
 * Body: { email, password }
 *
 * VERSION ACTUALIZADA - Si ves "LOGIN_V2" en la respuesta, el archivo correcto se está ejecutando
 */
$body = get_json_body();
$email    = strtolower(trim((string)($body['email'] ?? '')));
$password = (string)($body['password'] ?? '');

// Si no hay body o los campos están vacíos, es una petición sin datos
if (empty($body) || $email === '' || $password === '') {
    json_response(['detail' => 'email y password son requeridos.', 'code' => 'MISSING_FIELDS'], 422);
}

$user = db_one(
    "SELECT id, nombre, email, foto_url, password_hash,
            edad, sexualidad, genero, descripcion, activo
       FROM mobile_usuarios
      WHERE email = ?
      LIMIT 1",
    [$email]
);

if (!$user) {
    json_response(['detail' => 'El correo electrónico no está registrado.', 'code' => 'EMAIL_NOT_FOUND'], 401);
}
if (empty($user['password_hash'])) {
    json_response(['detail' => 'Esta cuenta fue creada sin contraseña. Contacta soporte.', 'code' => 'NO_PASSWORD'], 401);
}
if (!(int)$user['activo']) {
    json_response(['detail' => 'Esta cuenta está desactivada.', 'code' => 'ACCOUNT_DISABLED'], 403);
}
if (!password_verify($password, $user['password_hash'])) {
    json_response(['detail' => 'Contraseña incorrecta.', 'code' => 'WRONG_PASSWORD'], 401);
}

$rawToken = generate_token(32);
$tokenHash = sha256_hex($rawToken);
$now = now_ts();
$expires = (new DateTime('+30 days'))->format('Y-m-d H:i:s');

db_exec(
    "INSERT INTO mobile_sesiones
        (usuario_id, token_hash, platform, activo, ultimo_uso, expires_at, created_at)
     VALUES (?, ?, 'android', 1, ?, ?, ?)",
    [$user['id'], $tokenHash, $now, $expires, $now]
);

json_response([
    'user_id'             => (int) $user['id'],
    'nombre'              => $user['nombre'],
    'email'               => $user['email'],
    'foto_url'            => $user['foto_url'],
    'has_social_profile'  => has_social_profile($user),
    'token'               => $rawToken,
]);