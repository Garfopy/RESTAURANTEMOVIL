<?php
// Vista: Configurar PayPal plan IDs (superadmin)
$modoActivo = $modoActivo ?? 'sandbox';
$colMensual = $modoActivo === 'live' ? 'paypal_plan_id_live'       : 'paypal_plan_id';
$colAnual   = $modoActivo === 'live' ? 'paypal_plan_id_anual_live' : 'paypal_plan_id_anual';
$todosConId = array_reduce($planes, fn($ok, $p) =>
    $ok && !empty($p[$colMensual]) && !empty($p[$colAnual]), true);
?>
<div style="max-width:700px">

  <!-- Indicador de modo activo -->
  <div style="margin-bottom:14px;display:flex;align-items:center;gap:10px">
    <?php if ($modoActivo === 'live'): ?>
    <span style="display:inline-flex;align-items:center;gap:5px;background:#FFF7ED;border:1px solid #FED7AA;border-radius:20px;padding:4px 12px;font-size:.78rem;font-weight:700;color:#9A3412">
      🚀 Modo activo: LIVE
    </span>
    <?php else: ?>
    <span style="display:inline-flex;align-items:center;gap:5px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:20px;padding:4px 12px;font-size:.78rem;font-weight:700;color:#166534">
      🧪 Modo activo: SANDBOX
    </span>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>config/apis" style="font-size:.75rem;color:#6B7280;text-decoration:underline">Cambiar modo</a>
  </div>
  <?php if ($todosConId): ?>
  <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:.875rem;color:#166534">
    Todos los planes están sincronizados con PayPal (<?= strtoupper($modoActivo) ?>).
  </div>
  <?php else: ?>
  <div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:.875rem;color:#92400E">
    Hay planes sin ID de PayPal en modo <?= strtoupper($modoActivo) ?>. Usa el botón para generarlos automáticamente.
  </div>
  <?php endif; ?>

  <!-- Sincronización automática -->
  <form method="POST" action="<?= BASE_URL ?>suscripcion/sincronizarPlanes"
        style="margin-bottom:24px"
        onsubmit="return confirm('¿Crear los planes faltantes en PayPal automáticamente?')">
    <button type="submit"
            style="padding:10px 24px;background:#0070BA;color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
      Sincronizar planes con PayPal
    </button>
    <span style="font-size:.8rem;color:#6B7280;margin-left:10px">
      Solo crea los planes que aún no tienen ID asignado.
    </span>
  </form>

  <!-- Estado actual de los IDs -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px">
    <h2 style="font-size:.95rem;font-weight:700;margin-bottom:4px;color:#111827">
      PayPal Plan IDs actuales
    </h2>
    <p style="font-size:.78rem;color:#6B7280;margin-bottom:16px">Mostrando IDs del modo: <strong><?= strtoupper($modoActivo) ?></strong></p>
    <div style="display:flex;flex-direction:column;gap:16px">
      <?php foreach ($planes as $plan): ?>
      <div style="border:1px solid #E5E7EB;border-radius:8px;padding:14px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px">
          <div>
            <span style="font-size:.875rem;font-weight:700;color:#111827">
              <?= htmlspecialchars($plan['nombre']) ?>
            </span>
            <span style="font-weight:400;color:#6B7280;font-size:.8rem">
              ($<?= number_format($plan['precio_mensual'], 0, '.', ',') ?>/mes · $<?= number_format($plan['precio_anual'], 0, '.', ',') ?>/año)
            </span>
          </div>
          <a href="<?= BASE_URL ?>suscripcion/editarPlan/<?= (int)$plan['id'] ?>"
             style="font-size:.78rem;padding:5px 12px;background:#EFF6FF;color:#1D4ED8;border-radius:6px;text-decoration:none;font-weight:600">
            Editar límites
          </a>
        </div>
        <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;font-size:.78rem;margin-bottom:10px">
          <?php
          $ilim = fn(int $v) => $v === 0 ? '∞' : $v;
          $limites = [
            'Usuarios'  => $ilim((int)$plan['max_usuarios']),
            'Productos' => $ilim((int)$plan['max_productos']),
            'Pedidos/mes'=> $ilim((int)$plan['max_pedidos_mes']),
            'Sucursales'=> $ilim((int)$plan['max_sucursales']),
          ];
          foreach ($limites as $label => $val): ?>
          <div style="background:#F9FAFB;border-radius:6px;padding:6px 8px;text-align:center">
            <div style="color:#9CA3AF;font-size:.7rem"><?= $label ?></div>
            <div style="font-weight:700;color:#111827"><?= $val ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;font-size:.8rem">
          <div>
            <div style="color:#6B7280;margin-bottom:3px">Mensual</div>
            <?php if (!empty($plan[$colMensual])): ?>
              <code style="background:#F3F4F6;padding:3px 8px;border-radius:4px;color:#111827"><?= htmlspecialchars($plan[$colMensual]) ?></code>
            <?php else: ?>
              <span style="color:#EF4444">Sin ID</span>
            <?php endif; ?>
          </div>
          <div>
            <div style="color:#6B7280;margin-bottom:3px">Anual</div>
            <?php if (!empty($plan[$colAnual])): ?>
              <code style="background:#F3F4F6;padding:3px 8px;border-radius:4px;color:#111827"><?= htmlspecialchars($plan[$colAnual]) ?></code>
            <?php else: ?>
              <span style="color:#EF4444">Sin ID</span>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

</div>
