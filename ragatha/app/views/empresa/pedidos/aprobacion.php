<?php
// Vista: Aprobaciones pendientes (supervisor + admin_empresa)
?>
<?php if (empty($pendientes)): ?>
<div style="background:#fff;border-radius:12px;padding:48px;text-align:center;border:1px solid #E5E7EB">
  <div style="font-size:2.5rem;margin-bottom:12px">✅</div>
  <div style="font-weight:700;font-size:1.1rem;color:#111827;margin-bottom:6px">Sin pedidos pendientes</div>
  <p style="color:#6B7280;font-size:.9rem">No hay pedidos que requieran tu aprobación en este momento.</p>
</div>
<?php else: ?>
<p style="font-size:.85rem;color:#6B7280;margin-bottom:16px">
  <?= count($pendientes) ?> pedido(s) esperan tu aprobación.
</p>
<?php foreach ($pendientes as $p): ?>
<div style="background:#fff;border-radius:12px;border:1px solid #FCD34D;padding:16px 20px;margin-bottom:14px">
  <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap">
    <div style="flex:1">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:6px">
        <span style="font-size:1rem;font-weight:800;color:#111827;font-family:monospace"><?= htmlspecialchars($p['folio']) ?></span>
        <span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Pendiente aprobación</span>
      </div>
      <div style="font-size:.85rem;color:#6B7280">
        Solicitado por: <strong><?= htmlspecialchars($p['comprador_nombre'] . ' ' . $p['comprador_apellido']) ?></strong>
        · <?= date('d/m/Y H:i', strtotime($p['created_at'])) ?>
      </div>
      <?php if ($p['fecha_entrega']): ?>
      <div style="font-size:.85rem;color:#6B7280;margin-top:2px">
        Fecha de entrega solicitada: <strong><?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></strong>
      </div>
      <?php endif; ?>
      <?php if ($p['notas']): ?>
      <div style="font-size:.8rem;color:#6B7280;margin-top:4px;font-style:italic">"<?= htmlspecialchars(mb_strimwidth($p['notas'], 0, 100, '…')) ?>"</div>
      <?php endif; ?>
    </div>
    <div style="text-align:right">
      <div style="font-size:1.4rem;font-weight:800;color:var(--color-primary)">$<?= number_format($p['total'], 2) ?></div>
      <div style="font-size:.75rem;color:#9CA3AF;margin-bottom:12px"><?= ucfirst($p['metodo_pago'] ?? '') ?></div>
    </div>
  </div>

  <div style="display:flex;align-items:center;gap:10px;margin-top:14px;padding-top:14px;border-top:1px solid #FEF3C7;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>pedido/detalle/<?= $p['id'] ?>"
       style="padding:8px 16px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.85rem">
      Ver detalle
    </a>

    <!-- Aprobar -->
    <form method="POST" action="<?= BASE_URL ?>pedido/aprobar/<?= $p['id'] ?>" style="display:inline"
          onsubmit="return confirm('¿Aprobar el pedido <?= htmlspecialchars($p['folio']) ?>?')">
      <button type="submit" style="padding:8px 18px;background:#D1FAE5;color:#065F46;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer">
        ✓ Aprobar
      </button>
    </form>

    <!-- Rechazar (con modal de motivo) -->
    <button onclick="abrirRechazo(<?= $p['id'] ?>, '<?= htmlspecialchars($p['folio'], ENT_QUOTES) ?>')"
            style="padding:8px 18px;background:#FEE2E2;color:#991B1B;border:none;border-radius:8px;font-weight:700;font-size:.85rem;cursor:pointer">
      ✕ Rechazar
    </button>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Modal de rechazo -->
<div id="modalRechazo" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;max-width:440px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <h3 style="font-size:1rem;font-weight:700;margin-bottom:4px;color:#111827">Rechazar pedido</h3>
    <p id="modalFolio" style="font-size:.875rem;color:#6B7280;margin-bottom:16px"></p>

    <form id="formRechazo" method="POST">
      <div style="margin-bottom:14px">
        <label style="font-size:.8rem;font-weight:600;color:#374151;display:block;margin-bottom:4px">Motivo del rechazo *</label>
        <textarea name="motivo" rows="3" required placeholder="Explica brevemente el motivo..."
                  style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;resize:vertical"></textarea>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end">
        <button type="button" onclick="cerrarRechazo()" style="padding:9px 18px;background:#F3F4F6;color:#374151;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
          Cancelar
        </button>
        <button type="submit" style="padding:9px 18px;background:#EF4444;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.875rem;cursor:pointer">
          Rechazar pedido
        </button>
      </div>
    </form>
  </div>
</div>

<script>
function abrirRechazo(pedidoId, folio) {
  document.getElementById('formRechazo').action = '<?= BASE_URL ?>pedido/rechazar/' + pedidoId;
  document.getElementById('modalFolio').textContent = 'Pedido: ' + folio;
  document.getElementById('modalRechazo').style.display = 'flex';
}
function cerrarRechazo() {
  document.getElementById('modalRechazo').style.display = 'none';
}
</script>
