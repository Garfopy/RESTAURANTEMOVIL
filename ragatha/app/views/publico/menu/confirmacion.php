<?php
$ticketPagado  = isset($ticket) && ($ticket['estado'] ?? '') === 'pagado';
$ticketFolio   = $ticket['folio'] ?? '';
$ticketTotal   = isset($ticket) ? number_format((float)$ticket['total'],   2) : '0.00';
$ticketSubt    = isset($ticket) ? number_format((float)$ticket['subtotal'],2) : '0.00';
$ticketProp    = isset($ticket) ? number_format((float)$ticket['propina'], 2) : '0.00';
$ticketMetodo  = $ticket['metodo_pago'] ?? '';
$mesaNombre    = $visita['mesa_nombre'] ?? ($pedidos[0]['mesa_nombre'] ?? '');
$slug          = $restaurante['slug'] ?? '';
$visitaId      = (int)($visita['id'] ?? 0);

// Construir líneas de items para WhatsApp
$waLineas = [];
foreach ($pedidos as $p) {
    foreach ($p['items'] ?? [] as $it) {
        if (($it['estado'] ?? '') === 'cancelado') continue;
        $nom  = htmlspecialchars_decode($it['platillo_nombre'] ?? $it['nombre'] ?? '', ENT_QUOTES);
        $cant = (int)$it['cantidad'];
        $sub  = isset($it['subtotal']) ? ' $' . number_format((float)$it['subtotal'],2) : '';
        $waLineas[] = "  • {$cant}x {$nom}{$sub}";
    }
}
$waItemsTxt = implode("\n", $waLineas);
$waTexto  = "🍽️ *" . ($restaurante['nombre'] ?? 'Restaurante') . "*\n";
$waTexto .= $ticketFolio ? "📋 Folio: {$ticketFolio}\n" : '';
$waTexto .= $mesaNombre  ? "🪑 Mesa: {$mesaNombre}\n"   : '';
$waTexto .= "━━━━━━━━━━━━━━━━━━━\n";
$waTexto .= $waItemsTxt . "\n";
$waTexto .= "━━━━━━━━━━━━━━━━━━━\n";
if (isset($ticket)) {
    $waTexto .= "Subtotal: \${$ticketSubt}\n";
    if ((float)($ticket['propina'] ?? 0) > 0) $waTexto .= "Propina:  \${$ticketProp}\n";
    $waTexto .= "*Total: \${$ticketTotal}*\n";
    if ($ticketMetodo) $waTexto .= "💳 Pago: {$ticketMetodo}\n";
}
$waTexto .= "━━━━━━━━━━━━━━━━━━━\n¡Gracias por tu visita! 🙏";
$visitaQr  = $visita['qr_code'] ?? '';
$scanUrl   = $visitaQr ? BASE_URL . 'menu/scanPortero?qr=' . urlencode($visitaQr) : '';
$qrImgUrl  = $visitaQr
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=' . urlencode($visitaQr)
    : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>¡Pedido recibido! — <?= htmlspecialchars($restaurante['nombre'] ?? '') ?></title>
  <style>
    :root { --cp: <?= htmlspecialchars($restaurante['color_primario'] ?? '#C8102E') ?>; }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #F9FAFB; min-height: 100vh;
           display: flex; align-items: flex-start; justify-content: center; padding: 20px 16px 40px; }
    .card { background: #fff; border-radius: 20px; border: 1px solid #E5E7EB;
            padding: 28px 24px; max-width: 460px; width: 100%; }
    .badge { display: inline-block; padding: 3px 10px; border-radius: 20px;
             font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
    .badge-pendiente      { background:#FEF3C7; color:#92400E; }
    .badge-en_preparacion { background:#DBEAFE; color:#1E40AF; }
    .badge-listo          { background:#DCFCE7; color:#166534; }
    .badge-entregado      { background:#F3F4F6; color:#6B7280; }
    .badge-cancelado      { background:#FEE2E2; color:#991B1B; }
    .estado-bar { display: flex; gap: 0; margin: 20px 0; border-radius: 10px; overflow: hidden; }
    .estado-step { flex: 1; padding: 8px 4px; text-align: center; font-size: .66rem;
                   font-weight: 600; background: #F3F4F6; color: #9CA3AF; transition: .4s; }
    .estado-step.active   { background: var(--cp); color: #fff; }
    .estado-step.done     { background: #D1FAE5; color: #065F46; }
    .item-row { display: flex; justify-content: space-between; align-items: center;
                padding: 10px 0; border-bottom: 1px solid #F3F4F6; gap: 8px; }
    .item-row:last-child { border-bottom: none; }
    .btn-cancel { padding: 4px 10px; background: #FEE2E2; color: #991B1B; border: none;
                  border-radius: 6px; font-size: .72rem; font-weight: 600; cursor: pointer; }
    .btn-cancel:disabled { opacity: .45; cursor: not-allowed; }
    .link-btn { display: flex; align-items: center; justify-content: center; gap: 8px;
                padding: 14px; border-radius: 12px; font-weight: 700; text-align: center;
                text-decoration: none; transition: opacity .15s; font-size: .95rem; }
    .link-btn:active { opacity: .8; }
    /* Toast */
    #toast { position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px);
             background: #1F2937; color: #fff; padding: 12px 22px; border-radius: 30px;
             font-size: .88rem; font-weight: 600; opacity: 0;
             transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .35s; z-index:999; white-space:nowrap; }
    #toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
    /* Pulse para "listo" */
    @keyframes pulse-green { 0%,100%{box-shadow:0 0 0 0 #86efac88} 50%{box-shadow:0 0 0 10px transparent} }
    .pulse-green { animation: pulse-green 1.2s infinite; }
  </style>
</head>
<body>
<div class="card">

  <!-- Banner pagado / recibido -->
  <?php if ($ticketPagado): ?>
  <div id="banner-pagado"
       style="background:#DCFCE7;border:1.5px solid #86EFAC;border-radius:14px;
              padding:20px;text-align:center;margin-bottom:20px">
    <div style="font-size:2rem;margin-bottom:4px">🎉</div>
    <div style="font-weight:700;color:#166534;font-size:1.05rem">¡Cuenta pagada!</div>
    <div style="font-size:.83rem;color:#16A34A;margin-top:4px">Gracias por tu visita.</div>
  </div>
  <?php elseif (!empty($_GET['pagado'])): ?>
  <div id="banner-pagado"
       style="background:#DCFCE7;border:1.5px solid #86EFAC;border-radius:14px;
              padding:20px;text-align:center;margin-bottom:20px">
    <div style="font-size:2rem;margin-bottom:4px">🎉</div>
    <div style="font-weight:700;color:#166534;font-size:1.05rem">¡Pago confirmado!</div>
    <div style="font-size:.83rem;color:#16A34A;margin-top:4px">Tu pedido sigue en preparación.</div>
  </div>
  <?php endif; ?>

  <!-- Header -->
  <div style="text-align:center;margin-bottom:20px">
    <?php if (!empty($restaurante['logo'])): ?>
    <img src="<?= BASE_URL . htmlspecialchars($restaurante['logo']) ?>" alt=""
         style="height:36px;object-fit:contain;margin-bottom:8px;display:block;margin:0 auto 8px">
    <?php endif; ?>
    <div style="font-size:2.2rem;margin-bottom:6px">✅</div>
    <h1 style="font-size:1.2rem;font-weight:700;color:#111827">¡Pedido recibido!</h1>
    <p style="color:#6B7280;font-size:.83rem;margin-top:4px">Estado actualizado automáticamente</p>
  </div>

  <!-- Barra de progreso -->
  <div class="estado-bar" id="estadoBar">
    <div class="estado-step" id="step-pendiente">⏳ Recibido</div>
    <div class="estado-step" id="step-en_preparacion">👨‍🍳 Preparando</div>
    <div class="estado-step" id="step-listo">🔔 Listo</div>
    <div class="estado-step" id="step-entregado">🍽 Entregado</div>
  </div>

  <!-- Tiempo estimado -->
  <div id="tiempoEst" style="text-align:center;font-size:.82rem;color:#6B7280;margin-bottom:16px;display:none">
    ⏱️ Tiempo estimado: <strong id="tiempoMin">—</strong> min
  </div>

  <!-- Lista de pedidos -->
  <div id="pedidosList" style="margin-bottom:20px">
    <?php foreach ($pedidos as $p): ?>
    <div style="margin-bottom:12px" id="pedido-<?= (int)$p['id'] ?>">
      <div style="font-size:.78rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;
                  letter-spacing:.06em;margin-bottom:6px;display:flex;align-items:center;gap:8px">
        <span><?= htmlspecialchars($p['folio']) ?><?php if (!empty($p['mesa_nombre'])): ?> · Mesa <?= htmlspecialchars($p['mesa_nombre']) ?><?php endif; ?></span>
        <span class="badge badge-<?= htmlspecialchars($p['estado']) ?>" id="badge-<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['estado']) ?></span>
      </div>
      <?php foreach ($p['items'] ?? [] as $it): ?>
      <div class="item-row" id="item-row-<?= (int)$it['id'] ?>">
        <div>
          <span style="font-size:.88rem;font-weight:500"><?= htmlspecialchars($it['platillo_nombre'] ?? $it['nombre'] ?? '') ?></span>
          <span style="font-size:.78rem;color:#9CA3AF;margin-left:4px">×<?= (int)$it['cantidad'] ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:6px">
          <span class="badge badge-<?= htmlspecialchars($it['estado']) ?>" id="item-badge-<?= (int)$it['id'] ?>"><?= htmlspecialchars($it['estado']) ?></span>
          <?php if ($it['estado'] === 'pendiente' && $p['estado'] !== 'cancelado'): ?>
          <button class="btn-cancel" id="cancel-<?= (int)$it['id'] ?>" onclick="cancelarPedido(<?= (int)$p['id'] ?>)">Cancelar</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── Acciones ──────────────────────────────────────── -->
  <div id="accion-pagar" style="margin-bottom:10px">
    <?php if ($ticketPagado): ?>
    <div class="link-btn" style="background:#D1FAE5;color:#065F46;cursor:default">✅ Cuenta pagada</div>
    <?php else: ?>
    <a href="<?= BASE_URL ?>menu/<?= htmlspecialchars($slug) ?>/pagar/<?= $visitaId ?>"
       id="btn-pagar" class="link-btn pulse-green" style="background:var(--cp);color:#fff">
      💳 Pagar mi cuenta
    </a>
    <?php endif; ?>
  </div>

  <?php if ($ticketPagado && $qrImgUrl): ?>
  <div id="qr-portero-section"
       style="text-align:center;background:#F0FDF4;border:1.5px solid #86EFAC;
              border-radius:14px;padding:20px;margin-bottom:10px">
    <div style="font-size:1.5rem;margin-bottom:6px">📱</div>
    <div style="font-size:.82rem;font-weight:700;color:#166534;margin-bottom:12px">
      Muestra este QR al salir, por favor
    </div>
    <img src="<?= $qrImgUrl ?>" alt="QR de salida" id="qr-portero-img"
         style="width:200px;height:200px;display:block;margin:0 auto 12px;border-radius:8px">
    <div style="margin:0 auto 10px;font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:.1em;color:#111827;background:#fff;border:1px dashed #86EFAC;border-radius:8px;padding:8px 14px;display:inline-block"><?= htmlspecialchars(strtoupper(substr($visitaQr ?? '', 0, 8))) ?></div>
    <div style="font-size:.72rem;color:#9CA3AF;margin-bottom:12px">
      Si el QR no funciona, muestra este código al portero
    </div>
    <button type="button" onclick="descargarQR()"
       style="display:inline-block;padding:8px 18px;background:#DCFCE7;color:#166534;
              border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer">
      ⬇️ Guardar QR
    </button>
  </div>
  <?php endif; ?>

  <!-- ── Resumen del ticket ────────────────────────────── -->
  <?php if (isset($ticket)): ?>
  <?php
    $cardBg     = $ticketPagado ? '#DCFCE7' : '#FFFBEB';
    $cardBorder = $ticketPagado ? '#86EFAC' : '#FCD34D';
    $totalColor = $ticketPagado ? '#166534' : '#92400E';
  ?>
  <div id="ticket-summary"
       style="background:<?= $cardBg ?>;border:1.5px solid <?= $cardBorder ?>;border-radius:14px;padding:16px;margin-bottom:10px">
    <div style="font-size:.72rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.06em;margin-bottom:10px;display:flex;align-items:center;gap:8px">
      🧾 Ticket <?= htmlspecialchars($ticketFolio) ?>
      <?php if ($ticketMetodo): ?>
      <span style="background:#E5E7EB;padding:2px 8px;border-radius:10px;font-size:.68rem;font-weight:600"><?= htmlspecialchars(ucfirst($ticketMetodo)) ?></span>
      <?php endif; ?>
    </div>
    <div style="font-size:.85rem">
      <?php foreach ($pedidos as $p): foreach ($p['items'] ?? [] as $it): if (($it['estado'] ?? '') === 'cancelado') continue; ?>
      <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid rgba(0,0,0,.06)">
        <span><?= (int)$it['cantidad'] ?>× <?= htmlspecialchars($it['platillo_nombre'] ?? $it['nombre'] ?? '') ?></span>
        <?php if (!empty($it['subtotal'])): ?><span>$<?= number_format((float)$it['subtotal'],2) ?></span><?php endif; ?>
      </div>
      <?php endforeach; endforeach; ?>
    </div>
    <div style="margin-top:10px;padding-top:8px;border-top:2px solid rgba(0,0,0,.08)">
      <div style="display:flex;justify-content:space-between;font-size:.85rem;color:#6B7280;margin-bottom:4px">
        <span>Subtotal</span><span>$<?= $ticketSubt ?></span>
      </div>
      <?php if ((float)($ticket['propina'] ?? 0) > 0): ?>
      <div style="display:flex;justify-content:space-between;font-size:.85rem;color:#10B981;margin-bottom:4px">
        <span>Propina</span><span>$<?= $ticketProp ?></span>
      </div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;font-size:1.05rem;font-weight:800;color:<?= $totalColor ?>">
        <span>Total</span><span>$<?= $ticketTotal ?></span>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- Sin ticket: botón generar + placeholder AJAX -->
  <button id="btn-generar-ticket" class="link-btn-cerrar" onclick="generarTicket()"
      style="display:flex;align-items:center;justify-content:center;gap:8px;padding:14px;
             border-radius:12px;font-weight:700;font-size:.9rem;cursor:pointer;width:100%;
             background:#1F2937;color:#fff;border:none;margin-bottom:10px">
    🧾 Cerrar cuenta
  </button>
  <div id="ticket-ajax-result"></div>
  <?php endif; ?>

  <!-- ── WhatsApp ─────────────────────────────────────── -->
  <?php if (isset($ticket)): ?>
  <a id="btn-whatsapp"
     href="https://wa.me/?text=<?= rawurlencode($waTexto) ?>"
     target="_blank" rel="noopener"
     class="link-btn" style="background:#25D366;color:#fff;margin-bottom:10px">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M20.52 3.48A11.95 11.95 0 0012.02 0C5.4 0 .02 5.37.02 12a11.94 11.94 0 001.63 6.06L0 24l6.13-1.61A12.04 12.04 0 0012.02 24c6.62 0 11.98-5.37 11.98-12 0-3.2-1.25-6.21-3.48-8.52zM12.02 22a9.96 9.96 0 01-5.08-1.39l-.36-.22-3.74.98 1-3.65-.24-.38A9.96 9.96 0 012.02 12c0-5.52 4.49-10 10-10a9.98 9.98 0 017.07 2.93A9.93 9.93 0 0122.02 12c0 5.52-4.49 10-10 10zm5.48-7.54c-.3-.15-1.76-.87-2.03-.97-.28-.1-.48-.15-.68.15s-.78.97-.96 1.17c-.18.2-.35.22-.65.07-.3-.15-1.26-.47-2.4-1.49-.89-.8-1.49-1.78-1.66-2.08-.17-.3-.02-.46.13-.6.13-.13.3-.34.45-.51.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.62-.92-2.22-.24-.58-.49-.5-.68-.51h-.58c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.06 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.22 1.36.19 1.87.12.57-.08 1.76-.72 2.01-1.42.25-.7.25-1.3.17-1.42-.07-.12-.27-.19-.57-.34z"/></svg>
    Compartir ticket por WhatsApp
  </a>
  <?php else: ?>
  <div id="wa-placeholder"></div>
  <?php endif; ?>

  <?php if (!$ticketPagado): ?>
  <?php $menuLink = BASE_URL . 'menu/' . htmlspecialchars($slug) . (!empty($mesaQr) ? '?mesa=' . urlencode($mesaQr) : ''); ?>
  <a href="<?= $menuLink ?>"
     class="link-btn link-btn-mas" style="background:#F3F4F6;color:#374151;margin-bottom:10px">
    ← Agregar más items
  </a>
  <?php endif; ?>

  <div style="margin-top:16px;font-size:.7rem;color:#9CA3AF;text-align:center">
    Potenciado por <strong>CarniHub</strong>
  </div>
</div>

<!-- Toast -->
<div id="toast"></div>

<script>
const SLUG   = '<?= htmlspecialchars($slug) ?>';
const VISITA = <?= $visitaId ?>;
const PASOS  = ['pendiente','en_preparacion','listo','entregado'];

const ESTADO_LABEL = {
  pendiente:       'Recibido',
  en_preparacion:  'Preparando',
  listo:           '¡Listo para entregar!',
  entregado:       'Entregado',
};
const ESTADO_EMOJI = {
  pendiente:'⏳', en_preparacion:'👨‍🍳', listo:'🔔', entregado:'🍽️',
};

// Estado inicial del servidor
let prevItemEstados = {};
<?php foreach ($pedidos as $p): foreach ($p['items'] ?? [] as $it): ?>
prevItemEstados[<?= (int)$it['id'] ?>] = '<?= htmlspecialchars($it['estado']) ?>';
<?php endforeach; endforeach; ?>

let cuentaPagada = <?= $ticketPagado ? 'true' : 'false' ?>;
let primerPoll   = true;

// ── Toast ──────────────────────────────────────────
let toastTimer;
function showToast(msg, duration = 3500) {
  const t = document.getElementById('toast');
  t.textContent = msg;
  clearTimeout(toastTimer);
  t.classList.add('show');
  toastTimer = setTimeout(() => t.classList.remove('show'), duration);
}

// ── Vibración (móvil) ──────────────────────────────
function vibrar(ms = 200) {
  try { if (navigator.vibrate) navigator.vibrate(ms); } catch {}
}

// ── Beep sonoro ────────────────────────────────────
function beep() {
  try {
    const ctx = new AudioContext();
    const osc = ctx.createOscillator();
    const g   = ctx.createGain();
    osc.connect(g); g.connect(ctx.destination);
    osc.type = 'sine'; osc.frequency.value = 760;
    g.gain.setValueAtTime(.25, ctx.currentTime);
    g.gain.exponentialRampToValueAtTime(.001, ctx.currentTime + .5);
    osc.start(); osc.stop(ctx.currentTime + .5);
  } catch {}
}

// ── Actualizar barra de estado ─────────────────────
function actualizarBarra(estadoGlobal) {
  const gi = PASOS.indexOf(estadoGlobal);
  PASOS.forEach((s, i) => {
    const el = document.getElementById('step-' + s);
    if (!el) return;
    el.className = 'estado-step' + (i < gi ? ' done' : i === gi ? ' active' : '');
  });
}

// ── Actualizar botón pagar ─────────────────────────
function actualizarBtnPagar(ticketEstado, qrCode) {
  const wrap = document.getElementById('accion-pagar');
  if (!wrap) return;
  if (ticketEstado === 'pagado' && !cuentaPagada) {
    cuentaPagada = true;
    clearInterval(pollTimer);
    iniciarPollingsSalida();
    wrap.innerHTML = '<div class="link-btn" style="background:#D1FAE5;color:#065F46;cursor:default">✅ Cuenta pagada</div>';
    // Ocultar botones de flujo
    document.querySelectorAll('.link-btn-cerrar,.link-btn-mas').forEach(el => el.style.display = 'none');
    // Banner si no está visible
    if (!document.getElementById('banner-pagado')) {
      const banner = document.createElement('div');
      banner.id = 'banner-pagado';
      banner.innerHTML = '<div style="font-size:2rem;margin-bottom:4px">🎉</div><div style="font-weight:700;color:#166534;font-size:1.05rem">¡Cuenta pagada!</div><div style="font-size:.83rem;color:#16A34A;margin-top:4px">Gracias por tu visita.</div>';
      banner.style.cssText = 'background:#DCFCE7;border:1.5px solid #86EFAC;border-radius:14px;padding:20px;text-align:center;margin-bottom:20px';
      document.querySelector('.card').prepend(banner);
    }
    // Inyectar QR dinámicamente si no existe aún
    if (qrCode && !document.getElementById('qr-portero-section')) {
      const qrUrl   = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrCode)}`;
      const shortCode = qrCode.substring(0, 8).toUpperCase();
      const qrDiv = document.createElement('div');
      qrDiv.id = 'qr-portero-section';
      qrDiv.style.cssText = 'text-align:center;background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:14px;padding:20px;margin-bottom:10px';
      qrDiv.innerHTML = `<div style="font-size:1.5rem;margin-bottom:6px">📱</div>
        <div style="font-size:.82rem;font-weight:700;color:#166534;margin-bottom:12px">Muestra este QR al salir, por favor</div>
        <img src="${qrUrl}" id="qr-portero-img" style="width:200px;height:200px;display:block;margin:0 auto 12px;border-radius:8px" alt="QR de salida">
        <div style="margin:0 auto 10px;font-family:monospace;font-size:1rem;font-weight:700;letter-spacing:.1em;color:#111827;background:#fff;border:1px dashed #86EFAC;border-radius:8px;padding:8px 14px;display:inline-block">${shortCode}</div>
        <div style="font-size:.72rem;color:#9CA3AF;margin-bottom:12px">Si el QR no funciona, muestra este código al portero</div>
        <button type="button" onclick="descargarQR()" style="display:inline-block;padding:8px 18px;background:#DCFCE7;color:#166534;border:none;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer">⬇️ Guardar QR</button>`;
      wrap.after(qrDiv);
    }
  }
}

// ── Descargar QR como imagen ───────────────────────
async function descargarQR() {
  const img = document.getElementById('qr-portero-img');
  if (!img) return;
  try {
    const resp = await fetch(img.src, { mode: 'cors' });
    const blob = await resp.blob();
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'qr-salida.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
  } catch (e) {
    const a = document.createElement('a');
    a.href = img.src;
    a.download = 'qr-salida.png';
    document.body.appendChild(a);
    a.click();
    a.remove();
  }
}

// ── UI principal ───────────────────────────────────
function actualizarUI(data) {
  if (!data.ok) return;

  // Estado global: el ítem activo más atrasado entre todos los pedidos activos
  let estadoGlobal = 'entregado';
  data.pedidos.forEach(p => {
    if (p.estado === 'cancelado') return;
    const itemsActivos = (p.items || []).filter(it => it.estado !== 'cancelado');
    if (itemsActivos.length) {
      itemsActivos.forEach(it => {
        const ii = PASOS.indexOf(it.estado), gi = PASOS.indexOf(estadoGlobal);
        if (ii >= 0 && ii < gi) estadoGlobal = it.estado;
      });
    } else {
      const pi = PASOS.indexOf(p.estado), gi = PASOS.indexOf(estadoGlobal);
      if (pi >= 0 && pi < gi) estadoGlobal = p.estado;
    }
  });

  actualizarBarra(estadoGlobal);

  // Tiempo estimado
  if (data.tiempo_min > 0 && estadoGlobal === 'en_preparacion') {
    document.getElementById('tiempoEst').style.display = 'block';
    document.getElementById('tiempoMin').textContent   = data.tiempo_min;
  } else {
    document.getElementById('tiempoEst').style.display = 'none';
  }

  // Botón pagar
  actualizarBtnPagar(data.ticket_estado, data.qr_code || '');

  // Detectar cambios de estado en ítems
  let hayListos = false;
  data.pedidos.forEach(p => {
    const badge = document.getElementById('badge-' + p.id);
    if (badge) { badge.className = 'badge badge-' + p.estado; badge.textContent = p.estado; }

    p.items.forEach(it => {
      const ib = document.getElementById('item-badge-' + it.id);
      if (ib) { ib.className = 'badge badge-' + it.estado; ib.textContent = it.estado; }

      if (!primerPoll && prevItemEstados[it.id] && prevItemEstados[it.id] !== it.estado) {
        const emoji = ESTADO_EMOJI[it.estado] || '';
        showToast(emoji + ' ' + (it.nombre || '') + ' — ' + (ESTADO_LABEL[it.estado] || it.estado));
        if (it.estado === 'listo') { beep(); vibrar(300); hayListos = true; }
        else vibrar(100);
      }
      prevItemEstados[it.id] = it.estado;

      // Quitar botón cancelar si ya no está pendiente
      if (it.estado !== 'pendiente') {
        const cb = document.getElementById('cancel-' + it.id);
        if (cb) cb.remove();
      }
    });
  });

  // Notificación global cuando pedido completo listo
  if (!primerPoll && hayListos && estadoGlobal === 'listo') {
    showToast('🔔 ¡Tu pedido está listo para entregar!', 5000);
  }

  primerPoll = false;
}

// ── Polling ────────────────────────────────────────
function pollEstado() {
  fetch(`<?= BASE_URL ?>menu/${SLUG}/estadoPedido/${VISITA}?t=` + Date.now())
    .then(r => r.json())
    .then(actualizarUI)
    .catch(() => {});
}

pollEstado();
const pollTimer = setInterval(pollEstado, 3000);
if (cuentaPagada) clearInterval(pollTimer);

// ── Polling de salida (cuando la cuenta está pagada, esperar que el portero escanee el QR) ──
const QR_CODE     = '<?= addslashes($visitaQr) ?>';
let _salidaTimer = null;
function iniciarPollingsSalida() {
  if (_salidaTimer || !QR_CODE) return;
  _salidaTimer = setInterval(async () => {
    try {
      const resp = await fetch('<?= BASE_URL ?>menu/checkSalida?qr=' + encodeURIComponent(QR_CODE));
      const data = await resp.json();
      if (data.ok && data.salida && data.redirect) {
        clearInterval(_salidaTimer);
        window.location.href = data.redirect;
      }
    } catch(e) {}
  }, 3000);
}
if (cuentaPagada) iniciarPollingsSalida();

// ── Cancelar pedido ────────────────────────────────
function cancelarPedido(pedidoId) {
  if (!confirm('¿Cancelar este pedido?')) return;
  const pedidoEl = document.getElementById('pedido-' + pedidoId);
  const cancelBtns = pedidoEl ? pedidoEl.querySelectorAll('.btn-cancel') : [];
  cancelBtns.forEach(b => b.disabled = true);

  fetch(`<?= BASE_URL ?>menu/${SLUG}/cancelarPedido/${pedidoId}`, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        const badge = document.getElementById('badge-' + pedidoId);
        if (badge) { badge.className = 'badge badge-cancelado'; badge.textContent = 'cancelado'; }
        // Actualizar todos los items del pedido a cancelado y quitar botones
        if (pedidoEl) {
          pedidoEl.querySelectorAll('[id^="item-badge-"]').forEach(ib => {
            if (ib.textContent === 'pendiente') {
              ib.className = 'badge badge-cancelado';
              ib.textContent = 'cancelado';
            }
          });
          cancelBtns.forEach(b => b.remove());
        }
      } else {
        alert(d.msg ?? 'No se pudo cancelar');
        cancelBtns.forEach(b => b.disabled = false);
      }
    })
    .catch(() => { cancelBtns.forEach(b => b.disabled = false); });
}

// ── Generar ticket vía AJAX ────────────────────────────
const REST_NOMBRE = '<?= addslashes($restaurante['nombre'] ?? '') ?>';
const MESA_NOMBRE = '<?= addslashes($mesaNombre) ?>';

function generarTicket() {
  const btn = document.getElementById('btn-generar-ticket');
  if (btn) { btn.disabled = true; btn.textContent = '⏳ Generando...'; }

  fetch(`<?= BASE_URL ?>menu/${SLUG}/generarTicket/${VISITA}`, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) {
        if (btn) { btn.disabled = false; btn.textContent = '📋 Ver resumen y generar ticket'; }
        showToast('⚠️ ' + (d.error || 'No se pudo generar el ticket'));
        return;
      }

      const fmt = v => '$' + parseFloat(v).toFixed(2);

      // Items HTML
      const itemsHtml = (d.items || []).map(it =>
        `<div style="display:flex;justify-content:space-between;padding:4px 0;
                     border-bottom:1px solid rgba(0,0,0,.06);font-size:.85rem">
          <span>${it.cantidad}× ${it.nombre}</span>
          <span>${fmt(it.subtotal)}</span>
        </div>`
      ).join('');

      // QR section
      const qrCode = d.qr_code || QR_CODE;
      const qrUrl  = qrCode ? `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(qrCode)}` : '';
      const qrHtml = qrCode
        ? `<div style="text-align:center;background:#F0FDF4;border:1.5px solid #86EFAC;
                        border-radius:14px;padding:20px;margin-bottom:10px">
             <div style="font-size:1.5rem;margin-bottom:6px">📱</div>
             <div style="font-size:.82rem;font-weight:700;color:#166534;margin-bottom:12px">
               Muestra este QR al salir, por favor
             </div>
             <img src="${qrUrl}"
                  style="width:200px;height:200px;display:block;margin:0 auto 12px;border-radius:8px"
                  alt="QR de salida">
             <div style="font-size:.72rem;color:#9CA3AF;margin-bottom:12px">
               El portero o mesero verificará tu pago al escanearlo
             </div>
             <a href="${qrUrl}" target="_blank" rel="noopener"
                style="display:inline-block;padding:8px 18px;background:#DCFCE7;color:#166534;
                       border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none">
               ⬇️ Guardar QR
             </a>
           </div>`
        : '';

      // Propina row
      const propHtml = parseFloat(d.propina) > 0
        ? `<div style="display:flex;justify-content:space-between;font-size:.85rem;color:#10B981;margin-bottom:4px">
             <span>Propina</span><span>${fmt(d.propina)}</span></div>` : '';

      const result = document.getElementById('ticket-ajax-result');
      if (result) {
        result.innerHTML = `
          <div style="background:#FFFBEB;border:1.5px solid #FCD34D;border-radius:14px;
                      padding:16px;margin-bottom:10px">
            <div style="font-size:.72rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;
                        letter-spacing:.06em;margin-bottom:10px">🧾 Ticket ${d.folio}</div>
            <div>${itemsHtml}</div>
            <div style="margin-top:10px;padding-top:8px;border-top:2px solid rgba(0,0,0,.08)">
              <div style="display:flex;justify-content:space-between;font-size:.85rem;
                          color:#6B7280;margin-bottom:4px">
                <span>Subtotal</span><span>${fmt(d.subtotal)}</span>
              </div>
              ${propHtml}
              <div style="display:flex;justify-content:space-between;
                          font-size:1.05rem;font-weight:800;color:#92400E">
                <span>Total</span><span>${fmt(d.total)}</span>
              </div>
            </div>
          </div>
          ${qrHtml}`;
      }

      if (btn) btn.style.display = 'none';
      document.querySelectorAll('.link-btn-mas').forEach(el => el.style.display = 'none');
      showToast('✅ ¡Cuenta cerrada — listo para pagar!');
    })
    .catch(() => {
      if (btn) { btn.disabled = false; btn.textContent = '🧾 Cerrar cuenta'; }
    });
}
</script>
</body>
</html>
