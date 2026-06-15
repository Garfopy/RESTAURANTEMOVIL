<?php ob_start(); ?>
<style>
.local-card {
  background:#fff;border:1.5px solid #E5E7EB;border-radius:16px;
  overflow:hidden;transition:box-shadow .15s,border-color .15s;
}
.local-card:hover { box-shadow:0 4px 20px rgba(0,0,0,.08);border-color:#D1D5DB; }
.local-card.is-active { border-color:var(--cp);box-shadow:0 0 0 3px color-mix(in srgb,var(--cp) 12%,transparent); }
.local-card-header { padding:18px 20px 14px;border-bottom:1px solid #F3F4F6; }
.local-card-body   { padding:14px 20px; }
.local-card-footer { padding:12px 20px;border-top:1px solid #F3F4F6;display:flex;gap:8px;flex-wrap:wrap; }
.local-btn {
  flex:1;padding:8px 10px;border-radius:8px;font-size:.78rem;font-weight:600;
  text-align:center;text-decoration:none;border:1.5px solid #E5E7EB;
  color:#374151;background:#F9FAFB;transition:.15s;cursor:pointer;display:inline-block;
}
.local-btn:hover { border-color:var(--cp);color:var(--cp);background:#fff; }
.local-btn.primary { background:var(--cp);color:#fff;border-color:var(--cp); }
.local-btn.primary:hover { opacity:.9; }
.vinculo-select {
  width:100%;padding:6px 10px;border:1.5px solid #E5E7EB;border-radius:8px;
  font-size:.8rem;color:#374151;background:#fff;outline:none;
}
.vinculo-select:focus { border-color:var(--cp); }
</style>

<?php /* flash rendered by layout */ ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div style="font-size:.85rem;color:#6B7280">
    <?= count($sucursales) ?> local<?= count($sucursales) !== 1 ? 'es' : '' ?> registrado<?= count($sucursales) !== 1 ? 's' : '' ?>
  </div>
  <a href="<?= BASE_URL ?>restaurante/crear" class="btn btn-primary btn-sm">+ Nuevo local</a>
</div>

<!-- Info banner -->
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:14px 18px;margin-bottom:20px;
            display:flex;align-items:flex-start;gap:12px">
  <svg width="18" height="18" fill="none" stroke="#3B82F6" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <div style="font-size:.85rem;color:#1E40AF;line-height:1.5">
    <strong>Cada local es independiente.</strong> Tiene su propio menú, inventario y configuración.
    Vincula cada local a una sucursal de CarniHub para que los pedidos actualicen el inventario automáticamente.
  </div>
</div>

<!-- Grid de locales -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:16px">
  <?php foreach ($sucursales as $s):
    $activo = ((int)$s['id'] === (int)$restauranteActivoId);
    $sucursalVinculadaId = (int)($s['sucursal_id'] ?? 0);
    // Find linked CarniHub sucursal name
    $sucursalVinculadaNombre = null;
    foreach ($sucursalesCarniHub as $sc) {
      if ((int)$sc['id'] === $sucursalVinculadaId) {
        $sucursalVinculadaNombre = $sc['nombre'];
        break;
      }
    }
  ?>
  <div class="local-card <?= $activo ? 'is-active' : '' ?>">

    <div class="local-card-header">
      <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px">
        <div>
          <div style="font-weight:800;font-size:1rem;color:#111827;margin-bottom:4px">
            <?= htmlspecialchars($s['nombre']) ?>
          </div>
          <?php if (!empty($s['direccion'])): ?>
          <div style="font-size:.78rem;color:#6B7280;display:flex;align-items:center;gap:4px">
            <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <?= htmlspecialchars($s['direccion']) ?>
          </div>
          <?php endif; ?>
        </div>
        <?php if ($activo): ?>
        <span style="background:var(--cp);color:#fff;border-radius:99px;padding:3px 10px;
                     font-size:.68rem;font-weight:700;white-space:nowrap;flex-shrink:0">
          Activo
        </span>
        <?php else: ?>
        <span style="background:#F3F4F6;color:#9CA3AF;border-radius:99px;padding:3px 10px;
                     font-size:.68rem;font-weight:600;white-space:nowrap;flex-shrink:0">
          Inactivo
        </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="local-card-body">
      <div style="display:flex;gap:16px;margin-bottom:12px">
        <div style="text-align:center">
          <div style="font-size:1.1rem;font-weight:800;color:#111827"><?= $s['num_platillos'] ?></div>
          <div style="font-size:.7rem;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Platillos</div>
        </div>
        <div style="width:1px;background:#F3F4F6"></div>
        <div style="text-align:center">
          <div style="font-size:1.1rem;font-weight:800;color:#111827"><?= $s['num_ingredientes'] ?></div>
          <div style="font-size:.7rem;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.04em">Ingredientes</div>
        </div>
        <?php if (!empty($s['telefono'])): ?>
        <div style="width:1px;background:#F3F4F6"></div>
        <div style="display:flex;align-items:center">
          <div style="font-size:.78rem;color:#374151">📞 <?= htmlspecialchars($s['telefono']) ?></div>
        </div>
        <?php endif; ?>
      </div>

      <!-- Vinculación a sucursal CarniHub -->
      <?php if (!empty($sucursalesCarniHub)): ?>
      <div style="margin-bottom:10px">
        <div style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;
                    letter-spacing:.04em;margin-bottom:5px">
          Sucursal CarniHub vinculada
        </div>
        <?php if ($sucursalVinculadaNombre): ?>
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px">
          <span style="padding:3px 10px;background:#F0FDF4;color:#166534;border-radius:99px;
                       font-size:.75rem;font-weight:700">
            ✓ <?= htmlspecialchars($sucursalVinculadaNombre) ?>
          </span>
        </div>
        <?php else: ?>
        <div style="font-size:.75rem;color:#F59E0B;font-weight:600;margin-bottom:6px">
          ⚠ Sin vincular — los pedidos no actualizarán este inventario
        </div>
        <?php endif; ?>
        <form method="POST" action="<?= BASE_URL ?>restaurante/vincularSucursal"
              style="display:flex;gap:6px;align-items:center">
          <input type="hidden" name="local_id" value="<?= (int)$s['id'] ?>">
          <select name="sucursal_id" class="vinculo-select">
            <option value="">— Sin vincular —</option>
            <?php foreach ($sucursalesCarniHub as $sc): ?>
            <option value="<?= (int)$sc['id'] ?>"
              <?= $sucursalVinculadaId === (int)$sc['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($sc['nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <button type="submit"
            style="padding:6px 12px;background:var(--cp);color:#fff;border:none;border-radius:8px;
                   font-size:.78rem;font-weight:700;cursor:pointer;white-space:nowrap">
            Guardar
          </button>
        </form>
      </div>
      <?php endif; ?>

      <?php if ($activo): ?>
      <div style="margin-top:4px;padding:8px 12px;background:#F0FDF4;border-radius:8px;
                  font-size:.78rem;color:#166534;font-weight:600;display:flex;align-items:center;gap:6px">
        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        Estás administrando este local ahora mismo
      </div>
      <?php endif; ?>
    </div>

    <div class="local-card-footer">
      <?php if (!$activo): ?>
      <a href="<?= BASE_URL ?>restaurante/activar/<?= (int)$s['id'] ?>?redirect=restaurante/locales"
         class="local-btn primary" style="flex:100%;text-align:center;margin-bottom:4px">
        ⚡ Seleccionar este local
      </a>
      <?php endif; ?>
      <a href="<?= BASE_URL ?>restaurante/activar/<?= (int)$s['id'] ?>?redirect=rest-menu/index"
         class="local-btn">
        🍽 Menú
      </a>
      <a href="<?= BASE_URL ?>restaurante/activar/<?= (int)$s['id'] ?>?redirect=rest-inventario/index"
         class="local-btn">
        📦 Inventario
      </a>
      <a href="<?= BASE_URL ?>restaurante/activar/<?= (int)$s['id'] ?>?redirect=rest-config/index"
         class="local-btn">
        ⚙ Config
      </a>
    </div>

  </div>
  <?php endforeach; ?>
</div>

<?php if (empty($sucursales)): ?>
<div style="text-align:center;padding:60px 20px;color:#9CA3AF">
  <div style="font-size:3rem;margin-bottom:12px">🏪</div>
  <div style="font-weight:600;color:#374151;margin-bottom:8px">Sin locales registrados</div>
  <a href="<?= BASE_URL ?>restaurante/crear" class="btn btn-primary">+ Crear local</a>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
