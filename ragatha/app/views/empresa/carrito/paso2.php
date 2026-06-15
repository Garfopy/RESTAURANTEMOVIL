<?php
$distGuardada = $distribucion ?? [];
?>
<!-- Pasos -->
<div style="display:flex;align-items:center;gap:0;margin-bottom:24px;font-size:.8rem">
  <?php $pasos = ['1'=>'Carrito','2'=>'Sucursales','3'=>'Resumen','4'=>'Listo']; foreach ($pasos as $num => $label): $activo = $num === '2'; $hecho = $num === '1'; ?>
  <div style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:<?= $activo ? 'var(--color-primary)' : ($hecho ? '#D1FAE5' : '#E5E7EB') ?>;color:<?= $activo ? '#fff' : ($hecho ? '#065F46' : '#9CA3AF') ?>;<?= $num==='1' ? 'border-radius:8px 0 0 8px' : ($num==='4' ? 'border-radius:0 8px 8px 0' : '') ?>">
    <span style="font-weight:700"><?= $hecho ? '✓' : $num ?></span><?= $label ?>
  </div>
  <?php if ($num < '4'): ?><div style="width:0;height:0;border-top:18px solid transparent;border-bottom:18px solid transparent;border-left:10px solid <?= $activo ? 'var(--color-primary)' : ($hecho ? '#D1FAE5' : '#E5E7EB') ?>"></div><?php endif; ?>
  <?php endforeach; ?>
</div>

<!-- Guía de uso -->
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:16px;margin-bottom:20px">
  <div style="font-weight:700;color:#1E40AF;margin-bottom:10px;display:flex;align-items:center;gap:8px;font-size:.9rem">
    <svg width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
    ¿Cómo distribuir tu pedido?
  </div>
  <ol style="margin:0;padding:0 0 0 18px;font-size:.83rem;color:#374151;line-height:2.2">
    <li>Cada tarjeta es un producto de tu pedido.</li>
    <li>Indica cuántos <strong><?= count($items) === 1 ? array_values($items)[0]['presentacion'] : 'kg/piezas' ?></strong> van a cada sucursal usando los botones <strong>−</strong> y <strong>+</strong>, o escribe la cantidad directamente.</li>
    <li>Usa el botón <strong>"⚡ Repartir igual"</strong> para dividir automáticamente entre todas tus sucursales.</li>
    <li>La barra verde indica que la distribución está completa. Debes completar todos los productos para continuar.</li>
  </ol>
</div>

<form method="POST" action="<?= BASE_URL ?>carrito/guardarSucursales" id="distForm">
  <?php foreach ($items as $prodId => $item): ?>
  <?php
  $totalProd   = (float)$item['cantidad'];
  $sumGuardado = 0;
  foreach (($distGuardada[$prodId] ?? []) as $q) { $sumGuardado += (float)$q; }
  $completo    = abs($sumGuardado - $totalProd) < 0.01;
  $pct         = $totalProd > 0 ? min(100, ($sumGuardado / $totalProd) * 100) : 0;
  $numSucs     = count($sucursales);
  ?>
  <div style="background:#fff;border-radius:16px;border:2px solid <?= $completo ? '#A7F3D0' : '#E5E7EB' ?>;margin-bottom:18px;overflow:hidden;transition:border-color .3s" id="card-prod-<?= $prodId ?>">

    <!-- Cabecera del producto -->
    <div style="background:<?= $completo ? '#F0FDF4' : '#F9FAFB' ?>;padding:14px 18px;border-bottom:1px solid <?= $completo ? '#D1FAE5' : '#F3F4F6' ?>;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
      <div>
        <span style="font-weight:800;font-size:.95rem;color:#111827"><?= htmlspecialchars($item['nombre']) ?></span>
        <span style="font-size:.78rem;color:#6B7280;margin-left:6px"><?= $item['presentacion'] ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:10px">
        <span style="font-size:.85rem;color:#374151">Total: <strong style="color:var(--color-primary)"><?= number_format($totalProd, 2) ?> <?= $item['presentacion'] ?></strong></span>
        <?php if ($numSucs > 1): ?>
        <button type="button"
                onclick="repartirIgual(<?= $prodId ?>, <?= $totalProd ?>, <?= $numSucs ?>)"
                title="Divide el total en partes iguales entre todas las sucursales"
                style="padding:6px 14px;background:#fff;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.78rem;font-weight:600;color:#374151;cursor:pointer;font-family:inherit;transition:all .15s;white-space:nowrap"
                onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)';this.style.background='#FFF5F5'"
                onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151';this.style.background='#fff'">
          ⚡ Repartir igual
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Barra de progreso -->
    <div style="padding:12px 18px 0;background:<?= $completo ? '#F0FDF4' : '#fff' ?>">
      <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#6B7280;margin-bottom:5px">
        <span>Cantidad asignada</span>
        <span id="progress-text-<?= $prodId ?>"><?= number_format($sumGuardado, 2) ?> / <?= number_format($totalProd, 2) ?> <?= $item['presentacion'] ?></span>
      </div>
      <div style="height:7px;background:#E5E7EB;border-radius:999px;overflow:hidden;margin-bottom:14px">
        <div id="progress-bar-<?= $prodId ?>" style="height:100%;border-radius:999px;transition:width .35s,background .35s;background:<?= $completo ? '#10B981' : ($sumGuardado > $totalProd + 0.01 ? '#EF4444' : '#F59E0B') ?>;width:<?= $pct ?>%"></div>
      </div>
    </div>

    <!-- Grid de sucursales -->
    <div style="padding:0 16px 16px;display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px">
      <?php foreach ($sucursales as $idx => $suc): ?>
      <?php $prevQty = $distGuardada[$prodId][$suc['id']] ?? ''; ?>
      <div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:12px;padding:14px" id="suc-card-<?= $prodId ?>-<?= $suc['id'] ?>">
        <!-- Nombre de sucursal con número -->
        <div style="display:flex;align-items:flex-start;gap:8px;margin-bottom:12px">
          <div style="width:24px;height:24px;border-radius:50%;background:var(--color-primary);color:#fff;font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0"><?= $idx + 1 ?></div>
          <div style="min-width:0">
            <div style="font-size:.84rem;font-weight:700;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($suc['nombre']) ?></div>
            <?php if (!empty($suc['direccion'])): ?>
            <div style="font-size:.7rem;color:#9CA3AF;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars(mb_strimwidth($suc['direccion'], 0, 36, '…')) ?></div>
            <?php endif; ?>
          </div>
        </div>
        <!-- Control +/- -->
        <div style="display:flex;align-items:center;gap:6px">
          <button type="button"
                  onclick="ajustarDistSuc(<?= $prodId ?>, <?= $suc['id'] ?>, -0.5, <?= $totalProd ?>)"
                  style="width:36px;height:36px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;cursor:pointer;font-size:1.1rem;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;font-family:inherit;user-select:none"
                  onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
                  onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'">−</button>
          <input type="number"
                 name="dist[<?= $prodId ?>][<?= $suc['id'] ?>]"
                 class="qty-input prod-<?= $prodId ?>"
                 id="dist-<?= $prodId ?>-<?= $suc['id'] ?>"
                 data-total="<?= $item['cantidad'] ?>"
                 data-prod="<?= $prodId ?>"
                 value="<?= $prevQty ?>"
                 min="0" step="0.5" placeholder="0"
                 onclick="this.select()"
                 style="flex:1;padding:8px 6px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.95rem;text-align:center;font-weight:700;color:#111827;outline:none;min-width:0;transition:border-color .15s"
                 onfocus="this.style.borderColor='var(--color-primary)'"
                 onblur="this.style.borderColor='#D1D5DB'"
                 oninput="onInputDist(this, <?= $prodId ?>)">
          <button type="button"
                  onclick="ajustarDistSuc(<?= $prodId ?>, <?= $suc['id'] ?>, 0.5, <?= $totalProd ?>)"
                  style="width:36px;height:36px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;cursor:pointer;font-size:1.1rem;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s;font-family:inherit;user-select:none"
                  onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
                  onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'">+</button>
        </div>
        <div style="font-size:.68rem;color:#9CA3AF;text-align:center;margin-top:5px"><?= $item['presentacion'] ?> · toca el número para escribir</div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Estado de asignación -->
    <div style="padding:8px 18px 14px;display:flex;align-items:center;gap:8px" id="status-row-<?= $prodId ?>">
      <div id="status-icon-<?= $prodId ?>" style="font-size:.9rem;line-height:1"><?= $completo ? '✅' : '⏳' ?></div>
      <div id="status-text-<?= $prodId ?>" style="font-size:.82rem;font-weight:600;color:<?= $completo ? '#059669' : '#6B7280' ?>">
        <?php if ($completo): ?>Distribución completa — listo para continuar
        <?php elseif ($sumGuardado > $totalProd + 0.01): ?>Excedido: reduce <?= number_format($sumGuardado - $totalProd, 2) ?> <?= $item['presentacion'] ?>
        <?php else: ?>Asigna <?= number_format($totalProd - $sumGuardado, 2) ?> <?= $item['presentacion'] ?> más
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>

  <div style="display:flex;justify-content:space-between;gap:10px;margin-top:8px">
    <a href="<?= BASE_URL ?>carrito/index"
       style="padding:11px 22px;background:#F3F4F6;color:#374151;border-radius:9px;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:7px;transition:background .15s"
       onmouseenter="this.style.background='#E9EBF0'"
       onmouseleave="this.style.background='#F3F4F6'">
      ← Volver al carrito
    </a>
    <button type="submit" id="btnContinuar"
            style="padding:12px 32px;background:var(--color-primary);color:#fff;border:none;border-radius:9px;font-weight:700;font-size:.9rem;cursor:pointer;box-shadow:0 4px 16px rgba(200,16,46,.3);display:flex;align-items:center;gap:8px;transition:all .2s"
            onmouseenter="this.style.boxShadow='0 6px 22px rgba(200,16,46,.4)';this.style.transform='translateY(-1px)'"
            onmouseleave="this.style.boxShadow='0 4px 16px rgba(200,16,46,.3)';this.style.transform='translateY(0)'">
      Ver resumen
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
    </button>
  </div>
</form>

<script>
function ajustarDistSuc(prodId, sucId, delta, totalProd) {
  const input = document.getElementById('dist-' + prodId + '-' + sucId);
  if (!input) return;
  const val = parseFloat(input.value) || 0;
  let sumOtros = 0;
  document.querySelectorAll('.prod-' + prodId).forEach(el => {
    if (el !== input) sumOtros += parseFloat(el.value) || 0;
  });
  const maxPerm = Math.max(0, Math.round((totalProd - sumOtros) * 1000) / 1000);
  let nuevo = Math.max(0, Math.min(maxPerm, Math.round((val + delta) * 2) / 2));
  input.value = nuevo > 0 ? nuevo : '';
  validarTotal(prodId);
}

function onInputDist(input, prodId) {
  let v = parseFloat(input.value) || 0;
  if (v < 0) { input.value = ''; v = 0; }
  let sumOtros = 0;
  document.querySelectorAll('.prod-' + prodId).forEach(el => {
    if (el !== input) sumOtros += parseFloat(el.value) || 0;
  });
  const totalProd = parseFloat(input.dataset.total) || 0;
  const maxPerm = Math.max(0, Math.round((totalProd - sumOtros) * 1000) / 1000);
  if (v > maxPerm + 0.004) { input.value = maxPerm > 0 ? maxPerm.toFixed(2) : ''; }
  validarTotal(prodId);
}

function repartirIgual(prodId, total, numSucs) {
  if (numSucs === 0) return;
  const inputs = document.querySelectorAll('.prod-' + prodId);
  // Redondear a 0.5 cada parte
  const base = Math.floor((total / numSucs) * 2) / 2;
  let suma = 0;
  inputs.forEach((inp, idx) => {
    if (idx < inputs.length - 1) {
      inp.value = base;
      suma += base;
    } else {
      // El último recibe el sobrante para que sume exacto
      const rest = Math.round((total - suma) * 2) / 2;
      inp.value  = rest > 0 ? rest : 0;
    }
  });
  validarTotal(prodId);
}

function validarTotal(prodId) {
  const inputs  = document.querySelectorAll('.prod-' + prodId);
  let suma      = 0;
  inputs.forEach(el => {
    let v = parseFloat(el.value) || 0;
    if (v < 0) { el.value = ''; v = 0; }
    suma += v;
  });
  suma          = Math.round(suma * 1000) / 1000;
  const total   = parseFloat(inputs[0]?.dataset.total || 0);
  const completo = Math.abs(suma - total) < 0.01;
  const pct      = total > 0 ? Math.min(100, (suma / total) * 100) : 0;

  // Barra de progreso
  const bar = document.getElementById('progress-bar-' + prodId);
  const txt = document.getElementById('progress-text-' + prodId);
  if (bar) {
    bar.style.width      = pct + '%';
    bar.style.background = completo ? '#10B981' : '#F59E0B';
  }
  if (txt) txt.textContent = suma.toFixed(2) + ' / ' + total.toFixed(2);

  // Estado texto
  const icon  = document.getElementById('status-icon-' + prodId);
  const stTxt = document.getElementById('status-text-' + prodId);
  const card  = document.getElementById('card-prod-' + prodId);
  if (completo) {
    if (icon)  icon.textContent  = '✅';
    if (stTxt) { stTxt.textContent = 'Distribución completa — listo para continuar'; stTxt.style.color = '#059669'; }
    if (card)  { card.style.borderColor = '#A7F3D0'; card.style.background = ''; }
  } else {
    const falta = (total - suma).toFixed(2);
    if (icon)  icon.textContent  = '⏳';
    if (stTxt) { stTxt.textContent = 'Asigna ' + falta + ' más para completar'; stTxt.style.color = '#6B7280'; }
    if (card)  { card.style.borderColor = '#E5E7EB'; }
  }
}

document.addEventListener('DOMContentLoaded', () => {
  <?php foreach (array_keys($items) as $prodId): ?>
  validarTotal(<?= $prodId ?>);
  <?php endforeach; ?>
});
</script>
