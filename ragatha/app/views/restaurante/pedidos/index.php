<?php ob_start(); ?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div style="display:flex;gap:8px">
    <?php foreach ([''=>'Todos','pendiente'=>'Pendientes','en_preparacion'=>'En prep.','listo'=>'Listos','entregado'=>'Entregados','cancelado'=>'Cancelados'] as $v => $l): ?>
    <a href="?estado=<?= $v ?>"
       style="padding:5px 12px;border-radius:99px;font-size:.8rem;font-weight:500;text-decoration:none;
         background:<?= $estado === $v ? 'var(--color-primary)' : '#F3F4F6' ?>;
         color:<?= $estado === $v ? '#fff' : '#374151' ?>">
      <?= $l ?>
    </a>
    <?php endforeach; ?>
  </div>
  <a href="<?= BASE_URL ?>rest-pedido/nuevo"
     style="padding:8px 14px;background:var(--color-primary);color:#fff;border-radius:8px;font-size:.85rem;font-weight:500;text-decoration:none">
    + Pedido
  </a>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Folio</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Mesa</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Mesero</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Estado</th>
        <th style="padding:12px 16px;text-align:right;font-weight:600;color:#374151">Total</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Hora</th>
        <th style="padding:12px 16px;text-align:left;font-weight:600;color:#374151">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $estadoColors = [
        'pendiente'=>['#DBEAFE','#1E40AF'],'en_preparacion'=>['#FEF3C7','#92400E'],
        'listo'=>['#DCFCE7','#166534'],'entregado'=>['#F3F4F6','#374151'],'cancelado'=>['#FEE2E2','#991B1B'],
      ];
      foreach ($data as $p): $cs = $estadoColors[$p['estado']] ?? ['#F3F4F6','#374151']; ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px;font-weight:600"><?= htmlspecialchars($p['folio']) ?></td>
        <td style="padding:12px 16px"><?= htmlspecialchars($p['mesa_nombre'] ?? '—') ?></td>
        <td style="padding:12px 16px;color:#6B7280"><?= htmlspecialchars($p['mesero_nombre'] ?? 'Auto') ?></td>
        <td style="padding:12px 16px">
          <span style="padding:2px 10px;border-radius:99px;font-size:.72rem;font-weight:600;background:<?= $cs[0] ?>;color:<?= $cs[1] ?>">
            <?= $p['estado'] ?>
          </span>
        </td>
        <td style="padding:12px 16px;text-align:right;font-weight:600">$<?= number_format((float)$p['total'],2) ?></td>
        <td style="padding:12px 16px;color:#6B7280;font-size:.8rem"><?= date('H:i', strtotime($p['created_at'])) ?></td>
        <td style="padding:12px 16px">
          <a href="<?= BASE_URL ?>rest-pedido/detalle/<?= $p['id'] ?>" style="font-size:.8rem;color:var(--color-primary);font-weight:500">Ver</a>
          <?php if ($p['estado'] !== 'cancelado' && $p['estado'] !== 'entregado'): ?>
          <a href="<?= BASE_URL ?>rest-pedido/cancelar/<?= $p['id'] ?>" onclick="return confirm('¿Cancelar pedido?')"
             style="margin-left:10px;font-size:.8rem;color:#EF4444">Cancelar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($data)): ?>
      <tr><td colspan="7" style="padding:32px;text-align:center;color:#9CA3AF">No hay pedidos.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
