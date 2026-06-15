<?php
$baseUrl = BASE_URL;
?>

<!-- Flash -->
<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Filtros -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:16px;margin-bottom:20px">
  <form method="GET" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
    <div>
      <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Tipo</label>
      <select name="tipo" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;background:#fff">
        <option value="">Todos</option>
        <option value="entrada" <?= ($filtros['tipo'] ?? '') === 'entrada' ? 'selected' : '' ?>>Entradas</option>
        <option value="salida" <?= ($filtros['tipo'] ?? '') === 'salida' ? 'selected' : '' ?>>Salidas</option>
        <option value="merma" <?= ($filtros['tipo'] ?? '') === 'merma' ? 'selected' : '' ?>>Mermas</option>
        <option value="ajuste" <?= ($filtros['tipo'] ?? '') === 'ajuste' ? 'selected' : '' ?>>Ajustes</option>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Producto</label>
      <select name="producto_id" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;background:#fff">
        <option value="">Todos</option>
        <?php foreach ($productos as $p): ?>
        <option value="<?= $p['id'] ?>" <?= ($filtros['producto_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars($p['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Desde</label>
      <input type="date" name="fecha_desde" value="<?= htmlspecialchars($filtros['fecha_desde'] ?? '') ?>"
             style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem">
    </div>
    <div>
      <label style="display:block;font-size:.75rem;font-weight:600;color:#374151;margin-bottom:4px">Hasta</label>
      <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($filtros['fecha_hasta'] ?? '') ?>"
             style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem">
    </div>
    <button type="submit" style="padding:8px 16px;background:var(--color-primary);color:#fff;border:none;border-radius:6px;font-size:.8rem;cursor:pointer;font-weight:600">Filtrar</button>
    <a href="?" style="padding:8px 14px;border:1px solid #D1D5DB;border-radius:6px;color:#374151;text-decoration:none;font-size:.8rem">Limpiar</a>
  </form>
</div>

<!-- Tabla de log -->
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
  <?php if (empty($items)): ?>
    <div style="padding:48px;text-align:center;color:#9CA3AF">Sin movimientos para los filtros seleccionados.</div>
  <?php else: ?>
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Tipo</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Producto</th>
        <th style="padding:10px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Cantidad</th>
        <th style="padding:10px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Stock final</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Motivo</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Usuario</th>
        <th style="padding:10px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:600;text-transform:uppercase">Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $m): ?>
      <?php
        [$tipoBg, $tipoTx, $tipoLabel, $tipoIcon] = match ($m['tipo']) {
          'entrada' => ['#D1FAE5','#065F46','Entrada','↑'],
          'salida'  => ['#FEE2E2','#991B1B','Salida','↓'],
          'merma'   => ['#FEF3C7','#92400E','Merma','⚠'],
          default   => ['#E0E7FF','#3730A3','Ajuste','⟳'],
        };
      ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:10px 16px">
          <span style="padding:2px 8px;border-radius:999px;background:<?= $tipoBg ?>;color:<?= $tipoTx ?>;font-size:.7rem;font-weight:700">
            <?= $tipoIcon ?> <?= $tipoLabel ?>
          </span>
        </td>
        <td style="padding:10px 16px">
          <div style="font-size:.82rem;font-weight:600;color:#111827"><?= htmlspecialchars($m['producto_nombre']) ?></div>
          <div style="font-size:.7rem;color:#9CA3AF"><?= $m['presentacion'] ?></div>
        </td>
        <td style="padding:10px 16px;text-align:right;font-size:.85rem;font-weight:700;color:<?= $m['tipo'] === 'entrada' ? '#059669' : '#DC2626' ?>">
          <?= $m['tipo'] === 'entrada' ? '+' : '-' ?><?= number_format((float)$m['cantidad'], 1) ?>
        </td>
        <td style="padding:10px 16px;text-align:right;font-size:.85rem;font-weight:600;color:#111827">
          <?= number_format((float)$m['stock_despues'], 1) ?>
        </td>
        <td style="padding:10px 16px;font-size:.78rem;color:#6B7280">
          <?= htmlspecialchars($m['motivo'] ?? '—') ?>
          <?php if ($m['referencia']): ?>
          <div style="font-size:.7rem;color:#9CA3AF"><?= htmlspecialchars($m['referencia']) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding:10px 16px;font-size:.75rem;color:#6B7280"><?= htmlspecialchars($m['usuario_nombre']) ?></td>
        <td style="padding:10px 16px;font-size:.75rem;color:#9CA3AF;white-space:nowrap">
          <?= date('d/m/Y', strtotime($m['created_at'])) ?>
          <div style="font-size:.7rem"><?= date('H:i', strtotime($m['created_at'])) ?></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Paginación -->
  <?php if (($paginacion['total_pages'] ?? 1) > 1): ?>
  <div style="padding:16px;display:flex;justify-content:center;gap:4px;border-top:1px solid #E5E7EB">
    <?php for ($i = 1; $i <= $paginacion['total_pages']; $i++): ?>
      <a href="?<?= http_build_query(array_merge($filtros, ['page' => $i])) ?>"
         style="padding:6px 12px;border-radius:6px;font-size:.8rem;text-decoration:none;<?= $i === ($paginacion['current_page'] ?? 1) ? 'background:var(--color-primary);color:#fff' : 'background:#F3F4F6;color:#374151' ?>">
        <?= $i ?>
      </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>
