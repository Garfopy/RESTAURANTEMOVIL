<?php ob_start(); ?>
<style>
  .merma-card { background:#fff;border-radius:12px;padding:18px;border:1px solid #E5E7EB }
  .merma-card .lbl { font-size:.72rem;color:#6B7280;text-transform:uppercase;letter-spacing:.5px;margin-bottom:6px;font-weight:600 }
  .merma-card .val { font-size:1.5rem;font-weight:700;color:#111827;line-height:1.1 }
  .merma-card .sub { font-size:.72rem;color:#9CA3AF;margin-top:4px }
  .merma-section-title { font-size:.72rem;color:#6B7280;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin:18px 0 10px }
  .merma-chart-wrap { background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:16px }
  .merma-chart-wrap .ttl { font-weight:600;margin-bottom:10px;font-size:.9rem;color:#111827 }
  .merma-table { width:100%;border-collapse:collapse;font-size:.82rem }
  .merma-table th { background:#1f2937;color:#fff;text-align:left;padding:10px 12px;font-weight:600;font-size:.75rem;text-transform:uppercase;letter-spacing:.5px }
  .merma-table td { padding:9px 12px;border-bottom:1px solid #F3F4F6 }
  .merma-table tbody tr:nth-child(even) { background:#F9FAFB }
  .merma-notas { background:#FEF3C7;border-left:4px solid #F59E0B;border-radius:8px;padding:14px 16px }
  .merma-notas h4 { margin:0 0 8px;font-size:.78rem;color:#92400E;text-transform:uppercase;letter-spacing:.6px;font-weight:700 }
  .merma-notas ul { margin:0;padding-left:18px;color:#7C2D12;font-size:.85rem;line-height:1.7 }
  .btn-merma-primary { padding:9px 16px;background:#C8102E;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px }
  .btn-merma-outline { padding:9px 16px;background:#fff;color:#1f2937;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px }
</style>

<!-- Panel de período + rangos rápidos -->
<form method="GET" action="<?= BASE_URL ?>rest-mermas/index" id="formFiltro">
  <input type="hidden" name="desde" id="inputDesde" value="<?= htmlspecialchars($desde) ?>">
  <input type="hidden" name="hasta" id="inputHasta" value="<?= htmlspecialchars($hasta) ?>">

  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:14px 18px;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
    <div>
      <div style="font-size:.68rem;color:#6B7280;text-transform:uppercase;letter-spacing:.6px;font-weight:700">Período de análisis</div>
      <div style="font-weight:600;font-size:.95rem;margin-top:3px" id="periodoLabel">
        <?php
          $diasDiff = (strtotime($hasta) - strtotime($desde)) / 86400;
          if ($diasDiff == 0)        echo 'Hoy';
          elseif ($diasDiff == 6)    echo 'Últimos 7 días';
          elseif ($diasDiff == 29)   echo 'Últimos 30 días';
          elseif ($diasDiff == 89)   echo 'Últimos 90 días';
          elseif ($desde == date('Y-01-01')) echo 'Este año';
          else echo htmlspecialchars($desde) . ' — ' . htmlspecialchars($hasta);
        ?>
      </div>
    </div>
    <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
      <?php
        $hoy     = date('Y-m-d');
        $rangos  = [
          ['Hoy',        $hoy,                       $hoy],
          ['7 días',     date('Y-m-d',strtotime('-6 days')), $hoy],
          ['30 días',    date('Y-m-d',strtotime('-29 days')),$hoy],
          ['90 días',    date('Y-m-d',strtotime('-89 days')),$hoy],
          ['Este año',   date('Y-01-01'),              $hoy],
        ];
        foreach ($rangos as [$label, $d, $h]):
          $active = ($desde === $d && $hasta === $h);
      ?>
      <a href="<?= BASE_URL ?>rest-mermas/index?desde=<?= $d ?>&hasta=<?= $h ?>"
         style="padding:7px 14px;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none;border:1px solid <?= $active ? 'transparent' : '#D1D5DB' ?>;background:<?= $active ? '#C8102E' : '#fff' ?>;color:<?= $active ? '#fff' : '#374151' ?>;transition:.15s"
         onmouseover="if(this.style.background!='rgb(200, 16, 46)')this.style.background='#F3F4F6'"
         onmouseout="if(this.style.background!='rgb(200, 16, 46)')this.style.background='#fff'"><?= $label ?></a>
      <?php endforeach; ?>
      <button type="button"
              onclick="document.getElementById('rangoCustom').style.display=document.getElementById('rangoCustom').style.display==='none'?'flex':'none'"
              style="padding:7px 14px;border-radius:8px;font-size:.82rem;font-weight:600;border:1px solid #D1D5DB;background:#fff;color:#374151;cursor:pointer">Personalizado</button>
    </div>
  </div>

  <!-- Rango personalizado (oculto por defecto) -->
  <div id="rangoCustom" style="display:none;gap:10px;align-items:center;background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:10px 16px;margin-bottom:10px;flex-wrap:wrap">
    <input type="date" id="customDesde" value="<?= htmlspecialchars($desde) ?>"
           style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
    <span style="color:#9CA3AF">—</span>
    <input type="date" id="customHasta" value="<?= htmlspecialchars($hasta) ?>"
           style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
    <button type="button" class="btn-merma-primary" onclick="aplicarCustom()">Aplicar</button>
  </div>
</form>

<!-- Panel secciones PDF + acciones -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap">
    <span style="font-size:.68rem;color:#6B7280;text-transform:uppercase;letter-spacing:.6px;font-weight:700">Mostrar / incluir en PDF:</span>
    <?php foreach ([['chkLogo','Logo'],['chkKpis','KPIs'],['chkGraficas','Gráficas'],['chkTabla','Tabla'],['chkNotas','Notas']] as [$id,$lbl]): ?>
    <label style="display:flex;align-items:center;gap:5px;font-size:.85rem;font-weight:500;color:#374151;cursor:pointer">
      <input type="checkbox" id="<?= $id ?>" checked
             style="width:15px;height:15px;accent-color:#1f2937;cursor:pointer">
      <?= $lbl ?>
    </label>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;gap:8px">
    <button type="button" class="btn-merma-outline" onclick="document.getElementById('modalRegMerma').classList.add('open')">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Registrar merma
    </button>
    <button type="button" style="padding:9px 16px;background:#1f2937;color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:6px" onclick="generarReporteMermasPDF()">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      Generar reporte PDF
    </button>
  </div>
</div>

<!-- Indicadores -->
<div class="merma-section-title">Indicadores del período</div>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px">
  <div class="merma-card">
    <div class="lbl">Total mermas</div>
    <div class="val" style="color:#C8102E"><?= number_format($kpis['cantidad_total'],3) ?></div>
    <div class="sub">unidades acumuladas</div>
  </div>
  <div class="merma-card">
    <div class="lbl">Eventos del período</div>
    <div class="val"><?= number_format($kpis['eventos']) ?></div>
    <div class="sub">registros tipo merma</div>
  </div>
  <div class="merma-card">
    <div class="lbl">Ingredientes afectados</div>
    <div class="val"><?= number_format($kpis['ingredientes_afectados']) ?></div>
    <div class="sub">productos distintos</div>
  </div>
  <div class="merma-card">
    <div class="lbl">Valor estimado</div>
    <div class="val" style="color:#EF4444">$<?= number_format($kpis['valor_estimado'],2) ?></div>
    <div class="sub">según costo unitario</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:14px">
  <div class="merma-card">
    <div class="lbl">% merma vs consumo</div>
    <div class="val"><?= number_format($kpis['pct_merma'],2) ?>%</div>
    <div class="sub">Meta ideal &lt; 5%</div>
  </div>
  <div class="merma-card">
    <div class="lbl">Promedio diario</div>
    <div class="val" style="color:#F59E0B"><?= number_format($kpis['promedio_diario'],3) ?></div>
    <div class="sub">unidades por día</div>
  </div>
  <div class="merma-card">
    <div class="lbl">Motivo más frecuente</div>
    <div class="val" style="font-size:1rem"><?= htmlspecialchars($kpis['top_motivo']) ?></div>
    <div class="sub">causa principal</div>
  </div>
  <div class="merma-card">
    <div class="lbl">Ingrediente más afectado</div>
    <div class="val" style="font-size:1rem"><?= htmlspecialchars($kpis['top_ingrediente']) ?></div>
    <div class="sub">a vigilar</div>
  </div>
</div>

<!-- Gráficas -->
<div class="merma-section-title">Gráficas</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
  <div class="merma-chart-wrap">
    <div class="ttl">Top 10 productos con más mermas</div>
    <div style="position:relative;height:240px"><canvas id="chartTopMermas"></canvas></div>
  </div>
  <div class="merma-chart-wrap">
    <div class="ttl">Distribución por motivo</div>
    <div style="position:relative;height:240px"><canvas id="chartMotivos"></canvas></div>
  </div>
</div>
<div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
  <div class="merma-chart-wrap">
    <div class="ttl">Tendencia diaria de mermas</div>
    <div style="position:relative;height:240px"><canvas id="chartTendencia"></canvas></div>
  </div>
  <div class="merma-chart-wrap">
    <div class="ttl">Top 10 productos con menos mermas</div>
    <div style="position:relative;height:240px"><canvas id="chartMenosMermas"></canvas></div>
  </div>
</div>

<!-- Detalle -->
<div class="merma-section-title">Detalle de mermas</div>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px">
  <div style="overflow-x:auto;max-height:480px;overflow-y:auto">
    <table class="merma-table">
      <thead>
        <tr>
          <th>Fecha</th>
          <th>Ingrediente</th>
          <th>Cantidad</th>
          <th>Valor</th>
          <th>Motivo</th>
          <th>Usuario</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($detalle)): ?>
          <tr><td colspan="6" style="text-align:center;color:#9CA3AF;padding:24px">Sin mermas registradas en el período.</td></tr>
        <?php else: foreach ($detalle as $d): ?>
          <tr>
            <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($d['created_at']))) ?></td>
            <td><?= htmlspecialchars($d['ingrediente_nombre']) ?></td>
            <td><?= number_format((float)$d['cantidad'],3) ?> <span style="color:#9CA3AF;font-size:.75rem"><?= htmlspecialchars($d['unidad_principal']) ?></span></td>
            <td>$<?= number_format((float)$d['cantidad'] * (float)$d['costo_unitario'],2) ?></td>
            <td><?= htmlspecialchars($d['motivo'] ?: '—') ?></td>
            <td><?= htmlspecialchars($d['usuario_nombre'] ?: '—') ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Notas críticas -->
<?php
$notas = [];
if ($kpis['valor_estimado'] > 0) $notas[] = 'Valor estimado de mermas en el período: $' . number_format($kpis['valor_estimado'],2) . '. Revisar manejo y conservación.';
if ($kpis['pct_merma'] > 5)     $notas[] = '% de merma sobre consumo total: ' . number_format($kpis['pct_merma'],2) . '%. Excede el umbral recomendado del 5%.';
if (count($alertas) > 0)        $notas[] = count($alertas) . ' producto(s) en stock crítico. Coordinar reabastecimiento.';
if ($kpis['top_ingrediente'] !== '—') $notas[] = 'Ingrediente más afectado: ' . $kpis['top_ingrediente'] . '. Investigar causa raíz.';
$notas[] = 'Rango técnico de conservación para cadena fría: 0°C a 4°C.';
?>
<div class="merma-notas">
  <h4>Notas Críticas</h4>
  <ul>
    <?php foreach ($notas as $n): ?>
      <li><?= htmlspecialchars($n) ?></li>
    <?php endforeach; ?>
  </ul>
</div>

<!-- Modal registrar merma -->
<div class="rst-modal-backdrop" id="modalRegMerma" style="position:fixed;inset:0;background:rgba(0,0,0,.5);align-items:center;justify-content:center;z-index:1000">
  <div style="background:#fff;border-radius:12px;width:480px;max-width:95vw;padding:22px;max-height:90vh;overflow:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="margin:0;font-size:1.1rem">Registrar nueva merma</h3>
      <button onclick="document.getElementById('modalRegMerma').classList.remove('open')" style="background:none;border:none;font-size:1.4rem;cursor:pointer;color:#9CA3AF">&times;</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>rest-mermas/registrar" id="formRegMerma" onsubmit="return convertirUnidadMerma()">
      <input type="hidden" name="cantidad" id="hiddenCantidad">
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:#374151">Ingrediente</label>
        <select name="ingrediente_id" id="selIngrediente" required
                style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
          <option value="">— Selecciona ingrediente —</option>
          <?php foreach ($ingredientes as $i): ?>
            <option value="<?= (int)$i['id'] ?>"
                    data-unidad="<?= htmlspecialchars($i['unidad_principal']) ?>">
              <?= htmlspecialchars($i['nombre']) ?>
              (stock: <?= number_format((float)$i['stock'],3) ?> <?= htmlspecialchars($i['unidad_principal']) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:#374151">Cantidad</label>
        <div style="display:flex;gap:8px">
          <input type="number" id="inputCantidad" step="0.001" min="0.001" required
                 placeholder="0.000"
                 style="flex:1;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
          <select id="selUnidad"
                  style="flex:0 0 86px;padding:9px 8px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
            <option value="g">g</option>
            <option value="kg">kg</option>
            <option value="ml">ml</option>
            <option value="l">l</option>
            <option value="paquete">Paquete</option>
            <option value="botella">Botella</option>
            <option value="pieza">Pieza</option>
          </select>
        </div>
        <div id="conversionHint" style="font-size:.75rem;color:#6B7280;margin-top:5px"></div>
      </div>
      <div style="margin-bottom:18px">
        <label style="display:block;font-size:.82rem;font-weight:600;margin-bottom:6px;color:#374151">Motivo</label>
        <select name="motivo"
                style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
          <option value="">— Selecciona motivo —</option>
          <option>Caducidad</option>
          <option>Error de preparación</option>
          <option>Accidente de cocina</option>
          <option>Error de entrega</option>
          <option>Accidente en entrega</option>
          <option>Rechazo por calidad</option>
          <option>Cortesía</option>
          <option>Defecto</option>
          <option>Inventario</option>
        </select>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px">
        <button type="button" class="btn-merma-outline" onclick="document.getElementById('modalRegMerma').classList.remove('open')">Cancelar</button>
        <button type="submit" class="btn-merma-primary">Registrar merma</button>
      </div>
    </form>
  </div>
</div>

<!-- Libs PDF -->
<script src="<?= BASE_URL ?>public/js/lib/jspdf.umd.min.js"></script>
<script src="<?= BASE_URL ?>public/js/lib/jspdf.plugin.autotable.min.js"></script>

<script>
(function () {
  const PALETTE = ['#C8102E','#1f2937','#0EA5E9','#10B981','#F59E0B','#8B5CF6','#EC4899','#6366F1','#14B8A6','#EF4444'];

  const topMermas   = <?= json_encode(array_map(fn($r)=>['nombre'=>$r['nombre'],'total'=>(float)$r['total'],'valor'=>(float)$r['valor'],'unidad'=>$r['unidad_principal']], $topMermas)) ?>;
  const menosMermas = <?= json_encode(array_map(fn($r)=>['nombre'=>$r['nombre'],'total'=>(float)$r['total'],'valor'=>(float)$r['valor'],'unidad'=>$r['unidad_principal']], $menosMermas)) ?>;
  const motivos     = <?= json_encode(array_map(fn($r)=>['motivo'=>$r['motivo'],'total'=>(float)$r['total']], $porMotivo)) ?>;
  const tendencia   = <?= json_encode(array_map(fn($r)=>['dia'=>$r['dia'],'total'=>(float)$r['total']], $tendencia)) ?>;

  const charts = {};

  function buildBar(canvasId, data, color) {
    const ctx = document.getElementById(canvasId);
    if (!ctx) return null;
    return new Chart(ctx, {
      type: 'bar',
      data: {
        labels: data.map(d=>d.nombre),
        datasets: [{ label:'Cantidad', data: data.map(d=>d.total), backgroundColor: color, borderRadius:4 }]
      },
      options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display:false } },
        scales: { x: { beginAtZero:true } },
        animation: { duration: 600 }
      }
    });
  }

  charts.topMermas   = buildBar('chartTopMermas', topMermas, '#C8102E');
  charts.menosMermas = buildBar('chartMenosMermas', menosMermas, '#10B981');

  // Doughnut motivos
  const ctxM = document.getElementById('chartMotivos');
  if (ctxM) {
    charts.motivos = new Chart(ctxM, {
      type: 'doughnut',
      data: {
        labels: motivos.map(m=>m.motivo),
        datasets: [{
          data: motivos.map(m=>m.total),
          backgroundColor: motivos.map((_,i)=>PALETTE[i % PALETTE.length]),
          borderWidth: 2, borderColor: '#fff'
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position:'bottom', labels: { boxWidth: 12, font: { size: 11 } } } },
        animation: { duration: 600 }
      }
    });
  }

  // Línea tendencia
  const ctxT = document.getElementById('chartTendencia');
  if (ctxT) {
    charts.tendencia = new Chart(ctxT, {
      type: 'line',
      data: {
        labels: tendencia.map(t=>t.dia),
        datasets: [{
          label:'Mermas',
          data: tendencia.map(t=>t.total),
          borderColor:'#C8102E', backgroundColor:'rgba(200,16,46,.15)',
          fill:true, tension:0.3, pointRadius:3
        }]
      },
      options: {
        responsive:true, maintainAspectRatio:false,
        plugins: { legend: { display:false } },
        scales: { y: { beginAtZero:true } },
        animation: { duration: 600 }
      }
    });
  }

  // ── Datos para el PDF ──────────────────────────────────────────
  const REPORT_DATA = {
    restaurante: <?= json_encode([
      'id'     => $restaurante['id'] ?? 0,
      'nombre' => $restaurante['nombre'] ?? 'Mi Restaurante',
      'logo'   => $restaurante['logo'] ?? '',
      'slug'   => $restaurante['slug'] ?? '',
    ]) ?>,
    desde: <?= json_encode($desde) ?>,
    hasta: <?= json_encode($hasta) ?>,
    kpis:  <?= json_encode($kpis) ?>,
    notas: <?= json_encode($notas) ?>,
    detalle: <?= json_encode(array_map(fn($d)=>[
      'fecha'        => date('Y-m-d H:i', strtotime($d['created_at'])),
      'ingrediente'  => $d['ingrediente_nombre'],
      'cantidad'     => (float)$d['cantidad'],
      'unidad'       => $d['unidad_principal'],
      'valor'        => round((float)$d['cantidad'] * (float)$d['costo_unitario'], 2),
      'motivo'       => $d['motivo'] ?? '',
      'usuario'      => $d['usuario_nombre'] ?? '',
    ], $detalle)) ?>,
    topRotacion: <?= json_encode(array_map(fn($r)=>['nombre'=>$r['nombre'],'total'=>(float)$r['total']], $topRotacion)) ?>,
    baseUrl: <?= json_encode(BASE_URL) ?>,
  };

  // Cargar logo como dataURL (puede fallar si no hay logo)
  function loadLogoDataURL(path) {
    return new Promise(resolve => {
      if (!path) return resolve(null);
      const url = REPORT_DATA.baseUrl + path;
      const img = new Image();
      img.crossOrigin = 'anonymous';
      img.onload = () => {
        try {
          const c = document.createElement('canvas');
          c.width = img.naturalWidth; c.height = img.naturalHeight;
          c.getContext('2d').drawImage(img, 0, 0);
          resolve({ data: c.toDataURL('image/png'), w: img.naturalWidth, h: img.naturalHeight });
        } catch (e) { resolve(null); }
      };
      img.onerror = () => resolve(null);
      img.src = url;
    });
  }

  function folioReporte(restId) {
    const d = new Date();
    const pad = n => String(n).padStart(2,'0');
    const ts = d.getFullYear() + pad(d.getMonth()+1) + pad(d.getDate())
             + '-' + pad(d.getHours()) + pad(d.getMinutes()) + pad(d.getSeconds());
    const rand = Math.random().toString(16).slice(2,6).toUpperCase();
    return 'CH-MERMA-' + ts + '-' + String(restId).padStart(3,'0') + '-' + rand;
  }

  function fmtMoney(n) { return '$' + Number(n||0).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2}); }
  function fmtNum(n, d=3) { return Number(n||0).toLocaleString('es-MX',{minimumFractionDigits:d,maximumFractionDigits:d}); }

  window.generarReporteMermasPDF = async function () {
    const sec = {
      logo:     document.getElementById('chkLogo')?.checked    ?? true,
      kpis:     document.getElementById('chkKpis')?.checked    ?? true,
      graficas: document.getElementById('chkGraficas')?.checked ?? true,
      tabla:    document.getElementById('chkTabla')?.checked    ?? true,
      notas:    document.getElementById('chkNotas')?.checked    ?? true,
    };
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ unit:'pt', format:'a4' });
    const W = doc.internal.pageSize.getWidth();
    const H = doc.internal.pageSize.getHeight();
    const MARGIN = 32;
    const folio  = folioReporte(REPORT_DATA.restaurante.id);
    const fechaTxt = new Date().toLocaleDateString('es-MX',{year:'numeric',month:'long',day:'numeric'});

    // ── Header oscuro ──
    doc.setFillColor(31,41,55);
    doc.rect(0,0,W,80,'F');

    const logo = sec.logo ? await loadLogoDataURL(REPORT_DATA.restaurante.logo) : null;
    let textX = MARGIN;
    if (logo) {
      const targetH = 44;
      const ratio   = logo.w / logo.h;
      const targetW = targetH * ratio;
      try { doc.addImage(logo.data, 'PNG', MARGIN, 18, targetW, targetH); textX = MARGIN + targetW + 14; } catch (e) {}
    }

    doc.setTextColor(255,255,255);
    doc.setFont('helvetica','bold');
    doc.setFontSize(18);
    doc.text('Reporte de Mermas', textX, 44);

    doc.setFontSize(7.5);
    doc.setFont('helvetica','normal');
    doc.text('Fecha: ' + fechaTxt, W - MARGIN, 28, { align:'right' });
    doc.text('Período: ' + REPORT_DATA.desde + ' a ' + REPORT_DATA.hasta, W - MARGIN, 41, { align:'right' });
    doc.text('ID: #' + folio, W - MARGIN, 54, { align:'right' });

    let y = 100;

    // ── KPI cards (4x2) ──
    if (sec.kpis) {
    doc.setTextColor(107,114,128);
    doc.setFont('helvetica','bold');
    doc.setFontSize(9);
    doc.text('INDICADORES DEL PERÍODO', MARGIN, y);
    y += 8;

    const kpiCards = [
      { lbl:'TOTAL MERMAS',        val: fmtNum(REPORT_DATA.kpis.cantidad_total),         sub:'unidades', color:[200,16,46] },
      { lbl:'EVENTOS DEL PERÍODO', val: String(REPORT_DATA.kpis.eventos),                 sub:'registros', color:[31,41,55] },
      { lbl:'INGREDIENTES',        val: String(REPORT_DATA.kpis.ingredientes_afectados), sub:'afectados', color:[31,41,55] },
      { lbl:'VALOR ESTIMADO',      val: fmtMoney(REPORT_DATA.kpis.valor_estimado),       sub:'pérdida $', color:[239,68,68] },
      { lbl:'% MERMA vs CONSUMO',  val: Number(REPORT_DATA.kpis.pct_merma).toFixed(2)+'%', sub:'meta <5%', color:[31,41,55] },
      { lbl:'PROMEDIO DIARIO',     val: Number(REPORT_DATA.kpis.promedio_diario||0).toFixed(3), sub:'unidades/día', color:[245,158,11] },
      { lbl:'MOTIVO PRINCIPAL',    val: REPORT_DATA.kpis.top_motivo || '—',              sub:'más frecuente', color:[31,41,55], small:true },
      { lbl:'INGREDIENTE TOP',     val: REPORT_DATA.kpis.top_ingrediente || '—',         sub:'más afectado', color:[200,16,46], small:true },
    ];

    const colsKPI = 4;
    const gapKPI  = 10;
    const cardW   = (W - MARGIN*2 - gapKPI*(colsKPI-1)) / colsKPI;
    const cardH   = 58;

    kpiCards.forEach((c, i) => {
      const col = i % colsKPI;
      const row = Math.floor(i / colsKPI);
      const x = MARGIN + col * (cardW + gapKPI);
      const cy = y + 6 + row * (cardH + gapKPI);

      doc.setDrawColor(229,231,235);
      doc.setFillColor(255,255,255);
      doc.roundedRect(x, cy, cardW, cardH, 5, 5, 'FD');

      doc.setTextColor(107,114,128);
      doc.setFont('helvetica','bold');
      doc.setFontSize(7);
      doc.text(c.lbl, x + 10, cy + 14);

      doc.setTextColor(c.color[0], c.color[1], c.color[2]);
      doc.setFont('helvetica','bold');
      doc.setFontSize(c.small ? 11 : 16);
      const valStr = doc.splitTextToSize(String(c.val), cardW - 20)[0] || '';
      doc.text(valStr, x + 10, cy + (c.small ? 32 : 36));

      doc.setTextColor(156,163,175);
      doc.setFont('helvetica','normal');
      doc.setFontSize(7);
      doc.text(c.sub, x + 10, cy + 50);
    });

    y += 6 + 2 * (cardH + gapKPI) + 10;
    } // end sec.kpis

    // ── Sección gráficas ──
    if (sec.graficas) {
    doc.setTextColor(107,114,128);
    doc.setFont('helvetica','bold');
    doc.setFontSize(9);
    doc.text('GRÁFICAS', MARGIN, y);
    y += 8;

    const chartW = (W - MARGIN*2 - 12) / 2;
    const chartH = 150;
    const chartImgs = [
      { c: charts.topMermas,   ttl: 'Top productos con más mermas' },
      { c: charts.motivos,     ttl: 'Distribución por motivo' },
      { c: charts.tendencia,   ttl: 'Tendencia diaria' },
      { c: charts.menosMermas, ttl: 'Top productos con menos mermas' },
    ];

    chartImgs.forEach((ci, i) => {
      const col = i % 2;
      const row = Math.floor(i / 2);
      const x = MARGIN + col * (chartW + 12);
      const cy = y + row * (chartH + 24);

      doc.setDrawColor(229,231,235);
      doc.setFillColor(255,255,255);
      doc.roundedRect(x, cy, chartW, chartH + 18, 5, 5, 'FD');

      doc.setTextColor(31,41,55);
      doc.setFont('helvetica','bold');
      doc.setFontSize(8);
      doc.text(ci.ttl, x + 8, cy + 12);

      if (ci.c) {
        try {
          const img = ci.c.toBase64Image('image/png', 1);
          doc.addImage(img, 'PNG', x + 6, cy + 16, chartW - 12, chartH);
        } catch (e) {}
      } else {
        doc.setTextColor(156,163,175);
        doc.setFont('helvetica','normal');
        doc.setFontSize(8);
        doc.text('Sin datos', x + chartW/2, cy + chartH/2, { align:'center' });
      }
    });

    y += 2 * (chartH + 24) + 6;
    } // end sec.graficas

    // ── Tabla detalle ──
    let yAfter = y;
    if (sec.tabla) {
    if (y > H - 140) { doc.addPage(); y = 40; }

    doc.setTextColor(107,114,128);
    doc.setFont('helvetica','bold');
    doc.setFontSize(9);
    doc.text('DETALLE DE MERMAS', MARGIN, y);
    y += 6;

    const body = REPORT_DATA.detalle.map(d => [
      d.fecha,
      d.ingrediente,
      fmtNum(d.cantidad) + ' ' + d.unidad,
      fmtMoney(d.valor),
      d.motivo || '—',
      d.usuario || '—',
    ]);

    doc.autoTable({
      head: [['Fecha','Ingrediente','Cantidad','Valor','Motivo','Usuario']],
      body: body.length ? body : [['—','Sin mermas en el período','—','—','—','—']],
      startY: y + 4,
      margin: { left: MARGIN, right: MARGIN },
      theme: 'striped',
      styles: { fontSize: 8, cellPadding: 4 },
      headStyles: { fillColor: [31,41,55], textColor: [255,255,255], fontStyle: 'bold' },
      alternateRowStyles: { fillColor: [249,250,251] },
    });

    yAfter = doc.lastAutoTable ? doc.lastAutoTable.finalY + 18 : y + 18;
    if (yAfter > H - 120) { doc.addPage(); yAfter = 40; }
    } // end sec.tabla

    // ── Notas críticas ──
    if (sec.notas) {
    let yNotas = yAfter;
    if (yNotas > H - 120) { doc.addPage(); yNotas = 40; }
    doc.setFillColor(254,243,199);
    doc.setDrawColor(245,158,11);
    const notasH = 22 + REPORT_DATA.notas.length * 12;
    doc.roundedRect(MARGIN, yNotas, W - MARGIN*2, notasH, 5, 5, 'FD');
    doc.setTextColor(146,64,14);
    doc.setFont('helvetica','bold');
    doc.setFontSize(9);
    doc.text('NOTAS CRÍTICAS', MARGIN + 10, yNotas + 14);
    doc.setFont('helvetica','normal');
    doc.setFontSize(8);
    doc.setTextColor(124,45,18);
    REPORT_DATA.notas.forEach((n, i) => {
      const lines = doc.splitTextToSize('• ' + n, W - MARGIN*2 - 20);
      doc.text(lines, MARGIN + 14, yNotas + 28 + i * 12);
    });
    } // end sec.notas

    // ── Footer en todas las páginas ──
    const total = doc.internal.getNumberOfPages();
    for (let p = 1; p <= total; p++) {
      doc.setPage(p);
      doc.setDrawColor(229,231,235);
      doc.line(MARGIN, H - 28, W - MARGIN, H - 28);
      doc.setFontSize(7);
      doc.setTextColor(156,163,175);
      doc.setFont('helvetica','normal');
      doc.text('Generado automáticamente por el módulo de Mermas de CarniHub', W/2, H - 16, { align:'center' });
      doc.text('Página ' + p + ' de ' + total, W - MARGIN, H - 16, { align:'right' });
      doc.text('Folio: ' + folio, MARGIN, H - 16);
    }

    const slug = (REPORT_DATA.restaurante.slug || 'restaurante').replace(/[^a-z0-9-]/gi,'-').toLowerCase();
    const fname = 'mermas-' + slug + '-' + folio.split('-').slice(2,4).join('-') + '.pdf';
    doc.save(fname);
  };
  // ── Conversión de unidades en modal merma ──
  function convertirFactor(desde, hasta) {
    desde = desde.toLowerCase(); hasta = hasta.toLowerCase();
    if (desde === hasta) return 1;
    if (desde === 'g'  && hasta === 'kg') return 1/1000;
    if (desde === 'kg' && hasta === 'g')  return 1000;
    if (desde === 'ml' && hasta === 'l')  return 1/1000;
    if (desde === 'l'  && hasta === 'ml') return 1000;
    return 1; // unidades incompatibles → 1:1
  }

  function actualizarHint() {
    const sel  = document.getElementById('selIngrediente');
    const opt  = sel.options[sel.selectedIndex];
    const base = (opt && opt.dataset.unidad) ? opt.dataset.unidad : '';
    const cant = parseFloat(document.getElementById('inputCantidad').value) || 0;
    const uni  = document.getElementById('selUnidad').value;
    const hint = document.getElementById('conversionHint');
    if (!base || cant <= 0) { hint.textContent = ''; return; }
    const factor   = convertirFactor(uni, base);
    const resultado = cant * factor;
    hint.textContent = cant + ' ' + uni + ' = ' + resultado.toFixed(4) + ' ' + base + ' (unidad del ingrediente)';
  }

  window.convertirUnidadMerma = function () {
    const sel  = document.getElementById('selIngrediente');
    const opt  = sel.options[sel.selectedIndex];
    const base = (opt && opt.dataset.unidad) ? opt.dataset.unidad : '';
    const cant = parseFloat(document.getElementById('inputCantidad').value) || 0;
    const uni  = document.getElementById('selUnidad').value;
    const factor = base ? convertirFactor(uni, base) : 1;
    document.getElementById('hiddenCantidad').value = (cant * factor).toFixed(6);
    return true;
  };

  document.getElementById('selIngrediente').addEventListener('change', actualizarHint);
  document.getElementById('inputCantidad').addEventListener('input', actualizarHint);
  document.getElementById('selUnidad').addEventListener('change', actualizarHint);

  window.aplicarCustom = function () {
    const d = document.getElementById('customDesde').value;
    const h = document.getElementById('customHasta').value;
    if (d && h) window.location.href = '<?= BASE_URL ?>rest-mermas/index?desde=' + d + '&hasta=' + h;
  };

})();
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
