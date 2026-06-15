<?php ob_start(); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div></div>
  <button onclick="document.getElementById('modalRet').classList.add('open')"
    class="btn btn-primary btn-sm">
    + Registrar Retiro
  </button>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
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
      <?php foreach ($data as $r): ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px"><?= htmlspecialchars($r['descripcion']) ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:600;color:#F59E0B">$<?= number_format((float)$r['monto'],2) ?></td>
        <td style="padding:12px 16px;color:#6B7280"><?= date('d/m/Y H:i', strtotime($r['created_at'])) ?></td>
        <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($r['usuario_nombre'] ?? '') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($data)): ?>
      <tr><td colspan="4" style="padding:32px;text-align:center;color:#9CA3AF">No hay retiros registrados.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

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
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
