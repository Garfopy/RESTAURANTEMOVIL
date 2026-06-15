<?php
/**
 * Vista: Mis favoritos — productos favoritos del comprador.
 * Variables disponibles: $productos (array), $flash (array|null)
 */
?>
<div style="max-width:1200px;margin:0 auto">

  <!-- Encabezado -->
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:12px">
    <div>
      <h2 style="font-size:1.25rem;font-weight:800;color:#111827;margin:0">Mis productos favoritos</h2>
      <p style="font-size:.875rem;color:#6B7280;margin:4px 0 0">
        Acceso rápido a los productos que guardaste con
        <svg width="14" height="14" viewBox="0 0 24 24" fill="var(--color-primary)" stroke="var(--color-primary)" stroke-width="2" style="vertical-align:-2px"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
        desde el catálogo.
      </p>
    </div>
    <a href="<?= BASE_URL ?>catalogo/index"
       style="padding:9px 18px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem;display:inline-flex;align-items:center;gap:6px">
      ← Ir al catálogo
    </a>
  </div>

  <?php if (empty($productos)): ?>
    <div style="background:#fff;border:1px dashed #E5E7EB;border-radius:12px;padding:48px 24px;text-align:center">
      <div style="font-size:3rem;line-height:1;margin-bottom:10px">💛</div>
      <h3 style="font-size:1.05rem;font-weight:700;color:#111827;margin:0 0 6px">Aún no tienes favoritos</h3>
      <p style="font-size:.875rem;color:#6B7280;margin:0 0 16px">
        Marca un producto con el corazón en el catálogo para encontrarlo aquí más rápido.
      </p>
      <a href="<?= BASE_URL ?>catalogo/index"
         style="display:inline-block;padding:10px 22px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
        Explorar catálogo
      </a>
    </div>
  <?php else: ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:18px">
      <?php foreach ($productos as $prod): ?>
        <div class="fav-card" style="background:#fff;border-radius:14px;border:1px solid #E5E7EB;overflow:hidden;display:flex;flex-direction:column;transition:box-shadow .2s,transform .2s"
             onmouseenter="this.style.boxShadow='0 10px 28px rgba(0,0,0,.10)';this.style.transform='translateY(-3px)'"
             onmouseleave="this.style.boxShadow='none';this.style.transform='translateY(0)'">
          <div style="height:170px;background:linear-gradient(135deg,#F9FAFB 0%,#F3F4F6 100%);display:flex;align-items:center;justify-content:center;overflow:hidden;position:relative">
            <img src="<?= getProductImageUrl($prod) ?>"
                 alt="<?= htmlspecialchars($prod['nombre']) ?>"
                 loading="lazy"
                 style="width:100%;height:100%;object-fit:cover"
                 onerror="this.parentElement.innerHTML='<span style=\'font-size:3.5rem\'>🥩</span>'">
            <button type="button"
                    class="btn-fav-mini is-fav"
                    data-producto-id="<?= (int)$prod['id'] ?>"
                    onclick="toggleFavoritoEnLista(event, this)"
                    aria-label="Quitar de favoritos"
                    title="Quitar de favoritos"
                    style="position:absolute;top:10px;right:10px;width:34px;height:34px;border-radius:50%;border:1px solid #FECACA;background:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,.08)">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="var(--color-primary)" stroke="var(--color-primary)" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 016.364 0L12 7.636l1.318-1.318a4.5 4.5 0 116.364 6.364L12 20.364l-7.682-7.682a4.5 4.5 0 010-6.364z"/></svg>
            </button>
          </div>
          <div style="padding:14px 16px;flex:1;display:flex;flex-direction:column">
            <span style="font-size:.65rem;font-weight:700;color:var(--color-primary);background:#FEF2F2;padding:2px 8px;border-radius:999px;text-transform:uppercase;letter-spacing:.04em;align-self:flex-start;margin-bottom:6px">
              <?= htmlspecialchars($prod['categoria_nombre'] ?? '') ?>
            </span>
            <div style="font-weight:800;font-size:.95rem;color:#111827;line-height:1.3"><?= htmlspecialchars($prod['nombre']) ?></div>
            <div style="font-size:.78rem;color:#6B7280;margin-top:2px"><?= htmlspecialchars($prod['presentacion'] ?? '') ?></div>
            <div style="margin-top:10px">
              <div style="font-size:1.25rem;font-weight:900;color:var(--color-primary);line-height:1">
                $<?= number_format((float)($prod['precio_base'] ?? 0), 2) ?>
              </div>
              <div style="font-size:.72rem;color:#9CA3AF;margin-top:2px">por <?= htmlspecialchars($prod['presentacion'] ?? '') ?></div>
            </div>
          </div>
          <div style="padding:10px 14px;border-top:1px solid #F3F4F6;display:flex;gap:8px">
            <a href="<?= BASE_URL ?>catalogo/index?buscar=<?= urlencode($prod['nombre']) ?>"
               style="flex:1;padding:8px 10px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;text-align:center;font-size:.8rem;color:#374151;text-decoration:none;font-weight:600">
              Ver en catálogo
            </a>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<script>
const BASE_URL_FAV = '<?= BASE_URL ?>';

async function toggleFavoritoEnLista(ev, btn) {
  ev.preventDefault();
  ev.stopPropagation();
  const productoId = btn.dataset.productoId;
  if (!productoId || btn.disabled) return;
  btn.disabled = true;
  try {
    const fd = new FormData();
    fd.append('producto_id', productoId);
    const res = await fetch(BASE_URL_FAV + 'favorito/toggle', {
      method: 'POST',
      body: fd,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (!data.ok) throw new Error(data.error || 'Error');
    // Si se quitó de favoritos, retiramos la card de la vista
    if (!data.favorito) {
      const card = btn.closest('.fav-card');
      if (card) {
        card.style.transition = 'opacity .25s, transform .25s';
        card.style.opacity = '0';
        card.style.transform = 'scale(.95)';
        setTimeout(() => card.remove(), 250);
      }
    }
  } catch (err) {
    console.error('toggleFavoritoEnLista', err);
    btn.disabled = false;
  }
}
</script>
