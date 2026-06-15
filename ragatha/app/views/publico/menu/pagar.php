<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pagar cuenta — <?= htmlspecialchars($restaurante['nombre'] ?? '') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/css/restaurant.css">

  <style>
    :root {
      --cp: <?= htmlspecialchars($restaurante['color_primario'] ?? '#C8102E') ?>;
      --cs: <?= htmlspecialchars($restaurante['color_secundario'] ?? '#1f2937') ?>;
    }
    body { background: var(--cs); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
  </style>
</head>
<body>
<div style="width:100%;max-width:420px">
  <!-- Header marca -->
  <div style="text-align:center;margin-bottom:20px;color:#fff">
    <?php if (!empty($restaurante['logo'])): ?>
    <img src="<?= BASE_URL . htmlspecialchars($restaurante['logo']) ?>" alt=""
         style="height:48px;object-fit:contain;margin-bottom:8px">
    <?php endif; ?>
    <div style="font-weight:700;font-size:1.1rem"><?= htmlspecialchars($restaurante['nombre']) ?></div>
  </div>

  <!-- Ticket card -->
  <?php
    $flashErr = $_SESSION['flash_error'] ?? null;
    if ($flashErr) unset($_SESSION['flash_error']);
  ?>
  <?php if ($flashErr): ?>
  <div style="background:#FEE2E2;color:#991B1B;border:1.5px solid #FECACA;border-radius:10px;
               padding:12px 16px;margin-bottom:14px;font-size:.87rem;font-weight:500">
    ⚠️ <?= htmlspecialchars($flashErr) ?>
  </div>
  <?php endif; ?>
  <div class="rst-card" style="border-radius:20px;padding:28px;margin-bottom:0">
    <div style="text-align:center;margin-bottom:20px;padding-bottom:16px;border-bottom:1px dashed #E5E7EB">
      <div style="font-size:.75rem;color:#9CA3AF;font-weight:600;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px">
        Tu cuenta
      </div>
      <div style="font-size:1.1rem;font-weight:700;color:#111827"><?= htmlspecialchars($ticket['folio'] ?? '') ?></div>
      <?php if (!empty($ticket['mesa_nombre'])): ?>
      <div style="font-size:.85rem;color:#6B7280;margin-top:4px">Mesa: <?= htmlspecialchars($ticket['mesa_nombre']) ?></div>
      <?php endif; ?>
    </div>

    <!-- Desglose de ítems ordenados -->
    <?php if (!empty($todoItems)): ?>
    <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px dashed #E5E7EB">
      <div style="font-size:.72rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;
                  letter-spacing:.07em;margin-bottom:10px">Lo que ordenaste</div>
      <?php foreach ($todoItems as $it): ?>
      <div style="display:flex;justify-content:space-between;align-items:baseline;
                  padding:5px 0;font-size:.88rem;border-bottom:1px solid #F9FAFB">
        <div style="flex:1;padding-right:10px">
          <?= htmlspecialchars($it['platillo_nombre'] ?? $it['nombre'] ?? '?') ?>
          <span style="color:#9CA3AF;font-size:.78rem"> ×<?= (int)$it['cantidad'] ?></span>
        </div>
        <span style="font-weight:600;white-space:nowrap">$<?= number_format((float)$it['subtotal'], 2) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Detalle montos -->
    <div style="space-y:8px">
      <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.9rem">
        <span style="color:#6B7280">Subtotal</span>
        <span>$<?= number_format((float)($ticket['subtotal'] ?? 0), 2) ?></span>
      </div>
      <?php if (($ticket['propina'] ?? 0) > 0): ?>
      <div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.9rem">
        <span style="color:#10B981;font-weight:500">Propina</span>
        <span style="color:#10B981">$<?= number_format((float)$ticket['propina'], 2) ?></span>
      </div>
      <?php endif; ?>
      <div style="display:flex;justify-content:space-between;padding:12px 0;border-top:2px solid #F3F4F6;font-size:1.2rem;font-weight:800">
        <span>Total</span>
        <span style="color:var(--cp)">$<?= number_format((float)($ticket['total'] ?? 0), 2) ?></span>
      </div>
    </div>

    <?php if (($ticket['estado'] ?? '') === 'pagado'): ?>
    <!-- Ya pagado — QR de salida -->
    <div style="background:#DCFCE7;border-radius:12px;padding:16px;text-align:center;margin-top:8px">
      <div style="font-size:2rem;margin-bottom:4px">✅</div>
      <div style="font-weight:700;color:#166534;font-size:1rem">¡Cuenta pagada!</div>
      <div style="font-size:.85rem;color:#16A34A;margin-top:4px">
        <?= htmlspecialchars(ucfirst($ticket['metodo_pago'] ?? '')) ?>
      </div>
    </div>
    <?php if (!empty($visita['qr_code']) && empty($visita['salida_at'])): ?>
    <div style="margin-top:16px;text-align:center">
      <div style="font-size:.82rem;color:#374151;font-weight:600;margin-bottom:10px">
        🚪 Muestra este código al portero al salir
      </div>
      <div id="qr-salida" style="display:inline-block;padding:10px;background:#fff;border-radius:10px;border:1px solid #D1FAE5"></div>
      <div style="margin-top:10px;font-family:monospace;font-size:.95rem;font-weight:700;letter-spacing:.08em;color:#111827;background:#F3F4F6;border-radius:8px;padding:8px 14px;display:inline-block"><?= htmlspecialchars(strtoupper(substr($visita['qr_code'], 0, 8))) ?></div>
      <div style="font-size:.72rem;color:#6B7280;margin-top:6px">Si el QR no funciona, muestra este código al portero</div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script>
    new QRCode(document.getElementById('qr-salida'), {
      text: '<?= addslashes($visita['qr_code']) ?>',
      width: 180, height: 180,
      colorDark: '#111827', colorLight: '#ffffff',
      correctLevel: QRCode.CorrectLevel.M
    });
    const _QR_TOKEN = '<?= addslashes($visita['qr_code']) ?>';
    const _BASE_URL = '<?= BASE_URL ?>';
    const _salida_timer = setInterval(async () => {
      try {
        const resp = await fetch(_BASE_URL + 'menu/checkSalida?qr=' + encodeURIComponent(_QR_TOKEN));
        const data = await resp.json();
        if (data.ok && data.salida && data.redirect) {
          clearInterval(_salida_timer);
          window.location.href = data.redirect;
        }
      } catch(e) {}
    }, 3000);
    </script>
    <?php endif; ?>

    <?php else: ?>
    <!-- Selector método de pago -->
    <div style="margin-bottom:16px">
      <div style="font-size:.85rem;font-weight:600;color:#374151;margin-bottom:8px">Método de pago</div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px" id="metodoGrid">
        <?php
        $todosMetodos = [
          ['val'=>'efectivo',       'label'=>'Efectivo',       'icon'=>'💵'],
          ['val'=>'tarjeta',        'label'=>'Tarjeta',        'icon'=>'💳'],
          ['val'=>'transferencia',  'label'=>'Transferencia',  'icon'=>'📲'],
          ['val'=>'paypal',         'label'=>'PayPal',         'icon'=>'🅿️'],
        ];
        $habilitados   = $metodosHabilitados ?? ['efectivo','tarjeta','transferencia','paypal'];
        $primerMetodo  = null;
        foreach ($todosMetodos as $m):
          if (!in_array($m['val'], $habilitados)) continue;
          if ($primerMetodo === null) $primerMetodo = $m['val'];
          $tarjetaOk = ($m['val'] === 'tarjeta') ? !empty($stripePk) : true;
        ?>
        <button type="button"
                <?php if ($tarjetaOk): ?>
                onclick="seleccionarMetodo('<?= $m['val'] ?>')"
                <?php endif; ?>
                data-metodo="<?= $m['val'] ?>"
                class="metodo-btn"
                <?php if (!$tarjetaOk): ?>
                disabled
                title="Pago con tarjeta no configurado"
                <?php endif; ?>
                style="padding:12px;border-radius:10px;border:2px solid #E5E7EB;
                       background:<?= $tarjetaOk ? '#fff' : '#F3F4F6' ?>;
                       cursor:<?= $tarjetaOk ? 'pointer' : 'not-allowed' ?>;
                       opacity:<?= $tarjetaOk ? '1' : '.5' ?>;
                       transition:.15s;text-align:center;font-size:.85rem;font-weight:600">
          <div style="font-size:1.3rem;margin-bottom:3px"><?= $m['icon'] ?></div>
          <?= $m['label'] ?>
          <?php if (!$tarjetaOk): ?>
          <div style="font-size:.65rem;color:#9CA3AF;font-weight:400">No disponible</div>
          <?php endif; ?>
        </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Botón pagar -->
    <form method="POST" action="<?= BASE_URL ?>menu/confirmarPago/<?= htmlspecialchars($restaurante['slug'] ?? '') ?>/<?= (int)($ticket['id'] ?? 0) ?>" id="formPago">
      <input type="hidden" name="metodo_pago" id="inpMetodo" value="efectivo">
      <input type="hidden" name="propina" id="inpPropina" value="0">

      <!-- Propina selector -->
      <div style="margin-bottom:16px">
        <div style="font-size:.85rem;font-weight:600;color:#374151;margin-bottom:8px">¿Deseas dejar propina?</div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px" id="propinaGrid">
          <?php
          $subtotal = (float)($ticket['subtotal'] ?? 0);
          $tips = [0, 10, 15, 20];
          foreach ($tips as $pct):
            $monto = $subtotal * $pct / 100;
          ?>
          <button type="button" onclick="seleccionarPropina(<?= $pct ?>, <?= $monto ?>)"
                  class="propina-btn <?= $pct === 0 ? 'selected' : '' ?>"
                  data-pct="<?= $pct ?>"
                  style="padding:8px 4px;border-radius:8px;border:2px solid #E5E7EB;background:#fff;
                         font-size:.8rem;font-weight:600;cursor:pointer;transition:.15s;text-align:center">
            <?= $pct === 0 ? 'Sin propina' : $pct . '%' ?>
            <?php if ($pct > 0): ?>
            <div style="font-size:.7rem;color:#6B7280;font-weight:400">$<?= number_format($monto, 2) ?></div>
            <?php endif; ?>
          </button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Dividir cuenta (acordeón) -->
      <?php if (!empty($todoItems)): ?>
      <div style="margin-bottom:16px">
        <button type="button" id="btnDividir"
                onclick="toggleDividir()"
                style="width:100%;padding:10px;border:1.5px dashed #D1D5DB;border-radius:10px;
                       background:#F9FAFB;font-size:.85rem;font-weight:600;color:#374151;cursor:pointer;
                       display:flex;align-items:center;justify-content:center;gap:6px">
          👥 Dividir cuenta por ítems
        </button>
        <div id="dividirPanel" style="display:none;margin-top:10px;border:1.5px solid #E5E7EB;
                                       border-radius:12px;padding:14px">
          <div style="font-size:.8rem;color:#6B7280;margin-bottom:10px">
            Selecciona los ítems que vas a pagar tú:
          </div>
          <?php foreach ($todoItems as $it): ?>
          <label style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:8px;
                         cursor:pointer;font-size:.87rem;border:1.5px solid #E5E7EB;margin-bottom:6px">
            <input type="checkbox" class="split-chk"
                   data-subtotal="<?= number_format((float)$it['subtotal'], 2, '.', '') ?>"
                   checked
                   style="width:16px;height:16px;cursor:pointer;accent-color:var(--cp)">
            <span style="flex:1"><?= htmlspecialchars($it['platillo_nombre'] ?? $it['nombre'] ?? '?') ?>
              <span style="color:#9CA3AF;font-size:.78rem">×<?= (int)$it['cantidad'] ?></span>
            </span>
            <span style="font-weight:700;color:#111827">$<?= number_format((float)$it['subtotal'], 2) ?></span>
          </label>
          <?php endforeach; ?>
          <div style="display:flex;justify-content:space-between;padding:10px 0 0;
                       border-top:1.5px solid #F3F4F6;margin-top:4px;font-weight:700">
            <span>Mi parte:</span>
            <span id="splitTotal" style="color:var(--cp)">$<?= number_format((float)($ticket['subtotal']??0), 2) ?></span>
          </div>
          <input type="hidden" name="split_subtotal" id="inpSplitSubtotal" value="">
          <p style="font-size:.72rem;color:#9CA3AF;margin-top:6px">
            Desmarca los ítems de los demás. Cada persona confirma su pago por separado.
          </p>
        </div>
      </div>
      <?php endif; ?>

      <button type="submit" id="btnPagar"
              class="btn btn-primary btn-lg" style="width:100%;justify-content:center;border-radius:12px">
        Confirmar pago $<span id="totalFinal"><?= number_format((float)($ticket['total'] ?? 0), 2) ?></span> →
      </button>
    </form>
    <?php endif; ?>
  </div>

  <?php if (($ticket['estado'] ?? '') !== 'pagado'): ?>
  <a href="<?= BASE_URL ?>menu/<?= htmlspecialchars($restaurante['slug'] ?? '') ?><?= !empty($mesaQr) ? '?mesa=' . urlencode($mesaQr) : '' ?>"
     style="display:block;width:100%;margin-top:12px;padding:14px;border-radius:14px;
            border:2px solid rgba(255,255,255,.25);background:transparent;color:#fff;
            font-size:.95rem;font-weight:600;text-align:center;text-decoration:none;
            transition:.15s"
     onmouseover="this.style.background='rgba(255,255,255,.1)'"
     onmouseout="this.style.background='transparent'">
    ← Seguir ordenando
  </a>
  <?php endif; ?>

  <div style="text-align:center;margin-top:16px;font-size:.72rem;color:rgba(255,255,255,.5)">
    Potenciado por <strong style="color:rgba(255,255,255,.7)">CarniHub</strong>
  </div>
</div>

<style>
  .propina-btn.selected { border-color: var(--cp) !important; background: var(--cp) !important; color: #fff; }
  .propina-btn.selected div { color: rgba(255,255,255,.8) !important; }
  .metodo-btn.selected  { border-color: var(--cp) !important; background: color-mix(in srgb, var(--cp) 10%, white); color: var(--cp); }
</style>

<script>
const subtotal = <?= (float)($ticket['subtotal'] ?? 0) ?>;
const baseTotal = <?= (float)($ticket['total'] ?? 0) ?>;
const TICKET_ID = <?= (int)($ticket['id'] ?? 0) ?>;
const SLUG_PAGO = '<?= htmlspecialchars($restaurante['slug'] ?? '') ?>';
const STRIPE_PK = '<?= htmlspecialchars($stripePk ?? '', ENT_QUOTES) ?>';
let propinaMonto = 0;
let metodoActual = 'efectivo';
let splitSubtotal = null;

// Inicializar: usar el primer método habilitado
const PRIMER_METODO = '<?= htmlspecialchars($primerMetodo ?? 'efectivo', ENT_QUOTES) ?>';
seleccionarMetodo(PRIMER_METODO);
actualizarTotalDisplay();

// ── Propina (AJAX para exactitud) ─────────────────────────────────────────────
function seleccionarPropina(pct, monto) {
  propinaMonto = monto;
  document.querySelectorAll('.propina-btn').forEach(b => b.classList.remove('selected'));
  document.querySelector(`.propina-btn[data-pct="${pct}"]`).classList.add('selected');

  fetch(`<?= BASE_URL ?>menu/${SLUG_PAGO}/actualizarPropina/${TICKET_ID}`, {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `propina=${encodeURIComponent(monto.toFixed(2))}`
  })
  .then(r => r.json())
  .then(d => {
    if (d.ok) {
      propinaMonto = parseFloat(d.propina);
      document.getElementById('inpPropina').value = propinaMonto.toFixed(2);
    }
    actualizarTotalDisplay();
  })
  .catch(() => {
    document.getElementById('inpPropina').value = monto.toFixed(2);
    actualizarTotalDisplay();
  });
}

function actualizarTotalDisplay() {
  const base = (splitSubtotal !== null) ? splitSubtotal : subtotal;
  const total = base + propinaMonto;
  document.getElementById('totalFinal').textContent = total.toFixed(2);
}

function seleccionarMetodo(metodo) {
  metodoActual = metodo;
  document.getElementById('inpMetodo').value = metodo;
  document.querySelectorAll('.metodo-btn').forEach(b => b.classList.remove('selected'));
  const btn = document.querySelector(`.metodo-btn[data-metodo="${metodo}"]`);
  if (btn) btn.classList.add('selected');

  // Stripe Checkout: el pago se procesa en la página de Stripe, no hay Card Element
  if (metodo === 'tarjeta' && !STRIPE_PK) {
    alert('Pago con tarjeta no disponible. Por favor elige otro método.');
    seleccionarMetodo(PRIMER_METODO !== 'tarjeta' ? PRIMER_METODO : 'efectivo');
    return;
  }
}

// ── Dividir cuenta ────────────────────────────────────────────────────────────
function toggleDividir() {
  const panel = document.getElementById('dividirPanel');
  if (!panel) return;
  const visible = panel.style.display !== 'none';
  panel.style.display = visible ? 'none' : 'block';
  if (visible) {
    // Al cerrar, volver a pagar todo
    splitSubtotal = null;
    document.getElementById('inpSplitSubtotal').value = '';
    document.querySelectorAll('.split-chk').forEach(c => c.checked = true);
    document.getElementById('splitTotal').textContent = '$' + subtotal.toFixed(2);
    actualizarTotalDisplay();
  }
}

document.addEventListener('change', e => {
  if (!e.target.classList.contains('split-chk')) return;
  let suma = 0;
  document.querySelectorAll('.split-chk:checked').forEach(c => {
    suma += parseFloat(c.dataset.subtotal) || 0;
  });
  splitSubtotal = suma;
  document.getElementById('inpSplitSubtotal').value = suma.toFixed(2);
  document.getElementById('splitTotal').textContent = '$' + suma.toFixed(2);
  actualizarTotalDisplay();
});

// ── Submit form (Stripe Checkout: el form hace POST normal, el servidor redirige a Stripe) ──
document.getElementById('formPago').addEventListener('submit', function() {
  const btn = document.getElementById('btnPagar');
  btn.disabled = true;
  btn.textContent = 'Procesando…';
});
</script>
</body>
</html>
