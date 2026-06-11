<?php
/**
 * GET /auth/me
 * Retorna los datos del usuario actual
 */
$user = db_one(
    "SELECT id, nombre, email, foto_url FROM mobile_usuarios WHERE id = ? LIMIT 1",
    [$userId]
);

if (!$user) {
    json_response(['detail' => 'Usuario no encontrado.'], 404);
}

// Lo enviamos en un formato plano, similar a tu login
json_response([
    'id' => (int) $user['id'],
    'nombre' => $user['nombre'],
    'email' => $user['email'],
    'foto_url' => $user['foto_url']
]);
