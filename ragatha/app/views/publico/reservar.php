<?php
/**
 * Página pública de reservaciones — accesible vía QR sin login.
 *
 * @var array       $restaurante
 * @var string      $pageTitle
 * @var bool        $ok       true cuando la reservación se guardó con éxito
 * @var array|null  $flash
 * @var int         $reservaId
 */
$color  = htmlspecialchars($restaurante['color_primario'] ?? '#C8102E');
$nombre = htmlspecialchars($restaurante['nombre'] ?? 'el restaurante');
$logo   = $restaurante['logo'] ?? '';
$slug   = htmlspecialchars($restaurante['slug'] ?? '');
$habilitadas = !empty($restaurante['reservas_habilitadas']);

$flashMsg  = $flash['message'] ?? null;
$flashType = $flash['type']    ?? 'info';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <style>
    :root { --cp: <?= $color ?>; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #F3F4F6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .topbar {
      width: 100%; background: #fff; border-bottom: 1px solid #E5E7EB;
      display: flex; align-items: center; gap: 12px; padding: 14px 20px;
    }
    .topbar img  { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; }
    .topbar-name { font-weight: 700; font-size: 1rem; color: #111827; }
    .card {
      background: #fff; border-radius: 16px; box-shadow: 0 2px 12px rgba(0,0,0,.07);
      padding: 28px 24px; margin: 24px 16px; width: 100%; max-width: 480px;
    }
    .card-title { font-size: 1.15rem; font-weight: 700; color: #111827; margin-bottom: 4px; }
    .card-sub   { font-size: .82rem; color: #6B7280; margin-bottom: 22px; }
    label {
      display: block; font-size: .82rem; font-weight: 600; color: #374151; margin-bottom: 6px;
    }
    input, select, textarea {
      width: 100%; padding: 10px 12px; border: 1px solid #D1D5DB; border-radius: 10px;
      font-size: .9rem; color: #111827; background: #fff; outline: none;
      transition: border-color .15s; margin-bottom: 14px; font-family: inherit;
    }
    input:focus, select:focus, textarea:focus { border-color: var(--cp); }
    textarea { resize: vertical; min-height: 72px; }
    .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .btn-submit {
      width: 100%; padding: 13px; background: var(--cp); color: #fff; border: none;
      border-radius: 12px; font-size: 1rem; font-weight: 700; cursor: pointer;
      margin-top: 6px; transition: opacity .15s;
    }
    .btn-submit:disabled { opacity: .5; cursor: not-allowed; }
    .btn-submit:active:not(:disabled) { opacity: .85; }
    .alert { border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: .85rem; font-weight: 500; }
    .alert-error   { background: #FEE2E2; color: #991B1B; }
    .alert-success { background: #DCFCE7; color: #166534; }
    .success-box   { text-align: center; padding: 12px 0 4px; }
    .success-icon  { font-size: 3rem; margin-bottom: 10px; }
    .success-title { font-size: 1.2rem; font-weight: 700; color: #111827; margin-bottom: 6px; }
    .success-sub   { font-size: .85rem; color: #6B7280; margin-bottom: 20px; }
    .btn-otra {
      display: inline-block; padding: 10px 22px; border: 2px solid var(--cp);
      color: var(--cp); border-radius: 10px; font-size: .88rem; font-weight: 600;
      text-decoration: none; cursor: pointer; background: transparent;
    }
    .disabled-box { text-align: center; padding: 20px 0; color: #6B7280; font-size: .9rem; }
    .required-star { color: var(--cp); }

    /* ── Bloque de selección de mesa ── */
    .mesa-section { margin-top: 4px; margin-bottom: 14px; }
    .mesa-header  { display:flex; align-items:center; justify-content:space-between; margin-bottom:8px; }
    .mesa-info    { font-size:.78rem; color:#6B7280; }
    .mesa-list    { display:grid; grid-template-columns: repeat(2, 1fr); gap:8px; }
    .mesa-opt {
      border: 2px solid #E5E7EB; border-radius: 10px; padding: 10px 12px;
      cursor: pointer; background: #fff; text-align: left;
      transition: border-color .15s, background .15s;
    }
    .mesa-opt:hover { border-color: var(--cp); }
    .mesa-opt.selected {
      border-color: var(--cp);
      background: color-mix(in srgb, var(--cp) 8%, #fff);
    }
    .mesa-opt .nm { font-weight: 700; font-size: .9rem; color:#111827; }
    .mesa-opt .cap { font-size: .76rem; color: #6B7280; margin-top: 2px; }
    .mesa-state {
      font-size: .82rem; padding: 14px; border-radius: 10px;
      background: #F9FAFB; color: #6B7280; text-align: center; margin-bottom: 14px;
    }
    .mesa-state.empty { background: #FEF3C7; color: #92400E; }
    .mesa-state.loading::before {
      content:''; display:inline-block; width:14px; height:14px;
      border:2px solid #D1D5DB; border-top-color: var(--cp);
      border-radius:50%; animation: spin .8s linear infinite; vertical-align:-3px; margin-right:8px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

<div class="topbar">
  <?php if ($logo): ?>
    <img src="<?= htmlspecialchars($logo) ?>" alt="logo">
  <?php endif; ?>
  <span class="topbar-name"><?= $nombre ?></span>
</div>

<div class="card">

  <?php if (!$habilitadas): ?>
    <div class="disabled-box">
      <div style="font-size:2rem;margin-bottom:10px">🚫</div>
      <div style="font-weight:700;font-size:1rem;color:#111827;margin-bottom:6px">Reservaciones no disponibles</div>
      <div>Este restaurante no acepta reservaciones en este momento.</div>
    </div>

  <?php elseif ($ok): ?>
    <div class="success-box">
      <div class="success-icon">🎉</div>
      <div class="success-title">¡Reservación recibida!</div>
      <div class="success-sub">
        Te enviamos un correo de confirmación con los detalles.<br>
        ¡Te esperamos pronto!
      </div>
      <a href="<?= BASE_URL ?>menu/<?= $slug ?>/reservar" class="btn-otra">Hacer otra reservación</a>
      <?php if (!empty($reservaId)): ?>
        <div style="margin-top:14px">
          <a href="<?= BASE_URL ?>menu/<?= $slug ?>/cancelarReserva/<?= (int)$reservaId ?>"
             style="font-size:.8rem;color:#6B7280;text-decoration:underline">
            ¿Necesitas cancelar? Haz clic aquí
          </a>
        </div>
      <?php endif; ?>
    </div>

  <?php else: ?>
    <div class="card-title">📅 Reserva tu mesa</div>
    <div class="card-sub">en <?= $nombre ?></div>

    <?php if ($flashMsg): ?>
      <div class="alert alert-<?= $flashType === 'error' ? 'error' : 'success' ?>">
        <?= htmlspecialchars($flashMsg) ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>menu/<?= $slug ?>/guardarReserva" id="formReserva">

      <label>Nombre <span class="required-star">*</span></label>
      <input type="text" name="nombre" placeholder="Tu nombre completo" required autocomplete="name">

      <label>Teléfono <span class="required-star">*</span></label>
      <input type="tel" name="telefono" id="fTel" placeholder="10 dígitos" required
             inputmode="numeric" pattern="\d{10}" maxlength="10" minlength="10"
             title="Debe contener exactamente 10 dígitos numéricos" autocomplete="tel">

      <label>Correo electrónico <span class="required-star">*</span></label>
      <input type="email" name="email" id="fEmail" placeholder="tu@email.com" required
             pattern="[^\s@]+@[^\s@]+\.[^\s@]+"
             title="Ingresa un correo válido (ej: tu@email.com)" autocomplete="email">
      <div style="font-size:.72rem;color:#6B7280;margin-top:-10px;margin-bottom:14px">
        Te enviaremos la confirmación y un recordatorio el día antes.
      </div>

      <div class="row2">
        <div>
          <label>Fecha <span class="required-star">*</span></label>
          <input type="date" name="fecha" id="fFecha" required min="<?= date('Y-m-d') ?>">
        </div>
        <div>
          <label>Hora <span class="required-star">*</span></label>
          <input type="time" name="hora" id="fHora" required>
        </div>
      </div>

      <label>Número de personas <span class="required-star">*</span></label>
      <select name="personas" id="fPersonas">
        <?php for ($i = 1; $i <= 20; $i++): ?>
          <option value="<?= $i ?>" <?= $i === 2 ? 'selected' : '' ?>><?= $i ?> persona<?= $i > 1 ? 's' : '' ?></option>
        <?php endfor; ?>
      </select>

      <!-- Selección de mesa (dinámico) -->
      <div class="mesa-section">
        <div class="mesa-header">
          <label style="margin-bottom:0">Mesa disponible <span class="required-star">*</span></label>
          <span class="mesa-info" id="mesaInfo"></span>
        </div>
        <div id="mesaContainer" class="mesa-state">
          Elige fecha, hora y personas para ver mesas disponibles.
        </div>
        <input type="hidden" name="mesa_id" id="fMesaId" value="">
      </div>

      <label>Notas <span style="font-weight:400;color:#9CA3AF">(opcional)</span></label>
      <textarea name="notas" placeholder="Alergias, ocasión especial, preferencias de mesa…"></textarea>

      <button type="submit" class="btn-submit" id="btnSubmit" disabled>Selecciona una mesa</button>
    </form>
  <?php endif; ?>

</div>

<?php if ($habilitadas && !$ok): ?>
<script>
(function() {
  const fFecha    = document.getElementById('fFecha');
  const fHora     = document.getElementById('fHora');
  const fPersonas = document.getElementById('fPersonas');
  const fMesaId   = document.getElementById('fMesaId');
  const cont      = document.getElementById('mesaContainer');
  const info      = document.getElementById('mesaInfo');
  const btn       = document.getElementById('btnSubmit');
  const baseURL   = <?= json_encode(BASE_URL . 'menu/' . $slug . '/mesasDisponibles') ?>;

  let timer = null;

  function setBtn(enabled, txt) {
    btn.disabled = !enabled;
    btn.textContent = txt;
  }

  function clearMesas(msg, cls = '') {
    cont.className = 'mesa-state' + (cls ? ' ' + cls : '');
    cont.innerHTML = msg;
    fMesaId.value = '';
    info.textContent = '';
    setBtn(false, 'Selecciona una mesa');
  }

  function renderMesas(mesas, personas) {
    if (mesas.length === 0) {
      clearMesas('No hay mesas disponibles para esa fecha/hora con capacidad para ' + personas + ' personas.<br><br><span style="font-size:.78rem">Reservamos cada mesa con un margen de 2 horas antes y después para garantizar tu visita. Prueba con otro horario o ajusta el número de personas.</span>', 'empty');
      return;
    }
    cont.className = '';
    let html = '<div class="mesa-list">';
    mesas.forEach(m => {
      const zona = m.zona_nombre ? ' · ' + m.zona_nombre : '';
      html += `<button type="button" class="mesa-opt" data-id="${m.id}" data-nm="${m.nombre}">
        <div class="nm">${m.nombre}</div>
        <div class="cap">Capacidad: ${m.capacidad}${zona}</div>
      </button>`;
    });
    html += '</div>';
    cont.innerHTML = html;
    info.textContent = mesas.length + ' disponible' + (mesas.length > 1 ? 's' : '');

    cont.querySelectorAll('.mesa-opt').forEach(btnMesa => {
      btnMesa.addEventListener('click', () => {
        cont.querySelectorAll('.mesa-opt').forEach(b => b.classList.remove('selected'));
        btnMesa.classList.add('selected');
        fMesaId.value = btnMesa.dataset.id;
        setBtn(true, 'Reservar mesa ' + btnMesa.dataset.nm);
      });
    });
  }

  function buscarMesas() {
    const fecha    = fFecha.value;
    const hora     = fHora.value;
    const personas = parseInt(fPersonas.value, 10) || 2;
    if (!fecha || !hora) {
      clearMesas('Elige fecha, hora y personas para ver mesas disponibles.');
      return;
    }
    cont.className = 'mesa-state loading';
    cont.innerHTML = 'Buscando mesas disponibles…';
    fMesaId.value = '';
    info.textContent = '';
    setBtn(false, 'Selecciona una mesa');

    const url = baseURL + '?fecha=' + encodeURIComponent(fecha)
              + '&hora='     + encodeURIComponent(hora)
              + '&personas=' + personas;
    fetch(url)
      .then(r => r.json())
      .then(d => {
        if (!d.ok) { clearMesas('No se pudo verificar disponibilidad. Intenta de nuevo.'); return; }
        renderMesas(d.mesas || [], personas);
      })
      .catch(() => clearMesas('Error de conexión. Intenta de nuevo.'));
  }

  function programar() {
    clearTimeout(timer);
    timer = setTimeout(buscarMesas, 250);
  }

  [fFecha, fHora, fPersonas].forEach(el => el.addEventListener('change', programar));

  // Validación al enviar: forzar mesa seleccionada
  document.getElementById('formReserva').addEventListener('submit', e => {
    if (!fMesaId.value) {
      e.preventDefault();
      alert('Por favor selecciona una mesa.');
    }
  });

  // Teléfono: solo dígitos, máx 10
  const fTel = document.getElementById('fTel');
  fTel.addEventListener('input', () => {
    fTel.value = fTel.value.replace(/\D/g, '').slice(0, 10);
  });
})();
</script>
<?php endif; ?>

</body>
</html>
