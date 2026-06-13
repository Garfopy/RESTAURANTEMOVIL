<?php
/**
 * POST /orders
 * Body: { platillos: [{ platillo_id, cantidad, notas? }], tipo_pedido, mesa_id?, notas? }
 */
$body = get_json_body();
$platillos = $body['platillos'] ?? [];
$tipoPedido = (string)($body['tipo_pedido'] ?? 'dine_in');
$mesaId     = isset($body['mesa_id']) && $body['mesa_id'] !== null ? (int)$body['mesa_id'] : null;
$notas      = isset($body['notas']) ? (string)$body['notas'] : null;

if (empty($platillos) || !is_array($platillos)) {
    throw new RuntimeException('El pedido debe tener al menos un platillo.', 422);
}

$restId = (int)($_ENV['RESTAURANTE_ID'] ?? getenv('RESTAURANTE_ID') ?: 1);

// ── Validar y obtener platillos con precios del servidor ──
$ids = array_map(fn($l) => (int)$l['platillo_id'], $platillos);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$rows = db_all(
    "SELECT id, nombre, precio
       FROM rest_platillos
      WHERE id IN ($placeholders)
        AND restaurante_id = ?
        AND activo = 1
        AND disponible = 1",
    array_merge($ids, [$restId])
);

$platMap = [];
foreach ($rows as $r) $platMap[(int)$r['id']] = $r;

foreach ($platillos as $line) {
    $pid = (int)$line['platillo_id'];
    if (!isset($platMap[$pid])) {
        throw new RuntimeException("Platillo $pid no disponible.", 422);
    }
}

// ── Calcular totales ──
$subtotal = 0.0;
foreach ($platillos as $line) {
    $pid = (int)$line['platillo_id'];
    $cant = max(1, (int)$line['cantidad']);
    $subtotal += (float)$platMap[$pid]['precio'] * $cant;
}

$now = now_ts();

// ── Crear pedido ──
$pedidoId = db_insert(
    "INSERT INTO rest_pedidos
        (restaurante_id, mesa_id, folio, estado, notas, subtotal, total,
         tipo_pedido, mobile_usuario_id, created_at)
     VALUES (?, ?, '', 'pendiente', ?, ?, ?, ?, ?, ?)",
    [$restId, $mesaId, $notas, $subtotal, $subtotal, $tipoPedido, $userId, $now]
);

$folio = sprintf('P-%05d', $pedidoId);
db_exec("UPDATE rest_pedidos SET folio = ? WHERE id = ?", [$folio, $pedidoId]);

// ── Items ──
$itemsOut = [];
foreach ($platillos as $line) {
    $pid = (int)$line['platillo_id'];
    $cant = max(1, (int)$line['cantidad']);
    $precio = (float)$platMap[$pid]['precio'];
    $lineNotas = isset($line['notas']) ? (string)$line['notas'] : null;
    $sub = $precio * $cant;

    $itemId = db_insert(
        "INSERT INTO rest_pedido_items
            (pedido_id, platillo_id, cantidad, precio_unit, subtotal, notas, estado)
         VALUES (?, ?, ?, ?, ?, ?, 'pendiente')",
        [$pedidoId, $pid, $cant, $precio, $sub, $lineNotas]
    );

    $itemsOut[] = [
        'id'              => $itemId,
        'platillo_id'     => $pid,
        'platillo_nombre' => $platMap[$pid]['nombre'],
        'cantidad'        => $cant,
        'precio_unit'     => $precio,
        'subtotal'        => $sub,
        'notas'           => $lineNotas,
        'estado'          => 'pendiente',
    ];
}

json_response([
    'id'          => $pedidoId,
    'folio'       => $folio,
    'estado'      => 'pendiente',
    'tipo_pedido' => $tipoPedido,
    'mesa_id'     => $mesaId,
    'subtotal'    => $subtotal,
    'total'       => $subtotal,
    'notas'       => $notas,
    'items'       => $itemsOut,
    'created_at'  => $now,
], 201);