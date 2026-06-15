<?php
// Vista: Tracking GPS en tiempo real (Leaflet.js + OpenStreetMap + Firebase opcional)
$hayTracking   = !empty($tracking) && $tracking['lat_actual'] && $tracking['lng_actual'];
$sucursalLat   = $tracking['sucursal_lat'] ?? null;
$sucursalLng   = $tracking['sucursal_lng'] ?? null;
$estadoPedido  = $pedido['estado'] ?? 'pendiente';
$rutaPolyline  = $pedido['ruta_polyline'] ?? null;
$barraEstados  = [
    'pendiente'      => 0,
    'confirmado'     => 25,
    'en_preparacion' => 50,
    'en_ruta'        => 75,
    'entregado'      => 100,
];
$progreso = $barraEstados[$estadoPedido] ?? 0;
?>

<style>
@keyframes ripple {
  0%   { transform: scale(.7); opacity: .7; }
  100% { transform: scale(2.8); opacity: 0; }
}
@keyframes pulse-dot {
  0%, 100% { transform: scale(1); }
  50%       { transform: scale(1.12); }
}
</style>

<!-- Barra de progreso -->
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:16px 20px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;font-size:.75rem;color:#6B7280;margin-bottom:8px">
    <span style="<?= $progreso >= 0   ? 'color:var(--color-primary);font-weight:700' : '' ?>">Pendiente</span>
    <span style="<?= $progreso >= 25  ? 'color:var(--color-primary);font-weight:700' : '' ?>">Confirmado</span>
    <span style="<?= $progreso >= 50  ? 'color:var(--color-primary);font-weight:700' : '' ?>">En preparación</span>
    <span style="<?= $progreso >= 75  ? 'color:var(--color-primary);font-weight:700' : '' ?>">En ruta</span>
    <span style="<?= $progreso >= 100 ? 'color:var(--color-primary);font-weight:700' : '' ?>">Entregado</span>
  </div>
  <div style="background:#E5E7EB;border-radius:999px;height:8px;overflow:hidden">
    <div style="width:<?= $progreso ?>%;background:var(--color-primary);height:100%;border-radius:999px;transition:width .5s ease"></div>
  </div>

  <div style="display:flex;gap:20px;margin-top:12px;flex-wrap:wrap">
    <?php if (!empty($tracking['repartidor_nombre'])): ?>
    <div style="font-size:.85rem">
      <span style="color:#6B7280">Repartidor: </span>
      <strong><?= htmlspecialchars($tracking['repartidor_nombre']) ?></strong>
    </div>
    <?php endif; ?>
    <div style="font-size:.85rem">
      <span style="color:#6B7280">ETA: </span>
      <strong id="etaDisplay"><?= !empty($tracking['eta_minutos']) ? $tracking['eta_minutos'] . ' min' : '—' ?></strong>
    </div>
    <div style="font-size:.85rem">
      <span style="color:#6B7280">Km: </span>
      <strong id="kmDisplay">—</strong>
    </div>
    <?php if (!empty($tracking['sucursal_nombre'])): ?>
    <div style="font-size:.85rem">
      <span style="color:#6B7280">Destino: </span>
      <strong><?= htmlspecialchars($tracking['sucursal_nombre']) ?></strong>
    </div>
    <?php endif; ?>
    <?php if ($estadoPedido === 'en_ruta'): ?>
    <div style="font-size:.75rem;color:#9CA3AF;margin-left:auto" id="posCount"></div>
    <?php endif; ?>
  </div>
</div>

<!-- Leyenda + controles del mapa -->
<?php if ($estadoPedido === 'en_ruta' || $hayTracking): ?>
<div style="display:flex;gap:12px;flex-wrap:wrap;font-size:.72rem;color:#6B7280;margin-bottom:8px;align-items:center">
  <span><span style="display:inline-block;width:24px;height:3px;background:#3B82F6;vertical-align:middle;border-radius:2px;margin-right:4px"></span>Ruta al destino</span>
  <span><span style="display:inline-block;width:24px;height:3px;background:#C8102E;vertical-align:middle;border-radius:2px;border-bottom:2px dashed #C8102E;margin-right:4px"></span>Recorrido</span>
  <button id="btnSeguir" onclick="toggleSeguir()"
    style="margin-left:auto;padding:5px 12px;border-radius:8px;border:1px solid #D1D5DB;background:#F9FAFB;color:#374151;font-size:.72rem;font-weight:600;cursor:pointer">
    📍 Centrar auto: <span id="seguirLabel">ON</span>
  </button>
  <button onclick="ajustarVista()"
    style="padding:5px 12px;border-radius:8px;border:1px solid #D1D5DB;background:#F9FAFB;color:#374151;font-size:.72rem;font-weight:600;cursor:pointer">
    🗺 Ver todo
  </button>
</div>
<?php endif; ?>

<!-- Estado de conexión -->
<div id="estadoConexion" style="display:none;align-items:center;gap:8px;font-size:.78rem;color:#6B7280;margin-bottom:10px">
  <span id="conexionDot" style="width:8px;height:8px;border-radius:50%;background:#D1D5DB;display:inline-block;transition:background .4s"></span>
  <span id="conexionTxt">Conectando...</span>
</div>

<!-- Mapa Leaflet -->
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px">
  <div id="mapa" style="height:430px;width:100%"></div>
</div>

<!-- Alerta llegada -->
<div id="llegadoAlert" style="display:none;margin-bottom:14px;padding:13px 18px;background:#D1FAE5;border:1px solid #A7F3D0;border-radius:10px;font-size:.9rem;color:#065F46;font-weight:600">
  ✅ El repartidor ha llegado al destino. Esperando confirmación de entrega.
</div>

<!-- Tips / tutorial colapsable -->
<details style="margin-bottom:16px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:12px 16px">
  <summary style="font-size:.82rem;font-weight:700;color:#374151;cursor:pointer;list-style:none;display:flex;align-items:center;gap:6px">
    💡 ¿Cómo usar esta vista?
  </summary>
  <ul style="margin-top:10px;padding-left:0;list-style:none;font-size:.78rem;color:#6B7280;display:flex;flex-direction:column;gap:7px">
    <li>🔍 <strong>Zoom:</strong> Usa los botones <code>+</code> / <code>−</code> del mapa o pellizca en móvil para acercarte o alejarte.</li>
    <li>🗺 <strong>Ver todo:</strong> Pulsa "Ver todo" para encuadrar el recorrido completo en pantalla.</li>
    <li>📍 <strong>Centrar auto:</strong> Cuando está ON, el mapa sigue al repartidor. Desactívalo si quieres explorar sin que se mueva.</li>
    <li>📦 <strong>Marcadores azules numerados:</strong> Son las sucursales de entrega del pedido. Haz clic para ver el nombre.</li>
    <li>🚩 <strong>Bandera verde:</strong> Punto donde inició el viaje.</li>
    <li>🚚 <strong>Ícono rojo animado:</strong> Posición actual del repartidor.</li>
    <li>〰 <strong>Línea roja:</strong> Recorrido realizado (ajustado a calles).</li>
    <li>〰 <strong>Línea azul:</strong> Ruta sugerida al siguiente destino.</li>
  </ul>
</details>

<?php if (!$hayTracking): ?>
<div id="sinTracking" style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:20px;text-align:center;color:#6B7280;font-size:.875rem;margin-bottom:16px">
  <?php if ($estadoPedido === 'entregado'): ?>
    <span style="font-size:1.5rem">✅</span><br>Este pedido ya fue entregado.
  <?php elseif (in_array($estadoPedido, ['pendiente','confirmado'], true)): ?>
    <span style="font-size:1.5rem">⏳</span><br>El rastreo estará disponible cuando el repartidor inicie la entrega.
  <?php else: ?>
    <span style="font-size:1.5rem">📍</span><br>El repartidor aún no ha activado el rastreo GPS. <span style="font-size:.8rem">Se actualizará automáticamente.</span>
  <?php endif; ?>
</div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-top:4px">
  <a href="<?= BASE_URL ?>pedido/detalle/<?= $pedido['id'] ?>"
     style="padding:9px 18px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
    ← Ver detalle
  </a>
</div>

<!-- Leaflet CSS + JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
var BASE_URL  = '<?= BASE_URL ?>';
var pedidoId  = <?= (int)$pedido['id'] ?>;
var destLat   = <?= $sucursalLat ? (float)$sucursalLat : 'null' ?>;
var destLng   = <?= $sucursalLng ? (float)$sucursalLng : 'null' ?>;

var initLat = <?= $hayTracking ? (float)$tracking['lat_actual'] : ($sucursalLat ? (float)$sucursalLat : 19.4326) ?>;
var initLng = <?= $hayTracking ? (float)$tracking['lng_actual'] : ($sucursalLng ? (float)$sucursalLng : -99.1332) ?>;

var autoFollow = true;

function toggleSeguir() {
  autoFollow = !autoFollow;
  document.getElementById('seguirLabel').textContent = autoFollow ? 'ON' : 'OFF';
  document.getElementById('btnSeguir').style.background = autoFollow ? '#DBEAFE' : '#F9FAFB';
}

var mapa = L.map('mapa').setView([initLat, initLng], 15);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
  attribution: '© OpenStreetMap contributors', maxZoom: 19
}).addTo(mapa);

var iconoRepartidor = L.divIcon({
  className: '',
  html: '<div style="position:relative;width:52px;height:52px;display:flex;align-items:center;justify-content:center">'
      + '<div style="position:absolute;inset:0;border-radius:50%;background:#C8102E;opacity:.22;animation:ripple 2s ease-out infinite"></div>'
      + '<div style="position:relative;z-index:1;background:#C8102E;color:#fff;border-radius:50%;width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:18px;border:3px solid #fff;box-shadow:0 2px 10px rgba(0,0,0,.4);animation:pulse-dot 2s ease-in-out infinite">🚚</div>'
      + '</div>',
  iconSize: [52, 52], iconAnchor: [26, 26],
});

function iconoParada(num) {
  return L.divIcon({
    className: '',
    html: '<div style="background:#1E40AF;color:#fff;border-radius:50%;width:30px;height:30px;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.35)">' + num + '</div>',
    iconSize: [30, 30], iconAnchor: [15, 15],
  });
}

var iconoInicio = L.divIcon({
  className: '',
  html: '<div style="background:#059669;color:#fff;border-radius:50%;width:28px;height:28px;display:flex;align-items:center;justify-content:center;font-size:15px;border:2px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,.3)">🚩</div>',
  iconSize: [28, 28], iconAnchor: [14, 28],
});

var marcadorRepartidor = null;
var marcadorInicio     = null;
var posicionesRuta     = [];
var routeLine          = null;   // fallback raw polyline
var snappedLine        = null;   // road-snapped trail (OSRM match)
var routeGuideLine     = null;   // route TO destination (OSRM route)
var lastRouteFetch     = 0;
var rawBuffer          = [];     // points pending a match call
var lastMatchedAt      = 0;
var matchPending       = false;

// Paradas numeradas (sucursales del pedido)
<?php
$sucursalesJS = [];
foreach ($sucursales ?? [] as $i => $s) {
    $sucursalesJS[] = [
        'num'    => $i + 1,
        'nombre' => htmlspecialchars(addslashes($s['sucursal_nombre'] ?? '')),
        'lat'    => $s['lat'] ? (float)$s['lat'] : null,
        'lng'    => $s['lng'] ? (float)$s['lng'] : null,
    ];
}
echo 'var paradas = ' . json_encode($sucursalesJS) . ';' . "\n";
?>
paradas.forEach(function(p) {
  if (!p.lat || !p.lng) return;
  L.marker([p.lat, p.lng], {icon: iconoParada(p.num)})
   .addTo(mapa)
   .bindPopup('📦 Parada ' + p.num + ': ' + p.nombre);
});

// Si el viaje ya terminó, dibujar el recorrido guardado directamente
<?php if ($rutaPolyline): ?>
(function() {
  var storedPts = <?= $rutaPolyline /* JSON array de [lat,lng] */ ?>;
  if (storedPts && storedPts.length >= 2) {
    L.polyline(storedPts, {color:'#C8102E', weight:3, opacity:.75, dashArray:'8,5'}).addTo(mapa);
    var bounds = L.latLngBounds(storedPts);
    paradas.forEach(function(p) { if (p.lat && p.lng) bounds.extend([p.lat, p.lng]); });
    mapa.fitBounds(bounds, {padding:[30,30]});
  }
})();
<?php endif; ?>

// Si ya hay posición conocida al cargar
<?php if ($hayTracking): ?>
marcadorRepartidor = L.marker([<?= (float)$tracking['lat_actual'] ?>, <?= (float)$tracking['lng_actual'] ?>], {icon: iconoRepartidor})
  .addTo(mapa).bindPopup('Repartidor: <?= htmlspecialchars(addslashes($tracking['repartidor_nombre'] ?? '')) ?>');
posicionesRuta.push([<?= (float)$tracking['lat_actual'] ?>, <?= (float)$tracking['lng_actual'] ?>]);
<?php endif; ?>

// ── Distancia total recorrida ─────────────────────────────────────────────────
function calcDistanciaKm(points) {
  var total = 0;
  for (var i = 1; i < points.length; i++) {
    var dLa = (points[i][0] - points[i-1][0]) * Math.PI / 180;
    var dLo = (points[i][1] - points[i-1][1]) * Math.PI / 180;
    var a = Math.sin(dLa/2)*Math.sin(dLa/2)
          + Math.cos(points[i-1][0]*Math.PI/180)*Math.cos(points[i][0]*Math.PI/180)
          * Math.sin(dLo/2)*Math.sin(dLo/2);
    total += 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  }
  return total;
}
function actualizarKm() {
  var el = document.getElementById('kmDisplay');
  if (!el || posicionesRuta.length < 2) return;
  var km = calcDistanciaKm(posicionesRuta);
  el.textContent = km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(2) + ' km';
}

// ── OSRM Map Matching — ajusta el trail a calles reales ──────────────────────
function snapTrail(points) {
  if (points.length < 6 || matchPending) { drawRawTrail(); return; }
  var distKm = calcDistanciaKm(points);
  if (distKm < 0.2) { drawRawTrail(); return; }
  var pts = points;
  if (pts.length > 100) {
    var step = Math.floor(pts.length / 99);
    var sampled = [];
    for (var i = 0; i < pts.length - 1; i += step) sampled.push(pts[i]);
    sampled.push(pts[pts.length - 1]);
    pts = sampled;
  }
  var coords = pts.map(function(p) { return p[1] + ',' + p[0]; }).join(';');
  matchPending = true;
  fetch('https://router.project-osrm.org/match/v1/driving/' + coords
      + '?overview=full&geometries=geojson&tidy=true')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      matchPending = false;
      if (!data.matchings || !data.matchings.length) { drawRawTrail(); return; }
      var matched = [];
      data.matchings.forEach(function(m) {
        m.geometry.coordinates.forEach(function(c) { matched.push([c[1], c[0]]); });
      });
      if (!matched.length) { drawRawTrail(); return; }
      if (snappedLine) {
        snappedLine.setLatLngs(matched);
      } else {
        snappedLine = L.polyline(matched, {color: '#C8102E', weight: 3, opacity: .75, dashArray: '8,5'}).addTo(mapa);
        if (routeLine) { mapa.removeLayer(routeLine); routeLine = null; }
      }
      rawBuffer = [];
      lastMatchedAt = Date.now();
    })
    .catch(function() { matchPending = false; drawRawTrail(); });
}

function drawRawTrail() {
  if (posicionesRuta.length < 2) return;
  if (routeLine) {
    routeLine.setLatLngs(posicionesRuta);
  } else {
    routeLine = L.polyline(posicionesRuta, {color: '#C8102E', weight: 2, opacity: .5, dashArray: '6,4'}).addTo(mapa);
  }
}

function ajustarVista() {
  var pts = posicionesRuta.slice();
  paradas.forEach(function(p) { if (p.lat && p.lng) pts.push([p.lat, p.lng]); });
  if (pts.length) mapa.fitBounds(L.latLngBounds(pts), {padding: [30, 30]});
}

// Pre-cargar historial de posiciones para mostrar el recorrido completo
fetch(BASE_URL + 'api/historialTracking/' + pedidoId)
  .then(function(r) { return r.json(); })
  .then(function(pts) {
    if (!pts || !pts.length) return;
    var hist = [];
    pts.forEach(function(p) {
      if (p.lat && p.lng) hist.push([parseFloat(p.lat), parseFloat(p.lng)]);
    });
    if (!hist.length) return;
    if (posicionesRuta.length === 0) {
      posicionesRuta = hist.slice();
      if (hist.length >= 6 && calcDistanciaKm(hist) >= 0.2) {
        snapTrail(hist); lastMatchedAt = Date.now();
      } else if (hist.length >= 2) {
        drawRawTrail();
      }
      if (!marcadorRepartidor) {
        var last = posicionesRuta[posicionesRuta.length - 1];
        marcadorRepartidor = L.marker(last, {icon: iconoRepartidor}).addTo(mapa).bindPopup('🚚 Última posición registrada');
        if (autoFollow) mapa.setView(last, 15);
        var st = document.getElementById('sinTracking');
        if (st) st.style.display = 'none';
      }
    }
    // Marcador de inicio del viaje
    if (hist.length && !marcadorInicio) {
      marcadorInicio = L.marker(hist[0], {icon: iconoInicio, zIndexOffset: -10})
        .addTo(mapa).bindPopup('🚩 Inicio del viaje');
    }
    actualizarKm();
    var cntEl = document.getElementById('posCount');
    if (cntEl) cntEl.textContent = pts.length + ' pts registrados';
  })
  .catch(function() {});

// ── Ruta de conducción al destino (OSRM /route, throttle 30 s) ───────────────
function actualizarRutaOSRM(lat, lng) {
  if (!destLat || !destLng) return;
  var now = Date.now();
  if (now - lastRouteFetch < 30000) return;
  lastRouteFetch = now;
  fetch('https://router.project-osrm.org/route/v1/driving/' + lng + ',' + lat
      + ';' + destLng + ',' + destLat + '?overview=full&geometries=geojson')
    .then(function(r) { return r.json(); })
    .then(function(data) {
      if (!data.routes || !data.routes[0]) return;
      var coords = data.routes[0].geometry.coordinates.map(function(c) { return [c[1], c[0]]; });
      if (routeGuideLine) {
        routeGuideLine.setLatLngs(coords);
      } else {
        routeGuideLine = L.polyline(coords, {color: '#3B82F6', weight: 4, opacity: .65}).addTo(mapa);
        routeGuideLine.bringToBack();
      }
      var durMin = Math.round(data.routes[0].duration / 60);
      var etaEl = document.getElementById('etaDisplay');
      if (etaEl && durMin >= 0) etaEl.textContent = durMin + ' min';
    })
    .catch(function() {});
}

function actualizarPosicion(lat, lng) {
  var pos = [lat, lng];
  posicionesRuta.push(pos);
  rawBuffer.push(pos);

  if (marcadorRepartidor) {
    marcadorRepartidor.setLatLng(pos);
    if (autoFollow) mapa.panTo(pos);
  } else {
    marcadorRepartidor = L.marker(pos, {icon: iconoRepartidor})
      .addTo(mapa).bindPopup('🚚 Repartidor en camino').openPopup();
    if (autoFollow) mapa.panTo(pos);
  }

  // Marcador inicio del viaje (primera posición recibida en vivo)
  if (!marcadorInicio) {
    marcadorInicio = L.marker(pos, {icon: iconoInicio, zIndexOffset: -10})
      .addTo(mapa).bindPopup('🚩 Inicio del viaje');
  }

  var now = Date.now();
  var shouldSnap = rawBuffer.length >= 5 || (rawBuffer.length >= 2 && now - lastMatchedAt > 45000);
  if (shouldSnap) {
    snapTrail(posicionesRuta);
  } else {
    drawRawTrail();
  }

  actualizarKm();

  var st = document.getElementById('sinTracking');
  if (st) st.style.display = 'none';
  actualizarRutaOSRM(lat, lng);
}

function setConexion(ok, txt) {
  var c = document.getElementById('estadoConexion');
  var d = document.getElementById('conexionDot');
  var t = document.getElementById('conexionTxt');
  if (!c) return;
  c.style.display = 'flex';
  d.style.background = ok ? '#10B981' : '#F59E0B';
  t.textContent = txt;
}
</script>

<?php if (!empty($firebaseActivo)): ?>
<script type="module">
  import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-app.js';
  import { getDatabase, ref, onValue } from 'https://www.gstatic.com/firebasejs/10.12.0/firebase-database.js';

  const app = initializeApp(<?= json_encode($firebaseConfig) ?>);
  const db  = getDatabase(app);

  setConexion(true, 'Conectado — actualizando en tiempo real');

  onValue(ref(db, 'tracking/<?= (int)$pedido['id'] ?>'), snap => {
    const d = snap.val();
    if (!d || !d.lat || !d.lng) return;
    actualizarPosicion(d.lat, d.lng);
    setConexion(true, 'Última actualización: ' + new Date().toLocaleTimeString());
    if (d.llegado) {
      const al = document.getElementById('llegadoAlert');
      if (al) al.style.display = 'block';
    }
  });
</script>
<?php else: ?>
<script>
<?php if ($estadoPedido === 'en_ruta'): ?>
setConexion(true, 'Actualizando cada 5 segundos...');
function pollingTracking() {
  fetch(BASE_URL + 'api/tracking/' + pedidoId)
    .then(r => r.json())
    .then(d => {
      if (!d.lat || !d.lng) return;
      actualizarPosicion(d.lat, d.lng);
      setConexion(true, 'Actualizado: ' + new Date().toLocaleTimeString());
      if (d.estado === 'entregado') { clearInterval(pollingInterval); location.reload(); }
    }).catch(() => setConexion(false, 'Sin señal — reintentando...'));
}
var pollingInterval = setInterval(pollingTracking, 5000);
<?php endif; ?>
</script>
<?php endif; ?>
