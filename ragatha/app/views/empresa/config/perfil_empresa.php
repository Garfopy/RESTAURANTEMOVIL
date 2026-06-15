<?php if (!empty($flash['success'])): ?>
<div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#166534;display:flex;align-items:center;gap:8px">
  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
  <?= htmlspecialchars($flash['success']) ?>
</div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#B91C1C">
  <?= htmlspecialchars($flash['error']) ?>
</div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>empresa-config/empresa" enctype="multipart/form-data">

  <!-- Logo -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:20px">
    <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin-bottom:16px">Logo de la empresa</h2>
    <div style="display:flex;align-items:center;gap:20px">
      <div id="logo-preview-wrap" style="width:90px;height:90px;border-radius:10px;border:2px dashed #E5E7EB;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#F9FAFB;flex-shrink:0">
        <?php if (!empty($empresa['logo_path'])): ?>
          <img id="logo-preview" src="<?= htmlspecialchars(UPLOAD_URL . 'empresa/' . basename($empresa['logo_path'])) ?>"
               alt="Logo" style="width:100%;height:100%;object-fit:contain">
        <?php else: ?>
          <img id="logo-preview" src="" alt="" style="width:100%;height:100%;object-fit:contain;display:none">
          <svg id="logo-placeholder" width="28" height="28" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        <?php endif; ?>
      </div>
      <div>
        <label for="logo-input" style="display:inline-block;padding:8px 16px;border-radius:8px;background:var(--color-primary);color:#fff;font-size:.82rem;font-weight:600;cursor:pointer">
          <?= !empty($empresa['logo_path']) ? 'Cambiar logo' : 'Subir logo' ?>
        </label>
        <input type="file" id="logo-input" name="logo" accept="image/jpeg,image/png,image/webp"
               style="display:none" onchange="previewLogo(this)">
        <p style="font-size:.75rem;color:#9CA3AF;margin-top:6px">JPG, PNG o WEBP. Máximo 2 MB.</p>
        <?php if (!empty($empresa['logo_path'])): ?>
        <label>
          <input type="checkbox" name="eliminar_logo" value="1" style="accent-color:var(--color-primary)">
          <span style="font-size:.78rem;color:#6B7280;margin-left:4px">Eliminar logo actual</span>
        </label>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Datos generales -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:20px">
    <h2 style="font-size:.95rem;font-weight:700;color:#111827;margin-bottom:16px">Datos generales</h2>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div style="grid-column:1/-1">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Razón social</label>
        <input type="text" name="razon_social" class="form-control"
               value="<?= htmlspecialchars($empresa['razon_social'] ?? '') ?>"
               placeholder="Nombre legal de la empresa" required>
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">RFC</label>
        <input type="text" name="rfc" class="form-control"
               value="<?= htmlspecialchars($empresa['rfc'] ?? '') ?>"
               placeholder="RFC" maxlength="13"
               style="text-transform:uppercase"
               oninput="this.value=this.value.toUpperCase()">
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Correo electrónico</label>
        <input type="email" name="email" class="form-control"
               value="<?= htmlspecialchars($empresa['email'] ?? '') ?>"
               placeholder="correo@empresa.com">
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Teléfono</label>
        <input type="tel" name="telefono" class="form-control"
               value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>"
               placeholder="10 dígitos" maxlength="10" minlength="10"
               inputmode="numeric"
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
      </div>
      <div style="grid-column:1/-1">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Tipo de negocio</label>
        <select name="tipo_negocio" class="form-control">
          <?php
          $tipos = [
            ''            => '— Seleccionar —',
            'carniceria'  => 'Carnicería',
            'taqueria'    => 'Taquería',
            'restaurante' => 'Restaurante',
            'comedor'     => 'Comedor',
            'otro'        => 'Otro',
          ];
          $tipoActual = $empresa['tipo_negocio'] ?? '';
          foreach ($tipos as $val => $label):
          ?>
          <option value="<?= $val ?>" <?= $val === $tipoActual ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>

  <button type="submit" style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
    Guardar cambios
  </button>
</form>

<script>
function previewLogo(input) {
  var file = input.files[0];
  if (!file) return;
  var reader = new FileReader();
  reader.onload = function(e) {
    var img = document.getElementById('logo-preview');
    img.src = e.target.result;
    img.style.display = 'block';
    var placeholder = document.getElementById('logo-placeholder');
    if (placeholder) placeholder.style.display = 'none';
  };
  reader.readAsDataURL(file);
}
</script>
