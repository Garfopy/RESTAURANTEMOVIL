<?php
// Variables: $rutasActivas[], $posiciones[]
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;height:calc(100vh - 120px)">

  <!-- Mapa global -->
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
    <div style="padding:14px 18px;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center">
      <h3 style="margin:0;font-size:.95rem;font-weight:700;color:#111827">Mapa de repartidores activos</h3>
      <span style="font-size:.8rem;color:#6B7280"><?= count($posiciones) ?> en ruta</span>
    </div>
    <div id="mapa" style="height:calc(100% - 53px)"></div>
  </div>

  <!-- Panel lateral -->
  <div style="display:flex;flex-direction:column;gap:14px;overflow-y:auto">

    <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
        <h3 style="margin:0;font-size:.95rem;font-weight:700;color:#111827">Rutas activas</h3>
        <a href="<?= BASE_URL ?>panel-logistica/nuevaRuta"
           style="padding:5px 14px;background:var(--color-primary);color:#fff;border-radius:6px;font-size:.8rem;text-decoration:none;font-weight:600">
          + Nueva ruta
        </a>
      </div>

      <?php if (empty($rutasActivas)): ?>
      <p style="font-size:.875rem;color:#9CA3AF;text-align:center;padding:20px 0">No hay rutas activas.</p>
      <?php endif; ?>

      <?php foreach ($rutasActivas as $ruta): ?>
      <div style="border:1px solid #E5E7EB;border-radius:8px;padding:12px;margin-bottom:10px">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
          <div>
            <div style="font-weight:700;font-size:.875rem;color:#111827">
              <?= htmlspecialchars($ruta['repartidor_nombre'] . ' ' . $ruta['repartidor_apellido']) ?>
            </div>
            <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($ruta['empresa_nombre']) ?></div>
          </div>
          <?php
          $eBadge = match($ruta['estado']) {
              'en_ruta'   => '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:600">En ruta</span>',
              'pendiente' => '<span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:999px;font-size:.7rem;font-weight:600">Pendiente</span>',
              default     => '<span style="background:#F3F4F6;color:#6B7280;padding:2px 8px;border-radius:999px;font-size:.7rem">' . $ruta['estado'] . '</span>',
          };
          echo $eBadge;
          ?>
        </div>
        <div style="font-size:.75rem;color:#6B7280">
          <?= date('d/m/Y', strtotime($ruta['fecha'])) ?> ·
          <?= (int)$ruta['entregadas'] ?>/<?= (int)$ruta['total_paradas'] ?> paradas entregadas
        </div>
        <div style="margin-top:8px;background:#E5E7EB;border-radius:999px;height:4px">
          <?php
          $pct = $ruta['total_paradas'] > 0
              ? round(($ruta['entregadas'] / $ruta['total_paradas']) * 100)
              : 0;
          ?>
          <div style="background:var(--color-primary);height:4px;border-radius:999px;width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<script>
const mapa = L.map('mapa').setView([23.6345, -102.5528], 5);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap'
}).addTo(mapa);

const posiciones = <?= json_encode($posiciones) ?>;

posiciones.forEach(pos => {
  if (!pos.lat_actual || !pos.lng_actual) return;
  L.marker([pos.lat_actual, pos.lng_actual])
   .addTo(mapa)
   .bindPopup(`<strong>${pos.repartidor_nombre}</strong><br>Ruta #${pos.ruta_id}`);
});

if (posiciones.length > 0) {
  const first = posiciones.find(p => p.lat_actual);
  if (first) mapa.setView([first.lat_actual, first.lng_actual], 12);
}
</script>
