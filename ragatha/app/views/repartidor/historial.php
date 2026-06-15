<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Historial — CarniHub</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0F172A; color: #F1F5F9; font-family: 'Inter', sans-serif; min-height: 100vh; }
    .app-shell { max-width: 480px; margin: 0 auto; min-height: 100vh; background: #111827; }

    .header {
      background: #1E293B; padding: 14px 16px;
      display: flex; align-items: center; gap: 12px;
      border-bottom: 1px solid #334155;
      position: sticky; top: 0; z-index: 10;
    }
    .header-back { color: #94A3B8; text-decoration: none; font-size: 1.3rem; line-height: 1; padding: 4px; }
    .header-title { font-weight: 800; font-size: .95rem; }

    .body { padding: 16px; display: flex; flex-direction: column; gap: 12px; }

    .section-label { font-size: .68rem; font-weight: 700; color: #64748B; letter-spacing: .07em; margin-bottom: 4px; }

    /* Trip card (pedido directo) */
    .trip-card { background: #1E293B; border-radius: 14px; border: 1px solid #334155; overflow: hidden; }
    .trip-bar  { height: 4px; background: linear-gradient(90deg, #059669, #10B981); }
    .trip-body { padding: 14px 16px; }

    .folio { font-size: .95rem; font-weight: 800; font-family: monospace; color: #F1F5F9; }
    .empresa { font-size: .75rem; color: #94A3B8; margin-top: 2px; }

    .badge-done { background: #064E3B; color: #6EE7B7; padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 700; }

    .meta-row { display: flex; gap: 6px; align-items: center; font-size: .75rem; color: #94A3B8; margin-top: 6px; }

    /* Sucursal rows inside trip */
    .suc-list { display: flex; flex-direction: column; gap: 4px; margin-top: 10px; }
    .suc-row  { display: flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 8px; background: #0F172A; }
    .suc-num  { width: 20px; height: 20px; border-radius: 50%; background: #059669; color: #fff; font-size: .6rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .suc-nombre { font-size: .78rem; font-weight: 600; flex: 1; }
    .suc-hora   { font-size: .68rem; color: #6EE7B7; }
    .suc-foto-thumb { width: 32px; height: 32px; object-fit: cover; border-radius: 6px; border: 2px solid #065F46; cursor: pointer; flex-shrink: 0; }

    /* Parada formal row */
    .formal-row { background: #1E293B; border-radius: 10px; padding: 12px 14px; border: 1px solid #334155; }

    /* Map link button */
    .btn-map { display: inline-flex; align-items: center; gap: 6px; background: #1E3A5F; color: #93C5FD; border: 1px solid #1E40AF; border-radius: 9px; padding: 8px 14px; font-size: .8rem; font-weight: 700; text-decoration: none; margin-top: 10px; }
    .btn-map:active { opacity: .8; }

    /* Empty state */
    .empty { text-align: center; padding: 56px 20px; color: #475569; }
    .empty .icon { font-size: 3rem; margin-bottom: 12px; }
    .empty h2 { font-size: .95rem; font-weight: 700; color: #64748B; margin-bottom: 6px; }
    .empty p  { font-size: .8rem; line-height: 1.6; }

    /* Flash */
    .flash-ok  { background: #064E3B; color: #6EE7B7; border: 1px solid #065F46; border-radius: 10px; padding: 12px 14px; font-size: .85rem; font-weight: 600; }
    .flash-err { background: #7F1D1D; color: #FCA5A5; border: 1px solid #991B1B; border-radius: 10px; padding: 12px 14px; font-size: .85rem; font-weight: 600; }
  </style>
</head>
<body>

<div class="app-shell">
  <div class="header">
    <a href="<?= BASE_URL ?>repartidor/inicio" class="header-back">&larr;</a>
    <div>
      <div class="header-title">Historial de entregas</div>
    </div>
  </div>

  <div class="body">

    <?php if (!empty($flash)): ?>
    <div class="<?= $flash['type'] === 'error' ? 'flash-err' : 'flash-ok' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php
    $hayAlgo = !empty($pedidosEntregados) || !empty($historial);
    if (!$hayAlgo):
    ?>
    <div class="empty">
      <div class="icon">📋</div>
      <h2>Sin entregas aún</h2>
      <p>Cuando completes tus primeras entregas<br>aparecerán aquí con el recorrido del viaje.</p>
    </div>

    <?php else: ?>

    <?php if (!empty($pedidosEntregados)): ?>
    <!-- ── Pedidos directos completados ── -->
    <div class="section-label">PEDIDOS ENTREGADOS</div>

    <?php foreach ($pedidosEntregados as $pd): ?>
    <div class="trip-card">
      <div class="trip-bar"></div>
      <div class="trip-body">

        <div style="display:flex;justify-content:space-between;align-items:flex-start">
          <div>
            <div class="folio"><?= htmlspecialchars($pd['folio']) ?></div>
            <div class="empresa"><?= htmlspecialchars($pd['empresa_nombre']) ?></div>
          </div>
          <span class="badge-done">✓ Entregado</span>
        </div>

        <?php if ($pd['ruta_finalizada_at']): ?>
        <div class="meta-row">
          <span>📅</span>
          <span><?= date('d/m/Y', strtotime($pd['ruta_finalizada_at'])) ?></span>
          <span style="color:#475569">·</span>
          <span>Finalizado <?= date('H:i', strtotime($pd['ruta_finalizada_at'])) ?></span>
          <?php if ($pd['ruta_iniciada_at']): ?>
          <?php
          $duracion = (strtotime($pd['ruta_finalizada_at']) - strtotime($pd['ruta_iniciada_at'])) / 60;
          ?>
          <span style="color:#475569">·</span>
          <span><?= round($duracion) ?> min</span>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pd['sucursales'])): ?>
        <div class="suc-list">
          <?php foreach ($pd['sucursales'] as $i => $s): ?>
          <div class="suc-row">
            <div class="suc-num"><?= $i + 1 ?></div>
            <div class="suc-nombre"><?= htmlspecialchars($s['sucursal_nombre']) ?></div>
            <?php if (!empty($s['fecha_llegada'])): ?>
            <span class="suc-hora"><?= date('H:i', strtotime($s['fecha_llegada'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($s['foto_entrega_path'])): ?>
            <a href="<?= htmlspecialchars($s['foto_entrega_path']) ?>" target="_blank">
              <img src="<?= htmlspecialchars($s['foto_entrega_path']) ?>" alt="Evidencia" class="suc-foto-thumb">
            </a>
            <?php else: ?>
            <span style="font-size:.65rem;color:#475569">Sin foto</span>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pd['ruta_polyline'])): ?>
        <a href="<?= BASE_URL ?>repartidor/verViaje/<?= (int)$pd['id'] ?>" class="btn-map">
          🗺 Ver recorrido del viaje
        </a>
        <?php endif; ?>

      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($historial)): ?>
    <!-- ── Paradas de ruta formal ── -->
    <div class="section-label" style="margin-top:4px">RUTAS FORMALES</div>

    <?php foreach ($historial as $h): ?>
    <div class="formal-row">
      <div style="display:flex;justify-content:space-between;align-items:flex-start">
        <div>
          <div style="font-weight:700;font-size:.88rem"><?= htmlspecialchars($h['sucursal_nombre']) ?></div>
          <div style="font-size:.72rem;color:#94A3B8;margin-top:2px"><?= htmlspecialchars($h['empresa_nombre']) ?></div>
          <div style="font-size:.72rem;color:#64748B;margin-top:2px">Pedido: <?= htmlspecialchars($h['folio']) ?></div>
        </div>
        <div style="text-align:right">
          <span class="badge-done">✓ Entregado</span>
          <div style="font-size:.72rem;color:#64748B;margin-top:4px">
            <?= $h['hora_entrega'] ? date('d/m H:i', strtotime($h['hora_entrega'])) : date('d/m/Y', strtotime($h['fecha'])) ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</div>

</body>
</html>
