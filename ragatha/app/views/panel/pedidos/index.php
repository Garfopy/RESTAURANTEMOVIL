<?php
// Variables: $pedidos[], $paginacion, $empresas[], $filtros

function estadoBadge(string $estado): string {
    return match($estado) {
        'pendiente'      => '<span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Pendiente</span>',
        'confirmado'     => '<span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Confirmado</span>',
        'en_preparacion' => '<span style="background:#EDE9FE;color:#5B21B6;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">En prep.</span>',
        'en_ruta'        => '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">En ruta</span>',
        'entregado'      => '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Entregado</span>',
        'cancelado'      => '<span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Cancelado</span>',
        default          => '<span style="background:#F3F4F6;color:#374151;padding:2px 8px;border-radius:999px;font-size:.75rem">' . htmlspecialchars($estado) . '</span>',
    };
}
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <form method="GET" action="<?= BASE_URL ?>panel-pedido/index" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="buscar" value="<?= htmlspecialchars($filtros['buscar']) ?>"
           placeholder="Folio, empresa, comprador..."
           style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;outline:none;min-width:200px">
    <select name="empresa_id" style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      <option value="">Todas las empresas</option>
      <?php foreach ($empresas as $emp): ?>
      <option value="<?= $emp['id'] ?>" <?= $filtros['empresa_id'] == $emp['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($emp['razon_social']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="estado" style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      <option value="">Todos los estados</option>
      <?php foreach (['pendiente','confirmado','en_preparacion','en_ruta','entregado','cancelado'] as $e): ?>
      <option value="<?= $e ?>" <?= $filtros['estado'] === $e ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$e)) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit"
            style="padding:7px 16px;background:#374151;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer">Filtrar</button>
  </form>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Folio</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Empresa</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Comprador</th>
        <th style="padding:12px 16px;text-align:right;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Total</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Estado</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Fecha</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($pedidos)): ?>
      <tr><td colspan="7" style="padding:40px;text-align:center;color:#9CA3AF;font-size:.875rem">No hay pedidos.</td></tr>
      <?php endif; ?>
      <?php foreach ($pedidos as $ped): ?>
      <tr style="border-bottom:1px solid #F3F4F6" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='#fff'">
        <td style="padding:12px 16px;font-weight:700;font-size:.875rem;color:#111827;white-space:nowrap"><?= htmlspecialchars($ped['folio']) ?></td>
        <td style="padding:12px 16px;font-size:.875rem;color:#374151"><?= htmlspecialchars($ped['empresa_nombre']) ?></td>
        <td style="padding:12px 16px;font-size:.875rem;color:#374151">
          <?= htmlspecialchars($ped['comprador_nombre'] . ' ' . $ped['comprador_apellido']) ?>
        </td>
        <td style="padding:12px 16px;text-align:right;font-weight:700;color:#111827">$<?= number_format($ped['total'], 2) ?></td>
        <td style="padding:12px 16px;text-align:center"><?= estadoBadge($ped['estado']) ?></td>
        <td style="padding:12px 16px;font-size:.8rem;color:#6B7280;white-space:nowrap"><?= date('d/m/Y H:i', strtotime($ped['created_at'])) ?></td>
        <td style="padding:12px 16px;text-align:center;white-space:nowrap">
          <a href="<?= BASE_URL ?>panel-pedido/detalle/<?= $ped['id'] ?>"
             style="color:var(--color-primary);font-size:.8rem;font-weight:600;text-decoration:none;margin-right:8px">Ver</a>
          <select onchange="cambiarEstado(<?= $ped['id'] ?>, this)"
                  style="padding:3px 6px;border:1px solid #D1D5DB;border-radius:6px;font-size:.75rem;cursor:pointer">
            <option value="">Cambiar estado...</option>
            <?php foreach (['pendiente','confirmado','en_preparacion','en_ruta','entregado','cancelado'] as $e): ?>
            <option value="<?= $e ?>" <?= $ped['estado'] === $e ? 'disabled' : '' ?>><?= ucfirst(str_replace('_',' ',$e)) ?></option>
            <?php endforeach; ?>
          </select>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($paginacion['last_page'] > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
    <a href="?page=<?= $i ?>&buscar=<?= urlencode($filtros['buscar']) ?>&estado=<?= urlencode($filtros['estado']) ?>&empresa_id=<?= $filtros['empresa_id'] ?>"
       style="padding:6px 12px;border-radius:6px;font-size:.875rem;text-decoration:none;<?= $i === $paginacion['current_page'] ? 'background:var(--color-primary);color:#fff;font-weight:700' : 'background:#fff;border:1px solid #D1D5DB;color:#374151' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
function cambiarEstado(pedidoId, sel) {
  const estado = sel.value;
  if (!estado) return;
  if (!confirm('¿Cambiar estado a "' + estado.replace('_',' ') + '"?')) { sel.value=''; return; }

  fetch('<?= BASE_URL ?>panel-pedido/cambiarEstado', {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'pedido_id=' + pedidoId + '&estado=' + encodeURIComponent(estado)
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) location.reload();
    else alert('Error: ' + d.msg);
  })
  .catch(() => alert('Error de conexión'));
}
</script>
