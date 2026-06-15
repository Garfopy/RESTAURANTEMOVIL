<?php
// Variables: $usuario (null=nuevo, array=editar), $roles[], $empresas[]
$esEdicion = $usuario !== null;
$accion    = $esEdicion
    ? BASE_URL . 'panel-usuario/actualizar/' . $usuario['id']
    : BASE_URL . 'panel-usuario/guardar';
?>
<div style="max-width:580px">
  <a href="<?= BASE_URL ?>panel-usuario/index"
     style="display:inline-flex;align-items:center;gap:6px;color:#6B7280;font-size:.875rem;text-decoration:none;margin-bottom:20px">
    ← Volver a usuarios
  </a>

  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:24px">
    <h3 style="margin:0 0 20px;font-size:1rem;font-weight:700;color:#111827">
      <?= $esEdicion ? 'Editar usuario' : 'Nuevo usuario de plataforma' ?>
    </h3>

    <form method="POST" action="<?= $accion ?>">

      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:14px">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Nombre *</label>
          <input type="text" name="nombre" required value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>"
                 style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Apellido paterno</label>
          <input type="text" name="apellido_paterno" value="<?= htmlspecialchars($usuario['apellido_paterno'] ?? '') ?>"
                 style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Apellido materno</label>
          <input type="text" name="apellido_materno" value="<?= htmlspecialchars($usuario['apellido_materno'] ?? '') ?>"
                 style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
        </div>
      </div>

      <?php if (!$esEdicion): ?>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Email *</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
               style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
      </div>
      <?php else: ?>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#6B7280;margin-bottom:5px">Email (no editable)</label>
        <div style="padding:8px 12px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;font-size:.875rem;color:#374151">
          <?= htmlspecialchars($usuario['email']) ?>
        </div>
      </div>
      <?php endif; ?>

      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Teléfono</label>
        <input type="tel" name="telefono" value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>"
               maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
               placeholder="10 dígitos"
               oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)"
               style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
      </div>

      <?php if (!$esEdicion): ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Rol *</label>
          <select name="rol_slug" required id="sel-rol" onchange="toggleEmpresa(this.value)"
                  style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box">
            <option value="">Seleccionar...</option>
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['slug'] ?>" <?= ($usuario['rol_slug'] ?? '') === $r['slug'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($r['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div id="campo-empresa">
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Empresa</label>
          <select name="empresa_id"
                  style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box">
            <option value="">Sin empresa (admin plataforma)</option>
            <?php foreach ($empresas as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= ($usuario['empresa_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($emp['razon_social']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <?php endif; ?>

      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">
          Contraseña <?= $esEdicion ? '(dejar vacío para no cambiar)' : '*' ?>
        </label>
        <div style="position:relative">
          <input type="password" id="inp-password" name="password" <?= $esEdicion ? '' : 'required' ?> autocomplete="new-password"
                 minlength="6" placeholder="Mínimo 6 caracteres" oninput="onPasswordInput()"
                 style="width:100%;padding:8px 38px 8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
          <button type="button" onclick="togglePass('inp-password','eye-1')"
                  style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0;display:flex;align-items:center">
            <svg id="eye-1" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
      </div>

      <div id="campo-confirmar" style="margin-bottom:20px;<?= $esEdicion ? '' : '' ?>">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">
          Confirmar contraseña <?= $esEdicion ? '(solo si cambias la contraseña)' : '*' ?>
        </label>
        <div style="position:relative">
          <input type="password" id="inp-confirm" name="password_confirm" <?= $esEdicion ? '' : 'required' ?> autocomplete="new-password"
                 minlength="6" placeholder="Repite la contraseña" oninput="checkMatch()"
                 style="width:100%;padding:8px 38px 8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
          <button type="button" onclick="togglePass('inp-confirm','eye-2')"
                  style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9CA3AF;padding:0;display:flex;align-items:center">
            <svg id="eye-2" xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
        <p id="msg-mismatch" style="color:#DC2626;font-size:.75rem;margin-top:4px;display:none">Las contraseñas no coinciden</p>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit" id="btn-submit"
                style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
          <?= $esEdicion ? 'Guardar cambios' : 'Crear usuario' ?>
        </button>
        <a href="<?= BASE_URL ?>panel-usuario/index"
           style="padding:10px 20px;background:#F3F4F6;color:#374151;border-radius:8px;font-size:.875rem;text-decoration:none;font-weight:600">
          Cancelar
        </a>
      </div>

    </form>
  </div>
</div>

<script>
function toggleEmpresa(rol) {
  const campo = document.getElementById('campo-empresa');
  if (campo) {
    // Ya no hay rol 'admin', solo 'admin_empresa' requiere empresa_id
    campo.style.opacity = (rol === 'admin_empresa') ? '1' : '0.5';
  }
}

function togglePass(inputId, eyeId) {
  const inp = document.getElementById(inputId);
  const eye = document.getElementById(eyeId);
  const show = inp.type === 'password';
  inp.type = show ? 'text' : 'password';
  eye.innerHTML = show
    ? '<path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>'
    : '<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
}

function checkMatch() {
  const pass    = document.getElementById('inp-password').value;
  const confirm = document.getElementById('inp-confirm').value;
  const msg     = document.getElementById('msg-mismatch');
  const btn     = document.getElementById('btn-submit');
  const mismatch = confirm !== '' && pass !== confirm;
  msg.style.display = mismatch ? 'block' : 'none';
  document.getElementById('inp-confirm').style.borderColor = mismatch ? '#DC2626' : '#D1D5DB';
  btn.disabled = mismatch;
}

function onPasswordInput() {
  // Si el campo de contraseña está vacío en modo edición, limpiar también confirmar
  const pass = document.getElementById('inp-password').value;
  const confirmField = document.getElementById('inp-confirm');
  if (pass === '') {
    confirmField.value = '';
    document.getElementById('msg-mismatch').style.display = 'none';
    confirmField.style.borderColor = '#D1D5DB';
    document.getElementById('btn-submit').disabled = false;
  }
  checkMatch();
}

// Bloquear submit si no coinciden
document.querySelector('form').addEventListener('submit', function(e) {
  const pass    = document.getElementById('inp-password').value;
  const confirm = document.getElementById('inp-confirm').value;
  if (pass !== '' && pass !== confirm) {
    e.preventDefault();
    document.getElementById('msg-mismatch').style.display = 'block';
    document.getElementById('inp-confirm').focus();
  }
});
</script>
