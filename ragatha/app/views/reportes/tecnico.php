<?php
$mostrarLogo     = in_array('logo', $filtros['mostrar'] ?? [], true);
$mostrarKpis     = in_array('kpis', $filtros['mostrar'] ?? [], true);
$mostrarGraficas = in_array('graficas', $filtros['mostrar'] ?? [], true);
$mostrarTabla    = in_array('tabla', $filtros['mostrar'] ?? [], true);
$mostrarNotas    = in_array('notas', $filtros['mostrar'] ?? [], true);
$reportIdSanitized = isset($reportId[0]) && $reportId[0] === '#' ? substr($reportId, 1) : $reportId;
$graficas = $graficas ?? [];

// Período actual y presets
$periodoActual = $filtros['periodo'] ?? '30d';
$labelPeriodoActual = $filtros['label_periodo'] ?? 'Últimos 30 días';
$presetsPeriodo = [
    'hoy' => 'Hoy',
    '7d'  => '7 días',
    '30d' => '30 días',
    '90d' => '90 días',
    'año' => 'Este año',
];
?>
<div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:14px">
  <div>
    <h2 style="font-size:1.2rem;font-weight:800;color:#111827;margin:0">Módulo de Reportes</h2>
    <p style="color:#6B7280;font-size:.85rem;margin-top:6px">Selecciona el período y las secciones a incluir en el reporte y en el PDF.</p>
  </div>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <button type="button" onclick="descargarReporteXls()" style="background:#fff;border:1px solid #D1D5DB;color:#111827;font-weight:700;border-radius:8px;padding:8px 12px;font-size:.8rem">Descargar XLS</button>
    <button type="button" onclick="descargarReportePdf()" style="background:var(--color-primary,#C8102E);border:1px solid var(--color-primary,#C8102E);color:#fff;font-weight:700;border-radius:8px;padding:8px 12px;font-size:.8rem">Descargar PDF</button>
  </div>
</div>

<!-- Selector de período tipo botones (Hoy / 7 días / 30 días / 90 días / Este año / Personalizado) -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
  <div>
    <div style="font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px">Período de análisis</div>
    <div style="font-size:.95rem;font-weight:700;color:#111827"><?= htmlspecialchars($labelPeriodoActual) ?></div>
  </div>
  <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
    <?php foreach ($presetsPeriodo as $key => $lab): ?>
      <?php
        $href = '?periodo=' . urlencode($key);
        // Conservar selecciones de "mostrar" como hidden via query
        foreach (($filtros['mostrar'] ?? []) as $m) {
            $href .= '&mostrar[]=' . urlencode($m);
        }
        $isActive = ($periodoActual === $key);
      ?>
      <a href="<?= htmlspecialchars($href) ?>"
         style="padding:7px 14px;border-radius:8px;font-size:.78rem;font-weight:700;text-decoration:none;border:1px solid <?= $isActive ? 'var(--color-primary,#C8102E)' : '#D1D5DB' ?>;
                background:<?= $isActive ? 'var(--color-primary,#C8102E)' : '#fff' ?>;color:<?= $isActive ? '#fff' : '#374151' ?>">
        <?= htmlspecialchars($lab) ?>
      </a>
    <?php endforeach; ?>
    <button type="button"
            onclick="document.getElementById('rangoPersonalizado').style.display=document.getElementById('rangoPersonalizado').style.display==='none'?'flex':'none'"
            style="padding:7px 14px;border-radius:8px;font-size:.78rem;font-weight:700;cursor:pointer;border:1px solid <?= $periodoActual === 'custom' ? 'var(--color-primary,#C8102E)' : '#D1D5DB' ?>;
                   background:<?= $periodoActual === 'custom' ? 'var(--color-primary,#C8102E)' : '#fff' ?>;color:<?= $periodoActual === 'custom' ? '#fff' : '#374151' ?>">
      Personalizado
    </button>
  </div>
</div>

<!-- Picker fechas + checkboxes de secciones -->
<form method="GET" id="rangoPersonalizado" style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:16px;display:<?= $periodoActual === 'custom' ? 'grid' : 'none' ?>;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end">
  <input type="hidden" name="periodo" value="custom">
  <div>
    <label style="display:block;font-size:.72rem;font-weight:700;color:#6B7280;margin-bottom:4px">Desde</label>
    <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtros['fecha_desde']) ?>" style="width:100%;border:1px solid #D1D5DB;border-radius:8px;padding:8px 10px;font-size:.85rem">
  </div>
  <div>
    <label style="display:block;font-size:.72rem;font-weight:700;color:#6B7280;margin-bottom:4px">Hasta</label>
    <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtros['fecha_hasta']) ?>" style="width:100%;border:1px solid #D1D5DB;border-radius:8px;padding:8px 10px;font-size:.85rem">
  </div>
  <div style="grid-column:1/-1;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
    <span style="font-size:.72rem;font-weight:700;color:#6B7280;text-transform:uppercase">Mostrar / incluir en PDF:</span>
    <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="logo"     <?= $mostrarLogo ? 'checked' : '' ?>> Logo</label>
    <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="kpis"     <?= $mostrarKpis ? 'checked' : '' ?>> KPIs</label>
    <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="graficas" <?= $mostrarGraficas ? 'checked' : '' ?>> Gráficas</label>
    <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="tabla"    <?= $mostrarTabla ? 'checked' : '' ?>> Tabla</label>
    <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="notas"    <?= $mostrarNotas ? 'checked' : '' ?>> Notas</label>
  </div>
  <div style="display:flex;justify-content:flex-end">
    <button type="submit" style="background:var(--color-primary,#C8102E);color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:.8rem;font-weight:700">Aplicar</button>
  </div>
</form>

<!-- Form auxiliar para alternar secciones cuando el período NO es personalizado -->
<?php if ($periodoActual !== 'custom'): ?>
<form method="GET" style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:16px;display:flex;gap:14px;align-items:center;flex-wrap:wrap">
  <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodoActual) ?>">
  <span style="font-size:.72rem;font-weight:700;color:#6B7280;text-transform:uppercase">Mostrar / incluir en PDF:</span>
  <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="logo"     <?= $mostrarLogo ? 'checked' : '' ?>> Logo</label>
  <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="kpis"     <?= $mostrarKpis ? 'checked' : '' ?>> KPIs</label>
  <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="graficas" <?= $mostrarGraficas ? 'checked' : '' ?>> Gráficas</label>
  <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="tabla"    <?= $mostrarTabla ? 'checked' : '' ?>> Tabla</label>
  <label style="font-size:.82rem;color:#374151"><input type="checkbox" name="mostrar[]" value="notas"    <?= $mostrarNotas ? 'checked' : '' ?>> Notas</label>
  <button type="submit" style="margin-left:auto;background:#111827;color:#fff;border:none;border-radius:8px;padding:8px 14px;font-size:.78rem;font-weight:700">Aplicar secciones</button>
</form>
<?php endif; ?>

<div id="reporteTecnico" style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:20px;display:grid;gap:14px">
  <div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px;display:grid;grid-template-columns:auto 1fr auto;gap:14px;align-items:center">
    <?php if ($mostrarLogo): ?>
    <div style="width:62px;height:62px;border:1px solid #E5E7EB;border-radius:10px;display:flex;align-items:center;justify-content:center;background:#F9FAFB">
      <img id="logoReporte" src="<?= htmlspecialchars($logoUrl) ?>" alt="CarniHub" crossorigin="anonymous" style="max-width:52px;max-height:52px;object-fit:contain">
    </div>
    <?php else: ?>
    <div></div>
    <?php endif; ?>
    <div>
      <div style="font-size:1.1rem;font-weight:900;color:#111827;letter-spacing:.02em">CarniHub</div>
      <div style="font-size:.93rem;font-weight:700;color:#374151"><?= htmlspecialchars($tituloReporte) ?></div>
      <div style="font-size:.75rem;color:#6B7280">Fecha: <?= htmlspecialchars($fechaReporte) ?></div>
    </div>
    <div style="text-align:right">
      <div style="font-size:.72rem;color:#6B7280">ID de reporte</div>
      <div style="font-size:.95rem;font-weight:800;color:#111827" id="reportId"><?= htmlspecialchars($reportId) ?></div>
    </div>
  </div>

  <?php if ($mostrarKpis): ?>
  <div id="kpiPanel" style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px">
    <?php foreach ($kpis as $kpi): ?>
    <div style="border:1px solid #E5E7EB;border-radius:10px;padding:12px;background:linear-gradient(180deg,#fff,#F9FAFB)">
      <div style="font-size:.72rem;font-weight:700;color:#6B7280;text-transform:uppercase"><?= htmlspecialchars($kpi['label']) ?></div>
      <div style="font-size:1.3rem;font-weight:900;color:#111827;line-height:1.2;margin-top:3px"><?= htmlspecialchars($kpi['valor']) ?></div>
      <div style="font-size:.72rem;color:#6B7280;margin-top:3px"><?= htmlspecialchars($kpi['hint']) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($mostrarGraficas && !empty($graficas)): ?>
  <div id="graficasPanel" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px">
    <?php foreach ($graficas as $idx => $g): ?>
    <div style="border:1px solid #E5E7EB;border-radius:10px;padding:12px;background:#fff">
      <div style="font-size:.78rem;font-weight:800;color:#374151;margin-bottom:8px;text-transform:uppercase;letter-spacing:.04em"><?= htmlspecialchars($g['titulo']) ?></div>
      <div style="position:relative;height:240px"><canvas id="grafica<?= $idx ?>"></canvas></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($mostrarTabla): ?>
  <div>
    <div style="font-size:.78rem;font-weight:800;color:#374151;margin-bottom:6px;letter-spacing:.05em">CUERPO DEL REPORTE</div>
    <div style="overflow:auto;border:1px solid #E5E7EB;border-radius:10px">
      <table id="tablaReporte" style="width:100%;border-collapse:collapse;min-width:720px">
        <thead>
          <tr style="background:#F3F4F6">
            <?php foreach ($columnas as $c): ?>
            <th style="padding:10px;border-bottom:1px solid #E5E7EB;font-size:.72rem;text-align:left;color:#374151;text-transform:uppercase;letter-spacing:.04em"><?= htmlspecialchars($c) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filas)): ?>
          <tr><td colspan="<?= count($columnas) ?>" style="padding:14px;color:#6B7280;font-size:.84rem">No hay registros para el período seleccionado.</td></tr>
          <?php else: ?>
            <?php foreach ($filas as $fila): ?>
            <tr>
              <?php foreach ($fila as $cell): ?>
              <td style="padding:9px 10px;border-bottom:1px solid #F3F4F6;font-size:.82rem;color:#111827"><?= htmlspecialchars((string)$cell) ?></td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($mostrarNotas): ?>
  <div id="notasReporte" style="border:1px solid #E5E7EB;border-radius:10px;padding:12px;background:#FCFCFD">
    <div style="font-size:.78rem;font-weight:800;color:#374151;margin-bottom:6px;letter-spacing:.05em">NOTAS CRÍTICAS</div>
    <ul style="margin:0;padding-left:18px;color:#374151;font-size:.82rem;display:grid;gap:4px">
      <?php foreach ($notas as $nota): ?>
      <li><?= htmlspecialchars($nota) ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
  <?php endif; ?>

  <div style="font-size:.74rem;color:#6B7280;border-top:1px dashed #D1D5DB;padding-top:8px">
    Generado automáticamente por el módulo de Reportes SaaS de CarniHub.
  </div>
</div>

<script src="<?= BASE_URL ?>public/js/lib/chart.umd.min.js"></script>
<script src="<?= BASE_URL ?>public/js/lib/jspdf.umd.min.js"></script>
<script src="<?= BASE_URL ?>public/js/lib/jspdf.plugin.autotable.min.js"></script>

<script>
const REPORTE_DATA = {
  titulo: <?= json_encode($tituloReporte) ?>,
  fecha: <?= json_encode($fechaReporte) ?>,
  reportId: <?= json_encode($reportId) ?>,
  reportIdSafe: <?= json_encode($reportIdSanitized) ?>,
  logoUrl: <?= json_encode($logoUrl) ?>,
  kpis: <?= json_encode($kpis, JSON_UNESCAPED_UNICODE) ?>,
  columnas: <?= json_encode($columnas, JSON_UNESCAPED_UNICODE) ?>,
  filas: <?= json_encode($filas, JSON_UNESCAPED_UNICODE) ?>,
  notas: <?= json_encode($notas, JSON_UNESCAPED_UNICODE) ?>,
  graficas: <?= json_encode($graficas, JSON_UNESCAPED_UNICODE) ?>,
  mostrar: {
    logo: <?= $mostrarLogo ? 'true' : 'false' ?>,
    kpis: <?= $mostrarKpis ? 'true' : 'false' ?>,
    graficas: <?= $mostrarGraficas ? 'true' : 'false' ?>,
    tabla: <?= $mostrarTabla ? 'true' : 'false' ?>,
    notas: <?= $mostrarNotas ? 'true' : 'false' ?>
  }
};

const PALETTE = ['#C8102E','#1f2937','#0EA5E9','#10B981','#F59E0B','#8B5CF6','#EC4899','#6366F1'];
const chartInstances = {};

function renderCharts() {
  if (typeof Chart === 'undefined' || !REPORTE_DATA.mostrar.graficas) return;
  REPORTE_DATA.graficas.forEach((g, idx) => {
    const el = document.getElementById('grafica' + idx);
    if (!el) return;
    const isPie = g.tipo === 'doughnut' || g.tipo === 'pie';
    const isGauge = g.tipo === 'gauge';
    const isBubble = g.tipo === 'bubble';
    const isBarH = g.tipo === 'barH';
    let cfg;

    if (isGauge) {
      const val = Math.max(0, Math.min(100, Number(g.data[0]) || 0));
      const color = val >= 95 ? '#10B981' : val >= 80 ? '#F59E0B' : '#EF4444';
      cfg = {
        type: 'doughnut',
        data: {
          labels: ['Cumplimiento', 'Restante'],
          datasets: [{
            data: [val, 100 - val],
            backgroundColor: [color, '#E5E7EB'],
            borderWidth: 0,
            circumference: 180,
            rotation: 270,
          }]
        },
        options: {
          responsive: true, maintainAspectRatio: false, animation: false,
          cutout: '70%',
          plugins: { legend: { display: false }, tooltip: { enabled: false } }
        },
        plugins: [{
          id: 'gaugeText',
          afterDraw(chart) {
            const { ctx, chartArea: { width, height, top } } = chart;
            ctx.save();
            ctx.fillStyle = '#111827';
            ctx.font = 'bold 28px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(val.toFixed(1) + '%', width / 2, top + height * 0.78);
            ctx.font = '11px Inter, sans-serif';
            ctx.fillStyle = '#6B7280';
            ctx.fillText('Meta 98.5%', width / 2, top + height * 0.96);
            ctx.restore();
          }
        }]
      };
    } else if (isBubble) {
      cfg = {
        type: 'bubble',
        data: {
          datasets: g.data.map((d, i) => ({
            label: g.labels[i] || ('Cliente ' + (i + 1)),
            data: [d],
            backgroundColor: PALETTE[i % PALETTE.length] + 'AA',
            borderColor: PALETTE[i % PALETTE.length],
            borderWidth: 1,
          }))
        },
        options: {
          responsive: true, maintainAspectRatio: false, animation: false,
          plugins: {
            legend: { display: g.data.length <= 8, position: 'bottom', labels: { boxWidth: 10, font: { size: 9 } } },
            tooltip: { callbacks: { label: (c) => c.dataset.label + ': ' + c.raw.x + ' pedidos / $' + c.raw.y.toLocaleString() } }
          },
          scales: {
            x: { title: { display: true, text: 'N° de pedidos' }, beginAtZero: true },
            y: { title: { display: true, text: 'Gasto ($)' }, beginAtZero: true }
          }
        }
      };
    } else {
      // Soporte para múltiples datasets (g.datasets) o un solo dataset (g.data/g.label)
      let datasets;
      if (Array.isArray(g.datasets) && g.datasets.length) {
        datasets = g.datasets.map((ds, di) => ({
          label: ds.label || ('Serie ' + (di + 1)),
          data: ds.data || [],
          backgroundColor: ds.color || PALETTE[di % PALETTE.length],
          borderColor: ds.color || PALETTE[di % PALETTE.length],
          borderWidth: 2,
          tension: 0.3,
          fill: false,
        }));
      } else {
        datasets = [{
          label: g.label,
          data: g.data,
          backgroundColor: isPie
            ? PALETTE.slice(0, g.labels.length)
            : (isBarH ? PALETTE.slice(0, g.labels.length) : 'rgba(200,16,46,0.15)'),
          borderColor: isPie ? '#fff' : '#C8102E',
          borderWidth: 2,
          tension: 0.3,
          fill: !isPie && !isBarH
        }];
      }

      cfg = {
        type: isBarH ? 'bar' : g.tipo,
        data: { labels: g.labels, datasets },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          animation: false,
          indexAxis: isBarH ? 'y' : 'x',
          plugins: {
            legend: {
              display: isPie || (Array.isArray(g.datasets) && g.datasets.length > 1),
              position: 'bottom'
            }
          },
          scales: isPie ? {} : { [isBarH ? 'x' : 'y']: { beginAtZero: true } }
        }
      };
    }

    chartInstances[idx] = new Chart(el, cfg);
  });
}
document.addEventListener('DOMContentLoaded', renderCharts);

function loadImageAsDataURL(url) {
  return new Promise((resolve) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => {
      try {
        const canvas = document.createElement('canvas');
        canvas.width = img.naturalWidth || 100;
        canvas.height = img.naturalHeight || 100;
        canvas.getContext('2d').drawImage(img, 0, 0);
        resolve(canvas.toDataURL('image/png'));
      } catch (e) { resolve(null); }
    };
    img.onerror = () => resolve(null);
    img.src = url;
  });
}

async function descargarReportePdf() {
  if (!window.jspdf || !window.jspdf.jsPDF) {
    alert('No se pudo cargar el generador PDF. Verifica tu conexión.');
    return;
  }
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF({ orientation: 'portrait', unit: 'pt', format: 'a4' });
  const pageW = doc.internal.pageSize.getWidth();
  const margin = 28;
  let y = margin;

  // Header oscuro
  doc.setFillColor(31, 41, 55);
  doc.rect(margin, y, pageW - margin * 2, 70, 'F');

  let textX = margin + 14;
  if (REPORTE_DATA.mostrar.logo) {
    const logo = await loadImageAsDataURL(REPORTE_DATA.logoUrl);
    if (logo) {
      try { doc.addImage(logo, 'PNG', margin + 12, y + 14, 42, 42); textX = margin + 64; } catch (e) {}
    }
  }
  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold'); doc.setFontSize(20);
  doc.text('CarniHub', textX, y + 28);
  doc.setFont('helvetica', 'normal'); doc.setFontSize(11);
  doc.setTextColor(209, 213, 219);
  doc.text(REPORTE_DATA.titulo, textX, y + 46);
  doc.setFontSize(9);
  doc.text('Fecha: ' + REPORTE_DATA.fecha, pageW - margin - 14, y + 28, { align: 'right' });
  doc.text('ID: ' + REPORTE_DATA.reportId, pageW - margin - 14, y + 44, { align: 'right' });
  y += 84;

  // KPIs
  if (REPORTE_DATA.mostrar.kpis && REPORTE_DATA.kpis.length) {
    doc.setTextColor(107, 114, 128); doc.setFontSize(8); doc.setFont('helvetica', 'bold');
    doc.text('INDICADORES DEL PERÍODO', margin, y); y += 8;
    const perRow = 4;
    const gap = 6;
    const w = (pageW - margin * 2 - gap * (perRow - 1)) / perRow;
    const h = 60;
    REPORTE_DATA.kpis.forEach((k, i) => {
      const col = i % perRow;
      if (col === 0 && i > 0) y += h + gap;
      const x = margin + col * (w + gap);
      doc.setDrawColor(229, 231, 235); doc.setFillColor(250, 250, 250);
      doc.rect(x, y, w, h, 'FD');
      doc.setTextColor(107, 114, 128); doc.setFontSize(7); doc.setFont('helvetica', 'bold');
      doc.text(String(k.label).toUpperCase(), x + 8, y + 14);
      doc.setTextColor(17, 24, 39); doc.setFontSize(15);
      doc.text(String(k.valor), x + 8, y + 34);
      doc.setTextColor(156, 163, 175); doc.setFontSize(7); doc.setFont('helvetica', 'normal');
      doc.text(String(k.hint || ''), x + 8, y + 50);
    });
    y += h + 12;
  }

  // Gráficas
  if (REPORTE_DATA.mostrar.graficas && REPORTE_DATA.graficas.length) {
    doc.setTextColor(107, 114, 128); doc.setFontSize(8); doc.setFont('helvetica', 'bold');
    doc.text('GRÁFICAS', margin, y); y += 10;
    const cols = Math.min(2, REPORTE_DATA.graficas.length);
    const gap = 10;
    const w = (pageW - margin * 2 - gap * (cols - 1)) / cols;
    const h = 160;
    for (let i = 0; i < REPORTE_DATA.graficas.length; i++) {
      const inst = chartInstances[i];
      if (!inst) continue;
      const col = i % cols;
      if (col === 0 && i > 0) y += h + 14;
      if (y + h > doc.internal.pageSize.getHeight() - margin) { doc.addPage(); y = margin; }
      const x = margin + col * (w + gap);
      try {
        const img = inst.toBase64Image('image/png', 1);
        doc.addImage(img, 'PNG', x, y, w, h);
      } catch (e) {}
    }
    y += h + 14;
  }

  // Tabla
  if (REPORTE_DATA.mostrar.tabla && REPORTE_DATA.filas.length) {
    if (y > doc.internal.pageSize.getHeight() - 120) { doc.addPage(); y = margin; }
    doc.autoTable({
      head: [REPORTE_DATA.columnas],
      body: REPORTE_DATA.filas,
      startY: y,
      margin: { left: margin, right: margin },
      styles: { fontSize: 8, cellPadding: 5, textColor: [55, 65, 81] },
      headStyles: { fillColor: [31, 41, 55], textColor: [255, 255, 255], fontStyle: 'bold', fontSize: 8 },
      alternateRowStyles: { fillColor: [249, 250, 251] }
    });
    y = doc.lastAutoTable.finalY + 14;
  }

  // Notas
  if (REPORTE_DATA.mostrar.notas && REPORTE_DATA.notas.length) {
    if (y > doc.internal.pageSize.getHeight() - 100) { doc.addPage(); y = margin; }
    doc.setDrawColor(209, 213, 219); doc.setFillColor(252, 252, 253);
    const boxH = 24 + REPORTE_DATA.notas.length * 14;
    doc.rect(margin, y, pageW - margin * 2, boxH, 'FD');
    doc.setTextColor(55, 65, 81); doc.setFont('helvetica', 'bold'); doc.setFontSize(8);
    doc.text('NOTAS CRÍTICAS', margin + 10, y + 14);
    doc.setFont('helvetica', 'normal'); doc.setFontSize(9);
    REPORTE_DATA.notas.forEach((n, i) => {
      doc.text('• ' + n, margin + 12, y + 28 + i * 14, { maxWidth: pageW - margin * 2 - 24 });
    });
    y += boxH + 8;
  }

  // Footer
  doc.setTextColor(156, 163, 175); doc.setFontSize(7);
  doc.text('Generado automáticamente por el módulo de Reportes SaaS de CarniHub.',
    pageW / 2, doc.internal.pageSize.getHeight() - 14, { align: 'center' });

  doc.save('reporte-' + REPORTE_DATA.reportIdSafe + '.pdf');
}

function descargarReporteXls() {
  const safeId = String(REPORTE_DATA.reportIdSafe).replace(/[^A-Za-z0-9._-]/g, '_');
  if (!document.getElementById('tablaReporte')) {
    alert('Activa la sección de tabla para exportar en XLS.');
    return;
  }
  const headers = Array.from(document.querySelectorAll('#tablaReporte thead th')).map(th => th.innerText.trim());
  const rows = Array.from(document.querySelectorAll('#tablaReporte tbody tr')).map(tr =>
    Array.from(tr.querySelectorAll('td')).map(td => td.innerText.trim())
  );
  const xmlEscape = (v) => String(v)
    .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;').replaceAll("'", '&apos;');
  const toRowXml = (cols) => `<Row>${cols.map(c => `<Cell><Data ss:Type="String">${xmlEscape(c)}</Data></Cell>`).join('')}</Row>`;
  const xml = '<' + '?xml version="1.0"?>\n' +
    `<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">` +
    `<Worksheet ss:Name="Reporte"><Table>${toRowXml(headers)}${rows.map(toRowXml).join('')}</Table></Worksheet>` +
    `</Workbook>`;
  const blob = new Blob([xml], { type: 'application/vnd.ms-excel;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = 'reporte-' + safeId + '.xls';
  document.body.appendChild(a); a.click(); a.remove();
  URL.revokeObjectURL(url);
}
</script>
