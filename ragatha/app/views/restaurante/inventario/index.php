<?php ob_start(); ?>

<style>
.inv-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill,minmax(290px,1fr));
  gap:16px;
  margin-bottom:32px;
}
.inv-card {
  background:#fff;
  border-radius:14px;
  border:1.5px solid #E5E7EB;
  padding:16px;
  transition:box-shadow .15s,border-color .15s;
  position:relative;
  overflow:hidden;
}
.inv-card:hover { box-shadow:0 4px 16px rgba(0,0,0,.08); border-color:#D1D5DB; }
.inv-card.bajo { border-color:#FECACA; background:#FFFBFB; }
.inv-card.bajo::before {
  content:'';
  position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,#EF4444,#F87171);
}
.inv-card.ok::before {
  content:'';
  position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,#22C55E,#4ADE80);
}
.inv-card-head {
  display:flex;justify-content:space-between;align-items:flex-start;
  margin-bottom:12px;gap:8px;
}
.inv-card-name {
  font-weight:700;font-size:.95rem;color:#111827;
  line-height:1.25;flex:1;min-width:0;
}
.inv-card-name small {
  display:block;font-size:.72rem;font-weight:400;color:#9CA3AF;margin-top:1px;
}
.inv-stock-bar-wrap { margin-bottom:10px; }
.inv-stock-label {
  display:flex;justify-content:space-between;
  font-size:.75rem;color:#6B7280;margin-bottom:4px;
}
.inv-stock-label strong { color:#111827; }
.inv-bar {
  height:7px;border-radius:4px;background:#F3F4F6;overflow:hidden;
}
.inv-bar-fill {
  height:100%;border-radius:4px;transition:width .3s;
  background:linear-gradient(90deg,#22C55E,#4ADE80);
}
.inv-bar-fill.low { background:linear-gradient(90deg,#EF4444,#F87171); }
.inv-bar-fill.warn { background:linear-gradient(90deg,#F59E0B,#FBBF24); }
.inv-card-meta {
  display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;align-items:center;
}
.inv-card-cost {
  font-size:.78rem;color:#6B7280;
  margin-bottom:10px;
}
.inv-card-cost strong { color:#374151; }
.inv-card-actions {
  display:flex;gap:6px;
}
.inv-card-actions .btn { flex:1;justify-content:center;font-size:.78rem;padding:5px 8px; }

/* Movements table */
.mov-row { display:grid;grid-template-columns:90px 1fr 80px 90px 80px;gap:8px;
  align-items:center;padding:10px 0;border-bottom:1px solid #F3F4F6;font-size:.82rem; }
.mov-row:last-child { border-bottom:none; }
.mov-row .mov-tipo { display:inline-flex;align-items:center;gap:4px; }
.mov-row .mov-ing { font-weight:600;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.mov-row .mov-fecha { color:#9CA3AF;font-size:.75rem; }
.mov-header { font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#9CA3AF; }
</style>

<!-- Alertas stock bajo -->
<?php if (!empty($alertas)): ?>
<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:16px;display:flex;align-items:center;gap:10px">
  <svg width="18" height="18" fill="none" stroke="#EF4444" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <span style="font-size:.85rem;font-weight:600;color:#991B1B">
    <?= count($alertas) ?> ingrediente<?= count($alertas) > 1 ? 's' : '' ?> con stock bajo:
    <?= implode(', ', array_column($alertas, 'nombre')) ?>
  </span>
</div>
<?php endif; ?>

<!-- Guía colapsable -->
<div id="guia-inv" style="display:none;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:16px;margin-bottom:16px;font-size:.84rem;color:#1E3A5F">
  <div style="font-weight:700;margin-bottom:10px;font-size:.92rem">📋 Cómo operar el inventario</div>
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <div><span style="background:#DCFCE7;color:#166534;border-radius:6px;padding:2px 8px;font-weight:600;font-size:.75rem">＋ Entrada</span>
      — Cuando recibes mercancía, compras ingredientes o devuelven producto. <strong>Suma</strong> al stock.</div>
    <div><span style="background:#FEE2E2;color:#991B1B;border-radius:6px;padding:2px 8px;font-weight:600;font-size:.75rem">－ Salida</span>
      — Uso directo sin pasar por pedido (ej: consumo del personal). <strong>Resta</strong> del stock.</div>
    <div><span style="background:#FEF3C7;color:#92400E;border-radius:6px;padding:2px 8px;font-weight:600;font-size:.75rem">Merma</span>
      — Producto caducado, dañado o derramado. Registra la pérdida.</div>
    <div><span style="background:#DBEAFE;color:#1E40AF;border-radius:6px;padding:2px 8px;font-weight:600;font-size:.75rem">Ajuste</span>
      — Corrección manual tras conteo físico del almacén.</div>
  </div>
  <div style="margin-top:10px;padding-top:10px;border-top:1px solid #BFDBFE">
    <strong>Alerta stock bajo:</strong> cuando el stock llega al mínimo que configures, aparece un aviso rojo. <br>
    <strong>Descuento automático:</strong> cuando el chef marca un pedido como "listo", los ingredientes de la receta se descuentan solos. <br>
    <strong>Stock mínimo:</strong> edita el ingrediente y ajusta el campo "Stock mínimo" para configurar desde qué cantidad te alertamos.
  </div>
</div>

<!-- Barra de herramientas -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;gap:12px">
  <div style="display:flex;gap:8px;align-items:center;flex:1">
    <div style="position:relative;flex:1;max-width:320px">
      <svg width="16" height="16" fill="none" stroke="#9CA3AF" viewBox="0 0 24 24"
           style="position:absolute;left:10px;top:50%;transform:translateY(-50%);pointer-events:none">
        <circle cx="11" cy="11" r="8"/><path stroke-linecap="round" d="m21 21-4.35-4.35"/>
      </svg>
      <input type="text" id="invBuscar" oninput="filtrarIngredientes()"
             placeholder="Buscar ingrediente o categoría…"
             style="width:100%;padding:8px 12px 8px 34px;border:1.5px solid #E5E7EB;border-radius:10px;
                    font-size:.85rem;box-sizing:border-box;outline:none"
             onfocus="this.style.borderColor='var(--cp)'" onblur="this.style.borderColor='#E5E7EB'">
    </div>
    <a href="<?= BASE_URL ?>rest-inventario/movimientos" class="btn btn-outline btn-sm" style="white-space:nowrap">Ver historial</a>
    <a href="<?= BASE_URL ?>rest-inventario/proyecciones" class="btn btn-outline btn-sm" style="white-space:nowrap">📊 Proyecciones</a>
    <a href="<?= BASE_URL ?>rest-inventario/pedidosSugeridos" class="btn btn-outline btn-sm" style="white-space:nowrap">📦 Pedidos sugeridos</a>
    <button onclick="toggleGuia()" title="Ayuda"
            style="padding:7px 11px;border:1.5px solid #E5E7EB;border-radius:10px;background:#fff;
                   cursor:pointer;font-size:.85rem;color:#6B7280;transition:.15s"
            onmouseover="this.style.borderColor='#93C5FD'" onmouseout="this.style.borderColor='#E5E7EB'">
      ❓ Guía
    </button>
  </div>
  <button onclick="resetIngForm(); rstModal('modalIng')" class="btn btn-primary btn-sm" style="white-space:nowrap">
    + Ingrediente
  </button>
</div>

<!-- Cards grid -->
<?php if (!empty($ingredientes)): ?>
<div class="inv-grid">
<?php foreach ($ingredientes as $ing):
  $stock = (float)$ing['stock'];
  $min   = (float)$ing['stock_minimo'];
  $bajo  = $stock <= $min;
  $pct   = $min > 0 ? min(100, round($stock / ($min * 2) * 100)) : ($stock > 0 ? 100 : 0);
  $fillCls = $bajo ? 'low' : ($pct < 60 ? 'warn' : '');
?>
<div class="inv-card <?= $bajo ? 'bajo' : 'ok' ?>"
     id="inv-card-<?= $ing['id'] ?>"
     data-min="<?= $min ?>" data-unidad="<?= htmlspecialchars($ing['unidad_principal'], ENT_QUOTES) ?>"
     data-search="<?= strtolower(htmlspecialchars($ing['nombre'] . ' ' . ($ing['categoria'] ?? ''), ENT_QUOTES)) ?>">
  <div class="inv-card-head">
    <div class="inv-card-name">
      <?= htmlspecialchars($ing['nombre']) ?>
      <?php if ($ing['categoria']): ?>
      <small><?= htmlspecialchars($ing['categoria']) ?></small>
      <?php endif; ?>
    </div>
    <?php if ($bajo): ?>
    <svg width="16" height="16" fill="none" stroke="#EF4444" viewBox="0 0 24 24" title="Stock bajo" style="flex-shrink:0;margin-top:2px">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
    </svg>
    <?php endif; ?>
  </div>

  <div class="inv-stock-bar-wrap">
    <div class="inv-stock-label">
      <span>Stock actual</span>
      <strong id="inv-sv-<?= $ing['id'] ?>" style="color:<?= $bajo ? '#EF4444' : '#111827' ?>"><?= number_format($stock, 2) ?> <?= htmlspecialchars($ing['unidad_principal']) ?></strong>
    </div>
    <div class="inv-bar">
      <div class="inv-bar-fill <?= $fillCls ?>" id="inv-bar-<?= $ing['id'] ?>" style="width:<?= $pct ?>%"></div>
    </div>
    <?php if ($min > 0): ?>
    <div style="font-size:.7rem;color:#9CA3AF;margin-top:3px">Mínimo: <?= number_format($min, 2) ?> <?= htmlspecialchars($ing['unidad_principal']) ?></div>
    <?php endif; ?>
  </div>

  <div class="inv-card-meta">
    <?php if ($ing['proveedor_carnihub']): ?>
    <span class="badge badge-purple" style="font-size:.7rem">CarniHub</span>
    <?php elseif ($ing['proveedor_nombre']): ?>
    <span class="badge badge-gray" style="font-size:.68rem"><?= htmlspecialchars($ing['proveedor_nombre']) ?></span>
    <?php endif; ?>
    <span id="inv-badge-<?= $ing['id'] ?>" class="badge <?= $bajo ? 'badge-red' : 'badge-green' ?>" style="font-size:.7rem">
      <?= $bajo ? 'Stock bajo' : 'OK' ?>
    </span>
  </div>

  <div class="inv-card-cost">
    Costo/u: <strong>$<?= number_format((float)$ing['costo_unitario'], 2) ?></strong>
    <?php if ((float)$ing['costo_unitario'] > 0 && $stock > 0): ?>
    &nbsp;·&nbsp; Valor stock actual: <strong>$<?= number_format($stock * (float)$ing['costo_unitario'], 2) ?></strong>
    <?php endif; ?>
  </div>

  <div class="inv-card-actions">
    <button onclick='abrirModificar(<?= htmlspecialchars(json_encode($ing), ENT_QUOTES) ?>)'
            class="btn btn-primary" style="flex:1;justify-content:center;font-size:.82rem;padding:8px 12px">
      Modificar
    </button>
    <button onclick="eliminarIngrediente(<?= (int)$ing['id'] ?>, '<?= htmlspecialchars(addslashes($ing['nombre']), ENT_QUOTES) ?>')"
            class="btn btn-danger" title="Eliminar ingrediente"
            style="padding:8px 10px;flex-shrink:0;background:#FEF2F2;color:#DC2626;border:1.5px solid #FECACA">
      <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
    </button>
  </div>
</div>
<?php endforeach; ?>
</div>

<?php else: ?>
<div id="noMatch" style="display:none;text-align:center;padding:32px;color:#9CA3AF;font-size:.9rem">
  Sin resultados para tu búsqueda.
</div>
<div class="empty-state" style="margin-bottom:32px">
  <svg width="40" height="40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
  <div style="font-size:.95rem;font-weight:600;color:#374151;margin-bottom:4px">Sin ingredientes</div>
  <div style="font-size:.85rem">Agrega ingredientes de CarniHub o de proveedores externos</div>
</div>
<?php endif; ?>

<?php if (!empty($inactivos)): ?>
<!-- Papelera: ingredientes desactivados -->
<details style="background:#FFF7ED;border:1.5px solid #FED7AA;border-radius:14px;padding:16px 20px;margin-bottom:24px">
  <summary style="cursor:pointer;font-size:.9rem;font-weight:600;color:#C2410C;list-style:none;display:flex;align-items:center;gap:8px">
    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
    </svg>
    Ingredientes eliminados (<?= count($inactivos) ?>) — clic para restaurar
  </summary>
  <div style="margin-top:14px;display:flex;flex-direction:column;gap:8px">
    <?php foreach ($inactivos as $ing): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;background:#fff;border:1px solid #FED7AA;border-radius:10px;padding:10px 14px">
      <div>
        <span style="font-weight:600;font-size:.88rem;color:#374151"><?= htmlspecialchars($ing['nombre']) ?></span>
        <?php if (!empty($ing['proveedor_carnihub'])): ?>
          <span style="font-size:.72rem;background:#EDE9FE;color:#6D28D9;padding:1px 6px;border-radius:6px;margin-left:6px">🔗 CarniHub</span>
        <?php endif; ?>
        <?php if (!empty($ing['categoria'])): ?>
          <span style="font-size:.72rem;color:#9CA3AF;margin-left:6px"><?= htmlspecialchars($ing['categoria']) ?></span>
        <?php endif; ?>
      </div>
      <a href="<?= BASE_URL ?>rest-inventario/reactivar/<?= (int)$ing['id'] ?>"
         onclick="return confirm('¿Restaurar el ingrediente \'<?= htmlspecialchars(addslashes($ing['nombre']), ENT_QUOTES) ?>\' al inventario?')"
         style="font-size:.78rem;font-weight:600;color:#16A34A;background:#F0FDF4;border:1px solid #86EFAC;padding:5px 12px;border-radius:8px;text-decoration:none;white-space:nowrap">
        ↩ Restaurar
      </a>
    </div>
    <?php endforeach; ?>
  </div>
</details>
<?php endif; ?>

<!-- Movimientos recientes -->
<?php if (!empty($movRecientes)): ?>
<div style="background:#fff;border-radius:14px;border:1.5px solid #E5E7EB;padding:20px;margin-bottom:24px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div style="font-size:.95rem;font-weight:700;color:#111827">Movimientos recientes</div>
    <a href="<?= BASE_URL ?>rest-inventario/movimientos" style="font-size:.78rem;color:var(--cp);font-weight:600;text-decoration:none">
      Ver todos →
    </a>
  </div>
  <!-- Header -->
  <div class="mov-row mov-header" style="padding-bottom:6px;border-bottom:2px solid #E5E7EB">
    <div>Fecha</div>
    <div>Ingrediente / Motivo</div>
    <div style="text-align:center">Tipo</div>
    <div style="text-align:right">Cantidad</div>
    <div style="text-align:right">Stock final</div>
  </div>
  <?php
  $tipoCls = ['entrada'=>'badge-green','salida'=>'badge-red','merma'=>'badge-amber','ajuste'=>'badge-blue'];
  $tipoIcon = [
    'entrada' => '<svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"/></svg>',
    'salida'  => '<svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>',
    'merma'   => '<svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/></svg>',
    'ajuste'  => '<svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h5M20 20v-5h-5M4 9a9 9 0 0115 0M20 15a9 9 0 01-15 0"/></svg>',
  ];
  foreach ($movRecientes as $m):
    $cls  = $tipoCls[$m['tipo']] ?? 'badge-gray';
    $icon = $tipoIcon[$m['tipo']] ?? '';
    $fecha = date('d/m H:i', strtotime($m['created_at']));
    $delta = in_array($m['tipo'], ['entrada','ajuste']) && (float)$m['cantidad'] >= 0 ? '+' : '-';
    $delta = $m['tipo'] === 'ajuste' ? '±' : $delta;
  ?>
  <div class="mov-row">
    <div class="mov-fecha"><?= $fecha ?></div>
    <div>
      <div class="mov-ing" title="<?= htmlspecialchars($m['ingrediente_nombre'] ?? '') ?>"><?= htmlspecialchars($m['ingrediente_nombre'] ?? '—') ?></div>
      <?php if ($m['motivo']): ?>
      <div style="font-size:.72rem;color:#9CA3AF;margin-top:1px"><?= htmlspecialchars($m['motivo']) ?></div>
      <?php endif; ?>
    </div>
    <div style="text-align:center">
      <span class="badge <?= $cls ?>" style="font-size:.68rem;display:inline-flex;align-items:center;gap:3px">
        <?= $icon ?><?= ucfirst($m['tipo']) ?>
      </span>
    </div>
    <div style="text-align:right;font-weight:600;font-size:.82rem;color:<?= in_array($m['tipo'],['entrada']) ? '#16A34A' : '#EF4444' ?>">
      <?= $delta ?><?= number_format(abs((float)$m['cantidad']), 2) ?>
      <span style="font-size:.7rem;font-weight:400;color:#9CA3AF"><?= htmlspecialchars($m['unidad_principal'] ?? '') ?></span>
    </div>
    <div style="text-align:right;font-size:.82rem;color:#374151">
      <?= number_format((float)$m['stock_despues'], 2) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$ingCategorias = array_values(array_unique(array_filter(array_column($ingredientes ?? [], 'categoria'))));
sort($ingCategorias);
?>
<datalist id="dlCatIng">
  <?php foreach ($ingCategorias as $c): ?>
  <option value="<?= htmlspecialchars($c) ?>">
  <?php endforeach; ?>
</datalist>

<!-- Modal nuevo/editar ingrediente -->
<div id="modalIng" class="rst-modal-backdrop">
  <div class="rst-modal" style="max-width:520px">
    <div class="rst-modal-header">
      <div>
        <div class="rst-modal-title" id="modalIngTitle">Nuevo Ingrediente</div>
        <div style="font-size:.78rem;color:#9CA3AF;margin-top:2px" id="modalIngSub">Proveedor externo</div>
      </div>
      <button class="rst-modal-close" onclick="rstModal('modalIng')">✕</button>
    </div>

    <!-- Tabs fuente -->
    <div class="rst-tabs" id="ingTabs">
      <button class="rst-tab active" data-tab="ext" onclick="switchTab('ext')">Proveedor externo</button>
      <button class="rst-tab" data-tab="ch"  onclick="switchTab('ch')">
        <span style="color:var(--cp);font-weight:700">⚡ Desde CarniHub</span>
      </button>
    </div>

    <form method="POST" action="<?= BASE_URL ?>rest-inventario/guardar" id="formIng">
      <input type="hidden" name="id" id="ingId" value="">
      <input type="hidden" name="proveedor_carnihub" id="ingEsCarniHub" value="0">
      <input type="hidden" name="carnihub_producto_id" id="ingCarniHubId" value="">

      <!-- Panel externo -->
      <div class="rst-tab-panel active" id="panelExt">

        <!-- Nombre + categoría -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
          <div class="form-group" style="grid-column:span 2">
            <label class="form-label">Nombre del ingrediente *</label>
            <input type="text" name="nombre" id="ingNombre" class="form-input"
                   placeholder="Ej: Jitomate, Carne de res, Aceite" required>
          </div>
          <div class="form-group">
            <label class="form-label">Categoría
              <span style="color:#9CA3AF;font-weight:400;font-size:.72rem">— elige o escribe nueva</span>
            </label>
            <input type="text" name="categoria" id="ingCategoria" class="form-input"
                   list="dlCatIng"
                   placeholder="Ej: Lácteos, Carnes, Verduras">
          </div>
          <div class="form-group">
            <label class="form-label">Unidad de medida</label>
            <select name="unidad_principal" id="ingUnidad" class="form-select" onchange="calcCostos()">
              <option value="paquete" selected>paquete</option>
              <option value="pza">pza — pieza</option>
              <option value="porción">porción</option>
              <option value="caja">caja</option>
              <option value="bolsa">bolsa</option>
            </select>
          </div>
        </div>

        <!-- Costo con calculadora -->
        <div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:12px;padding:14px;margin-bottom:14px">
          <div style="font-weight:600;font-size:.85rem;color:#374151;margin-bottom:10px;display:flex;align-items:center;gap:6px">
            <svg width="14" height="14" fill="none" stroke="#6B7280" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            Costo
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Costo por <span id="unidadLabel">paquete</span></label>
              <div style="display:flex;align-items:center;gap:6px">
                <span style="color:#6B7280;font-weight:600">$</span>
                <input type="number" name="costo_unitario" id="ingCosto" class="form-input"
                       value="0" min="0" step="0.0001" placeholder="0.00"
                       oninput="calcCostos()" style="flex:1">
              </div>
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label class="form-label">Stock mínimo (alerta)</label>
              <div style="display:flex;align-items:center;gap:6px">
                <input type="number" name="stock_minimo" id="ingMinimo" class="form-input"
                       value="0" min="0" step="0.001" placeholder="0.000">
                <span id="unidadMinLabel" style="color:#9CA3AF;font-size:.8rem;white-space:nowrap">paquete</span>
              </div>
            </div>
          </div>
          <!-- Calculadora de equivalencias -->
          <div id="calcCostosWrap" style="display:none;margin-top:10px;padding:8px 10px;background:#EFF6FF;border-radius:8px;border:1px solid #BFDBFE">
            <div style="font-size:.72rem;font-weight:700;color:#1E40AF;margin-bottom:4px;text-transform:uppercase;letter-spacing:.04em">Equivalencias de costo</div>
            <div id="calcCostos" style="font-size:.8rem;color:#1E3A5F;display:flex;gap:16px;flex-wrap:wrap"></div>
          </div>
        </div>

        <!-- Proveedor -->
        <div class="form-group" style="margin-bottom:14px">
          <label class="form-label">Proveedor <span style="color:#9CA3AF;font-weight:400">(libre)</span></label>
          <input type="text" name="proveedor_nombre" id="ingProveedor" class="form-input"
                 placeholder="Ej: Mercado, Walmart, Don José">
        </div>

      </div>

      <!-- Panel CarniHub -->
      <div class="rst-tab-panel" id="panelCh">
        <div style="background:#FAF5FF;border:1.5px solid #DDD6FE;border-radius:10px;padding:14px;margin-bottom:14px;font-size:.85rem">
          <div style="font-weight:700;color:#5B21B6;margin-bottom:4px">⚡ Vincular con CarniHub</div>
          <div style="color:#6D28D9;line-height:1.4">Selecciona el producto CarniHub que corresponde a este ingrediente. Los pedidos automáticos se generarán hacia la empresa de ese producto.</div>
        </div>
        <?php if (!empty($productosCarnihub)): ?>
        <div style="margin-bottom:10px">
          <input type="text" id="chBuscar" class="form-input" placeholder="Buscar producto…"
                 oninput="filtrarCh(this.value)" style="font-size:.83rem">
        </div>
        <div style="border:1.5px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:12px">
          <div style="padding:8px 12px;background:#F9FAFB;font-size:.72rem;font-weight:700;color:#9CA3AF;
                      text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #E5E7EB">
            Selecciona un producto
          </div>
          <div style="max-height:220px;overflow-y:auto" id="chListaProductos">
          <?php
          $empresaActual = null;
          foreach ($productosCarnihub as $pc):
            if ($pc['empresa_nombre'] !== $empresaActual):
              $empresaActual = $pc['empresa_nombre'];
          ?>
          <div class="ch-empresa-sep" style="padding:5px 14px;background:#F3F4F6;font-size:.7rem;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.04em;border-bottom:1px solid #E5E7EB">
            <?= htmlspecialchars($empresaActual) ?>
          </div>
          <?php endif; ?>
          <div class="ch-prod-row" style="padding:9px 14px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:.1s"
               data-nombre="<?= htmlspecialchars(strtolower($pc['nombre'])) ?>"
               data-precio="<?= number_format((float)($pc['precio'] ?? 0), 4, '.', '') ?>"
               data-categoria="<?= htmlspecialchars(strtolower($pc['categoria'] ?? '')) ?>"
               onmouseover="this.style.background='#FAF5FF'" onmouseout="this.style.background=''"
               onclick="seleccionarCarniHub(<?= $pc['id'] ?>, '<?= htmlspecialchars($pc['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pc['unidad'] ?? 'kg', ENT_QUOTES) ?>', <?= (float)($pc['precio'] ?? 0) ?>, '<?= htmlspecialchars($pc['categoria'] ?? '', ENT_QUOTES) ?>', this)">
            <div>
              <div style="font-weight:600;font-size:.875rem"><?= htmlspecialchars($pc['nombre']) ?></div>
              <div style="font-size:.73rem;color:#9CA3AF">
                <?= htmlspecialchars($pc['unidad'] ?? '') ?>
                <?php if (!empty($pc['categoria'])): ?> · <?= htmlspecialchars($pc['categoria']) ?><?php endif; ?>
                <?php if ((float)($pc['precio'] ?? 0) > 0): ?>
                  · $<?= number_format((float)$pc['precio'], 2) ?>
                <?php endif; ?>
              </div>
            </div>
            <span class="badge badge-purple" style="font-size:.68rem;white-space:nowrap">CarniHub</span>
          </div>
          <?php endforeach; ?>
          </div>
        </div>
        <?php else: ?>
        <div class="empty-state" style="padding:24px">
          <div style="font-size:.85rem">No hay productos registrados en CarniHub aún. Agrega productos en el panel de empresa primero.</div>
        </div>
        <?php endif; ?>
        <div class="form-group" id="chNombreWrap" style="display:none">
          <label class="form-label">Producto seleccionado</label>
          <input type="text" id="ingNombreCh" class="form-input" readonly
                 style="background:#FAF5FF;color:#5B21B6;font-weight:600" placeholder="Selecciona un producto de arriba">
        </div>
        <div class="form-group">
          <label class="form-label">Stock mínimo (alerta)</label>
          <input type="number" name="stock_minimo_ch" id="ingMinimoCh" class="form-input"
                 value="0" min="0" step="0.001">
        </div>
      </div>

      <div class="rst-modal-footer">
        <button type="button" onclick="rstModal('modalIng')" class="btn btn-outline">Cancelar</button>
        <button type="submit" class="btn btn-primary">Guardar ingrediente</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal Modificar ingrediente (movimiento + editar) -->
<div id="modalModificar" class="rst-modal-backdrop">
  <div class="rst-modal" style="max-width:530px">
    <div class="rst-modal-header">
      <div style="flex:1;min-width:0">
        <div class="rst-modal-title" id="modifNombre">Ingrediente</div>
        <div style="font-size:.78rem;color:#6B7280;margin-top:2px">
          Stock: <strong id="modifStockActual">0</strong> <span id="modifUnidadPrincipal">kg</span>
          &nbsp;·&nbsp; $<span id="modifCostoU">0.00</span>/<span id="modifUnidadCosto">kg</span>
        </div>
      </div>
      <button class="rst-modal-close" onclick="rstModal('modalModificar')">✕</button>
    </div>

    <div class="rst-tabs" id="modifTabs">
      <button class="rst-tab active" onclick="switchModifTab('mov')">Movimiento de stock</button>
      <button class="rst-tab" onclick="switchModifTab('edit')">Editar datos</button>
    </div>

    <!-- Tab: Movimiento -->
    <div class="rst-tab-panel active" id="panelModifMov">
      <form method="POST" action="<?= BASE_URL ?>rest-inventario/movimiento" id="formModifMov"
            onsubmit="return prepararMovimiento()">
        <input type="hidden" name="ingrediente_id" id="modifIngId">
        <input type="hidden" name="cantidad" id="modifCantFinal">

        <div class="form-group">
          <label class="form-label">Tipo de movimiento</label>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:6px">
            <?php
            $tiposM = [
              ['val'=>'entrada','label'=>'Entrada', 'cls'=>'badge-green', 'desc'=>'Suma al stock'],
              ['val'=>'salida', 'label'=>'Salida',  'cls'=>'badge-red',   'desc'=>'Resta del stock'],
              ['val'=>'merma',  'label'=>'Merma',   'cls'=>'badge-amber', 'desc'=>'Pérdida/daño'],
              ['val'=>'ajuste', 'label'=>'Ajuste',  'cls'=>'badge-blue',  'desc'=>'Corrección manual'],
            ];
            foreach ($tiposM as $t):
            ?>
            <label style="display:flex;align-items:center;gap:8px;padding:9px 12px;border:2px solid #E5E7EB;
                          border-radius:8px;cursor:pointer;transition:.15s" class="mtipo-lbl">
              <input type="radio" name="tipo" value="<?= $t['val'] ?>" style="display:none" class="mtipo-radio"
                     onchange="calcModifConversion()">
              <span class="badge <?= $t['cls'] ?>"><?= $t['label'] ?></span>
              <span style="font-size:.78rem;color:#6B7280"><?= $t['desc'] ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Cantidad</label>
          <div style="display:flex;gap:8px;align-items:center">
            <input type="number" id="modifCantInput" class="form-input" style="flex:2"
                   step="0.001" min="0.001" placeholder="0.000"
                   oninput="calcModifConversion()">
            <select id="modifCantUnidad" class="form-select" style="flex:0 0 80px"
                    onchange="calcModifConversion()">
            </select>
          </div>
          <div id="modifConvPrev" style="display:none;margin-top:7px;padding:8px 12px;
               border-radius:8px;font-size:.8rem;line-height:1.6"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Motivo <span style="color:#9CA3AF;font-weight:400">(opcional)</span></label>
          <input type="text" name="motivo" class="form-input"
                 placeholder="Ej: Compra del día, Desperdicio, Inventario físico">
        </div>

        <div class="rst-modal-footer">
          <button type="button" onclick="rstModal('modalModificar')" class="btn btn-outline">Cancelar</button>
          <button type="submit" class="btn btn-primary">Registrar movimiento</button>
        </div>
      </form>
    </div>

    <!-- Tab: Editar datos -->
    <div class="rst-tab-panel" id="panelModifEdit">
      <form method="POST" action="<?= BASE_URL ?>rest-inventario/guardar" id="formModifEdit">
        <input type="hidden" name="id" id="modifEditId">
        <input type="hidden" name="proveedor_carnihub" id="modifEditCarnihub" value="0">
        <input type="hidden" name="carnihub_producto_id" id="modifEditCarnihubId" value="">

        <!-- Banner CarniHub (se muestra cuando el ingrediente está vinculado a CarniHub) -->
        <div id="modifChBanner" style="display:none;background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:.82rem;color:#1E3A5F">
          Este ingrediente está vinculado a CarniHub. Los datos se sincronizan automáticamente y no se pueden editar manualmente.
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
          <div class="form-group" style="grid-column:span 2;margin-bottom:0">
            <label class="form-label">Nombre *</label>
            <input type="text" name="nombre" id="modifEditNombre" class="form-input" required>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Categoría</label>
            <input type="text" name="categoria" id="modifEditCategoria" class="form-input" list="dlCatIng">
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Unidad de medida</label>
            <select name="unidad_principal" id="modifEditUnidad" class="form-select"
                    onchange="document.getElementById('modifEditUnidadLabel').textContent=this.value;
                              document.getElementById('modifEditUnidadWarn').style.display='block'">
              <option value="paquete">paquete</option>
              <option value="pza">pza — pieza</option>
              <option value="porción">porción</option>
              <option value="caja">caja</option>
              <option value="bolsa">bolsa</option>
            </select>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Costo/<span id="modifEditUnidadLabel">kg</span></label>
            <div style="display:flex;align-items:center;gap:6px">
              <span style="color:#6B7280;font-weight:600">$</span>
              <input type="number" name="costo_unitario" id="modifEditCosto" class="form-input"
                     min="0" step="0.0001" placeholder="0.00">
            </div>
          </div>
          <div class="form-group" style="margin-bottom:0">
            <label class="form-label">Stock mínimo (alerta)</label>
            <input type="number" name="stock_minimo" id="modifEditMinimo" class="form-input"
                   min="0" step="0.001" placeholder="0.000">
          </div>
        </div>

        <!-- Sección proveedor con toggle Externo / CarniHub -->
        <div style="border:1.5px solid #E5E7EB;border-radius:10px;overflow:hidden;margin-bottom:12px">
          <div style="padding:7px 12px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;
                      display:flex;align-items:center;gap:8px">
            <span style="font-size:.78rem;font-weight:700;color:#374151">Proveedor</span>
            <div style="display:flex;gap:4px;margin-left:auto">
              <button type="button" id="modifProvBtnExt" onclick="switchModifProv('ext')"
                      style="padding:3px 10px;font-size:.72rem;font-weight:600;border-radius:6px;
                             border:1.5px solid var(--cp);background:var(--cp);color:#fff;cursor:pointer;transition:.15s">
                Externo
              </button>
              <button type="button" id="modifProvBtnCh" onclick="switchModifProv('ch')"
                      style="padding:3px 10px;font-size:.72rem;font-weight:600;border-radius:6px;
                             border:1.5px solid #E5E7EB;background:#fff;color:#6B7280;cursor:pointer;transition:.15s">
                ⚡ CarniHub
              </button>
            </div>
          </div>

          <!-- Panel proveedor externo -->
          <div id="modifEditProvPanelExt" style="padding:10px 12px">
            <input type="text" name="proveedor_nombre" id="modifEditProveedor" class="form-input"
                   placeholder="Ej: Mercado, Walmart, Don José">
          </div>

          <!-- Panel proveedor CarniHub -->
          <div id="modifEditProvPanelCh" style="display:none;padding:10px 12px">
            <?php if (!empty($productosCarnihub)): ?>
            <input type="text" id="modifChBuscar" class="form-input"
                   placeholder="Buscar producto CarniHub…"
                   oninput="filtrarChEdit(this.value)"
                   style="font-size:.83rem;margin-bottom:8px">
            <div style="border:1.5px solid #E5E7EB;border-radius:8px;overflow:hidden">
              <div style="max-height:170px;overflow-y:auto" id="modifChListaProductos">
              <?php foreach ($productosCarnihub as $pc): ?>
              <div class="modif-ch-prod-row"
                   data-id="<?= $pc['id'] ?>"
                   data-nombre="<?= htmlspecialchars(strtolower($pc['nombre']), ENT_QUOTES) ?>"
                   data-display="<?= htmlspecialchars($pc['nombre'], ENT_QUOTES) ?>"
                   data-precio="<?= number_format((float)($pc['precio'] ?? 0), 4, '.', '') ?>"
                   data-categoria="<?= htmlspecialchars(strtolower($pc['categoria'] ?? ''), ENT_QUOTES) ?>"
                   style="padding:8px 12px;border-bottom:1px solid #F3F4F6;display:flex;
                          justify-content:space-between;align-items:center;cursor:pointer;transition:.1s"
                   onmouseover="if(!this.classList.contains('ch-sel'))this.style.background='#FAF5FF'"
                   onmouseout="if(!this.classList.contains('ch-sel'))this.style.background=''"
                   onclick="seleccionarCarniHubEdit(<?= $pc['id'] ?>, '<?= htmlspecialchars($pc['nombre'], ENT_QUOTES) ?>', '<?= htmlspecialchars($pc['unidad'] ?? 'kg', ENT_QUOTES) ?>', <?= (float)($pc['precio'] ?? 0) ?>, '<?= htmlspecialchars($pc['categoria'] ?? '', ENT_QUOTES) ?>', this)">
                <div>
                  <div style="font-weight:600;font-size:.85rem"><?= htmlspecialchars($pc['nombre']) ?></div>
                  <div style="font-size:.72rem;color:#9CA3AF">
                    <?= htmlspecialchars($pc['unidad'] ?? '') ?>
                    <?php if (!empty($pc['categoria'])): ?> · <?= htmlspecialchars($pc['categoria']) ?><?php endif; ?>
                    <?php if ((float)($pc['precio'] ?? 0) > 0): ?>
                      · $<?= number_format((float)$pc['precio'], 2) ?>
                    <?php endif; ?>
                  </div>
                </div>
                <span class="badge badge-purple" style="font-size:.68rem">CarniHub</span>
              </div>
              <?php endforeach; ?>
              </div>
            </div>
            <div id="modifChSelWrap" style="display:none;margin-top:8px;padding:6px 10px;
                 background:#FAF5FF;border:1px solid #DDD6FE;border-radius:8px;
                 font-size:.8rem;color:#5B21B6;font-weight:600">
              ✓ <span id="modifChSelNombre">—</span>
            </div>
            <div id="modifChNoMatch" style="display:none;margin-top:8px;padding:8px 10px;
                 background:#FEF3C7;border:1px solid #FDE68A;border-radius:8px;
                 font-size:.8rem;color:#92400E;font-weight:600">
              ⚠️ No hay coincidencia exacta para este ingrediente en CarniHub. Búscalo y selecciónalo manualmente.
            </div>
            <?php else: ?>
            <div style="font-size:.83rem;color:#9CA3AF;text-align:center;padding:12px 0">
              No hay productos CarniHub disponibles.
            </div>
            <?php endif; ?>
          </div>
        </div>

        <div id="modifEditUnidadWarn" style="display:none;background:#FEF3C7;border:1px solid #FDE68A;
             border-radius:8px;padding:8px 12px;font-size:.75rem;color:#92400E;margin-bottom:12px">
          ⚠️ Cambiar la unidad no convierte el stock existente.
        </div>

        <div class="rst-modal-footer">
          <button type="button" onclick="rstModal('modalModificar')" class="btn btn-outline">Cancelar</button>
          <button type="submit" id="modifEditSubmitBtn" class="btn btn-primary">Guardar cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function rstModal(id) {
  document.getElementById(id).classList.toggle('open');
}
document.querySelectorAll('.rst-modal-backdrop').forEach(bd => {
  bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});

function toggleGuia() {
  const g = document.getElementById('guia-inv');
  g.style.display = g.style.display === 'none' ? 'block' : 'none';
}

function filtrarIngredientes() {
  const q = document.getElementById('invBuscar').value.toLowerCase().trim();
  document.querySelectorAll('.inv-card[data-search]').forEach(card => {
    card.style.display = !q || card.dataset.search.includes(q) ? '' : 'none';
  });
  const vis = [...document.querySelectorAll('.inv-card[data-search]')].filter(c => c.style.display !== 'none').length;
  const nm = document.getElementById('noMatch');
  if (nm) nm.style.display = (vis === 0 && q) ? 'block' : 'none';
}

let tabActual = 'ext';
function switchTab(tab) {
  tabActual = tab;
  document.querySelectorAll('.rst-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  document.querySelectorAll('.rst-tab-panel').forEach(p => p.classList.toggle('active', p.id === 'panel' + tab.charAt(0).toUpperCase() + tab.slice(1)));
  document.getElementById('ingEsCarniHub').value = tab === 'ch' ? '1' : '0';
  document.getElementById('ingNombre').required = tab !== 'ch';
}

async function syncPrecioCarniHub(productId, modo) {
  const idNum = parseInt(productId, 10);
  if (!idNum) return;

  const BASE = '<?= BASE_URL ?>';
  try {
    const res = await fetch(BASE + 'rest-inventario/precioProductoCarnihub/' + idNum + '?t=' + Date.now(), {
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    if (!res.ok) return;

    const data = await res.json();
    if (!data || !data.ok) return;

    const precio = parseFloat(data.precio || 0);
    const unidad = (data.unidad || '').trim();

    if (modo === 'alta') {
      if (unidad) {
        const selUnidad = document.getElementById('ingUnidad');
        if (selUnidad) {
          let opt = [...selUnidad.options].find(o => o.value === unidad);
          if (!opt) { opt = new Option(unidad, unidad); selUnidad.appendChild(opt); }
          selUnidad.value = unidad;
        }
      }
      if (precio > 0) {
        const costo = document.getElementById('ingCosto');
        if (costo) {
          costo.value = precio.toFixed(4);
          calcCostos();
        }
      }
    }

    if (modo === 'edit') {
      if (data.nombre) {
        const elNombre = document.getElementById('modifEditNombre');
        if (elNombre) elNombre.value = data.nombre;
      }
      if (unidad) {
        const u = document.getElementById('modifEditUnidad');
        if (u) {
          let opt = [...u.options].find(o => o.value === unidad);
          if (!opt) { opt = new Option(unidad, unidad); u.appendChild(opt); }
          u.value = unidad;
          const lbl = document.getElementById('modifEditUnidadLabel');
          if (lbl) lbl.textContent = unidad;
        }
      }
      if (precio > 0) {
        const costoEdit = document.getElementById('modifEditCosto');
        if (costoEdit) costoEdit.value = precio.toFixed(4);
      }
    }
  } catch (e) {
    // fallback silencioso al precio local ya cargado en la fila
  }
}

function seleccionarCarniHub(id, nombre, unidad, precio, categoria = '', rowEl = null) {
  document.getElementById('ingCarniHubId').value = id;
  document.getElementById('ingNombreCh').value   = nombre;
  // Propagar al campo nombre del panel externo para que llegue al POST
  document.getElementById('ingNombre').value     = nombre;
  // Auto-rellenar categoría del producto CarniHub
  const catInput = document.getElementById('ingCategoria');
  if (catInput && categoria) catInput.value = categoria;
  // Propagar unidad del producto CarniHub al select de unidad
  const selUnidad = document.getElementById('ingUnidad');
  if (selUnidad && unidad) {
    let opt = [...selUnidad.options].find(o => o.value === unidad);
    if (!opt) { opt = new Option(unidad, unidad); selUnidad.appendChild(opt); }
    selUnidad.value = unidad;
  }
  const costoInput = document.getElementById('ingCosto');
  const precioNum = parseFloat(precio || 0);
  if (costoInput && precioNum > 0) {
    costoInput.value = precioNum.toFixed(4);
    calcCostos();
  }
  document.getElementById('chNombreWrap').style.display = 'block';
  document.querySelectorAll('.ch-prod-row').forEach(r => r.style.background = '');
  const row = rowEl || document.querySelector('.ch-prod-row[onclick*="seleccionarCarniHub(' + id + '"]');
  if (row) row.style.background = 'var(--cp-light, #FAF5FF)';
  syncPrecioCarniHub(id, 'alta');
}

function filtrarCh(q) {
  q = q.toLowerCase().trim();
  let empresaVis = {};
  document.querySelectorAll('.ch-prod-row').forEach(row => {
    const match = !q || row.dataset.nombre.includes(q) || (row.dataset.categoria || '').includes(q);
    row.style.display = match ? '' : 'none';
    if (match) {
      const sep = row.previousElementSibling;
      if (sep && sep.classList.contains('ch-empresa-sep')) empresaVis[sep.textContent.trim()] = sep;
    }
  });
  document.querySelectorAll('.ch-empresa-sep').forEach(sep => {
    const hasVisible = [...document.querySelectorAll('.ch-prod-row')].some(r =>
      r.style.display !== 'none' &&
      r.previousElementSibling === sep
    );
    sep.style.display = hasVisible ? '' : 'none';
  });
}

function resetIngForm() {
  document.getElementById('ingId').value = '';
  document.getElementById('ingNombre').value = '';
  document.getElementById('ingCategoria').value = '';
  document.getElementById('ingCosto').value = '0';
  document.getElementById('ingMinimo').value = '0';
  document.getElementById('ingProveedor').value = '';
  document.getElementById('modalIngTitle').textContent = 'Nuevo Ingrediente';
  document.getElementById('modalIngSub').textContent = 'Proveedor externo';
  switchTab('ext');
  calcCostos();
}

function calcCostos() {
  const costo  = parseFloat(document.getElementById('ingCosto').value) || 0;
  const unidad = document.getElementById('ingUnidad').value;
  document.getElementById('unidadLabel').textContent = unidad;
  document.getElementById('unidadMinLabel').textContent = unidad;
  let items = [];
  if (costo > 0) {
    items = [`Por ${unidad}: <strong>$${costo.toFixed(2)}</strong>`];
  }
  document.getElementById('calcCostos').innerHTML = items.map(i => `<span>${i}</span>`).join('');
  document.getElementById('calcCostosWrap').style.display = items.length ? 'block' : 'none';
}

// ── Modal Modificar ──────────────────────────────────────────
let modifIng = null;
let modifIngNombreActual = '';

function abrirModificar(ing) {
  modifIng = ing;
  document.getElementById('modifNombre').textContent = ing.nombre;
  document.getElementById('modifStockActual').textContent = parseFloat(ing.stock||0).toFixed(3);
  document.getElementById('modifUnidadPrincipal').textContent = ing.unidad_principal;
  document.getElementById('modifCostoU').textContent = parseFloat(ing.costo_unitario||0).toFixed(2);
  document.getElementById('modifUnidadCosto').textContent = ing.unidad_principal;

  // Tab movimiento
  document.getElementById('modifIngId').value = ing.id;
  document.getElementById('modifCantInput').value = '';
  document.getElementById('modifCantFinal').value = '';
  document.getElementById('modifConvPrev').style.display = 'none';
  setupModifUnidades(ing.unidad_principal);
  const firstMtipo = document.querySelector('.mtipo-lbl');
  if (firstMtipo) firstMtipo.click();

  // Tab editar
  document.getElementById('modifEditId').value = ing.id;
  document.getElementById('modifEditCarnihub').value = ing.proveedor_carnihub ? '1' : '0';
  document.getElementById('modifEditCarnihubId').value = ing.carnihub_producto_id || '';
  document.getElementById('modifEditNombre').value = ing.nombre;
  document.getElementById('modifEditCategoria').value = ing.categoria || '';
  const uSel = document.getElementById('modifEditUnidad');
  let found = false;
  for (let o of uSel.options) {
    if (o.value === ing.unidad_principal) { o.selected = true; found = true; break; }
  }
  if (!found) { uSel.add(new Option(ing.unidad_principal, ing.unidad_principal, true, true)); }
  document.getElementById('modifEditUnidadLabel').textContent = ing.unidad_principal;
  document.getElementById('modifEditCosto').value = ing.costo_unitario || 0;
  document.getElementById('modifEditMinimo').value = ing.stock_minimo || 0;
  document.getElementById('modifEditProveedor').value = ing.proveedor_nombre || '';

  // Toggle proveedor: inicializa panel según tipo actual
  switchModifProv(ing.proveedor_carnihub ? 'ch' : 'ext');
  if (ing.proveedor_carnihub && ing.carnihub_producto_id) {
    const row = document.querySelector(`.modif-ch-prod-row[data-id="${ing.carnihub_producto_id}"]`);
    if (row) {
      document.querySelectorAll('.modif-ch-prod-row').forEach(r => { r.style.background=''; r.classList.remove('ch-sel'); });
      row.style.background = 'var(--cp-light, #FAF5FF)';
      row.classList.add('ch-sel');
      row.scrollIntoView({ block: 'nearest' });
      const nombre = row.querySelector('div > div:first-child')?.textContent.trim() || '';
      document.getElementById('modifChSelNombre').textContent = nombre;
      document.getElementById('modifChSelWrap').style.display = 'block';
      const precioFila = parseFloat(row.dataset.precio || '0');
      if (precioFila > 0) {
        document.getElementById('modifEditCosto').value = precioFila.toFixed(4);
        const costoCab = document.getElementById('modifCostoU');
        if (costoCab) costoCab.textContent = precioFila.toFixed(2);
      }
      // Rellenar categoría desde el producto CarniHub si el ingrediente no la tiene guardada
      const catFila = row.dataset.categoria || '';
      const catEditEl = document.getElementById('modifEditCategoria');
      if (catEditEl && catFila && !catEditEl.value) catEditEl.value = catFila;
    }
    syncPrecioCarniHub(ing.carnihub_producto_id, 'edit');
  } else if (!ing.carnihub_producto_id) {
    // Auto-detectar coincidencia en CarniHub por nombre del ingrediente
    // (siempre, aunque el proveedor actual sea externo: queda listo si toggle a CH)
    autoDetectarCarniHub(ing.nombre);
  }
  // Guardar nombre para re-aplicar al toggle a CH
  modifIngNombreActual = ing.nombre || '';
  document.getElementById('modifEditUnidadWarn').style.display = 'none';

  switchModifTab('mov');
  document.getElementById('modalModificar').classList.add('open');
}

function switchModifTab(tab) {
  document.querySelectorAll('#modifTabs .rst-tab').forEach((t, i) => {
    t.classList.toggle('active', (tab==='mov' && i===0) || (tab==='edit' && i===1));
  });
  document.getElementById('panelModifMov').classList.toggle('active', tab === 'mov');
  document.getElementById('panelModifEdit').classList.toggle('active', tab === 'edit');
}

function switchModifProv(tipo) {
  const isExt = tipo === 'ext';
  const yaTeniaCh = document.getElementById('modifEditCarnihub').value === '1';
  document.getElementById('modifEditCarnihub').value = isExt ? '0' : '1';
  aplicarBloqueoCarniHub(!isExt);
  document.getElementById('modifEditProvPanelExt').style.display = isExt ? '' : 'none';
  document.getElementById('modifEditProvPanelCh').style.display  = isExt ? 'none' : '';
  const btnExt = document.getElementById('modifProvBtnExt');
  const btnCh  = document.getElementById('modifProvBtnCh');
  if (btnExt) {
    btnExt.style.background  = isExt ? 'var(--cp)' : '#fff';
    btnExt.style.color       = isExt ? '#fff' : '#6B7280';
    btnExt.style.borderColor = isExt ? 'var(--cp)' : '#E5E7EB';
  }
  if (btnCh) {
    btnCh.style.background  = !isExt ? '#5B21B6' : '#fff';
    btnCh.style.color       = !isExt ? '#fff' : '#6B7280';
    btnCh.style.borderColor = !isExt ? '#5B21B6' : '#E5E7EB';
  }
  if (isExt) {
    document.getElementById('modifEditCarnihubId').value = '';
    const selWrap = document.getElementById('modifChSelWrap');
    if (selWrap) selWrap.style.display = 'none';
    const noMatchBox = document.getElementById('modifChNoMatch');
    if (noMatchBox) noMatchBox.style.display = 'none';
    document.querySelectorAll('.modif-ch-prod-row').forEach(r => { r.style.background=''; r.classList.remove('ch-sel'); });
    const buscar = document.getElementById('modifChBuscar');
    if (buscar) { buscar.value = ''; filtrarChEdit(''); }
  } else if (!yaTeniaCh && !document.getElementById('modifEditCarnihubId').value && modifIngNombreActual) {
    // Usuario está cambiando a CarniHub manualmente y aún no hay producto seleccionado
    autoDetectarCarniHub(modifIngNombreActual);
  } else if (!isExt) {
    // Ya había selección previa: hacer scroll al producto resaltado
    const sel = document.querySelector('.modif-ch-prod-row.ch-sel');
    if (sel) setTimeout(() => {
      try { sel.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch(e){}
    }, 80);
  }
}

function seleccionarCarniHubEdit(id, nombre, unidad, precio, categoria = '', rowEl = null) {
  document.getElementById('modifEditCarnihubId').value = id;
  document.querySelectorAll('.modif-ch-prod-row').forEach(r => { r.style.background=''; r.classList.remove('ch-sel'); });
  const row = rowEl || document.querySelector(`.modif-ch-prod-row[data-id="${id}"]`);
  if (row) { row.style.background = 'var(--cp-light, #FAF5FF)'; row.classList.add('ch-sel'); }
  document.getElementById('modifChSelNombre').textContent = nombre;
  document.getElementById('modifChSelWrap').style.display = 'block';
  const costo = parseFloat(precio || 0);
  if (costo > 0) {
    document.getElementById('modifEditCosto').value = costo.toFixed(4);
  }
  // Auto-rellenar unidad de medida del producto CarniHub
  const selUnidad = document.getElementById('modifEditUnidad');
  if (selUnidad && unidad) {
    let opt = [...selUnidad.options].find(o => o.value === unidad);
    if (!opt) { opt = new Option(unidad, unidad); selUnidad.appendChild(opt); }
    selUnidad.value = unidad;
    const lbl = document.getElementById('modifEditUnidadLabel');
    if (lbl) lbl.textContent = unidad;
  }
  // Auto-rellenar categoría del producto CarniHub
  const catEdit = document.getElementById('modifEditCategoria');
  if (catEdit && categoria) catEdit.value = categoria;
  const noMatchBox = document.getElementById('modifChNoMatch');
  if (noMatchBox) noMatchBox.style.display = 'none';
  syncPrecioCarniHub(id, 'edit');
}

function filtrarChEdit(q) {
  q = (q || '').toLowerCase().trim();
  document.querySelectorAll('.modif-ch-prod-row').forEach(row => {
    const match = !q || row.dataset.nombre.includes(q) || (row.dataset.categoria || '').includes(q);
    row.style.display = match ? '' : 'none';
  });
}

function aplicarBloqueoCarniHub(bloquear) {
  const banner   = document.getElementById('modifChBanner');
  const elNombre = document.getElementById('modifEditNombre');
  const elCosto  = document.getElementById('modifEditCosto');
  const elUnidad = document.getElementById('modifEditUnidad');
  const btn      = document.getElementById('modifEditSubmitBtn');
  const bloqStyle = { background: '#F3F4F6', cursor: 'not-allowed', color: '#6B7280' };

  if (bloquear) {
    if (banner)   banner.style.display = '';
    if (elNombre) { elNombre.readOnly = true; Object.assign(elNombre.style, bloqStyle); }
    if (elCosto)  { elCosto.readOnly  = true; Object.assign(elCosto.style,  bloqStyle); }
    if (elUnidad) { elUnidad.disabled = true; Object.assign(elUnidad.style, bloqStyle); }
    if (btn) { btn.textContent = 'Sincronizado por CarniHub'; btn.style.background = '#5B21B6'; btn.style.borderColor = '#5B21B6'; }
  } else {
    if (banner)   banner.style.display = 'none';
    if (elNombre) { elNombre.readOnly = false; elNombre.style.background = ''; elNombre.style.cursor = ''; elNombre.style.color = ''; }
    if (elCosto)  { elCosto.readOnly  = false; elCosto.style.background  = ''; elCosto.style.cursor  = ''; elCosto.style.color  = ''; }
    if (elUnidad) { elUnidad.disabled = false; elUnidad.style.background = ''; elUnidad.style.cursor = ''; elUnidad.style.color = ''; }
    if (btn) { btn.textContent = 'Guardar cambios'; btn.style.background = ''; btn.style.borderColor = ''; }
  }
}

function normalizarNombre(str) {
  return (str || '').toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '') // quitar acentos
    .replace(/[^a-z0-9\s]/g, ' ')
    .replace(/\s+/g, ' ').trim();
}

// Genera n-gramas (sub-cadenas) de tamaño n a partir de un string
function _ngramas(s, n) {
  const out = new Set();
  for (let i = 0; i + n <= s.length; i++) out.add(s.substr(i, n));
  return out;
}

// Auto-detección con MATCH EXACTO únicamente.
// Solo selecciona un producto de CarniHub si su nombre normalizado
// (sin acentos, sin signos, en minúsculas) coincide EXACTAMENTE con
// el nombre normalizado del ingrediente. Evita falsos positivos por
// similitud (p.ej. "Tamales Oaxaqueños" ≠ "Mole Oaxaqueño").
function autoDetectarCarniHub(nombreIng) {
  const rows = document.querySelectorAll('.modif-ch-prod-row');
  const noMatchBox = document.getElementById('modifChNoMatch');
  const selWrap    = document.getElementById('modifChSelWrap');

  const needle = normalizarNombre(nombreIng);
  if (!needle) return;

  // Si NO hay productos cargados en el panel CarniHub, igual mostramos
  // el aviso amarillo para que el usuario sepa que no hay coincidencia.
  if (!rows.length) {
    if (selWrap) selWrap.style.display = 'none';
    if (noMatchBox) noMatchBox.style.display = 'block';
    return;
  }

  let exactRow = null;
  rows.forEach(row => {
    if (normalizarNombre(row.dataset.nombre) === needle) exactRow = row;
  });

  // Limpiar selección/avisos previos
  document.querySelectorAll('.modif-ch-prod-row').forEach(r => { r.style.background=''; r.classList.remove('ch-sel'); });
  if (noMatchBox) noMatchBox.style.display = 'none';

  if (exactRow) {
    const id = exactRow.dataset.id;
    const nombre = exactRow.dataset.display || exactRow.dataset.nombre || '';
    const precio = parseFloat(exactRow.dataset.precio || '0');
    exactRow.style.background = 'var(--cp-light, #FAF5FF)';
    exactRow.classList.add('ch-sel');
    document.getElementById('modifEditCarnihubId').value = id;
    document.getElementById('modifChSelNombre').textContent = nombre;
    if (precio > 0) {
      document.getElementById('modifEditCosto').value = precio.toFixed(4);
    }
    if (selWrap) selWrap.style.display = 'block';
    const buscar = document.getElementById('modifChBuscar');
    if (buscar) { buscar.value = ''; filtrarChEdit(''); }
    syncPrecioCarniHub(id, 'edit');
    setTimeout(() => {
      try { exactRow.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch(e){}
    }, 80);
  } else {
    // Sin coincidencia exacta: limpiar selección y mostrar aviso visible.
    document.getElementById('modifEditCarnihubId').value = '';
    if (selWrap) selWrap.style.display = 'none';
    if (noMatchBox) noMatchBox.style.display = 'block';
  }
}

document.querySelectorAll('.mtipo-lbl').forEach(lbl => {
  const radio = lbl.querySelector('.mtipo-radio');
  lbl.addEventListener('click', () => {
    document.querySelectorAll('.mtipo-lbl').forEach(l => l.style.borderColor = '#E5E7EB');
    lbl.style.borderColor = 'var(--cp)';
    radio.checked = true;
    calcModifConversion();
  });
});

function setupModifUnidades(mainUnit) {
  const sel = document.getElementById('modifCantUnidad');
  sel.innerHTML = '';
  const grupos = {
    'kg':['g','kg'],'g':['g','kg','mg'],'mg':['mg','g','kg'],
    'L':['ml','L'],'l':['ml','L'],'ml':['ml','L'],'mL':['ml','L'],
    'pza':['pza'],'caja':['caja'],'bolsa':['bolsa'],
    'paquete':['paquete'],'porción':['porción'],'porcion':['porción'],
  };
  const units = grupos[mainUnit] || [mainUnit];
  units.forEach(u => {
    const opt = new Option(u, u);
    if (u === mainUnit) opt.selected = true;
    sel.appendChild(opt);
  });
}

function convUnidad(q, desde, hasta) {
  const d = desde.toLowerCase();
  const h = hasta.toLowerCase();
  if (d === h) return q;
  const map = {
    'g_kg':1e-3,'kg_g':1e3,'mg_g':1e-3,'g_mg':1e3,'mg_kg':1e-6,'kg_mg':1e6,
    'ml_l':1e-3,'l_ml':1e3,
  };
  return q * (map[d+'_'+h] || 1);
}

function calcModifConversion() {
  if (!modifIng) return;
  const cant = parseFloat(document.getElementById('modifCantInput').value) || 0;
  const fromU = document.getElementById('modifCantUnidad').value;
  const mainU = modifIng.unidad_principal;
  const converted = convUnidad(cant, fromU, mainU);
  document.getElementById('modifCantFinal').value = converted.toFixed(6);

  const tipo = document.querySelector('.mtipo-radio:checked')?.value || 'entrada';
  const resta = ['salida','merma'].includes(tipo);
  const stockActual = parseFloat(modifIng.stock) || 0;
  const stockNuevo = resta ? Math.max(0, stockActual - converted) : stockActual + converted;

  const prev = document.getElementById('modifConvPrev');
  if (cant > 0) {
    let html = '';
    if (fromU.toLowerCase() !== mainU.toLowerCase()) {
      html += `<strong>${cant} ${fromU}</strong> = <strong>${converted.toFixed(4)} ${mainU}</strong><br>`;
    }
    const color = resta ? '#991B1B' : '#166534';
    html += `Stock: ${stockActual.toFixed(3)} → <strong style="color:${color}">${stockNuevo.toFixed(3)} ${mainU}</strong>`;
    prev.innerHTML = html;
    prev.style.background = resta ? '#FEF2F2' : '#F0FDF4';
    prev.style.border = `1px solid ${resta ? '#FECACA' : '#BBF7D0'}`;
    prev.style.color = resta ? '#991B1B' : '#166534';
    prev.style.display = 'block';
  } else {
    prev.style.display = 'none';
  }
}

function prepararMovimiento() {
  const cant = parseFloat(document.getElementById('modifCantInput').value) || 0;
  if (cant <= 0) { alert('Ingresa una cantidad mayor a 0'); return false; }
  if (!document.querySelector('.mtipo-radio:checked')) { alert('Selecciona el tipo de movimiento'); return false; }
  if (!document.getElementById('modifCantFinal').value) {
    const fromU = document.getElementById('modifCantUnidad').value;
    document.getElementById('modifCantFinal').value = convUnidad(cant, fromU, modifIng.unidad_principal).toFixed(6);
  }
  return true;
}

// —— Eliminar ingrediente (soft-delete) ——
function eliminarIngrediente(id, nombre) {
  if (!confirm('¿Eliminar el ingrediente "' + nombre + '"?\nEsta acción lo desactivará del inventario.')) return;
  const BASE = '<?= BASE_URL ?>';
  fetch(BASE + 'rest-inventario/eliminar/' + id, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
  })
  .then(r => r.json())
  .then(data => {
    if (!data.ok) throw new Error('Error del servidor');
    const card = document.getElementById('inv-card-' + id);
    if (card) {
      card.style.transition = 'opacity .25s, transform .25s';
      card.style.opacity = '0';
      card.style.transform = 'scale(.95)';
      setTimeout(() => card.remove(), 260);
    }
  })
  .catch(() => alert('No se pudo eliminar el ingrediente. Intenta de nuevo.'));
}

// —— Polling de stock en tiempo real ——
(function startStockPolling() {
  const BASE = '<?= BASE_URL ?>';

  async function refreshStocks() {
    try {
      const res = await fetch(BASE + 'rest-inventario/stocks?t=' + Date.now(), { credentials: 'same-origin' });
      if (!res.ok) return;
      const items = await res.json();
      items.forEach(function(i) {
        const card = document.getElementById('inv-card-' + i.id);
        if (!card) return;

        const min     = parseFloat(card.dataset.min) || 0;
        const bajo    = i.stock <= min;
        const pct     = min > 0
          ? Math.min(100, Math.round(i.stock / (min * 2) * 100))
          : (i.stock > 0 ? 100 : 0);
        const fillCls = bajo ? 'low' : (pct < 60 ? 'warn' : '');
        const unidad  = card.dataset.unidad || '';

        // Clase del card
        card.classList.toggle('bajo',  bajo);
        card.classList.toggle('ok',   !bajo);

        // Valor de stock
        const sv = document.getElementById('inv-sv-' + i.id);
        if (sv) {
          sv.textContent = i.stock.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' ' + unidad;
          sv.style.color = bajo ? '#EF4444' : '#111827';
        }

        // Barra de progreso
        const bar = document.getElementById('inv-bar-' + i.id);
        if (bar) {
          bar.style.width = pct + '%';
          bar.className = 'inv-bar-fill' + (fillCls ? ' ' + fillCls : '');
        }

        // Badge de estado
        const badge = document.getElementById('inv-badge-' + i.id);
        if (badge) {
          badge.textContent = bajo ? 'Stock bajo' : 'OK';
          badge.className = 'badge ' + (bajo ? 'badge-red' : 'badge-green');
          badge.style.fontSize = '.7rem';
        }

        // Icono de alerta (reaparece/desaparece según nivel)
        const head = card.querySelector('.inv-card-head');
        if (head) {
          const existing = head.querySelector('.inv-alert-icon');
          if (bajo && !existing) {
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('width', '16'); svg.setAttribute('height', '16');
            svg.setAttribute('fill', 'none'); svg.setAttribute('stroke', '#EF4444');
            svg.setAttribute('viewBox', '0 0 24 24'); svg.setAttribute('class', 'inv-alert-icon');
            svg.style.cssText = 'flex-shrink:0;margin-top:2px';
            svg.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>';
            head.appendChild(svg);
          } else if (!bajo && existing) {
            existing.remove();
          }
        }
      });
    } catch(e) { /* silenciar errores de red */ }
  }

  setInterval(refreshStocks, 8000);
}());
</script>

<?php
$content = ob_get_clean();
$activeMenu = 'rest_inventario';
$pageTitle  = 'Inventario';
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
