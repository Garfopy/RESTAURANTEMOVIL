<?php ob_start(); ?>

<!-- Login link para compartir con staff -->
<div style="background:linear-gradient(135deg,var(--cp-light) 0%,#fff 100%);border:1px solid var(--cp);
            border-radius:12px;padding:16px 20px;margin-bottom:20px;display:flex;align-items:center;
            justify-content:space-between;gap:16px;flex-wrap:wrap">
  <div>
    <div style="font-weight:700;color:var(--cp);font-size:.92rem;margin-bottom:3px">
      🔗 Link de acceso del staff
    </div>
    <div style="font-family:monospace;font-size:.8rem;color:#374151;word-break:break-all">
      <?= htmlspecialchars($linkAcceso) ?>
    </div>
  </div>
  <div style="display:flex;gap:8px;flex-shrink:0">
    <button onclick="navigator.clipboard.writeText('<?= htmlspecialchars($linkAcceso, ENT_QUOTES) ?>');this.textContent='¡Copiado!';setTimeout(()=>this.textContent='Copiar',2000)"
            class="btn btn-outline btn-sm">Copiar</button>
    <a href="<?= htmlspecialchars($linkAcceso) ?>" target="_blank" class="btn btn-primary btn-sm">
      Abrir ↗
    </a>
  </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div></div>
  <div style="display:flex;gap:8px">
    <a href="<?= BASE_URL ?>rest-staff/turno" class="btn btn-outline btn-sm">📅 Turno de hoy</a>
    <button onclick="rstModal('modalStaff')" class="btn btn-primary btn-sm">+ Nuevo staff</button>
  </div>
</div>

<!-- Roles de acceso rápido -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px">
  <?php
  $roles = [
    ['slug'=>'mesero',  'label'=>'Mesero',  'icon'=>'🧑‍💼', 'desc'=>'Toma pedidos, atiende mesas', 'badge'=>'badge-blue'],
    ['slug'=>'chef',    'label'=>'Chef',    'icon'=>'👨‍🍳', 'desc'=>'Ve el KDS, marca platillos listos', 'badge'=>'badge-amber'],
    ['slug'=>'portero', 'label'=>'Portero', 'icon'=>'🔐', 'desc'=>'Escanea QR de entrada/salida', 'badge'=>'badge-green'],
  ];
  foreach ($roles as $r):
    $count = count(array_filter($staff, fn($s) => $s['rol_slug'] === $r['slug'] && $s['staff_activo']));
  ?>
  <div class="rst-card" style="padding:16px;cursor:pointer" onclick="preseleccionarRol('<?= $r['slug'] ?>')">
    <div style="font-size:1.6rem;margin-bottom:6px"><?= $r['icon'] ?></div>
    <div style="font-weight:700;font-size:.95rem"><?= $r['label'] ?></div>
    <div style="font-size:.8rem;color:#6B7280;margin:3px 0 8px"><?= $r['desc'] ?></div>
    <span class="badge <?= $r['badge'] ?>"><?= $count ?> activo<?= $count !== 1 ? 's' : '' ?></span>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabla staff -->
<div class="rst-table-wrap">
  <table class="rst-table">
    <thead>
      <tr>
        <th>Nombre</th>
        <th>Correo</th>
        <th>Rol</th>
        <th>Código</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($staff as $s): ?>
      <tr>
        <td style="font-weight:600"><?= htmlspecialchars($s['nombre']) ?></td>
        <td style="color:#6B7280;font-size:.85rem"><?= htmlspecialchars($s['email']) ?></td>
        <td>
          <?php
          $badgeRol = ['mesero'=>'badge-blue','chef'=>'badge-amber','portero'=>'badge-green'][$s['rol_slug']] ?? 'badge-gray';
          ?>
          <span class="badge <?= $badgeRol ?>"><?= htmlspecialchars($s['rol_nombre']) ?></span>
        </td>
        <td style="font-family:monospace;font-size:.85rem;font-weight:600"><?= htmlspecialchars($s['codigo'] ?? '') ?></td>
        <td>
          <span class="badge <?= $s['staff_activo'] ? 'badge-green' : 'badge-red' ?>">
            <?= $s['staff_activo'] ? 'Activo' : 'Inactivo' ?>
          </span>
        </td>
        <td>
          <?php if ($s['staff_activo']): ?>
          <a href="<?= BASE_URL ?>rest-staff/desactivar/<?= $s['id'] ?>"
             onclick="return confirm('¿Desactivar a <?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>?')"
             class="btn btn-danger btn-sm">Desactivar</a>
          <?php else: ?>
          <a href="<?= BASE_URL ?>rest-staff/activar/<?= $s['id'] ?>"
             onclick="return confirm('¿Reactivar a <?= htmlspecialchars($s['nombre'], ENT_QUOTES) ?>?')"
             class="btn btn-success btn-sm">Activar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($staff)): ?>
      <tr>
        <td colspan="6">
          <div class="empty-state">
            <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <div style="font-size:.95rem;font-weight:600;color:#374151;margin-bottom:4px">Sin staff registrado</div>
            <div>Crea cuentas para tus meseros, chefs y porteros</div>
          </div>
        </td>
      </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Modal crear staff -->
<div id="modalStaff" class="rst-modal-backdrop">
  <div class="rst-modal">
    <div class="rst-modal-header">
      <div class="rst-modal-title">Agregar staff</div>
      <button class="rst-modal-close" onclick="rstModal('modalStaff')">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>rest-staff/crear">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="form-group" style="grid-column:span 2">
          <label class="form-label">Rol *</label>
          <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:4px">
            <?php foreach ($roles as $r): ?>
            <label class="rol-lbl" style="display:flex;flex-direction:column;align-items:center;padding:12px 8px;
                          border:2px solid #E5E7EB;border-radius:10px;cursor:pointer;transition:.15s;text-align:center">
              <input type="radio" name="rol_slug" value="<?= $r['slug'] ?>" class="rol-radio" style="display:none">
              <span style="font-size:1.4rem;margin-bottom:4px"><?= $r['icon'] ?></span>
              <span style="font-weight:700;font-size:.82rem"><?= $r['label'] ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="form-group" style="grid-column:span 2">
          <label class="form-label">Nombre completo *</label>
          <input type="text" name="nombre" class="form-input" required placeholder="Nombre del trabajador">
        </div>
        <div class="form-group" style="grid-column:span 2">
          <label class="form-label">Correo electrónico *</label>
          <input type="email" name="email" class="form-input" required placeholder="correo@ejemplo.com">
        </div>
        <div class="form-group">
          <label class="form-label">Contraseña *</label>
          <input type="password" name="password" class="form-input" required
                 placeholder="Min. 6 caracteres" minlength="6">
        </div>
        <div class="form-group">
          <label class="form-label">Código <span style="color:#9CA3AF;font-weight:400">(auto)</span></label>
          <input type="text" name="codigo" class="form-input" placeholder="Ej: ME001"
                 style="text-transform:uppercase">
        </div>
      </div>
      <div style="background:#F0FDF4;border-radius:8px;padding:10px 12px;font-size:.8rem;color:#166534;margin-bottom:4px">
        El staff iniciará sesión en <strong><?= BASE_URL ?>acceso/<?= htmlspecialchars($restaurante['slug'] ?? '') ?></strong> con su correo y contraseña.
      </div>
      <div class="rst-modal-footer">
        <button type="button" onclick="rstModal('modalStaff')" class="btn btn-outline">Cancelar</button>
        <button type="submit" class="btn btn-primary">Crear cuenta</button>
      </div>
    </form>
  </div>
</div>

<script>
function rstModal(id) {
  document.getElementById(id).classList.toggle('open');
}
document.querySelectorAll('.rst-modal-backdrop').forEach(bd => {
  bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});

// Rol radio buttons
document.querySelectorAll('.rol-lbl').forEach(lbl => {
  lbl.addEventListener('click', () => {
    document.querySelectorAll('.rol-lbl').forEach(l => l.style.borderColor = '#E5E7EB');
    lbl.style.borderColor = 'var(--cp)';
    lbl.querySelector('.rol-radio').checked = true;
  });
});
// Seleccionar mesero por defecto
const firstRol = document.querySelector('.rol-lbl');
if (firstRol) firstRol.click();

function preseleccionarRol(slug) {
  const lbl = document.querySelector(`.rol-lbl input[value="${slug}"]`)?.closest('.rol-lbl');
  if (lbl) {
    document.querySelectorAll('.rol-lbl').forEach(l => l.style.borderColor = '#E5E7EB');
    lbl.style.borderColor = 'var(--cp)';
    lbl.querySelector('.rol-radio').checked = true;
  }
  document.getElementById('modalStaff').classList.add('open');
}
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
