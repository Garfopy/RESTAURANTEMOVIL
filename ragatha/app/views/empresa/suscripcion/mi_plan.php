<?php
// Vista: Mi plan actual (portal empresa)
$planActivo  = $plan ?? null;
$tieneплан   = !empty($planActivo);
?>
<div style="max-width:560px">

  <?php if ($tieneплан): ?>
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:28px;margin-bottom:20px">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px">
      <div>
        <h2 style="font-size:1.1rem;font-weight:800;color:#111827;margin-bottom:4px">
          Plan <?= htmlspecialchars($planActivo['plan_nombre']) ?>
        </h2>
        <p style="color:#6B7280;font-size:.85rem">Suscripción activa</p>
      </div>
      <?php
      $colores = ['basico'=>'background:#F3F4F6;color:#374151','pro'=>'background:#DBEAFE;color:#1D4ED8','empresa'=>'background:#EDE9FE;color:#6D28D9'];
      $c = $colores[$planActivo['plan_slug'] ?? ''] ?? 'background:#D1FAE5;color:#065F46';
      ?>
      <span style="<?= $c ?>;padding:4px 14px;border-radius:999px;font-weight:700;font-size:.85rem">
        <?= htmlspecialchars($planActivo['plan_nombre']) ?>
      </span>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;font-size:.85rem;color:#374151">
      <div style="background:#F9FAFB;border-radius:8px;padding:12px">
        <div style="color:#6B7280;font-size:.75rem;margin-bottom:4px">Precio mensual</div>
        <div style="font-weight:700;font-size:1rem;color:#111827">
          $<?= number_format($planActivo['precio_mensual'] ?? 0, 0, '.', ',') ?> MXN
        </div>
      </div>
      <div style="background:#F9FAFB;border-radius:8px;padding:12px">
        <div style="color:#6B7280;font-size:.75rem;margin-bottom:4px">Próxima renovación</div>
        <div style="font-weight:700">
          <?= $planActivo['fecha_vencimiento'] ? date('d/m/Y', strtotime($planActivo['fecha_vencimiento'])) : 'Automática' ?>
        </div>
      </div>
      <?php $limites = [
        'Usuarios'    => $planActivo['max_usuarios']    ? $planActivo['max_usuarios']    : 'Ilimitados',
        'Productos'   => $planActivo['max_productos']   ? $planActivo['max_productos']   : 'Ilimitados',
        'Pedidos/mes' => $planActivo['max_pedidos_mes'] ? $planActivo['max_pedidos_mes'] : 'Ilimitados',
        'Sucursales'  => $planActivo['max_sucursales']  ? $planActivo['max_sucursales']  : 'Ilimitadas',
      ]; foreach ($limites as $label => $val): ?>
      <div style="background:#F9FAFB;border-radius:8px;padding:12px">
        <div style="color:#6B7280;font-size:.75rem;margin-bottom:4px"><?= $label ?></div>
        <div style="font-weight:700"><?= $val ?></div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <a href="<?= BASE_URL ?>empresa-suscripcion/planes"
     style="display:inline-block;padding:10px 22px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.875rem">
    Cambiar o mejorar plan
  </a>

  <?php else: ?>
  <div style="background:#FEF3C7;border-radius:12px;padding:28px;text-align:center;border:1px solid #FDE68A">
    <p style="color:#92400E;font-size:.9rem;margin-bottom:16px;font-weight:600">
      Tu cuenta no tiene una suscripción activa.
    </p>
    <a href="<?= BASE_URL ?>empresa-suscripcion/planes"
       style="padding:10px 24px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.875rem">
      Ver planes disponibles
    </a>
  </div>
  <?php endif; ?>
</div>
