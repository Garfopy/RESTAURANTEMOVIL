<?php
$carritoItems = $carrito ?? [];
// Mapa de precios especiales del comprador: producto_id => precio
$peMap = [];
foreach (($preciosEspeciales ?? []) as $pid => $p) {
    $peMap[(int)$pid] = (float)$p;
}
// Solo productos que ya están en el carrito con cantidad > 0
$productosEnCarrito = [];
foreach (($productos ?? []) as $prod) {
    if (isset($carritoItems[$prod['id']]) && ($carritoItems[$prod['id']]['cantidad'] ?? 0) > 0) {
        $productosEnCarrito[] = $prod;
    }
}
$totalItems = count($productosEnCarrito);
?>
<!-- Indicador de pasos -->
<div style="display:flex;align-items:center;gap:0;margin-bottom:24px;font-size:.8rem">
  <?php $pasos = ['1'=>'Carrito','2'=>'Resumen','3'=>'Confirmado']; foreach ($pasos as $num => $label): $activo = $num === '1'; ?>
  <div style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:<?= $activo ? 'var(--color-primary)' : '#E5E7EB' ?>;color:<?= $activo ? '#fff' : '#9CA3AF' ?>;<?= $num==='1' ? 'border-radius:8px 0 0 8px' : ($num==='3' ? 'border-radius:0 8px 8px 0' : '') ?>">
    <span style="font-weight:700"><?= $num ?></span><?= $label ?>
  </div>
  <?php if ($num < '3'): ?><div style="width:0;height:0;border-top:18px solid transparent;border-bottom:18px solid transparent;border-left:10px solid <?= $activo ? 'var(--color-primary)' : '#E5E7EB' ?>"></div><?php endif; ?>
  <?php endforeach; ?>
</div>

<?php if ($flash): ?>
<div style="margin-bottom:12px;padding:10px 14px;border-radius:8px;font-size:.875rem;font-weight:500;<?= $flash['type']==='success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Header del carrito -->
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px">
  <div>
    <h2 style="font-size:1.1rem;font-weight:800;color:#111827;margin:0;display:flex;align-items:center;gap:8px">
      <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m5-9v9m4-9v9m5-9l2 9"/></svg>
      Mi carrito
      <?php if ($totalItems > 0): ?>
      <span style="background:var(--color-primary);color:#fff;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:999px"><?= $totalItems ?> producto<?= $totalItems !== 1 ? 's' : '' ?></span>
      <?php endif; ?>
    </h2>
    <?php if ($totalItems > 0): ?>
    <p style="font-size:.8rem;color:#6B7280;margin:3px 0 0">Revisa las cantidades y continúa</p>
    <?php endif; ?>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($totalItems > 0): ?>
    <a href="<?= BASE_URL ?>carrito/vaciar" onclick="return confirm('¿Vaciar todo el carrito?')"
       style="font-size:.8rem;color:#9CA3AF;text-decoration:none;padding:7px 12px;border:1.5px solid #E5E7EB;border-radius:8px;display:flex;align-items:center;gap:5px;transition:all .2s"
       onmouseenter="this.style.color='#EF4444';this.style.borderColor='#FCA5A5';this.style.background='#FFF5F5'"
       onmouseleave="this.style.color='#9CA3AF';this.style.borderColor='#E5E7EB';this.style.background='transparent'">
      <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6m5 0V4h4v2"/></svg>
      Vaciar
    </a>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>catalogo/index"
       style="font-size:.85rem;font-weight:700;color:var(--color-primary);text-decoration:none;padding:8px 18px;border:2px solid var(--color-primary);border-radius:9px;display:flex;align-items:center;gap:6px;transition:all .2s"
       onmouseenter="this.style.background='var(--color-primary)';this.style.color='#fff'"
       onmouseleave="this.style.background='transparent';this.style.color='var(--color-primary)'">
      <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
      <?= $totalItems > 0 ? '+ Agregar más' : 'Ir al catálogo' ?>
    </a>
  </div>
</div>

<!-- Combos del comprador -->
<?php if (!empty($combos)): ?>
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:14px;margin-bottom:16px">
  <div style="font-weight:700;font-size:.82rem;color:#1E40AF;margin-bottom:8px">📦 Tus combos — carga un pedido predefinido en un clic</div>
  <div style="display:flex;flex-wrap:wrap;gap:8px">
    <?php foreach ($combos as $combo): ?>
    <form method="POST" action="<?= BASE_URL ?>carrito/cargarCombo" style="display:inline">
      <input type="hidden" name="combo_id" value="<?= $combo['id'] ?>">
      <button type="submit" style="padding:7px 14px;background:#fff;border:1px solid #BFDBFE;border-radius:8px;cursor:pointer;font-size:.82rem;color:#1E40AF;font-weight:600;font-family:inherit">
        <?= htmlspecialchars($combo['nombre']) ?> <span style="opacity:.55;font-size:.7rem">(<?= $combo['total_items'] ?> prod.)</span>
      </button>
    </form>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php if ($totalItems === 0): ?>
<!-- ── CARRITO VACÍO ─────────────────────────────────────────────────── -->
<div style="background:#fff;border-radius:18px;border:2px dashed #E5E7EB;padding:70px 40px;text-align:center">
  <div style="font-size:5rem;margin-bottom:16px;opacity:.5">🛒</div>
  <div style="font-size:1.15rem;font-weight:800;color:#111827;margin-bottom:8px">Tu carrito está vacío</div>
  <p style="color:#6B7280;font-size:.9rem;margin:0 auto 28px;max-width:340px">Explora el catálogo, elige los productos que necesitas y añádelos con el botón "+ Agregar".</p>
  <a href="<?= BASE_URL ?>catalogo/index"
     style="display:inline-flex;align-items:center;gap:10px;padding:13px 32px;background:var(--color-primary);color:#fff;border-radius:12px;text-decoration:none;font-weight:700;font-size:.95rem;box-shadow:0 6px 18px rgba(200,16,46,.3)">
    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
    Explorar catálogo
  </a>
</div>

<?php else: ?>
<!-- ── LAYOUT: ítems + ticket ────────────────────────────────────────── -->
<!-- Hint de uso -->
<div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:10px;padding:10px 16px;margin-bottom:16px;font-size:.82rem;color:#0369A1;display:flex;align-items:center;gap:10px">
  <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <span><strong>Ajusta las cantidades:</strong> toca el número para escribir directamente, o usa los botones <strong>−</strong> y <strong>+</strong> para cambiar de <strong>0.5 en 0.5</strong>. Luego haz clic en <em>Continuar</em>.</span>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start">
  <div>
    <form method="POST" action="<?= BASE_URL ?>carrito/actualizar" id="carritoForm">
      <div style="background:#fff;border-radius:14px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px;box-shadow:0 2px 12px rgba(0,0,0,.05)">
        <table style="width:100%;border-collapse:collapse;font-size:.875rem">
          <thead>
            <tr style="background:#F9FAFB">
              <th style="padding:13px 16px;text-align:left;color:#6B7280;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em">Producto</th>
              <th style="padding:13px 12px;text-align:center;color:#6B7280;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em">Precio unit.</th>
              <th style="padding:13px 12px;text-align:center;color:#6B7280;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em;min-width:190px">Cantidad</th>
              <th style="padding:13px 12px;text-align:right;color:#6B7280;font-weight:600;font-size:.78rem;text-transform:uppercase;letter-spacing:.04em">Subtotal</th>
              <th style="width:48px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($productosEnCarrito as $prod): ?>
            <?php
              $sessItem       = $carritoItems[$prod['id']] ?? [];
              $prev           = $sessItem['cantidad'] ?? 0;
              // Usar el precio guardado en sesión (ya fue calculado correctamente)
              $precioEfectivo = isset($sessItem['precio']) ? (float)$sessItem['precio'] : (float)$prod['precio_base'];
              $precioBase     = isset($sessItem['precio_base']) ? (float)$sessItem['precio_base'] : (float)$prod['precio_base'];
              $esPrecioEsp    = !empty($sessItem['es_precio_especial']);
              $hayDescuento   = $precioEfectivo < $precioBase;
            ?>
            <tr style="border-top:1px solid #F3F4F6;background:#FFFAF9" id="row-<?= $prod['id'] ?>">
              <!-- Producto -->
              <td style="padding:14px 16px">
                <div style="display:flex;align-items:center;gap:10px">
                  <?php if (!empty($prod['imagen'])): ?>
                  <img src="<?= htmlspecialchars($prod['imagen']) ?>" alt="" style="width:44px;height:44px;border-radius:10px;object-fit:cover;flex-shrink:0;border:1px solid #F3F4F6">
                  <?php else: ?>
                  <div style="width:44px;height:44px;border-radius:10px;background:#FEF2F2;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:1.4rem">🥩</div>
                  <?php endif; ?>
                  <div>
                    <div style="font-weight:700;color:#111827;font-size:.9rem"><?= htmlspecialchars($prod['nombre']) ?></div>
                    <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($prod['categoria_nombre'] ?? '') ?> · <?= $prod['presentacion'] ?></div>
                    <?php if (!empty($prod['tiene_escalonados'])): ?>
                    <div style="font-size:.68rem;color:#059669;font-weight:600;margin-top:2px">🏷 Descuento por volumen</div>
                    <?php endif; ?>
                    <?php if (!empty($limitePorProducto[$prod['id']])): ?>
                    <?php $lim = $limitePorProducto[$prod['id']]; $perC = ['por_pedido'=>'/pedido','semanal'=>'/semana','mensual'=>'/mes'][$lim['periodo']] ?? ''; ?>
                    <div style="font-size:.67rem;font-weight:700;color:#92400E;margin-top:2px">🔒 Máx. <?= number_format($lim['limite_kg'],0) ?> kg <?= $perC ?></div>
                    <?php endif; ?>
                    <div id="alert-<?= $prod['id'] ?>" style="display:none;margin-top:3px;font-size:.7rem;padding:3px 8px;border-radius:5px;font-weight:600"></div>
                  </div>
                </div>
              </td>
              <!-- Precio unitario -->
              <td style="padding:14px 12px;text-align:center">
                <div id="precio-display-<?= $prod['id'] ?>" style="color:var(--color-primary);font-weight:700;font-size:.9rem">
                  <?php if ($hayDescuento): ?>
                  <span style="text-decoration:line-through;color:#9CA3AF;font-size:.75rem">$<?= number_format($precioBase, 2) ?></span><br>
                  <span style="color:#059669;font-weight:700">$<?= number_format($precioEfectivo, 2) ?></span>
                  <?php if ($esPrecioEsp): ?>
                  <span style="display:inline-block;font-size:.62rem;background:#D1FAE5;color:#065F46;padding:1px 5px;border-radius:999px;font-weight:700;margin-top:1px">★ especial · solo &lt;10 kg</span>
                  <?php else: ?>
                  <span style="display:inline-block;font-size:.62rem;background:#ECFDF5;color:#059669;padding:1px 5px;border-radius:999px;font-weight:700;margin-top:1px">🏷 volumen</span>
                  <?php endif; ?>
                  <?php else: ?>
                  $<?= number_format($precioEfectivo, 2) ?>
                  <?php endif; ?>
                </div>
                <div style="font-size:.7rem;color:#9CA3AF">por <?= $prod['presentacion'] ?></div>
              </td>
              <!-- Cantidad con controles -->
              <td style="padding:14px 12px;text-align:center">
                <div style="display:flex;align-items:center;justify-content:center;gap:6px">
                  <button type="button"
                          onclick="cambiarCantidad(<?= $prod['id'] ?>, -0.5, <?= $precioBase ?>, '<?= htmlspecialchars($prod['nombre'], ENT_QUOTES) ?>', '<?= $prod['presentacion'] ?>')"
                          title="−0.5"
                          style="width:38px;height:38px;border:2px solid #E5E7EB;border-radius:9px;background:#F9FAFB;cursor:pointer;font-size:1.15rem;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:center;font-family:inherit;flex-shrink:0;transition:all .15s;user-select:none"
                          onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)';this.style.background='#FFF5F5'"
                          onmouseleave="this.style.borderColor='#E5E7EB';this.style.color='#374151';this.style.background='#F9FAFB'">−</button>
                  <div style="display:flex;flex-direction:column;align-items:center;gap:2px">
                    <input type="number" name="cantidad[<?= $prod['id'] ?>]"
                           id="qty-<?= $prod['id'] ?>"
                           value="<?= $prev > 0 ? $prev : '' ?>"
                           min="0" step="0.5"
                           <?= !empty($limitePorProducto[$prod['id']]) && $limitePorProducto[$prod['id']]['limite_kg'] ? 'max="'.htmlspecialchars($limitePorProducto[$prod['id']]['limite_kg']).'"' : '' ?>
                           placeholder="0"
                           onclick="this.select()"
                           oninput="actualizarFila(<?= $prod['id'] ?>, <?= $precioBase ?>, '<?= htmlspecialchars($prod['nombre'], ENT_QUOTES) ?>', '<?= $prod['presentacion'] ?>')"
                           style="width:74px;padding:8px 6px;border:2px solid var(--color-primary);border-radius:9px;text-align:center;font-size:1.05rem;font-weight:700;color:#111827;outline:none;background:#FFFAF9;box-shadow:0 2px 6px rgba(200,16,46,.1)">
                    <span style="font-size:.62rem;color:#9CA3AF;font-weight:500"><?= $prod['presentacion'] ?></span>
                  </div>
                  <button type="button"
                          onclick="cambiarCantidad(<?= $prod['id'] ?>, +0.5, <?= $precioBase ?>, '<?= htmlspecialchars($prod['nombre'], ENT_QUOTES) ?>', '<?= $prod['presentacion'] ?>')"
                          title="+0.5"
                          style="width:38px;height:38px;border:2px solid #E5E7EB;border-radius:9px;background:#F9FAFB;cursor:pointer;font-size:1.15rem;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:center;font-family:inherit;flex-shrink:0;transition:all .15s;user-select:none"
                          onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)';this.style.background='#FFF5F5'"
                          onmouseleave="this.style.borderColor='#E5E7EB';this.style.color='#374151';this.style.background='#F9FAFB'">+</button>
                </div>
              </td>
              <!-- Subtotal -->
              <td style="padding:14px 12px;text-align:right;font-weight:700;color:#111827;font-size:.95rem" id="sub-<?= $prod['id'] ?>">
                $<?= number_format($carritoItems[$prod['id']]['subtotal'] ?? ($prev * $precioEfectivo), 2) ?>
              </td>
              <!-- Quitar -->
              <td style="padding:14px 16px;text-align:center">
                <button type="button"
                        onclick="quitarProducto(<?= $prod['id'] ?>, '<?= htmlspecialchars($prod['nombre'], ENT_QUOTES) ?>')"
                        title="Quitar del carrito"
                        style="width:32px;height:32px;border:1.5px solid #FCA5A5;border-radius:8px;background:#FFF5F5;cursor:pointer;color:#EF4444;display:flex;align-items:center;justify-content:center;transition:all .15s;flex-shrink:0"
                        onmouseenter="this.style.background='#EF4444';this.style.color='#fff';this.style.borderColor='#EF4444'"
                        onmouseleave="this.style.background='#FFF5F5';this.style.color='#EF4444';this.style.borderColor='#FCA5A5'">
                  <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14H6L5 6m5 0V4h4v2"/></svg>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap">
        <a href="<?= BASE_URL ?>catalogo/index"
           style="padding:11px 22px;background:#F3F4F6;color:#374151;border-radius:9px;text-decoration:none;font-weight:600;font-size:.875rem;display:flex;align-items:center;gap:7px;transition:background .15s"
           onmouseenter="this.style.background='#E9EBF0'"
           onmouseleave="this.style.background='#F3F4F6'">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
          Seguir comprando
        </a>
        <button type="submit"
                style="padding:12px 32px;background:var(--color-primary);color:#fff;border:none;border-radius:9px;font-weight:700;font-size:.95rem;cursor:pointer;box-shadow:0 4px 16px rgba(200,16,46,.3);display:flex;align-items:center;gap:8px;transition:all .2s"
                onmouseenter="this.style.boxShadow='0 6px 22px rgba(200,16,46,.4)';this.style.transform='translateY(-1px)'"
                onmouseleave="this.style.boxShadow='0 4px 16px rgba(200,16,46,.3)';this.style.transform='translateY(0)'">
          Continuar al resumen
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M9 18l6-6-6-6"/></svg>
        </button>
      </div>
    </form>
  </div>

  <!-- ── TICKET ─────────────────────────────────────────── -->
  <div style="position:sticky;top:20px">
    <div style="background:#fff;border-radius:16px;border:2px dashed #E5E7EB;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.07)">
      <!-- Cabecera -->
      <div style="background:linear-gradient(135deg,var(--color-primary) 0%,#A00D24 100%);padding:16px;text-align:center">
        <div style="color:#fff;font-weight:800;font-size:.95rem;letter-spacing:.05em">🧾 RESUMEN</div>
        <div style="color:rgba(255,255,255,.75);font-size:.72rem;margin-top:2px"><?= $totalItems ?> producto<?= $totalItems !== 1 ? 's' : '' ?> seleccionado<?= $totalItems !== 1 ? 's' : '' ?></div>
      </div>
      <div style="border-bottom:2px dashed #E5E7EB"></div>
      <!-- Ítems del ticket -->
      <div id="ticket-body" style="padding:14px 16px;min-height:100px">
        <div id="ticket-empty" style="text-align:center;padding:14px 0;color:#9CA3AF;font-size:.82rem;display:none">
          <div style="font-size:1.5rem;margin-bottom:4px">🛒</div>
          <div>Ajusta las cantidades arriba</div>
        </div>
        <div id="ticket-items"></div>
      </div>
      <div style="border-bottom:2px dashed #E5E7EB"></div>
      <!-- Ahorro -->
      <div id="ticket-ahorro-box" style="display:none;padding:8px 16px;background:#F0FDF4;border-bottom:1px solid #D1FAE5">
        <div style="display:flex;justify-content:space-between;font-size:.78rem;color:#059669;font-weight:700">
          <span>🏷 Ahorro por volumen</span>
          <span id="ticket-ahorro">$0.00</span>
        </div>
      </div>
      <!-- Total -->
      <div style="padding:14px 16px">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;font-size:.8rem;color:#6B7280">
          <span>Subtotal</span>
          <span id="ticket-neto">$0.00</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;font-size:.8rem;color:#6B7280">
          <span>IVA (16%)</span>
          <span id="ticket-iva">$0.00</span>
        </div>
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-weight:700;color:#111827;font-size:.88rem">TOTAL</span>
          <span id="ticket-total" style="font-weight:900;color:var(--color-primary);font-size:1.2rem">$0.00</span>
        </div>
        <div style="font-size:.7rem;color:#9CA3AF;text-align:right;margin-top:4px">IVA incluido · Sujeto a confirmación</div>
      </div>
    </div>
    <div id="ticket-alertas" style="margin-top:10px"></div>
  </div>
</div>
<?php endif; ?>

<script>
const carritoInicial = <?= json_encode(array_values($carritoItems)) ?>;
const preciosProductos = {};
let debTimers = {};
const escalonadosMap = {};
const limitesProducto = <?= json_encode($limitePorProducto ?? []) ?>;

carritoInicial.forEach(item => {
  if (parseFloat(item.cantidad) > 0) {
    preciosProductos[item.producto_id] = {
      precio:     parseFloat(item.precio),
      precioBase: parseFloat(item.precio_base ?? item.precio),
      nombre:     item.nombre,
      presentacion: item.presentacion,
      cantidad:   parseFloat(item.cantidad),
      subtotal:   parseFloat(item.subtotal)
    };
  }
});

function cambiarCantidad(id, delta, precioBase, nombre, presentacion) {
  const input = document.getElementById('qty-' + id);
  if (!input) return;
  const val  = parseFloat(input.value) || 0;
  let nuevo  = Math.max(0, Math.round((val + delta) * 2) / 2);
  const lim  = limitesProducto[id];
  if (lim && lim.limite_kg && nuevo > parseFloat(lim.limite_kg)) nuevo = parseFloat(lim.limite_kg);
  input.value = nuevo > 0 ? nuevo : '';
  actualizarFila(id, precioBase, nombre, presentacion);
}

function quitarProducto(id, nombre) {
  if (!confirm('¿Quitar "' + nombre + '" del carrito?')) return;
  const fd = new FormData();
  fd.append('producto_id', id);
  fetch('<?= BASE_URL ?>carrito/quitarProducto', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      // Quitar fila de la tabla
      const row = document.getElementById('row-' + id);
      if (row) row.remove();
      // Quitar del estado local y actualizar ticket
      delete preciosProductos[id];
      delete escalonadosMap[id];
      renderTicket();
      // Actualizar badge del carrito en el nav
      const badge = document.getElementById('cartBadge');
      if (badge) {
        badge.textContent = d.total_items;
        badge.style.display = d.total_items > 0 ? 'inline' : 'none';
      }
      // Si no quedan items, mostrar estado vacío
      if (d.total_items === 0) location.reload();
    })
    .catch(() => alert('Error al quitar el producto.'));
}

function actualizarFila(id, precioBase, nombre, presentacion) {
  const input = document.getElementById('qty-' + id);
  let qty = parseFloat(input?.value) || 0;
  if (qty < 0) { if (input) input.value = ''; qty = 0; }
  const sub = document.getElementById('sub-' + id);
  if (!sub) return;

  if (qty <= 0) {
    sub.textContent = '—';
    const pdEl = document.getElementById('precio-display-' + id);
    if (pdEl) pdEl.innerHTML = '<span style="color:var(--color-primary);font-weight:700">$' + precioBase.toLocaleString('es-MX', {minimumFractionDigits:2}) + '</span>';
    const alertEl = document.getElementById('alert-' + id);
    if (alertEl) alertEl.style.display = 'none';
    delete preciosProductos[id];
    renderTicket(); return;
  }

  sub.textContent = '...';
  clearTimeout(debTimers[id]);
  debTimers[id] = setTimeout(() => {
    fetch('<?= BASE_URL ?>api/precios/' + id + '?cantidad=' + qty)
      .then(r => r.json())
      .then(d => {
        const precio   = d.precio || precioBase;
        const subtotal = precio * qty;
        sub.textContent = '$' + subtotal.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});

        const precioEl = document.getElementById('precio-display-' + id);
        if (precioEl) {
          if (precio < precioBase) {
            const badgeEsp = (d.es_precio_especial && qty < 10)
              ? ' <span style="display:inline-block;font-size:.62rem;background:#D1FAE5;color:#065F46;padding:1px 5px;border-radius:999px;font-weight:700;margin-top:1px">★ especial · solo &lt;10 kg</span>'
              : ' <span style="display:inline-block;font-size:.62rem;background:#ECFDF5;color:#059669;padding:1px 5px;border-radius:999px;font-weight:700;margin-top:1px">🏷 volumen</span>';
            precioEl.innerHTML = '<span style="text-decoration:line-through;color:#9CA3AF;font-size:.75rem">$' + precioBase.toLocaleString('es-MX', {minimumFractionDigits:2}) + '</span><br><span style="color:#059669;font-weight:700">$' + precio.toLocaleString('es-MX', {minimumFractionDigits:2}) + '</span>' + badgeEsp;
          } else {
            precioEl.innerHTML = '<span style="color:var(--color-primary);font-weight:700">$' + precio.toLocaleString('es-MX', {minimumFractionDigits:2}) + '</span>';
          }
        }
        preciosProductos[id] = { precio, precioBase, nombre, presentacion, cantidad: qty, subtotal };

        const limProd    = limitesProducto[id];
        const maxKgProd  = limProd?.limite_kg ? parseFloat(limProd.limite_kg) : Infinity;
        const escFiltrados = (d.escalonados || []).filter(t => parseFloat(t.cantidad_desde || t.cantidad_min || 0) <= maxKgProd);
        if (d.escalonados) escalonadosMap[id] = escFiltrados;
        mostrarAlertaDescuento(id, qty, precio, precioBase, escFiltrados);
        renderTicket();
      })
      .catch(() => {
        const subtotal = precioBase * qty;
        sub.textContent = '$' + subtotal.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
        preciosProductos[id] = { precio: precioBase, precioBase, nombre, presentacion, cantidad: qty, subtotal };
        renderTicket();
      });
  }, 350);
}

function mostrarAlertaDescuento(id, qty, precioActual, precioBase, escalonados) {
  const alertEl = document.getElementById('alert-' + id);
  if (!alertEl || !escalonados.length) { alertEl && (alertEl.style.display = 'none'); return; }
  const tiers    = escalonados.slice().sort((a, b) => (a.cantidad_desde || 0) - (b.cantidad_desde || 0));
  const nextTier = tiers.find(t => (t.cantidad_desde || 0) > qty && t.precio < precioActual);
  if (nextTier) {
    const falta = (nextTier.cantidad_desde - qty).toFixed(1).replace('.0', '');
    alertEl.style.cssText = 'display:block;margin-top:3px;font-size:.7rem;padding:3px 8px;border-radius:5px;font-weight:600;background:#FFF7ED;color:#B45309;border:1px solid #FED7AA';
    alertEl.textContent   = `🏷 Agrega ${falta} más → $${nextTier.precio}/${escalonados[0]?.presentacion || 'kg'}`;
  } else if (precioActual < precioBase) {
    alertEl.style.cssText = 'display:block;margin-top:3px;font-size:.7rem;padding:3px 8px;border-radius:5px;font-weight:600;background:#F0FDF4;color:#059669;border:1px solid #A7F3D0';
    alertEl.textContent   = `✓ Precio por volumen activo`;
  } else {
    alertEl.style.display = 'none';
  }
}

function renderTicket() {
  const items      = Object.entries(preciosProductos).filter(([, v]) => v.cantidad > 0);
  const ticketItems = document.getElementById('ticket-items');
  const ticketEmpty = document.getElementById('ticket-empty');
  const ticketTotal = document.getElementById('ticket-total');
  const ahorroBox   = document.getElementById('ticket-ahorro-box');
  const ahorroEl    = document.getElementById('ticket-ahorro');
  if (!ticketItems) return;

  if (items.length === 0) {
    if (ticketEmpty) ticketEmpty.style.display = 'block';
    ticketItems.innerHTML = '';
    if (ticketTotal) ticketTotal.textContent = '$0.00';
    if (ahorroBox) ahorroBox.style.display = 'none';
    return;
  }
  if (ticketEmpty) ticketEmpty.style.display = 'none';

  let total = 0, totalBase = 0, html = '';
  items.forEach(([id, item]) => {
    total     += item.subtotal;
    totalBase += item.precioBase * item.cantidad;
    html += `<div style="display:flex;justify-content:space-between;align-items:flex-start;padding:7px 0;border-bottom:1px solid #F9FAFB;font-size:.8rem">
      <div style="flex:1;padding-right:8px">
        <div style="font-weight:600;color:#111827;line-height:1.3">${item.nombre}</div>
        <div style="color:#9CA3AF;font-size:.7rem">${item.cantidad} ${item.presentacion} × $${item.precio.toLocaleString('es-MX', {minimumFractionDigits:2})}</div>
      </div>
      <div style="font-weight:700;color:#374151;white-space:nowrap">$${item.subtotal.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2})}</div>
    </div>`;
  });

  ticketItems.innerHTML = html;
  if (ticketTotal) ticketTotal.textContent = '$' + total.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});

  // Desglose IVA: precios CON IVA incluido — se extrae el 16% del total
  const netoEl  = document.getElementById('ticket-neto');
  const ivaEl  = document.getElementById('ticket-iva');
  if (netoEl && ivaEl) {
    const base       = total / 1.16;
    const iva        = total - base;
    netoEl.textContent  = '$' + base.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
    ivaEl.textContent   = '$' + iva.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
    if (ticketTotal) ticketTotal.textContent = '$' + total.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
  }

  const ahorroTotal = totalBase - total;
  if (ahorroTotal > 0.01) {
    if (ahorroBox) ahorroBox.style.display = 'block';
    if (ahorroEl) ahorroEl.textContent = '-$' + ahorroTotal.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
  } else {
    if (ahorroBox) ahorroBox.style.display = 'none';
  }
  renderAlertasGlobales();
}

function renderAlertasGlobales() {
  const cont = document.getElementById('ticket-alertas');
  if (!cont) return;
  let html = '';
  Object.entries(escalonadosMap).forEach(([id, tiers]) => {
    if (!preciosProductos[id]) return;
    const item   = preciosProductos[id];
    const sorted = tiers.slice().sort((a, b) => (a.cantidad_desde || 0) - (b.cantidad_desde || 0));
    const next   = sorted.find(t => (t.cantidad_desde || 0) > item.cantidad && t.precio < item.precio);
    if (next) {
      const falta = (next.cantidad_desde - item.cantidad).toFixed(1).replace('.0', '');
      html += `<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:8px 12px;margin-bottom:6px;font-size:.78rem;color:#92400E">
        💡 <strong>${item.nombre}</strong>: agrega ${falta} ${item.presentacion} más → $${parseFloat(next.precio).toFixed(2)}/${item.presentacion}
      </div>`;
    }
  });
  cont.innerHTML = html;
}

renderTicket();
</script>
