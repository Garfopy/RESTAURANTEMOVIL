<?php
/**
 * GET /auth/me
 * Retorna los datos del usuario actual
 */
$user = db_one(
    "SELECT id, nombre, email, telefono, foto_url, google_id, activo, created_at
       FROM mobile_usuarios
      WHERE id = ?
      LIMIT 1",
    [$userId]
);

if (!$user) {
    json_response(['detail' => 'Usuario no encontrado.'], 404);
}

json_response([
    'success' => true,
    'data' => [
        'user' => [
            'id' => (int) $user['id'],
            'nombre' => $user['nombre'],
            'email' => $user['email'],
            'telefono' => $user['telefono'] ?? null,
            'foto_url' => $user['foto_url'] ?? null,
            'google_id' => $user['google_id'] ?? null,
            'activo' => (bool) ($user['activo'] ?? true),
            'created_at' => $user['created_at'] ?? '',
        ],
    ],
]);
