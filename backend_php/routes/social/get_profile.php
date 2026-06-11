<?php
/**
 * GET /users/social-profile
 * Retorna el perfil social del usuario actual.
 */
$user = db_one(
    "SELECT id, nombre, foto_url, edad, sexualidad, genero, descripcion,
            intereses, que_busca, redes_sociales
       FROM mobile_usuarios WHERE id = ? LIMIT 1",
    [$userId]
);
if (!$user) {
    throw new RuntimeException('Usuario no encontrado.', 404);
}

json_response([
    'user_id'         => (int)$user['id'],
    'nombre'          => $user['nombre'],
    'foto_url'        => $user['foto_url'],
    'edad'            => $user['edad'] !== null ? (int)$user['edad'] : null,
    'sexualidad'      => $user['sexualidad'],
    'genero'          => $user['genero'],
    'descripcion'     => $user['descripcion'],
    'intereses'       => $user['intereses'],
    'que_busca'       => $user['que_busca'],
    'redes_sociales'  => $user['redes_sociales'],
    'has_social_profile' => has_social_profile($user),
]);