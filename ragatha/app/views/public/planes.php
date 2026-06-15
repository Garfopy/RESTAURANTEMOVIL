<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Planes y precios — <?= htmlspecialchars($appName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #F9FAFB; color: #111827; }
    :root { --color-primary: <?= htmlspecialchars($colorPrimary) ?>; }
    .plan-card { transition: transform .15s, box-shadow .15s; }
    .plan-card:hover { transform: translateY(-4px); box-shadow: 0 10px 30px rgba(0,0,0,.08); }
  </style>
</head>
<body>

<!-- Navbar -->
<nav style="background:#fff;border-bottom:1px solid #E5E7EB;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between">
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($appLogo): ?>
      <img src="<?= htmlspecialchars($appLogo) ?>" alt="Logo" style="height:36px;object-fit:contain">
    <?php endif; ?>
    <span style="font-weight:800;font-size:1.1rem;color:#111827"><?= htmlspecialchars($appName) ?></span>
  </div>
  <a href="<?= BASE_URL ?>auth/login"
     style="padding:8px 20px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.875rem">
    Iniciar sesión
  </a>
</nav>

<!-- Hero -->
<div style="text-align:center;padding:64px 24px 48px">
  <h1 style="font-size:2.2rem;font-weight:900;color:#111827;margin-bottom:14px;line-height:1.2">
    Planes para productores de carne
  </h1>
  <p style="color:#6B7280;font-size:1rem;max-width:500px;margin:0 auto 32px">
    Gestiona tu catálogo, pedidos, repartidores y clientes desde una sola plataforma.
  </p>

  <!-- Toggle mensual / anual -->
  <div style="display:inline-flex;background:#E5E7EB;border-radius:999px;padding:4px;gap:4px">
    <button id="btn-mensual" onclick="toggleCiclo('mensual')"
            style="padding:8px 24px;border-radius:999px;border:none;font-size:.875rem;font-weight:600;cursor:pointer;background:var(--color-primary);color:#fff">
      Mensual
    </button>
    <button id="btn-anual" onclick="toggleCiclo('anual')"
            style="padding:8px 24px;border-radius:999px;border:none;font-size:.875rem;font-weight:600;cursor:pointer;background:transparent;color:#6B7280">
      Anual <span style="font-size:.75rem;font-weight:700;color:#059669">Ahorra 17%</span>
    </button>
  </div>
</div>

<!-- Planes -->
<div style="max-width:980px;margin:0 auto 64px;padding:0 24px;display:grid;grid-template-columns:repeat(3,1fr);gap:24px">

  <?php
  $destaque = ['basico' => false, 'pro' => true, 'empresa' => false];
  $coloresNombre = ['basico' => '#374151', 'pro' => '#1D4ED8', 'empresa' => '#6D28D9'];
  foreach ($planes as $pl):
    $es_destacado = $destaque[$pl['slug']] ?? false;
    $colorNombre  = $coloresNombre[$pl['slug']] ?? '#374151';
    $features     = json_decode($pl['features'] ?? '[]', true) ?: [];
  ?>
  <div class="plan-card"
       style="background:#fff;border-radius:18px;border:2px solid <?= $es_destacado ? 'var(--color-primary)' : '#E5E7EB' ?>;padding:32px;display:flex;flex-direction:column;position:relative;<?= $es_destacado ? 'box-shadow:0 8px 24px rgba(0,0,0,.10)' : '' ?>">
    <?php if ($es_destacado): ?>
    <div style="position:absolute;top:-1px;left:50%;transform:translateX(-50%);background:var(--color-primary);color:#fff;font-size:.7rem;font-weight:800;padding:4px 18px;border-radius:0 0 10px 10px;letter-spacing:.05em;white-space:nowrap">
      MÁS POPULAR
    </div>
    <?php endif; ?>

    <div style="font-weight:800;font-size:1.1rem;color:<?= $colorNombre ?>;margin-bottom:6px">
      <?= htmlspecialchars($pl['nombre']) ?>
    </div>
    <div style="font-size:.8rem;color:#6B7280;margin-bottom:20px">
      <?= htmlspecialchars($pl['descripcion'] ?? '') ?>
    </div>

    <!-- Precio mensual -->
    <div class="precio-mensual" style="margin-bottom:24px">
      <span style="font-size:2.4rem;font-weight:900;color:#111827;line-height:1">
        $<?= number_format($pl['precio_mensual'], 0, '.', ',') ?>
      </span>
      <span style="font-size:.85rem;color:#6B7280"> MXN/mes</span>
    </div>
    <!-- Precio anual -->
    <div class="precio-anual" style="display:none;margin-bottom:24px">
      <span style="font-size:2.4rem;font-weight:900;color:#111827;line-height:1">
        $<?= number_format($pl['precio_anual'] / 12, 0, '.', ',') ?>
      </span>
      <span style="font-size:.85rem;color:#6B7280"> MXN/mes</span>
      <div style="font-size:.78rem;color:#059669;font-weight:700;margin-top:2px">
        $<?= number_format($pl['precio_anual'], 0, '.', ',') ?> facturado anualmente
      </div>
    </div>

    <!-- Límites rápidos -->
    <?php
    $limDesc = [];
    if ($pl['max_usuarios'])   $limDesc[] = $pl['max_usuarios'] . ' usuarios';
    else $limDesc[] = 'Usuarios ilimitados';
    if ($pl['max_sucursales']) $limDesc[] = $pl['max_sucursales'] . ' sucursales';
    else $limDesc[] = 'Sucursales ilimitadas';
    ?>
    <div style="background:#F9FAFB;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:.78rem;color:#6B7280">
      <?= implode(' · ', $limDesc) ?>
    </div>

    <!-- Features -->
    <ul style="list-style:none;padding:0;margin:0 0 28px;flex:1">
      <?php foreach ($features as $feat): ?>
      <li style="padding:5px 0;display:flex;align-items:flex-start;gap:8px;font-size:.82rem;color:#374151">
        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#059669" style="flex-shrink:0;margin-top:1px">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
        </svg>
        <?= htmlspecialchars($feat) ?>
      </li>
      <?php endforeach; ?>
    </ul>

    <!-- CTA -->
    <a href="<?= BASE_URL ?>planes/registro?plan=<?= urlencode($pl['slug']) ?>&ciclo=mensual"
       class="btn-plan"
       data-plan="<?= htmlspecialchars($pl['slug']) ?>"
       style="display:block;text-align:center;padding:13px;background:<?= $es_destacado ? 'var(--color-primary)' : '#111827' ?>;color:#fff;border-radius:10px;text-decoration:none;font-weight:700;font-size:.875rem">
      Empezar con <?= htmlspecialchars($pl['nombre']) ?>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- Footer -->
<div style="text-align:center;padding:32px 24px;border-top:1px solid #E5E7EB;color:#9CA3AF;font-size:.8rem">
  <?= htmlspecialchars($appName) ?> &copy; <?= date('Y') ?> — Todos los precios en MXN + IVA
</div>

<script>
function toggleCiclo(ciclo) {
  document.querySelectorAll('.precio-mensual').forEach(e => e.style.display = ciclo === 'mensual' ? 'block' : 'none');
  document.querySelectorAll('.precio-anual').forEach(e   => e.style.display = ciclo === 'anual'   ? 'block' : 'none');
  const btnM = document.getElementById('btn-mensual');
  const btnA = document.getElementById('btn-anual');
  if (ciclo === 'anual') {
    btnA.style.cssText += ';background:var(--color-primary);color:#fff';
    btnM.style.cssText += ';background:transparent;color:#6B7280';
  } else {
    btnM.style.cssText += ';background:var(--color-primary);color:#fff';
    btnA.style.cssText += ';background:transparent;color:#6B7280';
  }

  // Actualizar links de los botones de CTA
  document.querySelectorAll('.btn-plan').forEach(btn => {
    const plan = btn.getAttribute('data-plan');
    btn.href = '<?= BASE_URL ?>planes/registro?plan=' + encodeURIComponent(plan) + '&ciclo=' + ciclo;
  });
}
</script>
</body>
</html>
