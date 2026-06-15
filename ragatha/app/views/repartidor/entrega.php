<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Registrar entrega — CarniHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; }
    body { background: #111827; color: #F9FAFB; font-family: 'Inter', sans-serif; min-height: 100vh; margin: 0; }
    .header { background: #1F2937; padding: 14px 16px; display: flex; align-items: center; gap: 12px; border-bottom: 1px solid #374151; }
    .form-group { margin-bottom: 16px; }
    label { display: block; font-size: .8rem; font-weight: 600; color: #D1D5DB; margin-bottom: 6px; }
    input[type=text] { width: 100%; background: #374151; border: 1px solid #4B5563; border-radius: 8px; padding: 12px; color: #F9FAFB; font-size: .9rem; }
    canvas { width: 100%; border: 2px solid #4B5563; border-radius: 8px; background: #1F2937; touch-action: none; }
    .btn-primary { background: #C8102E; color: #fff; padding: 14px; border: none; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; width: 100%; }
    .btn-clear { background: #374151; color: #F9FAFB; padding: 10px; border: none; border-radius: 8px; font-size: .85rem; font-weight: 600; cursor: pointer; width: 100%; margin-top: 8px; }
  </style>
</head>
<body>

<div class="header">
  <a href="<?= BASE_URL ?>repartidor/inicio" style="color:#9CA3AF;text-decoration:none;font-size:1.4rem">&larr;</a>
  <div>
    <div style="font-weight:800;font-size:.95rem">Registrar entrega</div>
    <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($parada['sucursal_nombre']) ?></div>
  </div>
</div>

<div style="padding:16px">
  <!-- Info de la parada -->
  <div style="background:#1F2937;border-radius:10px;padding:14px;margin-bottom:20px">
    <div style="font-weight:700;margin-bottom:4px"><?= htmlspecialchars($parada['empresa_nombre']) ?></div>
    <div style="font-size:.8rem;color:#9CA3AF">📍 <?= htmlspecialchars($parada['direccion']) ?></div>
    <div style="font-size:.8rem;color:#9CA3AF;margin-top:4px">Pedido: <span style="color:#F9FAFB;font-weight:600"><?= htmlspecialchars($parada['folio']) ?></span></div>
    <?php if ($parada['notas']): ?>
    <div style="margin-top:8px;background:#111827;border-radius:6px;padding:8px;font-size:.8rem;color:#FCD34D">
      Notas: <?= htmlspecialchars($parada['notas']) ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- GPS tracking: iniciar al llegar -->
  <div id="gpsStatus" style="background:#064E3B;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:.8rem;color:#6EE7B7;display:none">
    GPS activo — enviando ubicación...
  </div>

  <form method="POST" action="<?= BASE_URL ?>repartidor/confirmarEntrega/<?= $parada['id'] ?>" enctype="multipart/form-data" id="entregaForm">

    <!-- Nombre de quien recibe -->
    <div class="form-group">
      <label>Nombre de quien recibe *</label>
      <input type="text" name="nombre_receptor" placeholder="Ej: Juan García" required>
    </div>

    <!-- Firma digital -->
    <div class="form-group">
      <label>Firma del receptor *</label>
      <canvas id="firmaCanvas" height="150"></canvas>
      <button type="button" class="btn-clear" onclick="limpiarFirma()">Borrar firma</button>
      <input type="hidden" name="firma_data" id="firmaData">
    </div>

    <!-- Foto de evidencia -->
    <div class="form-group">
      <label>Foto de evidencia (opcional)</label>
      <input type="file" name="foto" accept="image/*" capture="environment"
             style="background:#374151;border:1px solid #4B5563;border-radius:8px;padding:10px;width:100%;color:#F9FAFB;font-size:.85rem">
    </div>

    <button type="submit" class="btn-primary" onclick="return prepararEnvio()">
      Confirmar entrega
    </button>
  </form>
</div>

<script>
// ── Firma digital ──────────────────────────────────────────────
const canvas  = document.getElementById('firmaCanvas');
// Sincronizar tamaño interno con el tamaño renderizado real;
// sin esto canvas.width es 300px aunque se muestre más ancho,
// haciendo que getImageData devuelva una franja vacía.
canvas.width  = canvas.offsetWidth  || 300;
canvas.height = canvas.offsetHeight || 150;
const ctx     = canvas.getContext('2d');
let dibujando = false;

function getPos(e) {
  const r = canvas.getBoundingClientRect();
  const src = e.touches ? e.touches[0] : e;
  return { x: (src.clientX - r.left) * (canvas.width / r.width),
           y: (src.clientY - r.top)  * (canvas.height / r.height) };
}

canvas.addEventListener('mousedown',  e => { dibujando = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); });
canvas.addEventListener('mousemove',  e => { if (!dibujando) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#F9FAFB'; ctx.lineWidth = 2; ctx.stroke(); });
canvas.addEventListener('mouseup',    () => dibujando = false);
canvas.addEventListener('touchstart', e => { e.preventDefault(); dibujando = true; const p = getPos(e); ctx.beginPath(); ctx.moveTo(p.x, p.y); }, { passive: false });
canvas.addEventListener('touchmove',  e => { e.preventDefault(); if (!dibujando) return; const p = getPos(e); ctx.lineTo(p.x, p.y); ctx.strokeStyle = '#F9FAFB'; ctx.lineWidth = 2; ctx.stroke(); }, { passive: false });
canvas.addEventListener('touchend',   () => dibujando = false);

function limpiarFirma() {
  ctx.clearRect(0, 0, canvas.width, canvas.height);
}

function prepararEnvio() {
  const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const vacio   = !imgData.data.some(c => c !== 0);
  if (vacio) { alert('Por favor dibuja la firma del receptor.'); return false; }
  // Exportar negro sobre blanco para que sea legible en cualquier contexto
  // (pestaña nueva, ZIP, PDF). El canvas visual del repartidor no cambia.
  const tmp    = document.createElement('canvas');
  tmp.width    = canvas.width;
  tmp.height   = canvas.height;
  const tmpCtx = tmp.getContext('2d');
  tmpCtx.fillStyle = '#FFFFFF';
  tmpCtx.fillRect(0, 0, tmp.width, tmp.height);
  const d = imgData.data;
  for (let i = 0; i < d.length; i += 4) {
    if (d[i + 3] > 10) {        // píxel con trazo → invertir color
      d[i]     = 255 - d[i];
      d[i + 1] = 255 - d[i + 1];
      d[i + 2] = 255 - d[i + 2];
      d[i + 3] = 255;           // forzar opaco
    } else {
      d[i + 3] = 0;             // vacío → transparente (cubierto por fondo blanco)
    }
  }
  tmpCtx.putImageData(imgData, 0, 0);
  document.getElementById('firmaData').value = tmp.toDataURL('image/png');
  return true;
}

// ── GPS tracking ──────────────────────────────────────────────
if (navigator.geolocation) {
  navigator.geolocation.watchPosition(function(pos) {
    document.getElementById('gpsStatus').style.display = 'block';
    fetch('<?= BASE_URL ?>api/actualizarTracking', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ruta_detalle_id: <?= (int)$parada['id'] ?>,
        lat: pos.coords.latitude,
        lng: pos.coords.longitude
      })
    });
  }, null, { enableHighAccuracy: true, maximumAge: 30000, timeout: 10000 });
}
</script>
</body>
</html>
