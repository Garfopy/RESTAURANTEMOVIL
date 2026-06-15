<?php ob_start(); ?>
<!-- Filtro de fechas -->
<form method="GET" action="<?= BASE_URL ?>rest-finanzas/dashboard"
      style="display:flex;gap:10px;align-items:center;margin-bottom:20px">
  <input type="date" name="desde" value="<?= htmlspecialchars($desde) ?>"
    style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
  <span style="color:#6B7280">—</span>
  <input type="date" name="hasta" value="<?= htmlspecialchars($hasta) ?>"
    style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
  <button type="submit"
    style="padding:8px 16px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.85rem;font-weight:500;cursor:pointer">
    Filtrar
  </button>
</form>

<!-- KPI Cards principales -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">
  <?php $kpiCards = [
    ['label'=>'Ingresos Totales', 'val'=>'$'.number_format($kpis['ingresos'],2), 'color'=>'#10B981','icon'=>'↑'],
    ['label'=>'Gastos Totales',   'val'=>'$'.number_format($kpis['gastos'],2),   'color'=>'#EF4444','icon'=>'↓'],
    ['label'=>'Retiros',          'val'=>'$'.number_format($kpis['retiros'],2),   'color'=>'#F59E0B','icon'=>'↕'],
    ['label'=>'Utilidad Neta',    'val'=>'$'.number_format($kpis['utilidad'],2),  'color'=>'#6366F1','icon'=>'='],
  ]; foreach ($kpiCards as $c): ?>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280;margin-bottom:4px"><?= $c['label'] ?></div>
    <div style="font-size:1.5rem;font-weight:700;color:<?= $c['color'] ?>"><?= $c['val'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Margen de Ganancia</div>
    <div style="font-size:1.4rem;font-weight:700;color:#111827"><?= $kpis['margen'] ?>%</div>
  </div>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Total Tickets Pagados</div>
    <div style="font-size:1.4rem;font-weight:700;color:#111827"><?= $kpis['totalTickets'] ?></div>
  </div>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Propinas</div>
    <div style="font-size:1.4rem;font-weight:700;color:#10B981">$<?= number_format($kpis['propinas'],2) ?></div>
  </div>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Pendiente por Cobrar</div>
    <div style="font-size:1.4rem;font-weight:700;color:#EF4444">$<?= number_format($kpis['pendiente'],2) ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:24px">
  <!-- Gráfica ingresos vs egresos -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px">
    <div style="font-weight:600;margin-bottom:14px">Ingresos vs Egresos</div>
    <canvas id="chartIngEgr" height="120"></canvas>
  </div>

  <!-- Gastos por categoría -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px">
    <div style="font-weight:600;margin-bottom:14px">Gastos por Categoría</div>
    <?php foreach ($catGastos as $cg): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F3F4F6;font-size:.85rem">
      <span style="color:#374151"><?= htmlspecialchars($cg['categoria']) ?></span>
      <span style="font-weight:600">$<?= number_format((float)$cg['total'],2) ?></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($catGastos)): ?><p style="color:#9CA3AF;font-size:.85rem">Sin gastos en el período.</p><?php endif; ?>
  </div>
</div>

<!-- Métodos de pago y actividad reciente -->
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px">
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px">
    <div style="font-weight:600;margin-bottom:14px">Métodos de Pago</div>
    <?php if (!empty($metodos)): ?>
    <div style="position:relative;height:180px;margin-bottom:12px">
      <canvas id="chartMetodosPago"></canvas>
    </div>
    <?php endif; ?>
    <?php foreach ($metodos as $mp): ?>
    <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #F3F4F6;font-size:.85rem">
      <span style="color:#374151"><?= htmlspecialchars($mp['metodo_pago'] ?? 'efectivo') ?></span>
      <span style="font-weight:600">$<?= number_format((float)$mp['total'],2) ?> <span style="color:#9CA3AF">(<?= $mp['cantidad'] ?>)</span></span>
    </div>
    <?php endforeach; ?>
    <?php if (empty($metodos)): ?>
    <p style="color:#9CA3AF;font-size:.85rem">Sin pagos registrados en el período.</p>
    <?php endif; ?>
  </div>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px">
    <div style="font-weight:600;margin-bottom:14px">Actividad Reciente</div>
    <?php foreach ($reciente as $act): ?>
    <?php $colors=['gasto'=>['#FEE2E2','#991B1B'],'retiro'=>['#FEF3C7','#92400E'],'corte'=>['#DBEAFE','#1E40AF']]; $cs=$colors[$act['tipo']]??['#F3F4F6','#374151']; ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #F3F4F6;font-size:.85rem">
      <div>
        <span style="padding:1px 7px;border-radius:99px;font-size:.7rem;font-weight:600;background:<?= $cs[0] ?>;color:<?= $cs[1] ?>;margin-right:8px"><?= $act['tipo'] ?></span>
        <?= htmlspecialchars($act['descripcion'] ?? '') ?>
      </div>
      <span style="font-weight:600">$<?= number_format((float)($act['monto'] ?? 0), 2) ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
(function() {
  const ingData  = <?= json_encode(array_map(fn($r) => ['x'=>$r['dia'],'y'=> (float)$r['total']], $grafica['ingresos'])) ?>;
  const egrData  = <?= json_encode(array_map(fn($r) => ['x'=>$r['dia'],'y'=> (float)$r['total']], $grafica['egresos'])) ?>;
  const allDates = [...new Set([...ingData.map(d=>d.x), ...egrData.map(d=>d.x)])].sort();
  const toMap = arr => Object.fromEntries(arr.map(d=>[d.x, d.y]));
  const imap = toMap(ingData), emap = toMap(egrData);

  new Chart(document.getElementById('chartIngEgr'), {
    type: 'bar',
    data: {
      labels: allDates,
      datasets: [
        {label:'Ingresos', data: allDates.map(d=>imap[d]||0), backgroundColor:'#10B981'},
        {label:'Egresos',  data: allDates.map(d=>emap[d]||0), backgroundColor:'#EF4444'},
      ]
    },
    options: { responsive: true, plugins: { legend: { position: 'top' } }, scales: { y: { beginAtZero: true } } }
  });

  // Gráfica de métodos de pago (doughnut)
  const mpCanvas = document.getElementById('chartMetodosPago');
  const mpData   = <?= json_encode(array_map(fn($m) => [
                       'label' => $m['metodo_pago'] ?? 'efectivo',
                       'total' => (float)$m['total'],
                   ], $metodos)) ?>;
  if (mpCanvas && mpData.length) {
    const palette = ['#6366F1','#10B981','#F59E0B','#EF4444','#0EA5E9','#8B5CF6','#EC4899'];
    new Chart(mpCanvas, {
      type: 'doughnut',
      data: {
        labels: mpData.map(m => m.label),
        datasets: [{
          data: mpData.map(m => m.total),
          backgroundColor: mpData.map((_, i) => palette[i % palette.length]),
          borderWidth: 2,
          borderColor: '#fff',
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
          tooltip: { callbacks: {
            label: ctx => ctx.label + ': $' + ctx.parsed.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2})
          }}
        }
      }
    });
  }
})();
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
