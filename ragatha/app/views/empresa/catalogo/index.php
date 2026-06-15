<?php
// Vista: Catálogo de productos — comprador y admin_empresa
$productoModelCat = new ProductoModel();
// Pre-cargar precios escalonados por producto
foreach ($productos as &$prod) {
    $prod['escalonados'] = $productoModelCat->getEscalonados((int)$prod['id']);
}
unset($prod);

$rol          = $_SESSION['usuario']['rol_slug'] ?? '';
$puedeComprar = in_array($rol, ['admin_empresa','comprador'], true);
$itemsCarrito = $_SESSION['carrito']['items'] ?? [];
$totalCarrito = count($itemsCarrito);

// Helper para imágenes: getProductImageUrl() se carga desde app/helpers/ProductImageHelper.php
?>

<style>
@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

@keyframes modalSlideUp {
  from {
    transform: translateY(40px) scale(.92);
    opacity: 0;
  }
  to {
    transform: translateY(0) scale(1);
    opacity: 1;
  }
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.7; }
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 640px) {
  .catalog-grid {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }

  #modalVerPrecios > div,
  #modalAgregar > div {
    width: 96vw !important;
  }
}
</style>

<?php if (!empty($combos)): ?>
<!-- ═══════════════════════ Sección: Combos asignados ═══════════════════════ -->
<div style="margin-bottom:32px">
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <div style="width:4px;height:28px;background:var(--color-primary);border-radius:2px"></div>
    <h2 style="font-size:1.2rem;font-weight:800;color:#111827;margin:0">Combos disponibles</h2>
    <span style="font-size:.78rem;color:#6B7280;font-weight:500"><?= count($combos) ?> combo<?= count($combos) !== 1 ? 's' : '' ?></span>
  </div>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
    <?php foreach ($combos as $combo): ?>
    <div style="background:#fff;border-radius:14px;border:1px solid #E5E7EB;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);display:flex;flex-direction:column">
      <!-- Header del combo -->
      <?php
        // Precio fijo o calculado sumando items
        $precioCombo = isset($combo['precio']) && $combo['precio'] !== null && $combo['precio'] > 0
            ? (float)$combo['precio']
            : array_reduce($combo['items'] ?? [], fn($carry, $item) => $carry + ((float)$item['precio_base'] * (float)$item['cantidad']), 0.0);
        $esPrecioFijo = isset($combo['precio']) && $combo['precio'] !== null && $combo['precio'] > 0;
      ?>
      <div style="background:linear-gradient(135deg,#1F2937 0%,#374151 100%);padding:16px 20px">
        <div style="font-size:.68rem;font-weight:700;color:rgba(255,255,255,.6);text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px">Combo</div>
        <div style="font-size:1.05rem;font-weight:800;color:#fff;line-height:1.3"><?= htmlspecialchars($combo['nombre']) ?></div>
        <?php if (!empty($combo['descripcion'])): ?>
        <div style="font-size:.8rem;color:rgba(255,255,255,.7);margin-top:6px"><?= htmlspecialchars($combo['descripcion']) ?></div>
        <?php endif; ?>
        <?php if ($precioCombo > 0): ?>
        <div style="margin-top:10px;display:inline-flex;align-items:center;gap:6px">
          <span style="background:var(--color-primary);color:#fff;font-size:.85rem;font-weight:800;padding:4px 12px;border-radius:999px">
            $<?= number_format($precioCombo, 2) ?>
          </span>
          <?php if (!$esPrecioFijo): ?>
          <span style="font-size:.68rem;color:rgba(255,255,255,.5)">precio estimado</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Items del combo -->
      <div style="padding:14px 18px;flex:1">
        <?php if (empty($combo['items'])): ?>
        <p style="font-size:.82rem;color:#9CA3AF;font-style:italic;margin:0">Sin productos configurados.</p>
        <?php else: ?>
        <div style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px">Incluye:</div>
        <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px">
          <?php foreach ($combo['items'] as $item): ?>
          <li style="display:flex;align-items:center;gap:8px;font-size:.85rem;color:#374151">
            <span style="width:6px;height:6px;border-radius:50%;background:var(--color-primary);flex-shrink:0"></span>
            <span style="font-weight:600"><?= htmlspecialchars($item['producto_nombre']) ?></span>
            <span style="margin-left:auto;font-weight:700;color:#111827;white-space:nowrap">
              <?= number_format($item['cantidad'], 2) ?> <?= htmlspecialchars($item['presentacion']) ?>
            </span>
          </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </div>

      <!-- Acción -->
      <?php if ($puedeComprar && $rol === 'comprador' && !empty($combo['items'])): ?>
      <div style="padding:12px 18px;border-top:1px solid #F3F4F6">
        <form method="POST" action="<?= BASE_URL ?>carrito/cargarCombo">
          <input type="hidden" name="combo_id" value="<?= (int)$combo['id'] ?>">
          <button type="submit"
                  style="width:100%;padding:10px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit"
                  onmouseenter="this.style.background='#A00D24'"
                  onmouseleave="this.style.background='var(--color-primary)'">
            🛒 Cargar combo en pedido
          </button>
        </form>
      </div>
      <?php elseif (!empty($combo['items'])): ?>
      <div style="padding:12px 18px;border-top:1px solid #F3F4F6">
        <div style="font-size:.78rem;color:#9CA3AF;text-align:center">Solo compradores pueden cargar combos</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<form method="GET" action="<?= BASE_URL ?>catalogo/index" style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;align-items:flex-end">
  <div style="flex:1;min-width:180px">
    <label style="font-size:.75rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px">Buscar</label>
    <input type="text" name="buscar" value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>"
           placeholder="Nombre de producto..."
           style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
  </div>
  <div style="min-width:160px">
    <label style="font-size:.75rem;font-weight:600;color:#6B7280;display:block;margin-bottom:4px">Categoría</label>
    <select name="categoria_id" style="padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;width:100%">
      <option value="">Todas</option>
      <?php foreach ($categorias as $cat): ?>
      <option value="<?= $cat['id'] ?>" <?= ($filtros['categoria_id'] ?? 0) == $cat['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($cat['nombre']) ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit" style="padding:9px 20px;background:#374151;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
    Filtrar
  </button>
  <?php if ($puedeComprar): ?>
  <a href="<?= BASE_URL ?>carrito/index" id="btnVerCarrito"
     style="padding:9px 20px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem;margin-left:auto;display:flex;align-items:center;gap:6px">
    🛒 Ver carrito
    <span id="cartBadge" style="<?= $totalCarrito > 0 ? '' : 'display:none;' ?>background:#fff;color:var(--color-primary);border-radius:999px;padding:0 7px;font-size:.75rem;font-weight:800"><?= $totalCarrito ?: 0 ?></span>
  </a>
  <?php endif; ?>
</form>

<!-- Grid de productos -->
<?php if (empty($productos)): ?>
<div style="text-align:center;padding:40px;color:#6B7280">Sin productos disponibles.</div>
<?php else: ?>
<div class="catalog-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px">
  <?php foreach ($productos as $prod):
    $estaEnCarrito = isset($itemsCarrito[$prod['id']]) && ($itemsCarrito[$prod['id']]['cantidad'] ?? 0) > 0;
    $qtyEnCarrito  = $estaEnCarrito ? (float)$itemsCarrito[$prod['id']]['cantidad'] : 0;
  ?>
  <div style="background:#fff;border-radius:16px;border:1px solid <?= $estaEnCarrito ? 'var(--color-primary)' : '#E5E7EB' ?>;overflow:hidden;display:flex;flex-direction:column;transition:all .25s ease;box-shadow:<?= $estaEnCarrito ? '0 0 0 3px rgba(200,16,46,.08)' : '0 2px 8px rgba(0,0,0,.06)' ?>;cursor:pointer"
       onmouseenter="this.style.boxShadow='0 12px 32px rgba(0,0,0,.12)';this.style.transform='translateY(-5px)'"
       onmouseleave="this.style.boxShadow='<?= $estaEnCarrito ? '0 0 0 3px rgba(200,16,46,.08)' : '0 2px 8px rgba(0,0,0,.06)' ?>';this.style.transform='translateY(0)'">
    <!-- Stripe de categoría + badge carrito -->
    <div style="height:4px;background:linear-gradient(90deg,var(--color-primary) 0%,#FF6B6B 100%)"></div>
    <!-- Imagen con overlay si está en carrito -->
    <div style="height:200px;background:linear-gradient(135deg,#F9FAFB 0%,#F3F4F6 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative">
      <img src="<?= getProductImageUrl($prod) ?>"
           alt="<?= htmlspecialchars($prod['nombre']) ?>"
           loading="lazy" decoding="async"
           style="width:100%;height:100%;object-fit:cover"
           onerror="this.parentElement.innerHTML='<span style=\'font-size:4rem\'>🥩</span>'">
      <?php if ($estaEnCarrito): ?>
      <div style="position:absolute;top:10px;right:10px;background:var(--color-primary);color:#fff;font-size:.7rem;font-weight:700;padding:4px 10px;border-radius:999px;box-shadow:0 2px 8px rgba(0,0,0,.25)">
        🛒 <?= $qtyEnCarrito ?> <?= $prod['presentacion'] ?>
      </div>
      <?php endif; ?>
      <?php if (!empty($prod['escalonados'])): ?>
      <div style="position:absolute;bottom:10px;left:10px;background:#10B981;color:#fff;font-size:.65rem;font-weight:700;padding:3px 8px;border-radius:999px">
        ✦ Vol. desc.
      </div>
      <?php endif; ?>
      <?php
        $esComprador = ($_SESSION['usuario']['rol_slug'] ?? '') === 'comprador';
        $esFav = isset($favoritosSet) && isset($favoritosSet[$prod['id']]);
      ?>
      <?php if ($esComprador): ?>
      <button type="button"
              class="btn-fav <?= $esFav ? 'is-fav' : '' ?>"
              data-producto-id="<?= (int)$prod['id'] ?>"
              data-favorito="<?= $esFav ? '1' : '0' ?>"
              onclick="toggleFavoritoCatalogo(event, this)"
              aria-label="<?= $esFav ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>"
              title="<?= $esFav ? 'Quitar de favoritos' : 'Agregar a favoritos' ?>"
              style="position:absolute;top:10px;<?= $estaEnCarrito ? 'left:10px' : 'right:10px' ?>;width:34px;height:34px;border-radius:50%;border:1px solid #E5E7EB;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.08);transition:transform .15s">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="<?= $esFav ? 'var(--color-primary)' : 'none' ?>" stroke="<?= $esFav ? 'var(--color-primary)' : '#9CA3AF' ?>" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
      </button>
      <?php endif; ?>
    </div>
    <!-- Info -->
    <div style="padding:16px 18px;flex:1;display:flex;flex-direction:column">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
        <span style="font-size:.68rem;font-weight:700;color:var(--color-primary);background:#FEF2F2;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.04em"><?= htmlspecialchars($prod['categoria_nombre']) ?></span>
      </div>
      <div style="font-weight:800;font-size:1rem;color:#111827;margin-bottom:2px;line-height:1.3"><?= htmlspecialchars($prod['nombre']) ?></div>
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:12px"><?= htmlspecialchars($prod['presentacion']) ?></div>
      <div style="margin-top:auto">
        <?php $pEsp = isset($preciosEspeciales[$prod['id']]) ? $preciosEspeciales[$prod['id']] : null; ?>
        <?php if ($pEsp !== null): ?>
        <div style="display:flex;align-items:baseline;gap:8px">
          <div style="font-size:1.5rem;font-weight:900;color:#059669;line-height:1">$<?= number_format($pEsp, 2) ?></div>
          <div style="font-size:.82rem;color:#9CA3AF;text-decoration:line-through">$<?= number_format($prod['precio_base'], 2) ?></div>
        </div>
      <div style="display:flex;flex-wrap:wrap;align-items:center;gap:4px;margin-top:4px">
          <div style="display:inline-flex;align-items:center;gap:4px;background:#D1FAE5;color:#065F46;font-size:.68rem;font-weight:700;padding:2px 6px;border-radius:999px">
              Solo alt; 10 kg
          </div>
      </div>

      <div style="font-size:.75rem;color:#9CA3AF;margin-top:4px">
          por <?= $prod['presentacion'] ?> · IVA incluido
      </div>
        <?php else: ?>
        <div style="font-size:1.5rem;font-weight:900;color:var(--color-primary);line-height:1">
          $<?= number_format($prod['precio_base'],2) ?>
        </div>
        <div style="font-size:.75rem;color:#9CA3AF;margin-top:2px">por <?= $prod['presentacion'] ?> · IVA incluido</div>
        <?php endif; ?>
      </div>
      <?php if (!empty($limitePorProducto[$prod['id']])): ?>
      <?php $lim = $limitePorProducto[$prod['id']]; $periodoC = ['por_pedido'=>'/pedido','semanal'=>'/semana','mensual'=>'/mes'][$lim['periodo']] ?? ''; ?>
      <div style="font-size:.7rem;font-weight:700;color:#92400E;background:#FEF3C7;border-radius:6px;padding:3px 8px;display:inline-block;margin-top:8px">
        🔒 Máx. <?= number_format($lim['limite_kg'],0) ?> kg <?= $periodoC ?>
      </div>
      <?php endif; ?>
    </div>
    <!-- Acciones -->
    <?php
    $prodData = json_encode([
        'id'            => $prod['id'],
        'nombre'        => $prod['nombre'],
        'presentacion'  => $prod['presentacion'],
        'precio_base'   => (float)$prod['precio_base'],
        'precio_especial' => isset($preciosEspeciales[$prod['id']]) ? (float)$preciosEspeciales[$prod['id']] : null,
        'imagen'        => getProductImageUrl($prod),
        'categoria'     => $prod['categoria_nombre'],
        'escalonados'   => $prod['escalonados'],
        'limite'        => $limitePorProducto[$prod['id']] ?? null,
    ]);
    ?>
    <div style="padding:12px 16px;border-top:1px solid #F3F4F6;display:flex;align-items:center;justify-content:space-between;gap:10px">
      <button onclick='verDetalleCatalogo(<?= $prodData ?>)'
              style="font-size:.82rem;color:#374151;background:#F9FAFB;border:1.5px solid #E5E7EB;cursor:pointer;padding:8px 14px;border-radius:8px;font-weight:600;transition:all .2s;display:inline-flex;align-items:center;gap:5px;font-family:inherit"
              onmouseenter="this.style.background='#fff';this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
              onmouseleave="this.style.background='#F9FAFB';this.style.borderColor='#E5E7EB';this.style.color='#374151'">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Ver precios
      </button>
      <?php if ($puedeComprar): ?>
      <button onclick='abrirModalAgregar(<?= $prodData ?>)'
              data-producto-id="<?= (int)$prod['id'] ?>"
              id="btn-card-<?= (int)$prod['id'] ?>"
              style="padding:9px 18px;background:<?= $estaEnCarrito ? '#10B981' : 'var(--color-primary)' ?>;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:700;cursor:pointer;transition:all .2s;font-family:inherit;white-space:nowrap;box-shadow:0 2px 8px rgba(200,16,46,.2)"
              onmouseenter="this.dataset.orig=this.style.background;this.style.transform='translateY(-1px)';this.style.boxShadow='0 4px 14px rgba(200,16,46,.35)'"
              onmouseleave="this.style.background=this.dataset.orig||'var(--color-primary)';this.style.transform='translateY(0)';this.style.boxShadow='0 2px 8px rgba(200,16,46,.2)'">
        <?= $estaEnCarrito ? '✓ En carrito' : '+ Agregar' ?>
      </button>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (($paginacion['last_page'] ?? 1) > 1): ?>
<!-- ═══════════════════════ Paginación ═══════════════════════ -->
<div style="display:flex;justify-content:center;align-items:center;gap:6px;margin-top:32px;flex-wrap:wrap">
  <?php
    $cp   = $paginacion['current_page'];
    $lp   = $paginacion['last_page'];
    $qs   = http_build_query(array_filter([
        'buscar'       => $filtros['buscar'] ?? '',
        'categoria_id' => $filtros['categoria_id'] ?? '',
    ]));
    $base = BASE_URL . 'catalogo/index?' . ($qs ? $qs . '&' : '');

    // Prev
    if ($cp > 1):
  ?>
  <a href="<?= $base ?>page=<?= $cp - 1 ?>"
     style="padding:8px 14px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;color:#374151;text-decoration:none;font-size:.875rem;font-weight:600;transition:all .15s"
     onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
     onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'">
    ← Anterior
  </a>
  <?php endif; ?>

  <?php
    // Rango de páginas a mostrar (máx 7)
    $start = max(1, $cp - 3);
    $end   = min($lp, $cp + 3);
    if ($start > 1): ?>
    <a href="<?= $base ?>page=1"
       style="padding:8px 12px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;color:#374151;text-decoration:none;font-size:.875rem;font-weight:600;transition:all .15s"
       onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
       onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'">1</a>
    <?php if ($start > 2): ?><span style="color:#9CA3AF;font-size:.875rem">…</span><?php endif; ?>
  <?php endif; ?>

  <?php for ($i = $start; $i <= $end; $i++): ?>
  <?php if ($i === $cp): ?>
  <span style="padding:8px 13px;border:2px solid var(--color-primary);border-radius:8px;background:var(--color-primary);color:#fff;font-size:.875rem;font-weight:700;min-width:38px;text-align:center">
    <?= $i ?>
  </span>
  <?php else: ?>
  <a href="<?= $base ?>page=<?= $i ?>"
     style="padding:8px 13px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;color:#374151;text-decoration:none;font-size:.875rem;font-weight:600;min-width:38px;text-align:center;transition:all .15s"
     onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
     onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'">
    <?= $i ?>
  </a>
  <?php endif; ?>
  <?php endfor; ?>

  <?php if ($end < $lp): ?>
    <?php if ($end < $lp - 1): ?><span style="color:#9CA3AF;font-size:.875rem">…</span><?php endif; ?>
    <a href="<?= $base ?>page=<?= $lp ?>"
       style="padding:8px 12px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;color:#374151;text-decoration:none;font-size:.875rem;font-weight:600;transition:all .15s"
       onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
       onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'"><?= $lp ?></a>
  <?php endif; ?>

  <?php if ($cp < $lp): ?>
  <a href="<?= $base ?>page=<?= $cp + 1 ?>"
     style="padding:8px 14px;border:1.5px solid #D1D5DB;border-radius:8px;background:#fff;color:#374151;text-decoration:none;font-size:.875rem;font-weight:600;transition:all .15s"
     onmouseenter="this.style.borderColor='var(--color-primary)';this.style.color='var(--color-primary)'"
     onmouseleave="this.style.borderColor='#D1D5DB';this.style.color='#374151'">
    Siguiente →
  </a>
  <?php endif; ?>
</div>
<div style="text-align:center;margin-top:10px;font-size:.78rem;color:#9CA3AF">
  Mostrando página <?= $cp ?> de <?= $lp ?> — <?= $paginacion['total'] ?> productos en total
</div>
<?php endif; ?>

<!-- ═══════════════════════ Modal: Ver Precios (Solo información) ═══════════════════════ -->
<div id="modalVerPrecios" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;animation:fadeIn .2s ease">
  <div style="background:#fff;border-radius:16px;width:520px;max-width:96vw;max-height:92vh;overflow-y:auto;box-shadow:0 25px 70px rgba(0,0,0,.25);animation:modalSlideUp .3s ease">

    <!-- Header con close button -->
    <div style="padding:20px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:start">
      <div>
        <div style="font-size:.75rem;color:#9CA3AF" id="modVerCategoria"></div>
        <h3 id="modVerNombre" style="font-size:1.2rem;font-weight:800;color:#111827;margin:4px 0"></h3>
      </div>
      <button onclick="cerrarModalVerPrecios()" style="background:none;border:none;cursor:pointer;color:#9CA3AF;font-size:1.5rem;line-height:1;padding:0">&times;</button>
    </div>

    <!-- Imagen grande -->
    <div id="modVerImg" style="height:220px;background:linear-gradient(135deg,#F9FAFB 0%,#F3F4F6 100%);display:flex;align-items:center;justify-content:center;overflow:hidden">
      <span style="font-size:4.5rem">🥩</span>
    </div>

    <div style="padding:24px">
      <!-- Presentación -->
      <div style="background:#F9FAFB;border-radius:10px;padding:12px 16px;margin-bottom:16px">
        <span style="font-size:.8rem;color:#6B7280">Presentación:</span>
        <span id="modVerPresentacion" style="font-weight:700;color:#111827;margin-left:8px"></span>
      </div>

      <!-- Precio base destacado -->
      <div style="background:#FEF2F2;border:2px solid var(--color-primary);border-radius:12px;padding:16px;margin-bottom:20px;text-align:center">
        <div style="font-size:.85rem;color:#6B7280;margin-bottom:4px">Precio base</div>
        <div id="modVerPrecioBase" style="font-size:1.8rem;font-weight:900;color:var(--color-primary)">$0.00</div>
        <div id="modVerUnidad" style="font-size:.85rem;color:#9CA3AF;margin-top:2px"></div>
      </div>

      <!-- Tabla de precios por volumen -->
      <div id="modVerTiersSection" style="display:none">
        <div style="font-size:.9rem;font-weight:700;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px">
          📊 Precios por volumen
        </div>
        <div id="modVerTiersTable" style="border:1px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:16px"></div>

        <!-- Info sobre descuentos -->
        <div style="background:#D1FAE5;border:1px solid #A7F3D0;border-radius:8px;padding:12px 16px;font-size:.82rem;color:#065F46">
          💡 <strong>Tip:</strong> A mayor cantidad, mejor precio por unidad
        </div>
      </div>

      <!-- Sin descuentos -->
      <div id="modVerSinTiers" style="display:none;text-align:center;color:#9CA3AF;font-size:.85rem;padding:20px 0">
        Este producto tiene precio fijo
      </div>
    </div>

    <!-- Footer -->
    <div style="padding:20px;border-top:1px solid #F3F4F6">
      <button onclick="cerrarModalVerPrecios()"
              style="width:100%;padding:12px;background:#374151;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;font-family:inherit;transition:all .2s"
              onmouseenter="this.style.background='#1F2937'"
              onmouseleave="this.style.background='#374151'">
        Cerrar
      </button>
    </div>
  </div>
</div>

<!-- ═══════════════════════ Modal: Agregar al carrito ═══════════════════════ -->
<div id="modalAgregar" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:2001;align-items:center;justify-content:center">
  <div style="position:relative;width:460px;max-width:96vw">

    <!-- Botón flotante: ver carrito (centrado vertical, fuera del recuadro a la derecha) -->
    <a href="<?= BASE_URL ?>carrito/index" aria-label="Ver carrito" title="Ver carrito"
       style="position:absolute;top:50%;right:-80px;transform:translateY(-50%);width:56px;height:56px;border-radius:50%;background:#fff;color:var(--color-primary);text-decoration:none;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,.25);border:2px solid var(--color-primary);z-index:2;transition:transform .15s, box-shadow .15s"
       onmouseenter="this.style.transform='translateY(-50%) scale(1.08)';this.style.boxShadow='0 8px 22px rgba(0,0,0,.3)'"
       onmouseleave="this.style.transform='translateY(-50%) scale(1)';this.style.boxShadow='0 6px 18px rgba(0,0,0,.25)'">
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2 9m5-9v9m4-9v9m5-9l2 9"/></svg>
    </a>

  <div style="background:#fff;border-radius:14px;width:100%;max-height:92vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.2)">

    <!-- Header con gradiente -->
    <div style="position:relative;background:linear-gradient(135deg,var(--color-primary) 0%,#A00D24 100%);padding:18px 56px 18px 20px;border-radius:14px 14px 0 0">
      <!-- Botón cerrar (X) en la esquina superior derecha -->
      <button type="button" onclick="cerrarModalAgregar()" aria-label="Cerrar"
              title="Cerrar"
              style="position:absolute;top:10px;right:10px;width:34px;height:34px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.15);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;line-height:1;font-family:inherit;transition:background .15s"
              onmouseenter="this.style.background='rgba(255,255,255,.3)'"
              onmouseleave="this.style.background='rgba(255,255,255,.15)'">
        &times;
      </button>
      <div style="font-size:.75rem;color:rgba(255,255,255,.8)" id="modAgrCategoria"></div>
      <h3 id="modAgrNombre" style="font-size:1.1rem;font-weight:800;color:#fff;margin:4px 0 0"></h3>
    </div>

    <!-- Imagen -->
    <div id="modAgrImg" style="height:140px;background:#F3F4F6;display:flex;align-items:center;justify-content:center;overflow:hidden">
      <span style="font-size:4rem">🥩</span>
    </div>

    <div style="padding:20px">
      <div id="modAgrPresentacion" style="font-size:.8rem;color:#6B7280;margin-bottom:14px"></div>

      <!-- Precio estimado -->
      <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;align-items:center">
          <span style="font-size:.8rem;color:#374151">Precio estimado:</span>
          <span id="modAgrPrecioEst" style="font-size:1.2rem;font-weight:800;color:var(--color-primary)">$0.00</span>
        </div>
        <div id="modAgrSubtotalEst" style="text-align:right;font-size:.75rem;color:#6B7280;margin-top:2px"></div>
      </div>

      <!-- Alerta de tramo -->
      <div id="modAgrAlertaTramo" style="display:none;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:.82rem;font-weight:600"></div>

      <!-- Límite de compra -->
      <div id="modAgrLimite" style="display:none;background:#FEF3C7;border:1px solid #FCD34D;border-radius:8px;padding:8px 12px;margin-bottom:12px;font-size:.8rem;color:#92400E;font-weight:600">
        🔒 <span id="modAgrLimiteTxt"></span>
      </div>

      <!-- Precios por volumen -->
      <div id="modAgrTiersSection" style="display:none;margin-bottom:14px">
        <div style="font-size:.78rem;font-weight:700;color:#374151;margin-bottom:6px">📊 Precios por volumen</div>
        <div id="modAgrTiersTable" style="font-size:.8rem;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden"></div>
      </div>

      <!-- Cantidad -->
      <div style="margin-bottom:16px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">
          Cantidad <span id="modAgrUnidad" style="color:#9CA3AF;font-weight:400"></span>
        </label>
        <input type="number" id="modAgrCantidad" min="0.5" step="0.5" placeholder="0"
               style="width:100%;padding:13px 16px;border:2px solid #D1D5DB;border-radius:8px;font-size:1.1rem;text-align:center;font-weight:600;box-sizing:border-box;outline:none;transition:all .2s"
               oninput="actualizarModalPrecio()"
               onfocus="this.style.borderColor='var(--color-primary)';this.style.boxShadow='0 0 0 4px rgba(200,16,46,.08)'"
               onblur="this.style.borderColor='#D1D5DB';this.style.boxShadow='none'">
      </div>

      <div style="display:flex;gap:10px">
        <button type="button" onclick="cerrarModalAgregar()"
                style="flex:1;padding:11px;border:1px solid #D1D5DB;border-radius:8px;background:#fff;cursor:pointer;font-size:.875rem;color:#6B7280;font-family:inherit;transition:all .2s"
                onmouseenter="this.style.background='#F9FAFB'"
                onmouseleave="this.style.background='#fff'">
          Cancelar
        </button>
        <button type="button" id="btnAgregarConfirmar" onclick="confirmarAgregar()"
                style="flex:2;padding:12px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem;font-family:inherit;transition:all .2s;box-shadow:0 4px 12px rgba(200,16,46,.25)"
                onmouseenter="this.style.background='#A00D24';this.style.transform='translateY(-1px)';this.style.boxShadow='0 6px 16px rgba(200,16,46,.35)'"
                onmouseleave="this.style.background='var(--color-primary)';this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(200,16,46,.25)'">
          🛒 Agregar al pedido
        </button>
      </div>

      <div id="modAgrFeedback" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;text-align:center;border:1px solid transparent"></div>
    </div>
  </div>
  </div>
</div>

<script>
const BASE_URL_CAT = '<?= BASE_URL ?>';
let modalProducto = null;
let debTimer = null;
let tiersActivos = []; // tramos filtrados por el límite activo del producto

// ═══════════════ Modal "Agregar al carrito" ═══════════════
function abrirModalAgregar(prod) {
  modalProducto = prod;
  // Usar precio_especial como precio de referencia cuando existe
  modalProducto._precioRef = (prod.precio_especial !== null && prod.precio_especial !== undefined)
    ? prod.precio_especial
    : prod.precio_base;
  document.getElementById('modAgrNombre').textContent       = prod.nombre;
  document.getElementById('modAgrCategoria').textContent    = prod.categoria || '';
  document.getElementById('modAgrPresentacion').textContent = prod.presentacion;
  document.getElementById('modAgrUnidad').textContent       = '(' + prod.presentacion + ')';
  document.getElementById('modAgrCantidad').value           = '';
  document.getElementById('modAgrFeedback').style.display   = 'none';
  document.getElementById('modAgrAlertaTramo').style.display = 'none';
  document.getElementById('modAgrSubtotalEst').textContent  = '';

  // Mostrar badge de precio especial si aplica (solo < 10 kg)
  const precioLabel = prod.precio_especial !== null && prod.precio_especial !== undefined
    ? '<span style="color:#059669;font-weight:900">$' + prod.precio_especial.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
      + ' <span style="font-size:.75rem;text-decoration:line-through;color:#9CA3AF">$' + prod.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
      + ' <span style="font-size:.68rem;background:#D1FAE5;color:#065F46;padding:1px 6px;border-radius:999px;font-weight:700">★ especial &lt;10 kg</span>'
      + ' / ' + prod.presentacion
    : '$' + prod.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2}) + ' / ' + prod.presentacion;
  document.getElementById('modAgrPrecioEst').innerHTML = precioLabel;
  document.getElementById('btnAgregarConfirmar').style.display = 'block';

  const imgDiv = document.getElementById('modAgrImg');
  imgDiv.innerHTML = prod.imagen
    ? `<img src="${prod.imagen}" alt="${prod.nombre}" style="width:100%;height:100%;object-fit:cover">`
    : '<span style="font-size:4rem">🥩</span>';

  // Filtrar tramos por límite: solo mostrar los que el comprador puede alcanzar
  const maxKg = prod.limite?.limite_kg ? parseFloat(prod.limite.limite_kg) : Infinity;
  tiersActivos = (prod.escalonados || []).filter(t => parseFloat(t.cantidad_min) <= maxKg);

  // Tiers
  if (tiersActivos.length > 0) {
    document.getElementById('modAgrTiersSection').style.display = 'block';
    let html = '';
    tiersActivos.forEach((t, i) => {
      const desde  = parseFloat(t.cantidad_min);
      const hasta  = t.cantidad_max ? Math.min(parseFloat(t.cantidad_max), maxKg) : maxKg;
      const label  = isFinite(hasta) ? `${desde}–${hasta} ${prod.presentacion}` : `${desde}+ ${prod.presentacion}`;
      const precio = parseFloat(t.precio);
      const ahorro = prod.precio_base - precio;
      const pct    = prod.precio_base > 0 ? ((ahorro / prod.precio_base)*100).toFixed(0) : 0;
      html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:7px 12px;${i>0?'border-top:1px solid #F3F4F6':''}" id="tierAgr-${i}">
        <span style="color:#374151">${label}</span>
        <div style="text-align:right">
          <span style="font-weight:700;color:#111827">$${precio.toFixed(2)}</span>
          ${ahorro>0.01?`<span style="display:block;font-size:.68rem;color:#059669;font-weight:600">−${pct}% dto.</span>`:''}
        </div>
      </div>`;
    });
    document.getElementById('modAgrTiersTable').innerHTML = html;
  } else {
    document.getElementById('modAgrTiersSection').style.display = 'none';
  }

  document.getElementById('modalAgregar').style.display = 'flex';
  setTimeout(() => document.getElementById('modAgrCantidad').focus(), 100);

  // Límite de compra
  const limDiv = document.getElementById('modAgrLimite');
  const limTxt = document.getElementById('modAgrLimiteTxt');
  const qtyIn  = document.getElementById('modAgrCantidad');
  if (prod.limite && prod.limite.limite_kg) {
    const p = {'por_pedido':'/pedido','semanal':'/semana','mensual':'/mes'}[prod.limite.periodo] || '';
    limTxt.textContent = `Límite: ${parseFloat(prod.limite.limite_kg)} kg ${p}`;
    limDiv.style.display = 'block';
    qtyIn.max = prod.limite.limite_kg;
  } else {
    limDiv.style.display = 'none';
    qtyIn.removeAttribute('max');
  }
}

function cerrarModalAgregar() {
  document.getElementById('modalAgregar').style.display = 'none';
  modalProducto = null;
}

function actualizarModalPrecio() {
  if (!modalProducto) return;
  clearTimeout(debTimer);
  const qty = parseFloat(document.getElementById('modAgrCantidad').value) || 0;
  if (qty <= 0) {
    const precioLabel = modalProducto.precio_especial !== null && modalProducto.precio_especial !== undefined
      ? '<span style="color:#059669;font-weight:900">$' + modalProducto.precio_especial.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
        + ' <span style="font-size:.75rem;text-decoration:line-through;color:#9CA3AF">$' + modalProducto.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
        + ' <span style="font-size:.68rem;background:#D1FAE5;color:#065F46;padding:1px 6px;border-radius:999px;font-weight:700">★ especial &lt;10 kg</span>'
        + ' / ' + modalProducto.presentacion
      : '$' + modalProducto.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2}) + ' / ' + modalProducto.presentacion;
    document.getElementById('modAgrPrecioEst').innerHTML = precioLabel;
    document.getElementById('modAgrSubtotalEst').textContent = '';
    document.getElementById('modAgrAlertaTramo').style.display = 'none';
    resaltarTier(null); return;
  }
  debTimer = setTimeout(() => {
    fetch(BASE_URL_CAT + 'api/precios/' + modalProducto.id + '?cantidad=' + qty)
      .then(r => r.json())
      .then(d => {
        // El precio especial solo aplica para cantidades < 10 kg
        let precioFinal = d.precio || modalProducto._precioRef;
        if (qty < 10 && modalProducto.precio_especial !== null && modalProducto.precio_especial !== undefined) {
          precioFinal = Math.min(precioFinal, modalProducto.precio_especial);
        }
        aplicarPrecio(qty, precioFinal, d.es_precio_especial || false);
      })
      .catch(() => aplicarPrecio(qty, precioLocal(qty), qty < 10 && modalProducto.precio_especial !== null && precioLocal(qty) === modalProducto.precio_especial));
  }, 280);
}

function precioLocal(qty) {
  if (!modalProducto?.escalonados) return modalProducto._precioRef;
  let p = modalProducto._precioRef;
  modalProducto.escalonados.forEach(t => {
    const min = parseFloat(t.cantidad_min), max = t.cantidad_max ? parseFloat(t.cantidad_max) : Infinity;
    if (qty >= min && qty <= max) p = parseFloat(t.precio);
  });
  // El precio especial solo aplica para cantidades < 10 kg
  if (qty < 10 && modalProducto.precio_especial !== null && modalProducto.precio_especial !== undefined) {
    p = Math.min(p, modalProducto.precio_especial);
  }
  return p;
}

function aplicarPrecio(qty, precio, esPrecioEspecial) {
  const badge = (esPrecioEspecial && qty < 10)
    ? ' <span style="font-size:.68rem;background:#D1FAE5;color:#065F46;padding:1px 6px;border-radius:999px;font-weight:700">★ especial · solo &lt;10 kg</span>'
    : '';
  const base = modalProducto.precio_base;
  const precioHtml = (precio < base)
    ? '<span style="text-decoration:line-through;color:#9CA3AF;font-size:.75rem">$' + base.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
      + ' <span style="color:#059669;font-weight:900">$' + precio.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
      + badge + ' <span style="color:#6B7280">/ ' + modalProducto.presentacion + '</span>'
    : '$' + precio.toLocaleString('es-MX',{minimumFractionDigits:2}) + ' / ' + modalProducto.presentacion;
  document.getElementById('modAgrPrecioEst').innerHTML = precioHtml;
  document.getElementById('modAgrSubtotalEst').textContent = 'Subtotal: $' + (precio*qty).toLocaleString('es-MX',{minimumFractionDigits:2,maximumFractionDigits:2});

  const alerta = document.getElementById('modAgrAlertaTramo');
  let activoIdx = -1;
  if (tiersActivos.length) {
    tiersActivos.forEach((t, i) => {
      const min = parseFloat(t.cantidad_min), max = t.cantidad_max ? parseFloat(t.cantidad_max) : Infinity;
      if (qty >= min && qty <= max) activoIdx = i;
    });
  }
  resaltarTier(activoIdx);

  const ahorro = modalProducto.precio_base - precio;
  if (ahorro > 0.01) {
    const pct = ((ahorro / modalProducto.precio_base)*100).toFixed(0);
    alerta.style.cssText = 'display:block;background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:.82rem;font-weight:600';
    alerta.textContent = `¡Ahorrando ${pct}% — $${ahorro.toFixed(2)} menos por ${modalProducto.presentacion}!`;
    return;
  }
  if (tiersActivos.length) {
    const siguiente = activoIdx + 1;
    if (siguiente < tiersActivos.length) {
      const sig   = tiersActivos[siguiente];
      const falta = parseFloat(sig.cantidad_min) - qty;
      const pSig  = parseFloat(sig.precio);
      const pctSig = ((modalProducto.precio_base - pSig) / modalProducto.precio_base * 100).toFixed(0);
      alerta.style.cssText = 'display:block;background:#FEF3C7;color:#92400E;border:1px solid #FCD34D;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:.82rem;font-weight:600';
      alerta.textContent = `Agrega ${falta.toFixed(1)} ${modalProducto.presentacion} más → precio ${pctSig}% dto. ($${pSig.toFixed(2)}/${modalProducto.presentacion})`;
      return;
    }
  }
  alerta.style.display = 'none';
}

function resaltarTier(idx) {
  if (!tiersActivos.length) return;
  tiersActivos.forEach((_, i) => {
    const r = document.getElementById('tierAgr-' + i);
    if (r) {
      r.style.transition = 'all .25s ease';
      if (i === idx) {
        r.style.background = '#F0FDF4';
        r.style.fontWeight = '700';
        r.style.transform = 'scale(1.02)';
        r.style.boxShadow = '0 2px 8px rgba(5,150,105,.15)';
      } else {
        r.style.background = '';
        r.style.fontWeight = '';
        r.style.transform = 'scale(1)';
        r.style.boxShadow = 'none';
      }
    }
  });
}

function confirmarAgregar() {
  if (!modalProducto) return;
  const qty = parseFloat(document.getElementById('modAgrCantidad').value) || 0;
  if (qty <= 0) {
    document.getElementById('modAgrCantidad').style.borderColor = '#DC2626';
    document.getElementById('modAgrCantidad').focus();
    return;
  }
  // Validación de límite en cliente
  if (modalProducto.limite && modalProducto.limite.limite_kg && qty > parseFloat(modalProducto.limite.limite_kg)) {
    document.getElementById('modAgrCantidad').style.borderColor = '#DC2626';
    const fb = document.getElementById('modAgrFeedback');
    fb.style.cssText = 'display:block;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;text-align:center;background:#FEE2E2;color:#991B1B;border:1px solid #FECACA';
    fb.textContent = `🔒 Límite superado: máximo ${modalProducto.limite.limite_kg} ${modalProducto.presentacion} por pedido`;
    return;
  }
  document.getElementById('modAgrCantidad').style.borderColor = '#D1D5DB';

  const btn = document.getElementById('btnAgregarConfirmar');
  btn.disabled = true;
  btn.innerHTML = `<div style="display:inline-flex;align-items:center;gap:8px">
    <div style="width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite"></div>
    Agregando...
  </div>`;

  const fd = new FormData();
  fd.append('producto_id', modalProducto.id);
  fd.append('cantidad', qty);

  fetch(BASE_URL_CAT + 'carrito/agregarProducto', { method:'POST', body:fd })
    .then(r => r.json())
    .then(d => {
      const fb = document.getElementById('modAgrFeedback');
      fb.style.display = 'block';
      if (d.ok) {
        fb.style.cssText = 'display:block;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;text-align:center;background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0';
        fb.textContent = '✓ ' + d.msg;
        const badge = document.getElementById('cartBadge');
        if (badge) { badge.textContent = d.total_items; badge.style.display = 'inline'; }
        // Actualizar botón de la tarjeta en el grid
        const cardBtn = document.getElementById('btn-card-' + modalProducto.id);
        if (cardBtn) {
          cardBtn.textContent = '✓ En carrito';
          cardBtn.style.background = '#10B981';
          cardBtn.dataset.orig = '#10B981';
        }
        document.getElementById('modAgrCantidad').value = '';
        document.getElementById('modAgrSubtotalEst').textContent = '';
        document.getElementById('modAgrAlertaTramo').style.display = 'none';
        // Resetear precio al estado inicial (precio especial si aplica, o base)
        const resetLabel = modalProducto.precio_especial !== null && modalProducto.precio_especial !== undefined
          ? '<span style="color:#059669;font-weight:900">$' + modalProducto.precio_especial.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
            + ' <span style="font-size:.75rem;text-decoration:line-through;color:#9CA3AF">$' + modalProducto.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2}) + '</span>'
            + ' <span style="font-size:.68rem;background:#D1FAE5;color:#065F46;padding:1px 6px;border-radius:999px;font-weight:700">★ especial &lt;10 kg</span>'
            + ' / ' + modalProducto.presentacion
          : '$' + modalProducto.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2}) + ' / ' + modalProducto.presentacion;
        document.getElementById('modAgrPrecioEst').innerHTML = resetLabel;

        // Success animation
        btn.innerHTML = '✓ ¡Agregado!';
        btn.style.background = '#10B981';
        setTimeout(() => {
          btn.disabled = false;
          btn.innerHTML = '🛒 Agregar al pedido';
          btn.style.background = 'var(--color-primary)';
        }, 2000);
      } else {
        fb.style.cssText = 'display:block;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;text-align:center;background:#FEE2E2;color:#991B1B;border:1px solid #FECACA';
        fb.textContent = '✕ ' + d.msg;
        btn.disabled = false;
        btn.innerHTML = '🛒 Agregar al pedido';
      }
    })
    .catch(() => {
      const fb = document.getElementById('modAgrFeedback');
      fb.style.cssText = 'display:block;margin-top:12px;padding:10px 14px;border-radius:8px;font-size:.85rem;text-align:center;background:#FEE2E2;color:#991B1B;border:1px solid #FECACA';
      fb.textContent = 'Error de conexión.';
      btn.disabled = false;
      btn.innerHTML = '🛒 Agregar al pedido';
    });
}

document.getElementById('modalAgregar').addEventListener('click', e => {
  if (e.target === document.getElementById('modalAgregar')) cerrarModalAgregar();
});

// ═══════════════ Modal "Ver Precios" (Solo información) ═══════════════
function verDetalleCatalogo(prod) {
  document.getElementById('modVerNombre').textContent = prod.nombre;
  document.getElementById('modVerCategoria').textContent = prod.categoria || '';
  document.getElementById('modVerPresentacion').textContent = prod.presentacion;
  document.getElementById('modVerPrecioBase').textContent =
    '$' + prod.precio_base.toLocaleString('es-MX',{minimumFractionDigits:2});
  document.getElementById('modVerUnidad').textContent = 'por ' + prod.presentacion;

  // Imagen
  const imgDiv = document.getElementById('modVerImg');
  imgDiv.innerHTML = prod.imagen
    ? `<img src="${prod.imagen}" alt="${prod.nombre}" style="width:100%;height:100%;object-fit:cover" loading="lazy">`
    : '<span style="font-size:4.5rem">🥩</span>';

  // Tiers
  if (prod.escalonados && prod.escalonados.length > 0) {
    document.getElementById('modVerTiersSection').style.display = 'block';
    document.getElementById('modVerSinTiers').style.display = 'none';

    let html = '';
    prod.escalonados.forEach((t, i) => {
      const desde = parseFloat(t.cantidad_min);
      const hasta = t.cantidad_max ? parseFloat(t.cantidad_max) : null;
      const label = hasta ? `${desde}–${hasta} ${prod.presentacion}` : `${desde}+ ${prod.presentacion}`;
      const precio = parseFloat(t.precio);
      const ahorro = prod.precio_base - precio;
      const pct = prod.precio_base > 0 ? ((ahorro / prod.precio_base)*100).toFixed(0) : 0;

      html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 16px;${i>0?'border-top:1px solid #E5E7EB':''}">
        <div>
          <div style="font-weight:700;color:#111827;font-size:.95rem">${label}</div>
          ${ahorro>0.01?`<div style="font-size:.72rem;color:#059669;font-weight:600">Ahorro: ${pct}%</div>`:''}
        </div>
        <div style="text-align:right">
          <div style="font-weight:800;color:var(--color-primary);font-size:1.05rem">$${precio.toFixed(2)}</div>
          <div style="font-size:.7rem;color:#9CA3AF">por ${prod.presentacion}</div>
        </div>
      </div>`;
    });
    document.getElementById('modVerTiersTable').innerHTML = html;
  } else {
    document.getElementById('modVerTiersSection').style.display = 'none';
    document.getElementById('modVerSinTiers').style.display = 'block';
  }

  document.getElementById('modalVerPrecios').style.display = 'flex';
}

function cerrarModalVerPrecios() {
  document.getElementById('modalVerPrecios').style.display = 'none';
}

document.getElementById('modalVerPrecios').addEventListener('click', e => {
  if (e.target === document.getElementById('modalVerPrecios')) cerrarModalVerPrecios();
});

// ═══════════════ Favoritos (toggle AJAX) ═══════════════
async function toggleFavoritoCatalogo(ev, btn) {
  ev.preventDefault();
  ev.stopPropagation();
  const productoId = btn.dataset.productoId;
  if (!productoId || btn.disabled) return;
  btn.disabled = true;
  const prevTransform = btn.style.transform;
  btn.style.transform = 'scale(.85)';
  try {
    const fd = new FormData();
    fd.append('producto_id', productoId);
    const res = await fetch(BASE_URL_CAT + 'favorito/toggle', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    const isFav = !!data.favorito;
    btn.dataset.favorito = isFav ? '1' : '0';
    btn.classList.toggle('is-fav', isFav);
    btn.setAttribute('aria-label', isFav ? 'Quitar de favoritos' : 'Agregar a favoritos');
    btn.title = isFav ? 'Quitar de favoritos' : 'Agregar a favoritos';
    const svg = btn.querySelector('svg');
    if (svg) {
      svg.setAttribute('fill',  isFav ? 'var(--color-primary)' : 'none');
      svg.setAttribute('stroke', isFav ? 'var(--color-primary)' : '#9CA3AF');
    }
  } catch (err) {
    console.error('toggleFavoritoCatalogo', err);
  } finally {
    btn.disabled = false;
    btn.style.transform = prevTransform || '';
  }
}
</script>
