<?php
// Variables: $pedido (array con items[] y sucursales[])

$estadoColores = [
    'pendiente'      => ['bg'=>'#FEF3C7','text'=>'#92400E'],
    'confirmado'     => ['bg'=>'#DBEAFE','text'=>'#1E40AF'],
    'en_preparacion' => ['bg'=>'#EDE9FE','text'=>'#5B21B6'],
    'en_ruta'        => ['bg'=>'#D1FAE5','text'=>'#065F46'],
    'entregado'      => ['bg'=>'#D1FAE5','text'=>'#065F46'],
    'cancelado'      => ['bg'=>'#FEE2E2','text'=>'#991B1B'],
];
$col = $estadoColores[$pedido['estado']] ?? ['bg'=>'#F3F4F6','text'=>'#374151'];
?>

<div style="max-width:900px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px">
    <a href="<?= BASE_URL ?>panel-pedido/index"
       style="display:inline-flex;align-items:center;gap:6px;color:#6B7280;font-size:.875rem;text-decoration:none">
      ← Volver a pedidos
    </a>
    <div style="display:flex;align-items:center;gap:10px">
      <span style="background:<?= $col['bg'] ?>;color:<?= $col['text'] ?>;padding:5px 14px;border-radius:999px;font-size:.8rem;font-weight:700">
        <?= ucfirst(str_replace('_',' ', $pedido['estado'])) ?>
      </span>
      <select id="sel-estado"
              onchange="cambiarEstado(<?= $pedido['id'] ?>, this)"
              style="padding:6px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.8rem;cursor:pointer">
        <option value="">Cambiar estado...</option>
        <?php foreach (['pendiente','confirmado','en_preparacion','en_ruta','entregado','cancelado'] as $e): ?>
        <option value="<?= $e ?>" <?= $pedido['estado'] === $e ? 'disabled' : '' ?>><?= ucfirst(str_replace('_',' ',$e)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>

  <!-- Cabecera del pedido -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px;margin-bottom:16px">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px">
      <div>
        <div style="font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase;margin-bottom:4px">Folio</div>
        <div style="font-size:1.1rem;font-weight:800;color:#111827"><?= htmlspecialchars($pedido['folio']) ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase;margin-bottom:4px">Empresa</div>
        <div style="font-size:.875rem;font-weight:600;color:#374151"><?= htmlspecialchars($pedido['empresa_nombre'] ?? '—') ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase;margin-bottom:4px">Comprador</div>
        <div style="font-size:.875rem;color:#374151"><?= htmlspecialchars($pedido['comprador_nombre'] . ' ' . $pedido['comprador_apellido']) ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase;margin-bottom:4px">Total</div>
        <div style="font-size:1.1rem;font-weight:800;color:#111827">$<?= number_format($pedido['total'], 2) ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase;margin-bottom:4px">Fecha entrega</div>
        <div style="font-size:.875rem;color:#374151"><?= $pedido['fecha_entrega'] ? date('d/m/Y', strtotime($pedido['fecha_entrega'])) : '—' ?></div>
      </div>
      <div>
        <div style="font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase;margin-bottom:4px">Creado</div>
        <div style="font-size:.875rem;color:#374151"><?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?></div>
      </div>
    </div>
    <?php if ($pedido['notas']): ?>
    <div style="margin-top:14px;padding:10px 14px;background:#F9FAFB;border-radius:8px;font-size:.875rem;color:#374151">
      <strong>Notas:</strong> <?= nl2br(htmlspecialchars($pedido['notas'])) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Productos -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:16px">
    <div style="padding:16px 20px;border-bottom:1px solid #E5E7EB">
      <h3 style="margin:0;font-size:.95rem;font-weight:700;color:#111827">Productos del pedido</h3>
    </div>
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:#F9FAFB">
          <th style="padding:10px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Producto</th>
          <th style="padding:10px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Cantidad</th>
          <th style="padding:10px 16px;text-align:right;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Precio unit.</th>
          <th style="padding:10px 16px;text-align:right;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedido['items'] as $item): ?>
        <tr style="border-bottom:1px solid #F3F4F6">
          <td style="padding:10px 16px">
            <div style="font-weight:600;font-size:.875rem;color:#111827"><?= htmlspecialchars($item['producto_nombre']) ?></div>
            <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($item['presentacion'] ?? '') ?></div>
          </td>
          <td style="padding:10px 16px;text-align:center;font-size:.875rem"><?= $item['cantidad'] ?></td>
          <td style="padding:10px 16px;text-align:right;font-size:.875rem">$<?= number_format($item['precio_unit'], 2) ?></td>
          <td style="padding:10px 16px;text-align:right;font-weight:700">$<?= number_format($item['subtotal'], 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#F9FAFB">
          <td colspan="3" style="padding:12px 16px;text-align:right;font-weight:700;font-size:.875rem">Total:</td>
          <td style="padding:12px 16px;text-align:right;font-weight:800;font-size:1rem;color:#111827">$<?= number_format($pedido['total'], 2) ?></td>
        </tr>
      </tbody>
    </table>
  </div>

  <!-- Sucursales -->
  <?php if (!empty($pedido['sucursales'])): ?>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="margin:0 0 14px;font-size:.95rem;font-weight:700;color:#111827">Sucursales de entrega</h3>
    <div style="display:flex;flex-wrap:wrap;gap:10px">
      <?php foreach ($pedido['sucursales'] as $suc): ?>
      <div style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:10px 16px">
        <div style="font-weight:600;font-size:.875rem;color:#111827"><?= htmlspecialchars($suc['sucursal_nombre']) ?></div>
        <div style="font-size:.75rem;color:#9CA3AF;margin-top:2px"><?= htmlspecialchars($suc['direccion'] ?? '') ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
function cambiarEstado(pedidoId, sel) {
  const estado = sel.value;
  if (!estado) return;
  if (!confirm('¿Cambiar estado a "' + estado.replace(/_/g,' ') + '"?')) { sel.value=''; return; }

  fetch('<?= BASE_URL ?>panel-pedido/cambiarEstado', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'pedido_id=' + pedidoId + '&estado=' + encodeURIComponent(estado)
  })
  .then(r => r.json())
  .then(d => { if (d.ok) location.reload(); else alert('Error: ' + d.msg); })
  .catch(() => alert('Error de conexión'));
}
</script>
