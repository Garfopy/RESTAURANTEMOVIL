<?php
/**
 * PUT /users/social-profile
 * Body: { edad, sexualidad, genero, descripcion, intereses?, que_busca?, redes_sociales? }
 */
$body = get_json_body();
$edad            = (int)($body['edad'] ?? 0);
$sexualidad      = trim((string)($body['sexualidad'] ?? ''));
$genero          = trim((string)($body['genero'] ?? ''));
$descripcion     = trim((string)($body['descripcion'] ?? ''));
$intereses       = isset($body['intereses']) && $body['intereses'] !== null ? (string)$body['intereses'] : null;
$queBusca        = isset($body['que_busca']) && $body['que_busca'] !== null ? (string)$body['que_busca'] : null;
$redesSociales   = isset($body['redes_sociales']) && $body['redes_sociales'] !== null ? trim((string)$body['redes_sociales']) : null;

if ($edad < 1 || $edad > 120) {
    throw new RuntimeException('Edad inválida.', 422);
}
if ($sexualidad === '' || $genero === '' || $descripcion === '') {
    throw new RuntimeException('sexualidad, genero y descripcion son obligatorios.', 422);
}

$now = now_ts();
db_exec(
    "UPDATE mobile_usuarios
        SET edad = ?, sexualidad = ?, genero = ?, descripcion = ?,
            intereses = ?, que_busca = ?, redes_sociales = ?,
            updated_at = ?
      WHERE id = ?",
    [$edad, $sexualidad, $genero, $descripcion, $intereses, $queBusca, $redesSociales, $now, $userId]
);

$user = db_one(
    "SELECT id, nombre, foto_url, edad, sexualidad, genero, descripcion,
            intereses, que_busca, redes_sociales
       FROM mobile_usuarios WHERE id = ?",
    [$userId]
);

json_response([
    'user_id'         => (int)$user['id'],
    'nombre'          => $user['nombre'],
    'foto_url'        => $user['foto_url'],
    'edad'            => (int)$user['edad'],
    'sexualidad'      => $user['sexualidad'],
    'genero'          => $user['genero'],
    'descripcion'     => $user['descripcion'],
    'intereses'       => $user['intereses'],
    'que_busca'       => $user['que_busca'],
    'redes_sociales'  => $user['redes_sociales'],
    'has_social_profile' => has_social_profile($user),
]);