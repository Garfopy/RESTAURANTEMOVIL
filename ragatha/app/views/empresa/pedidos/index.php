<?php
// Vista: Historial de pedidos del comprador / admin empresa (versión simple)
$estados = [
    'pendiente'       => ['label'=>'Pendiente',       'bg'=>'#FEF3C7','color'=>'#92400E','dot'=>'#D97706'],
    'confirmado'      => ['label'=>'Confirmado',       'bg'=>'#DBEAFE','color'=>'#1E40AF','dot'=>'#3B82F6'],
    'en_preparacion'  => ['label'=>'En preparación',   'bg'=>'#EDE9FE','color'=>'#5B21B6','dot'=>'#8B5CF6'],
    'en_ruta'         => ['label'=>'En ruta',           'bg'=>'#FEF3C7','color'=>'#B45309','dot'=>'#F59E0B'],
    'entregado'       => ['label'=>'Entregado',         'bg'=>'#D1FAE5','color'=>'#065F46','dot'=>'#10B981'],
    'cancelado'       => ['label'=>'Cancelado',         'bg'=>'#FEE2E2','color'=>'#991B1B','dot'=>'#DC2626'],
];
$rol = $_SESSION['usuario']['rol_slug'] ?? '';
$puedeComprar = in_array($rol, ['admin_empresa','comprador'], true);
?>
<style>
.mp-toolbar {
  display:flex;gap:10px;margin-bottom:18px;flex-wrap:wrap;align-items:center;
  background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:10px 12px;
  box-shadow:0 1px 3px rgba(0,0,0,.04);
}
.mp-input, .mp-select {
  padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;
  background:#fff;color:#111827;outline:none;transition:border-color .15s, box-shadow .15s;
  font-family:inherit;
}
.mp-input { flex:1;min-width:200px }
.mp-input:focus, .mp-select:focus {
  border-color:var(--color-primary);
  box-shadow:0 0 0 3px rgba(200,16,46,.12);
}
.mp-btn-filter {
  padding:9px 18px;background:#1F2937;color:#fff;border:none;border-radius:8px;
  font-weight:600;font-size:.875rem;cursor:pointer;font-family:inherit;
  display:inline-flex;align-items:center;gap:6px;transition:background .15s;
}
.mp-btn-filter:hover { background:#111827 }
.mp-btn-new {
  padding:9px 18px;background:var(--color-primary);color:#fff;border-radius:8px;
  text-decoration:none;font-weight:700;font-size:.875rem;margin-left:auto;
  display:inline-flex;align-items:center;gap:6px;
  box-shadow:0 4px 12px rgba(200,16,46,.25);transition:transform .12s, box-shadow .15s, background .15s;
}
.mp-btn-new:hover { background:#A00D24;transform:translateY(-1px);box-shadow:0 6px 16px rgba(200,16,46,.35) }

.mp-table-wrap {
  background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;
  box-shadow:0 4px 14px rgba(15,23,42,.05);
}
.mp-table { width:100%;border-collapse:separate;border-spacing:0;font-size:.875rem }
.mp-table thead tr {
  background:linear-gradient(180deg,#FAFBFC 0%,#F3F4F6 100%);
}
.mp-table th {
  padding:13px 16px;text-align:left;color:#374151;font-weight:700;
  font-size:.7rem;letter-spacing:.08em;text-transform:uppercase;
  border-bottom:2px solid #E5E7EB;white-space:nowrap;
}
.mp-table th.center { text-align:center }
.mp-table th.right  { text-align:right }
.mp-table tbody tr {
  border-bottom:1px solid #E5E7EB;transition:background .15s, box-shadow .15s;
}
.mp-table tbody tr:nth-child(even) { background:#FAFBFC }
.mp-table tbody tr:hover {
  background:#FFF5F6;
  box-shadow:inset 4px 0 0 var(--color-primary);
}
.mp-table tbody tr:last-child { border-bottom:none }
.mp-table td { padding:14px 16px;color:#374151;border-bottom:1px solid #F3F4F6;vertical-align:middle }
.mp-table tbody tr:last-child td { border-bottom:none }
.mp-table td.right  { text-align:right }
.mp-table td.center { text-align:center }

.mp-folio {
  font-family:'JetBrains Mono', ui-monospace, SFMono-Regular, Menlo, monospace;
  font-weight:800;font-size:.85rem;color:var(--color-primary);
  text-decoration:none;letter-spacing:.02em;
}
.mp-folio:hover { text-decoration:underline }
.mp-total { font-weight:800;color:#111827;font-size:.95rem }
.mp-fecha { color:#374151;font-weight:500 }
.mp-fecha-small { color:#9CA3AF;font-size:.78rem }

.mp-badge {
  display:inline-flex;align-items:center;gap:6px;
  padding:4px 11px;border-radius:999px;font-size:.72rem;font-weight:700;
  white-space:nowrap;border:1px solid transparent;
}
.mp-badge .dot { width:6px;height:6px;border-radius:50%;flex-shrink:0 }

.mp-flag-aprob {
  display:inline-flex;align-items:center;gap:4px;
  margin-top:4px;padding:1px 7px;border-radius:999px;
  background:#FEF3C7;color:#92400E;font-size:.65rem;font-weight:700;
}

.mp-action {
  display:inline-flex;align-items:center;gap:5px;
  padding:6px 12px;border-radius:7px;font-size:.78rem;font-weight:700;
  text-decoration:none;border:1px solid transparent;
  transition:background .15s, border-color .15s, color .15s, transform .1s;
  font-family:inherit;
}
.mp-action.ver {
  background:#F9FAFB;color:#374151;border-color:#E5E7EB;
}
.mp-action.ver:hover { background:#fff;border-color:var(--color-primary);color:var(--color-primary) }
.mp-action.rastrear {
  background:var(--color-primary);color:#fff;border-color:var(--color-primary);
  box-shadow:0 2px 6px rgba(200,16,46,.25);
}
.mp-action.rastrear:hover { background:#A00D24;transform:translateY(-1px) }

.mp-empty {
  background:#fff;border:1px dashed #E5E7EB;border-radius:14px;
  padding:48px 24px;text-align:center;color:#6B7280;
}

.mp-pagination { display:flex;justify-content:center;gap:6px;margin-top:16px;flex-wrap:wrap }
.mp-page {
  padding:7px 13px;border-radius:8px;font-size:.85rem;text-decoration:none;font-weight:600;
  background:#F3F4F6;color:#374151;border:1px solid #E5E7EB;transition:all .15s;
}
.mp-page:hover { background:#E5E7EB;color:#111827 }
.mp-page.active {
  background:var(--color-primary);color:#fff;border-color:var(--color-primary);
  box-shadow:0 2px 8px rgba(200,16,46,.3);
}
</style>

<!-- Filtros -->
<form method="GET" class="mp-toolbar">
  <input type="text" name="buscar" class="mp-input" placeholder="Folio o comprador..." value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>">
  <select name="estado" class="mp-select">
    <option value="">Todos los estados</option>
    <?php foreach ($estados as $k => $v): ?>
    <option value="<?= $k ?>" <?= ($filtros['estado'] ?? '') === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="mp-btn-filter">
    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2a1 1 0 01-.293.707L13 13.414V19a1 1 0 01-.553.894l-4 2A1 1 0 017 21v-7.586L3.293 6.707A1 1 0 013 6V4z"/></svg>
    Filtrar
  </button>
  <?php if ($puedeComprar): ?>
  <a href="<?= BASE_URL ?>carrito/index" class="mp-btn-new">+ Nuevo pedido</a>
  <?php endif; ?>
</form>

<?php if (empty($pedidos)): ?>
<div class="mp-empty">
  <div style="font-size:2.4rem;line-height:1;margin-bottom:10px">📋</div>
  <h3 style="font-size:1rem;font-weight:700;color:#111827;margin:0 0 6px">No hay pedidos registrados</h3>
  <?php if ($puedeComprar): ?>
  <p style="margin:0 0 14px;font-size:.875rem">Comienza explorando el catálogo y agrega productos al carrito.</p>
  <a href="<?= BASE_URL ?>carrito/index" class="mp-btn-new" style="margin-left:0;display:inline-flex">Hacer el primer pedido</a>
  <?php endif; ?>
</div>
<?php else: ?>
<div class="mp-table-wrap">
  <table class="mp-table">
    <thead>
      <tr>
        <th>Folio</th>
        <th>Comprador</th>
        <th>Fecha entrega</th>
        <th class="right">Total</th>
        <th class="center">Estado</th>
        <th>Fecha</th>
        <th class="center">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pedidos as $p): ?>
      <?php $est = $estados[$p['estado']] ?? ['label'=>$p['estado'],'bg'=>'#F3F4F6','color'=>'#374151','dot'=>'#9CA3AF']; ?>
      <tr>
        <td>
          <a href="<?= BASE_URL ?>pedido/detalle/<?= $p['id'] ?>" class="mp-folio">
            <?= htmlspecialchars($p['folio']) ?>
          </a>
          <?php if ($p['requiere_aprobacion'] && $p['estado'] === 'pendiente'): ?>
          <div><span class="mp-flag-aprob">⏳ Pendiente aprobación</span></div>
          <?php endif; ?>
        </td>
        <td><?= htmlspecialchars($p['comprador_nombre'] . ' ' . $p['comprador_apellido']) ?></td>
        <td class="mp-fecha"><?= $p['fecha_entrega'] ? date('d/m/Y', strtotime($p['fecha_entrega'])) : '—' ?></td>
        <td class="right mp-total">$<?= number_format($p['total'], 2) ?></td>
        <td class="center">
          <span class="mp-badge" style="background:<?= $est['bg'] ?>;color:<?= $est['color'] ?>;border-color:<?= $est['color'] ?>22">
            <span class="dot" style="background:<?= $est['dot'] ?>"></span>
            <?= $est['label'] ?>
          </span>
        </td>
        <td class="mp-fecha-small"><?= date('d/m/Y', strtotime($p['created_at'])) ?></td>
        <td class="center">
          <a href="<?= BASE_URL ?>pedido/detalle/<?= $p['id'] ?>" class="mp-action ver">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Ver
          </a>
          <?php if (in_array($p['estado'], ['en_ruta','en_preparacion'], true)): ?>
          <a href="<?= BASE_URL ?>pedido/tracking/<?= $p['id'] ?>" class="mp-action rastrear">
            <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Rastrear
          </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<!-- Paginación -->
<?php if (($paginacion['last_page'] ?? 1) > 1): ?>
<div class="mp-pagination">
  <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
  <a href="?page=<?= $i ?>&buscar=<?= urlencode($filtros['buscar'] ?? '') ?>&estado=<?= urlencode($filtros['estado'] ?? '') ?>"
     class="mp-page <?= $i === $paginacion['current_page'] ? 'active' : '' ?>">
    <?= $i ?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>

