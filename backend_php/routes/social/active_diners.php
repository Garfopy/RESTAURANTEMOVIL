<?php
/**
 * GET /restaurants/{id}/active-users
 * Query: edad_min, edad_max, genero, sexualidad
 */
$restaurantId = (int)($_GET['restaurant_id'] ?? 0);

$requester = db_one(
    "SELECT id, is_social_active, current_restaurante_id
       FROM mobile_usuarios WHERE id = ? LIMIT 1",
    [$userId]
);
if (!$requester || !(bool)$requester['is_social_active']
    || (int)$requester['current_restaurante_id'] !== $restaurantId) {
    throw new RuntimeException('Debes tener el modo social activo en este restaurante.', 403);
}

$where = ['is_social_active = 1', 'current_restaurante_id = ?', 'id != ?'];
$params = [$restaurantId, $userId];

if (!empty($_GET['edad_min'])) {
    $where[] = 'edad >= ?';
    $params[] = (int)$_GET['edad_min'];
}
if (!empty($_GET['edad_max'])) {
    $where[] = 'edad <= ?';
    $params[] = (int)$_GET['edad_max'];
}
if (!empty($_GET['genero'])) {
    $where[] = 'genero = ?';
    $params[] = (string)$_GET['genero'];
}
if (!empty($_GET['sexualidad'])) {
    $where[] = 'sexualidad = ?';
    $params[] = (string)$_GET['sexualidad'];
}

$sql = 'SELECT id, nombre, foto_url FROM mobile_usuarios WHERE ' . implode(' AND ', $where);
$rows = db_all($sql, $params);

$out = array_map(fn($r) => [
    'user_id'  => (int)$r['id'],
    'nombre'   => $r['nombre'],
    'foto_url' => $r['foto_url'],
], $rows);

json_response($out);