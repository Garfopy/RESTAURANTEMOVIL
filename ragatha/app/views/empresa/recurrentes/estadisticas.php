<?php
/**
 * Vista: empresa/recurrentes/estadisticas.php
 * Variables disponibles: $resumen, $topProductos, $diasSemana, $topCompradores, $productosRecurrentes
 */
$fmt = fn($n) => '$' . number_format((float)$n, 2, '.', ',');
?>

<!-- Encabezado de página -->
<div style="margin-bottom:24px">
  <h1 style="font-size:1.4rem;font-weight:800;color:#111827;margin:0">Patrones de compra frecuente</h1>
  <p style="font-size:.85rem;color:#6B7280;margin:4px 0 0">Análisis del historial real de pedidos de tu empresa.</p>
</div>

<?php if ($resumen['total_pedidos'] === 0): ?>
<!-- Estado vacío -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:48px;text-align:center">
  <div style="font-size:3rem;margin-bottom:12px">&#128230;</div>
  <div style="font-size:1rem;font-weight:700;color:#374151;margin-bottom:6px">Aún no hay pedidos registrados</div>
  <div style="font-size:.85rem;color:#6B7280">Cuando tus compradores realicen pedidos, aquí verás los patrones de compra más frecuentes.</div>
</div>
<?php else: ?>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px">
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Total de pedidos</div>
    <div style="font-size:2rem;font-weight:800;color:#111827"><?= number_format($resumen['total_pedidos']) ?></div>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Compradores únicos</div>
    <div style="font-size:2rem;font-weight:800;color:#2563EB"><?= number_format($resumen['compradores_unicos']) ?></div>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Productos distintos</div>
    <div style="font-size:2rem;font-weight:800;color:#059669"><?= number_format($resumen['productos_distintos']) ?></div>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Monto total</div>
    <div style="font-size:1.5rem;font-weight:800;color:#7C3AED"><?= $fmt($resumen['monto_total']) ?></div>
  </div>
</div>

<!-- Pedidos por día de la semana -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px;margin-bottom:24px">
  <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin:0 0 16px">&#128197; Pedidos por día de la semana</h2>
  <canvas id="chartDias" height="100"></canvas>
</div>

<!-- Top productos -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:24px">
  <div style="padding:16px 18px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin:0">&#127942; Productos más pedidos</h2>
    <span style="font-size:.75rem;color:#9CA3AF">Top 10</span>
  </div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem">
      <thead>
        <tr style="background:#F9FAFB">
          <th style="padding:10px 14px;text-align:left;color:#6B7280;font-weight:600">#</th>
          <th style="padding:10px 14px;text-align:left;color:#6B7280;font-weight:600">Producto</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Presentación</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Veces pedido</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Cantidad total</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Compradores</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topProductos as $i => $prod): ?>
        <tr style="border-top:1px solid #F3F4F6;<?= $i % 2 === 0 ? '' : 'background:#FAFAFA' ?>">
          <td style="padding:10px 14px;font-weight:700;color:<?= $i < 3 ? '#D97706' : '#9CA3AF' ?>"><?= $i + 1 ?></td>
          <td style="padding:10px 14px;font-weight:600;color:#111827"><?= htmlspecialchars($prod['nombre']) ?></td>
          <td style="padding:10px;color:#6B7280"><?= htmlspecialchars($prod['presentacion'] ?? '—') ?></td>
          <td style="padding:10px;text-align:right;font-weight:700;color:#2563EB"><?= number_format($prod['veces_pedido']) ?></td>
          <td style="padding:10px;text-align:right;color:#374151"><?= number_format($prod['cantidad_total'], 1) ?></td>
          <td style="padding:10px;text-align:right;color:#059669"><?= number_format($prod['compradores']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Top compradores -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6">
      <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">&#128100; Compradores más frecuentes</h2>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead>
          <tr style="background:#F9FAFB">
            <th style="padding:9px 14px;text-align:left;color:#6B7280;font-weight:600">Comprador</th>
            <th style="padding:9px;text-align:right;color:#6B7280;font-weight:600">Pedidos</th>
            <th style="padding:9px;text-align:right;color:#6B7280;font-weight:600">Monto</th>
            <th style="padding:9px;text-align:left;color:#6B7280;font-weight:600">Último pedido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topCompradores as $comp): ?>
          <tr style="border-top:1px solid #F3F4F6">
            <td style="padding:9px 14px;font-weight:600;color:#111827"><?= htmlspecialchars($comp['comprador']) ?></td>
            <td style="padding:9px;text-align:right;font-weight:700;color:#2563EB"><?= $comp['total_pedidos'] ?></td>
            <td style="padding:9px;text-align:right;color:#374151"><?= $fmt($comp['monto_total']) ?></td>
            <td style="padding:9px;color:#9CA3AF;font-size:.78rem"><?= $comp['ultimo_pedido'] ? date('d/m/Y', strtotime($comp['ultimo_pedido'])) : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Gráfica top productos (horizontal bar) -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0 0 14px">Frecuencia por producto</h2>
    <canvas id="chartProductos" height="200"></canvas>
  </div>

</div>

<!-- Patrones recurrentes por comprador -->
<?php if (!empty($productosRecurrentes)): ?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:24px">
  <div style="padding:16px 18px;border-bottom:1px solid #F3F4F6">
    <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin:0">&#128260; Patrones recurrentes por comprador</h2>
    <p style="font-size:.78rem;color:#9CA3AF;margin:2px 0 0">Productos pedidos &#8805;2 veces por el mismo comprador</p>
  </div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.83rem">
      <thead>
        <tr style="background:#F9FAFB">
          <th style="padding:10px 14px;text-align:left;color:#6B7280;font-weight:600">Comprador</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Producto</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Presentación</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Veces pedido</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Cantidad</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Último pedido</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($productosRecurrentes as $rec): ?>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:10px 14px;font-weight:600;color:#374151"><?= htmlspecialchars($rec['comprador']) ?></td>
          <td style="padding:10px;color:#111827"><?= htmlspecialchars($rec['nombre']) ?></td>
          <td style="padding:10px;color:#6B7280"><?= htmlspecialchars($rec['presentacion'] ?? '—') ?></td>
          <td style="padding:10px;text-align:right">
            <span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:700"><?= $rec['veces_pedido'] ?>&#215;</span>
          </td>
          <td style="padding:10px;text-align:right;color:#374151"><?= number_format($rec['cantidad_total'], 1) ?></td>
          <td style="padding:10px;color:#9CA3AF;font-size:.78rem"><?= $rec['ultimo_pedido'] ? date('d/m/Y', strtotime($rec['ultimo_pedido'])) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Scripts Chart.js -->
<script src="<?= BASE_URL ?>js/chart.umd.min.js"></script>
<script>
(function(){
  // Gráfica de días de la semana
  const diasLabels = <?= json_encode(array_column($diasSemana, 'dia_nombre')) ?>;
  const diasData   = <?= json_encode(array_column($diasSemana, 'total_pedidos')) ?>;
  new Chart(document.getElementById('chartDias'), {
    type: 'bar',
    data: {
      labels: diasLabels,
      datasets: [{
        label: 'Pedidos',
        data: diasData,
        backgroundColor: '#3B82F6',
        borderRadius: 6,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });

  // Gráfica horizontal de top productos
  const prodLabels = <?= json_encode(array_map(fn($p) => mb_strimwidth($p['nombre'], 0, 24, '...'), array_slice($topProductos, 0, 8))) ?>;
  const prodData   = <?= json_encode(array_column(array_slice($topProductos, 0, 8), 'veces_pedido')) ?>;
  new Chart(document.getElementById('chartProductos'), {
    type: 'bar',
    data: {
      labels: prodLabels,
      datasets: [{
        label: 'Veces pedido',
        data: prodData,
        backgroundColor: '#8B5CF6',
        borderRadius: 4,
      }]
    },
    options: {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });
})();
</script>

<?php endif; ?>
�gina -->
<div style="margin-bottom:24px">
  <h1 style="font-size:1.4rem;font-weight:800;color:#111827;margin:0">Patrones de compra frecuente</h1>
  <p style="font-size:.85rem;color:#6B7280;margin:4px 0 0">An�lisis del historial real de pedidos de tu empresa.</p>
</div>

<?php if ($resumen['total_pedidos'] === 0): ?>
<!-- Estado vac�o -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:48px;text-align:center">
  <div style="font-size:3rem;margin-bottom:12px">??</div>
  <div style="font-size:1rem;font-weight:700;color:#374151;margin-bottom:6px">A�n no hay pedidos registrados</div>
  <div style="font-size:.85rem;color:#6B7280">Cuando tus compradores realicen pedidos, aqu� ver�s los patrones de compra m�s frecuentes.</div>
</div>
<?php else: ?>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px;margin-bottom:24px">
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Total de pedidos</div>
    <div style="font-size:2rem;font-weight:800;color:#111827"><?= number_format($resumen['total_pedidos']) ?></div>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Compradores �nicos</div>
    <div style="font-size:2rem;font-weight:800;color:#2563EB"><?= number_format($resumen['compradores_unicos']) ?></div>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Productos distintos</div>
    <div style="font-size:2rem;font-weight:800;color:#059669"><?= number_format($resumen['productos_distintos']) ?></div>
  </div>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <div style="font-size:.72rem;font-weight:600;color:#6B7280;margin-bottom:4px">Monto total</div>
    <div style="font-size:1.5rem;font-weight:800;color:#7C3AED"><?= $fmt($resumen['monto_total']) ?></div>
  </div>
</div>

<!-- Pedidos por d�a de la semana -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px;margin-bottom:24px">
  <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin:0 0 16px">?? Pedidos por d�a de la semana</h2>
  <canvas id="chartDias" height="100"></canvas>
</div>

<!-- Top productos -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:24px">
  <div style="padding:16px 18px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin:0">?? Productos m�s pedidos</h2>
    <span style="font-size:.75rem;color:#9CA3AF">Top 10</span>
  </div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.85rem">
      <thead>
        <tr style="background:#F9FAFB">
          <th style="padding:10px 14px;text-align:left;color:#6B7280;font-weight:600">#</th>
          <th style="padding:10px 14px;text-align:left;color:#6B7280;font-weight:600">Producto</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Presentaci�n</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Veces pedido</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Cantidad total</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Compradores</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($topProductos as $i => $prod): ?>
        <tr style="border-top:1px solid #F3F4F6;<?= $i % 2 === 0 ? '' : 'background:#FAFAFA' ?>">
          <td style="padding:10px 14px;font-weight:700;color:<?= $i < 3 ? '#D97706' : '#9CA3AF' ?>"><?= $i + 1 ?></td>
          <td style="padding:10px 14px;font-weight:600;color:#111827"><?= htmlspecialchars($prod['nombre']) ?></td>
          <td style="padding:10px;color:#6B7280"><?= htmlspecialchars($prod['presentacion'] ?? '�') ?></td>
          <td style="padding:10px;text-align:right;font-weight:700;color:#2563EB"><?= number_format($prod['veces_pedido']) ?></td>
          <td style="padding:10px;text-align:right;color:#374151"><?= number_format($prod['cantidad_total'], 1) ?></td>
          <td style="padding:10px;text-align:right;color:#059669"><?= number_format($prod['compradores']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

  <!-- Top compradores -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #F3F4F6">
      <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">?? Compradores m�s frecuentes</h2>
    </div>
    <div style="overflow-x:auto">
      <table style="width:100%;border-collapse:collapse;font-size:.82rem">
        <thead>
          <tr style="background:#F9FAFB">
            <th style="padding:9px 14px;text-align:left;color:#6B7280;font-weight:600">Comprador</th>
            <th style="padding:9px;text-align:right;color:#6B7280;font-weight:600">Pedidos</th>
            <th style="padding:9px;text-align:right;color:#6B7280;font-weight:600">Monto</th>
            <th style="padding:9px;text-align:left;color:#6B7280;font-weight:600">�ltimo pedido</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($topCompradores as $comp): ?>
          <tr style="border-top:1px solid #F3F4F6">
            <td style="padding:9px 14px;font-weight:600;color:#111827"><?= htmlspecialchars($comp['comprador']) ?></td>
            <td style="padding:9px;text-align:right;font-weight:700;color:#2563EB"><?= $comp['total_pedidos'] ?></td>
            <td style="padding:9px;text-align:right;color:#374151"><?= $fmt($comp['monto_total']) ?></td>
            <td style="padding:9px;color:#9CA3AF;font-size:.78rem"><?= $comp['ultimo_pedido'] ? date('d/m/Y', strtotime($comp['ultimo_pedido'])) : '�' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Gr�fica top productos (horizontal bar) -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:18px">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0 0 14px">Frecuencia por producto</h2>
    <canvas id="chartProductos" height="200"></canvas>
  </div>

</div>

<!-- Patrones recurrentes por comprador -->
<?php if (!empty($productosRecurrentes)): ?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:24px">
  <div style="padding:16px 18px;border-bottom:1px solid #F3F4F6">
    <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin:0">?? Patrones recurrentes por comprador</h2>
    <p style="font-size:.78rem;color:#9CA3AF;margin:2px 0 0">Productos pedidos =2 veces por el mismo comprador</p>
  </div>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;font-size:.83rem">
      <thead>
        <tr style="background:#F9FAFB">
          <th style="padding:10px 14px;text-align:left;color:#6B7280;font-weight:600">Comprador</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Producto</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Presentaci�n</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Veces pedido</th>
          <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Cantidad</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">�ltimo pedido</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($productosRecurrentes as $rec): ?>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:10px 14px;font-weight:600;color:#374151"><?= htmlspecialchars($rec['comprador']) ?></td>
          <td style="padding:10px;color:#111827"><?= htmlspecialchars($rec['nombre']) ?></td>
          <td style="padding:10px;color:#6B7280"><?= htmlspecialchars($rec['presentacion'] ?? '�') ?></td>
          <td style="padding:10px;text-align:right">
            <span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:700"><?= $rec['veces_pedido'] ?>�</span>
          </td>
          <td style="padding:10px;text-align:right;color:#374151"><?= number_format($rec['cantidad_total'], 1) ?></td>
          <td style="padding:10px;color:#9CA3AF;font-size:.78rem"><?= $rec['ultimo_pedido'] ? date('d/m/Y', strtotime($rec['ultimo_pedido'])) : '�' ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Scripts Chart.js -->
<script src="<?= BASE_URL ?>js/chart.umd.min.js"></script>
<script>
(function(){
  // Gr�fica de d�as de la semana
  const diasLabels = <?= json_encode(array_column($diasSemana, 'dia_nombre')) ?>;
  const diasData   = <?= json_encode(array_column($diasSemana, 'total_pedidos')) ?>;
  new Chart(document.getElementById('chartDias'), {
    type: 'bar',
    data: {
      labels: diasLabels,
      datasets: [{
        label: 'Pedidos',
        data: diasData,
        backgroundColor: '#3B82F6',
        borderRadius: 6,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });

  // Gr�fica horizontal de top productos
  const prodLabels = <?= json_encode(array_map(fn($p) => mb_strimwidth($p['nombre'], 0, 24, '�'), array_slice($topProductos, 0, 8))) ?>;
  const prodData   = <?= json_encode(array_column(array_slice($topProductos, 0, 8), 'veces_pedido')) ?>;
  new Chart(document.getElementById('chartProductos'), {
    type: 'bar',
    data: {
      labels: prodLabels,
      datasets: [{
        label: 'Veces pedido',
        data: prodData,
        backgroundColor: '#8B5CF6',
        borderRadius: 4,
      }]
    },
    options: {
      indexAxis: 'y',
      plugins: { legend: { display: false } },
      scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
  });
})();
</script>

<?php endif; ?>
