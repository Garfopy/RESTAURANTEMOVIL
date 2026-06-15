<?php
$baseUrl = BASE_URL;
$rol     = $_SESSION['usuario']['rol_slug'] ?? '';

$nAgotado = count(array_filter($resumen, fn($r) => $r['estado_stock'] === 'agotado'));
$nCritico = count(array_filter($resumen, fn($r) => $r['estado_stock'] === 'critico'));
$nBajo    = count(array_filter($resumen, fn($r) => $r['estado_stock'] === 'bajo'));
$nOk      = count(array_filter($resumen, fn($r) => $r['estado_stock'] === 'ok'));
?>
<style>
.inv-action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 11px 20px;
  border-radius: 11px;
  text-decoration: none;
  font-weight: 700;
  font-size: .84rem;
  font-family: 'Inter', sans-serif;
  transition: transform .15s, box-shadow .15s, opacity .15s;
  border: none;
  cursor: pointer;
}
.inv-action-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(0,0,0,.15); }
.inv-action-btn:active { transform: translateY(0); }
.inv-stat-card {
  border-radius: 13px;
  padding: 18px 16px;
  display: flex;
  align-items: center;
  gap: 14px;
  transition: transform .15s, box-shadow .15s;
}
.inv-stat-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,.08); }
.inv-stat-icon {
  width: 48px; height: 48px;
  border-radius: 12px;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
}
.inv-table-card {
  background: #fff;
  border-radius: 14px;
  border: 1px solid #E5E7EB;
  overflow: hidden;
  box-shadow: 0 1px 4px rgba(0,0,0,.03);
}
.inv-table { width: 100%; border-collapse: collapse; }
.inv-table thead tr { background: #F9FAFB; }
.inv-table th {
  padding: 11px 16px;
  text-align: left;
  font-size: .65rem;
  color: #6B7280;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  border-bottom: 1px solid #E5E7EB;
  white-space: nowrap;
}
.inv-table th.right { text-align: right; }
.inv-table th.center { text-align: center; }
.inv-table tbody tr {
  border-bottom: 1px solid #F3F4F6;
  transition: background .1s;
}
.inv-table tbody tr:hover { background: #FAFAFA; }
.inv-table td { padding: 10px 16px; }
.inv-table td.right { text-align: right; }
.inv-table td.center { text-align: center; }
.stock-badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 3px 10px; border-radius: 999px;
  font-size: .68rem; font-weight: 700;
}
.btn-sm {
  padding: 5px 11px;
  border-radius: 7px;
  font-size: .72rem;
  font-weight: 600;
  font-family: 'Inter', sans-serif;
  cursor: pointer;
  text-decoration: none;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  transition: background .15s;
  border: 1px solid #E5E7EB;
  background: #F9FAFB;
  color: #374151;
}
.btn-sm:hover { background: #F3F4F6; }
.alert-product-card {
  border-radius: 11px;
  padding: 14px;
  display: flex;
  gap: 12px;
  align-items: center;
  transition: transform .15s;
}
.alert-product-card:hover { transform: translateY(-1px); }
.inv-section-title {
  font-size: .68rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: .08em;
  color: #9CA3AF;
  margin-bottom: 12px;
  display: flex;
  align-items: center;
  gap: 8px;
}
.inv-section-title::after {
  content: '';
  flex: 1;
  height: 1px;
  background: #E5E7EB;
}
</style>

<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:8px;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?php if ($flash['type'] === 'success'): ?>
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <?php else: ?>
    <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <?php endif; ?>
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Acciones rápidas -->
<div class="inv-section-title">Movimientos</div>
<div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap">
  <a href="<?= $baseUrl ?>empresa-inventario/movimiento/entrada" class="inv-action-btn" style="background:linear-gradient(135deg,#059669,#047857);color:#fff">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
    Registrar Entrada
  </a>
  <a href="<?= $baseUrl ?>empresa-inventario/movimiento/salida" class="inv-action-btn" style="background:linear-gradient(135deg,#DC2626,#B91C1C);color:#fff">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4"/></svg>
    Registrar Salida
  </a>
  <a href="<?= $baseUrl ?>empresa-inventario/movimiento/merma" class="inv-action-btn" style="background:linear-gradient(135deg,#D97706,#B45309);color:#fff">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    Registrar Merma
  </a>
  <a href="<?= $baseUrl ?>empresa-inventario/log_movimientos" class="inv-action-btn" style="background:#fff;color:#374151;border:1.5px solid #E5E7EB">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
    Ver Log Completo
  </a>
</div>

<!-- Cards de resumen -->
<div class="inv-section-title">Estado del stock</div>
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px">

  <div class="inv-stat-card" style="background:#FEF2F2;border:1px solid #FECACA">
    <div class="inv-stat-icon" style="background:#FEE2E2">
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#DC2626" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div>
      <div style="font-size:2rem;font-weight:800;color:#DC2626;line-height:1"><?= $nAgotado ?></div>
      <div style="font-size:.76rem;color:#991B1B;font-weight:700;margin-top:3px">Sin Stock</div>
    </div>
  </div>

  <div class="inv-stat-card" style="background:#FFFBEB;border:1px solid #FDE68A">
    <div class="inv-stat-icon" style="background:#FEF3C7">
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#D97706" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
    </div>
    <div>
      <div style="font-size:2rem;font-weight:800;color:#D97706;line-height:1"><?= $nCritico ?></div>
      <div style="font-size:.76rem;color:#92400E;font-weight:700;margin-top:3px">Crítico</div>
    </div>
  </div>

  <div class="inv-stat-card" style="background:#FFF7ED;border:1px solid #FED7AA">
    <div class="inv-stat-icon" style="background:#FFEDD5">
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#EA580C" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/></svg>
    </div>
    <div>
      <div style="font-size:2rem;font-weight:800;color:#EA580C;line-height:1"><?= $nBajo ?></div>
      <div style="font-size:.76rem;color:#7C2D12;font-weight:700;margin-top:3px">Stock Bajo</div>
    </div>
  </div>

  <div class="inv-stat-card" style="background:#F0FDF4;border:1px solid #BBF7D0">
    <div class="inv-stat-icon" style="background:#DCFCE7">
      <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#16A34A" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <div>
      <div style="font-size:2rem;font-weight:800;color:#16A34A;line-height:1"><?= $nOk ?></div>
      <div style="font-size:.76rem;color:#14532D;font-weight:700;margin-top:3px">Normal</div>
    </div>
  </div>

</div>

<!-- Alertas: productos que necesitan atención -->
<?php if ($nAgotado + $nCritico + $nBajo > 0): ?>
<div class="inv-section-title" style="color:#DC2626">
  <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  Productos que necesitan atención
</div>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px;margin-bottom:26px">
  <?php foreach (array_merge(array_values($criticos), array_values($bajos)) as $p): ?>
  <?php
    $isAgotado = $p['estado_stock'] === 'agotado';
    $isCritico = $p['estado_stock'] === 'critico';
    $bgCard    = $isAgotado ? '#FEF2F2' : ($isCritico ? '#FFFBEB' : '#FFF7ED');
    $bdCard    = $isAgotado ? '#FECACA' : ($isCritico ? '#FDE68A' : '#FED7AA');
    $stColor   = $isAgotado ? '#DC2626' : ($isCritico ? '#D97706' : '#EA580C');
    $bdgBg     = $isAgotado ? '#FEE2E2' : ($isCritico ? '#FEF3C7' : '#FFEDD5');
    $bdgTx     = $isAgotado ? '#991B1B' : ($isCritico ? '#92400E' : '#7C2D12');
    $bdgLabel  = $isAgotado ? 'Agotado' : ($isCritico ? 'Crítico' : 'Bajo');
  ?>
  <div class="alert-product-card" style="background:<?= $bgCard ?>;border:1px solid <?= $bdCard ?>">
    <?php if (!empty($p['imagen'])): ?>
      <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="" style="width:46px;height:46px;object-fit:cover;border-radius:9px;flex-shrink:0;border:1px solid <?= $bdCard ?>">
    <?php else: ?>
      <div style="width:46px;height:46px;background:<?= $bdgBg ?>;border-radius:9px;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:1px solid <?= $bdCard ?>">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="<?= $stColor ?>" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
      </div>
    <?php endif; ?>
    <div style="flex:1;min-width:0">
      <div style="font-weight:700;color:#111827;font-size:.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= htmlspecialchars($p['nombre']) ?></div>
      <div style="font-size:.72rem;color:#6B7280;margin-bottom:5px"><?= htmlspecialchars($p['categoria_nombre']) ?></div>
      <div style="display:flex;align-items:center;justify-content:space-between">
        <span style="font-size:1.1rem;font-weight:800;color:<?= $stColor ?>"><?= number_format((float)$p['stock_actual'], 1) ?> <small style="font-size:.68rem;font-weight:500;color:<?= $stColor ?>;opacity:.7"><?= $p['presentacion'] ?></small></span>
        <span class="stock-badge" style="background:<?= $bdgBg ?>;color:<?= $bdgTx ?>">
          <span style="width:5px;height:5px;border-radius:50%;background:<?= $stColor ?>"></span>
          <?= $bdgLabel ?>
        </span>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Tabla completa de stock -->
<div class="inv-section-title">Todos los productos</div>
<div class="inv-table-card" style="margin-bottom:26px">
  <?php if (empty($resumen)): ?>
    <div style="padding:56px;text-align:center">
      <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.2" style="margin:0 auto 10px;display:block"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
      <p style="color:#9CA3AF;font-size:.875rem">Sin productos registrados.</p>
    </div>
  <?php else: ?>
  <table class="inv-table">
    <thead>
      <tr>
        <th>Producto</th>
        <th class="center">Estado</th>
        <th class="right">Stock actual</th>
        <th class="right">Mínimo</th>
        <th class="center">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($resumen as $p): ?>
      <?php
        $s = (float)$p['stock_actual'];
        $u = (float)$p['umbral_minimo'];
        [$bBg, $bTx, $bLabel, $dot] = match ($p['estado_stock']) {
          'agotado' => ['#FEE2E2','#991B1B','Agotado','#DC2626'],
          'critico' => ['#FEF3C7','#92400E','Crítico','#D97706'],
          'bajo'    => ['#FFEDD5','#7C2D12','Bajo','#EA580C'],
          default   => ['#D1FAE5','#065F46','Normal','#059669'],
        };
        $pct = $u > 0 ? min(100, round($s / $u * 100)) : 100;
        $barColor = $dot;
      ?>
      <tr>
        <td>
          <div style="display:flex;align-items:center;gap:10px">
            <?php if (!empty($p['imagen'])): ?>
              <img src="<?= htmlspecialchars($p['imagen']) ?>" alt="" style="width:34px;height:34px;object-fit:cover;border-radius:7px;flex-shrink:0;border:1px solid #F3F4F6">
            <?php else: ?>
              <div style="width:34px;height:34px;background:#F3F4F6;border-radius:7px;flex-shrink:0;display:flex;align-items:center;justify-content:center">
                <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="#D1D5DB" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
              </div>
            <?php endif; ?>
            <div>
              <div style="font-weight:600;font-size:.85rem;color:#111827"><?= htmlspecialchars($p['nombre']) ?></div>
              <div style="font-size:.7rem;color:#9CA3AF"><?= htmlspecialchars($p['categoria_nombre']) ?></div>
            </div>
          </div>
        </td>
        <td class="center">
          <span class="stock-badge" style="background:<?= $bBg ?>;color:<?= $bTx ?>">
            <span style="width:5px;height:5px;border-radius:50%;background:<?= $dot ?>"></span>
            <?= $bLabel ?>
          </span>
        </td>
        <td class="right">
          <div style="font-size:.9rem;font-weight:700;color:#111827"><?= number_format($s, 1) ?> <span style="font-size:.7rem;color:#9CA3AF;font-weight:400"><?= $p['presentacion'] ?></span></div>
          <div style="margin-top:4px;height:4px;background:#F3F4F6;border-radius:2px;width:80px;margin-left:auto;overflow:hidden">
            <div style="height:100%;width:<?= $pct ?>%;background:<?= $barColor ?>;border-radius:2px;transition:width .3s"></div>
          </div>
        </td>
        <td class="right" style="font-size:.82rem;color:#6B7280"><?= number_format($u, 1) ?></td>
        <td class="center">
          <div style="display:flex;justify-content:center;gap:6px">
            <a href="<?= $baseUrl ?>empresa-inventario/historial/<?= $p['id'] ?>" class="btn-sm">
              <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              Historial
            </a>
            <?php if ($rol === 'admin_empresa'): ?>
            <a href="<?= $baseUrl ?>empresa-inventario/ajuste/<?= $p['id'] ?>" class="btn-sm">
              <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
              Ajuste
            </a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php endif; ?>
</div>

<!-- Últimos movimientos -->
<?php if (!empty($ultimos)): ?>
<div class="inv-section-title">Últimos movimientos</div>
<div class="inv-table-card">
  <table class="inv-table">
    <thead>
      <tr>
        <th>Tipo</th>
        <th>Producto</th>
        <th class="right">Cantidad</th>
        <th>Motivo</th>
        <th>Usuario</th>
        <th>Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($ultimos as $m): ?>
      <?php
        [$tipoBg, $tipoTx, $tLabel, $tipoIcon] = match ($m['tipo']) {
          'entrada' => ['#D1FAE5','#065F46','Entrada', '#059669'],
          'salida'  => ['#FEE2E2','#991B1B','Salida',  '#EF4444'],
          'merma'   => ['#FEF3C7','#92400E','Merma',   '#D97706'],
          default   => ['#E0E7FF','#3730A3','Ajuste',  '#6366F1'],
        };
      ?>
      <tr>
        <td>
          <span class="stock-badge" style="background:<?= $tipoBg ?>;color:<?= $tipoTx ?>">
            <span style="width:5px;height:5px;border-radius:50%;background:<?= $tipoIcon ?>"></span>
            <?= $tLabel ?>
          </span>
        </td>
        <td style="font-size:.84rem;color:#111827;font-weight:500"><?= htmlspecialchars($m['producto_nombre']) ?></td>
        <td class="right" style="font-size:.86rem;font-weight:700;color:#111827"><?= number_format((float)$m['cantidad'], 1) ?> <span style="font-size:.7rem;font-weight:400;color:#9CA3AF"><?= $m['presentacion'] ?></span></td>
        <td style="font-size:.78rem;color:#6B7280"><?= htmlspecialchars($m['motivo'] ?? '—') ?></td>
        <td style="font-size:.76rem;color:#6B7280"><?= htmlspecialchars($m['usuario_nombre']) ?></td>
        <td style="font-size:.74rem;color:#9CA3AF"><?= date('d/m H:i', strtotime($m['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <div style="padding:12px 18px;text-align:right;border-top:1px solid #F3F4F6">
    <a href="<?= $baseUrl ?>empresa-inventario/log_movimientos"
       style="display:inline-flex;align-items:center;gap:5px;font-size:.78rem;color:#C8102E;text-decoration:none;font-weight:700">
      Ver todos los movimientos
      <svg width="11" height="11" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
    </a>
  </div>
</div>
<?php endif; ?>
