<?php ob_start(); ?>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Nombre</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Teléfono</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Visitas</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Total gastado</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($top as $i => $c): ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px">
          <span style="display:inline-block;width:24px;height:24px;background:<?= $i < 3 ? '#FEF3C7' : '#F3F4F6' ?>;color:<?= $i < 3 ? '#92400E' : '#374151' ?>;border-radius:50%;text-align:center;line-height:24px;font-size:.75rem;font-weight:700;margin-right:8px"><?= $i+1 ?></span>
          <?= htmlspecialchars($c['nombre'] ?? 'Visitante') ?>
        </td>
        <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($c['telefono'] ?? '—') ?></td>
        <td style="padding:12px 16px;text-align:right"><?= (int)$c['total_visitas'] ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:600">$<?= number_format((float)$c['total_gastado'],2) ?></td>
        <td style="padding:12px 16px">
          <a href="<?= BASE_URL ?>rest-cliente/detalle/<?= $c['id'] ?>" style="font-size:.8rem;color:var(--color-primary);font-weight:500">Ver →</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
