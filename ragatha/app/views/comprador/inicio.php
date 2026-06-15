<?php
// Vista: Portal de inicio del comprador
$baseUrl = BASE_URL;
$usuario = $_SESSION['usuario'] ?? [];
$estadoLabel = [
    'pendiente'  => ['Pendiente',  '#FEF3C7','#92400E'],
    'aprobado'   => ['Aprobado',   '#D1FAE5','#065F46'],
    'confirmado' => ['Confirmado', '#DBEAFE','#1E40AF'],
    'en_ruta'    => ['En ruta',    '#EDE9FE','#5B21B6'],
    'entregado'  => ['Entregado',  '#D1FAE5','#065F46'],
    'cancelado'  => ['Cancelado',  '#F3F4F6','#6B7280'],
];
?>

<!-- Banner de cambio de contraseña (primer login) -->
<?php if (!empty($flash) && $flash['type'] === 'first_login'): ?>
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px 24px; border-radius: 12px; margin-bottom: 24px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 16px;">
        <div style="flex: 1;">
            <div style="font-weight: 600; font-size: 1.1rem; margin-bottom: 8px;">
                🔐 Actualiza tu contraseña
            </div>
            <div style="opacity: 0.95; font-size: 0.9rem;">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        </div>
        <div style="display: flex; gap: 12px; align-items: center;">
            <a href="<?= BASE_URL ?>cuenta/perfil"
               style="background: white; color: #667eea; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600; white-space: nowrap; transition: transform 0.2s;"
               onmouseover="this.style.transform='scale(1.05)'"
               onmouseout="this.style.transform='scale(1)'">
                Cambiar contraseña
            </a>
            <button onclick="dismissFirstLoginBanner(<?= $_SESSION['usuario']['id'] ?>)"
                    style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 10px 16px; border-radius: 8px; cursor: pointer; font-weight: 600; white-space: nowrap;"
                    onmouseover="this.style.background='rgba(255,255,255,0.3)'"
                    onmouseout="this.style.background='rgba(255,255,255,0.2)'">
                Recordar después
            </button>
        </div>
    </div>
</div>
<script>
function dismissFirstLoginBanner(userId) {
    fetch('<?= BASE_URL ?>cuenta/dismissFirstLogin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ user_id: userId })
    }).then(() => {
        location.reload();
    });
}
</script>
<?php endif; ?>

<!-- Bienvenida -->
<div style="background:linear-gradient(135deg,var(--color-primary),#991B1B);border-radius:12px;padding:24px;color:#fff;margin-bottom:24px">
  <h2 style="font-size:1.25rem;font-weight:800;margin-bottom:4px">
    Hola, <?= htmlspecialchars($usuario['nombre'] ?? 'Comprador') ?> 👋
  </h2>
  <p style="font-size:.875rem;opacity:.85">¿Qué necesitas hoy? Explora el catálogo o revisa tus pedidos.</p>
  <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap">
    <a href="<?= $baseUrl ?>catalogo/index"
       style="padding:9px 20px;background:#fff;color:var(--color-primary);border-radius:8px;font-weight:700;text-decoration:none;font-size:.875rem">
      Ver catálogo
    </a>
    <a href="<?= $baseUrl ?>carrito/index"
       style="padding:9px 20px;background:rgba(255,255,255,.2);color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:.875rem;border:1px solid rgba(255,255,255,.3)">
      Hacer pedido
    </a>
    <a href="<?= $baseUrl ?>empresa-reporte/index"
       style="padding:9px 20px;background:rgba(255,255,255,.18);color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:.875rem;border:1px solid rgba(255,255,255,.3);display:inline-flex;align-items:center;gap:6px">
      <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
      Generar reporte
    </a>
  </div>
</div>

<?php
// ── KPI helpers ────────────────────────────────────────────────────────────
$pctPresupuesto = $presupuesto > 0 ? min(100, ($gastoMes / $presupuesto) * 100) : 0;
$proximaTxt = '—';
$proximaSub = 'Sin entrega programada';
if (!empty($proximaEntrega)) {
    if (!empty($proximaEntrega['fecha_entrega'])) {
        $proximaTxt = date('d/m H:i', strtotime((string)$proximaEntrega['fecha_entrega']));
    } elseif (!empty($proximaEntrega['eta_minutos'])) {
        $proximaTxt = 'ETA ' . (int)$proximaEntrega['eta_minutos'] . ' min';
    } else {
        $proximaTxt = strtoupper((string)$proximaEntrega['estado']);
    }
    $proximaSub = '#' . htmlspecialchars((string)($proximaEntrega['folio'] ?? ''));
}
$topProdNombre = $topProducto ? mb_strimwidth((string)$topProducto['nombre'], 0, 22, '…') : '—';
$topProdSub    = $topProducto ? number_format((float)$topProducto['total_cantidad'], 2) . ' ' . ($topProducto['presentacion'] ?? '') : 'Sin compras';
?>

<!-- ── KPIs estratégicos del Comprador ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:18px">

  <!-- Roja: Volumen / Kilos -->
  <div style="background:linear-gradient(135deg,#C8102E,#9B0A22);border-radius:12px;padding:18px;color:#fff">
    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85;margin-bottom:8px">Kilos del mes</div>
    <div style="font-size:1.9rem;font-weight:800;line-height:1"><?= number_format($kgMes, 1) ?> <span style="font-size:1rem;opacity:.85">kg</span></div>
    <div style="font-size:.72rem;opacity:.8;margin-top:6px">Volumen para precios escalonados</div>
  </div>

  <!-- Azul: Gasto vs Presupuesto -->
  <div style="background:linear-gradient(135deg,#1D4ED8,#1E40AF);border-radius:12px;padding:18px;color:#fff">
    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85;margin-bottom:8px">Gasto del mes</div>
    <div style="font-size:1.7rem;font-weight:800;line-height:1">$<?= number_format($gastoMes, 0) ?></div>
    <?php if ($presupuesto > 0): ?>
      <div style="height:6px;background:rgba(255,255,255,.25);border-radius:999px;margin-top:10px;overflow:hidden">
        <div style="height:100%;width:<?= number_format($pctPresupuesto, 1) ?>%;background:#fff;border-radius:999px"></div>
      </div>
      <div style="font-size:.72rem;opacity:.85;margin-top:6px">de $<?= number_format($presupuesto, 0) ?> · <?= number_format($pctPresupuesto, 1) ?>%</div>
    <?php else: ?>
      <div style="font-size:.72rem;opacity:.7;margin-top:6px">Sin presupuesto configurado</div>
    <?php endif; ?>
  </div>

  <!-- Naranja: Pedidos en Tránsito -->
  <div style="background:linear-gradient(135deg,#D97706,#B45309);border-radius:12px;padding:18px;color:#fff">
    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85;margin-bottom:8px">En tránsito</div>
    <div style="font-size:1.9rem;font-weight:800;line-height:1"><?= number_format($enTransitoGps) ?></div>
    <div style="font-size:.72rem;opacity:.8;margin-top:6px">pedido(s) con rastreo GPS activo</div>
  </div>

  <!-- Púrpura: Ahorro por Volumen -->
  <div style="background:linear-gradient(135deg,#7C3AED,#5B21B6);border-radius:12px;padding:18px;color:#fff">
    <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;opacity:.85;margin-bottom:8px">Ahorro por volumen</div>
    <div style="font-size:1.7rem;font-weight:800;line-height:1">$<?= number_format($ahorroVolumen, 2) ?></div>
    <div style="font-size:.72rem;opacity:.8;margin-top:6px">precios escalonados (30 días)</div>
  </div>

</div>

<!-- ── KPIs secundarios ─────────────────────────────────────────────────── -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:20px">

  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:14px;display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;background:#EDE9FE;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#7C3AED" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
    </div>
    <div>
      <div style="font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Recurrentes activos</div>
      <div style="font-size:1.4rem;font-weight:800;color:#111827"><?= number_format($recurrentesActivos) ?></div>
      <div style="font-size:.7rem;color:#9CA3AF">plantillas automáticas</div>
    </div>
  </div>

  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:14px;display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;background:#DBEAFE;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#1D4ED8" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
    </div>
    <div style="min-width:0">
      <div style="font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Próxima entrega</div>
      <div style="font-size:1.1rem;font-weight:800;color:#111827"><?= htmlspecialchars($proximaTxt) ?></div>
      <div style="font-size:.7rem;color:#9CA3AF;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $proximaSub ?></div>
    </div>
  </div>

  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:14px;display:flex;align-items:center;gap:12px">
    <div style="width:42px;height:42px;background:#FEF3C7;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.286 3.967h4.17c.969 0 1.371 1.24.588 1.81l-3.376 2.455 1.287 3.967c.3.921-.755 1.688-1.54 1.118L12 13.347l-3.366 2.897c-.785.57-1.84-.197-1.54-1.118l1.287-3.967-3.376-2.455c-.783-.57-.38-1.81.588-1.81h4.17l1.286-3.967z"/></svg>
    </div>
    <div style="min-width:0">
      <div style="font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Top producto</div>
      <div style="font-size:1.1rem;font-weight:800;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($topProducto['nombre'] ?? '') ?>"><?= htmlspecialchars($topProdNombre) ?></div>
      <div style="font-size:.7rem;color:#9CA3AF"><?= htmlspecialchars($topProdSub) ?></div>
    </div>
  </div>

</div>

<!-- ── Gráficas: Consumo por categoría + Gasto semanal ──────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1.6fr;gap:18px;margin-bottom:24px">

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:18px">
    <div style="font-weight:700;color:#111827;font-size:.95rem;margin-bottom:4px">Consumo por categoría</div>
    <div style="font-size:.74rem;color:#6B7280;margin-bottom:12px">Últimos 30 días · monto</div>
    <?php if (empty($consumoCategoria)): ?>
      <div style="text-align:center;padding:40px 0;color:#9CA3AF;font-size:.85rem">Sin datos en este período.</div>
    <?php else: ?>
      <div style="height:200px"><canvas id="chart-comp-categoria"></canvas></div>
    <?php endif; ?>
  </div>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:18px">
    <div style="font-weight:700;color:#111827;font-size:.95rem;margin-bottom:4px">Historial de gasto semanal</div>
    <div style="font-size:.74rem;color:#6B7280;margin-bottom:12px">Últimas 8 semanas</div>
    <?php if (empty($gastoSemanal)): ?>
      <div style="text-align:center;padding:60px 0;color:#9CA3AF;font-size:.85rem">Sin compras registradas.</div>
    <?php else: ?>
      <div style="height:220px"><canvas id="chart-comp-semanal"></canvas></div>
    <?php endif; ?>
  </div>

</div>

<!-- Pedidos en ruta -->
<?php if (!empty($enRuta)): ?>
<div style="background:#EDE9FE;border:1px solid #C4B5FD;border-radius:10px;padding:16px;margin-bottom:20px">
  <div style="font-weight:700;color:#5B21B6;margin-bottom:10px;font-size:.875rem">
    🚚 <?= count($enRuta) ?> pedido(s) en camino a ti
  </div>
  <div style="display:flex;flex-direction:column;gap:8px">
    <?php foreach ($enRuta as $pr): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;background:#fff;padding:10px 14px;border-radius:8px">
      <span style="font-size:.875rem;font-weight:600;color:#5B21B6"><?= htmlspecialchars($pr['folio']) ?></span>
      <a href="<?= $baseUrl ?>pedido/tracking/<?= $pr['id'] ?>"
         style="padding:5px 14px;background:#7C3AED;color:#fff;border-radius:6px;text-decoration:none;font-size:.78rem;font-weight:600">
        Rastrear en mapa
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Grid: últimos pedidos + acceso rápido -->
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px">

  <!-- Últimos pedidos -->
  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
    <div style="padding:14px 16px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between">
      <span style="font-weight:700;color:#111827">Mis últimos pedidos</span>
      <a href="<?= $baseUrl ?>pedido/index" style="font-size:.8rem;color:var(--color-primary);text-decoration:none;font-weight:600">Ver historial →</a>
    </div>
    <?php if (empty($ultimosPedidos)): ?>
      <div style="padding:32px;text-align:center;color:#9CA3AF">
        <p style="font-size:1rem;font-weight:600">Aún no tienes pedidos</p>
        <p style="font-size:.875rem;margin-top:4px">Haz tu primer pedido en el catálogo.</p>
        <a href="<?= $baseUrl ?>catalogo/index"
           style="display:inline-block;margin-top:12px;padding:9px 20px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
          Explorar catálogo
        </a>
      </div>
    <?php else: ?>
    <div style="padding:16px;display:flex;flex-direction:column;gap:8px">
      <?php foreach ($ultimosPedidos as $ped):
        [$lb, $bg, $tx] = $estadoLabel[$ped['estado']] ?? ['—','#F3F4F6','#6B7280'];
      ?>
      <a href="<?= $baseUrl ?>pedido/detalle/<?= $ped['id'] ?>"
         style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border:1px solid #E5E7EB;border-radius:8px;text-decoration:none;transition:background .15s"
         onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='transparent'">
        <div>
          <div style="font-size:.875rem;font-weight:700;color:#111827"><?= htmlspecialchars($ped['folio']) ?></div>
          <div style="font-size:.75rem;color:#6B7280"><?= date('d/m/Y', strtotime($ped['created_at'])) ?></div>
        </div>
        <div style="display:flex;align-items:center;gap:12px">
          <span style="font-size:.875rem;font-weight:700;color:#111827">$<?= number_format($ped['total'], 2) ?></span>
          <span style="padding:3px 10px;border-radius:999px;background:<?= $bg ?>;color:<?= $tx ?>;font-size:.7rem;font-weight:600"><?= $lb ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Acceso rápido -->
  <div style="display:flex;flex-direction:column;gap:12px">
    <a href="<?= $baseUrl ?>catalogo/index"
       style="display:flex;align-items:center;gap:12px;padding:16px;background:#fff;border-radius:10px;border:1px solid #E5E7EB;text-decoration:none">
      <span style="font-size:1.5rem">📋</span>
      <div>
        <div style="font-weight:700;color:#111827;font-size:.875rem">Catálogo</div>
        <div style="font-size:.75rem;color:#6B7280">Ver todos los productos</div>
      </div>
    </a>
    <a href="<?= $baseUrl ?>carrito/index"
       style="display:flex;align-items:center;gap:12px;padding:16px;background:#fff;border-radius:10px;border:1px solid #E5E7EB;text-decoration:none">
      <span style="font-size:1.5rem">🛒</span>
      <div>
        <div style="font-weight:700;color:#111827;font-size:.875rem">Nuevo pedido</div>
        <div style="font-size:.75rem;color:#6B7280">Agregar al carrito</div>
      </div>
    </a>
    <a href="<?= $baseUrl ?>pedido/index"
       style="display:flex;align-items:center;gap:12px;padding:16px;background:#fff;border-radius:10px;border:1px solid #E5E7EB;text-decoration:none">
      <span style="font-size:1.5rem">📦</span>
      <div>
        <div style="font-weight:700;color:#111827;font-size:.875rem">Mis pedidos</div>
        <div style="font-size:.75rem;color:#6B7280">Historial completo</div>
      </div>
    </a>
    <a href="<?= $baseUrl ?>cuenta/perfil"
       style="display:flex;align-items:center;gap:12px;padding:16px;background:#fff;border-radius:10px;border:1px solid #E5E7EB;text-decoration:none">
      <span style="font-size:1.5rem">👤</span>
      <div>
        <div style="font-weight:700;color:#111827;font-size:.875rem">Mi perfil</div>
        <div style="font-size:.75rem;color:#6B7280">Datos y contraseña</div>
      </div>
    </a>
  </div>
</div>

<!-- ── Chart.js para gráficas del Comprador ─────────────────────────────── -->
<script src="<?= $baseUrl ?>public/js/chart.min.js"></script>
<script>
(function(){
  if (typeof Chart === 'undefined') return;

  const catLabels = <?= json_encode(array_map(fn($x) => (string)$x['categoria'], $consumoCategoria), JSON_UNESCAPED_UNICODE) ?>;
  const catData   = <?= json_encode(array_map(fn($x) => (float)$x['monto'], $consumoCategoria)) ?>;
  const semLabels = <?= json_encode(array_map(fn($x) => 'Sem ' . substr((string)$x['yw'], 4), $gastoSemanal)) ?>;
  const semData   = <?= json_encode(array_map(fn($x) => (float)$x['gasto'], $gastoSemanal)) ?>;
  const palette   = ['#C8102E','#1D4ED8','#D97706','#7C3AED','#10B981','#0EA5E9','#F59E0B','#EC4899'];

  const cCat = document.getElementById('chart-comp-categoria');
  if (cCat && catLabels.length) {
    new Chart(cCat, {
      type: 'doughnut',
      data: {
        labels: catLabels,
        datasets: [{ data: catData, backgroundColor: palette, borderWidth: 0 }]
      },
      options: {
        responsive: true, maintainAspectRatio: false, cutout: '62%',
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } },
          tooltip: { callbacks: { label: (c) => c.label + ': $' + Number(c.raw).toLocaleString('es-MX', { minimumFractionDigits: 2 }) } }
        }
      }
    });
  }

  const cSem = document.getElementById('chart-comp-semanal');
  if (cSem && semLabels.length) {
    new Chart(cSem, {
      type: 'bar',
      data: {
        labels: semLabels,
        datasets: [{
          label: 'Gasto semanal',
          data: semData,
          backgroundColor: 'rgba(200,16,46,.85)',
          borderRadius: 6, maxBarThickness: 38
        }]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: { callbacks: { label: (c) => '$' + Number(c.raw).toLocaleString('es-MX', { minimumFractionDigits: 2 }) } }
        },
        scales: {
          y: { beginAtZero: true, ticks: { callback: (v) => '$' + Number(v).toLocaleString('es-MX') }, grid: { color: '#F3F4F6' } },
          x: { grid: { display: false } }
        }
      }
    });
  }
})();
</script>

