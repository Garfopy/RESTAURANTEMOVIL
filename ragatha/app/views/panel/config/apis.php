<?php
// Vista: Configuración — APIs y servicios externos (superadmin)
$s  = fn(string $k, string $d = '') => htmlspecialchars($settings[$k] ?? $d);
$pw = fn(string $k) => empty($settings[$k] ?? '') ? '' : '••••••••'; // No mostrar contraseñas reales

$inputId = 0;
function toggleBtn(string $target): string {
    return '<button type="button" onclick="toggleVis(\''.$target.'\')"
             style="padding:8px 12px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280">
             Ver
             </button>';
}
?>
<script>
function toggleVis(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.type = el.type === 'password' ? 'text' : 'password';
  el.nextElementSibling.textContent = el.type === 'password' ? 'Ver' : 'Ocultar';
}
</script>

<!-- Tabs de sección -->
<div style="display:flex;gap:4px;margin-bottom:24px">
  <?php
  $tabs = ['general'=>'General','apis'=>'APIs y servicios','correo'=>'Correo'];
  foreach ($tabs as $slug => $label):
    $active = ($seccion === $slug);
  ?>
  <a href="<?= BASE_URL ?>config/<?= $slug ?>"
     style="padding:8px 16px;border-radius:8px;font-size:.85rem;font-weight:600;text-decoration:none;
            <?= $active ? 'background:var(--color-primary);color:#fff' : 'background:#fff;color:#374151;border:1px solid #E5E7EB' ?>">
    <?= $label ?>
  </a>
  <?php endforeach; ?>
</div>

<form method="POST" action="<?= BASE_URL ?>config/apis">

  <!-- Google Maps -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7z"/><circle cx="12" cy="9" r="2.5"/></svg>
      Google Maps
    </h3>
    <label class="form-label">API Key</label>
    <div style="display:flex">
      <input type="password" id="google_maps_key" name="google_maps_key"
             value="<?= $s('google_maps_key') ?>"
             class="form-control" style="border-radius:6px 0 0 6px;border-right:none;font-family:monospace;font-size:.85rem"
             placeholder="AIza...">
      <button type="button" onclick="toggleVis('google_maps_key')"
              style="padding:0 12px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
    </div>
    <p style="font-size:.75rem;color:#6B7280;margin-top:4px">Necesaria para los mapas en sucursales y logística</p>
  </div>

  <!-- WhatsApp -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg>
      WhatsApp Business API
    </h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label class="form-label">API Token</label>
        <div style="display:flex">
          <input type="password" id="whatsapp_api_token" name="whatsapp_api_token"
                 value="<?= $s('whatsapp_api_token') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none;font-family:monospace;font-size:.85rem">
          <button type="button" onclick="toggleVis('whatsapp_api_token')"
                  style="padding:0 12px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
      <div>
        <label class="form-label">Phone ID</label>
        <input type="text" name="whatsapp_phone_id" value="<?= $s('whatsapp_phone_id') ?>"
               class="form-control" placeholder="123456789012345">
      </div>
    </div>
    <div>
      <label class="form-label">Número de contacto (para enlace en login)</label>
      <input type="text" name="whatsapp_numero_contacto" value="<?= $s('whatsapp_numero_contacto') ?>"
             class="form-control" placeholder="+5219991234567" style="max-width:280px">
      <p style="font-size:.75rem;color:#6B7280;margin-top:4px">Número en formato internacional. Se usa en el enlace "Contacta al administrador" de la pantalla de login.</p>
    </div>
  </div>

  <!-- Traccar GPS -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
      Traccar (GPS en tiempo real)
    </h3>
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px">
      <div>
        <label class="form-label">URL del servidor</label>
        <input type="text" name="traccar_url" value="<?= $s('traccar_url') ?>"
               class="form-control" placeholder="https://traccar.tudominio.com">
      </div>
      <div>
        <label class="form-label">Usuario</label>
        <input type="text" name="traccar_user" value="<?= $s('traccar_user') ?>"
               class="form-control">
      </div>
      <div>
        <label class="form-label">Contraseña</label>
        <div style="display:flex">
          <input type="password" id="traccar_pass" name="traccar_pass"
                 value="<?= $s('traccar_pass') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
          <button type="button" onclick="toggleVis('traccar_pass')"
                  style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
    </div>
  </div>

  <!-- PayPal -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:6px;display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
      PayPal
    </h3>
    <!-- Selector de modo -->
    <div style="margin-bottom:16px">
      <label class="form-label">Modo activo</label>
      <select name="paypal_mode" id="paypal_mode" class="form-control" style="max-width:220px"
              onchange="togglePaypalCreds(this.value)">
        <option value="sandbox" <?= ($settings['paypal_mode'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (pruebas)</option>
        <option value="live"    <?= ($settings['paypal_mode'] ?? '') === 'live' ? 'selected' : '' ?>>Live (producción)</option>
      </select>
    </div>
    <!-- Sandbox -->
    <div id="paypal-sandbox-creds" style="padding:14px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;margin-bottom:12px">
      <p style="font-size:.75rem;font-weight:700;color:#166534;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">🧪 Sandbox (pruebas)</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label class="form-label">Client ID</label>
          <input type="text" name="paypal_client_id_sandbox"
                 value="<?= htmlspecialchars($settings['paypal_client_id_sandbox'] ?? $settings['paypal_client_id'] ?? '') ?>"
                 class="form-control" placeholder="AaBbCc...">
        </div>
        <div>
          <label class="form-label">Secret</label>
          <div style="display:flex">
            <input type="password" id="paypal_secret_sandbox" name="paypal_secret_sandbox"
                   value="<?= htmlspecialchars($settings['paypal_secret_sandbox'] ?? $settings['paypal_secret'] ?? '') ?>"
                   class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
            <button type="button" onclick="toggleVis('paypal_secret_sandbox')"
                    style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Live -->
    <div id="paypal-live-creds" style="padding:14px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px">
      <p style="font-size:.75rem;font-weight:700;color:#9A3412;margin-bottom:10px;text-transform:uppercase;letter-spacing:.05em">🚀 Live (producción)</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label class="form-label">Client ID</label>
          <input type="text" name="paypal_client_id_live"
                 value="<?= htmlspecialchars($settings['paypal_client_id_live'] ?? '') ?>"
                 class="form-control" placeholder="AaBbCc...">
        </div>
        <div>
          <label class="form-label">Secret</label>
          <div style="display:flex">
            <input type="password" id="paypal_secret_live" name="paypal_secret_live"
                   value="<?= htmlspecialchars($settings['paypal_secret_live'] ?? '') ?>"
                   class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
            <button type="button" onclick="toggleVis('paypal_secret_live')"
                    style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
          </div>
        </div>
      </div>
    </div>
    <!-- Input hidden para retrocompatibilidad -->
    <input type="hidden" name="paypal_client_id" value="">
    <input type="hidden" name="paypal_secret"    value="">
  </div>
  <script>
  function togglePaypalCreds(mode) {
    var sandbox = document.getElementById('paypal-sandbox-creds');
    var live    = document.getElementById('paypal-live-creds');
    if (mode === 'live') {
      sandbox.style.opacity = '0.5';
      live.style.opacity    = '1';
      live.style.outline    = '2px solid #F97316';
      sandbox.style.outline = 'none';
    } else {
      sandbox.style.opacity = '1';
      live.style.opacity    = '0.5';
      sandbox.style.outline = '2px solid #22C55E';
      live.style.outline    = 'none';
    }
  }
  togglePaypalCreds(document.getElementById('paypal_mode').value);
  </script>

  <!-- Factura-lo -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:4px">FacturaLO Plus (CFDI México)</h3>
    <p style="font-size:.75rem;color:#6B7280;margin-bottom:16px">
      Regístrate en <strong>facturaloplus.com</strong>, sube tu CSD y obtén el API Key.
      Convierte tus archivos .key y .cer a PEM con: <code style="background:#F3F4F6;padding:1px 5px;border-radius:4px">openssl pkcs8 -inform DER -in SAT.key -out key.pem</code>
      y <code style="background:#F3F4F6;padding:1px 5px;border-radius:4px">openssl x509 -inform DER -in SAT.cer -out cer.pem</code>
    </p>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label class="form-label">API Key (32 chars)</label>
        <div style="display:flex">
          <input type="password" id="facturalo_apikey" name="facturalo_apikey"
                 value="<?= $s('facturalo_apikey') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none;font-family:monospace;font-size:.82rem">
          <button type="button" onclick="toggleVis('facturalo_apikey')"
                  style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
      <div>
        <label class="form-label">Ambiente</label>
        <select name="facturalo_ambiente" class="form-control">
          <option value="dev" <?= ($settings['facturalo_ambiente'] ?? 'dev') === 'dev' ? 'selected' : '' ?>>Pruebas (dev)</option>
          <option value="app" <?= ($settings['facturalo_ambiente'] ?? '') === 'app' ? 'selected' : '' ?>>Producción (app)</option>
        </select>
      </div>
      <div>
        <label class="form-label">Plantilla PDF</label>
        <input type="text" name="facturalo_plantilla" value="<?= $s('facturalo_plantilla', '1') ?>"
               class="form-control" placeholder="1">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label class="form-label">RFC emisor</label>
        <input type="text" name="facturalo_rfc" value="<?= $s('facturalo_rfc') ?>"
               class="form-control" placeholder="ABC010101XYZ" style="text-transform:uppercase">
      </div>
      <div style="grid-column:span 2">
        <label class="form-label">Nombre / Razón social emisor</label>
        <input type="text" name="facturalo_nombre" value="<?= $s('facturalo_nombre') ?>"
               class="form-control" placeholder="MI EMPRESA SA DE CV">
      </div>
      <div>
        <label class="form-label">CP de expedición</label>
        <input type="text" name="facturalo_cp" value="<?= $s('facturalo_cp') ?>"
               class="form-control" placeholder="76000" maxlength="5">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label class="form-label">Régimen fiscal emisor</label>
        <select name="facturalo_regimen" class="form-control">
          <?php foreach ([
            '601'=>'601 - General de Ley Personas Morales',
            '612'=>'612 - Personas Físicas con Actividades Empresariales',
            '616'=>'616 - Sin obligaciones fiscales',
            '621'=>'621 - Incorporación Fiscal',
          ] as $clave => $label): ?>
          <option value="<?= $clave ?>" <?= ($settings['facturalo_regimen'] ?? '601') === $clave ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="form-label">Contraseña CSD (llave privada SAT)</label>
        <div style="display:flex">
          <input type="password" id="facturalo_csd_pass" name="facturalo_csd_pass"
                 value="<?= $s('facturalo_csd_pass') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
          <button type="button" onclick="toggleVis('facturalo_csd_pass')"
                  style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <label class="form-label">Llave privada PEM (.key convertido)</label>
        <textarea name="facturalo_key_pem" rows="5"
                  class="form-control" style="font-family:monospace;font-size:.72rem;resize:vertical"
                  placeholder="-----BEGIN ENCRYPTED PRIVATE KEY-----&#10;...&#10;-----END ENCRYPTED PRIVATE KEY-----"><?= htmlspecialchars($settings['facturalo_key_pem'] ?? '') ?></textarea>
      </div>
      <div>
        <label class="form-label">Certificado PEM (.cer convertido)</label>
        <textarea name="facturalo_cer_pem" rows="5"
                  class="form-control" style="font-family:monospace;font-size:.72rem;resize:vertical"
                  placeholder="-----BEGIN CERTIFICATE-----&#10;...&#10;-----END CERTIFICATE-----"><?= htmlspecialchars($settings['facturalo_cer_pem'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <!-- IoT — Shelly -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:14px">IoT — Shelly Cloud</h3>
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:14px">
      <div>
        <label class="form-label">API URL</label>
        <input type="text" name="shelly_api_url" value="<?= $s('shelly_api_url') ?>"
               class="form-control" placeholder="https://shelly-56-eu.shelly.cloud">
      </div>
      <div>
        <label class="form-label">Auth Key</label>
        <div style="display:flex">
          <input type="password" id="shelly_auth_key" name="shelly_auth_key"
                 value="<?= $s('shelly_auth_key') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
          <button type="button" onclick="toggleVis('shelly_auth_key')"
                  style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
    </div>
  </div>

  <!-- IoT — HikVision -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:14px">IoT — HikVision (Cámaras)</h3>
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:14px">
      <div>
        <label class="form-label">Host / IP</label>
        <input type="text" name="hikvision_host" value="<?= $s('hikvision_host') ?>"
               class="form-control" placeholder="192.168.1.100">
      </div>
      <div>
        <label class="form-label">Usuario</label>
        <input type="text" name="hikvision_user" value="<?= $s('hikvision_user') ?>"
               class="form-control">
      </div>
      <div>
        <label class="form-label">Contraseña</label>
        <div style="display:flex">
          <input type="password" id="hikvision_pass" name="hikvision_pass"
                 value="<?= $s('hikvision_pass') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
          <button type="button" onclick="toggleVis('hikvision_pass')"
                  style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
    </div>
  </div>

  <!-- cPanel FTP Management -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:14px;display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/></svg>
      cPanel FTP (Gestión automática de usuarios FTP)
    </h3>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label class="form-label">Host cPanel (con puerto)</label>
        <input type="text" name="cpanel_host" value="<?= $s('cpanel_host') ?>"
               class="form-control" placeholder="cpanel.tudominio.com:2083">
        <p style="font-size:.75rem;color:#6B7280;margin-top:4px">Usa puerto 2083 (HTTPS) o 2082 (HTTP)</p>
      </div>
      <div>
        <label class="form-label">Usuario principal de cPanel</label>
        <input type="text" name="cpanel_username" value="<?= $s('cpanel_username') ?>"
               class="form-control" placeholder="usuario_cpanel">
      </div>
    </div>
    <div style="margin-bottom:14px">
      <label class="form-label">API Token (Security > Manage API Tokens)</label>
      <div style="display:flex">
        <input type="password" id="cpanel_token" name="cpanel_token"
               value="<?= $s('cpanel_token') ?>"
               class="form-control" style="border-radius:6px 0 0 6px;border-right:none;font-family:monospace;font-size:.85rem">
        <button type="button" onclick="toggleVis('cpanel_token')"
                style="padding:0 12px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
      </div>
      <p style="font-size:.75rem;color:#6B7280;margin-top:4px">
        ⚠️ <strong>Importante:</strong> Generar token con permisos SOLO de "FTP" desde cPanel > Security > Manage API Tokens
      </p>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
      <div>
        <label class="form-label">Dominio principal</label>
        <input type="text" name="cpanel_domain" value="<?= $s('cpanel_domain') ?>"
               class="form-control" placeholder="tudominio.com">
      </div>
      <div>
        <label class="form-label">Directorio base FTP</label>
        <input type="text" name="cpanel_ftp_dir" value="<?= $s('cpanel_ftp_dir', '/public_html/uploads/usuarios/') ?>"
               class="form-control">
      </div>
      <div>
        <label class="form-label">Cuota por usuario (MB)</label>
        <input type="number" name="cpanel_ftp_quota" value="<?= $s('cpanel_ftp_quota', '500') ?>"
               class="form-control" min="50" max="10000">
      </div>
    </div>
  </div>

  <!-- Firebase Realtime Database -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:24px">
    <h3 style="font-size:.85rem;font-weight:700;color:#111827;margin-bottom:6px;display:flex;align-items:center;gap:8px">
      🔥 Firebase Realtime Database (Tracking GPS)
    </h3>
    <p style="font-size:.75rem;color:#6B7280;margin-bottom:14px">
      Usada para el tracking GPS en tiempo real del repartidor. Obtén las credenciales en
      <strong>console.firebase.google.com</strong> → tu proyecto → Configuración → Web App.
    </p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
      <div>
        <label class="form-label">API Key</label>
        <div style="display:flex">
          <input type="password" id="firebase_api_key" name="firebase_api_key"
                 value="<?= $s('firebase_api_key') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none;font-family:monospace;font-size:.82rem"
                 placeholder="AIza...">
          <button type="button" onclick="toggleVis('firebase_api_key')"
                  style="padding:0 10px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
      <div>
        <label class="form-label">Auth Domain</label>
        <input type="text" name="firebase_auth_domain" value="<?= $s('firebase_auth_domain') ?>"
               class="form-control" placeholder="tu-proyecto.firebaseapp.com">
      </div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px">
      <div>
        <label class="form-label">Database URL</label>
        <input type="text" name="firebase_database_url" value="<?= $s('firebase_database_url') ?>"
               class="form-control" placeholder="https://tu-proyecto-default-rtdb.firebaseio.com">
      </div>
      <div>
        <label class="form-label">Project ID</label>
        <input type="text" name="firebase_project_id" value="<?= $s('firebase_project_id') ?>"
               class="form-control" placeholder="tu-proyecto">
      </div>
      <div>
        <label class="form-label">App ID</label>
        <input type="text" name="firebase_app_id" value="<?= $s('firebase_app_id') ?>"
               class="form-control" placeholder="1:123...:web:abc...">
      </div>
    </div>
    <div style="margin-top:12px;padding:10px 14px;background:#FEF3C7;border-radius:8px;font-size:.75rem;color:#92400E">
      <strong>Reglas Firebase recomendadas</strong> (Realtime DB → Reglas):<br>
      <code style="font-family:monospace">{ "rules": { "tracking": { ".read": true, ".write": true } } }</code><br>
      En producción limita escritura solo desde tu dominio con reglas más estrictas.
    </div>
  </div>

  <div style="display:flex;align-items:center;gap:12px">
    <button type="submit" style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
      Guardar APIs
    </button>
    <p style="font-size:.75rem;color:#6B7280">Las claves se almacenan cifradas en la base de datos y solo son accesibles para el superadmin.</p>
  </div>
</form>
