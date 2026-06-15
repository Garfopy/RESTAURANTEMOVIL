<?php
// Vista: Reportes de plataforma (superadmin / admin)
// Variables: $totalEmpresas, $suscActivas, $ingresosMes, $pedidosMesCount,
//            $distPlanes, $estadoSus, $pedidosMes, $ingresosPorMes,
//            $topEmpresas, $empresasNuevas, $tasaErrores
?>
<script src="<?= BASE_URL ?>public/js/chart.min.js"></script>

<!-- ── KPI cards ── -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:24px">
  <?php
  $kpis = [
    ['label'=>'Empresas activas',    'valor'=>number_format($totalEmpresas),             'bg'=>'#EFF6FF','text'=>'#1E40AF'],
    ['label'=>'Suscripciones activas','valor'=>number_format($suscActivas),              'bg'=>'#F0FDF4','text'=>'#166534'],
    ['label'=>'Ingresos SaaS/mes',   'valor'=>'$'.number_format($ingresosMes,0,'.',','), 'bg'=>'#FFF1F2','text'=>'#9F1239'],
    ['label'=>'Pedidos este mes',    'valor'=>number_format($pedidosMesCount),            'bg'=>'#FFF7ED','text'=>'#9A3412'],
  ];
  foreach ($kpis as $k): ?>
  <div style="background:<?= $k['bg'] ?>;border-radius:12px;padding:18px 20px">
    <div style="font-size:.75rem;font-weight:600;color:<?= $k['text'] ?>;margin-bottom:6px"><?= $k['label'] ?></div>
    <div style="font-size:1.6rem;font-weight:800;color:<?= $k['text'] ?>"><?= $k['valor'] ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Fila 1: Ingresos + Pedidos por mes ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px">
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Ingresos SaaS por mes (nuevas empresas)</h3>
    <canvas id="chartIngresos" height="120"></canvas>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Pedidos procesados por mes</h3>
    <canvas id="chartPedidos" height="120"></canvas>
  </div>
</div>

<!-- ── Fila 2: Planes + Estado suscripciones + Top empresas ── -->
<div style="display:grid;grid-template-columns:1fr 1fr 1.5fr;gap:16px;margin-bottom:20px">

  <!-- Distribución de planes -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Distribución de planes</h3>
    <canvas id="chartPlanes" height="150"></canvas>
    <div style="margin-top:12px">
      <?php foreach ($distPlanes as $pl): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;font-size:.78rem;padding:5px 0;border-bottom:1px solid #F3F4F6">
        <span style="color:#374151"><?= htmlspecialchars($pl['plan']) ?></span>
        <span>
          <strong><?= (int)$pl['activas'] ?></strong>
          <span style="color:#9CA3AF"> / <?= (int)$pl['total_empresas'] ?></span>
        </span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Estado de suscripciones (dona) -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Estado suscripciones</h3>
    <canvas id="chartEstado" height="150"></canvas>
    <div style="margin-top:12px">
      <?php
      $coloresEstado = ['activo'=>'#D1FAE5','suspendido'=>'#FEE2E2','cancelado'=>'#F3F4F6','pendiente_paypal'=>'#FEF3C7'];
      $textosEstado  = ['activo'=>'#065F46','suspendido'=>'#991B1B','cancelado'=>'#374151','pendiente_paypal'=>'#92400E'];
      foreach ($estadoSus as $es): ?>
      <div style="display:flex;justify-content:space-between;font-size:.78rem;padding:4px 0;border-bottom:1px solid #F3F4F6">
        <span style="background:<?= $coloresEstado[$es['estado']] ?? '#F3F4F6' ?>;color:<?= $textosEstado[$es['estado']] ?? '#374151' ?>;padding:2px 8px;border-radius:999px;font-weight:600">
          <?= htmlspecialchars($es['estado']) ?>
        </span>
        <strong><?= (int)$es['total'] ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Top 5 empresas -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Top 5 empresas por pedidos</h3>
    <?php if (empty($topEmpresas)): ?>
      <p style="color:#9CA3AF;font-size:.8rem;text-align:center;margin-top:30px">Sin datos aún.</p>
    <?php else: ?>
    <div style="display:flex;flex-direction:column;gap:10px">
      <?php foreach ($topEmpresas as $i => $emp): ?>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:.8rem;margin-bottom:3px">
          <span style="font-weight:600;color:#111827">
            <span style="color:#9CA3AF;margin-right:6px"><?= $i+1 ?>.</span>
            <?= htmlspecialchars($emp['razon_social']) ?>
          </span>
          <span style="color:#6B7280"><?= (int)$emp['total_pedidos'] ?> ped.</span>
        </div>
        <div style="background:#F3F4F6;border-radius:4px;height:6px">
          <?php $pct = $topEmpresas[0]['total_pedidos'] > 0 ? ((int)$emp['total_pedidos'] / (int)$topEmpresas[0]['total_pedidos'] * 100) : 0; ?>
          <div style="width:<?= $pct ?>%;height:6px;background:var(--color-primary);border-radius:4px"></div>
        </div>
        <div style="font-size:.72rem;color:#9CA3AF;margin-top:2px">$<?= number_format((float)$emp['monto'],0,'.',',') ?> en ventas</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- ── Fila 3: Empresas nuevas + Tasa errores ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Empresas nuevas por mes</h3>
    <canvas id="chartEmpresas" height="120"></canvas>
  </div>

  <?php if (!empty($tasaErrores)): ?>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
    <h3 style="font-size:.875rem;font-weight:700;color:#111827;margin:0 0 14px">Errores del sistema (últimos 30 días)</h3>
    <canvas id="chartErrores" height="120"></canvas>
  </div>
  <?php else: ?>
  <div style="background:#F9FAFB;border:1px dashed #E5E7EB;border-radius:12px;padding:20px;display:flex;align-items:center;justify-content:center">
    <p style="color:#9CA3AF;font-size:.8rem;text-align:center">No hay registro de errores en los últimos 30 días.</p>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  const clrs = ['#C8102E','#1D4ED8','#047857','#92400E','#5B21B6'];

  // Ingresos por mes
  const ingMeses = <?= json_encode(array_column($ingresosPorMes,'mes')) ?>;
  const ingVals  = <?= json_encode(array_map('floatval', array_column($ingresosPorMes,'ingresos'))) ?>;
  new Chart(document.getElementById('chartIngresos'), {
    type:'bar',
    data:{ labels:ingMeses, datasets:[{ label:'Ingresos ($)',data:ingVals,backgroundColor:'rgba(200,16,46,0.15)',borderColor:'#C8102E',borderWidth:2 }] },
    options:{ responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{callback:v=>'$'+v.toLocaleString()}}} }
  });

  // Pedidos por mes
  const pedMeses  = <?= json_encode(array_column($pedidosMes,'mes')) ?>;
  const pedTotals = <?= json_encode(array_map('intval', array_column($pedidosMes,'total'))) ?>;
  const pedMontos = <?= json_encode(array_map('floatval', array_column($pedidosMes,'monto'))) ?>;
  new Chart(document.getElementById('chartPedidos'), {
    type:'bar',
    data:{ labels:pedMeses, datasets:[
      { label:'Pedidos',type:'bar',data:pedTotals,backgroundColor:'rgba(29,78,216,0.15)',borderColor:'#1D4ED8',borderWidth:2,yAxisID:'y' },
      { label:'Monto',type:'line',data:pedMontos,borderColor:'#047857',backgroundColor:'transparent',borderWidth:2,tension:.4,yAxisID:'y1',pointRadius:4 }
    ]},
    options:{responsive:true,interaction:{mode:'index',intersect:false},scales:{y:{position:'left',beginAtZero:true},y1:{position:'right',beginAtZero:true,grid:{drawOnChartArea:false},ticks:{callback:v=>'$'+v.toLocaleString()}}}}
  });

  // Distribución planes
  const planNombres = <?= json_encode(array_column($distPlanes,'plan')) ?>;
  const planActivas = <?= json_encode(array_map('intval', array_column($distPlanes,'activas'))) ?>;
  new Chart(document.getElementById('chartPlanes'),{
    type:'doughnut',
    data:{ labels:planNombres, datasets:[{ data:planActivas, backgroundColor:['#DBEAFE','#D1FAE5','#EDE9FE'], borderWidth:2 }]},
    options:{responsive:true,plugins:{legend:{display:false}}}
  });

  // Estado suscripciones
  const susEstados = <?= json_encode(array_column($estadoSus,'estado')) ?>;
  const susTotales = <?= json_encode(array_map('intval', array_column($estadoSus,'total'))) ?>;
  new Chart(document.getElementById('chartEstado'),{
    type:'doughnut',
    data:{ labels:susEstados, datasets:[{ data:susTotales, backgroundColor:['#D1FAE5','#FEE2E2','#F3F4F6','#FEF3C7'], borderWidth:2 }]},
    options:{responsive:true,plugins:{legend:{display:false}}}
  });

  // Empresas nuevas
  const empMeses  = <?= json_encode(array_column($empresasNuevas,'mes')) ?>;
  const empTotals = <?= json_encode(array_map('intval', array_column($empresasNuevas,'total'))) ?>;
  new Chart(document.getElementById('chartEmpresas'),{
    type:'bar',
    data:{ labels:empMeses, datasets:[{ label:'Empresas',data:empTotals,backgroundColor:'rgba(92,33,182,0.15)',borderColor:'#5B21B6',borderWidth:2 }]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
  });

  <?php if (!empty($tasaErrores)): ?>
  const errDias   = <?= json_encode(array_column($tasaErrores,'dia')) ?>;
  const errTotals = <?= json_encode(array_map('intval', array_column($tasaErrores,'total'))) ?>;
  new Chart(document.getElementById('chartErrores'),{
    type:'bar',
    data:{ labels:errDias, datasets:[{ label:'Errores',data:errTotals,backgroundColor:'rgba(239,68,68,0.2)',borderColor:'#EF4444',borderWidth:2 }]},
    options:{responsive:true,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{stepSize:1}}}}
  });
  <?php endif; ?>
})();
</script>
