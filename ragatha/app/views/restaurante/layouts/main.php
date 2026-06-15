<?php
$usuario    = $_SESSION['usuario'] ?? [];
$restaurante = $restaurante ?? (new RestauranteModel())->find($_SESSION['restaurante_activo_id'] ?? 0);
$colorPri   = $restaurante['color_primario']   ?? '#C8102E';
$colorSec   = $restaurante['color_secundario'] ?? '#1f2937';

// Detecta si el color primario es claro (para usar texto oscuro en el sidebar)
function _sidebarIsLight(string $hex): bool {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (strlen($hex) !== 6) return false;
    $r = hexdec(substr($hex,0,2))/255;
    $g = hexdec(substr($hex,2,2))/255;
    $b = hexdec(substr($hex,4,2))/255;
    foreach ([$r,$g,$b] as &$c) $c = $c<=0.04045 ? $c/12.92 : (($c+0.055)/1.055)**2.4;
    return (0.2126*$r + 0.7152*$g + 0.0722*$b) > 0.35;
}
$_sidebarLight = _sidebarIsLight($colorPri);
$restNombre = $restaurante['nombre'] ?? 'Mi Restaurante';
$restLogo   = $restaurante['logo']   ?? '';
$activeMenu = $activeMenu ?? '';

// Roles para visibilidad del sidebar
$_rol      = $usuario['rol_slug'] ?? '';
$_isAdmin  = in_array($_rol, ['admin_restaurante', 'comprador'], true); // gestión del restaurante
$_isMesero = in_array($_rol, ['mesero', 'comprador'], true);            // operación de salón
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Restaurante') ?> — <?= htmlspecialchars($restNombre) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/css/restaurant.css?v=<?= @filemtime(ROOT_PATH . '/public/css/restaurant.css') ?: time() ?>">
  <style>.rst-modal-backdrop{display:none}.rst-modal-backdrop.open{display:flex}</style>
  <script>var CARNIHUB_BASE_URL = '<?= BASE_URL ?>';</script>
  <script src="<?= BASE_URL ?>public/js/api-client.js?v=<?= @filemtime(ROOT_PATH . '/public/js/api-client.js') ?: time() ?>"></script>
  <script src="<?= BASE_URL ?>public/js/chart.umd.min.js"></script>
  <style>
    :root {
      --cp: <?= htmlspecialchars($colorPri) ?>;
      --cs: <?= htmlspecialchars($colorSec) ?>;
      --color-primary:   <?= htmlspecialchars($colorPri) ?>;
      --color-secondary: <?= htmlspecialchars($colorSec) ?>;
    }
    /* Sidebar con color de marca */
    .rst-sidebar {
      background: var(--cp);
      border-right: <?= $_sidebarLight ? '1px solid #E5E7EB' : 'none' ?>;
    }
    <?php if ($_sidebarLight): ?>
    /* Color primario claro → texto oscuro para contraste */
    .rst-sidebar-logo { border-bottom-color: rgba(0,0,0,.10); }
    .rst-sidebar nav::-webkit-scrollbar-thumb { background: rgba(0,0,0,.15); }
    .rst-nav-section { color: rgba(0,0,0,.45); }
    .rst-nav-link { color: rgba(0,0,0,.70); }
    .rst-nav-link:hover { background: rgba(0,0,0,.08); color: #111827; }
    .rst-nav-link.active {
      background: rgba(0,0,0,.12);
      color: #111827;
      border-left-color: rgba(0,0,0,.6);
      font-weight: 700;
    }
    .rst-sidebar-footer { border-top-color: rgba(0,0,0,.10); color: rgba(0,0,0,.45); }
    .rst-sidebar-footer strong { color: rgba(0,0,0,.6); }
    <?php else: ?>
    /* Color primario oscuro → texto blanco */
    .rst-sidebar-logo { border-bottom-color: rgba(255,255,255,.15); }
    .rst-sidebar nav::-webkit-scrollbar-thumb { background: rgba(255,255,255,.25); }
    .rst-nav-section { color: rgba(255,255,255,.55); }
    .rst-nav-link { color: rgba(255,255,255,.82); }
    .rst-nav-link:hover { background: rgba(255,255,255,.15); color: #fff; }
    .rst-nav-link.active {
      background: rgba(255,255,255,.22);
      color: #fff;
      border-left-color: rgba(255,255,255,.9);
      font-weight: 700;
    }
    .rst-sidebar-footer { border-top-color: rgba(255,255,255,.15); color: rgba(255,255,255,.5); }
    .rst-sidebar-footer strong { color: rgba(255,255,255,.7); }
    <?php endif; ?>
  </style>
</head>
<body>

<!-- Sidebar -->
<aside class="rst-sidebar" id="rstSidebar">
  <div class="rst-sidebar-logo">
    <?php if ($restLogo): ?>
    <img src="<?= BASE_URL . htmlspecialchars($restLogo) ?>" alt="Logo"
         style="height:36px;object-fit:contain;margin-bottom:8px;display:block">
    <?php endif; ?>
    <div style="font-weight:700;font-size:.95rem;color:<?= $_sidebarLight ? '#111827' : '#fff' ?>;line-height:1.2">
      <?= htmlspecialchars($restNombre) ?>
    </div>
    <div style="font-size:.7rem;color:<?= $_sidebarLight ? 'rgba(0,0,0,.5)' : 'rgba(255,255,255,.65)' ?>;margin-top:3px">Mi Empresa</div>
  </div>

  <nav>
    <a class="rst-nav-link <?= $activeMenu === 'rest_dashboard' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>restaurante/dashboard">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>

    <div class="rst-nav-section">Operación</div>
    <a class="rst-nav-link <?= $activeMenu === 'rest_mesas' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-mesa/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      Mesas
    </a>
    <?php if ($_isMesero): ?>
    <a class="rst-nav-link <?= $activeMenu === 'rest_pedidos' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-pedido/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Pedidos
    </a>
    <?php endif; ?>
    <?php if ($_isAdmin || $_isMesero): ?>
    <a class="rst-nav-link <?= $activeMenu === 'rest_reservas' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-reserva/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Reservaciones
    </a>
    <?php endif; ?>
    <?php if ($_isMesero): ?>
    <a class="rst-nav-link <?= $activeMenu === 'rest_tickets' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-ticket/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"/></svg>
      Tickets
    </a>
    <?php endif; ?>

    <div class="rst-nav-section">Financiero</div>
    <a class="rst-nav-link <?= $activeMenu === 'rest_finanzas' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-finanzas/dashboard">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
      Dashboard Financiero
    </a>
    <a class="rst-nav-link <?= in_array($activeMenu, ['rest_gastos','rest_retiros','rest_egresos']) ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-finanzas/egresos" style="padding-left:38px;font-size:.82rem">
      Gastos y Retiros
    </a>
    <a class="rst-nav-link <?= $activeMenu === 'rest_cortes' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-finanzas/cortes" style="padding-left:38px;font-size:.82rem">
      Corte de Caja
    </a>

    <div class="rst-nav-section">Cocina & Menú</div>
    <a class="rst-nav-link <?= $activeMenu === 'rest_menu' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-menu/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
      Menú
    </a>
    <a class="rst-nav-link <?= $activeMenu === 'rest_inventario' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-inventario/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      Ingredientes
    </a>
    <?php if ($_isAdmin): ?>
    <a class="rst-nav-link <?= $activeMenu === 'rest_mermas' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-mermas/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/></svg>
      Mermas
    </a>
    <?php endif; ?>

    <div class="rst-nav-section">Clientes</div>
    <a class="rst-nav-link <?= $activeMenu === 'rest_clientes' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-cliente/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Comensales
    </a>
    <a class="rst-nav-link <?= $activeMenu === 'rest_promociones' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-promocion/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Promociones
    </a>

    <div class="rst-nav-section">Ajustes</div>
    <a class="rst-nav-link <?= $activeMenu === 'rest_locales' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>restaurante/locales">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      Mis Locales
    </a>
    <a class="rst-nav-link <?= $activeMenu === 'rest_staff' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-staff/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Staff
    </a>
    <a class="rst-nav-link <?= $activeMenu === 'rest_qr' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-config/qr">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"/></svg>
      QR del local
    </a>
    <a class="rst-nav-link <?= $activeMenu === 'rest_config' ? 'active' : '' ?>"
       href="<?= BASE_URL ?>rest-config/index">
      <svg style="width:16px;height:16px;flex-shrink:0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Configuración
    </a>
  </nav>

  <div class="rst-sidebar-footer">
    <a href="<?= BASE_URL ?>auth/logout"
       style="display:flex;align-items:center;justify-content:center;gap:6px;
              padding:8px 12px;margin-bottom:10px;border-radius:8px;
              background:<?= $_sidebarLight ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.15)' ?>;
              color:<?= $_sidebarLight ? '#374151' : '#fff' ?>;text-decoration:none;
              font-size:.82rem;font-weight:600;transition:background .15s"
       onmouseover="this.style.background='<?= $_sidebarLight ? 'rgba(0,0,0,.14)' : 'rgba(255,255,255,.25)' ?>'"
       onmouseout="this.style.background='<?= $_sidebarLight ? 'rgba(0,0,0,.08)' : 'rgba(255,255,255,.15)' ?>'">
      <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Cerrar sesión
    </a>
    <div style="text-align:center;font-size:.7rem;color:#9CA3AF">
      Potenciado por <strong>CarniHub</strong>
    </div>
  </div>
</aside>

<!-- Main content -->
<div class="rst-main">
  <header class="rst-topbar">
    <div style="display:flex;align-items:center;gap:12px">
      <!-- Mobile menu toggle -->
      <button onclick="document.getElementById('rstSidebar').classList.toggle('open')"
              style="display:none;background:none;border:none;cursor:pointer;padding:4px"
              id="menuToggle">
        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div style="font-weight:600;font-size:.95rem;color:#111827">
        <?= htmlspecialchars($pageTitle ?? '') ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:14px">
      <?php if (!empty($restaurante['slug'])): ?>
      <a href="<?= BASE_URL ?>menu/<?= htmlspecialchars($restaurante['slug']) ?>"
         target="_blank"
         style="font-size:.8rem;color:var(--cp);font-weight:600;text-decoration:none;
                display:flex;align-items:center;gap:4px">
        <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
        Ver menú
      </a>
      <span style="width:1px;height:16px;background:#E5E7EB"></span>
      <?php endif; ?>
      <span style="font-size:.82rem;color:#6B7280">
        <?= htmlspecialchars($usuario['nombre'] ?? '') ?>
      </span>
    </div>
  </header>

  <div class="rst-page page-content">
    <?php if (!empty($flash)): ?>
    <div class="flash flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"
         data-flash="<?= htmlspecialchars(md5(($flash['type'] ?? '') . '|' . ($flash['message'] ?? ''))) ?>"
         onclick="this.remove()">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
  </div>
</div>

<script>
// Mobile: muestra toggle en pantallas pequeñas
if (window.innerWidth <= 768) {
  document.getElementById('menuToggle').style.display = 'flex';
}
// Cerrar sidebar al hacer click fuera (mobile)
document.addEventListener('click', e => {
  const sb = document.getElementById('rstSidebar');
  if (window.innerWidth <= 768 && sb.classList.contains('open') && !sb.contains(e.target)) {
    sb.classList.remove('open');
  }
});
// Teleport modal backdrops to <body> so position:fixed works correctly
// (page-content animation creates a containing block that clips fixed children)
document.querySelectorAll('.rst-modal-backdrop').forEach(m => document.body.appendChild(m));

// Flash: dedupe duplicates + auto-remove after fade-out animation
(function(){
  const seen = new Set();
  document.querySelectorAll('.flash[data-flash]').forEach(el => {
    const k = el.dataset.flash;
    if (seen.has(k)) { el.remove(); return; }
    seen.add(k);
    setTimeout(() => el.remove(), 5000);
  });
})();
</script>
</body>
</html>
