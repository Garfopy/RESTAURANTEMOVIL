<?php ob_start(); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div></div>
  <button onclick="document.getElementById('modalCorte').style.display='flex'"
    style="padding:8px 16px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:500;cursor:pointer">
    + Hacer Corte de Caja
  </button>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Turno</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Ingresos</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Gastos</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Retiros</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Utilidad</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $c): ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px;font-weight:500"><?= htmlspecialchars($c['turno']) ?></td>
        <td style="padding:12px 16px;text-align:right;color:#10B981;font-weight:600">$<?= number_format((float)$c['ingresos'],2) ?></td>
        <td style="padding:12px 16px;text-align:right;color:#EF4444;font-weight:600">$<?= number_format((float)$c['gastos'],2) ?></td>
        <td style="padding:12px 16px;text-align:right;color:#F59E0B;font-weight:600">$<?= number_format((float)$c['retiros'],2) ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:700;color:<?= (float)$c['utilidad_neta'] >= 0 ? '#10B981' : '#EF4444' ?>">
          $<?= number_format((float)$c['utilidad_neta'],2) ?>
        </td>
        <td style="padding:12px 16px;color:#6B7280"><?= date('d/m/Y H:i', strtotime($c['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($data)): ?>
      <tr><td colspan="6" style="padding:32px;text-align:center;color:#9CA3AF">No hay cortes registrados.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<div id="modalCorte" class="rst-modal-backdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center;overflow-y:auto;padding:20px 0">
  <div style="background:#fff;border-radius:16px;padding:28px;width:440px;max-width:95vw;max-height:90vh;overflow-y:auto;margin:auto">
    <h3 style="font-weight:700;margin-bottom:18px">Corte de Caja</h3>
    <form method="POST" action="<?= BASE_URL ?>rest-finanzas/guardarCorte">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
        <div>
          <label style="font-size:.85rem;font-weight:500">Desde</label>
          <input type="date" name="desde" value="<?= date('Y-m-d') ?>"
            style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
        </div>
        <div>
          <label style="font-size:.85rem;font-weight:500">Hasta</label>
          <input type="date" name="hasta" value="<?= date('Y-m-d') ?>"
            style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
        </div>
      </div>
      <div style="margin-bottom:12px">
        <label style="font-size:.85rem;font-weight:500">Turno</label>
        <select name="turno"
          style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
          <option value="General">General</option>
          <option value="Matutino">Matutino</option>
          <option value="Vespertino">Vespertino</option>
          <option value="Nocturno">Nocturno</option>
        </select>
      </div>
      <div style="margin-bottom:18px">
        <label style="font-size:.85rem;font-weight:500">Notas</label>
        <textarea name="notas" rows="2"
          style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem;resize:vertical"></textarea>
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <button type="button" onclick="document.getElementById('modalCorte').style.display='none'"
          style="padding:8px 16px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;cursor:pointer;background:#fff">Cancelar</button>
        <button type="submit"
          style="padding:8px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:500;cursor:pointer">Generar Corte</button>
      </div>
    </form>
  </div>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
