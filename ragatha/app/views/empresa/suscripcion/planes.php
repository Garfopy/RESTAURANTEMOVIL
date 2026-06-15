<?php
// Vista: Planes disponibles para el admin_empresa (portal empresa)
$planActualSlug = $plan['plan_slug'] ?? '';
$badgeColores = [
    'basico'  => '#374151',
    'pro'     => '#1D4ED8',
    'empresa' => '#6D28D9',
];
?>
<div style="max-width:900px;margin:0 auto">

  <!-- Encabezado con toggle mensual/anual -->
  <div style="text-align:center;margin-bottom:32px">
    <h2 style="font-size:1.3rem;font-weight:800;color:#111827;margin-bottom:8px">
      Elige tu plan
    </h2>
    <p style="color:#6B7280;font-size:.9rem;margin-bottom:20px">
      Todos los planes incluyen acceso completo a la plataforma CarniHub
    </p>
    <div style="display:inline-flex;background:#F3F4F6;border-radius:999px;padding:4px;gap:4px">
      <button id="btn-mensual" onclick="toggleCiclo('mensual')"
              style="padding:6px 18px;border-radius:999px;border:none;font-size:.8rem;font-weight:600;cursor:pointer;background:var(--color-primary);color:#fff">
        Mensual
      </button>
      <button id="btn-anual" onclick="toggleCiclo('anual')"
              style="padding:6px 18px;border-radius:999px;border:none;font-size:.8rem;font-weight:600;cursor:pointer;background:transparent;color:#6B7280">
        Anual <span style="font-size:.7rem;color:#059669;font-weight:700">-17%</span>
      </button>
    </div>
  </div>

  <!-- Cards de planes -->
  <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:20px">
    <?php foreach ($planes as $pl):
      $esActual = ($pl['slug'] === $planActualSlug);
      $color    = $badgeColores[$pl['slug']] ?? '#374151';
      $features = json_decode($pl['features'] ?? '[]', true) ?: [];
    ?>
    <div style="background:#fff;border-radius:16px;border:2px solid <?= $esActual ? 'var(--color-primary)' : '#E5E7EB' ?>;padding:28px;display:flex;flex-direction:column;position:relative">
      <?php if ($esActual): ?>
      <div style="position:absolute;top:-1px;right:18px;background:var(--color-primary);color:#fff;font-size:.7rem;font-weight:700;padding:4px 12px;border-radius:0 0 8px 8px">
        Tu plan actual
      </div>
      <?php endif; ?>

      <div style="font-weight:800;font-size:1rem;color:<?= $color ?>;margin-bottom:6px">
        <?= htmlspecialchars($pl['nombre']) ?>
      </div>
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:16px">
        <?= htmlspecialchars($pl['descripcion'] ?? '') ?>
      </div>

      <!-- Precio -->
      <div style="margin-bottom:20px">
        <div class="precio-mensual">
          <span style="font-size:2rem;font-weight:900;color:#111827">
            $<?= number_format($pl['precio_mensual'], 0, '.', ',') ?>
          </span>
          <span style="font-size:.8rem;color:#6B7280">/mes MXN</span>
        </div>
        <div class="precio-anual" style="display:none">
          <span style="font-size:2rem;font-weight:900;color:#111827">
            $<?= number_format($pl['precio_anual'] / 12, 0, '.', ',') ?>
          </span>
          <span style="font-size:.8rem;color:#6B7280">/mes MXN</span>
          <div style="font-size:.75rem;color:#059669;font-weight:600">
            $<?= number_format($pl['precio_anual'], 0, '.', ',') ?> facturado anualmente
          </div>
        </div>
      </div>

      <!-- Features -->
      <ul style="list-style:none;padding:0;margin:0 0 24px;flex:1;font-size:.8rem;color:#374151">
        <?php foreach ($features as $feat): ?>
        <li style="padding:5px 0;display:flex;align-items:flex-start;gap:8px">
          <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="#059669" style="flex-shrink:0;margin-top:1px">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          <?= htmlspecialchars($feat) ?>
        </li>
        <?php endforeach; ?>
      </ul>

      <!-- Botón -->
      <?php if ($esActual): ?>
      <div style="text-align:center;padding:10px;background:#F9FAFB;border-radius:8px;font-size:.8rem;color:#6B7280;font-weight:600">
        Plan activo
      </div>
      <?php elseif (empty($pl['paypal_plan_id'])): ?>
      <div style="text-align:center;padding:10px;background:#FEF3C7;border-radius:8px;font-size:.8rem;color:#92400E">
        Contacta a soporte para activar
      </div>
      <?php else: ?>
      <form method="POST" action="<?= BASE_URL ?>empresa-suscripcion/checkout">
        <input type="hidden" name="plan_slug" value="<?= htmlspecialchars($pl['slug']) ?>">
        <button type="submit"
                style="width:100%;padding:12px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.875rem;cursor:pointer">
          Suscribirse con PayPal
        </button>
      </form>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function toggleCiclo(ciclo) {
  const mensual = document.querySelectorAll('.precio-mensual');
  const anual   = document.querySelectorAll('.precio-anual');
  const btnM    = document.getElementById('btn-mensual');
  const btnA    = document.getElementById('btn-anual');
  if (ciclo === 'anual') {
    mensual.forEach(el => el.style.display = 'none');
    anual.forEach(el   => el.style.display = 'block');
    btnA.style.background = 'var(--color-primary)'; btnA.style.color = '#fff';
    btnM.style.background = 'transparent'; btnM.style.color = '#6B7280';
  } else {
    anual.forEach(el   => el.style.display = 'none');
    mensual.forEach(el => el.style.display = 'block');
    btnM.style.background = 'var(--color-primary)'; btnM.style.color = '#fff';
    btnA.style.background = 'transparent'; btnA.style.color = '#6B7280';
  }
}
</script>
