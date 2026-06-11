<?php
/**
 * GET /restaurants/{id}/menu
 * Retorna todas las categorías activas y los platillos disponibles.
 */
$restaurantId = (int)($_GET['restaurant_id'] ?? 1);

$categorias = db_all(
    "SELECT id, nombre, descripcion, imagen, orden
       FROM rest_categorias_menu
      WHERE restaurante_id = ? AND activo = 1
      ORDER BY orden ASC",
    [$restaurantId]
);

if (empty($categorias)) {
    throw new RuntimeException('Restaurante no encontrado o sin categorías activas.', 404);
}

$catMap = [];
foreach ($categorias as $c) {
    $catMap[(int)$c['id']] = $c['nombre'];
}
$catIds = array_keys($catMap);
$placeholders = implode(',', array_fill(0, count($catIds), '?'));

$platillos = db_all(
    "SELECT id, codigo, nombre, descripcion, alergenos, precio, imagen,
            tiempo_preparacion_min, disponible, categoria_id
       FROM rest_platillos
      WHERE restaurante_id = ?
        AND activo = 1
        AND disponible = 1
        AND categoria_id IN ($placeholders)
      ORDER BY categoria_id, nombre",
    array_merge([$restaurantId], $catIds)
);

$platillosOut = array_map(function ($p) use ($catMap) {
    return [
        'id'                       => (int)$p['id'],
        'codigo'                   => $p['codigo'],
        'nombre'                   => $p['nombre'],
        'descripcion'              => $p['descripcion'],
        'alergenos'                => $p['alergenos'],
        'precio'                   => (float)$p['precio'],
        'imagen'                   => $p['imagen'],
        'tiempo_preparacion_min'   => (int)$p['tiempo_preparacion_min'],
        'disponible'               => (bool)$p['disponible'],
        'categoria_id'             => $p['categoria_id'] !== null ? (int)$p['categoria_id'] : null,
        'categoria_nombre'         => isset($catMap[(int)$p['categoria_id']]) ? $catMap[(int)$p['categoria_id']] : null,
    ];
}, $platillos);

$categoriasOut = array_map(function ($c) {
    return [
        'id'          => (int)$c['id'],
        'nombre'      => $c['nombre'],
        'descripcion' => $c['descripcion'],
        'imagen'      => $c['imagen'],
        'orden'       => (int)$c['orden'],
    ];
}, $categorias);

json_response([
    'categorias' => $categoriasOut,
    'platillos'  => $platillosOut,
]);