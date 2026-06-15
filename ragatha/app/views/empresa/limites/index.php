<?php
// Vista: Límites de compra (aplica globalmente a todos los compradores)
$periodoLabels = ['por_pedido' => 'Por pedido', 'semanal' => 'Semanal', 'mensual' => 'Mensual'];
$activos   = count(array_filter($limites, fn($l) => $l['activo']));
$inactivos = count($limites) - $activos;
?>

<!-- Guía para el admin -->
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start">
  <span style="font-size:1.2rem;flex-shrink:0">💡</span>
  <div>
    <div style="font-size:.82rem;font-weight:700;color:#1E40AF;margin-bottom:2px">Cómo funcionan los límites</div>
    <div style="font-size:.78rem;color:#1D4ED8">Los límites aplican a <strong>todos tus compradores</strong>. Cuando configuras un límite para un producto, se muestra en el catálogo y se valida automáticamente al hacer un pedido. Puedes limitar por <strong>kg</strong> y/o por <strong>monto ($)</strong>.</div>
  </div>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <p style="font-size:.875rem;color:#6B7280">Controla los límites de compra por producto para tus compradores.</p>
  </div>
  <button onclick="document.getElementById('formNuevo').classList.toggle('hidden')"
          style="padding:8px 16px;background:var(--color-primary);color:#fff;border:none;border-radius:6px;font-weight:600;font-size:.875rem;cursor:pointer">
    + Nuevo límite
  </button>
</div>

<!-- Stats rápidas -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
  <?php foreach ([['Total límites',count($limites),'#1E40AF','#DBEAFE'],['Activos',$activos,'#065F46','#D1FAE5'],['Inactivos',$inactivos,'#92400E','#FEF3C7']] as [$lbl,$val,$c,$bg]): ?>
  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:16px;text-align:center">
    <div style="font-size:1.6rem;font-weight:800;color:<?= $c ?>"><?= $val ?></div>
    <div style="font-size:.78rem;color:#6B7280;font-weight:600;margin-top:2px"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Formulario nuevo límite (colapsable) -->
<div id="formNuevo" class="hidden" style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:20px;margin-bottom:20px">
  <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:14px">Nuevo límite de compra</div>
  <form method="POST" action="<?= BASE_URL ?>limite/guardar">
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:14px">

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Producto</label>
        <select name="producto_id" style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
          <option value="">— Todos los productos —</option>
          <?php foreach ($productos as $prod): ?>
          <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['nombre']) ?> (<?= $prod['unidad'] ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Límite kg *</label>
        <input type="number" name="limite_kg" min="0" step="0.01" placeholder="Ej: 50"
               style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
      </div>

      <div>
        <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Período</label>
        <select name="periodo" style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
          <option value="por_pedido">Por pedido</option>
          <option value="semanal">Semanal</option>
          <option value="mensual">Mensual</option>
        </select>
      </div>
    </div>

    <div style="display:flex;gap:10px">
      <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.875rem">
        💾 Guardar límite
      </button>
      <button type="button" onclick="document.getElementById('formNuevo').classList.add('hidden')"
              style="padding:9px 16px;background:#F3F4F6;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem">
        Cancelar
      </button>
    </div>
  </form>
</div>

<!-- Tabla de límites -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
  <div style="padding:14px 16px;border-bottom:1px solid #E5E7EB">
    <span style="font-weight:700;font-size:.9rem;color:#111827">Límites configurados (<?= count($limites) ?>)</span>
  </div>
  <?php if (empty($limites)): ?>
  <div style="padding:48px;text-align:center;color:#9CA3AF">
    <div style="font-size:2.5rem;margin-bottom:12px">🔒</div>
    <p style="font-weight:600;font-size:.95rem">No hay límites configurados</p>
    <p style="font-size:.85rem;margin-top:4px">Agrega un límite para controlar lo que pueden pedir tus compradores.</p>
  </div>
  <?php else: ?>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;min-width:560px">
      <thead>
        <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
          <?php foreach (['Producto','Límite kg','Período','Estado','Acciones'] as $h): ?>
          <th style="padding:10px 14px;text-align:left;font-size:.72rem;color:#6B7280;font-weight:700;text-transform:uppercase;white-space:nowrap"><?= $h ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($limites as $l): ?>
        <tr style="border-bottom:1px solid #F3F4F6;<?= !$l['activo'] ? 'opacity:.55' : '' ?>">
          <td style="padding:11px 14px;font-size:.85rem;color:#374151">
            <?= $l['producto_nombre'] ? htmlspecialchars($l['producto_nombre'] . ' (' . $l['unidad'] . ')') : '<span style="color:#9CA3AF;font-style:italic">Todos los productos</span>' ?>
          </td>
          <td style="padding:11px 14px;font-size:.875rem;font-weight:600;color:#374151">
            <?= $l['limite_kg'] ? number_format($l['limite_kg'], 2) . ' kg' : '—' ?>
          </td>
          <td style="padding:11px 14px">
            <span style="font-size:.75rem;padding:2px 8px;border-radius:999px;background:#EDE9FE;color:#5B21B6;font-weight:600">
              <?= $periodoLabels[$l['periodo']] ?? $l['periodo'] ?>
            </span>
          </td>
          <td style="padding:11px 14px">
            <span style="font-size:.75rem;padding:2px 8px;border-radius:999px;font-weight:600;background:<?= $l['activo'] ? '#D1FAE5' : '#F3F4F6' ?>;color:<?= $l['activo'] ? '#065F46' : '#6B7280' ?>">
              <?= $l['activo'] ? '✓ Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td style="padding:11px 14px">
            <div style="display:flex;gap:6px;flex-wrap:wrap">
              <a href="<?= BASE_URL ?>limite/toggleActivo/<?= $l['id'] ?>"
                 style="font-size:.75rem;padding:4px 10px;border-radius:6px;background:#F3F4F6;color:#374151;text-decoration:none;font-weight:600;white-space:nowrap">
                <?= $l['activo'] ? 'Desactivar' : 'Activar' ?>
              </a>
              <button onclick="abrirEditar(<?= htmlspecialchars(json_encode($l)) ?>)"
                      style="font-size:.75rem;padding:4px 10px;border-radius:6px;background:#DBEAFE;color:#1E40AF;border:none;cursor:pointer;font-weight:600;white-space:nowrap">
                Editar
              </button>
              <a href="<?= BASE_URL ?>limite/eliminar/<?= $l['id'] ?>"
                 onclick="return confirm('¿Eliminar este límite?')"
                 style="font-size:.75rem;padding:4px 10px;border-radius:6px;background:#FEE2E2;color:#991B1B;text-decoration:none;font-weight:600;white-space:nowrap">
                Eliminar
              </a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Modal editar -->
<div id="modalEditar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:999;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:12px;padding:24px;width:min(460px,94vw);max-height:90vh;overflow-y:auto">
    <div style="font-weight:700;font-size:.95rem;color:#111827;margin-bottom:16px">Editar límite</div>
    <form id="formEditar" method="POST">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">

        <div style="grid-column:1/-1">
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Producto</label>
          <select name="producto_id" id="editProducto" style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
            <option value="">— Todos los productos —</option>
            <?php foreach ($productos as $prod): ?>
            <option value="<?= $prod['id'] ?>"><?= htmlspecialchars($prod['nombre']) ?> (<?= $prod['unidad'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>

        <div>
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Límite kg *</label>
          <input type="number" name="limite_kg" id="editLimiteKg" min="0" step="0.01" placeholder="Ej: 50"
                 style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem">
        </div>
        <div>
          <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Período</label>
          <select name="periodo" id="editPeriodo" style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;background:#fff">
            <option value="por_pedido">Por pedido</option>
            <option value="semanal">Semanal</option>
            <option value="mensual">Mensual</option>
          </select>
        </div>
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.875rem">Guardar cambios</button>
        <button type="button" onclick="cerrarEditar()" style="padding:9px 16px;background:#F3F4F6;color:#374151;border:none;border-radius:8px;font-weight:600;cursor:pointer;font-size:.875rem">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<style>.hidden { display:none !important; }</style>
<script>
function abrirEditar(l) {
  document.getElementById('formEditar').action = '<?= BASE_URL ?>limite/actualizar/' + l.id;
  document.getElementById('editProducto').value = l.producto_id || '';
  document.getElementById('editLimiteKg').value = l.limite_kg   || '';
  document.getElementById('editPeriodo').value  = l.periodo;
  document.getElementById('modalEditar').style.display = 'flex';
}
function cerrarEditar() {
  document.getElementById('modalEditar').style.display = 'none';
}
</script>
