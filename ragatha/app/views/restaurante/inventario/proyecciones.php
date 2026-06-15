<?php ob_start(); ?>

<style>
/* ── Variables y reset ───────────────────── */
:root { --cp:#C8102E; --cp-light:#FEF2F2; --cp-border:#FECACA; }

/* ── Layout top bar ─────────────────────── */
.fc-topbar {
  display:flex;justify-content:space-between;align-items:center;
  margin-bottom:20px;flex-wrap:wrap;gap:10px;
}
.fc-topbar h2 { font-size:1.1rem;font-weight:700;color:#111827;margin:0 }
.fc-topbar p  { font-size:.8rem;color:#6B7280;margin:2px 0 0 }

/* ── KPI strip ──────────────────────────── */
.fc-kpis {
  display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));
  gap:12px;margin-bottom:24px;
}
.fc-kpi {
  background:#fff;border:1.5px solid #E5E7EB;border-radius:12px;
  padding:14px 16px;
}
.fc-kpi.red   { border-color:#FECACA;background:#FFF5F5; }
.fc-kpi.amber { border-color:#FDE68A;background:#FFFBEB; }
.fc-kpi.green { border-color:#BBF7D0;background:#F0FDF4; }
.fc-kpi-val  { font-size:1.6rem;font-weight:800;line-height:1; }
.fc-kpi-label { font-size:.72rem;color:#6B7280;margin-top:4px; }

/* ── Tabla de análisis ──────────────────── */
.fc-table { width:100%;border-collapse:collapse;font-size:.84rem; }
.fc-table th {
  background:#F9FAFB;padding:10px 14px;text-align:left;
  font-weight:600;color:#374151;border-bottom:1.5px solid #E5E7EB;
  white-space:nowrap;
}
.fc-table td { padding:11px 14px;border-bottom:1px solid #F3F4F6;vertical-align:middle; }
.fc-table tr:hover td { background:#FAFAFA; }
.fc-table tr.critico td { background:#FFF5F5; }
.fc-table tr.advertencia td { background:#FFFBEB; }

/* ── Badges alerta ──────────────────────── */
.al-badge {
  display:inline-flex;align-items:center;gap:4px;
  padding:3px 8px;border-radius:20px;font-size:.72rem;font-weight:600;
  white-space:nowrap;
}
.al-critico     { background:#FEE2E2;color:#991B1B; }
.al-advertencia { background:#FEF3C7;color:#92400E; }
.al-ok          { background:#DCFCE7;color:#166534; }
.al-sin_datos   { background:#F3F4F6;color:#6B7280; }

/* ── Barra de días ──────────────────────── */
.dias-bar {
  height:6px;border-radius:3px;background:#F3F4F6;overflow:hidden;
  margin-top:4px;width:100%;max-width:120px;
}
.dias-bar-fill {
  height:100%;border-radius:3px;transition:width .3s;
  background:linear-gradient(90deg,#22C55E,#4ADE80);
}
.dias-bar-fill.warn { background:linear-gradient(90deg,#F59E0B,#FBBF24); }
.dias-bar-fill.crit { background:linear-gradient(90deg,#EF4444,#F87171); }

/* ── Mini sparkline ─────────────────────── */
.spark-wrap { display:flex;align-items:flex-end;gap:2px;height:28px; }
.spark-bar  { width:8px;border-radius:2px 2px 0 0;background:#CBD5E1;flex-shrink:0; }

/* ── Panel de acciones ──────────────────── */
.fc-actions {
  background:#fff;border:1.5px solid #E5E7EB;border-radius:14px;
  padding:20px;margin-bottom:24px;
}
.fc-actions h3 { font-size:.92rem;font-weight:700;color:#111827;margin:0 0 12px; }

/* ── Responsive table wrapper ───────────── */
.fc-table-wrap {
  background:#fff;border:1.5px solid #E5E7EB;border-radius:14px;
  overflow:hidden;
}
.fc-table-wrap .fc-thead-wrap { overflow-x:auto; }
</style>

<!-- Top bar -->
<div class="fc-topbar">
  <div>
    <h2>📊 Proyección Inteligente de Inventario</h2>
    <p>Basada en consumo de los últimos 7 días · Actualizada al <?= date('d/m/Y H:i') ?></p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>rest-inventario/pedidosSugeridos" class="btn btn-outline btn-sm" style="white-space:nowrap">📋 Historial</a>
    <a href="<?= BASE_URL ?>rest-inventario/index" class="btn btn-outline btn-sm">← Inventario</a>
  </div>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] ?>" style="margin-bottom:16px"><?= $flash['message'] ?></div>
<?php endif; ?>

<!-- ── Comprobante: pedido generado automáticamente ─────────────── -->
<?php if (!empty($comprobante)): ?>
<div style="background:#F0FDF4;border:2px solid #4ADE80;border-radius:16px;padding:20px 24px;margin-bottom:20px">
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
    <div style="width:44px;height:44px;background:#DCFCE7;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0">✅</div>
    <div>
      <div style="font-size:1rem;font-weight:700;color:#14532D">Pedido generado automáticamente</div>
      <div style="font-size:.78rem;color:#16A34A"><?= date('d/m/Y \a \l\a\s H:i') ?> &middot; <?= count($comprobante) ?> pedido(s) enviado(s) al proveedor CarniHub</div>
    </div>
  </div>
  <?php foreach ($comprobante as $c): ?>
  <div style="background:#fff;border:1.5px solid #BBF7D0;border-radius:12px;margin-bottom:12px;overflow:hidden">
    <div style="background:#DCFCE7;padding:10px 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
      <div>
        <span style="font-weight:700;color:#14532D;font-size:.92rem"><?= htmlspecialchars($c['empresa']) ?></span>
        <span style="margin-left:10px;font-size:.74rem;font-family:monospace;background:#fff;color:#166534;border:1px solid #BBF7D0;padding:2px 8px;border-radius:10px"><?= htmlspecialchars($c['folio']) ?></span>
      </div>
      <strong style="color:#14532D;font-size:.95rem">Total: $<?= number_format($c['total'],2) ?></strong>
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:.82rem">
      <thead>
        <tr style="background:#F0FDF4">
          <th style="padding:7px 14px;text-align:left;color:#166534;font-weight:600">Ingrediente</th>
          <th style="padding:7px 14px;text-align:right;color:#166534;font-weight:600">Cantidad</th>
          <th style="padding:7px 14px;text-align:right;color:#166534;font-weight:600">Precio unit.</th>
          <th style="padding:7px 14px;text-align:right;color:#166534;font-weight:600">Subtotal</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($c['items'] as $it): ?>
        <tr style="border-top:1px solid #DCFCE7">
          <td style="padding:7px 14px;color:#111827"><?= htmlspecialchars($it['nombre']) ?></td>
          <td style="padding:7px 14px;text-align:right;color:#374151"><?= number_format($it['cantidad'],3) ?> <span style="font-size:.72rem;color:#9CA3AF"><?= htmlspecialchars($it['unidad']) ?></span></td>
          <td style="padding:7px 14px;text-align:right;color:#374151">$<?= number_format($it['precio'],2) ?></td>
          <td style="padding:7px 14px;text-align:right;font-weight:700;color:#111827">$<?= number_format($it['subtotal'],2) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="padding:8px 16px;font-size:.72rem;color:#6B7280;display:flex;justify-content:space-between;flex-wrap:wrap;gap:4px">
      <?php if ($c['enviado'] ?? false): ?>
        <span style="color:#166534">✅ Enviado a CarniHub · Estado inicial: <strong>pendiente</strong></span>
      <?php else: ?>
        <span style="color:#92400E">⚠️ No se pudo enviar automáticamente: <?= htmlspecialchars($c['carnihub_error'] ?? 'Error desconocido') ?></span>
        &nbsp;·&nbsp;
        <a href="<?= BASE_URL ?>rest-inventario/pedidosSugeridos" style="color:#D97706">Reintentar manualmente →</a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>rest-inventario/pedidosSugeridos" style="color:#2563EB">Ver historial &rarr;</a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php elseif (!empty($ultimoPedidoAt)): ?>
<div style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:12px;padding:13px 18px;margin-bottom:20px;font-size:.84rem;color:#1D4ED8;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
  <span>📦 <strong>Pedido automático enviado</strong> el <?= date('d/m/Y \a \l\a\s H:i', strtotime($ultimoPedidoAt)) ?></span>
  <div style="display:flex;gap:12px;align-items:center">
    <a href="<?= BASE_URL ?>rest-inventario/pedidosSugeridos" style="color:#2563EB;font-size:.8rem">Ver historial &rarr;</a>
    <a href="<?= BASE_URL ?>rest-inventario/proyecciones?forzar=1" style="color:#9CA3AF;font-size:.75rem">Forzar nuevo pedido</a>
  </div>
</div>
<?php endif; ?>

<!-- KPIs -->
<?php
$totalIng    = count($analisis);
$nCriticos   = count(array_filter($analisis, fn($i) => $i['nivel_alerta'] === 'critico'));
$nAdvertencia = count(array_filter($analisis, fn($i) => $i['nivel_alerta'] === 'advertencia'));
$nOk         = count(array_filter($analisis, fn($i) => $i['nivel_alerta'] === 'ok'));
$nSinDatos   = count(array_filter($analisis, fn($i) => $i['nivel_alerta'] === 'sin_datos'));
$puedenPedirse = count(array_filter($analisis, fn($i) => $i['requiere_pedido'] && !empty($i['empresa'])));
?>
<div class="fc-kpis">
  <div class="fc-kpi red">
    <div class="fc-kpi-val" style="color:#DC2626"><?= $nCriticos ?></div>
    <div class="fc-kpi-label">🔴 Críticos (se agotan antes de entrega)</div>
  </div>
  <div class="fc-kpi amber">
    <div class="fc-kpi-val" style="color:#D97706"><?= $nAdvertencia ?></div>
    <div class="fc-kpi-label">🟡 Advertencia (margen ajustado)</div>
  </div>
  <div class="fc-kpi green">
    <div class="fc-kpi-val" style="color:#16A34A"><?= $nOk ?></div>
    <div class="fc-kpi-label">🟢 Stock suficiente</div>
  </div>
  <div class="fc-kpi">
    <div class="fc-kpi-val" style="color:#374151"><?= $nSinDatos ?></div>
    <div class="fc-kpi-label">⚪ Sin historial de consumo</div>
  </div>
  <div class="fc-kpi" style="border-color:#BFDBFE;background:#EFF6FF">
    <div class="fc-kpi-val" style="color:#1D4ED8"><?= $puedenPedirse ?></div>
    <div class="fc-kpi-label">📦 Listo para pedir (tienen proveedor CarniHub)</div>
  </div>
</div>

<?php
// Ingredientes críticos sin vínculo CarniHub (no pueden pedirse automáticamente)
$criticosSinLink = count(array_filter($analisis, fn($i) => $i['requiere_pedido'] && empty($i['empresa'])));
?>
<?php if ($criticosSinLink > 0): ?>
<div class="fc-actions" style="border-color:#FDE68A;background:#FFFBEB">
  <h3 style="color:#92400E">⚠️ <?= $criticosSinLink ?> ingrediente<?= $criticosSinLink>1?'s':'' ?> crítico<?= $criticosSinLink>1?'s':'' ?> sin proveedor CarniHub</h3>
  <p style="font-size:.85rem;color:#374151;margin:0">
    Para que los pedidos se generen automáticamente al cargar esta página,
    vincula estos ingredientes a productos de tu empresa proveedora CarniHub
    desde el formulario de inventario (<strong>Proveedor CarniHub</strong>).
  </p>
</div>
<?php endif; ?>

<!-- Tabla de análisis completo -->
<div class="fc-table-wrap">
  <div style="padding:16px 20px;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center">
    <div style="font-weight:700;font-size:.95rem;color:#111827">Análisis por ingrediente</div>
    <div style="display:flex;gap:8px">
      <button onclick="filtrarNivel('all')" id="f-all" class="fc-f-btn active">Todos (<?= $totalIng ?>)</button>
      <?php if ($nCriticos > 0): ?>
      <button onclick="filtrarNivel('critico')" id="f-critico" class="fc-f-btn">🔴 Críticos (<?= $nCriticos ?>)</button>
      <?php endif; ?>
      <?php if ($nAdvertencia > 0): ?>
      <button onclick="filtrarNivel('advertencia')" id="f-advertencia" class="fc-f-btn">🟡 Advert. (<?= $nAdvertencia ?>)</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="fc-thead-wrap">
    <table class="fc-table">
      <thead>
        <tr>
          <th>Ingrediente</th>
          <th>Stock actual</th>
          <th>Consumo diario</th>
          <th>Promedio móvil (3d)</th>
          <th>Días restantes</th>
          <th>Proyección 7 días</th>
          <th>Estado</th>
          <th>Proveedor</th>
          <th style="text-align:right">Pedir</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($analisis)): ?>
        <tr><td colspan="9" style="text-align:center;padding:32px;color:#9CA3AF">
          Sin ingredientes activos. Agrega ingredientes en el inventario.
        </td></tr>
        <?php endif; ?>
        <?php foreach ($analisis as $ing):
          $nivel   = $ing['nivel_alerta'];
          $cpd     = $ing['cpd'];
          $movil   = $ing['promedio_movil'];
          $dias    = $ing['dias_restantes'];
          $leadTime = (int)($ing['dias_entrega'] ?? 1);
          $stock   = (float)$ing['stock'];
          $cantPedir = (float)$ing['cantidad_sugerida'];

          // Calcular % días para la barra (escala 0-14 días)
          $diasPct  = ($dias !== null) ? min(100, round(($dias / 14) * 100)) : 0;
          $diaCls   = ($nivel === 'critico') ? 'crit' : (($nivel === 'advertencia') ? 'warn' : '');
          $diasLabel = ($dias === null) ? '∞' : (($dias < 1) ? '< 1 día' : round($dias, 1) . ' d');

          // Sparkline: proyeccion 7d para mini-chart
          $proj7   = $ing['proyeccion_7d'];
          $maxProj = max(array_column($proj7, 'stock_proyectado') ?: [0.01]);
        ?>
        <tr class="fc-row <?= $nivel ?>" data-nivel="<?= $nivel ?>">
          <td>
            <div style="font-weight:600;color:#111827"><?= htmlspecialchars($ing['nombre']) ?></div>
            <?php if ($ing['categoria']): ?>
            <small style="color:#9CA3AF;font-size:.72rem"><?= htmlspecialchars($ing['categoria']) ?></small>
            <?php endif; ?>
          </td>
          <td>
            <strong><?= number_format($stock, 2) ?></strong>
            <span style="color:#9CA3AF;font-size:.75rem"> <?= htmlspecialchars($ing['unidad_principal']) ?></span>
          </td>
          <td>
            <?php if ($cpd > 0): ?>
            <span style="font-weight:600"><?= number_format($cpd, 3) ?></span>
            <span style="color:#9CA3AF;font-size:.75rem"> <?= htmlspecialchars($ing['unidad_principal']) ?>/día</span>
            <?php else: ?>
            <span style="color:#D1D5DB;font-size:.8rem">Sin datos</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($movil > 0): ?>
            <span style="font-weight:600"><?= number_format($movil, 3) ?></span>
            <span style="color:#9CA3AF;font-size:.75rem"> <?= htmlspecialchars($ing['unidad_principal']) ?>/día</span>
            <?php else: ?>
            <span style="color:#D1D5DB;font-size:.8rem">—</span>
            <?php endif; ?>
          </td>
          <td>
            <div style="font-weight:600;color:<?= $nivel==='critico'?'#DC2626':($nivel==='advertencia'?'#D97706':'#111827') ?>">
              <?= $diasLabel ?>
            </div>
            <div class="dias-bar">
              <div class="dias-bar-fill <?= $diaCls ?>" style="width:<?= $diasPct ?>%"></div>
            </div>
            <?php if ($leadTime > 0 && $cpd > 0): ?>
            <div style="font-size:.68rem;color:#9CA3AF;margin-top:2px">Lead time: <?= $leadTime ?>d</div>
            <?php endif; ?>
          </td>
          <td>
            <!-- Mini sparkline del stock proyectado -->
            <div class="spark-wrap" title="Stock proyectado 7 días">
              <?php foreach ($proj7 as $d):
                $pct = $maxProj > 0 ? max(2, round(($d['stock_proyectado'] / $maxProj) * 28)) : 2;
                $col = $d['stock_proyectado'] <= 0 ? '#EF4444' : ($pct < 10 ? '#F59E0B' : '#6EE7B7');
              ?>
              <div class="spark-bar" style="height:<?= $pct ?>px;background:<?= $col ?>"
                   title="<?= $d['fecha'] ?>: <?= number_format($d['stock_proyectado'],2) ?> <?= htmlspecialchars($ing['unidad_principal']) ?>">
              </div>
              <?php endforeach; ?>
            </div>
            <div style="font-size:.65rem;color:#9CA3AF;margin-top:2px">
              <?= date('d/m', strtotime('+1 day')) ?> → <?= date('d/m', strtotime('+7 days')) ?>
            </div>
          </td>
          <td>
            <span class="al-badge al-<?= $nivel ?>">
              <?php
              echo match($nivel) {
                'critico'     => '🔴 Crítico',
                'advertencia' => '🟡 Advertencia',
                'ok'          => '🟢 OK',
                default       => '⚪ Sin datos',
              };
              ?>
            </span>
            <?php if ($ing['requiere_pedido']): ?>
            <div style="font-size:.68rem;color:#DC2626;margin-top:3px;font-weight:600">
              ⚡ Pedir ahora
            </div>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($ing['empresa']): ?>
            <div style="font-size:.78rem;font-weight:600;color:#1D4ED8"><?= htmlspecialchars($ing['empresa']['razon_social']) ?></div>
            <div style="font-size:.68rem;color:#9CA3AF">$<?= number_format($ing['empresa']['precio_base'], 2) ?>/<?= htmlspecialchars($ing['empresa']['unidad']) ?></div>
            <?php elseif ($ing['proveedor_nombre']): ?>
            <span style="font-size:.75rem;color:#6B7280"><?= htmlspecialchars($ing['proveedor_nombre']) ?></span>
            <div style="font-size:.68rem;color:#F59E0B;margin-top:2px">Sin vínculo CarniHub</div>
            <?php else: ?>
            <span style="font-size:.75rem;color:#D1D5DB">Sin proveedor</span>
            <?php endif; ?>
          </td>
          <td style="text-align:right">
            <?php if ($cantPedir > 0): ?>
            <div style="font-weight:700;color:#111827;font-size:.88rem">
              <?= number_format($cantPedir, 2) ?>
              <span style="font-size:.7rem;color:#9CA3AF"><?= htmlspecialchars($ing['unidad_principal']) ?></span>
            </div>
            <?php else: ?>
            <span style="color:#D1D5DB;font-size:.78rem">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="margin-top:12px;padding:10px 16px;background:#F9FAFB;border-radius:8px;font-size:.76rem;color:#9CA3AF">
  💡 <strong>Cómo funciona:</strong>
  Consumo promedio diario = consumo total últimos 7 días / días con actividad ·
  Promedio móvil = promedio de los últimos 3 días ·
  Días restantes = stock / consumo diario ·
  Cantidad a pedir cubre lead time + 7 días extra de buffer
</div>

<style>
.fc-f-btn {
  padding:5px 12px;border:1.5px solid #E5E7EB;border-radius:8px;background:#fff;
  font-size:.77rem;cursor:pointer;color:#374151;transition:.15s;font-weight:500;
}
.fc-f-btn:hover,.fc-f-btn.active {
  border-color:var(--cp);color:var(--cp);background:#FFF5F5;
}
</style>

<script>
function filtrarNivel(nivel) {
  document.querySelectorAll('.fc-row').forEach(tr => {
    tr.style.display = (nivel === 'all' || tr.dataset.nivel === nivel) ? '' : 'none';
  });
  document.querySelectorAll('.fc-f-btn').forEach(b => b.classList.remove('active'));
  const btn = document.getElementById('f-' + nivel);
  if (btn) btn.classList.add('active');
}
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
?>
  <div style="background:#fff;border-radius:16px;width:100%;max-width:680px;max-height:90vh;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 25px 60px rgba(0,0,0,.25)">
    <div style="padding:20px 24px;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center">
      <div>
        <h3 style="margin:0;font-size:1rem;font-weight:700;color:#111827">✅ Pedidos enviados a proveedores</h3>
        <p id="modalSubtitle" style="margin:3px 0 0;font-size:.78rem;color:#6B7280"></p>
      </div>
      <button onclick="cerrarModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#9CA3AF">×</button>
    </div>
    <div id="modalCuerpo" style="padding:20px 24px;overflow-y:auto;flex:1"></div>
    <div style="padding:14px 24px;border-top:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center">
      <a href="<?= BASE_URL ?>rest-inventario/pedidosSugeridos" style="font-size:.82rem;color:#2563EB">Ver historial completo →</a>
      <button onclick="cerrarModal()" class="btn btn-primary btn-sm">Cerrar</button>
    </div>
  </div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';

function filtrarNivel(nivel) {
  document.querySelectorAll('.fc-row').forEach(tr => {
    tr.style.display = (nivel === 'all' || tr.dataset.nivel === nivel) ? '' : 'none';
  });
  document.querySelectorAll('.fc-f-btn').forEach(b => b.classList.remove('active'));
  const btn = document.getElementById('f-' + nivel);
  if (btn) btn.classList.add('active');
}

function cerrarModal() {
  document.getElementById('modalPedidos').style.display = 'none';
}

async function generarPedidos() {
  const btn    = document.getElementById('btnGenerar');
  const status = document.getElementById('genStatus');
  btn.disabled = true;
  btn.textContent = '⏳ Procesando…';
  status.style.display = 'inline';

  try {
    const res  = await fetch(BASE + 'rest-inventario/generarPedidoAutomatico', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const data = await res.json();

    status.style.display = 'none';

    if (data.ok) {
      btn.textContent = '✅ Pedidos enviados';
      btn.style.background = '#16A34A';
      btn.style.borderColor = '#16A34A';
      mostrarModalDetalle(data.pedidos);
    } else {
      btn.disabled = false;
      btn.textContent = '⚡ Generar pedidos automáticamente';
      status.textContent = '⚠️ ' + (data.error || 'Error desconocido');
      status.style.display = 'inline';
      status.style.color = '#DC2626';
    }
  } catch (e) {
    btn.disabled = false;
    btn.textContent = '⚡ Generar pedidos automáticamente';
    status.textContent = '⚠️ Error de red';
    status.style.display = 'inline';
    status.style.color = '#DC2626';
  }
}

function mostrarModalDetalle(pedidos) {
  const totalGeneral = pedidos.reduce((s, p) => s + parseFloat(p.total), 0);
  document.getElementById('modalSubtitle').textContent =
    pedidos.length + ' pedido(s) generado(s) · Total: $' + totalGeneral.toFixed(2);

  let html = '';
  pedidos.forEach(p => {
    html += `
      <div style="margin-bottom:20px;border:1.5px solid #E5E7EB;border-radius:12px;overflow:hidden">
        <div style="background:#F9FAFB;padding:10px 14px;display:flex;justify-content:space-between;align-items:center">
          <div>
            <span style="font-weight:700;font-size:.9rem;color:#111827">${p.empresa}</span>
            <span style="margin-left:10px;font-size:.75rem;color:#6B7280;background:#E5E7EB;padding:2px 8px;border-radius:12px">${p.folio}</span>
          </div>
          <span style="font-weight:700;color:#111827">$${parseFloat(p.total).toFixed(2)}</span>
        </div>
        <table style="width:100%;border-collapse:collapse;font-size:.8rem">
          <thead>
            <tr style="background:#F3F4F6">
              <th style="padding:7px 12px;text-align:left;color:#374151">Ingrediente</th>
              <th style="padding:7px 12px;text-align:right;color:#374151">Cantidad</th>
              <th style="padding:7px 12px;text-align:right;color:#374151">Precio unit.</th>
              <th style="padding:7px 12px;text-align:right;color:#374151">Subtotal</th>
            </tr>
          </thead>
          <tbody>`;
    p.items.forEach(it => {
      html += `<tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:7px 12px">${it.nombre}</td>
        <td style="padding:7px 12px;text-align:right">${parseFloat(it.cantidad).toFixed(2)} ${it.unidad}</td>
        <td style="padding:7px 12px;text-align:right">$${parseFloat(it.precio).toFixed(2)}</td>
        <td style="padding:7px 12px;text-align:right;font-weight:600">$${parseFloat(it.subtotal).toFixed(2)}</td>
      </tr>`;
    });
    html += `</tbody></table>
        <div style="padding:8px 14px;text-align:right;font-size:.75rem;color:#6B7280">
          El proveedor ya recibió este pedido con estado <strong>pendiente</strong>.
        </div>
      </div>`;
  });

  document.getElementById('modalCuerpo').innerHTML = html;
  const modal = document.getElementById('modalPedidos');
  modal.style.display = 'flex';
}

document.getElementById('modalPedidos').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
?>
