<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Repartidor — CarniHub</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #0F172A; color: #F1F5F9; font-family: 'Inter', sans-serif; min-height: 100vh; }

    .app-shell { max-width: 480px; margin: 0 auto; min-height: 100vh; display: flex; flex-direction: column; background: #111827; }

    .header {
      background: #1E293B; padding: 16px 18px;
      display: flex; align-items: center; justify-content: space-between;
      border-bottom: 1px solid #334155;
      position: sticky; top: 0; z-index: 10;
    }
    .header-name { font-weight: 800; font-size: 1rem; }
    .header-sub  { font-size: .72rem; color: #94A3B8; margin-top: 2px; }
    .header-logout { font-size: .78rem; color: #64748B; text-decoration: none; padding: 6px 10px; border: 1px solid #334155; border-radius: 8px; font-weight: 600; }

    .body { padding: 16px; flex: 1; display: flex; flex-direction: column; gap: 12px; }

    /* Section label */
    .section-label { font-size: .68rem; font-weight: 700; color: #64748B; letter-spacing: .07em; margin-bottom: 6px; }

    /* Cards */
    .card { background: #1E293B; border-radius: 14px; padding: 16px; border: 1px solid #334155; }

    /* Pedido card */
    .pedido-card { background: #1E293B; border-radius: 14px; border: 1px solid #334155; overflow: hidden; }
    .pedido-card-bar { height: 4px; }
    .pedido-card-bar.listo   { background: linear-gradient(90deg, #7C3AED, #A78BFA); }
    .pedido-card-bar.en-ruta { background: linear-gradient(90deg, #D97706, #F59E0B); }
    .pedido-card-body { padding: 14px 16px; }

    .folio { font-size: .95rem; font-weight: 800; font-family: monospace; color: #F1F5F9; }
    .empresa-name { font-size: .78rem; color: #94A3B8; margin-top: 2px; }

    .badge { display: inline-block; padding: 3px 10px; border-radius: 999px; font-size: .7rem; font-weight: 700; }
    .badge-listo   { background: #3B0764; color: #E9D5FF; }
    .badge-en-ruta { background: #78350F; color: #FCD34D; }

    .info-row { display: flex; align-items: flex-start; gap: 6px; font-size: .8rem; color: #CBD5E1; margin-top: 8px; }
    .info-row span { flex-shrink: 0; }

    /* Alert box for guidance */
    .alert { border-radius: 10px; padding: 11px 14px; font-size: .82rem; font-weight: 600; margin-top: 10px; }
    .alert-purple { background: #2E1065; color: #C4B5FD; border: 1px solid #4C1D95; }
    .alert-yellow { background: #1C1407; color: #FCD34D; border: 1px solid #78350F; }
    .alert-green  { background: #022C22; color: #6EE7B7; border: 1px solid #065F46; }

    /* Paradas mini list */
    .paradas-mini { display: flex; flex-direction: column; gap: 4px; margin-top: 10px; }
    .parada-mini-item { display: flex; align-items: center; gap: 8px; padding: 6px 8px; border-radius: 8px; background: #0F172A; }
    .parada-num-mini { width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: .65rem; font-weight: 700; flex-shrink: 0; }
    .parada-num-mini.done { background: #059669; color: #fff; }
    .parada-num-mini.pend { background: #334155; color: #94A3B8; }
    .parada-mini-nombre { font-size: .78rem; font-weight: 600; color: #E2E8F0; flex: 1; }
    .parada-mini-estado { font-size: .68rem; color: #64748B; }
    .parada-mini-estado.done { color: #6EE7B7; }

    /* Progress bar */
    .progress-bar-wrap { height: 6px; background: #1E293B; border-radius: 999px; overflow: hidden; margin-top: 8px; }
    .progress-bar-fill { height: 100%; background: linear-gradient(90deg, #059669, #10B981); border-radius: 999px; transition: width .4s; }

    /* Buttons */
    .btn { width: 100%; border: none; border-radius: 11px; font-size: .9rem; font-weight: 700; cursor: pointer; padding: 13px; transition: opacity .15s; text-decoration: none; display: block; text-align: center; }
    .btn:active { opacity: .85; }
    .btn-red    { background: linear-gradient(135deg, #C8102E, #E11D48); color: #fff; }
    .btn-yellow { background: linear-gradient(135deg, #D97706, #F59E0B); color: #fff; }
    .btn-gray   { background: #1E293B; color: #94A3B8; border: 1px solid #334155; }

    /* Empty state */
    .empty-state { text-align: center; padding: 48px 20px; color: #475569; }
    .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }
    .empty-state h2 { font-size: 1rem; font-weight: 700; color: #64748B; margin-bottom: 6px; }
    .empty-state p  { font-size: .82rem; line-height: 1.6; }

    /* Stats strip */
    .stats-strip { display: flex; gap: 10px; }
    .stat-chip { flex: 1; background: #1E293B; border: 1px solid #334155; border-radius: 10px; padding: 10px 12px; text-align: center; }
    .stat-chip .val { font-size: 1.2rem; font-weight: 800; color: #F1F5F9; }
    .stat-chip .lbl { font-size: .65rem; color: #64748B; margin-top: 2px; font-weight: 600; letter-spacing: .04em; }

    /* Flash */
    .flash-ok  { background: #064E3B; color: #6EE7B7; border: 1px solid #065F46; border-radius: 10px; padding: 12px 14px; font-size: .85rem; font-weight: 600; }
    .flash-err { background: #7F1D1D; color: #FCA5A5; border: 1px solid #991B1B; border-radius: 10px; padding: 12px 14px; font-size: .85rem; font-weight: 600; }

    /* Bottom nav */
    .bottom-nav { background: #1E293B; border-top: 1px solid #334155; padding: 10px 16px; display: flex; justify-content: center; }
  </style>
</head>
<body>

<div class="app-shell">

  <!-- Header -->
  <div class="header">
    <div>
      <div class="header-name">CarniHub</div>
      <div class="header-sub"><?= htmlspecialchars($_SESSION['usuario']['nombre'] ?? '') ?> — Repartidor</div>
    </div>
    <a href="<?= BASE_URL ?>auth/logout" class="header-logout">Salir</a>
  </div>

  <!-- Body -->
  <div class="body">

  <!-- Banner de cambio de contraseña (primer login) -->
  <?php if (!empty($flash) && $flash['type'] === 'first_login'): ?>
  <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 16px 20px; border-radius: 12px; margin-bottom: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
    <div style="display: flex; flex-direction: column; gap: 12px;">
      <div>
        <div style="font-weight: 700; font-size: 1rem; margin-bottom: 6px;">
          🔐 Actualiza tu contraseña
        </div>
        <div style="opacity: 0.95; font-size: 0.85rem;">
          <?= htmlspecialchars($flash['message']) ?>
        </div>
      </div>
      <div style="display: flex; gap: 10px;">
        <a href="<?= BASE_URL ?>cuenta/perfil"
           style="background: white; color: #667eea; padding: 10px 18px; border-radius: 8px; text-decoration: none; font-weight: 600; font-size: 0.9rem; flex: 1; text-align: center;">
          Cambiar contraseña
        </a>
        <button onclick="dismissFirstLoginBanner(<?= $_SESSION['usuario']['id'] ?>)"
                style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 10px 18px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 0.9rem; flex: 1;">
          Después
        </button>
      </div>
    </div>
  </div>
  <script>
  function dismissFirstLoginBanner(userId) {
      fetch('<?= BASE_URL ?>cuenta/dismissFirstLogin', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ user_id: userId })
      }).then(() => {
          location.reload();
      });
  }
  </script>
  <?php elseif (!empty($flash)): ?>
    <div class="<?= $flash['type'] === 'error' ? 'flash-err' : 'flash-ok' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
  <?php endif; ?>

  <h1 style="font-size:1.1rem;font-weight:800;margin-bottom:4px">Entregas de hoy</h1>
  <p style="font-size:.8rem;color:#9CA3AF;margin-bottom:16px"><?= date('d \d\e F \d\e Y') ?></p>

  <?php
    // ── Datos de KPIs (defensivos) ─────────────────────────────────────────
    $resumenHoy     = $resumenHoy     ?? [];
    $proximaParada  = $proximaParada  ?? null;
    $evidencia      = $evidencia      ?? ['entregadas' => 0, 'completas' => 0, 'pct' => 0.0];
    $incidencias    = $incidencias    ?? 0;
    $tiempoProm     = isset($tiempoProm) ? (float)$tiempoProm : 0.0;
    $kilosPendientes = isset($kilosPendientes) ? (float)$kilosPendientes : 0.0;
    $prodSemanal    = is_array($prodSemanal ?? null) ? $prodSemanal : [];
    $slaMinutosParada = $slaMinutosParada ?? 30;

    $totalHoy      = (int)($resumenHoy['total'] ?? 0);
    $entregadasHoy = (int)($resumenHoy['entregadas'] ?? 0);
    $pendientesHoy = (int)($resumenHoy['pendientes'] ?? 0);
    $pctRuta       = $totalHoy > 0 ? round(($entregadasHoy / $totalHoy) * 100) : 0;

    // Entregas exitosas % = entregadas / (entregadas + fallidas + parciales)
    $intentosHoy = $entregadasHoy + (int)($resumenHoy['fallidas'] ?? 0) + (int)($resumenHoy['parciales'] ?? 0);
    $pctExitosas = $intentosHoy > 0 ? round(($entregadasHoy / $intentosHoy) * 100) : 0;

    // Próxima entrega
    $proxFolio = $proximaParada ? ($proximaParada['folio'] ?? '') : '';
    $proxHora  = '—';
    if ($proximaParada) {
        if (!empty($proximaParada['fecha_entrega'])) {
            $proxHora = date('H:i', strtotime((string)$proximaParada['fecha_entrega']));
        } elseif (!empty($proximaParada['eta_minutos'])) {
            $proxHora = 'ETA ' . (int)$proximaParada['eta_minutos'] . ' min';
        }
    }

    // Estado SLA por tiempo promedio
    $slaOk = $tiempoProm > 0 && $tiempoProm <= $slaMinutosParada;
    $slaColor = $tiempoProm <= 0 ? '#9CA3AF' : ($slaOk ? '#10B981' : '#F59E0B');
    $slaLabel = $tiempoProm <= 0 ? 'Sin datos' : ($slaOk ? 'Dentro de SLA' : 'Por encima del SLA');
  ?>

  <!-- ── Botón generar reporte semanal ─────────────────────────────────── -->
  <a href="<?= BASE_URL ?>empresa-reporte/index?periodo=7d"
     style="display:flex;align-items:center;justify-content:center;gap:8px;background:linear-gradient(135deg,#7C3AED,#5B21B6);color:#fff;padding:12px;border-radius:10px;text-decoration:none;font-weight:700;font-size:.88rem;margin-bottom:14px">
    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6h6v6m-3-13v9m-7 4h14a2 2 0 002-2V8.414a1 1 0 00-.293-.707l-4.414-4.414A1 1 0 0014.586 3H5a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
    Generar reporte semanal (PDF)
  </a>

  <!-- ── KPIs estratégicos del Repartidor ──────────────────────────────── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">

    <!-- Roja: Paradas Completadas -->
    <div style="background:linear-gradient(135deg,#C8102E,#9B0A22);border-radius:12px;padding:14px;color:#fff">
      <div style="font-size:.65rem;font-weight:700;opacity:.85;text-transform:uppercase;letter-spacing:.06em">Paradas completadas</div>
      <div style="font-size:1.7rem;font-weight:900;line-height:1;margin-top:6px"><?= $entregadasHoy ?>/<?= $totalHoy ?></div>
      <div style="height:5px;background:rgba(255,255,255,.25);border-radius:999px;margin-top:8px;overflow:hidden">
        <div style="height:100%;width:<?= $pctRuta ?>%;background:#fff;border-radius:999px"></div>
      </div>
      <div style="font-size:.68rem;opacity:.85;margin-top:5px"><?= $pctRuta ?>% de la ruta del día</div>
    </div>

    <!-- Azul: Kilos Pendientes -->
    <div style="background:linear-gradient(135deg,#1D4ED8,#1E40AF);border-radius:12px;padding:14px;color:#fff">
      <div style="font-size:.65rem;font-weight:700;opacity:.85;text-transform:uppercase;letter-spacing:.06em">Kilos pendientes</div>
      <div style="font-size:1.7rem;font-weight:900;line-height:1;margin-top:6px"><?= number_format($kilosPendientes, 1) ?> <span style="font-size:.85rem;opacity:.85">kg</span></div>
      <div style="font-size:.68rem;opacity:.85;margin-top:8px">en el vehículo por descargar</div>
    </div>

    <!-- Verde: Entregas Exitosas % -->
    <div style="background:linear-gradient(135deg,#059669,#047857);border-radius:12px;padding:14px;color:#fff">
      <div style="font-size:.65rem;font-weight:700;opacity:.85;text-transform:uppercase;letter-spacing:.06em">Entregas exitosas</div>
      <div style="font-size:1.7rem;font-weight:900;line-height:1;margin-top:6px"><?= $pctExitosas ?>%</div>
      <div style="font-size:.68rem;opacity:.85;margin-top:8px"><?= $entregadasHoy ?> de <?= $intentosHoy ?> intento(s)</div>
    </div>

    <!-- Naranja: Próxima Entrega -->
    <div style="background:linear-gradient(135deg,#D97706,#B45309);border-radius:12px;padding:14px;color:#fff">
      <div style="font-size:.65rem;font-weight:700;opacity:.85;text-transform:uppercase;letter-spacing:.06em">Próxima entrega</div>
      <?php if ($proximaParada): ?>
        <div style="font-size:1rem;font-weight:800;line-height:1.1;margin-top:6px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($proximaParada['sucursal_nombre'] ?? '') ?>">
          <?= htmlspecialchars($proxFolio) ?>
        </div>
        <div style="font-size:.78rem;opacity:.95;font-weight:700;margin-top:3px"><?= htmlspecialchars($proxHora) ?></div>
        <div style="font-size:.65rem;opacity:.8;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($proximaParada['sucursal_nombre'] ?? '') ?></div>
      <?php else: ?>
        <div style="font-size:1.3rem;font-weight:900;margin-top:6px">—</div>
        <div style="font-size:.68rem;opacity:.85;margin-top:8px">Sin paradas pendientes</div>
      <?php endif; ?>
    </div>

  </div>

  <!-- Tarjeta púrpura full-width: Evidencias listas -->
  <div style="background:linear-gradient(135deg,#7C3AED,#5B21B6);border-radius:12px;padding:14px;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:14px">
    <div style="flex:1">
      <div style="font-size:.65rem;font-weight:700;opacity:.85;text-transform:uppercase;letter-spacing:.06em">Evidencias listas</div>
      <div style="display:flex;align-items:baseline;gap:10px;margin-top:6px">
        <div style="font-size:1.7rem;font-weight:900;line-height:1"><?= number_format($evidencia['pct'], 0) ?>%</div>
        <div style="font-size:.78rem;opacity:.85"><?= $evidencia['completas'] ?> de <?= $evidencia['entregadas'] ?> entregas con foto + firma</div>
      </div>
      <?php if ($evidencia['pct'] < 100 && $evidencia['entregadas'] > 0): ?>
      <div style="font-size:.7rem;opacity:.9;margin-top:6px;background:rgba(255,255,255,.18);padding:5px 8px;border-radius:6px;display:inline-block">
        ⚠ Subir evidencias asegura tu pago
      </div>
      <?php endif; ?>
    </div>
    <div style="width:54px;height:54px;border-radius:50%;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0">
      <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
  </div>

  <!-- ── Indicador SLA + Incidencias ──────────────────────────────────── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">
    <div style="background:#1F2937;border-radius:12px;padding:14px;border-left:4px solid <?= $slaColor ?>">
      <div style="font-size:.65rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.06em">Tiempo por parada</div>
      <div style="display:flex;align-items:baseline;gap:6px;margin-top:6px">
        <span style="font-size:1.5rem;font-weight:900;color:#F9FAFB"><?= $tiempoProm > 0 ? number_format($tiempoProm, 0) : '—' ?></span>
        <span style="font-size:.75rem;color:#9CA3AF">min</span>
      </div>
      <div style="font-size:.68rem;color:<?= $slaColor ?>;font-weight:700;margin-top:4px"><?= $slaLabel ?> (<?= $slaMinutosParada ?> min)</div>
    </div>

    <div style="background:#1F2937;border-radius:12px;padding:14px;border-left:4px solid <?= $incidencias > 0 ? '#EF4444' : '#10B981' ?>">
      <div style="font-size:.65rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.06em">Incidencias (30d)</div>
      <div style="font-size:1.5rem;font-weight:900;color:#F9FAFB;margin-top:6px"><?= number_format($incidencias) ?></div>
      <div style="font-size:.68rem;color:#9CA3AF;margin-top:4px">Fallidas o parciales</div>
    </div>
  </div>

  <!-- ── Mini-chart: Productividad semanal ────────────────────────────── -->
  <?php if (!empty($prodSemanal)): ?>
  <div style="background:#1F2937;border-radius:12px;padding:14px;margin-bottom:14px">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
      <div>
        <div style="font-size:.85rem;font-weight:700;color:#F9FAFB">Productividad semanal</div>
        <div style="font-size:.68rem;color:#9CA3AF">Entregadas vs intentos · últimas 6 semanas</div>
      </div>
    </div>
    <div style="height:140px"><canvas id="chart-prod-rep"></canvas></div>
  </div>
  <?php endif; ?>

  <?php if (!$rutaHoy): ?>
    <div style="text-align:center;padding:40px 20px;color:#6B7280">
      <div style="font-size:2.5rem;margin-bottom:12px">📦</div>
      <p style="font-weight:600">No tienes entregas asignadas para hoy.</p>
      <p style="font-size:.85rem;margin-top:4px">Contacta a tu empresa para más información.</p>
      <a href="<?= BASE_URL ?>repartidor/historial" class="btn-secondary" style="margin-top:20px;display:inline-block;width:auto;padding:10px 24px">Ver historial</a>
    </div>
  <?php endif; ?>

    <?php
    // Calcular stats generales
    $totalPedidosDirectos = count($pedidosDirectos ?? []);
    $enRuta = count(array_filter($pedidosDirectos ?? [], fn($p) => $p['estado'] === 'en_ruta'));
    $listos  = count(array_filter($pedidosDirectos ?? [], fn($p) => $p['estado'] === 'en_preparacion'));

    // Stats de ruta tradicional
    $totalParadas = count($paradas ?? []);
    $entregadas   = count(array_filter($paradas ?? [], fn($p) => $p['estado'] === 'entregado'));
    ?>

    <?php if ($totalPedidosDirectos > 0 || $totalParadas > 0): ?>
    <!-- Stats strip -->
    <div class="stats-strip">
      <?php if ($totalPedidosDirectos > 0): ?>
      <div class="stat-chip">
        <div class="val"><?= $totalPedidosDirectos ?></div>
        <div class="lbl">PEDIDOS</div>
      </div>
      <?php endif; ?>
      <?php if ($enRuta > 0): ?>
      <div class="stat-chip">
        <div class="val" style="color:#FCD34D"><?= $enRuta ?></div>
        <div class="lbl">EN CAMINO</div>
      </div>
      <?php endif; ?>
      <?php if ($totalParadas > 0): ?>
      <div class="stat-chip">
        <div class="val" style="color:#6EE7B7"><?= $entregadas ?>/<?= $totalParadas ?></div>
        <div class="lbl">PARADAS</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($pedidosDirectos)): ?>
    <!-- ────── PEDIDOS DIRECTOS ────── -->
    <div class="section-label">PEDIDOS ASIGNADOS DIRECTAMENTE</div>

    <?php foreach ($pedidosDirectos as $pd):
      $esEnRuta = $pd['estado'] === 'en_ruta';
      $barClass  = $esEnRuta ? 'en-ruta' : 'listo';
      $badgeClass = $esEnRuta ? 'badge-en-ruta' : 'badge-listo';
      $badgeLabel = $esEnRuta ? 'En camino' : 'Listo para salir';

      // Paradas del pedido (si vienen en $pd)
      $sucursalesPd = $pd['sucursales'] ?? [];
      $entregadasPd = count(array_filter($sucursalesPd, fn($s) => !empty($s['foto_entrega_path'])));
      $totalPd      = count($sucursalesPd);
    ?>
    <div class="pedido-card">
      <div class="pedido-card-bar <?= $barClass ?>"></div>
      <div class="pedido-card-body">

        <!-- Folio + badge -->
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px">
          <div>
            <div class="folio"><?= htmlspecialchars($pd['folio']) ?></div>
            <div class="empresa-name"><?= htmlspecialchars($pd['empresa_nombre']) ?></div>
          </div>
          <span class="badge <?= $badgeClass ?>"><?= $badgeLabel ?></span>
        </div>

        <?php if (!empty($pd['fecha_entrega'])): ?>
        <div class="info-row"><span>📅</span> Entrega: <?= date('d/m/Y', strtotime($pd['fecha_entrega'])) ?></div>
        <?php endif; ?>

        <?php if (!empty($sucursalesPd)): ?>
        <!-- Mini lista de paradas -->
        <div style="font-size:.68rem;font-weight:700;color:#64748B;letter-spacing:.05em;margin-top:12px;margin-bottom:4px">
          PARADAS (<?= $entregadasPd ?>/<?= $totalPd ?>)
        </div>
        <?php if ($totalPd > 0): ?>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?= $totalPd > 0 ? round($entregadasPd/$totalPd*100) : 0 ?>%"></div>
        </div>
        <?php endif; ?>
        <div class="paradas-mini">
          <?php foreach ($sucursalesPd as $i => $s):
            $done = !empty($s['foto_entrega_path']);
          ?>
          <div class="parada-mini-item">
            <div class="parada-num-mini <?= $done ? 'done' : 'pend' ?>"><?= $done ? '✓' : ($i+1) ?></div>
            <div class="parada-mini-nombre"><?= htmlspecialchars($s['sucursal_nombre'] ?? $s['nombre'] ?? '') ?></div>
            <div class="parada-mini-estado <?= $done ? 'done' : '' ?>">
              <?= $done ? 'Entregada' : 'Pendiente' ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php elseif (!empty($pd['direccion_entrega'])): ?>
        <div class="info-row"><span>📍</span><?= htmlspecialchars($pd['direccion_entrega']) ?></div>
        <?php endif; ?>

        <!-- Guía contextual -->
        <?php if ($pd['estado'] === 'en_preparacion'): ?>
        <div class="alert alert-purple">
          📦 El pedido está listo en bodega. Recógelo y pulsa <strong>Empezar viaje</strong>.
        </div>
        <form method="POST" action="<?= BASE_URL ?>repartidor/iniciarViaje/<?= $pd['id'] ?>"
              onsubmit="return confirm('¿Iniciar el viaje para <?= htmlspecialchars(addslashes($pd['folio'])) ?>?')"
              style="margin-top:10px">
          <button type="submit" class="btn btn-red">🚀 Empezar viaje</button>
        </form>

        <?php else: ?>
        <div class="alert alert-yellow">
          🚚 Viaje en curso — marca cada parada entregada desde la pantalla de entrega.
        </div>
        <a href="<?= BASE_URL ?>repartidor/pedidoDirecto/<?= $pd['id'] ?>" class="btn btn-yellow"
           style="margin-top:10px">
          📍 Ver entrega en curso
        </a>
        <?php endif; ?>

      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($rutaHoy): ?>
    <!-- ────── RUTA DEL DÍA ────── -->
    <div class="section-label" style="margin-top:4px">RUTA DEL DÍA</div>

    <?php
    $progP = $rutaHoy['total_paradas'] > 0
           ? round($rutaHoy['entregadas'] / $rutaHoy['total_paradas'] * 100)
           : 0;
    ?>
    <div class="card" style="padding:12px 16px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <div>
          <div style="font-size:.75rem;color:#94A3B8;margin-bottom:2px">Progreso de ruta</div>
          <div style="font-weight:800;font-size:1rem"><?= (int)$rutaHoy['entregadas'] ?> / <?= (int)$rutaHoy['total_paradas'] ?> entregas</div>
        </div>
        <div style="font-size:1.5rem;font-weight:800;color:#10B981"><?= $progP ?>%</div>
      </div>
      <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?= $progP ?>%"></div>
      </div>
    </div>

    <?php foreach ($paradas as $i => $parada):
      $pDone = $parada['estado'] === 'entregado';
    ?>
    <div class="pedido-card">
      <div class="pedido-card-bar" style="background:<?= $pDone ? '#059669' : ($parada['estado']==='fallido' ? '#EF4444' : '#F59E0B') ?>"></div>
      <div class="pedido-card-body">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:6px">
          <div>
            <div style="font-weight:700;font-size:.9rem"><?= ($i+1) ?>. <?= htmlspecialchars($parada['sucursal_nombre']) ?></div>
            <div style="font-size:.75rem;color:#94A3B8;margin-top:2px"><?= htmlspecialchars($parada['empresa_nombre'] ?? '') ?></div>
          </div>
          <?php if ($pDone): ?>
          <span class="badge" style="background:#064E3B;color:#6EE7B7">✓ Entregado</span>
          <?php elseif ($parada['estado'] === 'fallido'): ?>
          <span class="badge" style="background:#7F1D1D;color:#FCA5A5">Fallido</span>
          <?php else: ?>
          <span class="badge badge-listo">Pendiente</span>
          <?php endif; ?>
        </div>
        <div class="info-row"><span>📍</span><?= htmlspecialchars($parada['direccion']) ?></div>
        <div class="info-row"><span>📋</span>Pedido: <strong style="color:#F1F5F9"><?= htmlspecialchars($parada['pedido_folio']) ?></strong></div>
        <?php if ($pDone && $parada['hora_entrega']): ?>
        <div class="alert alert-green" style="margin-top:8px;font-size:.75rem">
          ✅ Entregado a las <?= date('H:i', strtotime($parada['hora_entrega'])) ?>
        </div>
        <?php elseif ($parada['estado'] === 'pendiente'): ?>
        <a href="<?= BASE_URL ?>repartidor/entrega/<?= $parada['id'] ?>" class="btn btn-yellow" style="margin-top:10px;font-size:.85rem">
          Registrar entrega
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!$rutaHoy && empty($pedidosDirectos)): ?>
    <!-- Estado vacío -->
    <div class="empty-state">
      <div class="icon">📦</div>
      <h2>Sin entregas asignadas hoy</h2>
      <p>No tienes ningún pedido asignado por el momento.<br>Contacta a tu supervisor para más información.</p>
    </div>
    <?php endif; ?>

    <!-- Historial -->
    <a href="<?= BASE_URL ?>repartidor/historial" class="btn btn-gray" style="margin-top:4px">
      📋 Ver historial de entregas
    </a>

  </div><!-- /.body -->

  <!-- Bottom nav -->
  <div class="bottom-nav">
    <span style="font-size:.7rem;color:#475569">CarniHub &copy; <?= date('Y') ?></span>
  </div>

</div><!-- /.app-shell -->

<script src="<?= BASE_URL ?>public/js/chart.min.js"></script>
<script>
(function(){
  if (typeof Chart === 'undefined') return;
  const c = document.getElementById('chart-prod-rep');
  if (!c) return;
  const labels    = <?= json_encode(array_map(fn($x) => 'S' . substr((string)$x['yw'], 4), $prodSemanal)) ?>;
  const entreg    = <?= json_encode(array_map(fn($x) => (int)$x['entregadas'], $prodSemanal)) ?>;
  const intentos  = <?= json_encode(array_map(fn($x) => (int)$x['intentos'],   $prodSemanal)) ?>;
  new Chart(c, {
    type: 'bar',
    data: {
      labels: labels,
      datasets: [
        { label: 'Entregadas', data: entreg,   backgroundColor: 'rgba(16,185,129,.85)', borderRadius: 4, maxBarThickness: 22 },
        { label: 'Intentos',   data: intentos, backgroundColor: 'rgba(96,165,250,.55)', borderRadius: 4, maxBarThickness: 22 }
      ]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { color: '#D1D5DB', font: { size: 10 }, boxWidth: 10 } },
        tooltip: { backgroundColor: '#111827' }
      },
      scales: {
        y: { beginAtZero: true, ticks: { color: '#9CA3AF', font: { size: 10 } }, grid: { color: 'rgba(255,255,255,.08)' } },
        x: { ticks: { color: '#9CA3AF', font: { size: 10 } }, grid: { display: false } }
      }
    }
  });
})();
</script>

</body>
</html>
