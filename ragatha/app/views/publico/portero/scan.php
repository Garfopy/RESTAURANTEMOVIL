<?php
/**
 * Vista pública de verificación de salida para portero/mesero.
 * Accedida al escanear el QR generado tras el pago.
 *
 * @var array  $visita
 * @var array  $restaurante
 * @var string $qr
 */
$pagado   = ($visita['estado'] ?? '') === 'pagada';
$yaSalio  = !empty($visita['salida_at']);
$mesa     = $visita['mesa_nombre']     ?? '—';
$comensal = $visita['comensal_nombre'] ?? 'Visitante';
$total    = number_format((float)($visita['total'] ?? 0), 2);
$propina  = number_format((float)($visita['propina'] ?? 0), 2);
$color    = htmlspecialchars($restaurante['color_primario'] ?? '#C8102E');
$logo     = $restaurante['logo'] ?? '';
$nombre   = htmlspecialchars($restaurante['nombre'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verificar salida — <?= $nombre ?></title>
  <style>
    :root { --cp: <?= $color ?>; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #111827;
      min-height: 100vh;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: max(24px, env(safe-area-inset-top)) 16px max(40px, env(safe-area-inset-bottom));
    }
    .card {
      background: #1F2937;
      border-radius: 20px;
      border: 1px solid #374151;
      padding: 32px 24px;
      max-width: 400px;
      width: 100%;
      text-align: center;
    }
    .logo-wrap { margin-bottom: 16px; }
    .logo-wrap img { height: 40px; object-fit: contain; }
    .rest-name { font-size: .8rem; font-weight: 600; color: #9CA3AF; margin-bottom: 24px; }
    .status-icon { font-size: 3.5rem; margin-bottom: 12px; }
    .status-title { font-size: 1.4rem; font-weight: 800; margin-bottom: 6px; }
    .status-sub { font-size: .85rem; color: #9CA3AF; margin-bottom: 20px; }
    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 10px 0;
      border-bottom: 1px solid #374151;
      font-size: .9rem;
    }
    .info-row:last-of-type { border-bottom: none; }
    .info-label { color: #9CA3AF; }
    .info-val   { font-weight: 700; color: #F9FAFB; }
    .info-block {
      background: #111827;
      border-radius: 12px;
      padding: 4px 16px;
      margin-bottom: 24px;
    }
    .btn-salida {
      width: 100%;
      padding: 14px;
      background: #10B981;
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: opacity .15s;
    }
    .btn-salida:disabled { opacity: .5; cursor: not-allowed; }
    .btn-salida:not(:disabled):hover { opacity: .9; }
    .result-msg {
      margin-top: 16px;
      padding: 14px;
      border-radius: 12px;
      font-weight: 600;
      font-size: .9rem;
      display: none;
    }
    .result-ok  { background: #064E3B; color: #6EE7B7; border: 1.5px solid #10B981; }
    .result-err { background: #7F1D1D; color: #FCA5A5; border: 1.5px solid #EF4444; }
    /* Estado ya registrado */
    .ya-salio {
      background: #1F2937;
      border: 2px solid #6B7280;
      color: #9CA3AF;
      border-radius: 12px;
      padding: 20px;
      font-size: .9rem;
    }
    /* Estado pendiente de pago */
    .pendiente-pago {
      background: #7F1D1D;
      border: 2px solid #EF4444;
      border-radius: 16px;
      padding: 24px;
      color: #FCA5A5;
    }
    .pendiente-pago .status-title { color: #FCA5A5; }
  </style>
</head>
<body>
<div class="card">
  <!-- Encabezado restaurante -->
  <?php if ($logo): ?>
  <div class="logo-wrap">
    <img src="<?= BASE_URL . htmlspecialchars($logo) ?>" alt="<?= $nombre ?>">
  </div>
  <?php endif; ?>
  <div class="rest-name"><?= $nombre ?></div>

  <?php if ($yaSalio): ?>
  <!-- ── Salida ya registrada ─────────────────────────── -->
  <div class="status-icon">🚪</div>
  <div class="status-title" style="color:#9CA3AF">Salida ya registrada</div>
  <div class="status-sub">Esta visita ya fue procesada.</div>
  <div class="ya-salio">
    Mesa: <strong><?= htmlspecialchars($mesa) ?></strong> &nbsp;·&nbsp; <?= htmlspecialchars($comensal) ?>
  </div>

  <?php elseif ($pagado): ?>
  <!-- ── Cuenta pagada — listo para salir ──────────────── -->
  <div class="status-icon">✅</div>
  <div class="status-title" style="color:#6EE7B7">CUENTA PAGADA</div>
  <div class="status-sub">El comensal puede salir</div>

  <div class="info-block">
    <div class="info-row">
      <span class="info-label">Mesa</span>
      <span class="info-val"><?= htmlspecialchars($mesa) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Comensal</span>
      <span class="info-val"><?= htmlspecialchars($comensal) ?></span>
    </div>
    <div class="info-row">
      <span class="info-label">Total</span>
      <span class="info-val">$<?= $total ?></span>
    </div>
    <?php if ((float)($visita['propina'] ?? 0) > 0): ?>
    <div class="info-row">
      <span class="info-label">Propina</span>
      <span class="info-val" style="color:#6EE7B7">$<?= $propina ?></span>
    </div>
    <?php endif; ?>
  </div>

  <div style="background:#064E3B;border-radius:12px;padding:16px;text-align:center;margin-top:8px">
    <div style="font-weight:700;color:#6EE7B7;font-size:.95rem">🚶 El portero registrará tu salida</div>
    <div style="font-size:.78rem;color:#6EE7B7;opacity:.8;margin-top:4px">Muestra el QR de pago en tu pantalla al portero</div>
  </div>

  <?php else: ?>
  <!-- ── Pago pendiente ──────────────────────────────────── -->
  <div class="pendiente-pago">
    <div class="status-icon">❌</div>
    <div class="status-title">PAGO PENDIENTE</div>
    <div style="margin:8px 0 14px;font-size:.85rem">
      El comensal debe pagar antes de salir.
    </div>
    <div style="font-size:1.3rem;font-weight:800;color:#FCA5A5">$<?= $total ?></div>
    <div style="margin-top:8px;font-size:.8rem">
      Mesa: <strong><?= htmlspecialchars($mesa) ?></strong>
    </div>
  </div>
  <?php endif; ?>

</div>

</body>
</html>

