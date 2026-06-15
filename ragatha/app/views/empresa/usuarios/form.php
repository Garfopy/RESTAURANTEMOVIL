<?php
// Vista: Formulario alta/edición de usuario de empresa
$editando = !empty($usuario);
$rolSelec = $usuario['rol_slug'] ?? '';
?>
<div style="max-width:640px">
  <a href="<?= BASE_URL ?>empresa-usuario/index"
     style="display:inline-flex;align-items:center;gap:4px;font-size:.875rem;color:#6B7280;text-decoration:none;margin-bottom:20px">
    ← Volver al equipo
  </a>

  <form method="POST" action="<?= BASE_URL ?><?= $editando ? 'empresa-usuario/actualizar/'.$usuario['id'] : 'empresa-usuario/guardar' ?>">

    <!-- Datos base del usuario -->
    <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:16px">
      <h2 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:#111827">
        <?= $editando ? 'Editar usuario' : 'Agregar usuario' ?>
      </h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Nombre *</label>
          <input type="text" name="nombre" required value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>"
                 style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Apellido paterno *</label>
          <input type="text" name="apellido_paterno" required value="<?= htmlspecialchars($usuario['apellido_paterno'] ?? '') ?>"
                 style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Apellido materno</label>
          <input type="text" name="apellido_materno" value="<?= htmlspecialchars($usuario['apellido_materno'] ?? '') ?>"
                 style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Teléfono *</label>
          <input type="tel" name="telefono" required value="<?= htmlspecialchars($usuario['telefono'] ?? '') ?>"
                 placeholder="10 dígitos"
                 maxlength="10" minlength="10" pattern="[0-9]{10}" inputmode="numeric"
                 oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)"
                 style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
      </div>

      <div style="margin-top:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Correo electrónico *</label>
        <input type="email" name="email" <?= $editando ? 'readonly style="background:#F9FAFB;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;width:100%;box-sizing:border-box"' : 'required style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;box-sizing:border-box"' ?>
               value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
        <?php if ($editando): ?>
          <p style="font-size:.75rem;color:#6B7280;margin-top:4px">El correo no se puede cambiar.</p>
        <?php endif; ?>
      </div>

      <?php if (!$editando): ?>
      <!-- Selector de rol (solo al crear) -->
      <div style="margin-top:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Rol del usuario *</label>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px" id="selectorRol">
          <?php foreach ($roles as $r): ?>
          <label id="card-<?= $r['id'] ?>"
                 style="display:flex;flex-direction:column;gap:6px;padding:14px;border:2px solid #E5E7EB;border-radius:10px;cursor:pointer;transition:border-color .15s">
            <input type="radio" name="rol_id" value="<?= $r['id'] ?>" data-slug="<?= htmlspecialchars($r['slug']) ?>"
                   style="display:none" required
                   onchange="seleccionarRol('<?= htmlspecialchars($r['slug']) ?>', <?= $r['id'] ?>)">
            <span style="font-size:1.4rem">
              <?php
              $emoji = ['comprador' => '🛒', 'supervisor' => '👁️', 'repartidor' => '🚚'];
              echo $emoji[$r['slug']] ?? '👤';
              ?>
            </span>
            <span style="font-size:.875rem;font-weight:700;color:#111827"><?= htmlspecialchars($r['nombre']) ?></span>
            <span style="font-size:.75rem;color:#6B7280">
              <?php
              $desc = [
                'comprador'  => 'Hace pedidos a tu empresa',
                'supervisor' => 'Aprueba pedidos y configura límites',
                'repartidor' => 'Realiza entregas con GPS',
              ];
              echo $desc[$r['slug']] ?? '';
              ?>
            </span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div style="margin-top:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Estado</label>
        <select name="activo" style="padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem">
          <option value="1" <?= ($usuario['activo'] ?? 1) ? 'selected' : '' ?>>Activo</option>
          <option value="0" <?= !($usuario['activo'] ?? 1) ? 'selected' : '' ?>>Inactivo</option>
        </select>
      </div>
      <?php endif; ?>
    </div>

    <!-- Campos específicos para COMPRADOR (ocultos hasta seleccionar rol) -->
    <?php if (!$editando): ?>
    <div id="campos-comprador" style="display:none;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:24px;margin-bottom:16px">
      <h3 style="font-size:.9rem;font-weight:700;color:#1E40AF;margin-bottom:4px">🛒 Datos del negocio comprador</h3>
      <p style="font-size:.8rem;color:#3B82F6;margin-bottom:16px">Esta información sirve para identificar el punto de entrega y para el tracking del pedido.</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="grid-column:1/-1">
          <label style="display:block;font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:4px">Nombre del negocio / tienda *</label>
          <input type="text" name="nombre_negocio" placeholder="Ej: Carnicería El Buen Corte"
                 style="width:100%;padding:9px 12px;border:1px solid #BFDBFE;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div style="grid-column:1/-1">
          <label style="display:block;font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:4px">Dirección completa de entrega *</label>
          <input type="text" name="direccion_entrega" placeholder="Calle, número, colonia"
                 style="width:100%;padding:9px 12px;border:1px solid #BFDBFE;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:4px">Ciudad / Municipio</label>
          <input type="text" name="ciudad" placeholder="Ej: Monterrey"
                 style="width:100%;padding:9px 12px;border:1px solid #BFDBFE;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:4px">Código postal</label>
          <input type="text" name="codigo_postal" placeholder="Ej: 64000" maxlength="10"
                 style="width:100%;padding:9px 12px;border:1px solid #BFDBFE;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:4px">Persona responsable de recibir</label>
          <input type="text" name="responsable_entrega" placeholder="Nombre de quien recibe"
                 style="width:100%;padding:9px 12px;border:1px solid #BFDBFE;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#1E40AF;margin-bottom:4px">Horario preferido de entrega</label>
          <input type="text" name="horario_entrega" placeholder="Ej: 7am – 12pm"
                 style="width:100%;padding:9px 12px;border:1px solid #BFDBFE;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
      </div>
    </div>

    <!-- Campos específicos para REPARTIDOR -->
    <div id="campos-repartidor" style="display:none;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:12px;padding:24px;margin-bottom:16px">
      <h3 style="font-size:.9rem;font-weight:700;color:#065F46;margin-bottom:4px">🚚 Datos del repartidor</h3>
      <p style="font-size:.8rem;color:#059669;margin-bottom:16px">Información personal y del vehículo para las rutas de entrega.</p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#065F46;margin-bottom:4px">Tipo de vehículo</label>
          <select name="tipo_vehiculo" style="width:100%;padding:9px 12px;border:1px solid #BBF7D0;border-radius:6px;font-size:.875rem">
            <option value="">Sin vehículo asignado</option>
            <option value="camioneta">Camioneta / Van</option>
            <option value="motocicleta">Motocicleta</option>
            <option value="camion">Camión de reparto</option>
            <option value="otro">Otro</option>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#065F46;margin-bottom:4px">Placas del vehículo</label>
          <input type="text" name="placas_vehiculo" placeholder="Ej: ABC-1234"
                 style="width:100%;padding:9px 12px;border:1px solid #BBF7D0;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#065F46;margin-bottom:4px">Marca / Modelo</label>
          <input type="text" name="vehiculo_modelo" placeholder="Ej: Toyota Hilux 2022"
                 style="width:100%;padding:9px 12px;border:1px solid #BBF7D0;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#065F46;margin-bottom:4px">Número de licencia</label>
          <input type="text" name="licencia" placeholder="Número de licencia de conducir"
                 style="width:100%;padding:9px 12px;border:1px solid #BBF7D0;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
      </div>
    </div>

    <!-- Aviso contraseña temporal -->
    <div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:8px;padding:12px 16px;margin-bottom:16px;font-size:.85rem;color:#92400E">
      💡 Se generará una contraseña temporal que debes comunicar al usuario para que pueda acceder por primera vez.
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:10px">
      <button type="submit"
              style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
        <?= $editando ? 'Guardar cambios' : 'Crear usuario' ?>
      </button>
      <a href="<?= BASE_URL ?>empresa-usuario/index"
         style="padding:10px 20px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
function seleccionarRol(slug, id) {
  // Resaltar card seleccionada
  document.querySelectorAll('#selectorRol > label').forEach(function(card) {
    card.style.borderColor = '#E5E7EB';
    card.style.background  = '#fff';
  });
  var card = document.getElementById('card-' + id);
  if (card) {
    card.style.borderColor = 'var(--color-primary)';
    card.style.background  = '#FEF2F2';
  }

  // Mostrar/ocultar sección de campos según rol
  document.getElementById('campos-comprador').style.display  = slug === 'comprador'  ? 'block' : 'none';
  document.getElementById('campos-repartidor').style.display = slug === 'repartidor' ? 'block' : 'none';

  // Campos requeridos dinámicos
  var reqComp = document.querySelectorAll('#campos-comprador input[type=text]');
  reqComp.forEach(function(el, i) {
    el.required = (slug === 'comprador' && i < 2); // Solo nombre_negocio y dirección son requeridos
  });
}
</script>
