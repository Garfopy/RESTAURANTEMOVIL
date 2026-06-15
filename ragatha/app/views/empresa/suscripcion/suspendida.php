<?php
// Vista: Cuenta suspendida — se muestra cuando requireSuscripcionActiva() falla
?>
<div style="max-width:520px;margin:60px auto;text-align:center">
  <div style="width:72px;height:72px;background:#FEE2E2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 24px">
    <svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="#991B1B">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
  </div>
  <h2 style="font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:10px">
    Tu cuenta está suspendida
  </h2>
  <p style="color:#6B7280;font-size:.9rem;margin-bottom:24px">
    Tu suscripción fue suspendida o cancelada. Renueva tu plan para continuar usando CarniHub.
  </p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
    <a href="<?= BASE_URL ?>empresa-suscripcion/planes"
       style="padding:12px 28px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.875rem">
      Ver planes
    </a>
    <a href="<?= BASE_URL ?>auth/logout"
       style="padding:12px 24px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
      Cerrar sesión
    </a>
  </div>
</div>
