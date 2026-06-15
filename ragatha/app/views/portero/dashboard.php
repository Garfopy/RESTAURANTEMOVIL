<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Portero — <?= htmlspecialchars($restaurante['nombre'] ?? '') ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    body { background: #111827; color: #F9FAFB; font-family: system-ui, sans-serif; min-height: 100vh; }
    .topbar { background: #1F2937; border-bottom: 1px solid #374151; padding: 14px 20px; display: flex; justify-content: space-between; align-items: center; }
    .result-box { border-radius: 16px; padding: 28px; text-align: center; font-size: 1.4rem; font-weight: 700; margin-top: 16px; display: none; }
    .result-ok  { background: #064E3B; border: 2px solid #10B981; color: #6EE7B7; }
    .result-err { background: #7F1D1D; border: 2px solid #EF4444; color: #FCA5A5; }
  </style>
</head>
<body>
<div class="topbar">
  <div style="font-weight:700">🚪 Portero — <?= htmlspecialchars($restaurante['nombre'] ?? '') ?></div>
  <a href="<?= BASE_URL ?>auth/logoutStaff/portero" style="color:#9CA3AF;font-size:.8rem">Salir</a>
</div>

<div style="padding:28px;max-width:480px;margin:0 auto">

  <!-- Scanner manual -->
  <div style="background:#1F2937;border-radius:16px;padding:24px;margin-bottom:20px">
    <div style="font-weight:600;font-size:1rem;margin-bottom:14px">Verificar código de visita</div>
    <form id="formVerificar" style="display:flex;gap:10px">
      <input type="text" id="qrInput" placeholder="Escanear o ingresar código QR..."
        style="flex:1;padding:12px 14px;border:1px solid #374151;border-radius:10px;background:#111827;color:#F9FAFB;font-size:1rem"
        autofocus>
      <button type="submit"
        style="padding:10px 18px;background:#C8102E;color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:600;cursor:pointer">
        Verificar
      </button>
    </form>
    <div id="resultBox" class="result-box"></div>
  </div>

  <!-- Cámara QR (opcional) -->
  <div style="background:#1F2937;border-radius:16px;padding:24px;margin-bottom:20px">
    <div style="font-weight:600;margin-bottom:12px">Escanear QR con cámara</div>
    <video id="video" playsinline muted autoplay style="width:100%;border-radius:10px;display:none;max-height:280px;object-fit:cover"></video>
    <canvas id="canvas" style="display:none"></canvas>
    <button id="btnCam"
      style="width:100%;padding:10px;background:#374151;color:#F9FAFB;border:none;border-radius:10px;cursor:pointer;font-size:.9rem">
      📷 Activar cámara
    </button>
  </div>

</div>

<script src="<?= BASE_URL ?>public/js/jsQR.min.js"></script>
<script>
const baseUrl = '<?= BASE_URL ?>';
const video   = document.getElementById('video');
const canvas  = document.getElementById('canvas');
const ctx     = canvas.getContext('2d');

document.getElementById('formVerificar').addEventListener('submit', async e => {
  e.preventDefault();
  const qr  = document.getElementById('qrInput').value.trim();
  if (!qr) return;
  await verificar(qr);
});

let lastQr = '';

async function verificar(qr) {
  const fd = new FormData();
  fd.append('qr_code', qr);
  const res  = await fetch(baseUrl + 'rest-portero/verificar', { method: 'POST', body: fd });
  const data = await res.json();
  const box  = document.getElementById('resultBox');
  document.getElementById('qrInput').value = '';

  if (!data.ok) {
    box.className = 'result-box result-err';
    box.innerHTML = '<div style="font-size:2rem;margin-bottom:8px">⚠️</div><div>' + (data.mensaje || 'QR no reconocido') + '</div>';
    box.style.display = 'block';
    return;
  }

  lastQr = qr;

  if (data.pagado && data.ya_salio) {
    box.className = 'result-box';
    box.style.cssText = box.style.cssText;
    box.style.background = '#1F2937';
    box.style.border = '2px solid #6B7280';
    box.style.color = '#9CA3AF';
    box.style.display = 'block';
    box.innerHTML =
      '<div style="font-size:2rem;margin-bottom:8px">🚪</div>' +
      '<div style="font-size:1.15rem;font-weight:700;margin-bottom:8px">SALIDA YA REGISTRADA</div>' +
      '<div style="font-size:.85rem">Mesa: ' + data.mesa + '</div>';
    return;
  }

  if (data.pagado) {
    const propText = data.propina > 0
      ? '<div style="font-size:.85rem;color:#A7F3D0;margin-bottom:20px">Propina incluida: $' + data.propina.toFixed(2) + '</div>'
      : '<div style="margin-bottom:20px"></div>';
    box.className = 'result-box result-ok';
    box.style.background = '';
    box.style.border = '';
    box.style.color = '';
    box.style.display = 'block';
    box.innerHTML =
      '<div style="font-size:2.5rem;margin-bottom:8px">✅</div>' +
      '<div style="font-size:1.3rem;margin-bottom:6px">CUENTA PAGADA</div>' +
      '<div style="font-size:.88rem;font-weight:400;color:#A7F3D0;margin-bottom:14px">Mesa: ' + data.mesa + ' &nbsp;·&nbsp; ' + data.comensal + '</div>' +
      '<div style="font-size:1.15rem;font-weight:700;margin-bottom:4px">Total: $' + data.total.toFixed(2) + '</div>' +
      propText +
      '<button onclick="registrarSalida()" style="width:100%;padding:12px;background:#10B981;color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer">🚪 Registrar salida</button>';
    return;
  }

  // No pagado
  box.className = 'result-box result-err';
  box.style.background = '';
  box.style.border = '';
  box.style.color = '';
  box.style.display = 'block';
  box.innerHTML =
    '<div style="font-size:2.5rem;margin-bottom:8px">❌</div>' +
    '<div style="font-size:1.3rem;margin-bottom:10px">PAGO PENDIENTE</div>' +
    '<div style="font-size:1.1rem;font-weight:700">$' + data.total.toFixed(2) + '</div>' +
    '<div style="font-size:.85rem;color:#FCA5A5;margin-top:6px">El comensal debe pagar antes de salir.</div>';
}

async function registrarSalida() {
  if (!lastQr) return;
  const btn = document.querySelector('#resultBox button');
  if (btn) { btn.disabled = true; btn.textContent = 'Registrando...'; }
  const fd = new FormData();
  fd.append('qr_code', lastQr);
  const res  = await fetch(baseUrl + 'rest-portero/registrarSalida', { method: 'POST', body: fd });
  const data = await res.json();
  const box  = document.getElementById('resultBox');
  if (data.ok) {
    box.innerHTML =
      '<div style="font-size:2.5rem;margin-bottom:8px">🚪</div>' +
      '<div style="font-size:1.3rem">SALIDA REGISTRADA</div>' +
      '<div style="font-size:.85rem;color:#A7F3D0;margin-top:8px">¡Hasta pronto!</div>';
    lastQr = '';
  } else {
    if (btn) { btn.disabled = false; btn.textContent = '🚪 Registrar salida'; }
  }
}

// Cámara QR
let scanning = false;
let stream   = null;
let lastScan = 0;

document.getElementById('btnCam').addEventListener('click', async () => {
  const btn = document.getElementById('btnCam');
  if (scanning) {
    scanning = false;
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    video.srcObject = null;
    video.style.display = 'none';
    btn.textContent = '📷 Activar cámara';
    return;
  }
  if (typeof jsQR !== 'function') {
    alert('Librería de QR no cargada. Revisa tu conexión.');
    return;
  }
  try {
    stream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: { ideal: 'environment' } },
      audio: false
    });
    video.srcObject = stream;
    video.setAttribute('playsinline', 'true');
    video.muted = true;
    await video.play();
    video.style.display = 'block';
    scanning = true;
    btn.textContent = '⏹ Detener cámara';
    requestAnimationFrame(scan);
  } catch (err) {
    console.error('getUserMedia error:', err);
    alert('No se pudo acceder a la cámara: ' + (err.message || err.name));
  }
});

function scan() {
  if (!scanning) return;
  if (video.readyState >= video.HAVE_ENOUGH_DATA && video.videoWidth > 0) {
    canvas.width  = video.videoWidth;
    canvas.height = video.videoHeight;
    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
    const img  = ctx.getImageData(0, 0, canvas.width, canvas.height);
    const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
    if (code && code.data) {
      const now = Date.now();
      if (code.data !== lastQr || now - lastScan > 3000) {
        lastScan = now;
        verificar(code.data);
      }
    }
  }
  requestAnimationFrame(scan);
}
</script>
</body>
</html>
