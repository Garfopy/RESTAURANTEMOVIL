<?php
// Vista: Catálogo de productos (admin_empresa)
// Stock visible para el admin; activo/inactivo = visibilidad para compradores
$baseUrl = BASE_URL;
?>

<!-- Flash -->
<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= $flash['message'] ?>
</div>
<?php endif; ?>

<!-- Info contextual -->
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.8rem;color:#1E40AF;display:flex;align-items:flex-start;gap:10px">
  <span style="font-size:1rem;flex-shrink:0">💡</span>
  <div>
    <strong>Visibilidad para compradores:</strong> Los productos <strong>Visibles</strong> aparecen en el catálogo de tus compradores.
    Los <strong>Ocultos</strong> no se muestran. Usa este control para manejar disponibilidad sin borrar el producto.
    <br>El stock es solo para tu referencia interna — tus compradores <strong>no lo ven</strong>.
  </div>
</div>

<!-- Toolbar -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;gap:10px;flex-wrap:wrap">
  <form method="GET" action="<?= $baseUrl ?>empresa-producto/index" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="buscar" value="<?= htmlspecialchars($filtros['buscar']) ?>"
           placeholder="Buscar producto..."
           style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;width:200px">
    <select name="categoria_id" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem">
      <option value="">Todas las categorías</option>
      <?php foreach ($categorias as $cat): ?>
        <option value="<?= $cat['id'] ?>" <?= $filtros['categoria_id'] == $cat['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($cat['nombre']) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <label style="display:flex;align-items:center;gap:5px;font-size:.875rem;color:#374151;cursor:pointer;padding:0 4px">
      <input type="checkbox" name="stock_bajo" value="1" <?= $filtros['stock_bajo'] ? 'checked' : '' ?>> Stock bajo
    </label>
    <button type="submit" style="padding:8px 16px;background:var(--color-primary);color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.875rem;font-weight:600">Filtrar</button>
    <?php if ($filtros['buscar'] || $filtros['categoria_id'] || $filtros['stock_bajo']): ?>
      <a href="<?= $baseUrl ?>empresa-producto/index" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;color:#374151;text-decoration:none">Limpiar</a>
    <?php endif; ?>
  </form>
  <a href="<?= $baseUrl ?>empresa-producto/nuevo"
     style="padding:8px 18px;background:var(--color-primary);color:#fff;border-radius:6px;font-weight:700;text-decoration:none;font-size:.875rem;white-space:nowrap">
    + Nuevo producto
  </a>
</div>

<!-- Tabla -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
  <?php if (empty($productos)): ?>
    <div style="padding:48px;text-align:center;color:#9CA3AF">
      <p style="font-size:1.1rem;font-weight:600">Sin productos</p>
      <p style="margin-top:4px;font-size:.875rem">Crea tu primer producto para que tus compradores puedan hacer pedidos.</p>
      <a href="<?= $baseUrl ?>empresa-producto/nuevo" style="display:inline-block;margin-top:16px;padding:10px 20px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600">+ Nuevo producto</a>
    </div>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Producto</th>
        <th style="padding:12px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Categoría</th>
        <th style="padding:12px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Precio base</th>
        <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">
          Stock interno
          <span style="display:block;font-size:.65rem;color:#9CA3AF;font-weight:400;text-transform:none">(solo tú lo ves)</span>
        </th>
        <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">
          Visible al comprador
        </th>
        <th style="padding:12px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($productos as $prod): ?>
      <?php
        $stock  = (float)($prod['stock_actual'] ?? 0);
        $umbral = (float)($prod['umbral_minimo'] ?? 10);
        $activo = (bool)$prod['activo'];

        if ($stock <= 0) {
            $stockColor = '#DC2626'; $stockLabel = 'Sin stock';
        } elseif ($stock <= $umbral) {
            $stockColor = '#D97706'; $stockLabel = 'Stock bajo';
        } else {
            $stockColor = '#059669'; $stockLabel = 'OK';
        }
      ?>
      <tr style="border-bottom:1px solid #F3F4F6;<?= !$activo ? 'opacity:.65;background:#FAFAFA' : '' ?>">
        <td style="padding:12px 16px">
          <div style="display:flex;align-items:center;gap:10px">
            <?php if ($prod['imagen']): ?>
              <img src="<?= htmlspecialchars($prod['imagen']) ?>" alt=""
                   style="width:40px;height:40px;border-radius:6px;object-fit:cover;border:1px solid #E5E7EB;flex-shrink:0">
            <?php else: ?>
              <div style="width:40px;height:40px;border-radius:6px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;color:#9CA3AF;font-size:1.1rem;flex-shrink:0">🥩</div>
            <?php endif; ?>
            <div>
              <div style="font-weight:600;color:#111827;font-size:.875rem"><?= htmlspecialchars($prod['nombre']) ?></div>
              <div style="font-size:.72rem;color:#9CA3AF"><?= htmlspecialchars($prod['presentacion']) ?></div>
            </div>
          </div>
        </td>
        <td style="padding:12px 16px;font-size:.875rem;color:#374151"><?= htmlspecialchars($prod['categoria_nombre'] ?? '—') ?></td>
        <td style="padding:12px 16px;text-align:right;font-size:.875rem;font-weight:700;color:#111827">
          $<?= number_format($prod['precio_base'], 2) ?>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <div style="display:inline-flex;flex-direction:column;align-items:center;gap:2px">
            <span style="font-size:.9rem;font-weight:700;color:<?= $stockColor ?>">
              <?= number_format($stock, 1) ?>
            </span>
            <span style="font-size:.65rem;color:<?= $stockColor ?>;font-weight:600"><?= $stockLabel ?></span>
            <span style="font-size:.65rem;color:#9CA3AF"><?= $prod['presentacion'] ?></span>
          </div>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <?php if ($activo): ?>
            <span style="padding:4px 12px;border-radius:999px;background:#D1FAE5;color:#065F46;font-size:.72rem;font-weight:700;display:inline-block">
              Visible
            </span>
          <?php else: ?>
            <span style="padding:4px 12px;border-radius:999px;background:#F3F4F6;color:#9CA3AF;font-size:.72rem;font-weight:700;display:inline-block">
              Oculto
            </span>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;text-align:right;white-space:nowrap">
          <a href="<?= $baseUrl ?>empresa-producto/editar/<?= $prod['id'] ?>"
             style="font-size:.8rem;color:var(--color-primary);text-decoration:none;font-weight:600;margin-right:10px">Editar</a>
          <?php if ($activo): ?>
          <a href="<?= $baseUrl ?>empresa-producto/eliminar/<?= $prod['id'] ?>"
             onclick="return confirm('¿Ocultar este producto? Los compradores ya no lo verán en el catálogo.')"
             style="font-size:.8rem;color:#D97706;text-decoration:none;font-weight:600">Ocultar</a>
          <?php else: ?>
          <a href="<?= $baseUrl ?>empresa-producto/activar/<?= $prod['id'] ?>"
             onclick="return confirm('¿Mostrar este producto a los compradores?')"
             style="font-size:.8rem;color:#059669;text-decoration:none;font-weight:600">Mostrar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Paginación -->
  <?php if (($paginacion['last_page'] ?? 1) > 1): ?>
  <div style="padding:16px;display:flex;justify-content:center;gap:4px;border-top:1px solid #E5E7EB">
    <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
      <a href="?<?= http_build_query(array_merge($filtros, ['page' => $i])) ?>"
         style="padding:6px 12px;border-radius:6px;font-size:.8rem;text-decoration:none;<?= $i === ($paginacion['current_page'] ?? 1) ? 'background:var(--color-primary);color:#fff' : 'background:#F3F4F6;color:#374151' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
