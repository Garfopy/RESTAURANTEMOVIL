<?php
/**
 * GET /users/{id}/public-profile
 */
$userIdTarget = (int)($_GET['user_id'] ?? 0);

$requester = db_one(
    "SELECT is_social_active, current_restaurante_id
       FROM mobile_usuarios WHERE id = ? LIMIT 1",
    [$userId]
);
if (!$requester || !(bool)$requester['is_social_active'] || $requester['current_restaurante_id'] === null) {
    throw new RuntimeException('Debes tener el modo social activo para ver perfiles.', 403);
}

$target = db_one(
    "SELECT id, nombre, foto_url, edad, sexualidad, genero, descripcion,
            intereses, que_busca, redes_sociales,
            is_social_active, current_restaurante_id
       FROM mobile_usuarios WHERE id = ? LIMIT 1",
    [$userIdTarget]
);
if (!$target) {
    throw new RuntimeException('Usuario no encontrado.', 404);
}
if (!(bool)$target['is_social_active']
    || (int)$target['current_restaurante_id'] !== (int)$requester['current_restaurante_id']) {
    throw new RuntimeException('Este usuario no está visible en este restaurante.', 403);
}

json_response([
    'user_id'         => (int)$target['id'],
    'nombre'          => $target['nombre'],
    'foto_url'        => $target['foto_url'],
    'edad'            => $target['edad'] !== null ? (int)$target['edad'] : null,
    'sexualidad'      => $target['sexualidad'],
    'genero'          => $target['genero'],
    'descripcion'     => $target['descripcion'],
    'intereses'       => $target['intereses'],
    'que_busca'       => $target['que_busca'],
    'redes_sociales'  => $target['redes_sociales'],
]);