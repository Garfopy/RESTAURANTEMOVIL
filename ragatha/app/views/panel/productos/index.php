<?php
// Variables: $productos[], $paginacion, $categorias[], $filtros
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <form method="GET" action="<?= BASE_URL ?>panel-producto/index" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="buscar" value="<?= htmlspecialchars($filtros['buscar']) ?>"
           placeholder="Buscar producto..."
           style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;outline:none;min-width:200px">
    <select name="categoria_id" style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      <option value="">Todas las categorías</option>
      <?php foreach ($categorias as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= $filtros['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($cat['nombre']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;color:#374151;padding:0 4px">
      <input type="checkbox" name="stock_bajo" value="1" <?= !empty($filtros['stock_bajo']) ? 'checked' : '' ?>>
      Solo stock bajo
    </label>
    <button type="submit" style="padding:7px 16px;background:#374151;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer">Filtrar</button>
  </form>
  <a href="<?= BASE_URL ?>panel-producto/nuevo"
     style="padding:8px 18px;background:var(--color-primary);color:#fff;border-radius:8px;font-size:.875rem;text-decoration:none;font-weight:600;white-space:nowrap">
    + Nuevo producto
  </a>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Producto</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Categoría</th>
        <th style="padding:12px 16px;text-align:right;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Precio base</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Stock</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Estado</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($productos)): ?>
      <tr><td colspan="6" style="padding:40px;text-align:center;color:#9CA3AF;font-size:.875rem">No hay productos registrados.</td></tr>
      <?php endif; ?>
      <?php foreach ($productos as $prod): ?>
      <tr style="border-bottom:1px solid #F3F4F6" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='#fff'">
        <td style="padding:12px 16px">
          <div style="display:flex;align-items:center;gap:10px">
            <?php if (!empty($prod['imagen'])): ?>
              <img src="<?= htmlspecialchars($prod['imagen']) ?>" style="width:40px;height:40px;border-radius:8px;object-fit:cover;border:1px solid #E5E7EB">
            <?php else: ?>
              <div style="width:40px;height:40px;border-radius:8px;background:#FEE2E2;display:flex;align-items:center;justify-content:center;font-size:1.3rem">🥩</div>
            <?php endif; ?>
            <div>
              <div style="font-weight:600;font-size:.875rem;color:#111827"><?= htmlspecialchars($prod['nombre']) ?></div>
              <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($prod['presentacion'] ?? '') ?> · <?= htmlspecialchars($prod['unidad']) ?></div>
            </div>
          </div>
        </td>
        <td style="padding:12px 16px;font-size:.875rem;color:#374151"><?= htmlspecialchars($prod['categoria_nombre']) ?></td>
        <td style="padding:12px 16px;text-align:right;font-weight:700;color:#111827">$<?= number_format($prod['precio_base'], 2) ?></td>
        <td style="padding:12px 16px;text-align:center">
          <?php $bajo = (int)$prod['stock'] <= (int)$prod['umbral_minimo']; ?>
          <span style="padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600;<?= $bajo ? 'background:#FEE2E2;color:#991B1B' : 'background:#D1FAE5;color:#065F46' ?>">
            <?= $prod['stock'] ?> <?= htmlspecialchars($prod['unidad']) ?>
          </span>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <?php if ($prod['activo']): ?>
            <span style="background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">Activo</span>
          <?php else: ?>
            <span style="background:#F3F4F6;color:#6B7280;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">Inactivo</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;text-align:center;white-space:nowrap">
          <a href="<?= BASE_URL ?>panel-producto/editar/<?= $prod['id'] ?>"
             style="color:var(--color-primary);font-size:.8rem;font-weight:600;text-decoration:none;margin-right:10px">Editar</a>
          <?php if ($prod['activo']): ?>
          <a href="<?= BASE_URL ?>panel-producto/eliminar/<?= $prod['id'] ?>"
             onclick="return confirm('¿Desactivar «<?= htmlspecialchars(addslashes($prod['nombre'])) ?>»?')"
             style="color:#6B7280;font-size:.8rem;font-weight:600;text-decoration:none">Desactivar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($paginacion['last_page'] > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
    <a href="?page=<?= $i ?>&buscar=<?= urlencode($filtros['buscar']) ?>&categoria_id=<?= urlencode($filtros['categoria_id']) ?>&stock_bajo=<?= $filtros['stock_bajo'] ? '1' : '' ?>"
       style="padding:6px 12px;border-radius:6px;font-size:.875rem;text-decoration:none;<?= $i === $paginacion['current_page'] ? 'background:var(--color-primary);color:#fff;font-weight:700' : 'background:#fff;border:1px solid #D1D5DB;color:#374151' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
