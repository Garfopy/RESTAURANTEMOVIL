<?php
// Vista: Configuración General (superadmin)
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

<form method="POST" action="<?= BASE_URL ?>config/general" enctype="multipart/form-data">

  <!-- Identidad -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:20px">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:20px">Identidad de la plataforma</h2>

    <div style="margin-bottom:16px">
      <label class="form-label">Nombre de la plataforma</label>
      <input type="text" name="app_name" class="form-control" value="<?= $s('app_name', 'CarniHub') ?>" required maxlength="80">
    </div>

    <!-- Logo -->
    <div style="margin-bottom:16px">
      <label class="form-label">Logo</label>
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:8px">
        <?php if (!empty($settings['app_logo'])): ?>
          <img src="<?= $s('app_logo') ?>" alt="Logo actual" style="height:48px;object-fit:contain;border:1px solid #E5E7EB;border-radius:8px;padding:4px">
          <span style="font-size:.8rem;color:#6B7280">Logo actual</span>
        <?php else: ?>
          <div style="width:64px;height:48px;border:2px dashed #D1D5DB;border-radius:8px;display:flex;align-items:center;justify-content:center">
            <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#9CA3AF"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          </div>
          <span style="font-size:.8rem;color:#9CA3AF">Sin logo</span>
        <?php endif; ?>
      </div>
      <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,.svg" class="form-control" style="font-size:.85rem">
      <p style="font-size:.75rem;color:#6B7280;margin-top:4px">JPG, PNG, WebP o SVG · Máx 2 MB · Recomendado: fondo transparente</p>
    </div>
  </div>

  <!-- Colores -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:20px">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:20px">Colores de la plataforma</h2>
    <p style="font-size:.8rem;color:#6B7280;margin-bottom:16px">Los cambios se aplican en el próximo inicio de sesión o recarga de página.</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
      <div>
        <label class="form-label">Color primario</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="color" name="color_primary" value="<?= $s('color_primary', '#C8102E') ?>"
                 style="width:40px;height:40px;padding:2px;border:1px solid #E5E7EB;border-radius:6px;cursor:pointer"
                 oninput="document.getElementById('hex_primary').value=this.value">
          <input type="text" id="hex_primary" value="<?= $s('color_primary', '#C8102E') ?>"
                 class="form-control" style="width:100px;font-family:monospace;font-size:.85rem"
                 oninput="document.querySelector('[name=color_primary]').value=this.value">
        </div>
      </div>
      <div>
        <label class="form-label">Color secundario (sidebar)</label>
        <div style="display:flex;align-items:center;gap:10px">
          <input type="color" name="color_secondary" value="<?= $s('color_secondary', '#1f2937') ?>"
                 style="width:40px;height:40px;padding:2px;border:1px solid #E5E7EB;border-radius:6px;cursor:pointer"
                 oninput="document.getElementById('hex_secondary').value=this.value">
          <input type="text" id="hex_secondary" value="<?= $s('color_secondary', '#1f2937') ?>"
                 class="form-control" style="width:100px;font-family:monospace;font-size:.85rem"
                 oninput="document.querySelector('[name=color_secondary]').value=this.value">
        </div>
      </div>
    </div>
  </div>

  <!-- Contacto -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:24px">
    <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:20px">Datos de contacto</h2>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
      <div>
        <label class="form-label">Teléfono de contacto</label>
        <input type="text" name="telefono_contacto" class="form-control" value="<?= $s('telefono_contacto') ?>" maxlength="20">
      </div>
      <div>
        <label class="form-label">Horarios de atención</label>
        <input type="text" name="horarios_atencion" class="form-control" value="<?= $s('horarios_atencion', 'Lun-Vie 8am-6pm') ?>" maxlength="100">
      </div>
    </div>
  </div>

  <button type="submit" style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
    Guardar configuración
  </button>
</form>
