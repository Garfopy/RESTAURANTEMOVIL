<?php ob_start(); ?>
<style>
.det-header { background:#fff;border:1.5px solid #E5E7EB;border-radius:14px;padding:22px 28px;margin-bottom:18px }
.det-stats   { display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:0;
               background:#fff;border:1.5px solid #E5E7EB;border-radius:14px;overflow:hidden;margin-bottom:18px }
.det-stat    { padding:14px 18px;border-right:1px solid #E5E7EB }
.det-stat:last-child { border-right:none }
.det-stat-label { font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#9CA3AF;font-weight:700;margin-bottom:4px }
.det-stat-value { font-size:1rem;font-weight:700;color:#111827 }
.rec-table { width:100%;border-collapse:collapse;font-size:.85rem }
.rec-table th { font-size:.72rem;text-transform:uppercase;letter-spacing:.04em;color:#9CA3AF;font-weight:700;
                padding:8px 14px;border-bottom:2px solid #E5E7EB;text-align:left }
.rec-table th:last-child,.rec-table td:last-child { text-align:right }
.rec-table th.num,.rec-table td.num { text-align:right }
.rec-table td { padding:11px 14px;border-bottom:1px solid #F3F4F6;color:#374151 }
.rec-table tr:last-child td { border-bottom:none }
.rec-table tr:hover td { background:#FAFAFA }
.rec-total td { border-top:2px solid #E5E7EB!important;font-weight:700;background:#F9FAFB!important }
</style>

<a href="<?= BASE_URL ?>rest-menu/index"
   style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:18px">
  ← Volver a Recetas
</a>

<!-- Header platillo -->
<div class="det-header" style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
  <div>
    <h1 style="font-size:1.35rem;font-weight:800;color:#111827;margin:0 0 4px">
      <?= htmlspecialchars($platillo['nombre']) ?>
    </h1>
    <?php if (!empty($platillo['categoria_nombre'])): ?>
    <div style="font-size:.82rem;color:#6B7280"><?= htmlspecialchars($platillo['categoria_nombre']) ?></div>
    <?php endif; ?>
  </div>
  <a href="<?= BASE_URL ?>rest-menu/form/<?= $platillo['id'] ?>" class="btn btn-primary btn-sm">
    ✏ Editar
  </a>
</div>

<!-- Stats row -->
<div class="det-stats">
  <div class="det-stat">
    <div class="det-stat-label">Porciones Base</div>
    <div class="det-stat-value"><?= $porciones ?></div>
  </div>
  <div class="det-stat">
    <div class="det-stat-label">Tiempo Preparación</div>
    <div class="det-stat-value"><?= ($platillo['tiempo_preparacion_min'] ?? 0) > 0 ? (int)$platillo['tiempo_preparacion_min'] . ' min' : '— min' ?></div>
  </div>
  <div class="det-stat">
    <div class="det-stat-label">Ingredientes</div>
    <div class="det-stat-value"><?= count($platillo['ingredientes'] ?? []) ?></div>
  </div>
  <div class="det-stat">
    <div class="det-stat-label">Estado</div>
    <div class="det-stat-value" style="color:<?= ($platillo['disponible'] ?? 0) ? '#16A34A' : '#9CA3AF' ?>">
      <?= ($platillo['disponible'] ?? 0) ? 'Activo' : 'Inactivo' ?>
    </div>
  </div>
  <div class="det-stat">
    <div class="det-stat-label">Precio venta</div>
    <div class="det-stat-value">$<?= number_format((float)($platillo['precio'] ?? 0), 2) ?></div>
  </div>
</div>

<!-- Tabla gramajes y costos -->
<?php if (!empty($platillo['ingredientes'])): ?>
<div style="background:#fff;border:1.5px solid #E5E7EB;border-radius:14px;overflow:hidden;margin-bottom:18px">
  <div style="padding:16px 20px;border-bottom:1px solid #F3F4F6;display:flex;align-items:center;gap:8px">
    <svg width="16" height="16" fill="none" stroke="#6B7280" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01
               M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
    </svg>
    <span style="font-weight:700;color:#374151;font-size:.9rem">Gramajes por Ingrediente</span>
    <?php if ($platillo['receta']): ?>
    <span style="font-size:.75rem;color:#9CA3AF">(<?= htmlspecialchars($platillo['receta']['notas'] ?? '') ?>)</span>
    <?php endif; ?>
  </div>
  <div style="overflow-x:auto">
    <table class="rec-table">
      <thead>
        <tr>
          <th>Ingrediente</th>
          <th class="num">Cantidad</th>
          <th>Unidad</th>
          <th class="num">Costo/Unidad</th>
          <th class="num">Costo Total</th>
          <th>Notas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($platillo['ingredientes'] as $ing):
          $costo = (float)($ing['costo_total_ing'] ?? 0);
          $costoUnit = (float)($ing['costo_por_unidad_receta'] ?? 0);
        ?>
        <tr <?= ($ing['es_informativo'] ?? 0) ? 'style="opacity:.6"' : '' ?>>
          <td>
            <strong><?= htmlspecialchars($ing['ingrediente_nombre'] ?? $ing['nombre'] ?? '—') ?></strong>
            <?php if ($ing['es_informativo'] ?? 0): ?>
            <span class="badge badge-gray" style="font-size:.65rem;margin-left:4px">solo info</span>
            <?php endif; ?>
          </td>
          <td class="num"><?= number_format((float)$ing['cantidad'], 3) ?></td>
          <td><?= htmlspecialchars($ing['unidad'] ?? '') ?></td>
          <td class="num" style="color:#6B7280">$<?= number_format($costoUnit, 2) ?></td>
          <td class="num"><strong>$<?= number_format($costo, 2) ?></strong></td>
          <td style="color:#9CA3AF;font-size:.78rem"><?= htmlspecialchars($ing['notas'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="rec-total">
          <td colspan="4" style="text-align:right;color:#374151">
            COSTO TOTAL (<?= $porciones ?> porcion<?= $porciones != 1 ? 'es' : '' ?>):
          </td>
          <td class="num" style="color:#16A34A;font-size:1rem">$<?= number_format($platillo['costo_total'], 2) ?></td>
          <td style="color:#6B7280;font-size:.78rem">
            $<?= number_format($platillo['costo_por_porcion'], 2) ?>/porción
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Calculadora logística -->
<?php
$precio   = (float)($platillo['precio'] ?? 0);
$costo    = $platillo['costo_total'];
$margen   = $precio - $costo;
$margenPct = $precio > 0 ? $margen / $precio * 100 : 0;
?>
<?php if ($precio > 0 && $costo > 0): ?>
<div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:14px;padding:20px 24px">
  <div style="font-weight:700;color:#374151;font-size:.9rem;margin-bottom:14px;display:flex;align-items:center;gap:6px">
    <svg width="14" height="14" fill="none" stroke="#6B7280" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
    </svg>
    Calculadora de Costo Logístico
  </div>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:16px">
    <div style="text-align:center;padding:12px;background:#fff;border-radius:10px;border:1px solid #E5E7EB">
      <div style="font-size:.72rem;color:#9CA3AF;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">Costo ingredientes</div>
      <div style="font-size:1.1rem;font-weight:800;color:#111827">$<?= number_format($costo, 2) ?></div>
      <div style="font-size:.72rem;color:#6B7280;margin-top:2px">$<?= number_format($platillo['costo_por_porcion'], 2) ?>/porción</div>
    </div>
    <div style="text-align:center;padding:12px;background:#fff;border-radius:10px;border:1px solid #E5E7EB">
      <div style="font-size:.72rem;color:#9CA3AF;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">Precio de venta</div>
      <div style="font-size:1.1rem;font-weight:800;color:#111827">$<?= number_format($precio, 2) ?></div>
      <div style="font-size:.72rem;color:#6B7280;margin-top:2px">por porción</div>
    </div>
    <div style="text-align:center;padding:12px;background:<?= $margen >= 0 ? '#F0FDF4' : '#FEF2F2' ?>;border-radius:10px;border:1px solid <?= $margen >= 0 ? '#BBF7D0' : '#FECACA' ?>">
      <div style="font-size:.72rem;color:#9CA3AF;font-weight:700;text-transform:uppercase;letter-spacing:.04em;margin-bottom:6px">Margen estimado</div>
      <div style="font-size:1.1rem;font-weight:800;color:<?= $margen >= 0 ? '#16A34A' : '#EF4444' ?>">$<?= number_format($margen, 2) ?></div>
      <div style="font-size:.72rem;color:<?= $margen >= 0 ? '#16A34A' : '#EF4444' ?>;margin-top:2px"><?= number_format($margenPct, 1) ?>% del precio</div>
    </div>
  </div>
  <div style="font-size:.72rem;color:#9CA3AF;margin-top:12px;text-align:center">
    * Costo basado solo en ingredientes de la receta (sin mano de obra, renta, servicios)
  </div>
</div>
<?php endif; ?>
<?php else: ?>
<div class="empty-state">
  <div style="font-size:.9rem;font-weight:600;color:#374151">Sin receta definida</div>
  <div style="font-size:.82rem;margin-top:4px">
    <a href="<?= BASE_URL ?>rest-menu/form/<?= $platillo['id'] ?>" style="color:var(--cp)">Agregar receta →</a>
  </div>
</div>
<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
