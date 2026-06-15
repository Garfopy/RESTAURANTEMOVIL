<?php
// Vista: Configuración — Correo SMTP (superadmin)
$s = fn(string $k, string $d = '') => htmlspecialchars($settings[$k] ?? $d);
?>

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

<script>
function toggleVis(id) {
  const el = document.getElementById(id);
  if (!el) return;
  el.type = el.type === 'password' ? 'text' : 'password';
  el.nextElementSibling.textContent = el.type === 'password' ? 'Ver' : 'Ocultar';
}
</script>

<form method="POST" action="<?= BASE_URL ?>config/correo">

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:20px">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:6px">Servidor de correo (SMTP)</h2>
    <p style="font-size:.8rem;color:#6B7280;margin-bottom:20px">
      Usado para envío de credenciales de usuario y notificaciones automáticas.
      PHPMailer está integrado y listo para usar.
    </p>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <label class="form-label">Servidor SMTP (host)</label>
        <input type="text" name="smtp_host" class="form-control" value="<?= $s('smtp_host') ?>"
               placeholder="smtp.gmail.com">
      </div>
      <div>
        <label class="form-label">Puerto</label>
        <input type="number" name="smtp_port" class="form-control" value="<?= $s('smtp_port', '587') ?>"
               placeholder="587" min="1" max="65535">
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <label class="form-label">Usuario SMTP</label>
        <input type="text" name="smtp_username" class="form-control" value="<?= $s('smtp_username') ?>"
               placeholder="notificaciones@tudominio.com">
      </div>
      <div>
        <label class="form-label">Contraseña SMTP</label>
        <div style="display:flex">
          <input type="password" id="smtp_pass" name="smtp_password"
                 value="<?= $s('smtp_password') ?>"
                 class="form-control" style="border-radius:6px 0 0 6px;border-right:none">
          <button type="button" onclick="toggleVis('smtp_pass')"
                  style="padding:0 12px;border:1px solid #E5E7EB;border-left:none;border-radius:0 6px 6px 0;background:#F9FAFB;cursor:pointer;font-size:.8rem;color:#6B7280;white-space:nowrap">Ver</button>
        </div>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
      <div>
        <label class="form-label">Nombre remitente</label>
        <input type="text" name="smtp_from_name" class="form-control" value="<?= $s('smtp_from_name', 'CarniHub') ?>"
               placeholder="CarniHub">
        <p style="font-size:.75rem;color:#6B7280;margin-top:4px">
          Nombre que aparecerá como remitente
        </p>
      </div>
      <div>
        <label class="form-label">Cifrado</label>
        <select name="smtp_encryption" class="form-control">
          <option value="tls" <?= $s('smtp_encryption', 'tls') === 'tls' ? 'selected' : '' ?>>TLS (recomendado)</option>
          <option value="ssl" <?= $s('smtp_encryption') === 'ssl' ? 'selected' : '' ?>>SSL</option>
          <option value="" <?= $s('smtp_encryption') === '' ? 'selected' : '' ?>>Sin cifrado</option>
        </select>
      </div>
    </div>

    <div style="margin-bottom:8px">
      <label class="form-label">Correo remitente (email)</label>
      <input type="email" name="smtp_from_email" class="form-control" value="<?= $s('smtp_from_email') ?>"
             placeholder="no-reply@tudominio.com" style="max-width:400px">
      <p style="font-size:.75rem;color:#6B7280;margin-top:4px">
        Este correo aparecerá como remitente en todos los mensajes automáticos.
      </p>
    </div>
  </div>

  <div style="background:#DBEAFE;border:1px solid #3B82F6;border-radius:10px;padding:14px 18px;margin-bottom:20px;display:flex;gap:10px;align-items:flex-start">
    <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="#2563EB" style="flex-shrink:0;margin-top:1px"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <div>
      <p style="font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:2px">Configura tus credenciales SMTP</p>
      <p style="font-size:.75rem;color:#1E40AF">Para Gmail, usa una App Password de 16 dígitos (genera en: myaccount.google.com/apppasswords). Para otros proveedores (SendGrid, SES, Mailgun), usa sus credenciales SMTP.</p>
    </div>
  </div>

  <button type="submit" style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
    Guardar configuración de correo
  </button>
</form>
