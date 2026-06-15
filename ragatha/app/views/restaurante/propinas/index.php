<?php ob_start(); ?>
<style>
  .prop-card { background:#fff; border-radius:14px; border:1.5px solid #E5E7EB; padding:20px; margin-bottom:10px; }
  .prop-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:.72rem; font-weight:700; }
  .badge-pendiente { background:#FEF3C7; color:#92400E; }
  .badge-ok        { background:#DCFCE7; color:#166534; }
  .btn-entregar    { padding:6px 14px; border-radius:8px; font-size:.8rem; font-weight:600; cursor:pointer;
                     border:none; background:#10B981; color:#fff; transition:.15s; }
  .btn-entregar:hover   { background:#059669; }
  .btn-entregar:disabled { opacity:.45; cursor:not-allowed; }
  .btn-todas { padding:8px 18px; border-radius:8px; font-size:.82rem; font-weight:700; cursor:pointer;
               border:none; background:#3B82F6; color:#fff; transition:.15s; }
  .btn-todas:hover { background:#2563EB; }
</style>

<div class="container-fluid" style="max-width:900px">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
    <h2 style="font-size:1.3rem;font-weight:700;color:#111827">💰 Propinas por Mesero</h2>
    <!-- Filtro de fechas -->
    <form method="GET" action="<?= BASE_URL ?>rest-propinas/index"
          style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <label style="font-size:.82rem;color:#6B7280">Desde
        <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"
               style="margin-left:4px;padding:5px 8px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.85rem">
      </label>
      <label style="font-size:.82rem;color:#6B7280">Hasta
        <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"
               style="margin-left:4px;padding:5px 8px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.85rem">
      </label>
      <button type="submit"
              style="padding:6px 14px;border-radius:8px;border:none;background:#111827;color:#fff;
                     font-size:.82rem;font-weight:600;cursor:pointer">
        Filtrar
      </button>
    </form>
  </div>

  <?php if (empty($meseros) && (float)($sinMesero['total_propinas'] ?? 0) == 0): ?>
  <div style="background:#F9FAFB;border-radius:14px;padding:40px;text-align:center;color:#9CA3AF">
    Sin propinas registradas para el período seleccionado.
  </div>

  <?php else: ?>

  <?php foreach ($meseros as $m): ?>
  <?php $pendiente = (float)$m['propinas_pendientes']; ?>
  <div class="prop-card">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-weight:700;font-size:1rem;color:#111827">
          👤 <?= htmlspecialchars($m['mesero_nombre']) ?>
        </div>
        <div style="font-size:.8rem;color:#6B7280;margin-top:3px">
          <?= (int)$m['total_tickets'] ?> ticket<?= $m['total_tickets'] != 1 ? 's' : '' ?> con propina
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:1.4rem;font-weight:800;color:#111827">
          $<?= number_format((float)$m['total_propinas'], 2) ?>
        </div>
        <div style="font-size:.78rem;margin-top:2px">
          <?php if ($pendiente > 0): ?>
          <span class="prop-badge badge-pendiente">
            Pendiente $<?= number_format($pendiente, 2) ?>
          </span>
          <?php else: ?>
          <span class="prop-badge badge-ok">✓ Todo entregado</span>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php if ($pendiente > 0): ?>
    <div style="margin-top:14px;display:flex;gap:8px;align-items:center;flex-wrap:wrap">
      <button class="btn-todas"
              onclick="marcarTodas(<?= (int)$m['mesero_id'] ?>, this)"
              data-mesero="<?= (int)$m['mesero_id'] ?>">
        ✓ Marcar $<?= number_format($pendiente, 2) ?> como entregada
      </button>
      <span style="font-size:.78rem;color:#9CA3AF">
        (<?= (int)$m['tickets_pendientes'] ?> ticket<?= $m['tickets_pendientes'] != 1 ? 's' : '' ?> pendiente<?= $m['tickets_pendientes'] != 1 ? 's' : '' ?>)
      </span>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php if (!empty($sinMesero) && (float)$sinMesero['total_propinas'] > 0): ?>
  <div class="prop-card" style="border-style:dashed">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <div style="font-weight:700;font-size:1rem;color:#6B7280">⚠️ Sin mesero asignado</div>
        <div style="font-size:.8rem;color:#9CA3AF;margin-top:3px">
          Pedidos sin mesero registrado · <?= (int)$sinMesero['total_tickets'] ?> ticket<?= $sinMesero['total_tickets'] != 1 ? 's' : '' ?>
        </div>
      </div>
      <div style="text-align:right">
        <div style="font-size:1.4rem;font-weight:800;color:#6B7280">
          $<?= number_format((float)$sinMesero['total_propinas'], 2) ?>
        </div>
        <?php if ((float)$sinMesero['propinas_pendientes'] > 0): ?>
        <span class="prop-badge badge-pendiente">
          Pendiente $<?= number_format((float)$sinMesero['propinas_pendientes'], 2) ?>
        </span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; ?>

</div>

<script>
const BASE = '<?= BASE_URL ?>';
const DESDE = '<?= htmlspecialchars($desde) ?>';
const HASTA = '<?= htmlspecialchars($hasta) ?>';

async function marcarTodas(meseroId, btn) {
  if (!confirm('¿Marcar todas las propinas pendientes de este mesero como entregadas?')) return;
  btn.disabled = true;
  btn.textContent = '...';
  try {
    const body = new URLSearchParams({ desde: DESDE, hasta: HASTA });
    const res = await fetch(`${BASE}rest-propinas/marcarTodasEntregadas/${meseroId}`, {
      method: 'POST',
      credentials: 'same-origin',
      body,
    });
    const data = await res.json();
    if (data.ok) {
      location.reload();
    } else {
      alert('Error al actualizar');
      btn.disabled = false;
      btn.textContent = '✓ Marcar como entregada';
    }
  } catch (e) {
    alert('Error de red');
    btn.disabled = false;
  }
}
</script>

<?php $content = ob_get_clean(); ?>
<?php require ROOT_PATH . '/app/views/restaurante/layout.php'; ?>
