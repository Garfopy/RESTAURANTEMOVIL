<?php ob_start(); ?>
<a href="<?= BASE_URL ?>rest-inventario/index" style="font-size:.85rem;color:#6B7280;text-decoration:none;margin-bottom:20px;display:inline-block">← Inventario</a>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Ingrediente</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Tipo</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Cantidad</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Stock antes</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Stock después</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Motivo</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($data as $m): ?>
      <?php $colors=['entrada'=>['#DCFCE7','#166534'],'salida'=>['#FEE2E2','#991B1B'],'merma'=>['#FEF3C7','#92400E'],'ajuste'=>['#DBEAFE','#1E40AF']][$m['tipo']] ?? ['#F3F4F6','#374151']; ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px;font-weight:500"><?= htmlspecialchars($m['ingrediente_nombre']) ?></td>
        <td style="padding:12px 16px">
          <span style="padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:600;background:<?= $colors[0] ?>;color:<?= $colors[1] ?>">
            <?= $m['tipo'] ?>
          </span>
        </td>
        <td style="padding:12px 16px;text-align:right"><?= number_format((float)$m['cantidad'],3) ?></td>
        <td style="padding:12px 16px;text-align:right;color:#9CA3AF"><?= number_format((float)$m['stock_antes'],3) ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:500"><?= number_format((float)$m['stock_despues'],3) ?></td>
        <td style="padding:12px 16px;color:#6B7280;font-size:.8rem"><?= htmlspecialchars($m['motivo'] ?? '') ?></td>
        <td style="padding:12px 16px;color:#6B7280;font-size:.8rem"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($data)): ?>
      <tr><td colspan="7" style="padding:32px;text-align:center;color:#9CA3AF">Sin movimientos.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
