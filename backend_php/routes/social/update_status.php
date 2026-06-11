<?php
/**
 * PATCH /users/social-status
 * Body: { is_social_active, current_restaurante_id? }
 */
$body = get_json_body();
$isActive = $body['is_social_active'] ?? null;
$restId   = isset($body['current_restaurante_id']) && $body['current_restaurante_id'] !== null
            ? (int)$body['current_restaurante_id'] : null;

if (!is_bool($isActive)) {
    throw new RuntimeException('is_social_active debe ser boolean.', 422);
}
if ($isActive && $restId === null) {
    throw new RuntimeException('current_restaurante_id es requerido al activar el modo social.', 422);
}

$user = db_one(
    "SELECT id, foto_url, edad, sexualidad, genero, descripcion
       FROM mobile_usuarios WHERE id = ? LIMIT 1",
    [$userId]
);
if (!$user) {
    throw new RuntimeException('Usuario no encontrado.', 404);
}

if ($isActive && !has_social_profile($user)) {
    throw new RuntimeException('Debes completar tu perfil social antes de activar el modo social.', 400);
}

$now = now_ts();
$dbRestId = $isActive ? $restId : null;

db_exec(
    "UPDATE mobile_usuarios
        SET is_social_active = ?,
            current_restaurante_id = ?,
            social_updated_at = ?
      WHERE id = ?",
    [$isActive ? 1 : 0, $dbRestId, $now, $userId]
);

$updated = db_one(
    "SELECT id, nombre, is_social_active, current_restaurante_id, social_updated_at
       FROM mobile_usuarios WHERE id = ?",
    [$userId]
);

json_response([
    'user_id'                 => (int)$updated['id'],
    'nombre'                  => $updated['nombre'],
    'is_social_active'        => (bool)$updated['is_social_active'],
    'current_restaurante_id'  => $updated['current_restaurante_id'] !== null ? (int)$updated['current_restaurante_id'] : null,
    'social_updated_at'       => $updated['social_updated_at'],
]);