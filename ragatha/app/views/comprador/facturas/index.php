<?php
$fmt   = fn($n) => '$' . number_format((float)$n, 2, '.', ',');
$fecha = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
?>

<?php if (!empty($flash['success'])): ?>
<div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#166534">
  <?= htmlspecialchars($flash['success']) ?>
</div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#B91C1C">
  <?= htmlspecialchars($flash['error']) ?>
</div>
<?php endif; ?>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Mis facturas CFDI</h2>
    <?php if ($total > 0): ?>
      <span style="font-size:.8rem;color:#9CA3AF"><?= $total ?> factura<?= $total !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>

  <?php if (empty($facturas)): ?>
  <div style="padding:48px 20px;text-align:center">
    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.5" style="margin:0 auto 12px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <p style="color:#9CA3AF;font-size:.875rem;margin:0 0 8px">No tienes facturas todavía</p>
    <p style="color:#D1D5DB;font-size:.8rem">Las facturas aparecerán aquí cuando tu proveedor timbre un CFDI para tus pedidos entregados.</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.845rem">
      <thead>
        <tr style="border-bottom:2px solid #F3F4F6;background:#FAFAFA">
          <th style="text-align:left;padding:10px 16px;color:#6B7280;font-weight:600">UUID CFDI</th>
          <th style="text-align:left;padding:10px 12px;color:#6B7280;font-weight:600">Pedido</th>
          <th style="text-align:left;padding:10px 12px;color:#6B7280;font-weight:600">Fecha</th>
          <th style="text-align:right;padding:10px 12px;color:#6B7280;font-weight:600">Monto</th>
          <th style="text-align:center;padding:10px 12px;color:#6B7280;font-weight:600">Estado</th>
          <th style="text-align:center;padding:10px 16px;color:#6B7280;font-weight:600">Descargar</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($facturas as $f):
          $cancelada = ($f['estado'] ?? '') === 'cancelada';
        ?>
        <tr style="border-bottom:1px solid #F9FAFB;<?= $cancelada ? 'opacity:.55' : '' ?>">
          <td style="padding:10px 16px;font-family:monospace;font-size:.76rem;color:#374151;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
              title="<?= htmlspecialchars($f['uuid_cfdi'] ?? '') ?>">
            <?= htmlspecialchars(substr($f['uuid_cfdi'] ?? '—', 0, 20)) ?>…
          </td>
          <td style="padding:10px 12px;font-weight:600;color:#111827"><?= htmlspecialchars($f['pedido_folio'] ?? '—') ?></td>
          <td style="padding:10px 12px;color:#6B7280;white-space:nowrap"><?= $fecha($f['created_at']) ?></td>
          <td style="padding:10px 12px;text-align:right;font-weight:700"><?= $fmt($f['monto'] ?? 0) ?></td>
          <td style="padding:10px 12px;text-align:center">
            <?php if ($cancelada): ?>
              <span style="background:#FEE2E2;color:#B91C1C;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px">Cancelada</span>
            <?php else: ?>
              <span style="background:#DCFCE7;color:#166534;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px">Timbrada</span>
            <?php endif; ?>
          </td>
          <td style="padding:10px 16px;text-align:center">
            <div style="display:flex;align-items:center;justify-content:center;gap:6px">
              <?php if (!empty($f['pdf_path'])): ?>
              <a href="<?= BASE_URL . htmlspecialchars($f['pdf_path']) ?>" target="_blank" rel="noopener"
                 style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;background:#EF4444;color:#fff;border-radius:6px;font-size:.75rem;font-weight:600;text-decoration:none">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>PDF
              </a>
              <?php endif; ?>
              <?php if (!empty($f['xml_path'])): ?>
              <a href="<?= BASE_URL . htmlspecialchars($f['xml_path']) ?>" target="_blank" rel="noopener"
                 style="display:inline-flex;align-items:center;gap:4px;padding:5px 10px;background:#2563EB;color:#fff;border-radius:6px;font-size:.75rem;font-weight:600;text-decoration:none">
                <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>XML
              </a>
              <?php endif; ?>
              <?php if (empty($f['pdf_path']) && empty($f['xml_path'])): ?>
                <span style="color:#D1D5DB;font-size:.78rem">—</span>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div style="padding:14px 20px;border-top:1px solid #F3F4F6;display:flex;gap:8px">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <a href="?page=<?= $i ?>" style="padding:6px 12px;border-radius:6px;font-size:.8rem;font-weight:600;text-decoration:none;<?= $i === $page ? 'background:var(--color-primary);color:#fff' : 'background:#F3F4F6;color:#374151' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
