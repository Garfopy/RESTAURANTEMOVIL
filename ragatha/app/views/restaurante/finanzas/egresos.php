<?php ob_start(); ?>
<?php
  $tabActivo   = $tab ?? 'gastos';
  $gastosData  = $resGastos['data']  ?? [];
  $retirosData = $resRetiros['data'] ?? [];
?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div style="font-size:.85rem;color:#6B7280">Registra y consulta gastos operativos y retiros de caja.</div>
  <div style="display:flex;gap:8px">
    <button onclick="document.getElementById('modalGasto').classList.add('open')"
      class="btn btn-outline btn-sm">+ Gasto</button>
    <button onclick="document.getElementById('modalRet').classList.add('open')"
      class="btn btn-primary btn-sm">+ Retiro</button>
  </div>
</div>

<!-- Flash -->
<?php if (!empty($flash)): ?>
<div style="margin-bottom:14px;padding:12px 16px;border-radius:8px;
     background:<?= $flash['type'] === 'success' ? '#DCFCE7' : '#FEE2E2' ?>;
     color:<?= $flash['type'] === 'success' ? '#166534' : '#991B1B' ?>;
     font-size:.875rem;font-weight:500">
  <?= htmlspecialchars($flash['msg'] ?? '') ?>
</div>
<?php endif; ?>

<!-- Tabs -->
<div style="display:flex;gap:0;margin-bottom:0;border-bottom:2px solid #E5E7EB">
  <a href="<?= BASE_URL ?>rest-finanzas/egresos?tab=gastos"
     style="padding:10px 20px;font-size:.9rem;font-weight:600;text-decoration:none;
            border-bottom:2px solid <?= $tabActivo === 'gastos' ? '#C8102E' : 'transparent' ?>;
            color:<?= $tabActivo === 'gastos' ? '#C8102E' : '#6B7280' ?>;
            margin-bottom:-2px;transition:.15s">
    💳 Gastos
    <span style="font-size:.72rem;background:#F3F4F6;color:#6B7280;border-radius:99px;
                 padding:1px 7px;margin-left:4px"><?= count($gastosData) ?></span>
  </a>
  <a href="<?= BASE_URL ?>rest-finanzas/egresos?tab=retiros"
     style="padding:10px 20px;font-size:.9rem;font-weight:600;text-decoration:none;
            border-bottom:2px solid <?= $tabActivo === 'retiros' ? '#C8102E' : 'transparent' ?>;
            color:<?= $tabActivo === 'retiros' ? '#C8102E' : '#6B7280' ?>;
            margin-bottom:-2px;transition:.15s">
    🏦 Retiros
    <span style="font-size:.72rem;background:#F3F4F6;color:#6B7280;border-radius:99px;
                 padding:1px 7px;margin-left:4px"><?= count($retirosData) ?></span>
  </a>
</div>

<!-- ── TAB GASTOS ──────────────────────────────────────────────── -->
<div id="panelGastos" style="<?= $tabActivo !== 'gastos' ? 'display:none' : '' ?>">
  <div style="background:#fff;border-radius:0 0 12px 12px;border:1px solid #E5E7EB;border-top:none;overflow:hidden;margin-bottom:16px">
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <thead>
        <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Descripción</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Categoría</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Fecha</th>
          <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Monto</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Registrado por</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($gastosData as $g): ?>
        <tr style="border-bottom:1px solid #F3F4F6">
          <td style="padding:12px 16px"><?= htmlspecialchars($g['descripcion']) ?></td>
          <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($g['categoria']) ?></td>
          <td style="padding:12px 16px;color:#6B7280"><?= $g['fecha'] ?></td>
          <td style="padding:12px 16px;text-align:right;font-weight:600;color:#EF4444">$<?= number_format((float)$g['monto'],2) ?></td>
          <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($g['usuario_nombre'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($gastosData)): ?>
        <tr><td colspan="5" style="padding:32px;text-align:center;color:#9CA3AF">No hay gastos registrados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── TAB RETIROS ─────────────────────────────────────────────── -->
<div id="panelRetiros" style="<?= $tabActivo !== 'retiros' ? 'display:none' : '' ?>">
  <div style="background:#fff;border-radius:0 0 12px 12px;border:1px solid #E5E7EB;border-top:none;overflow:hidden;margin-bottom:16px">
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <thead>
        <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Descripción</th>
          <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Monto</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Fecha</th>
          <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Registrado por</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($retirosData as $r): ?>
        <tr style="border-bottom:1px solid #F3F4F6">
          <td style="padding:12px 16px"><?= htmlspecialchars($r['descripcion']) ?></td>
          <td style="padding:12px 16px;text-align:right;font-weight:600;color:#F59E0B">$<?= number_format((float)$r['monto'],2) ?></td>
          <td style="padding:12px 16px;color:#6B7280"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
          <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($r['usuario_nombre'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($retirosData)): ?>
        <tr><td colspan="4" style="padding:32px;text-align:center;color:#9CA3AF">No hay retiros registrados.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Modal Gasto ──────────────────────────────────────────────── -->
<div id="modalGasto" class="rst-modal-backdrop">
  <div class="rst-modal rst-modal-sm">
    <div class="rst-modal-header">
      <div class="rst-modal-title">Registrar Gasto</div>
      <button class="rst-modal-close" onclick="document.getElementById('modalGasto').classList.remove('open')">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>rest-finanzas/guardarGasto">
      <div class="form-group">
        <label class="form-label">Descripción *</label>
        <input type="text" name="descripcion" class="form-input" required>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group">
          <label class="form-label">Categoría</label>
          <select name="categoria" class="form-input">
            <option value="personal">Personal</option>
            <option value="suministros">Suministros</option>
            <option value="mantenimiento">Mantenimiento</option>
            <option value="servicios">Servicios</option>
            <option value="propinas">Propinas</option>
            <option value="marketing">Marketing</option>
            <option value="otros">Otros</option>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Monto *</label>
          <input type="number" name="monto" step="0.01" min="0" class="form-input" required>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" value="<?= date('Y-m-d') ?>" class="form-input">
      </div>
      <div class="rst-modal-footer">
        <button type="button" onclick="document.getElementById('modalGasto').classList.remove('open')" class="btn btn-outline">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- ── Modal Retiro ─────────────────────────────────────────────── -->
<div id="modalRet" class="rst-modal-backdrop">
  <div class="rst-modal rst-modal-sm">
    <div class="rst-modal-header">
      <div class="rst-modal-title">Registrar Retiro</div>
      <button class="rst-modal-close" onclick="document.getElementById('modalRet').classList.remove('open')">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>rest-finanzas/guardarRetiro">
      <div class="form-group">
        <label class="form-label">Descripción *</label>
        <input type="text" name="descripcion" class="form-input" required>
      </div>
      <div class="form-group">
        <label class="form-label">Monto *</label>
        <input type="number" name="monto" step="0.01" min="0" class="form-input" required>
      </div>
      <div class="rst-modal-footer">
        <button type="button" onclick="document.getElementById('modalRet').classList.remove('open')" class="btn btn-outline">Cancelar</button>
        <button type="submit" class="btn btn-primary">Registrar</button>
      </div>
    </form>
  </div>
</div>

<script>
document.querySelectorAll('.rst-modal-backdrop').forEach(bd => {
  bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});
<?php if ($tabActivo === 'gastos' && !empty($flash)): ?>
document.getElementById('modalGasto') && null; // foco en tab correcto
<?php elseif ($tabActivo === 'retiros' && !empty($flash)): ?>
document.getElementById('modalRet') && null;
<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
