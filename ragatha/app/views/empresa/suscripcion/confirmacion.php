<?php
// Vista: Confirmación de suscripción activada con PayPal
$planNombre = $plan['plan_nombre'] ?? 'Pro';
$vence      = $plan['fecha_vencimiento'] ?? null;
?>
<div style="max-width:520px;margin:60px auto;text-align:center">
  <div style="width:72px;height:72px;background:#D1FAE5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#059669">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
    </svg>
  </div>
  <h2 style="font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:10px">
    ¡Suscripción activada!
  </h2>
  <p style="color:#6B7280;font-size:.9rem;margin-bottom:6px">
    Tu plan <strong style="color:#111827"><?= htmlspecialchars($planNombre) ?></strong> está activo.
  </p>
  <?php if ($vence): ?>
  <p style="color:#6B7280;font-size:.875rem;margin-bottom:28px">
    Próxima renovación: <strong><?= date('d/m/Y', strtotime($vence)) ?></strong>
  </p>
  <?php else: ?>
  <p style="color:#6B7280;font-size:.875rem;margin-bottom:28px">
    PayPal procesará el cobro mensualmente.
  </p>
  <?php endif; ?>
  <a href="<?= BASE_URL ?>empresa/dashboard"
     style="display:inline-block;padding:12px 32px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.9rem">
    Ir al dashboard
  </a>
</div>
