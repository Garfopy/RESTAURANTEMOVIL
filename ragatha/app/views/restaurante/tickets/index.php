<?php ob_start(); ?>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Folio</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Mesa</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Total</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Propina</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Estado</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Método pago</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Fecha</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $t): ?>
      <?php $pagado = $t['estado'] === 'pagado'; ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px;font-weight:600"><?= htmlspecialchars($t['folio']) ?></td>
        <td style="padding:12px 16px"><?= htmlspecialchars($t['mesa_nombre'] ?? '—') ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:600">$<?= number_format((float)$t['total'],2) ?></td>
        <td style="padding:12px 16px;text-align:right;color:#10B981">$<?= number_format((float)$t['propina'],2) ?></td>
        <td style="padding:12px 16px">
          <span style="padding:2px 10px;border-radius:99px;font-size:.72rem;font-weight:600;
            background:<?= $pagado ? '#DCFCE7' : '#FEF3C7' ?>;color:<?= $pagado ? '#166534' : '#92400E' ?>">
            <?= $t['estado'] ?>
          </span>
        </td>
        <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($t['metodo_pago'] ?? '—') ?></td>
        <td style="padding:12px 16px;color:#6B7280;font-size:.8rem"><?= date('d/m H:i', strtotime($t['created_at'])) ?></td>
        <td style="padding:12px 16px">
          <a href="<?= BASE_URL ?>rest-ticket/detalle/<?= $t['id'] ?>" style="font-size:.8rem;color:var(--color-primary);font-weight:500">Ver</a>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($data)): ?>
      <tr><td colspan="8" style="padding:32px;text-align:center;color:#9CA3AF">No hay tickets.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
