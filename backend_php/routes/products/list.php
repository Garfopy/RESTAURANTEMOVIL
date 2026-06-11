<?php
/**
 * GET /products
 * Retorna TODOS los productos disponibles (regalos y del menú).
 * Parámetros opcionales:
 *   ?es_regalo=1   Solo productos regalables
 *   ?es_regalo=0   Solo productos del menú (no regalables)
 *   ?categoria=    Filtrar por categoría
 */
$where = ['1=1'];
$params = [];

$esRegalo = $_GET['es_regalo'] ?? null;
if ($esRegalo !== null && $esRegalo !== '') {
    $where[] = 'es_regalo = ?';
    $params[] = (int)$esRegalo;
}

$categoria = $_GET['categoria'] ?? '';
if ($categoria !== '') {
    $where[] = 'categoria = ?';
    $params[] = $categoria;
}

$whereStr = implode(' AND ', $where);

$rows = db_all(
    "SELECT id, nombre, descripcion, precio, icono, color, es_regalo, categoria, imagen, orden
       FROM social_gift_products
      WHERE $whereStr
        AND activo = 1
      ORDER BY orden ASC, es_regalo DESC, nombre ASC",
    $params
);

$out = array_map(fn($r) => [
    'id'          => (int)$r['id'],
    'nombre'      => $r['nombre'],
    'descripcion' => $r['descripcion'],
    'precio'      => (float)$r['precio'],
    'icono'       => $r['icono'],
    'color'       => $r['color'],
    'es_regalo'   => (bool)$r['es_regalo'],
    'categoria'   => $r['categoria'],
    'imagen'      => $r['imagen'],
    'orden'       => (int)$r['orden'],
], $rows);

json_response($out);