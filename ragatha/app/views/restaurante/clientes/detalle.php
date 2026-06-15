<?php ob_start(); ?>
<div style="max-width:700px">
  <a href="<?= BASE_URL ?>rest-cliente/index" style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:20px">← Comensales</a>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:16px">
    <div style="font-size:1.1rem;font-weight:700;margin-bottom:4px"><?= htmlspecialchars($comensal['nombre'] ?? 'Visitante anónimo') ?></div>
    <div style="font-size:.85rem;color:#6B7280"><?= htmlspecialchars($comensal['telefono'] ?? '') ?> <?= htmlspecialchars($comensal['email'] ?? '') ?></div>
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-top:20px">
      <div style="background:#F9FAFB;border-radius:10px;padding:16px">
        <div style="font-size:.78rem;color:#6B7280">Visitas totales</div>
        <div style="font-size:1.3rem;font-weight:700"><?= (int)$comensal['total_visitas'] ?></div>
      </div>
      <div style="background:#F9FAFB;border-radius:10px;padding:16px">
        <div style="font-size:.78rem;color:#6B7280">Total gastado</div>
        <div style="font-size:1.3rem;font-weight:700">$<?= number_format((float)$comensal['total_gastado'],2) ?></div>
      </div>
      <div style="background:#F9FAFB;border-radius:10px;padding:16px">
        <div style="font-size:.78rem;color:#6B7280">Última visita</div>
        <div style="font-size:1rem;font-weight:600"><?= $comensal['ultima_visita'] ? date('d/m/Y', strtotime($comensal['ultima_visita'])) : '—' ?></div>
      </div>
    </div>
  </div>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
    <div style="padding:14px 20px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;font-weight:600">Historial</div>
    <?php foreach ($historial as $v): ?>
    <div style="padding:12px 20px;border-bottom:1px solid #F3F4F6;font-size:.875rem;display:flex;justify-content:space-between">
      <div>
        <div><?= date('d/m/Y H:i', strtotime($v['created_at'])) ?> <?= $v['mesa_nombre'] ? '· '.$v['mesa_nombre'] : '' ?></div>
        <div style="font-size:.78rem;color:#9CA3AF"><?= htmlspecialchars($v['metodo_pago'] ?? '') ?></div>
      </div>
      <div style="font-weight:600">$<?= number_format((float)($v['ticket_total'] ?? 0),2) ?></div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($historial)): ?><div style="padding:24px;text-align:center;color:#9CA3AF;font-size:.875rem">Sin historial.</div><?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
