<?php
// Vista: Gestión de suscripciones (panel superadmin/admin)
$planBadge = function(string $slug, string $nombre): string {
    $colores = [
        'basico'  => 'background:#F3F4F6;color:#374151',
        'pro'     => 'background:#DBEAFE;color:#1D4ED8',
        'empresa' => 'background:#EDE9FE;color:#6D28D9',
    ];
    $estilo = $colores[$slug] ?? 'background:#FEE2E2;color:#991B1B';
    return "<span style=\"{$estilo};padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:600\">{$nombre}</span>";
};
$estadoBadge = function(string $estado): string {
    return match($estado) {
        'activo'           => '<span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Activo</span>',
        'suspendido'       => '<span style="background:#FEF3C7;color:#92400E;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Suspendido</span>',
        'cancelado'        => '<span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Cancelado</span>',
        'pendiente_paypal' => '<span style="background:#E0F2FE;color:#0369A1;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Pendiente PayPal</span>',
        default            => '<span style="background:#F3F4F6;color:#6B7280;padding:2px 8px;border-radius:999px;font-size:.75rem">—</span>',
    };
};
$suscripciones = $resultado['data']  ?? [];
$paginacion    = $resultado          ?? [];
?>

<!-- Filtros + acciones -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;gap:12px;flex-wrap:wrap">
  <form method="GET" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="buscar" placeholder="Buscar empresa..."
           value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>"
           style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
    <select name="plan_id" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      <option value="">Todos los planes</option>
      <?php foreach ($planes as $pl): ?>
      <option value="<?= $pl['id'] ?>" <?= ($filtros['plan_id'] ?? '') == $pl['id'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($pl['nombre']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <select name="estado" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      <option value="">Todos los estados</option>
      <?php foreach (['activo'=>'Activo','suspendido'=>'Suspendido','cancelado'=>'Cancelado','pendiente_paypal'=>'Pendiente PayPal'] as $v => $l): ?>
      <option value="<?= $v ?>" <?= ($filtros['estado'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" style="padding:8px 16px;background:#374151;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer">Filtrar</button>
    <?php $hayFiltros = !empty($filtros['buscar']) || !empty($filtros['plan_id']) || !empty($filtros['estado']); ?>
    <?php if ($hayFiltros): ?>
    <a href="<?= BASE_URL ?>suscripcion/index"
       style="padding:8px 14px;background:#FEE2E2;color:#991B1B;border-radius:8px;font-size:.875rem;font-weight:600;text-decoration:none;white-space:nowrap">
      ✕ Limpiar filtros
    </a>
    <?php endif; ?>
  </form>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php foreach ($planes as $pl): ?>
    <a href="<?= BASE_URL ?>suscripcion/editarPlan/<?= (int)$pl['id'] ?>"
       style="padding:9px 14px;background:#F0FDF4;color:#166534;border:1px solid #BBF7D0;border-radius:8px;text-decoration:none;font-weight:600;font-size:.8rem;white-space:nowrap">
      ✏️ <?= htmlspecialchars($pl['nombre']) ?>
    </a>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>suscripcion/configurar"
       style="padding:9px 16px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
      Configurar PayPal
    </a>
  </div>
</div>

<?php if (empty($suscripciones)): ?>
<div style="background:#fff;border-radius:12px;padding:40px;text-align:center;border:1px solid #E5E7EB;color:#6B7280">
  No hay suscripciones que coincidan con los filtros.
</div>
<?php else: ?>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB">
        <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600">Empresa</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Plan</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Estado</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Inicio</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Vencimiento</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">PayPal ID</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($suscripciones as $s): ?>
      <tr style="border-top:1px solid #F3F4F6">
        <td style="padding:12px 16px">
          <div style="font-weight:600;color:#111827"><?= htmlspecialchars($s['razon_social']) ?></div>
          <div style="font-size:.8rem;color:#6B7280"><?= htmlspecialchars($s['empresa_email'] ?? '') ?></div>
        </td>
        <td style="padding:12px"><?= $planBadge($s['plan_slug'], $s['plan_nombre']) ?></td>
        <td style="padding:12px"><?= $estadoBadge($s['estado']) ?></td>
        <td style="padding:12px;color:#374151;white-space:nowrap">
          <?= $s['fecha_inicio'] ? date('d/m/Y', strtotime($s['fecha_inicio'])) : '—' ?>
        </td>
        <td style="padding:12px;color:#374151;white-space:nowrap">
          <?= $s['fecha_vencimiento'] ? date('d/m/Y', strtotime($s['fecha_vencimiento'])) : '—' ?>
        </td>
        <td style="padding:12px;font-family:monospace;font-size:.75rem;color:#6B7280">
          <?= $s['paypal_subscription_id'] ? htmlspecialchars(substr($s['paypal_subscription_id'], 0, 16)) . '…' : '—' ?>
        </td>
        <td style="padding:12px">
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <!-- Cambiar plan -->
            <form method="POST" action="<?= BASE_URL ?>suscripcion/cambiarPlan" style="display:flex;gap:4px">
              <input type="hidden" name="suscripcion_id" value="<?= $s['id'] ?>">
              <select name="plan_id" style="padding:4px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.75rem">
                <?php foreach ($planes as $pl): ?>
                <option value="<?= $pl['id'] ?>" <?= $pl['id'] == $s['plan_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($pl['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
              <button type="submit" style="padding:4px 10px;background:#374151;color:#fff;border:none;border-radius:6px;font-size:.75rem;cursor:pointer">
                Cambiar
              </button>
            </form>
            <!-- Suspender / Activar -->
            <?php if ($s['estado'] === 'activo'): ?>
            <form method="POST" action="<?= BASE_URL ?>suscripcion/suspender">
              <input type="hidden" name="suscripcion_id" value="<?= $s['id'] ?>">
              <button type="submit"
                      onclick="return confirm('¿Suspender la suscripción de <?= htmlspecialchars(addslashes($s['razon_social'])) ?>?')"
                      style="padding:4px 10px;background:#FEF3C7;color:#92400E;border:none;border-radius:6px;font-size:.75rem;cursor:pointer;font-weight:600">
                Suspender
              </button>
            </form>
            <?php elseif (in_array($s['estado'], ['suspendido','cancelado'])): ?>
            <form method="POST" action="<?= BASE_URL ?>suscripcion/activar">
              <input type="hidden" name="suscripcion_id" value="<?= $s['id'] ?>">
              <button type="submit"
                      style="padding:4px 10px;background:#D1FAE5;color:#065F46;border:none;border-radius:6px;font-size:.75rem;cursor:pointer;font-weight:600">
                Activar
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if (($paginacion['last_page'] ?? 1) > 1): ?>
<div style="margin-top:16px;display:flex;gap:6px;justify-content:center">
  <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
  <a href="?page=<?= $i ?>&buscar=<?= urlencode($filtros['buscar']??'') ?>&plan_id=<?= urlencode($filtros['plan_id']??'') ?>&estado=<?= urlencode($filtros['estado']??'') ?>"
     style="padding:6px 12px;border-radius:6px;font-size:.8rem;text-decoration:none;<?= $i === ($paginacion['current_page'] ?? 1) ? 'background:var(--color-primary);color:#fff' : 'background:#fff;color:#374151;border:1px solid #E5E7EB' ?>">
    <?= $i ?>
  </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
<?php endif; ?>
