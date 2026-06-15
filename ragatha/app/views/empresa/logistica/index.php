<?php
// Vista: Logística — Mis Rutas (admin_empresa)
$baseUrl = BASE_URL;
?>

<!-- Header de acción -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
  <p style="font-size:.875rem;color:#6B7280">Gestiona las rutas de entrega de tu empresa y sigue a tus repartidores en tiempo real.</p>
  <a href="<?= $baseUrl ?>empresa-logistica/nuevaRuta"
     style="padding:8px 16px;background:var(--color-primary);color:#fff;border-radius:6px;font-weight:600;text-decoration:none;font-size:.875rem">
    + Nueva ruta
  </a>
</div>

<!-- Mapa -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:20px">
  <div style="padding:12px 16px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;justify-content:space-between">
    <span style="font-weight:600;color:#111827;font-size:.875rem">Mapa de rutas activas</span>
    <span style="font-size:.75rem;color:#6B7280"><?= count($posiciones) ?> repartidor(es) en movimiento</span>
  </div>
  <div id="mapa" style="height:380px"></div>
</div>

<!-- Tabla de rutas activas -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
  <div style="padding:14px 16px;border-bottom:1px solid #E5E7EB">
    <span style="font-weight:600;color:#111827;font-size:.875rem">Rutas activas y pendientes</span>
  </div>
  <?php if (empty($rutasActivas)): ?>
    <div style="padding:40px;text-align:center;color:#9CA3AF">
      <p style="font-weight:600;font-size:1rem">No hay rutas activas</p>
      <p style="font-size:.875rem;margin-top:4px">Crea una nueva ruta para comenzar a asignar entregas.</p>
      <a href="<?= $baseUrl ?>empresa-logistica/nuevaRuta"
         style="display:inline-block;margin-top:16px;padding:10px 20px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600">
        + Nueva ruta
      </a>
    </div>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase">Fecha</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase">Repartidor</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase">Paradas</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase">Entregadas</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;color:#6B7280;font-weight:600;text-transform:uppercase">Estado</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($rutasActivas as $ruta): ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:12px 16px;font-size:.875rem;color:#374151">
          <?= date('d/m/Y', strtotime($ruta['fecha'])) ?>
        </td>
        <td style="padding:12px 16px;font-size:.875rem;font-weight:600;color:#111827">
          <?= htmlspecialchars($ruta['repartidor_nombre'] . ' ' . $ruta['repartidor_apellido']) ?>
        </td>
        <td style="padding:12px 16px;text-align:center;font-size:.875rem;color:#374151">
          <?= (int)$ruta['total_paradas'] ?>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <span style="font-size:.875rem;font-weight:700;color:#059669"><?= (int)$ruta['entregadas'] ?></span>
          <span style="font-size:.75rem;color:#9CA3AF"> / <?= (int)$ruta['total_paradas'] ?></span>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <?php
            $stColor = $ruta['estado'] === 'en_ruta' ? ['#D1FAE5','#065F46','En ruta'] : ['#FEF3C7','#92400E','Pendiente'];
          ?>
          <span style="padding:3px 10px;border-radius:999px;background:<?= $stColor[0] ?>;color:<?= $stColor[1] ?>;font-size:.7rem;font-weight:600">
            <?= $stColor[2] ?>
          </span>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const mapa = L.map('mapa').setView([19.4326, -99.1332], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap'
}).addTo(mapa);

const posiciones = <?= json_encode(array_values($posiciones)) ?>;
posiciones.forEach(function(pos) {
  if (!pos.lat_actual || !pos.lng_actual) return;
  L.marker([pos.lat_actual, pos.lng_actual])
    .addTo(mapa)
    .bindPopup('<b>' + pos.repartidor_nombre + '</b><br>Ruta #' + pos.ruta_id);
});

if (posiciones.length === 0) {
  document.getElementById('mapa').style.background = '#F9FAFB';
  document.getElementById('mapa').innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:#9CA3AF;font-size:.875rem">Sin repartidores en movimiento ahora mismo</div>';
}
</script>
