<?php
/**
 * Vista de impresión / generación de PDF para un pedido.
 * Página standalone (sin layout) con CSS @media print para impresión limpia.
 * El usuario presiona "Imprimir / Guardar PDF" → diálogo nativo del navegador.
 */
$estadoLabels = [
    'pendiente'      => 'Pendiente',
    'confirmado'     => 'Confirmado',
    'en_preparacion' => 'En preparación',
    'en_ruta'        => 'En ruta',
    'entregado'      => 'Entregado',
    'cancelado'      => 'Cancelado',
];
$estadoLabel = $estadoLabels[$pedido['estado']] ?? ucfirst($pedido['estado']);

$colorPrimary = htmlspecialchars($colorPrimary ?? '#C8102E');

// Logo: usar app_logo si está configurado; de lo contrario el SVG incorporado
$logoHtml = '';
if (!empty($appLogo)) {
    $logoHtml = '<img src="' . htmlspecialchars($appLogo) . '" alt="Logo" style="height:56px;max-width:200px;object-fit:contain">';
} else {
    // SVG inline para que se imprima correctamente sin peticiones HTTP externas
    $logoHtml = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 320 80" style="height:56px;width:auto">
  <style>.lr{fill:' . $colorPrimary . '}.ld{fill:#1A1D23}.lt{font-family:\'Inter\',\'Arial Black\',sans-serif;font-weight:900}</style>
  <g transform="translate(8,8)">
    <text x="0" y="54" class="lt lr" font-size="58">C</text>
    <path d="M58 8 Q52 2 46 6 Q44 14 50 18 M58 8 Q64 2 70 6 Q72 14 66 18" stroke="' . $colorPrimary . '" stroke-width="3" fill="none" stroke-linecap="round"/>
    <ellipse cx="58" cy="26" rx="10" ry="11" class="lr"/>
    <circle cx="54" cy="24" r="2" fill="white"/>
    <circle cx="62" cy="24" r="2" fill="white"/>
    <ellipse cx="58" cy="32" rx="5" ry="3" fill="#A00D24"/>
    <text x="72" y="54" class="lt lr" font-size="58">ARNI</text>
  </g>
  <text x="216" y="62" class="lt ld" font-size="58">HUB</text>
</svg>';
}

$metodoPagoLabel = [
    'transferencia' => 'Transferencia bancaria',
    'tarjeta'       => 'Tarjeta',
    'credito'       => 'Crédito',
][$pedido['metodo_pago'] ?? ''] ?? ucfirst($pedido['metodo_pago'] ?? '—');

$tipoEntregaLabel = match ($pedido['tipo_entrega'] ?? '') {
    'pickup'      => 'Recoger en bodega',
    'repartidor'  => 'Envío a domicilio',
    default       => '—',
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pedido <?= htmlspecialchars($pedido['folio']) ?> — CarniHub</title>
  <style>
    /* ── Reset y base ─────────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Segoe UI', 'Arial', sans-serif;
      font-size: 13px;
      color: #1f2937;
      background: #f3f4f6;
      line-height: 1.5;
    }
    /* ── Página imprimible ────────────────────────────────────────── */
    .page {
      background: #fff;
      width: 210mm;
      min-height: 297mm;
      margin: 20px auto;
      padding: 18mm 16mm 14mm;
      box-shadow: 0 4px 24px rgba(0,0,0,.12);
      position: relative;
    }
    /* ── Barra de acción (no se imprime) ──────────────────────────── */
    .no-print {
      text-align: center;
      padding: 16px;
      background: #fff;
      border-bottom: 1px solid #e5e7eb;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 8px rgba(0,0,0,.08);
    }
    .btn-print {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 28px;
      background: <?= $colorPrimary ?>;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 15px;
      font-weight: 700;
      cursor: pointer;
      text-decoration: none;
      margin-right: 10px;
      transition: opacity .15s;
    }
    .btn-print:hover { opacity: .88; }
    .btn-back {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 10px 20px;
      background: #f3f4f6;
      color: #374151;
      border: none;
      border-radius: 8px;
      font-size: 14px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
    }
    /* ── Encabezado del documento ─────────────────────────────────── */
    .doc-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      padding-bottom: 14px;
      border-bottom: 3px solid <?= $colorPrimary ?>;
      margin-bottom: 18px;
    }
    .doc-header .empresa-info {
      text-align: right;
      font-size: 12px;
      color: #4b5563;
      max-width: 260px;
    }
    .doc-header .empresa-info strong {
      display: block;
      font-size: 14px;
      color: #111827;
      margin-bottom: 2px;
    }
    /* ── Título del documento ─────────────────────────────────────── */
    .doc-title {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
    }
    .doc-title h1 {
      font-size: 22px;
      font-weight: 900;
      color: <?= $colorPrimary ?>;
      letter-spacing: .02em;
      text-transform: uppercase;
    }
    /* ── Badges ───────────────────────────────────────────────────── */
    .badge {
      display: inline-block;
      padding: 4px 14px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }
    .badge-pendiente      { background:#FEF3C7; color:#92400E; }
    .badge-confirmado     { background:#DBEAFE; color:#1E40AF; }
    .badge-en_preparacion { background:#EDE9FE; color:#5B21B6; }
    .badge-en_ruta        { background:#FEF3C7; color:#B45309; }
    .badge-entregado      { background:#D1FAE5; color:#065F46; }
    .badge-cancelado      { background:#FEE2E2; color:#991B1B; }
    /* ── Meta-datos del pedido ────────────────────────────────────── */
    .meta-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      padding: 14px 16px;
      margin-bottom: 20px;
    }
    .meta-item .label {
      font-size: 10px;
      font-weight: 700;
      color: #9ca3af;
      letter-spacing: .06em;
      text-transform: uppercase;
      margin-bottom: 2px;
    }
    .meta-item .value {
      font-size: 13px;
      font-weight: 700;
      color: #111827;
    }
    /* ── Tabla de productos ───────────────────────────────────────── */
    .products-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: 16px;
      font-size: 12.5px;
    }
    .products-table thead tr {
      background: <?= $colorPrimary ?>;
      color: #fff;
    }
    .products-table thead th {
      padding: 9px 12px;
      text-align: left;
      font-weight: 700;
      font-size: 11px;
      letter-spacing: .04em;
      text-transform: uppercase;
    }
    .products-table thead th.right { text-align: right; }
    .products-table thead th.center { text-align: center; }
    .products-table tbody tr { border-bottom: 1px solid #f3f4f6; }
    .products-table tbody tr:nth-child(even) { background: #f9fafb; }
    .products-table tbody td { padding: 9px 12px; color: #374151; }
    .products-table tbody td.right { text-align: right; }
    .products-table tbody td.center { text-align: center; }
    .products-table .producto-nombre { font-weight: 600; color: #111827; }
    .products-table .presentacion { font-size: 11px; color: #9ca3af; margin-top: 1px; }
    /* ── Totales ──────────────────────────────────────────────────── */
    .totals-section {
      display: flex;
      justify-content: flex-end;
      margin-bottom: 20px;
    }
    .totals-box {
      width: 260px;
      border: 1px solid #e5e7eb;
      border-radius: 10px;
      overflow: hidden;
    }
    .totals-row {
      display: flex;
      justify-content: space-between;
      padding: 8px 16px;
      font-size: 12.5px;
      border-bottom: 1px solid #f3f4f6;
    }
    .totals-row.total-final {
      background: <?= $colorPrimary ?>;
      color: #fff;
      font-weight: 800;
      font-size: 15px;
      border-bottom: none;
    }
    .totals-row .tl { color: #6b7280; }
    .totals-row.total-final .tl { color: rgba(255,255,255,.85); }
    /* ── Notas ────────────────────────────────────────────────────── */
    .notes-box {
      background: #fffbeb;
      border: 1px solid #fcd34d;
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 16px;
      font-size: 12px;
      color: #78350f;
    }
    .notes-box strong { display: block; margin-bottom: 4px; color: #92400e; }
    /* ── Información del comprador ────────────────────────────────── */
    .buyer-box {
      background: #f9fafb;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      padding: 12px 14px;
      margin-bottom: 16px;
      font-size: 12px;
    }
    .buyer-box strong { display: block; font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    /* ── Pie de página ────────────────────────────────────────────── */
    .doc-footer {
      border-top: 1px solid #e5e7eb;
      padding-top: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      font-size: 10.5px;
      color: #9ca3af;
      margin-top: auto;
    }
    .doc-footer .brand { font-weight: 700; color: <?= $colorPrimary ?>; }
    /* ── Media print ──────────────────────────────────────────────── */
    @media print {
      html, body { background: #fff !important; }
      .no-print { display: none !important; }
      .page {
        margin: 0;
        padding: 12mm 14mm 10mm;
        box-shadow: none;
        width: 100%;
        min-height: auto;
      }
      body { font-size: 12px; }
      .meta-grid { break-inside: avoid; }
      .products-table { break-inside: auto; }
      .products-table thead { display: table-header-group; }
      .products-table tbody tr { break-inside: avoid; }
      .totals-section { break-inside: avoid; }
    }
  </style>
</head>
<body>

<!-- Barra de acción (no se imprime) -->
<div class="no-print">
  <button class="btn-print" onclick="window.print()">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
    </svg>
    Imprimir / Guardar PDF
  </button>
  <a href="<?= BASE_URL ?>empresa-pedido/index" class="btn-back">← Volver</a>
</div>

<!-- Documento imprimible -->
<div class="page">

  <!-- ── Encabezado ─────────────────────────────────────────────── -->
  <div class="doc-header">
    <div class="logo-area">
      <?= $logoHtml ?>
    </div>
    <div class="empresa-info">
      <strong><?= htmlspecialchars($empresa['razon_social'] ?? $pedido['empresa_nombre'] ?? 'CarniHub') ?></strong>
      <?php if (!empty($empresa['rfc'])): ?>
        RFC: <?= htmlspecialchars($empresa['rfc']) ?><br>
      <?php endif; ?>
      <?php if (!empty($empresa['direccion_fiscal'])): ?>
        <?= htmlspecialchars($empresa['direccion_fiscal']) ?><br>
      <?php endif; ?>
      <?php if (!empty($empresa['telefono'])): ?>
        Tel: <?= htmlspecialchars($empresa['telefono']) ?><br>
      <?php endif; ?>
      <?php if (!empty($empresa['email'])): ?>
        <?= htmlspecialchars($empresa['email']) ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Título y folio ─────────────────────────────────────────── -->
  <div class="doc-title">
    <h1>Detalle de Pedido</h1>
    <div style="text-align:right">
      <div style="font-size:22px;font-weight:900;font-family:monospace;color:#111827"><?= htmlspecialchars($pedido['folio']) ?></div>
      <span class="badge badge-<?= htmlspecialchars($pedido['estado']) ?>"><?= htmlspecialchars($estadoLabel) ?></span>
    </div>
  </div>

  <!-- ── Meta-datos ─────────────────────────────────────────────── -->
  <div class="meta-grid">
    <div class="meta-item">
      <div class="label">Fecha del pedido</div>
      <div class="value"><?= date('d/m/Y', strtotime($pedido['created_at'])) ?></div>
    </div>
    <div class="meta-item">
      <div class="label">Comprador</div>
      <div class="value"><?= htmlspecialchars(trim(($pedido['comprador_nombre'] ?? '') . ' ' . ($pedido['comprador_apellido'] ?? ''))) ?></div>
    </div>
    <?php if (!empty($pedido['fecha_entrega'])): ?>
    <div class="meta-item">
      <div class="label">Fecha de entrega</div>
      <div class="value"><?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($pedido['metodo_pago'])): ?>
    <div class="meta-item">
      <div class="label">Método de pago</div>
      <div class="value"><?= htmlspecialchars($metodoPagoLabel) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($pedido['tipo_entrega'])): ?>
    <div class="meta-item">
      <div class="label">Tipo de entrega</div>
      <div class="value"><?= htmlspecialchars($tipoEntregaLabel) ?></div>
    </div>
    <?php endif; ?>
    <?php if (!empty($pedido['aprobador_nombre'])): ?>
    <div class="meta-item">
      <div class="label">Aprobado por</div>
      <div class="value"><?= htmlspecialchars($pedido['aprobador_nombre']) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <!-- ── Notas del pedido ───────────────────────────────────────── -->
  <?php if (!empty($pedido['notas'])): ?>
  <div class="notes-box">
    <strong>Notas del pedido:</strong>
    <?= nl2br(htmlspecialchars($pedido['notas'])) ?>
  </div>
  <?php endif; ?>

  <!-- ── Dirección de entrega ───────────────────────────────────── -->
  <?php if (!empty($pedido['direccion_entrega'])): ?>
  <div class="buyer-box" style="margin-bottom:16px">
    <strong>Dirección de entrega</strong>
    <?= htmlspecialchars($pedido['direccion_entrega']) ?>
    <?php if (!empty($pedido['referencia_entrega'])): ?>
      <br><span style="color:#6b7280">Referencia: <?= htmlspecialchars($pedido['referencia_entrega']) ?></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ── Tabla de productos ─────────────────────────────────────── -->
  <table class="products-table">
    <thead>
      <tr>
        <th style="width:40%">Producto</th>
        <th class="center" style="width:15%">Cantidad</th>
        <th class="right" style="width:20%">Precio unit.</th>
        <th class="right" style="width:25%">Subtotal</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pedido['items'] as $item): ?>
      <tr>
        <td>
          <div class="producto-nombre"><?= htmlspecialchars($item['producto_nombre']) ?></div>
          <?php if (!empty($item['presentacion'])): ?>
          <div class="presentacion"><?= htmlspecialchars($item['presentacion']) ?></div>
          <?php endif; ?>
        </td>
        <td class="center"><?= number_format((float)$item['cantidad'], 2) ?></td>
        <td class="right">$<?= number_format((float)$item['precio_unit'], 2) ?></td>
        <td class="right" style="font-weight:700">$<?= number_format((float)$item['subtotal'], 2) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- ── Totales ───────────────────────────────────────────────── -->
  <div class="totals-section">
    <div class="totals-box">
<?php
$_pdfBase = round((float)$pedido['subtotal'] / 1.16, 2);
$_pdfIva  = round((float)$pedido['subtotal'] - $_pdfBase, 2);
$_pdfTotal = round((float)$pedido['subtotal'] + (float)($pedido['costo_envio'] ?? 0), 2);
?>
      <div class="totals-row">
        <span class="tl">Base (sin IVA)</span>
        <span style="font-weight:600">$<?= number_format($_pdfBase, 2) ?></span>
      </div>
      <div class="totals-row">
        <span class="tl">IVA (16%)</span>
        <span style="font-weight:600">$<?= number_format($_pdfIva, 2) ?></span>
      </div>
      <?php if (($pedido['costo_envio'] ?? 0) > 0): ?>
      <div class="totals-row">
        <span class="tl">Costo de envío</span>
        <span style="font-weight:600">$<?= number_format((float)$pedido['costo_envio'], 2) ?></span>
      </div>
      <?php endif; ?>
      <div class="totals-row total-final">
        <span class="tl">TOTAL</span>
        <span>$<?= number_format($_pdfTotal, 2) ?></span>
      </div>
    </div>
  </div>

  <!-- ── Pie de página ─────────────────────────────────────────── -->
  <div class="doc-footer">
    <div>
      Generado el <?= date('d/m/Y \a \l\a\s H:i') ?> hrs
    </div>
    <div>
      <span class="brand">CarniHub</span> — Sistema de Gestión Carnícola
    </div>
  </div>

</div>

</body>
</html>
