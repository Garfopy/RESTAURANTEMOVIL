<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Recorrido — <?= htmlspecialchars($pedido['folio']) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0F172A; color: #F1F5F9; font-family: 'Inter', sans-serif; min-height: 100vh; }
    .app-shell { max-width: 480px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; background: #111827; }
    .header { background: #1E293B; padding: 14px 16px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #334155; position: sticky; top: 0; z-index: 10; }
    .header-back { color: #94A3B8; text-decoration: none; font-size: 1.3rem; line-height: 1; padding: 4px; }
    .header-title { font-weight: 800; font-size: .95rem; }
    .header-sub   { font-size: .72rem; color: #94A3B8; margin-top: 1px; }
    .body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 12px; }
    .card { background: #1E293B; border-radius: 14px; padding: 14px 16px; border: 1px solid #334155; }
    #mapa { height: 280px; width: 100%; border-radius: 12px; overflow: hidden; border: 1px solid #334155; }
    .suc-row { display: flex; align-items: center; gap: 10px; padding: 9px 0; border-bottom: 1px solid #1E293B; }
    .suc-row:last-child { border-bottom: none; }
    .suc-num { width: 24px; height: 24px; border-radius: 50%; background: #059669; color: #fff; font-size: .65rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .suc-nombre { font-size: .82rem; font-weight: 600; flex: 1; }
    .suc-hora { font-size: .7rem; color: #6EE7B7; }
    .suc-foto { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 2px solid #065F46; cursor: pointer; flex-shrink: 0; }
    .stat-row { display: flex; gap: 10px; }
    .stat { flex: 1; background: #0F172A; border-radius: 10px; padding: 10px 12px; text-align: center; }
    .stat .val { font-size: 1.1rem; font-weight: 800; color: #10B981; }
    .stat .lbl { font-size: .65rem; color: #64748B; margin-top: 2px; font-weight: 600; }
    .no-route { padding: 24px; text-align: center; color: #64748B; font-size: .85rem; }
  </style>
</head>
<body>

<div class="app-shell">
  <div class="header">
    <a href="<?= BASE_URL ?>repartidor/historial" class="header-back">&larr;</a>
    <div>
      <div class="header-title"><?= htmlspecialchars($pedido['folio']) ?></div>
      <div class="header-sub"><?= htmlspecialchars($pedido['empresa_nombre']) ?></div>
    </div>
  </div>

  <div class="body">

    <?php
    $durMin = null;
    if ($pedido['ruta_iniciada_at'] && $pedido['ruta_finalizada_at']) {
        $durMin = round((strtotime($pedido['ruta_finalizada_at']) - strtotime($pedido['ruta_iniciada_at'])) / 60);
    }
    ?>

    <!-- Stats -->
    <div class="stat-row">
      <?php if ($pedido['ruta_finalizada_at']): ?>
      <div class="stat">
        <div class="val"><?= date('d/m/Y', strtotime($pedido['ruta_finalizada_at'])) ?></div>
        <div class="lbl">FECHA</div>
      </div>
      <?php endif; ?>
      <?php if ($durMin !== null): ?>
      <div class="stat">
        <div class="val"><?= $durMin ?> min</div>
        <div class="lbl">DURACIÓN</div>
      </div>
      <?php endif; ?>
      <div class="stat">
        <div class="val"><?= count($sucursales) ?></div>
        <div class="lbl">PARADAS</div>
      </div>
    </div>

    <!-- Mapa -->
    <?php if (!empty($pedido['ruta_polyline'])): ?>
    <div id="mapa"></div>
    <?php else: ?>
    <div class="card no-route">📍 No hay recorrido GPS guardado para este viaje.</div>
    <?php endif; ?>

    <!-- Paradas con fotos -->
    <div class="card">
      <div style="font-size:.7rem;font-weight:700;color:#64748B;letter-spacing:.06em;margin-bottom:10px">
        PARADAS ENTREGADAS
      </div>
      <?php if (empty($sucursales)): ?>
      <div style="font-size:.82rem;color:#64748B">Sin paradas registradas.</div>
      <?php else: ?>
      <?php foreach ($sucursales as $i => $s): ?>
      <div class="suc-row">
        <div class="suc-num"><?= $i + 1 ?></div>
        <div class="suc-nombre"><?= htmlspecialchars($s['sucursal_nombre']) ?></div>
        <?php if (!empty($s['fecha_llegada'])): ?>
        <span class="suc-hora"><?= date('H:i', strtotime($s['fecha_llegada'])) ?></span>
        <?php endif; ?>
        <?php if (!empty($s['foto_entrega_path'])): ?>
        <a href="<?= htmlspecialchars($s['foto_entrega_path']) ?>" target="_blank">
          <img src="<?= htmlspecialchars($s['foto_entrega_path']) ?>" alt="Evidencia" class="suc-foto">
        </a>
        <?php else: ?>
        <span style="font-size:.65rem;color:#475569">Sin foto</span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<?php if (!empty($pedido['ruta_polyline'])): ?>
<script>
(function() {
  var pts  = <?= $pedido['ruta_polyline'] ?>;
  var mapa = L.map('mapa');
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
  }).addTo(mapa);

  if (pts && pts.length >= 2) {
    var line = L.polyline(pts, { color: '#C8102E', weight: 4, opacity: .85, dashArray: '8,5' }).addTo(mapa);
    mapa.fitBounds(line.getBounds(), { padding: [20, 20] });

    // Marcador inicio
    L.marker(pts[0], { icon: L.divIcon({
      className: '',
      html: '<div style="background:#3B82F6;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)">▶</div>',
      iconSize: [26, 26], iconAnchor: [13, 13]
    }) }).addTo(mapa).bindPopup('Inicio del viaje');

    // Marcador fin
    L.marker(pts[pts.length - 1], { icon: L.divIcon({
      className: '',
      html: '<div style="background:#059669;color:#fff;border-radius:50%;width:26px;height:26px;display:flex;align-items:center;justify-content:center;font-size:12px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)">■</div>',
      iconSize: [26, 26], iconAnchor: [13, 13]
    }) }).addTo(mapa).bindPopup('Fin del viaje');
  }

  // Paradas numeradas
  var paradas = <?php
    $paradaJs = [];
    foreach ($sucursales as $idx => $s) {
        $paradaJs[] = [
            'lat'    => isset($s['lat'])  ? (float)$s['lat']  : null,
            'lng'    => isset($s['lng'])  ? (float)$s['lng']  : null,
            'nombre' => $s['sucursal_nombre'],
            'num'    => $idx + 1,
        ];
    }
    echo json_encode($paradaJs);
  ?>;
  paradas.forEach(function(p) {
    if (!p.lat || !p.lng) return;
    L.marker([p.lat, p.lng], { icon: L.divIcon({
      className: '',
      html: '<div style="background:#C8102E;color:#fff;border-radius:50%;width:24px;height:24px;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.4)">' + p.num + '</div>',
      iconSize: [24, 24], iconAnchor: [12, 12]
    }) }).addTo(mapa).bindPopup('Parada ' + p.num + ': ' + p.nombre);
  });
})();
</script>
<?php endif; ?>

</body>
</html>
