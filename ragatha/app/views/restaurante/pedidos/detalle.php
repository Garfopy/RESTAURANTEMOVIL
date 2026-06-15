<?php ob_start(); ?>
<div style="max-width:700px">
  <a href="<?= BASE_URL ?>rest-pedido/index" style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:20px">← Pedidos</a>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:16px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div>
        <div style="font-size:1.1rem;font-weight:700"><?= htmlspecialchars($pedido['folio']) ?></div>
        <div style="font-size:.85rem;color:#6B7280"><?= htmlspecialchars($pedido['mesa_nombre'] ?? 'Sin mesa') ?></div>
      </div>
      <?php $cs = ['pendiente'=>['#DBEAFE','#1E40AF'],'en_preparacion'=>['#FEF3C7','#92400E'],'listo'=>['#DCFCE7','#166534'],'entregado'=>['#F3F4F6','#374151'],'cancelado'=>['#FEE2E2','#991B1B']][$pedido['estado']] ?? ['#F3F4F6','#374151']; ?>
      <span style="padding:4px 14px;border-radius:99px;font-size:.8rem;font-weight:700;background:<?= $cs[0] ?>;color:<?= $cs[1] ?>">
        <?= $pedido['estado'] ?>
      </span>
    </div>

    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <?php foreach ($pedido['items'] as $item): ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:10px 0;font-weight:500"><?= htmlspecialchars($item['platillo_nombre']) ?></td>
        <td style="padding:10px;text-align:center;color:#6B7280">x<?= (int)$item['cantidad'] ?></td>
        <td style="padding:10px 0;text-align:right;color:#6B7280">$<?= number_format((float)$item['precio_unit'],2) ?></td>
        <td style="padding:10px 0;text-align:right;font-weight:600">$<?= number_format((float)$item['subtotal'],2) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="3" style="padding:12px 0;font-weight:700;font-size:1rem;text-align:right">Total</td>
        <td style="padding:12px 0;font-weight:700;font-size:1rem;text-align:right">$<?= number_format((float)$pedido['total'],2) ?></td>
      </tr>
    </table>
  </div>

  <?php if ($pedido['estado'] !== 'cancelado' && $pedido['estado'] !== 'entregado'): ?>
  <div style="display:flex;gap:10px">
    <a href="<?= BASE_URL ?>rest-pedido/cancelar/<?= $pedido['id'] ?>" onclick="return confirm('¿Cancelar?')"
       style="padding:8px 16px;background:#FEE2E2;color:#991B1B;border-radius:8px;font-size:.875rem;font-weight:500;text-decoration:none">
      Cancelar pedido
    </a>
    <?php if ($pedido['visita_id']): ?>
    <a href="<?= BASE_URL ?>rest-ticket/generar/<?= $pedido['visita_id'] ?>"
       style="padding:8px 16px;background:var(--color-primary);color:#fff;border-radius:8px;font-size:.875rem;font-weight:500;text-decoration:none">
      Generar ticket
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
