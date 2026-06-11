<?php
/**
 * GET /gift-products
 * Retorna productos de regalo + platillos/bebidas del menú como opciones para regalar.
 *
 * Cada ítem incluye un campo "tipo": 'gift' (producto clásico) o 'menu' (platillo/bebida).
 */
$restaurantId = (int)($_GET['restaurant_id'] ?? 1);

// 1. Productos de regalo clásicos
$gifts = db_all(
    "SELECT id, nombre, descripcion, precio, icono, color
       FROM social_gift_products
      WHERE activo = 1
      ORDER BY orden ASC"
);

$giftOut = array_map(fn($r) => [
    'id'          => (int)$r['id'],
    'tipo'        => 'gift',
    'nombre'      => $r['nombre'],
    'descripcion' => $r['descripcion'],
    'precio'      => (float)$r['precio'],
    'icono'       => $r['icono'],
    'color'       => $r['color'],
], $gifts);

// 2. Platillos/bebidas del menú disponibles para regalar
// Consideramos bebidas: refrescos, cervezas, vinos, aguas, cafés, etc.
$menuItems = db_all(
    "SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen, c.nombre AS categoria_nombre
       FROM rest_platillos p
       JOIN rest_categorias_menu c ON p.categoria_id = c.id
      WHERE p.restaurante_id = ?
        AND p.activo = 1
        AND p.disponible = 1
        AND (c.nombre LIKE '%bebida%' OR c.nombre LIKE '%trago%' OR c.nombre LIKE '%cerveza%'
             OR c.nombre LIKE '%vino%' OR c.nombre LIKE '%refresco%' OR c.nombre LIKE '%agua%'
             OR c.nombre LIKE '%café%' OR c.nombre LIKE '%postre%')
      ORDER BY c.nombre, p.nombre",
    [$restaurantId]
);

$menuOut = array_map(fn($r) => [
    'id'              => (int)$r['id'],
    'tipo'            => 'menu',
    'nombre'          => $r['nombre'],
    'descripcion'     => $r['descripcion'] ?: $r['categoria_nombre'],
    'precio'          => (float)$r['precio'],
    'icono'           => match (true) {
        str_contains($r['categoria_nombre'] ?? '', 'Cerveza') => 'beer-outline',
        str_contains($r['categoria_nombre'] ?? '', 'Vino')    => 'wine-outline',
        str_contains($r['categoria_nombre'] ?? '', 'Café')    => 'cafe-outline',
        str_contains($r['categoria_nombre'] ?? '', 'Trago')   => 'flame-outline',
        str_contains($r['categoria_nombre'] ?? '', 'Refresco') => 'water-outline',
        str_contains($r['categoria_nombre'] ?? '', 'Postre')  => 'ice-cream-outline',
        default => 'fast-food-outline',
    },
    'color'           => '#FF6F00',
    'categoria_nombre'=> $r['categoria_nombre'],
], $menuItems);

// 3. Combinar: gifts primero, luego menú
$out = array_merge($giftOut, $menuOut);

json_response($out);