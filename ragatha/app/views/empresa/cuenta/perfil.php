<?php
$usuario   = $usuario ?? $_SESSION['usuario'];
$rol       = $_SESSION['usuario']['rol_slug'] ?? '';
$iniciales = strtoupper(mb_substr($usuario['nombre'] ?? 'U', 0, 1) . mb_substr($usuario['apellido_paterno'] ?? '', 0, 1));
$rolLabels = [
    'admin_empresa' => ['Admin de empresa', '#C8102E', '#FEE2E2'],
    'supervisor'    => ['Supervisor',        '#2563EB', '#DBEAFE'],
    'comprador'     => ['Comprador',         '#059669', '#D1FAE5'],
];
[$rolLabel, $rolColor, $rolBg] = $rolLabels[$rol] ?? ['Usuario', '#6B7280', '#F3F4F6'];
?>
<style>
.perfil-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #E5E7EB;
  padding: 26px;
  box-shadow: 0 1px 4px rgba(0,0,0,.03);
  margin-bottom: 18px;
}
.perfil-card-title {
  font-size: .9rem;
  font-weight: 800;
  color: #111827;
  margin-bottom: 20px;
  padding-bottom: 14px;
  border-bottom: 1px solid #F3F4F6;
  display: flex;
  align-items: center;
  gap: 9px;
}
.perfil-card-title-icon {
  width: 30px; height: 30px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.perfil-input {
  width: 100%;
  padding: 10px 13px;
  border: 1.5px solid #E5E7EB;
  border-radius: 9px;
  font-size: .875rem;
  font-family: 'Inter', sans-serif;
  color: #111827;
  background: #fff;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  box-sizing: border-box;
}
.perfil-input:focus { border-color: var(--color-primary); box-shadow: 0 0 0 3px rgba(200,16,46,.09); }
.perfil-input::placeholder { color: #BFC4CE; }
.perfil-input[readonly] { background: #F9FAFB; color: #6B7280; cursor: not-allowed; }
.perfil-label { display: block; font-size: .8rem; font-weight: 700; color: #374151; margin-bottom: 6px; }
.perfil-save-btn {
  padding: 10px 22px;
  background: linear-gradient(135deg, var(--color-primary), #A00D24);
  color: #fff;
  border: none;
  border-radius: 9px;
  font-weight: 700;
  font-size: .875rem;
  font-family: 'Inter', sans-serif;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: transform .15s, box-shadow .15s;
}
.perfil-save-btn:hover { transform: translateY(-1px); box-shadow: 0 6px 18px rgba(200,16,46,.3); }
.perfil-save-btn:active { transform: translateY(0); }
.perfil-sec-btn {
  padding: 10px 22px;
  background: #111827;
  color: #fff;
  border: none;
  border-radius: 9px;
  font-weight: 700;
  font-size: .875rem;
  font-family: 'Inter', sans-serif;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  transition: background .15s;
}
.perfil-sec-btn:hover { background: #1F2937; }
</style>

<div style="display:grid;grid-template-columns:340px 1fr;gap:24px;align-items:start">

  <!-- ── Columna izquierda: avatar + datos personales ── -->
  <div>

    <!-- Avatar card -->
    <div class="perfil-card">
      <div class="perfil-card-title">
        <div class="perfil-card-title-icon" style="background:#FEF2F2">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#C8102E" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5.121 17.804A8.966 8.966 0 0112 15c2.485 0 4.745.99 6.379 2.596M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        </div>
        Foto de perfil
      </div>

      <div style="display:flex;flex-direction:column;align-items:center;margin-bottom:20px">
        <div id="avatar-preview" style="margin-bottom:14px">
          <?php if (!empty($usuario['avatar'])): ?>
            <img src="<?= htmlspecialchars($usuario['avatar']) ?>" alt="Avatar"
                 style="width:96px;height:96px;border-radius:50%;object-fit:cover;border:3px solid #FECACA;box-shadow:0 4px 16px rgba(200,16,46,.18)">
          <?php else: ?>
            <div style="width:96px;height:96px;border-radius:50%;background:linear-gradient(135deg,#FEE2E2,#FEF2F2);border:3px solid #FECACA;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:1.8rem;color:var(--color-primary);box-shadow:0 4px 16px rgba(200,16,46,.15)">
              <?= htmlspecialchars($iniciales) ?>
            </div>
          <?php endif; ?>
        </div>

        <div style="text-align:center">
          <div style="font-weight:800;color:#111827;font-size:1rem"><?= htmlspecialchars(($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido_paterno'] ?? '')) ?></div>
          <span style="display:inline-block;margin-top:5px;padding:3px 10px;border-radius:999px;background:<?= $rolBg ?>;color:<?= $rolColor ?>;font-size:.72rem;font-weight:700"><?= $rolLabel ?></span>
          <?php if (!empty($usuario['email'])): ?>
          <div style="margin-top:7px;font-size:.78rem;color:#9CA3AF"><?= htmlspecialchars($usuario['email']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <form id="form-avatar" method="POST" action="<?= BASE_URL ?>cuenta/subirAvatar" enctype="multipart/form-data">
        <input type="file" id="avatar_input" name="avatar" accept=".jpg,.jpeg,.png,.webp" style="display:none">
        <div style="display:flex;flex-direction:column;gap:8px">
          <button type="button" onclick="document.getElementById('avatar_input').click()"
                  style="width:100%;padding:9px;border:1.5px dashed #E5E7EB;border-radius:9px;background:#F9FAFB;font-size:.84rem;font-weight:600;color:#374151;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:background .15s" onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='#F9FAFB'">
            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Seleccionar foto
          </button>
          <button type="submit" id="btn-subir" disabled
                  style="width:100%;padding:9px;border:none;border-radius:9px;background:var(--color-primary);color:#fff;font-size:.84rem;font-weight:700;cursor:pointer;opacity:.5;font-family:'Inter',sans-serif;transition:opacity .15s">
            Subir foto
          </button>
        </div>
        <p id="nombre-archivo" style="font-size:.75rem;color:#6B7280;margin-top:6px;min-height:1em;text-align:center"></p>
        <p style="font-size:.7rem;color:#9CA3AF;text-align:center">JPG, PNG o WebP · Máx 2 MB</p>
      </form>

      <?php if (!empty($usuario['avatar'])): ?>
      <form method="POST" action="<?= BASE_URL ?>cuenta/quitarAvatar" style="margin-top:6px"
            onsubmit="return confirm('¿Quitar tu foto de perfil?')">
        <button type="submit"
                style="width:100%;padding:8px;border:1px solid #FECACA;border-radius:8px;background:#FEF2F2;color:#DC2626;font-size:.8rem;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif">
          Quitar foto actual
        </button>
      </form>
      <?php endif; ?>

      <script>
        document.getElementById('avatar_input').addEventListener('change', function() {
          const btn = document.getElementById('btn-subir');
          const lbl = document.getElementById('nombre-archivo');
          if (this.files[0]) {
            lbl.textContent = this.files[0].name;
            btn.disabled = false;
            btn.style.opacity = '1';
          }
        });
      </script>
    </div>

  </div>

  <!-- ── Columna derecha: formularios ── -->
  <div>

    <!-- Información personal -->
    <div class="perfil-card">
      <div class="perfil-card-title">
        <div class="perfil-card-title-icon" style="background:#EFF6FF">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#2563EB" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        </div>
        Información personal
      </div>

      <form method="POST" action="<?= BASE_URL ?>cuenta/guardar">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div>
            <label class="perfil-label">Nombre</label>
            <input type="text" name="nombre" class="perfil-input" value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required>
          </div>
          <div>
            <label class="perfil-label">Apellido paterno</label>
            <input type="text" name="apellido_paterno" class="perfil-input" value="<?= htmlspecialchars($usuario['apellido_paterno'] ?? '') ?>" required>
          </div>
        </div>
        <div style="margin-bottom:14px">
          <label class="perfil-label">
            Correo electrónico
            <span style="font-size:.72rem;color:#9CA3AF;font-weight:400">(no editable)</span>
          </label>
          <input type="email" class="perfil-input" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" readonly>
        </div>
        <div style="margin-bottom:20px">
          <label class="perfil-label">Teléfono</label>
          <input type="text" name="telefono" class="perfil-input" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>" placeholder="10 dígitos">
        </div>
        <button type="submit" class="perfil-save-btn">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          Guardar cambios
        </button>
      </form>
    </div>

    <!-- Cambiar contraseña -->
    <div class="perfil-card">
      <div class="perfil-card-title">
        <div class="perfil-card-title-icon" style="background:#F3F4F6">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#374151" stroke-width="2"><rect x="5" y="11" width="14" height="10" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 018 0v4"/></svg>
        </div>
        Cambiar contraseña
      </div>

      <form method="POST" action="<?= BASE_URL ?>cuenta/cambiarPassword">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
          <div style="grid-column:1/-1">
            <label class="perfil-label">Contraseña actual</label>
            <div style="position:relative">
              <input type="password" name="password_actual" id="pwd_actual" class="perfil-input" required style="padding-right:42px">
              <button type="button" onclick="togglePwd('pwd_actual',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0;line-height:0">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="perfil-label">Nueva contraseña</label>
            <div style="position:relative">
              <input type="password" name="password_nuevo" id="pwd_nuevo" class="perfil-input" minlength="8" required placeholder="Mínimo 8 caracteres" style="padding-right:42px">
              <button type="button" onclick="togglePwd('pwd_nuevo',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0;line-height:0">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>
          <div>
            <label class="perfil-label">Confirmar contraseña</label>
            <div style="position:relative">
              <input type="password" name="password_confirm" id="pwd_confirm" class="perfil-input" minlength="8" required placeholder="Repite la nueva contraseña" style="padding-right:42px">
              <button type="button" onclick="togglePwd('pwd_confirm',this)" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0;line-height:0">
                <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
              </button>
            </div>
          </div>
        </div>
        <script>
        function togglePwd(id, btn) {
          var inp = document.getElementById(id);
          var showing = inp.type === 'text';
          inp.type = showing ? 'password' : 'text';
          btn.style.color = showing ? '#9CA3AF' : 'var(--color-primary)';
        }
        </script>
        <button type="submit" class="perfil-sec-btn">
          <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
          Cambiar contraseña
        </button>
      </form>
    </div>

  <?php if (($rol ?? '') === 'comprador'): ?>
  <!-- Dirección de entrega (solo compradores) -->
  <?php
  $configModel2 = new ConfigModel();
  $gmKeyPerfil  = $configModel2->get('google_maps_key', '');
  ?>
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-top:16px">
    <h2 style="font-size:.95rem;font-weight:700;margin-bottom:4px;color:#111827">Dirección de entrega principal</h2>
    <p style="font-size:.8rem;color:#6B7280;margin-bottom:16px">
      Dirección predeterminada para pedidos con envío. También puedes gestionar múltiples sucursales desde
      <a href="<?= BASE_URL ?>comprador-sucursal/index" style="color:var(--color-primary)">Mis sucursales</a>.
    </p>
    <form method="POST" action="<?= BASE_URL ?>cuenta/guardarDireccion">
      <div style="margin-bottom:12px">
        <label class="form-label">Dirección completa</label>
        <?php if ($gmKeyPerfil): ?>
        <input type="text" id="perfil-dir-input" name="direccion_entrega" class="form-control"
               placeholder="Escribe tu dirección para buscar con Google Maps..."
               value="<?= htmlspecialchars($usuario['direccion_entrega'] ?? '') ?>"
               autocomplete="off">
        <?php else: ?>
        <textarea name="direccion_entrega" class="form-control" rows="2"
                  placeholder="Calle, número exterior, colonia, municipio, estado..."><?= htmlspecialchars($usuario['direccion_entrega'] ?? '') ?></textarea>
        <?php endif; ?>
      </div>
      <div style="margin-bottom:<?= $gmKeyPerfil ? '12px' : '16px' ?>">
        <label class="form-label">Referencia / número interior</label>
        <input type="text" name="referencia_entrega" class="form-control"
               placeholder="Ej: Depto 3B, edificio azul, portón negro..."
               value="<?= htmlspecialchars($usuario['referencia_entrega'] ?? '') ?>">
      </div>

      <?php if ($gmKeyPerfil): ?>
      <!-- Mapa para confirmar ubicación -->
      <div id="mapa-perfil-container" style="border-radius:10px;overflow:hidden;height:220px;margin-bottom:12px;border:1px solid #E5E7EB;display:<?= (!empty($usuario['lat_entrega'])) ? 'block' : 'none' ?>">
        <div id="mapa-perfil" style="width:100%;height:100%"></div>
      </div>
      <p id="mapa-perfil-hint" style="font-size:.75rem;color:#6B7280;margin-bottom:12px;display:<?= (!empty($usuario['lat_entrega'])) ? 'none' : 'block' ?>">
        Escribe la dirección para ver el mapa y confirmar la ubicación exacta.
      </p>
      <?php endif; ?>

      <?php if (!empty($usuario['lat_entrega']) && !empty($usuario['lng_entrega'])): ?>
      <div style="margin-bottom:12px;font-size:.78rem;color:#059669">
        ✓ Ubicación GPS guardada (<?= number_format((float)$usuario['lat_entrega'],5) ?>, <?= number_format((float)$usuario['lng_entrega'],5) ?>)
      </div>
      <?php endif; ?>

      <input type="hidden" name="lat_entrega" id="perfil-lat" value="<?= htmlspecialchars($usuario['lat_entrega'] ?? '') ?>">
      <input type="hidden" name="lng_entrega" id="perfil-lng" value="<?= htmlspecialchars($usuario['lng_entrega'] ?? '') ?>">

      <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
        Guardar dirección
      </button>
    </form>
  </div>

  <?php if ($gmKeyPerfil): ?>
  <script>
  (function() {
    var mapPerfil = null, markerPerfil = null;
    var initLat = parseFloat('<?= (float)($usuario['lat_entrega'] ?? 0) ?>') || null;
    var initLng = parseFloat('<?= (float)($usuario['lng_entrega'] ?? 0) ?>') || null;

    window.initGoogleMapsPerfil = function() {
      var center = (initLat && initLng) ? { lat: initLat, lng: initLng } : { lat: 19.4326, lng: -99.1332 };
      mapPerfil = new google.maps.Map(document.getElementById('mapa-perfil'), {
        center: center, zoom: (initLat && initLng) ? 16 : 12,
        mapTypeControl: false, streetViewControl: false
      });

      if (initLat && initLng) {
        markerPerfil = new google.maps.Marker({ position: center, map: mapPerfil, draggable: true });
        markerPerfil.addListener('dragend', actualizarCoords);
        document.getElementById('mapa-perfil-container').style.display = 'block';
      }

      mapPerfil.addListener('click', function(e) {
        var pos = e.latLng;
        if (markerPerfil) { markerPerfil.setPosition(pos); } else {
          markerPerfil = new google.maps.Marker({ position: pos, map: mapPerfil, draggable: true });
          markerPerfil.addListener('dragend', actualizarCoords);
        }
        actualizarCoords();
      });

      var autocomplete = new google.maps.places.Autocomplete(
        document.getElementById('perfil-dir-input'),
        { componentRestrictions: { country: 'mx' }, fields: ['geometry', 'formatted_address'] }
      );
      autocomplete.addListener('place_changed', function() {
        var place = autocomplete.getPlace();
        if (!place.geometry) return;
        var pos = place.geometry.location;
        mapPerfil.setCenter(pos);
        mapPerfil.setZoom(16);
        if (markerPerfil) { markerPerfil.setPosition(pos); } else {
          markerPerfil = new google.maps.Marker({ position: pos, map: mapPerfil, draggable: true });
          markerPerfil.addListener('dragend', actualizarCoords);
        }
        document.getElementById('perfil-lat').value = pos.lat().toFixed(7);
        document.getElementById('perfil-lng').value = pos.lng().toFixed(7);
        document.getElementById('mapa-perfil-container').style.display = 'block';
        var hint = document.getElementById('mapa-perfil-hint');
        if (hint) hint.style.display = 'none';
      });
    };

    function actualizarCoords() {
      if (!markerPerfil) return;
      var pos = markerPerfil.getPosition();
      document.getElementById('perfil-lat').value = pos.lat().toFixed(7);
      document.getElementById('perfil-lng').value = pos.lng().toFixed(7);
    }
  })();
  </script>
  <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($gmKeyPerfil) ?>&libraries=places&callback=initGoogleMapsPerfil">
  </script>
  <?php endif; ?>
  <?php endif; ?>

  <?php if (in_array($rol ?? '', ['admin_empresa', 'supervisor'], true)): ?>
  <?php
  $empresaDataPerfil = $_SESSION['empresa'] ?? [];
  $configModelEmp    = new ConfigModel();
  $gmKeyEmp          = $configModelEmp->get('google_maps_key', '');
  ?>
  <!-- Dirección de la empresa (supervisores y admin_empresa) -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-top:16px">
    <h2 style="font-size:.95rem;font-weight:700;margin-bottom:4px;color:#111827">Dirección de la empresa</h2>
    <p style="font-size:.8rem;color:#6B7280;margin-bottom:16px">
      Esta dirección se usa como <strong>punto de origen</strong> para calcular rutas de entrega y el costo de envío automáticamente.
      <?php if (empty($empresaDataPerfil['direccion_fiscal'])): ?>
      <strong style="color:#DC2626">⚠ Sin dirección registrada — algunos cálculos de ruta no funcionarán.</strong>
      <?php endif; ?>
    </p>
    <form method="POST" action="<?= BASE_URL ?>empresa/guardarDireccion">
      <div style="margin-bottom:12px">
        <label class="form-label">Dirección completa de la empresa</label>
        <?php if ($gmKeyEmp): ?>
        <input type="text" id="emp-dir-input" name="direccion_fiscal" class="form-control"
               placeholder="Busca la dirección con Google Maps..."
               value="<?= htmlspecialchars($empresaDataPerfil['direccion_fiscal'] ?? '') ?>"
               autocomplete="off">
        <?php else: ?>
        <input type="text" name="direccion_fiscal" class="form-control"
               placeholder="Calle, número, colonia, ciudad..."
               value="<?= htmlspecialchars($empresaDataPerfil['direccion_fiscal'] ?? '') ?>">
        <?php endif; ?>
      </div>

      <?php if ($gmKeyEmp): ?>
      <div id="mapa-emp-container" style="border-radius:10px;overflow:hidden;height:220px;margin-bottom:12px;border:1px solid #E5E7EB;display:<?= (!empty($empresaDataPerfil['lat'])) ? 'block' : 'none' ?>">
        <div id="mapa-emp" style="width:100%;height:100%"></div>
      </div>
      <p id="mapa-emp-hint" style="font-size:.75rem;color:#6B7280;margin-bottom:12px;display:<?= (!empty($empresaDataPerfil['lat'])) ? 'none' : 'block' ?>">
        Escribe la dirección para ver el mapa y confirmar la ubicación exacta.
      </p>
      <?php endif; ?>

      <?php if (!empty($empresaDataPerfil['lat']) && !empty($empresaDataPerfil['lng'])): ?>
      <div style="margin-bottom:12px;font-size:.78rem;color:#059669">
        ✓ Ubicación GPS guardada (<?= number_format((float)$empresaDataPerfil['lat'],5) ?>, <?= number_format((float)$empresaDataPerfil['lng'],5) ?>)
      </div>
      <?php endif; ?>

      <input type="hidden" name="lat" id="emp-lat" value="<?= htmlspecialchars($empresaDataPerfil['lat'] ?? '') ?>">
      <input type="hidden" name="lng" id="emp-lng" value="<?= htmlspecialchars($empresaDataPerfil['lng'] ?? '') ?>">

      <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
        Guardar dirección
      </button>
    </form>
  </div>

  <?php if ($gmKeyEmp): ?>
  <script>
  (function() {
    var mapEmp = null, markerEmp = null;
    var initLatEmp = parseFloat('<?= (float)($empresaDataPerfil['lat'] ?? 0) ?>') || null;
    var initLngEmp = parseFloat('<?= (float)($empresaDataPerfil['lng'] ?? 0) ?>') || null;

    window.initGoogleMapsEmpresa = function() {
      var center = (initLatEmp && initLngEmp) ? { lat: initLatEmp, lng: initLngEmp } : { lat: 19.4326, lng: -99.1332 };
      mapEmp = new google.maps.Map(document.getElementById('mapa-emp'), {
        center: center, zoom: (initLatEmp && initLngEmp) ? 16 : 12,
        mapTypeControl: false, streetViewControl: false
      });
      if (initLatEmp && initLngEmp) {
        markerEmp = new google.maps.Marker({ position: center, map: mapEmp, draggable: true });
        markerEmp.addListener('dragend', actualizarCoordsEmp);
        document.getElementById('mapa-emp-container').style.display = 'block';
      }
      mapEmp.addListener('click', function(e) {
        var pos = e.latLng;
        if (markerEmp) { markerEmp.setPosition(pos); } else {
          markerEmp = new google.maps.Marker({ position: pos, map: mapEmp, draggable: true });
          markerEmp.addListener('dragend', actualizarCoordsEmp);
        }
        actualizarCoordsEmp();
      });
      var autocomplete = new google.maps.places.Autocomplete(
        document.getElementById('emp-dir-input'),
        { componentRestrictions: { country: 'mx' }, fields: ['geometry', 'formatted_address'] }
      );
      autocomplete.addListener('place_changed', function() {
        var place = autocomplete.getPlace();
        if (!place.geometry) return;
        var pos = place.geometry.location;
        mapEmp.setCenter(pos); mapEmp.setZoom(16);
        if (markerEmp) { markerEmp.setPosition(pos); } else {
          markerEmp = new google.maps.Marker({ position: pos, map: mapEmp, draggable: true });
          markerEmp.addListener('dragend', actualizarCoordsEmp);
        }
        document.getElementById('emp-lat').value = pos.lat().toFixed(7);
        document.getElementById('emp-lng').value = pos.lng().toFixed(7);
        document.getElementById('mapa-emp-container').style.display = 'block';
        var hint = document.getElementById('mapa-emp-hint');
        if (hint) hint.style.display = 'none';
      });
    };
    function actualizarCoordsEmp() {
      if (!markerEmp) return;
      var pos = markerEmp.getPosition();
      document.getElementById('emp-lat').value = pos.lat().toFixed(7);
      document.getElementById('emp-lng').value = pos.lng().toFixed(7);
    }
  })();
  </script>
  <script async defer
    src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($gmKeyEmp) ?>&libraries=places&callback=initGoogleMapsEmpresa">
  </script>
  <?php endif; ?>
  <?php endif; ?>

</div>
