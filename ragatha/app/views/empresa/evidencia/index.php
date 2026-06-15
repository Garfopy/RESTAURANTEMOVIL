<?php
// Variables: $pedidos (array), $desde, $hasta, $proximos (int), $esComprador, $flash
$retDias = 60;
$hoy     = time();
?>

<!-- Banner de retención -->
<?php if ($proximos > 0): ?>
<div style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:12px;padding:14px 20px;margin-bottom:18px;display:flex;align-items:flex-start;gap:12px">
  <svg width="20" height="20" fill="none" stroke="#B45309" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:2px"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
  <div>
    <div style="font-weight:700;color:#92400E;font-size:.9rem">¡Atención! <?= $proximos ?> pedido<?= $proximos !== 1 ? 's' : '' ?> con archivos próximos a vencer</div>
    <div style="font-size:.82rem;color:#9A3412;margin-top:3px">Las fotos de entrega y firmas se eliminan automáticamente después de <?= $retDias ?> días. Descarga el ZIP antes de que expiren para conservar la evidencia.</div>
  </div>
</div>
<?php else: ?>
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:12px;padding:12px 18px;margin-bottom:18px;display:flex;align-items:center;gap:10px">
  <svg width="17" height="17" fill="none" stroke="#1D4ED8" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <span style="font-size:.83rem;color:#1E40AF">Las fotos y firmas de entrega tienen <strong>vigencia de <?= $retDias ?> días</strong>. Exporta el ZIP para conservar el respaldo antes de que se eliminen.</span>
</div>
<?php endif; ?>

<!-- Guía paso a paso -->
<details style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden;margin-bottom:18px" open>
  <summary style="padding:14px 18px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;font-size:.88rem;font-weight:700;color:#111827;cursor:pointer;user-select:none;list-style:none;display:flex;justify-content:space-between;align-items:center">
    <span>¿Cómo funciona? — Guía rápida</span>
    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 9l-7 7-7-7"/></svg>
  </summary>
  <div style="padding:16px 20px">
    <ol style="margin:0;padding-left:20px;font-size:.84rem;color:#374151;line-height:2.1">
      <li>Usa los filtros de fecha para encontrar el pedido con la entrega que quieres archivar.</li>
      <li>Identifica el pedido en la tabla: el ícono <strong>📷</strong> indica que hay fotos de entrega, y <strong>GPS</strong> que tiene mapa de ruta.</li>
      <li>Haz clic en <strong>"↓ Exportar ZIP"</strong> del pedido correspondiente.</li>
      <li>El ZIP se descarga con:<br>
        &nbsp;&nbsp;• <code>reporte_CHB-XXX.html</code> — recibo completo con fotos en grande y mapa de ruta interactivo<br>
        &nbsp;&nbsp;• <code>fotos/</code> — archivos de imagen originales<br>
        &nbsp;&nbsp;• <code>firmas/</code> — firmas digitales en PNG
      </li>
      <li>Abre el archivo <code>reporte_CHB-XXX.html</code> en tu navegador y usa <strong>Ctrl+P → Guardar como PDF</strong> para convertirlo a PDF imprimible.</li>
    </ol>

    <details style="margin-top:14px;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden">
      <summary style="padding:8px 14px;background:#F9FAFB;font-size:.8rem;font-weight:600;color:#374151;cursor:pointer">¿Qué incluye el reporte HTML?</summary>
      <div style="padding:12px 16px;font-size:.82rem;color:#374151;line-height:1.8">
        <strong>Sección 1 — Detalle del pedido:</strong> Mismos datos del recibo comercial (empresa, folio, comprador, productos, montos).<br>
        <strong>Sección 2 — Evidencias fotográficas:</strong> Fotos de entrega por sucursal en tamaño completo, con firma digital y nombre del receptor.<br>
        <strong>Sección 3 — Mapa de la ruta:</strong> Recorrido GPS completo trazado sobre el mapa (requiere internet al abrir el HTML).
      </div>
    </details>
  </div>
</details>

<!-- Filtros -->
<form method="GET" action="<?= BASE_URL ?>empresa-evidencia/index" style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:16px 20px;margin-bottom:18px">
  <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap">
    <div>
      <label style="font-size:.75rem;font-weight:600;color:#374151;display:block;margin-bottom:4px">Desde</label>
      <input type="date" name="fecha_desde" value="<?= htmlspecialchars($desde) ?>"
             style="padding:8px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#111827;outline:none">
    </div>
    <div>
      <label style="font-size:.75rem;font-weight:600;color:#374151;display:block;margin-bottom:4px">Hasta</label>
      <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($hasta) ?>"
             style="padding:8px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#111827;outline:none">
    </div>
    <button type="submit"
            style="padding:9px 20px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
      Filtrar
    </button>
  </div>
</form>

<!-- Tabla de pedidos -->
<?php if (empty($pedidos)): ?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:40px;text-align:center">
  <div style="font-size:2.5rem;margin-bottom:12px">📦</div>
  <div style="font-weight:700;color:#111827;margin-bottom:6px">Sin pedidos con evidencia en este rango</div>
  <div style="font-size:.83rem;color:#6B7280">Ajusta el rango de fechas para encontrar pedidos entregados con fotos o ruta GPS.</div>
</div>
<?php else: ?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;overflow:hidden">
  <div style="padding:14px 20px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center">
    <h2 style="font-size:.875rem;font-weight:700;color:#111827;margin:0"><?= count($pedidos) ?> pedido<?= count($pedidos) !== 1 ? 's' : '' ?> con evidencia</h2>
    <span style="font-size:.75rem;color:#6B7280"><?= htmlspecialchars($desde) ?> al <?= htmlspecialchars($hasta) ?></span>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:.83rem">
    <thead>
      <tr style="background:#F9FAFB">
        <th style="padding:9px 16px;text-align:left;color:#6B7280;font-weight:600">Folio</th>
        <th style="padding:9px 8px;text-align:left;color:#6B7280;font-weight:600">Fecha entrega</th>
        <th style="padding:9px 8px;text-align:center;color:#6B7280;font-weight:600">Fotos</th>
        <th style="padding:9px 8px;text-align:center;color:#6B7280;font-weight:600">Firmas</th>
        <th style="padding:9px 8px;text-align:center;color:#6B7280;font-weight:600">Ruta GPS</th>
        <th style="padding:9px 8px;text-align:center;color:#6B7280;font-weight:600">Vence en</th>
        <th style="padding:9px 16px;text-align:right;color:#6B7280;font-weight:600">Exportar</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($pedidos as $ped):
        $ts       = strtotime($ped['created_at']);
        $diasVida = (int)floor(($hoy - $ts) / 86400);
        $diasRest = $retDias - $diasVida;
        $urgente  = $diasRest <= 15;
        $proximo  = $diasRest <= 30 && $diasRest > 15;
        $totalFotos = (int)$ped['evidencias_ruta'] + (int)$ped['fotos_sucursales'] + ($ped['has_foto_directa'] ? 1 : 0);
        $totalFirmas = (int)$ped['evidencias_ruta']; // firmas = evidencias_ruta count
        $bgRow = $urgente ? 'background:#FFF1F2' : ($proximo ? 'background:#FFFBEB' : '');
      ?>
      <tr style="border-top:1px solid #F3F4F6;<?= $bgRow ?>">
        <td style="padding:9px 16px">
          <span style="font-weight:700;color:#111827"><?= htmlspecialchars($ped['folio']) ?></span>
          <?php if ($urgente): ?>
            <span style="display:block;font-size:.68rem;font-weight:700;color:#B91C1C;margin-top:2px">¡Último momento!</span>
          <?php elseif ($proximo): ?>
            <span style="display:block;font-size:.68rem;font-weight:700;color:#B45309;margin-top:2px">Expira pronto</span>
          <?php endif; ?>
        </td>
        <td style="padding:9px 8px;color:#374151"><?= date('d/m/Y', $ts) ?></td>
        <td style="padding:9px 8px;text-align:center">
          <?php if ($totalFotos > 0): ?>
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-weight:700;font-size:.75rem">📷 <?= $totalFotos ?></span>
          <?php else: ?>
            <span style="color:#D1D5DB;font-size:.75rem">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:9px 8px;text-align:center">
          <?php if ($totalFirmas > 0): ?>
            <span style="background:#E0E7FF;color:#3730A3;padding:2px 8px;border-radius:999px;font-weight:700;font-size:.75rem">✓ <?= $totalFirmas ?></span>
          <?php else: ?>
            <span style="color:#D1D5DB;font-size:.75rem">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:9px 8px;text-align:center">
          <?php if ($ped['has_ruta']): ?>
            <span style="background:#DBEAFE;color:#1E40AF;padding:2px 8px;border-radius:999px;font-weight:700;font-size:.72rem">GPS</span>
          <?php else: ?>
            <span style="color:#D1D5DB;font-size:.75rem">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:9px 8px;text-align:center">
          <?php if ($diasRest <= 0): ?>
            <span style="color:#B91C1C;font-weight:700;font-size:.75rem">Expirado</span>
          <?php else: ?>
            <span style="font-size:.78rem;font-weight:600;color:<?= $urgente ? '#B91C1C' : ($proximo ? '#B45309' : '#374151') ?>">
              <?= $diasRest ?> día<?= $diasRest !== 1 ? 's' : '' ?>
            </span>
          <?php endif; ?>
        </td>
        <td style="padding:9px 16px;text-align:right">
          <form method="POST" action="<?= BASE_URL ?>empresa-evidencia/exportar" style="display:inline">
            <input type="hidden" name="pedido_id" value="<?= (int)$ped['id'] ?>">
            <button type="submit"
                    style="padding:7px 14px;background:var(--color-primary);color:#fff;border:none;border-radius:7px;font-weight:700;font-size:.78rem;cursor:pointer;white-space:nowrap;transition:opacity .15s"
                    onmouseenter="this.style.opacity='.8'" onmouseleave="this.style.opacity='1'"
                    title="Descargar ZIP con reporte HTML + fotos + firmas">
              ↓ Exportar ZIP
            </button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
