<?php ob_start(); ?>
<div style="max-width:600px;margin:0 auto">
  <a href="<?= BASE_URL ?>rest-ticket/index" style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:20px">← Tickets</a>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:28px">
    <div style="text-align:center;margin-bottom:20px">
      <div style="font-size:1.3rem;font-weight:700"><?= htmlspecialchars($ticket['folio']) ?></div>
      <div style="color:#6B7280;font-size:.875rem"><?= htmlspecialchars($ticket['mesa_nombre'] ?? '') ?></div>
    </div>

    <?php if (!empty($todoItems)): ?>
    <table style="width:100%;border-collapse:collapse;margin-bottom:16px;font-size:.875rem">
      <thead>
        <tr style="border-bottom:2px solid #E5E7EB;color:#6B7280">
          <th style="text-align:left;padding:6px 4px">Platillo</th>
          <th style="text-align:center;padding:6px 4px">Cant.</th>
          <th style="text-align:right;padding:6px 4px">P.Unit</th>
          <th style="text-align:right;padding:6px 4px">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($todoItems as $it): ?>
        <tr style="border-bottom:1px solid #F3F4F6">
          <td style="padding:7px 4px">
            <?= htmlspecialchars($it['platillo_nombre']) ?>
            <?php if (!empty($it['exclusiones'])): ?>
              <div style="font-size:.75rem;color:#EF4444">Sin: <?= htmlspecialchars($it['exclusiones']) ?></div>
            <?php endif; ?>
            <?php if (!empty($it['notas'])): ?>
              <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($it['notas']) ?></div>
            <?php endif; ?>
          </td>
          <td style="text-align:center;padding:7px 4px"><?= (int)$it['cantidad'] ?></td>
          <td style="text-align:right;padding:7px 4px">$<?= number_format((float)$it['precio_unit'],2) ?></td>
          <td style="text-align:right;padding:7px 4px">$<?= number_format((float)$it['subtotal'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
      <span>Propina</span><span style="color:#10B981">$<?= number_format((float)$ticket['propina'],2) ?></span>
    </div>
    <div style="display:flex;justify-content:space-between;padding:12px 0;font-size:1.1rem;font-weight:700">
      <span>Total</span><span>$<?= number_format((float)$ticket['total'],2) ?></span>
    </div>

    <?php if ($ticket['estado'] === 'pendiente'): ?>
    <form method="POST" action="<?= BASE_URL ?>rest-ticket/confirmarPago/<?= $ticket['id'] ?>" style="margin-top:16px">
      <div style="margin-bottom:12px">
        <label style="font-size:.85rem;font-weight:500">Método de pago</label>
        <select name="metodo_pago" style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
          <option value="efectivo">Efectivo</option>
          <option value="tarjeta">Tarjeta</option>
          <option value="transferencia">Transferencia</option>
          <option value="paypal">PayPal</option>
        </select>
      </div>
      <button type="submit"
        style="width:100%;padding:12px;background:var(--color-primary);color:#fff;border:none;border-radius:10px;font-weight:700;font-size:1rem;cursor:pointer">
        Confirmar pago ✓
      </button>
    </form>
    <?php else: ?>
    <div style="text-align:center;margin-top:16px;padding:12px;background:#DCFCE7;border-radius:10px;color:#166534;font-weight:600">
      ✅ PAGADO — <?= htmlspecialchars($ticket['metodo_pago'] ?? '') ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
