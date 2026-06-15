<?php
$esAdmin = ($usuario['rol_slug'] ?? '') === 'admin_empresa';
$fmt     = fn($n) => '$' . number_format((float)$n, 2, '.', ',');
$fecha   = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
// $hayCredenciales is set by EmpresaFacturaController
$hayCredenciales = $hayCredenciales ?? false;
?>

<?php if (!$hayCredenciales && $esAdmin): ?>
<div style="background:#FEF3C7;border:1px solid #F59E0B;border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:flex-start;gap:12px">
  <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div>
    <p style="font-weight:700;font-size:.875rem;color:#92400E;margin-bottom:4px">Facturación no configurada</p>
    <p style="font-size:.82rem;color:#92400E">
      Configura tus credenciales de <strong>FacturaLO Plus</strong> en
      <a href="<?= BASE_URL ?>empresa-config/facturacion" style="color:#92400E;font-weight:700;text-decoration:underline">Facturación → Configuración</a>.
    </p>
  </div>
</div>
<?php endif; ?>

<!-- ── Pedidos entregados sin factura ───────────────────────────────── -->
<?php if (!empty($pedidosSinFactura) && $esAdmin): ?>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:20px">
  <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0 0 14px">
    Pedidos entregados sin facturar
    <span style="background:#EF4444;color:#fff;font-size:.65rem;padding:2px 8px;border-radius:999px;margin-left:8px"><?= count($pedidosSinFactura) ?></span>
  </h2>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.845rem">
      <thead>
        <tr style="border-bottom:2px solid #F3F4F6">
          <th style="text-align:left;padding:8px 10px;color:#6B7280;font-weight:600">Folio</th>
          <th style="text-align:left;padding:8px 10px;color:#6B7280;font-weight:600">Fecha</th>
          <th style="text-align:right;padding:8px 10px;color:#6B7280;font-weight:600">Total</th>
          <th style="text-align:center;padding:8px 10px;color:#6B7280;font-weight:600">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedidosSinFactura as $p): ?>
        <tr style="border-bottom:1px solid #F9FAFB">
          <td style="padding:9px 10px;font-weight:600;color:#111827"><?= htmlspecialchars($p['folio']) ?></td>
          <td style="padding:9px 10px;color:#6B7280"><?= $fecha($p['created_at']) ?></td>
          <td style="padding:9px 10px;text-align:right;font-weight:600"><?= $fmt($p['total']) ?></td>
          <td style="padding:9px 10px;text-align:center">
            <a href="<?= BASE_URL ?>empresa-factura/generar/<?= (int)$p['id'] ?>"
               onclick="return confirm('¿Generar factura CFDI para el pedido <?= htmlspecialchars($p['folio']) ?>?')"
               style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;background:var(--color-primary);color:#fff;border-radius:6px;font-size:.8rem;font-weight:600;text-decoration:none">
              <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Facturar
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif (empty($pedidosSinFactura)): ?>
<div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#166534">
  Todos los pedidos entregados ya tienen factura.
</div>
<?php endif; ?>

<!-- ── Historial ─────────────────────────────────────────────────────── -->
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Historial de facturas</h2>
    <?php if ($total > 0): ?>
      <span style="font-size:.8rem;color:#9CA3AF"><?= $total ?> factura<?= $total !== 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>

  <?php if (empty($facturas)): ?>
  <div style="padding:48px 20px;text-align:center">
    <svg width="40" height="40" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.5" style="margin:0 auto 12px"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    <p style="color:#9CA3AF;font-size:.875rem">No hay facturas aún</p>
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
          <th style="text-align:center;padding:10px 16px;color:#6B7280;font-weight:600">Descargas</th>
          <?php if ($esAdmin): ?><th style="width:80px"></th><?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($facturas as $f):
          $cancelada = ($f['estado'] ?? '') === 'cancelada';
        ?>
        <tr style="border-bottom:1px solid #F9FAFB;<?= $cancelada ? 'opacity:.55' : '' ?>">
          <td style="padding:10px 16px;font-family:monospace;font-size:.76rem;color:#374151;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($f['uuid_cfdi'] ?? '') ?>">
            <?= htmlspecialchars(substr($f['uuid_cfdi'] ?? '—', 0, 24)) ?>…
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
            </div>
          </td>
          <?php if ($esAdmin): ?>
          <td style="padding:10px 12px;text-align:center">
            <?php if (!$cancelada): ?>
            <a href="<?= BASE_URL ?>empresa-factura/cancelar/<?= urlencode($f['uuid_cfdi'] ?? '') ?>"
               onclick="return confirm('¿Cancelar esta factura ante el SAT? No se puede deshacer.')"
               style="font-size:.75rem;color:#EF4444;text-decoration:none;font-weight:600">Cancelar</a>
            <?php endif; ?>
          </td>
          <?php endif; ?>
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

<details style="margin-top:20px">
  <summary style="cursor:pointer;font-size:.8rem;font-weight:600;color:#6B7280;padding:8px 0;list-style:none;display:flex;align-items:center;gap:6px">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
    ¿Cómo funciona la facturación?
  </summary>
  <div style="background:#F9FAFB;border-radius:10px;padding:16px 20px;margin-top:8px;font-size:.83rem;color:#374151;line-height:1.7">
    <p><strong>CFDI</strong> es la factura electrónica oficial avalada por el SAT. Tiene un UUID único y se puede descargar en PDF y XML.</p>
    <p style="margin-top:8px"><strong>¿Cuándo puedo facturar?</strong> Solo pedidos con estado <em>Entregado</em>.</p>
    <p style="margin-top:8px"><strong>¿Qué necesito?</strong> Configura tus credenciales de FacturaLO Plus (apikey + CSD PEM) en <a href="<?= BASE_URL ?>empresa-config/facturacion" style="color:#374151;font-weight:600">Facturación → Configuración</a>.</p>
  </div>
</details>
