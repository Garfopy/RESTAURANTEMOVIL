<?php
// Vista: Detalle de producto con precios escalonados
?>
<div style="max-width:680px">
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px">
    <div style="display:grid;grid-template-columns:220px 1fr">
      <!-- Imagen -->
      <div style="background:#F3F4F6;display:flex;align-items:center;justify-content:center;min-height:200px">
        <?php if ($producto['imagen']): ?>
          <img src="<?= htmlspecialchars(UPLOAD_URL . $producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" style="width:100%;height:100%;object-fit:cover">
        <?php else: ?>
          <span style="font-size:4rem">🥩</span>
        <?php endif; ?>
      </div>
      <!-- Info -->
      <div style="padding:20px">
        <div style="font-size:.75rem;color:#9CA3AF;margin-bottom:4px"><?= htmlspecialchars($producto['categoria_nombre']) ?></div>
        <h2 style="font-size:1.2rem;font-weight:800;color:#111827;margin-bottom:8px"><?= htmlspecialchars($producto['nombre']) ?></h2>
        <?php if ($producto['descripcion']): ?>
        <p style="font-size:.875rem;color:#6B7280;margin-bottom:12px"><?= htmlspecialchars($producto['descripcion']) ?></p>
        <?php endif; ?>

        <div style="display:flex;gap:16px;margin-bottom:16px">
          <div>
            <div style="font-size:.7rem;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Presentación</div>
            <div style="font-size:.95rem;font-weight:600;color:#374151;margin-top:2px"><?= $producto['presentacion'] ?></div>
          </div>
          <div>
            <div style="font-size:.7rem;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Precio base</div>
            <div style="font-size:1.2rem;font-weight:800;color:var(--color-primary);margin-top:2px">$<?= number_format($producto['precio_base'], 2) ?></div>
          </div>
          <?php if ($producto['stock'] !== null): ?>
          <div>
            <div style="font-size:.7rem;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Stock</div>
            <div style="font-size:.95rem;font-weight:600;color:<?= $producto['stock'] <= ($producto['umbral_minimo'] ?? 0) ? '#EF4444' : '#374151' ?>;margin-top:2px">
              <?= number_format($producto['stock'], 1) ?> <?= $producto['presentacion'] ?>
              <?php if ($producto['stock'] <= ($producto['umbral_minimo'] ?? 0)): ?>
              <span style="font-size:.7rem;color:#EF4444"> — Bajo</span>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        </div>

        <?php
        $rol = $_SESSION['usuario']['rol_slug'] ?? '';
        $puedeComprar = in_array($rol, ['admin_empresa','comprador'], true);
        if ($puedeComprar):
        ?>
        <a href="<?= BASE_URL ?>carrito/index"
           style="display:inline-block;padding:10px 22px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
          Ir al pedido →
        </a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Precios escalonados -->
  <?php if (!empty($producto['escalonados'])): ?>
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
    <div style="padding:14px 16px;border-bottom:1px solid #F3F4F6;font-weight:700;font-size:.9rem;color:#111827">
      Precios por volumen
    </div>
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <thead>
        <tr style="background:#F9FAFB">
          <th style="padding:10px 16px;text-align:left;color:#6B7280;font-weight:600">Desde</th>
          <th style="padding:10px;text-align:left;color:#6B7280;font-weight:600">Hasta</th>
          <th style="padding:10px 16px;text-align:right;color:#6B7280;font-weight:600">Precio / <?= $producto['presentacion'] ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($producto['escalonados'] as $esc): ?>
        <tr style="border-top:1px solid #F3F4F6">
          <td style="padding:10px 16px;color:#374151"><?= number_format($esc['cantidad_min'], 1) ?> <?= $producto['presentacion'] ?></td>
          <td style="padding:10px;color:#374151">
            <?= $esc['cantidad_max'] ? number_format($esc['cantidad_max'], 1) . ' ' . $producto['presentacion'] : 'En adelante' ?>
          </td>
          <td style="padding:10px 16px;text-align:right;font-weight:700;color:var(--color-primary);font-size:1rem">
            $<?= number_format($esc['precio'], 2) ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <div style="margin-top:14px">
    <a href="<?= BASE_URL ?>catalogo/index" style="font-size:.875rem;color:#6B7280;text-decoration:none">← Volver al catálogo</a>
  </div>
</div>
