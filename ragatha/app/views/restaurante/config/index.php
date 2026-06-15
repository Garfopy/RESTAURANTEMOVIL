<?php ob_start(); ?>
<div>
  <div class="rst-card">
    <form method="POST" action="<?= BASE_URL ?>rest-config/guardar" enctype="multipart/form-data" accept-charset="UTF-8">

      <?php if (!empty($bloqueadoPorCarniHub)): ?>
      <div style="background:#EFF6FF;border:1.5px solid #BFDBFE;border-radius:12px;padding:12px 14px;margin-bottom:16px;
                  font-size:.82rem;color:#1E40AF;line-height:1.45">
        <strong>Local sincronizado con CarniHub.</strong>
        Los datos del restaurante se autocompletan desde CarniHub y no se pueden editar aquí para evitar desajustes en el pedido automático.
      </div>
      <?php endif; ?>

      <!-- Información general -->
      <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px;
                  display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        Información general
      </div>

      <div class="form-group">
        <label class="form-label">Nombre del restaurante *</label>
        <input type="text" name="nombre" class="form-input"
               value="<?= htmlspecialchars($restaurante['nombre'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Descripción</label>
        <textarea name="descripcion" class="form-textarea" rows="3"><?= htmlspecialchars($restaurante['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Teléfono</label>
        <input type="text" name="telefono" class="form-input"
               value="<?= htmlspecialchars($restaurante['telefono'] ?? '') ?>">
      </div>

      <!-- Dirección + Mapa lado a lado -->
      <div style="display:grid;grid-template-columns:280px 1fr;gap:16px;margin-bottom:20px;align-items:start">
        <div>
          <label class="form-label">Dirección</label>
          <div style="position:relative">
            <input type="text" name="direccion" id="inpDireccion" class="form-input"
                   value="<?= htmlspecialchars($restaurante['direccion'] ?? '') ?>"
                   placeholder="Ej: Av. Principal 123, Ciudad"
                   autocomplete="off"
                   style="margin-bottom:0">
            <div id="addrSugg" style="display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;
                 z-index:300;background:#fff;border:1.5px solid #E5E7EB;border-radius:10px;
                 box-shadow:0 6px 24px rgba(0,0,0,.1);max-height:220px;overflow-y:auto"></div>
          </div>
          <div style="margin-top:10px"></div>
          <div id="coordsBox" style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:10px 12px;<?= empty($restaurante['direccion']) ? 'display:none' : '' ?>">
            <div style="font-size:.75rem;font-weight:700;color:#065F46;margin-bottom:6px">📍 Coordenadas</div>
            <div style="font-size:.78rem;color:#374151">Lat: <span id="coordLat"><?= $restaurante['lat'] ?? '—' ?></span></div>
            <div style="font-size:.78rem;color:#374151">Lng: <span id="coordLng"><?= $restaurante['lng'] ?? '—' ?></span></div>
            <input type="hidden" name="lat" id="inpLat" value="<?= htmlspecialchars($restaurante['lat'] ?? '') ?>">
            <input type="hidden" name="lng" id="inpLng" value="<?= htmlspecialchars($restaurante['lng'] ?? '') ?>">
          </div>
          <div id="mapNote" style="font-size:.72rem;color:#9CA3AF;margin-top:6px">
            Guarda para actualizar el mapa.
          </div>
        </div>
        <div>
          <label class="form-label">Ubicación en mapa</label>
          <div id="rstMap"
               data-direccion="<?= htmlspecialchars($restaurante['direccion'] ?? '', ENT_QUOTES) ?>"
               style="border-radius:10px;overflow:hidden;border:1px solid #E5E7EB;height:200px;background:#F3F4F6;display:flex;align-items:center;justify-content:center">
            <?php if (empty($restaurante['direccion'])): ?>
            <div style="text-align:center;color:#9CA3AF;font-size:.82rem">
              <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto 6px;display:block"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
              Agrega una dirección para ver el mapa
            </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php if (!empty($restaurante['direccion'])): ?>
      <?php if (!empty($mapsApiKey)): ?>
      <!-- Nominatim geocoding + Google Maps display -->
      <script>
      window._mapCoords = null;

      function initMap() {
        // Called when Google Maps SDK loads — render if Nominatim already resolved
        if (window._mapCoords) _renderGoogleMap(window._mapCoords.lat, window._mapCoords.lng);
      }

      function _renderGoogleMap(lat, lng) {
        var el = document.getElementById('rstMap');
        var dir = el.dataset.direccion;
        el.innerHTML = '';
        var map = new google.maps.Map(el, { center:{lat:lat,lng:lng}, zoom:16 });
        new google.maps.Marker({ position:{lat:lat,lng:lng}, map:map, title:dir });
      }

      (function(){
        var el  = document.getElementById('rstMap');
        var dir = el.dataset.direccion;
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(dir))
          .then(function(r){ return r.json(); })
          .then(function(data) {
            if (data[0]) {
              var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
              document.getElementById('coordLat').textContent = lat.toFixed(6);
              document.getElementById('coordLng').textContent = lng.toFixed(6);
              document.getElementById('inpLat').value = lat.toFixed(6);
              document.getElementById('inpLng').value = lng.toFixed(6);
              document.getElementById('coordsBox').style.display = 'block';
              window._mapCoords = {lat:lat, lng:lng};
              if (window.google && window.google.maps) _renderGoogleMap(lat, lng);
            } else {
              el.innerHTML = '<div style="padding:20px;text-align:center;color:#9CA3AF;font-size:.82rem">No se encontró la dirección en el mapa.</div>';
            }
          })
          .catch(function(){ el.innerHTML = '<div style="padding:20px;text-align:center;color:#9CA3AF;font-size:.82rem">No se pudo cargar el mapa.</div>'; });
      })();
      </script>
      <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($mapsApiKey) ?>&callback=initMap" async defer></script>
      <?php else: ?>
      <!-- Nominatim + Leaflet (sin API key) -->
      <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
      <script>
      (function(){
        var sc = document.createElement('script');
        sc.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        sc.onload = function() {
          var markerIcon = L.divIcon({
            className: 'rst-map-pin',
            html: '<div style="width:22px;height:22px;border-radius:50% 50% 50% 0;background:var(--cp);transform:rotate(-45deg);box-shadow:0 2px 8px rgba(0,0,0,.25);border:2px solid #fff;position:relative"><div style="position:absolute;inset:6px;background:#fff;border-radius:50%"></div></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 22],
            popupAnchor: [0, -18]
          });
          var el  = document.getElementById('rstMap');
          var dir = el.dataset.direccion;
          el.innerHTML = '';
          fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(dir))
            .then(function(r){ return r.json(); })
            .then(function(data) {
              if (data[0]) {
                var lat = parseFloat(data[0].lat), lng = parseFloat(data[0].lon);
                var map = L.map(el).setView([lat, lng], 16);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
                  { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
                L.marker([lat, lng], { icon: markerIcon }).addTo(map).bindPopup(dir).openPopup();
                document.getElementById('coordLat').textContent = lat.toFixed(6);
                document.getElementById('coordLng').textContent = lng.toFixed(6);
                document.getElementById('inpLat').value = lat.toFixed(6);
                document.getElementById('inpLng').value = lng.toFixed(6);
                document.getElementById('coordsBox').style.display = 'block';
              } else {
                el.innerHTML = '<div style="padding:20px;text-align:center;color:#9CA3AF;font-size:.82rem">No se encontró la dirección en el mapa.</div>';
              }
            })
            .catch(function(){ el.innerHTML = '<div style="padding:20px;text-align:center;color:#9CA3AF;font-size:.82rem">No se pudo cargar el mapa.</div>'; });
        };
        document.head.appendChild(sc);
      })();
      </script>
      <?php endif; ?>
      <?php endif; ?>

      <!-- Horarios por día de la semana -->
      <div style="border-top:1px solid #F3F4F6;padding-top:20px;margin-bottom:20px">
        <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:6px;
                    display:flex;align-items:center;gap:8px">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          Horarios de atención
        </div>
        <div style="font-size:.8rem;color:#6B7280;margin-bottom:14px">
          Selecciona los días que abres y define el horario. Los comensales no podrán ordenar fuera de este horario.
        </div>

        <?php
          $diasKeys = ['lun','mar','mie','jue','vie','sab','dom'];
          $diasNom  = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];
          $horariosJson = !empty($restaurante['horarios_json'])
            ? json_decode($restaurante['horarios_json'], true)
            : [];
          // Default: fill from old fields or 9:00-22:00
          $defaultAbre  = substr($restaurante['horario_apertura'] ?? '09:00', 0, 5);
          $defaultCierra = substr($restaurante['horario_cierre']  ?? '22:00', 0, 5);
          foreach ($diasKeys as $d) {
            if (!isset($horariosJson[$d])) {
              $horariosJson[$d] = ['abre' => $defaultAbre, 'cierra' => $defaultCierra, 'cerrado' => 0];
            }
          }
        ?>

        <!-- Day chips -->
        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px" id="dayChips">
          <?php foreach ($diasKeys as $i => $d): ?>
          <?php $cerrado = (int)($horariosJson[$d]['cerrado'] ?? 0); ?>
          <button type="button"
                  data-dia="<?= $d ?>"
                  onclick="toggleDia('<?= $d ?>', this)"
                  style="padding:8px 16px;border-radius:99px;font-size:.85rem;font-weight:600;cursor:pointer;
                         border:2px solid <?= !$cerrado ? 'var(--cp)' : '#D1D5DB' ?>;
                         background:<?= !$cerrado ? 'var(--cp)' : '#fff' ?>;
                         color:<?= !$cerrado ? '#fff' : '#6B7280' ?>;transition:.15s">
            <?= $diasNom[$i] ?>
          </button>
          <?php endforeach; ?>
        </div>

        <!-- Time rows -->
        <div id="horarioRows" style="display:grid;gap:8px">
          <?php
          function horaSelect(string $id, string $val): string {
            $h = "<select id=\"$id\" onchange=\"actualizarHorariosJson()\"
                style=\"padding:7px 10px;border:1.5px solid #D1D5DB;border-radius:8px;
                        background:#fff;color:#374151;font-size:.85rem;cursor:pointer;
                        appearance:none;-webkit-appearance:none;min-width:110px\">";
            for ($hh = 0; $hh < 24; $hh++) {
              foreach ([0, 30] as $mm) {
                $v    = sprintf('%02d:%02d', $hh, $mm);
                $ampm = $hh < 12 ? 'AM' : 'PM';
                $h12  = $hh % 12 ?: 12;
                $lbl  = sprintf('%d:%02d %s', $h12, $mm, $ampm);
                $sel  = $v === $val ? ' selected' : '';
                $h   .= "<option value=\"$v\"$sel>$lbl</option>";
              }
            }
            return $h . '</select>';
          }
          ?>
          <?php foreach ($diasKeys as $i => $d): ?>
          <?php $h = $horariosJson[$d]; $cerrado = (int)($h['cerrado'] ?? 0); ?>
          <div id="row_<?= $d ?>" style="<?= $cerrado ? 'display:none' : 'display:flex' ?>;align-items:center;gap:12px;
               background:#F9FAFB;border-radius:10px;padding:10px 14px;flex-wrap:wrap">
            <span style="font-weight:600;font-size:.88rem;color:#374151;width:80px"><?= $diasNom[$i] ?></span>
            <div style="display:flex;align-items:center;gap:10px;flex:1;flex-wrap:wrap">
              <label style="font-size:.78rem;color:#6B7280;font-weight:500">Abre</label>
              <?= horaSelect('abre_' . $d, $h['abre'] ?? '09:00') ?>
              <label style="font-size:.78rem;color:#6B7280;font-weight:500">Cierra</label>
              <?= horaSelect('cierra_' . $d, $h['cierra'] ?? '22:00') ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>

        <input type="hidden" name="horarios_json" id="horariosJson"
               value="<?= htmlspecialchars(json_encode($horariosJson)) ?>">
        <!-- Keep legacy columns for backwards compat -->
        <input type="hidden" name="horario_apertura" id="legacyAbre" value="<?= htmlspecialchars($defaultAbre) ?>">
        <input type="hidden" name="horario_cierre" id="legacyCierra" value="<?= htmlspecialchars($defaultCierra) ?>">
      </div>

      <!-- Branding -->
      <div style="border-top:1px solid #F3F4F6;padding-top:20px;margin-top:4px;margin-bottom:20px">
        <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px;
                    display:flex;align-items:center;gap:8px">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/></svg>
          Branding
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;align-items:end">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Color primario</label>
            <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
              <input type="color" name="color_primario" id="cpicker"
                     value="<?= htmlspecialchars($restaurante['color_primario'] ?? '#C8102E') ?>"
                     style="height:40px;width:48px;border:1px solid #D1D5DB;border-radius:6px;padding:2px;cursor:pointer">
              <input type="text" id="txtColorPri"
                     value="<?= htmlspecialchars($restaurante['color_primario'] ?? '#C8102E') ?>"
                     class="form-input" style="flex:1">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Color secundario</label>
            <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
              <input type="color" name="color_secundario" id="spicker"
                     value="<?= htmlspecialchars($restaurante['color_secundario'] ?? '#1f2937') ?>"
                     style="height:40px;width:48px;border:1px solid #D1D5DB;border-radius:6px;padding:2px;cursor:pointer">
              <input type="text" id="txtColorSec"
                     value="<?= htmlspecialchars($restaurante['color_secundario'] ?? '#1f2937') ?>"
                     class="form-input" style="flex:1">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Logo</label>
            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg"
                   class="form-input" style="padding:6px">
            <?php if (!empty($restaurante['logo'])): ?>
            <img src="<?= BASE_URL . htmlspecialchars($restaurante['logo']) ?>"
                 style="height:36px;margin-top:6px;border-radius:4px;object-fit:contain;display:block">
            <?php endif; ?>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Foto de portada <span style="font-weight:400;color:#9CA3AF">(banner del menú público)</span></label>
            <input type="file" name="imagen_banner" accept=".jpg,.jpeg,.png,.webp"
                   class="form-input" style="padding:6px">
            <?php if (!empty($restaurante['imagen_banner'])): ?>
            <img src="<?= BASE_URL . htmlspecialchars($restaurante['imagen_banner']) ?>"
                 style="width:100%;max-height:120px;margin-top:8px;border-radius:8px;object-fit:cover;display:block">
            <?php endif; ?>
            <div style="font-size:.72rem;color:#9CA3AF;margin-top:4px">Se muestra como fondo del encabezado en la vista del cliente. Recomendado: 1200×400 px.</div>
          </div>
        </div>
      </div>

      <!-- Modos de operación -->
      <div style="border-top:1px solid #F3F4F6;padding-top:20px;margin-bottom:20px">
        <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:6px;
                    display:flex;align-items:center;gap:8px">
          <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
          Modos de operación
        </div>
        <div style="font-size:.8rem;color:#6B7280;margin-bottom:14px">
          Adapta CarniHub a cómo opera tu sucursal: restaurante con mesas, taquería, take-away, etc.
        </div>

        <?php
          $r = $restaurante ?? [];
          $toggles = [
            ['mesas_habilitadas',       1, '🪑 Mesas habilitadas',       'Sucursal con mesas físicas. Desactiva para take-away o banqueta sin mesas.'],
            ['reservas_habilitadas',    1, '📅 Reservaciones',           'Permite que los comensales reserven mesa con anticipación.'],
            ['portero_habilitado',      1, '🛡️ Portero (verifica pago)','Un portero escanea el QR del comensal al salir para confirmar el pago.'],
            ['requiere_login_comensal', 0, '🔐 Login obligatorio',       'Exige Google login o nombre+teléfono antes de ordenar.'],
          ];
          foreach ($toggles as [$key, $def, $label, $desc]):
            $val = (int)($r[$key] ?? $def);
        ?>
        <label class="rst-toggle-row <?= $val ? 'is-on' : '' ?>">
          <span class="rst-toggle">
            <input type="checkbox" name="<?= $key ?>" value="1" <?= $val ? 'checked' : '' ?>
                   onchange="this.closest('.rst-toggle-row').classList.toggle('is-on', this.checked)">
            <span class="rst-toggle-track"></span>
          </span>
          <div style="flex:1">
            <div style="font-weight:600;color:#111827;font-size:.92rem"><?= $label ?></div>
            <div style="font-size:.78rem;color:#6B7280;margin-top:2px"><?= $desc ?></div>
          </div>
          <span class="badge rst-toggle-badge <?= $val ? 'badge-green' : 'badge-gray' ?>">
            <?= $val ? 'Activo' : 'Apagado' ?>
          </span>
        </label>
        <?php endforeach; ?>

        <script>
        document.querySelectorAll('.rst-toggle-row input[type="checkbox"]').forEach(chk => {
          chk.addEventListener('change', () => {
            const badge = chk.closest('.rst-toggle-row').querySelector('.rst-toggle-badge');
            badge.textContent = chk.checked ? 'Activo' : 'Apagado';
            badge.className = 'badge rst-toggle-badge ' + (chk.checked ? 'badge-green' : 'badge-gray');
          });
        });
        </script>

        <div class="form-group" style="margin-top:14px">
          <label class="form-label">💰 Propinas sugeridas (CSV de %)</label>
          <input type="text" name="propinas_sugeridas" class="form-input"
                 value="<?= htmlspecialchars($r['propinas_sugeridas'] ?? '0,10,15,20') ?>"
                 placeholder="0,10,15,20">
          <div style="font-size:.75rem;color:#9CA3AF;margin-top:4px">
            Porcentajes mostrados al comensal en la pantalla de pago, separados por comas.
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           SECCIÓN: TIPOS DE ENTREGA (APP MÓVIL)
           ═════════════════════════════════════════════════════════ -->
      <hr style="border:none;border-top:1.5px solid #F3F4F6;margin:24px 0">
      <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:6px;
                  display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        🛵 Tipos de entrega (App Móvil)
      </div>
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:14px">
        Controla cómo los clientes pueden recibir sus pedidos desde la app móvil (CarniHub / Amare).
      </div>

      <?php
        $tiposEntregaConfig = json_decode($cfgPagos['tipos_entrega_habilitados'] ?? '["delivery","pickup"]', true) ?: ['delivery','pickup'];
        $tiposEntregaOpts = [
          'delivery' => '🛵 A domicilio',
          'pickup'   => '🛍️ Para llevar (recoger en tienda)',
          'eat_in'   => '🍽️ Comer en el restaurante',
        ];
      ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <?php foreach ($tiposEntregaOpts as $val => $label): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                      border:1.5px solid #E5E7EB;border-radius:10px;cursor:pointer;
                      background:#F9FAFB;font-size:.88rem;font-weight:500">
          <input type="checkbox"
                 name="tipos_entrega_habilitados[]"
                 value="<?= $val ?>"
                 <?= in_array($val, $tiposEntregaConfig) ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:#C8102E">
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="font-size:.75rem;color:#9CA3AF;margin-bottom:20px">
        Solo los tipos marcados aparecerán en la app móvil. Al menos uno debe estar habilitado.
      </div>

      <!-- ══════════════════════════════════════════════════════════
           SECCIÓN: MÉTODOS DE PAGO (APP MÓVIL)
           ═════════════════════════════════════════════════════════ -->
      <hr style="border:none;border-top:1.5px solid #F3F4F6;margin:24px 0">
      <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:6px;
                  display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        💳 Métodos de pago (App Móvil)
      </div>
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:14px">
        Controla qué métodos de pago estarán disponibles en la app móvil (CarniHub / Amare).
      </div>

      <?php
        $appBackgroundColor = $cfgPagos['app_background_color'] ?? '#FFFFFF';
        $appButtonColor = $cfgPagos['app_button_color'] ?? ($restaurante['color_primario'] ?? '#C8102E');
        $appButtonTextColor = $cfgPagos['app_button_text_color'] ?? '#FFFFFF';
      ?>
      <div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:18px">
        <div style="font-weight:700;font-size:.88rem;color:#111827;margin-bottom:6px">Colores de la app m&oacute;vil</div>
        <div style="font-size:.76rem;color:#6B7280;margin-bottom:12px">
          Ajusta el fondo y los botones principales que se sincronizan con Amare-App.
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px">
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Fondo de la app</label>
            <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
              <input type="color" name="app_background_color" id="appBgPicker"
                     value="<?= htmlspecialchars($appBackgroundColor) ?>"
                     style="height:40px;width:48px;border:1px solid #D1D5DB;border-radius:6px;padding:2px;cursor:pointer">
              <input type="text" id="txtAppBgColor"
                     value="<?= htmlspecialchars($appBackgroundColor) ?>"
                     class="form-input" style="flex:1;min-width:0">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Botones de la app</label>
            <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
              <input type="color" name="app_button_color" id="appBtnPicker"
                     value="<?= htmlspecialchars($appButtonColor) ?>"
                     style="height:40px;width:48px;border:1px solid #D1D5DB;border-radius:6px;padding:2px;cursor:pointer">
              <input type="text" id="txtAppBtnColor"
                     value="<?= htmlspecialchars($appButtonColor) ?>"
                     class="form-input" style="flex:1;min-width:0">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Texto de botones</label>
            <div style="display:flex;gap:8px;align-items:center;margin-top:4px">
              <input type="color" name="app_button_text_color" id="appBtnTextPicker"
                     value="<?= htmlspecialchars($appButtonTextColor) ?>"
                     style="height:40px;width:48px;border:1px solid #D1D5DB;border-radius:6px;padding:2px;cursor:pointer">
              <input type="text" id="txtAppBtnTextColor"
                     value="<?= htmlspecialchars($appButtonTextColor) ?>"
                     class="form-input" style="flex:1;min-width:0">
            </div>
          </div>
        </div>
      </div>

      <?php
        $metodosAppConfig = json_decode($cfgPagos['metodos_pago_app_habilitados'] ?? '["card","cash"]', true) ?: ['card','cash'];
        $metodosAppOpts = [
          'card'      => '💳 Tarjeta (Stripe)',
          'cash'      => '💵 Efectivo',
          'apple_pay' => '🍎 Apple Pay',
          'google_pay'=> '🤖 Google Pay',
        ];
      ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <?php foreach ($metodosAppOpts as $val => $label): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                      border:1.5px solid #E5E7EB;border-radius:10px;cursor:pointer;
                      background:#F9FAFB;font-size:.88rem;font-weight:500">
          <input type="checkbox"
                 name="metodos_pago_app_habilitados[]"
                 value="<?= $val ?>"
                 <?= in_array($val, $metodosAppConfig) ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:#C8102E">
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="font-size:.75rem;color:#9CA3AF;margin-bottom:8px">
        Solo los métodos marcados aparecerán en la app móvil. Al menos uno debe estar habilitado.
      </div>

      <!-- Costo de envío y pedido mínimo -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px">
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">💰 Costo de envío (MXN)</label>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="color:#6B7280;font-weight:600">$</span>
            <input type="number" name="costo_envio_app" class="form-input"
                   value="<?= htmlspecialchars($cfgPagos['costo_envio_app'] ?? '0') ?>"
                   min="0" step="0.01" placeholder="0.00">
          </div>
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label">📦 Pedido mínimo (MXN)</label>
          <div style="display:flex;align-items:center;gap:6px">
            <span style="color:#6B7280;font-weight:600">$</span>
            <input type="number" name="pedido_minimo_app" class="form-input"
                   value="<?= htmlspecialchars($cfgPagos['pedido_minimo_app'] ?? '0') ?>"
                   min="0" step="0.01" placeholder="0.00">
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           SECCIÓN: CONEXIÓN API AMARE-APP
           ═════════════════════════════════════════════════════════ -->
      <hr style="border:none;border-top:1.5px solid #F3F4F6;margin:24px 0">
      <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:6px;
                  display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
        🔗 Conexión con API Amare-App
      </div>
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:14px">
        Configura la URL y el token de la API de Amare-App para sincronizar automáticamente métodos de pago y tipos de entrega. Al guardar, se enviarán los cambios a la app móvil.
      </div>

      <div style="background:#F0F9FF;border:1.5px solid #BAE6FD;border-radius:12px;
                  padding:16px 18px;margin-bottom:20px">
        <div class="form-group">
          <label class="form-label" style="font-size:.8rem">URL de la API Amare-App</label>
          <input type="url" name="amare_api_url" id="amareApiUrl" class="form-input"
                 value="<?= htmlspecialchars($cfgPagos['amare_api_url'] ?? '') ?>"
                 placeholder="https://api.turestaurante.com/api"
                 style="font-family:monospace;font-size:.8rem">
          <div style="font-size:.72rem;color:#9CA3AF;margin-top:4px">
            Ej: https://tudominio.com/api (sin slash final)
          </div>
        </div>

        <?php
          $yaConectado = !empty($cfgPagos['amare_api_token'] ?? '') && !empty($cfgPagos['amare_api_url'] ?? '');
          $tokenExpirado = ($cfgPagos['amare_token_expirado'] ?? '') === '1';
          $amareEmailGuardado = htmlspecialchars($cfgPagos['amare_email'] ?? '', ENT_QUOTES);
        ?>
        <input type="hidden" name="amare_api_token" id="amareApiToken" value="<?= $yaConectado ? '••••••••••••' : '' ?>">
        <input type="hidden" name="amare_email" id="amareEmailHidden" value="<?= $amareEmailGuardado ?>">

        <?php if ($yaConectado && !$tokenExpirado): ?>
        <!-- Estado: Conectado -->
        <div id="amareStatusConnected" style="background:#F0FDF4;border:1.5px solid #BBF7D0;border-radius:10px;padding:12px 14px;margin-bottom:14px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span style="font-size:1.1rem">✅</span>
            <span style="font-weight:700;color:#065F46;font-size:.88rem">Conectado a la App Móvil</span>
          </div>
          <div style="font-size:.76rem;color:#166534;margin-bottom:8px">
            La configuración se sincroniza automáticamente al guardar.
          </div>
          <button type="button" onclick="desconectarAmare()"
                  style="font-size:.76rem;color:#DC2626;background:#FEF2F2;border:1px solid #FECACA;border-radius:6px;padding:4px 12px;cursor:pointer">
            Desconectar
          </button>
        </div>
        <?php endif; ?>

        <?php if ($tokenExpirado): ?>
        <!-- Estado: Token expirado -->
        <div id="amareStatusExpired" style="background:#FFFBEB;border:1.5px solid #FDE68A;border-radius:10px;padding:12px 14px;margin-bottom:14px">
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
            <span style="font-size:1.1rem">⚠️</span>
            <span style="font-weight:700;color:#92400E;font-size:.88rem">La conexión con la app móvil ha expirado</span>
          </div>
          <div style="font-size:.76rem;color:#92400E;margin-bottom:8px">
            El token de acceso venció. Ingresa tu email y contraseña para renovar la conexión.
          </div>
          <button type="button" onclick="desconectarAmare()"
                  style="font-size:.76rem;color:#DC2626;background:#FEF2F2;border:1px solid #FECACA;border-radius:6px;padding:4px 12px;cursor:pointer">
            Desconectar
          </button>
        </div>
        <?php endif; ?>

        <!-- Formulario de registro/login -->
        <?php $mostrarForm = !$yaConectado || $tokenExpirado; ?>
        <div id="amareRegisterForm" style="<?= $mostrarForm ? '' : 'display:none' ?>">
          <div style="font-weight:600;color:#0C4A6E;font-size:.85rem;margin-bottom:10px">
            <?= $tokenExpirado ? '🔄 Renovar conexión con la App Móvil' : '📝 Crea tu cuenta en la App Móvil' ?>
          </div>
          <div style="font-size:.76rem;color:#0369A1;margin-bottom:14px">
            <?= $tokenExpirado ? 'Tu sesión expiró. Ingresa tu contraseña para renovar el token de acceso.' : 'Ingresa un email y contraseña para crear tu cuenta automáticamente en la app móvil y conectar el panel.' ?>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label" style="font-size:.75rem">Email</label>
              <input type="email" id="amareEmail" class="form-input"
                     value="<?= $amareEmailGuardado ?>"
                     placeholder="admin@turestaurante.com"
                     style="font-size:.82rem">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label" style="font-size:.75rem">Contraseña</label>
              <input type="password" id="amarePassword" class="form-input"
                     placeholder="<?= $tokenExpirado ? 'Tu contraseña' : 'Mínimo 8 caracteres' ?>"
                     style="font-size:.82rem" autocomplete="new-password">
            </div>
          </div>

          <div class="form-group" style="margin-bottom:12px">
            <label class="form-label" style="font-size:.75rem">Nombre del administrador</label>
            <input type="text" id="amareNombre" class="form-input"
                   value="<?= htmlspecialchars(($restaurante['nombre'] ?? '') . ' Admin') ?>"
                   placeholder="Admin Restaurante"
                   style="font-size:.82rem">
          </div>

          <div id="amareMsg" style="font-size:.78rem;margin-bottom:10px;display:none"></div>

          <button type="button" id="amareBtnRegistrar" onclick="registrarEnAmare()"
                  style="background:#0369A1;color:#fff;border:none;border-radius:8px;padding:8px 18px;
                         font-size:.84rem;font-weight:600;cursor:pointer;transition:.15s"
                  onmouseover="this.style.background='#0284C7'" onmouseout="this.style.background='#0369A1'">
            🚀 Registrar y Conectar
          </button>
          <span id="amareSpinner" style="display:none;margin-left:8px;font-size:.82rem;color:#6B7280">Conectando...</span>
        </div>
      </div>

      <script>
      async function registrarEnAmare() {
        const urlRaw  = document.getElementById('amareApiUrl').value.trim();
        // Sanitizar URL: quitar slash final para evitar doble slash
        const url     = urlRaw.replace(/\/+$/, '');
        const email   = document.getElementById('amareEmail').value.trim();
        const pass    = document.getElementById('amarePassword').value;
        const nombre  = document.getElementById('amareNombre').value.trim();
        const btn     = document.getElementById('amareBtnRegistrar');
        const spinner = document.getElementById('amareSpinner');
        const msg     = document.getElementById('amareMsg');

        msg.style.display = 'none';

        if (!url)   { mostrarMsg('error', 'Primero ingresa la URL de la API (ej: https://idactivos.digital/api_restaurante)'); return; }
        if (!email) { mostrarMsg('error', 'Ingresa un email'); return; }
        const nameVal = nombre || 'Admin';
        if (nameVal.length < 3) { mostrarMsg('error', 'El nombre debe tener al menos 3 caracteres'); return; }
        if (pass.length < 6) { mostrarMsg('error', 'La contraseña debe tener al menos 6 caracteres'); return; }

        // Guardar URL sanitizada en el input
        document.getElementById('amareApiUrl').value = url;

        btn.disabled = true; spinner.style.display = 'inline';

        try {
          // 1. Intentar registrar (Amare-App espera "name", no "nombre")
          const registerUrl = url + '/auth/register';
          console.log('[Amare] Registrando en:', registerUrl);
          let res = await fetch(registerUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: nameVal, email, password: pass })
          });
          let data = await res.json();

          // Si ya existe (409 Conflict u otro), no es error — seguimos al login
          if (!res.ok && res.status !== 409 && res.status !== 422) {
            throw new Error(data.message || data.error || 'Error al registrar');
          }

          // 2. Hacer login para obtener el token
          res = await fetch(url + '/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password: pass })
          });
          data = await res.json();

          if (!res.ok || !data.success) {
            throw new Error(data.message || data.error || 'Error al iniciar sesión');
          }

          const token = data.data?.token || data.token;
          if (!token) throw new Error('No se recibió el token');

          // 3. Guardar token y email en campos ocultos
          document.getElementById('amareApiToken').value = token;
          document.getElementById('amareEmailHidden').value = email;

          // Ocultar aviso de expiración si está visible
          const expiredDiv = document.getElementById('amareStatusExpired');
          if (expiredDiv) expiredDiv.style.display = 'none';

          // 4. Mostrar estado conectado
          document.getElementById('amareRegisterForm').style.display = 'none';
          const statusDiv = document.getElementById('amareStatusConnected');
          if (statusDiv) {
            statusDiv.style.display = '';
          } else {
            document.getElementById('amareRegisterForm').insertAdjacentHTML('beforebegin',
              '<div id="amareStatusConnected" style="background:#F0FDF4;border:1.5px solid #BBF7D0;border-radius:10px;padding:12px 14px;margin-bottom:14px">' +
              '<div style="display:flex;align-items:center;gap:8px;margin-bottom:6px">' +
              '<span style="font-size:1.1rem">✅</span>' +
              '<span style="font-weight:700;color:#065F46;font-size:.88rem">Conectado a la App Móvil</span>' +
              '</div>' +
              '<div style="font-size:.76rem;color:#166534;margin-bottom:8px">' +
              'La configuración se sincroniza automáticamente al guardar.' +
              '</div>' +
              '<button type="button" onclick="desconectarAmare()" ' +
              'style="font-size:.76rem;color:#DC2626;background:#FEF2F2;border:1px solid #FECACA;border-radius:6px;padding:4px 12px;cursor:pointer">' +
              'Desconectar</button></div>');
          }

          mostrarMsg('success', '✅ ¡Conectado! Guarda la configuración para sincronizar.');

        } catch (err) {
          mostrarMsg('error', '❌ ' + (err instanceof Error ? err.message : 'Error de conexión'));
        } finally {
          btn.disabled = false; spinner.style.display = 'none';
        }
      }

      function desconectarAmare() {
        document.getElementById('amareApiToken').value = '';
        document.getElementById('amareRegisterForm').style.display = '';
        const status = document.getElementById('amareStatusConnected');
        if (status) status.style.display = 'none';
        const expired = document.getElementById('amareStatusExpired');
        if (expired) expired.style.display = 'none';
        const msg = document.getElementById('amareMsg');
        msg.style.display = 'none';
      }

      function mostrarMsg(tipo, texto) {
        const msg = document.getElementById('amareMsg');
        msg.style.display = 'block';
        msg.style.color = tipo === 'success' ? '#065F46' : '#DC2626';
        msg.style.background = tipo === 'success' ? '#F0FDF4' : '#FEF2F2';
        msg.style.border = '1px solid ' + (tipo === 'success' ? '#BBF7D0' : '#FECACA');
        msg.style.borderRadius = '8px';
        msg.style.padding = '8px 12px';
        msg.textContent = texto;
      }
      </script>

      <!-- ══════════════════════════════════════════════════════════
           SECCIÓN: PAGOS DE COMENSALES
           ═════════════════════════════════════════════════════════ -->
      <hr style="border:none;border-top:1.5px solid #F3F4F6;margin:24px 0">
      <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px;
                  display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        Métodos de pago para comensales
      </div>

      <?php
        $metodosConfig = json_decode($cfgPagos['metodos_pago_habilitados'] ?? '["efectivo","tarjeta","transferencia","paypal"]', true) ?: ['efectivo','tarjeta','transferencia','paypal'];
        $metodosOpts = [
          'efectivo'       => '💵 Efectivo',
          'tarjeta'        => '💳 Tarjeta (Stripe)',
          'transferencia'  => '📲 Transferencia',
          'paypal'         => '🅿️ PayPal',
        ];
      ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">
        <?php foreach ($metodosOpts as $val => $label): ?>
        <label style="display:flex;align-items:center;gap:10px;padding:10px 12px;
                      border:1.5px solid #E5E7EB;border-radius:10px;cursor:pointer;
                      background:#F9FAFB;font-size:.88rem;font-weight:500">
          <input type="checkbox"
                 name="metodos_pago_habilitados[]"
                 value="<?= $val ?>"
                 <?= in_array($val, $metodosConfig) ? 'checked' : '' ?>
                 style="width:16px;height:16px;accent-color:#C8102E">
          <?= $label ?>
        </label>
        <?php endforeach; ?>
      </div>
      <div style="font-size:.75rem;color:#9CA3AF;margin-bottom:20px">
        Solo los métodos marcados aparecerán al comensal en la pantalla de pago.
        Al menos uno debe estar habilitado.
      </div>

      <!-- Stripe keys -->
      <div style="background:#F0F9FF;border:1.5px solid #BAE6FD;border-radius:12px;
                  padding:16px 18px;margin-bottom:16px">
        <div style="font-weight:600;color:#0C4A6E;font-size:.9rem;margin-bottom:12px">
          🔑 Credenciales Stripe <span style="font-size:.75rem;font-weight:400;color:#0369A1">(para pagos con tarjeta)</span>
        </div>
        <div class="form-group">
          <label class="form-label" style="font-size:.8rem">Publishable Key (pk_...)</label>
          <input type="text" name="stripe_public_key" class="form-input"
                 value="<?= htmlspecialchars($cfgPagos['stripe_public_key'] ?? '') ?>"
                 placeholder="pk_live_... o pk_test_..."
                 style="font-family:monospace;font-size:.8rem">
        </div>
        <div class="form-group" style="margin-bottom:0">
          <label class="form-label" style="font-size:.8rem">Secret Key (sk_...)</label>
          <input type="password" name="stripe_secret_key" class="form-input"
                 value="<?= empty($cfgPagos['stripe_secret_key'] ?? '') ? '' : '••••••••••••' ?>"
                 placeholder="sk_live_... o sk_test_... (déjalo vacío para no cambiarla)"
                 style="font-family:monospace;font-size:.8rem"
                 autocomplete="new-password">
          <div style="font-size:.72rem;color:#9CA3AF;margin-top:4px">
            ⚠️ La Secret Key solo se muestra enmascarada. Escribe una nueva solo si deseas cambiarla.
          </div>
        </div>
      </div>

      <!-- Notificación email -->
      <div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:12px;
                  padding:14px 16px;margin-bottom:20px">
        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;margin-bottom:10px">
          <input type="checkbox" name="notif_email_pago" value="1"
                 <?= ($cfgPagos['notif_email_pago'] ?? '0') === '1' ? 'checked' : '' ?>
                 id="chkNotifEmail"
                 onchange="document.getElementById('rowEmailDestino').style.display=this.checked?'block':'none'"
                 style="width:16px;height:16px;accent-color:#C8102E">
          <div>
            <div style="font-weight:600;font-size:.88rem;color:#111827">📧 Recibir email cuando un comensal pague</div>
            <div style="font-size:.75rem;color:#6B7280">Recibirás un resumen del pago al correo configurado abajo.</div>
          </div>
        </label>
        <div id="rowEmailDestino" style="display:<?= ($cfgPagos['notif_email_pago'] ?? '0') === '1' ? 'block' : 'none' ?>">
          <label class="form-label" style="font-size:.8rem">Email destino</label>
          <input type="email" name="notif_email_pago_destino" class="form-input"
                 value="<?= htmlspecialchars($cfgPagos['notif_email_pago_destino'] ?? '') ?>"
                 placeholder="admin@mirestaurante.com">
        </div>
      </div>

      <!-- ══════════════════════════════════════════════════════════
           SECCIÓN: CARNIHUB — PAGO DE PEDIDOS A PROVEEDOR
           ═════════════════════════════════════════════════════════ -->
      <?php if (!empty($cfgCarniHub)): ?>
      <hr style="border:none;border-top:1.5px solid #F3F4F6;margin:24px 0">
      <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px;
                  display:flex;align-items:center;gap:8px">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
        CarniHub — Pago de pedidos a proveedor
      </div>
      <div style="background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:12px;
                  padding:14px 16px;margin-bottom:14px;font-size:.8rem;color:#92400E">
        Cuando envíes un pedido de insumos a CarniHub, el sistema usará el método configurado aquí
        para procesar el cobro automáticamente.
      </div>

      <div class="form-group">
        <label class="form-label">Método de pago al proveedor</label>
        <select name="ch_metodo_pago" class="form-input"
                onchange="chOnMetodoChange(this.value)">
          <?php foreach (['stripe'=>'💳 Cargo automático con Stripe','paypal'=>'🅿️ PayPal','transferencia'=>'📲 Transferencia bancaria'] as $v => $lbl): ?>
          <option value="<?= $v ?>" <?= ($cfgCarniHub['metodo_pago'] ?? 'transferencia') === $v ? 'selected' : '' ?>>
            <?= $lbl ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div style="font-size:.74rem;color:#9CA3AF;margin-top:4px">
          Stripe y PayPal usan las mismas credenciales configuradas arriba en "Métodos de pago para comensales".
        </div>
      </div>

      <div id="chTransfPanel"
           style="display:<?= ($cfgCarniHub['metodo_pago'] ?? 'transferencia') === 'transferencia' ? 'block' : 'none' ?>;
                  background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:10px;padding:14px;margin-bottom:16px">
        <label class="form-label" style="font-size:.8rem">
          Instrucciones de transferencia (proporcionadas por CarniHub)
        </label>
        <textarea name="ch_instrucciones_transferencia" class="form-textarea" rows="4"
                  placeholder="Banco: BBVA&#10;CLABE: 012345678901234567&#10;Beneficiario: CarniHub S.A."><?= htmlspecialchars($cfgCarniHub['instrucciones_transferencia'] ?? '') ?></textarea>
        <div style="font-size:.73rem;color:#9CA3AF;margin-top:4px">
          Estas instrucciones se mostrarán al administrador al enviar un pedido de insumos.
        </div>
      </div>

      <!-- Panel tarjeta Stripe para cobro automático off-session -->
      <div id="chStripeCardPanel"
           style="display:<?= ($cfgCarniHub['metodo_pago'] ?? 'transferencia') === 'stripe' ? 'block' : 'none' ?>;
                  margin-bottom:16px">
        <div style="background:#F0F9FF;border:1.5px solid #BAE6FD;border-radius:10px;padding:14px">
          <div style="font-size:.84rem;font-weight:600;color:#0369A1;margin-bottom:10px">
            💳 Tarjeta para cobro automático
          </div>

          <?php if (!empty($cfgCarniHub['stripe_payment_method_id'])): ?>
          <div id="chCardSavedInfo" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">
            <span style="background:#fff;border:1.5px solid #E5E7EB;border-radius:8px;padding:6px 12px;
                         font-size:.9rem;letter-spacing:.05em;font-family:monospace">
              •••• •••• •••• <?= htmlspecialchars($cfgCarniHub['stripe_card_last4'] ?? '????') ?>
            </span>
            <span style="font-size:.78rem;color:#059669;font-weight:600">✓ Activa</span>
            <button type="button"
                  onclick="document.getElementById('chCardSavedInfo').style.display='none';document.getElementById('chCardInputWrap').style.display='block';if(window.chInitCard){window.chInitCard();}"
                    style="font-size:.76rem;color:#6B7280;background:none;border:1px solid #D1D5DB;border-radius:6px;padding:3px 10px;cursor:pointer">
              Cambiar tarjeta
            </button>
          </div>
          <?php endif; ?>

          <div id="chCardInputWrap" style="display:<?= empty($cfgCarniHub['stripe_payment_method_id']) ? 'block' : 'none' ?>">
            <div style="font-size:.78rem;color:#0C4A6E;margin-bottom:8px">
              Ingresa los datos de tu tarjeta. Se guardará de forma segura para cobros futuros.
            </div>
            <div id="chCardElement" style="padding:12px 14px;border:1.5px solid #BAE6FD;border-radius:10px;background:#fff;margin-bottom:10px"></div>
            <div id="chCardError" style="color:#EF4444;font-size:.78rem;margin-bottom:8px"></div>
            <button type="button" id="chBtnGuardarTarjeta" onclick="chGuardarTarjeta()"
                    style="background:#0369A1;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:.84rem;font-weight:600;cursor:pointer">
              Guardar tarjeta
            </button>
          </div>

          <div style="font-size:.73rem;color:#0C4A6E;margin-top:10px">
            Al enviar un pedido a CarniHub se cobrará automáticamente, sin necesidad de confirmar manualmente.
          </div>
        </div>
      </div>

      <?php endif; // if (!empty($cfgCarniHub)) ?>

<!-- Nota footer -->
      <div style="background:#F9FAFB;border-radius:8px;padding:12px;font-size:.8rem;color:#6B7280;margin-bottom:20px">
        El footer del menú siempre mostrará: <strong>Potenciado por CarniHub</strong>
      </div>

      <div style="display:flex;justify-content:flex-end">
        <?php if (!empty($bloqueadoPorCarniHub)): ?>
        <button type="button" class="btn btn-outline" disabled>
          Sincronizado por CarniHub
        </button>
        <?php else: ?>
        <button type="submit" class="btn btn-primary">Guardar configuración</button>
        <?php endif; ?>
      </div>
    </form>
  </div>

  <!-- QR del restaurante -->
  <div class="rst-card" style="margin-top:0">
    <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px;
                display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
      QR del menú público
    </div>
    <div style="display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
      <div>
        <div id="qrcanvas"></div>
      </div>
      <div style="flex:1;min-width:220px">
        <div style="font-size:.85rem;color:#6B7280;margin-bottom:8px;line-height:1.5">
          Imprime este QR y colócalo en cada mesa o en la entrada del restaurante.<br>
          Los clientes lo escanean para ver el menú y ordenar directamente desde su celular.
        </div>
        <div style="background:#F9FAFB;border-radius:8px;padding:10px 12px;font-size:.8rem;
                    color:#374151;word-break:break-all;font-family:monospace;margin-bottom:12px">
          <?= BASE_URL ?>menu/<?= htmlspecialchars($restaurante['slug'] ?? '') ?>
        </div>
        <a href="<?= BASE_URL ?>menu/<?= htmlspecialchars($restaurante['slug'] ?? '') ?>"
           target="_blank" class="btn btn-outline btn-sm">
          Ver menú público ↗
        </a>
      </div>
    </div>
  </div>
</div>

<script>
const BASE = '<?= BASE_URL ?>';
// ── CarniHub: método de pago + tarjeta guardada ──────────────────────────────
function chOnMetodoChange(val) {
  document.getElementById('chTransfPanel').style.display     = val === 'transferencia' ? 'block' : 'none';
  document.getElementById('chStripeCardPanel').style.display = val === 'stripe'        ? 'block' : 'none';
  if (val === 'stripe') setTimeout(chInitCard, 100);
}

(function() {
  const stripePk = '<?= htmlspecialchars($cfgPagos['stripe_public_key'] ?? '', ENT_QUOTES) ?>';
  let _chStripe, _chCard, _chCardMounted = false;

  window.chInitCard = function() {
    if (_chCardMounted) return;
    if (!stripePk) {
      document.getElementById('chCardError').textContent = 'Configura las claves Stripe en la sección "Métodos de pago" primero.';
      return;
    }
    if (typeof Stripe === 'undefined') {
      // Cargar Stripe.js dinámicamente si no está disponible
      const s = document.createElement('script');
      s.src = 'https://js.stripe.com/v3/';
      s.onload = function() { chInitCard(); };
      document.head.appendChild(s);
      return;
    }
    _chStripe = Stripe(stripePk);
    const elements = _chStripe.elements();
    _chCard = elements.create('card', { style: { base: { fontSize: '15px', color: '#111827' } } });
    _chCard.mount('#chCardElement');
    _chCard.on('change', function(e) {
      document.getElementById('chCardError').textContent = e.error ? e.error.message : '';
    });
    _chCardMounted = true;
  };

  // Auto-inicializar si Stripe ya está seleccionado
  if ('<?= ($cfgCarniHub['metodo_pago'] ?? '') ?>' === 'stripe' &&
      '<?= htmlspecialchars($cfgCarniHub['stripe_payment_method_id'] ?? '') ?>' === '') {
    if (typeof Stripe !== 'undefined') setTimeout(chInitCard, 200);
  }

  window.chGuardarTarjeta = async function() {
    if (!_chCard) {
      chInitCard();
      await new Promise(resolve => setTimeout(resolve, 250));
      if (!_chCard) {
        document.getElementById('chCardError').textContent = 'No se pudo inicializar el formulario de tarjeta. Verifica Stripe y vuelve a intentar.';
        return;
      }
    }
    const btn = document.getElementById('chBtnGuardarTarjeta');
    btn.disabled = true; btn.textContent = 'Guardando…';
    document.getElementById('chCardError').textContent = '';
    try {
      // 1. Obtener SetupIntent clientSecret
      const setupResp = await fetch(BASE + 'rest-config/setupCardCarniHub', { credentials: 'same-origin' });
      const setupData = await setupResp.json();
      if (!setupData.ok) throw new Error(setupData.error || 'Error al iniciar guardado');

      // 2. Confirmar tarjeta con Stripe
      const { setupIntent, error } = await _chStripe.confirmCardSetup(setupData.clientSecret, {
        payment_method: { card: _chCard }
      });
      if (error) throw new Error(error.message);
      if (setupIntent.status !== 'succeeded') throw new Error('No se pudo guardar la tarjeta');

      // 3. Guardar PM ID en backend
      const saveResp = await fetch(BASE + 'rest-config/guardarTarjetaCarniHub', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ payment_method_id: setupIntent.payment_method })
      });
      const saveData = await saveResp.json();
      if (!saveData.ok) throw new Error(saveData.error || 'Error al guardar');

      // 4. Mostrar tarjeta guardada en UI
      document.getElementById('chCardInputWrap').style.display = 'none';
      const last4 = saveData.last4 || '????';
      const existing = document.getElementById('chCardSavedInfo');
      if (existing) {
        existing.querySelector('span:first-child').textContent = '\u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 ' + last4;
        existing.style.display = 'flex';
      } else {
        document.getElementById('chCardInputWrap').insertAdjacentHTML('beforebegin',
          '<div id="chCardSavedInfo" style="display:flex;align-items:center;gap:10px;margin-bottom:10px;flex-wrap:wrap">' +
          '<span style="background:#fff;border:1.5px solid #E5E7EB;border-radius:8px;padding:6px 12px;' +
                       'font-size:.9rem;letter-spacing:.05em;font-family:monospace">' +
            '\u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 \u2022\u2022\u2022\u2022 ' + last4 +
          '</span>' +
          '<span style="font-size:.78rem;color:#059669;font-weight:600">\u2713 Activa</span>' +
          '<button type="button" onclick="document.getElementById(\'chCardSavedInfo\').style.display=\'none\';document.getElementById(\'chCardInputWrap\').style.display=\'block\';if(window.chInitCard){window.chInitCard();}"' +
                  ' style="font-size:.76rem;color:#6B7280;background:none;border:1px solid #D1D5DB;border-radius:6px;padding:3px 10px;cursor:pointer">' +
            'Cambiar tarjeta' +
          '</button></div>');
      }

      // Pequeño mensaje de confirmación
      const err = document.getElementById('chCardError');
      err.style.color = '#059669';
      err.textContent = '✓ Tarjeta guardada correctamente';
    } catch(e) {
      const err = document.getElementById('chCardError');
      err.style.color = '#EF4444';
      err.textContent = e.message;
    } finally {
      btn.disabled = false; btn.textContent = 'Guardar tarjeta';
    }
  };
})();

// ── Horarios ────────────────────────────────────────────────────────────────
const DIAS = ['lun','mar','mie','jue','vie','sab','dom'];

function toggleDia(dia, btn) {
  const row = document.getElementById('row_' + dia);
  const abierto = row.style.display !== 'none' && row.style.display !== '';
  if (abierto) {
    row.style.display = 'none';
    btn.style.background = '#fff';
    btn.style.color = '#6B7280';
    btn.style.borderColor = '#D1D5DB';
  } else {
    row.style.display = 'flex';
    btn.style.background = 'var(--cp)';
    btn.style.color = '#fff';
    btn.style.borderColor = 'var(--cp)';
  }
  actualizarHorariosJson();
}

function actualizarHorariosJson() {
  const data = {};
  DIAS.forEach(d => {
    const row = document.getElementById('row_' + d);
    const cerrado = !row || row.style.display === 'none' ? 1 : 0;
    const abre   = document.getElementById('abre_'  + d)?.value || '09:00';
    const cierra = document.getElementById('cierra_' + d)?.value || '22:00';
    data[d] = { abre, cierra, cerrado };
  });
  document.getElementById('horariosJson').value = JSON.stringify(data);
  // Update legacy fallback fields with Mon hours
  const mon = data['lun'];
  document.getElementById('legacyAbre').value  = mon?.cerrado ? '' : mon?.abre;
  document.getElementById('legacyCierra').value = mon?.cerrado ? '' : mon?.cierra;
}

// ── Color pickers ────────────────────────────────────────────────────────────
function bindColorPair(pickerId, textId) {
  const picker = document.getElementById(pickerId);
  const text = document.getElementById(textId);
  if (!picker || !text) return;

  picker.addEventListener('input', function() {
    text.value = this.value.toUpperCase();
  });
  text.addEventListener('input', function() {
    const value = this.value.trim();
    if (/^#[0-9a-fA-F]{6}$/.test(value)) {
      picker.value = value;
    }
  });
}
bindColorPair('cpicker', 'txtColorPri');
bindColorPair('spicker', 'txtColorSec');
bindColorPair('appBgPicker', 'txtAppBgColor');
bindColorPair('appBtnPicker', 'txtAppBtnColor');
bindColorPair('appBtnTextPicker', 'txtAppBtnTextColor');

// ── Address autocomplete con Nominatim ──────────────────────────────────────
(function() {
  const inp  = document.getElementById('inpDireccion');
  const sugg = document.getElementById('addrSugg');
  if (!inp || !sugg) return;
  let timer;
  inp.addEventListener('input', function() {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 4) { sugg.style.display = 'none'; return; }
    timer = setTimeout(function() {
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&addressdetails=0&q=' + encodeURIComponent(q))
        .then(function(r){ return r.json(); })
        .then(function(data) {
          if (!data || !data.length) { sugg.style.display = 'none'; return; }
          sugg.innerHTML = data.map(function(item) {
            var name = item.display_name.replace(/</g,'&lt;').replace(/>/g,'&gt;');
            return '<div class="addr-opt" onmousedown="addrSelect(event,this)" data-val="' + name.replace(/"/g,'&quot;') + '"'
              + ' style="padding:9px 13px;cursor:pointer;font-size:.82rem;color:#374151;border-bottom:1px solid #F3F4F6;display:flex;align-items:flex-start;gap:8px">'
              + '<span style="flex-shrink:0;color:var(--cp)">📍</span>'
              + '<span>' + name + '</span></div>';
          }).join('');
          sugg.style.display = 'block';
        })
        .catch(function(){ sugg.style.display = 'none'; });
    }, 420);
  });
  inp.addEventListener('blur', function() {
    setTimeout(function(){ sugg.style.display = 'none'; }, 200);
  });
  inp.addEventListener('focus', function() {
    if (sugg.innerHTML && this.value.length >= 4) sugg.style.display = 'block';
  });
})();
function addrSelect(e, el) {
  e.preventDefault();
  document.getElementById('inpDireccion').value = el.dataset.val;
  document.getElementById('addrSugg').style.display = 'none';
}

// Hover effect for suggestion options
document.addEventListener('mouseover', function(e) {
  if (e.target.closest('.addr-opt')) e.target.closest('.addr-opt').style.background = '#F9FAFB';
});
document.addEventListener('mouseout', function(e) {
  if (e.target.closest('.addr-opt')) e.target.closest('.addr-opt').style.background = '';
});
(function() {
  const script = document.createElement('script');
  script.src = 'https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js';
  script.onload = function() {
    new QRCode(document.getElementById('qrcanvas'), {
      text: '<?= addslashes(BASE_URL . 'menu/' . ($restaurante['slug'] ?? '')) ?>',
      width: 160, height: 160,
      colorDark: '<?= addslashes($restaurante['color_secundario'] ?? '#1f2937') ?>',
      colorLight: '#ffffff',
    });
  };
  document.head.appendChild(script);
})();

<?php if (!empty($bloqueadoPorCarniHub)): ?>
(function() {
  const form = document.querySelector('form[action="<?= BASE_URL ?>rest-config/guardar"]');
  if (!form) return;

  form.querySelectorAll('input, textarea, select').forEach(function(el) {
    // Mantener habilitados los hidden para no romper JS que los lee/escribe.
    if (el.type !== 'hidden') {
      el.disabled = true;
    }
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
  });
})();
<?php endif; ?>
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';

