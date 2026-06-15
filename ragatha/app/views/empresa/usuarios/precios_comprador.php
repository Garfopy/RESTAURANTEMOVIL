<?php
$baseUrl = BASE_URL;
?>

<div style="display:flex;align-items:center;gap:10px;margin-bottom:20px">
  <a href="<?= $baseUrl ?>empresa-usuario" style="font-size:.85rem;color:#6B7280;text-decoration:none">← Mi equipo</a>
  <span style="color:#D1D5DB">/</span>
  <span style="font-size:.85rem;color:#111827;font-weight:600">
    Precios especiales — <?= htmlspecialchars($comprador['nombre'] . ' ' . $comprador['apellido_paterno']) ?>
  </span>
</div>

<!-- Flash -->
<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<p style="font-size:.85rem;color:#6B7280;padding:12px 16px;background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;margin-bottom:24px">
  Define precios acordados con este comprador. Si activas un precio especial, se aplicará en lugar del precio escalonado estándar.
</p>

<form method="POST" action="<?= $baseUrl ?>empresa-usuario/precios/<?= $comprador['id'] ?>">
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
    <table style="width:100%;border-collapse:collapse">
      <thead>
        <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
          <th style="padding:12px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Producto</th>
          <th style="padding:12px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Precio base</th>
          <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">¿Precio especial?</th>
          <th style="padding:12px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Precio especial ($)</th>
          <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Diferencia</th>
        </tr>
      </thead>
      <tbody>
        <?php $categoriaActual = ''; ?>
        <?php foreach ($productos as $i => $p): ?>
        <?php if ($p['categoria_nombre'] !== $categoriaActual): ?>
          <?php $categoriaActual = $p['categoria_nombre']; ?>
          <tr style="background:#F9FAFB">
            <td colspan="5" style="padding:8px 16px;font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase"><?= htmlspecialchars($categoriaActual) ?></td>
          </tr>
        <?php endif; ?>
        <?php
          $tieneEspecial = !empty($p['precio_especial_id']);
          $precioEspecial = (float)($p['precio_especial'] ?? 0);
          $precioBase = (float)$p['precio_base'];
          $diff = $tieneEspecial ? round($precioEspecial - $precioBase, 2) : null;
        ?>
        <tr style="border-bottom:1px solid #F3F4F6">
          <td style="padding:12px 16px">
            <input type="hidden" name="producto_id[]" value="<?= $p['id'] ?>">
            <div style="font-weight:600;font-size:.875rem;color:#111827"><?= htmlspecialchars($p['nombre']) ?></div>
            <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($p['presentacion']) ?></div>
          </td>
          <td style="padding:12px 16px;text-align:right;font-size:.875rem;color:#6B7280">
            $<?= number_format($precioBase, 2) ?>
          </td>
          <td style="padding:12px 16px;text-align:center">
            <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer">
              <input type="checkbox" name="activo[]" id="check_<?= $p['id'] ?>"
                     value="1" <?= $tieneEspecial ? 'checked' : '' ?>
                     onchange="togglePrecio(this, <?= $p['id'] ?>)"
                     style="width:16px;height:16px;cursor:pointer">
              <span id="label_<?= $p['id'] ?>" style="font-size:.75rem;color:<?= $tieneEspecial ? '#059669' : '#9CA3AF' ?>;font-weight:600">
                <?= $tieneEspecial ? 'Sí' : 'No' ?>
              </span>
            </label>
          </td>
          <td style="padding:12px 16px;text-align:right">
            <div style="display:inline-flex;align-items:center;gap:4px">
              <span style="color:#9CA3AF;font-size:.85rem">$</span>
              <input type="number" name="precio[]" id="precio_<?= $p['id'] ?>"
                     value="<?= $tieneEspecial ? number_format($precioEspecial, 2, '.', '') : '' ?>"
                     min="0.01" step="0.01" placeholder="—"
                     <?= !$tieneEspecial ? 'disabled' : '' ?>
                     onchange="calcDiff(<?= $p['id'] ?>, <?= $precioBase ?>)"
                     style="width:100px;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;text-align:right;
                       <?= !$tieneEspecial ? 'background:#F9FAFB;color:#9CA3AF' : '' ?>">
            </div>
          </td>
          <td style="padding:12px 16px;text-align:center" id="diff_<?= $p['id'] ?>">
            <?php if ($diff !== null): ?>
              <span style="font-size:.8rem;font-weight:700;color:<?= $diff <= 0 ? '#059669' : '#DC2626' ?>">
                <?= $diff >= 0 ? '+' : '' ?>$<?= number_format(abs($diff), 2) ?>
              </span>
            <?php else: ?>
              <span style="color:#D1D5DB">—</span>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div style="padding:16px 20px;border-top:1px solid #E5E7EB;display:flex;justify-content:flex-end;gap:10px">
      <a href="<?= $baseUrl ?>empresa-usuario"
         style="padding:10px 20px;border:1px solid #D1D5DB;border-radius:8px;color:#374151;text-decoration:none;font-size:.875rem">
        Cancelar
      </a>
      <button type="submit"
              style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.875rem;cursor:pointer">
        Guardar precios especiales
      </button>
    </div>
  </div>
</form>

<script>
function togglePrecio(checkbox, id) {
  const input = document.getElementById('precio_' + id);
  const label = document.getElementById('label_' + id);
  input.disabled = !checkbox.checked;
  if (checkbox.checked) {
    input.style.background = '';
    input.style.color = '';
    label.textContent = 'Sí';
    label.style.color = '#059669';
    input.focus();
  } else {
    input.style.background = '#F9FAFB';
    input.style.color = '#9CA3AF';
    label.textContent = 'No';
    label.style.color = '#9CA3AF';
    document.getElementById('diff_' + id).innerHTML = '<span style="color:#D1D5DB">—</span>';
  }
}

function calcDiff(id, base) {
  const input = document.getElementById('precio_' + id);
  const val   = parseFloat(input.value);
  const diff  = val - base;
  const cell  = document.getElementById('diff_' + id);
  if (isNaN(val)) {
    cell.innerHTML = '<span style="color:#D1D5DB">—</span>';
    return;
  }
  const color = diff <= 0 ? '#059669' : '#DC2626';
  const sign  = diff >= 0 ? '+' : '';
  cell.innerHTML = `<span style="font-size:.8rem;font-weight:700;color:${color}">${sign}$${Math.abs(diff).toFixed(2)}</span>`;
}
</script>
