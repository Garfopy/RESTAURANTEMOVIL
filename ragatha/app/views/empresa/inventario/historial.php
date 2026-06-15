<?php
$baseUrl = BASE_URL;
?>

<!-- Flash -->
<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Encabezado producto -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:12px">
    <?php if (!empty($producto['imagen'])): ?>
      <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px;border:1px solid #E5E7EB">
    <?php endif; ?>
    <div>
      <div style="font-size:1rem;font-weight:700;color:#111827"><?= htmlspecialchars($producto['nombre']) ?></div>
      <div style="font-size:.8rem;color:#6B7280">Stock actual: <strong style="color:<?= (float)$producto['stock_actual'] <= 0 ? '#DC2626' : '#059669' ?>"><?= number_format((float)$producto['stock_actual'], 1) ?> <?= $producto['presentacion'] ?></strong></div>
    </div>
  </div>
  <a href="<?= $baseUrl ?>empresa-inventario"
     style="padding:8px 16px;border:1px solid #D1D5DB;border-radius:8px;color:#374151;text-decoration:none;font-size:.85rem">
    ← Volver
  </a>
</div>

<!-- Tabla de movimientos -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
  <?php if (empty($items)): ?>
    <div style="padding:48px;text-align:center;color:#9CA3AF">Sin movimientos registrados para este producto.</div>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Tipo</th>
        <th style="padding:10px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Cantidad</th>
        <th style="padding:10px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Antes</th>
        <th style="padding:10px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Después</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Motivo / Referencia</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Usuario</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $m): ?>
      <?php
        [$tipoBg, $tipoTx, $tipoLabel] = match ($m['tipo']) {
          'entrada' => ['#D1FAE5','#065F46','↑ Entrada'],
          'salida'  => ['#FEE2E2','#991B1B','↓ Salida'],
          'merma'   => ['#FEF3C7','#92400E','⚠ Merma'],
          default   => ['#E0E7FF','#3730A3','⟳ Ajuste'],
        };
        $delta = (float)$m['stock_despues'] - (float)$m['stock_antes'];
      ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:10px 16px">
          <span style="padding:2px 8px;border-radius:999px;background:<?= $tipoBg ?>;color:<?= $tipoTx ?>;font-size:.7rem;font-weight:700"><?= $tipoLabel ?></span>
        </td>
        <td style="padding:10px 16px;text-align:right;font-size:.85rem;font-weight:700;color:<?= $delta >= 0 ? '#059669' : '#DC2626' ?>">
          <?= $delta >= 0 ? '+' : '' ?><?= number_format((float)$m['cantidad'], 1) ?> <span style="font-size:.7rem;color:#9CA3AF;font-weight:400"><?= $producto['presentacion'] ?></span>
        </td>
        <td style="padding:10px 16px;text-align:right;font-size:.8rem;color:#9CA3AF"><?= number_format((float)$m['stock_antes'], 1) ?></td>
        <td style="padding:10px 16px;text-align:right;font-size:.85rem;font-weight:600;color:#111827"><?= number_format((float)$m['stock_despues'], 1) ?></td>
        <td style="padding:10px 16px">
          <?php if ($m['motivo']): ?>
            <div style="font-size:.8rem;color:#374151"><?= htmlspecialchars($m['motivo']) ?></div>
          <?php endif; ?>
          <?php if ($m['referencia']): ?>
            <div style="font-size:.7rem;color:#9CA3AF"><?= htmlspecialchars($m['referencia']) ?></div>
          <?php endif; ?>
          <?php if (!$m['motivo'] && !$m['referencia']): ?>
            <span style="font-size:.75rem;color:#D1D5DB">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:10px 16px;font-size:.75rem;color:#6B7280"><?= htmlspecialchars($m['usuario_nombre']) ?></td>
        <td style="padding:10px 16px;font-size:.75rem;color:#9CA3AF"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Paginación -->
  <?php if (($paginacion['total_pages'] ?? 1) > 1): ?>
  <div style="padding:16px;display:flex;justify-content:center;gap:4px;border-top:1px solid #E5E7EB">
    <?php for ($i = 1; $i <= $paginacion['total_pages']; $i++): ?>
      <a href="?page=<?= $i ?>"
         style="padding:6px 12px;border-radius:6px;font-size:.8rem;text-decoration:none;<?= $i === ($paginacion['current_page'] ?? 1) ? 'background:var(--color-primary);color:#fff' : 'background:#F3F4F6;color:#374151' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
