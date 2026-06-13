<?php
/**
 * GET /orders/{id}
 * Retorna el estado de un pedido del usuario actual.
 */
$orderId = (int)($_GET['order_id'] ?? 0);

$pedido = db_one(
    "SELECT id, folio, estado, tipo_pedido, mesa_id, subtotal, total,
            notas, created_at
       FROM rest_pedidos
      WHERE id = ? AND mobile_usuario_id = ?
      LIMIT 1",
    [$orderId, $userId]
);
if (!$pedido) {
    throw new RuntimeException('Pedido no encontrado.', 404);
}

$rows = db_all(
    "SELECT pi.id, pi.platillo_id, p.nombre, pi.cantidad,
            pi.precio_unit, pi.subtotal, pi.notas, pi.estado
       FROM rest_pedido_items pi
       JOIN rest_platillos p ON p.id = pi.platillo_id
      WHERE pi.pedido_id = ?",
    [$orderId]
);

$items = array_map(fn($r) => [
    'id'              => (int)$r['id'],
    'platillo_id'     => (int)$r['platillo_id'],
    'platillo_nombre' => $r['nombre'],
    'cantidad'        => (int)$r['cantidad'],
    'precio_unit'     => (float)$r['precio_unit'],
    'subtotal'        => (float)$r['subtotal'],
    'notas'           => $r['notas'],
    'estado'          => $r['estado'],
], $rows);

json_response([
    'id'          => (int)$pedido['id'],
    'folio'       => $pedido['folio'],
    'estado'      => $pedido['estado'],
    'tipo_pedido' => $pedido['tipo_pedido'],
    'mesa_id'     => $pedido['mesa_id'] !== null ? (int)$pedido['mesa_id'] : null,
    'subtotal'    => (float)$pedido['subtotal'],
    'total'       => (float)$pedido['total'],
    'notas'       => $pedido['notas'],
    'items'       => $items,
    'created_at'  => $pedido['created_at'],
]);