<?php
// Vista: Vehículos
$totalVeh  = count($vehiculos);
$activos   = count(array_filter($vehiculos, fn($v) => $v['activo']));
$inactivos = $totalVeh - $activos;
$asignados = count(array_filter($vehiculos, fn($v) => !empty($v['repartidor_id'])));
?>

<!-- Guía para el admin -->
<div style="background:#FDF4FF;border:1px solid #E9D5FF;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start">
  <span style="font-size:1.2rem;flex-shrink:0">💡</span>
  <div>
    <div style="font-size:.82rem;font-weight:700;color:#6D28D9;margin-bottom:2px">Flota de vehículos</div>
    <div style="font-size:.78rem;color:#7C3AED">Registra tus vehículos y asígnalos a repartidores. Un repartidor solo puede tener un vehículo activo a la vez. Los vehículos sin repartidor asignado no pueden ser usados en rutas. Ve a <strong>Mi equipo</strong> para gestionar a tus repartidores.</div>
  </div>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <p style="font-size:.875rem;color:#6B7280">Flota de vehículos y asignación a repartidores.</p>
  </div>
  <button onclick="document.getElementById('formNuevo').classList.toggle('hidden')"
          style="padding:8px 16px;background:var(--color-primary);color:#fff;border:none;border-radius:6px;font-weight:600;font-size:.875rem;cursor:pointer">
    + Nuevo vehículo
  </button>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px">
  <?php foreach ([['Total',$totalVeh,'#1E40AF','#DBEAFE'],['Activos',$activos,'#065F46','#D1FAE5'],['Inactivos',$inactivos,'#92400E','#FEF3C7'],['Con repartidor',$asignados,'#5B21B6','#EDE9FE']] as [$lbl,$val,$c,$bg]): ?>
  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:16px;text-align:center">
    <div style="font-size:1.6rem;font-weight:800;color:<?= $c ?>"><?= $val ?></div>
    <div style="font-size:.78rem;color:#6B7280;font-weight:600;margin-top:2px"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Formulario nuevo vehículo (colapsable) -->
<div id="formNuevo" class="hidden" style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:20px;margin-bottom:20px">
  <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:14px">Registrar nuevo vehículo</div>
  <form method="POST" action="<?= BASE_URL ?>empresa-vehiculo/guardar">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px">

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Placa *</label>
        <input type="text" name="placa" required maxlength="20" placeholder="Ej: ABC-1234"
               style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
      </div>

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Modelo / Descripción</label>
        <input type="text" name="modelo" maxlength="100" placeholder="Ej: Toyota Hilux 2022"
               style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
      </div>

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Capacidad (kg)</label>
        <input type="number" name="capacidad" min="0" step="0.01" placeholder="Sin límite"
               style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
      </div>

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Asignar repartidor</label>
        <select name="repartidor_id" style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
          <option value="">— Sin asignar —</option>
          <?php foreach ($repartidores as $r): ?>
          <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido_paterno']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

    </div>
    <div style="display:flex;gap:10px">
      <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.875rem">
        💾 Guardar vehículo
      </button>
      <button type="button" onclick="document.getElementById('formNuevo').classList.add('hidden')"
              style="padding:9px 16px;background:#F3F4F6;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem">
        Cancelar
      </button>
    </div>
  </form>
</div>

<!-- Cards de vehículos -->
<?php if (empty($vehiculos)): ?>
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:48px;text-align:center;color:#9CA3AF">
  <div style="font-size:2.5rem;margin-bottom:12px">🚛</div>
  <p style="font-weight:600;font-size:.95rem">No hay vehículos registrados</p>
  <p style="font-size:.85rem;margin-top:4px">Agrega un vehículo para asignarlo a tus repartidores.</p>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px">

  <?php foreach ($vehiculos as $v): ?>
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden;<?= !$v['activo'] ? 'opacity:.65' : '' ?>">

    <!-- Top bar -->
    <div style="height:5px;background:<?= $v['activo'] ? 'linear-gradient(90deg,var(--color-primary),#7C3AED)' : '#E5E7EB' ?>"></div>

    <div style="padding:16px">

      <!-- Placa + estado -->
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
        <div style="font-size:1.1rem;font-weight:800;color:#111827;letter-spacing:.05em"><?= htmlspecialchars($v['placa']) ?></div>
        <span style="font-size:.68rem;padding:2px 8px;border-radius:999px;font-weight:600;background:<?= $v['activo'] ? '#D1FAE5' : '#F3F4F6' ?>;color:<?= $v['activo'] ? '#065F46' : '#6B7280' ?>">
          <?= $v['activo'] ? '● Activo' : '● Inactivo' ?>
        </span>
      </div>

      <?php if ($v['modelo']): ?>
      <div style="font-size:.82rem;color:#6B7280;margin-bottom:6px">🚛 <?= htmlspecialchars($v['modelo']) ?></div>
      <?php endif; ?>

      <?php if ($v['capacidad']): ?>
      <div style="font-size:.82rem;color:#6B7280;margin-bottom:10px">⚖️ Capacidad: <?= number_format((float)$v['capacidad'], 2) ?> kg</div>
      <?php endif; ?>

      <!-- Repartidor asignado -->
      <?php if ($v['repartidor_id']): ?>
      <div style="background:#EDE9FE;border-radius:8px;padding:8px 10px;display:flex;align-items:center;gap:8px;margin-bottom:12px">
        <div style="width:28px;height:28px;border-radius:50%;background:#7C3AED;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.75rem;flex-shrink:0">
          <?= mb_strtoupper(mb_substr($v['repartidor_nombre'] ?? '?', 0, 1)) ?>
        </div>
        <div>
          <div style="font-size:.78rem;font-weight:700;color:#5B21B6"><?= htmlspecialchars($v['repartidor_nombre'] . ' ' . $v['repartidor_apellido']) ?></div>
          <div style="font-size:.68rem;color:#7C3AED">Repartidor asignado</div>
        </div>
      </div>
      <?php else: ?>
      <div style="background:#F9FAFB;border-radius:8px;padding:8px 10px;margin-bottom:12px;font-size:.78rem;color:#9CA3AF;font-style:italic">
        Sin repartidor asignado
      </div>
      <?php endif; ?>

      <!-- Acciones -->
      <div style="display:flex;gap:6px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>empresa-vehiculo/toggleActivo/<?= $v['id'] ?>"
           style="font-size:.72rem;padding:5px 10px;border-radius:6px;background:#F3F4F6;color:#374151;text-decoration:none;font-weight:600;white-space:nowrap">
          <?= $v['activo'] ? 'Desactivar' : 'Activar' ?>
        </a>
        <button onclick="abrirEditarVeh(<?= htmlspecialchars(json_encode($v)) ?>)"
                style="font-size:.72rem;padding:5px 10px;border-radius:6px;background:#DBEAFE;color:#1E40AF;border:none;cursor:pointer;font-weight:600;white-space:nowrap">
          Editar
        </button>
      </div>

    </div>
  </div>
  <?php endforeach; ?>

</div>
<?php endif; ?>

<!-- Modal editar vehículo -->
<div id="modalEditarVeh" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:min(480px,94vw);max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px">Editar vehículo</div>
    <form id="formEditarVeh" method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">

        <div>
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Placa *</label>
          <input type="text" name="placa" id="editVehPlaca" required maxlength="20"
                 style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
        </div>

        <div>
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Capacidad (kg)</label>
          <input type="number" name="capacidad" id="editVehCapacidad" min="0" step="0.01" placeholder="Sin límite"
                 style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
        </div>

        <div style="grid-column:1/-1">
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Modelo / Descripción</label>
          <input type="text" name="modelo" id="editVehModelo" maxlength="100"
                 style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
        </div>

        <div style="grid-column:1/-1">
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Repartidor asignado</label>
          <select name="repartidor_id" id="editVehRepartidor" style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
            <option value="">— Sin asignar —</option>
            <?php foreach ($repartidores as $r): ?>
            <option value="<?= $r['id'] ?>"><?= htmlspecialchars($r['nombre'] . ' ' . $r['apellido_paterno']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.875rem">Guardar cambios</button>
        <button type="button" onclick="cerrarEditarVeh()" style="padding:9px 16px;background:#F3F4F6;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<style>.hidden { display:none !important; }</style>
<script>
function abrirEditarVeh(v) {
  document.getElementById('formEditarVeh').action = '<?= BASE_URL ?>empresa-vehiculo/actualizar/' + v.id;
  document.getElementById('editVehPlaca').value      = v.placa     || '';
  document.getElementById('editVehModelo').value     = v.modelo    || '';
  document.getElementById('editVehCapacidad').value  = v.capacidad || '';
  document.getElementById('editVehRepartidor').value = v.repartidor_id || '';
  document.getElementById('modalEditarVeh').style.display = 'flex';
}
function cerrarEditarVeh() {
  document.getElementById('modalEditarVeh').style.display = 'none';
}
</script>
