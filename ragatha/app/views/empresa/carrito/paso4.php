<?php
// Vista: Paso 3 (confirmado) — Pedido registrado + timeline de pasos
$tipoEntrega = $pedido['tipo_entrega'] ?? 'pickup';
?>
<!-- Pasos -->
<div style="display:flex;align-items:center;gap:0;margin-bottom:32px;font-size:.8rem">
  <?php
  $pasos = ['1'=>'Productos','2'=>'Resumen','3'=>'Confirmado'];
  foreach ($pasos as $num => $label):
    $activo = $num === '3';
    $hecho  = $num < '3';
  ?>
  <div style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:<?= $activo || $hecho ? '#D1FAE5' : '#E5E7EB' ?>;color:<?= $activo || $hecho ? '#065F46' : '#9CA3AF' ?>;<?= $num === '1' ? 'border-radius:8px 0 0 8px' : ($num === '3' ? 'border-radius:0 8px 8px 0' : '') ?>">
    <span style="font-weight:700"><?= ($hecho || $activo) ? '✓' : $num ?></span>
    <?= $label ?>
  </div>
  <?php if ($num < '3'): ?>
  <div style="width:0;height:0;border-top:18px solid transparent;border-bottom:18px solid transparent;border-left:10px solid <?= $activo || $hecho ? '#D1FAE5' : '#E5E7EB' ?>"></div>
  <?php endif; ?>
  <?php endforeach; ?>
</div>

<div style="max-width:520px;margin:0 auto">
  <!-- Folio -->
  <div style="text-align:center;margin-bottom:28px">
    <div style="width:72px;height:72px;border-radius:50%;background:#D1FAE5;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:2rem">✓</div>
    <h2 style="font-size:1.4rem;font-weight:800;color:#111827;margin-bottom:8px">¡Pedido registrado!</h2>
    <div style="background:#F9FAFB;border:2px dashed #D1D5DB;border-radius:12px;padding:16px;display:inline-block;min-width:220px">
      <div style="font-size:.78rem;color:#6B7280;margin-bottom:4px">Número de folio</div>
      <div style="font-size:1.8rem;font-weight:800;color:var(--color-primary);letter-spacing:.05em"><?= htmlspecialchars($folio) ?></div>
      <div style="font-size:.72rem;color:#9CA3AF;margin-top:4px">Guarda este folio para dar seguimiento</div>
    </div>
  </div>

  <!-- Timeline de pasos siguientes -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px;margin-bottom:20px">
    <div style="font-weight:700;font-size:.9rem;color:#111827;margin-bottom:16px">¿Qué sigue?</div>

    <?php
    $pasosFlujo = [
      [
        'icon'  => '✓',
        'bg'    => '#D1FAE5',
        'color' => '#065F46',
        'titulo'=> 'Pedido registrado',
        'desc'  => 'Tu pedido fue recibido con el folio ' . htmlspecialchars($folio) . '.',
        'hecho' => true,
      ],
      [
        'icon'  => '2',
        'bg'    => '#DBEAFE',
        'color' => '#1E40AF',
        'titulo'=> 'Revisión por el equipo',
        'desc'  => 'El supervisor o administrador revisará y aprobará tu pedido. Recibirás una notificación.',
        'hecho' => false,
        'actual'=> true,
      ],
      [
        'icon'  => '3',
        'bg'    => '#F3F4F6',
        'color' => '#9CA3AF',
        'titulo'=> 'Sube tu comprobante de pago',
        'desc'  => 'Una vez aprobado, podrás subir el comprobante desde el detalle de tu pedido.',
        'hecho' => false,
        'actual'=> false,
      ],
      [
        'icon'  => '4',
        'bg'    => '#F3F4F6',
        'color' => '#9CA3AF',
        'titulo'=> $tipoEntrega === 'pickup' ? 'Recoger en bodega' : 'Entrega por repartidor',
        'desc'  => $tipoEntrega === 'pickup'
          ? 'Cuando el pedido esté listo, la empresa te indicará cuándo puedes pasar a recogerlo.'
          : 'Un repartidor llevará tu pedido a la dirección registrada. Subirá foto como confirmación.',
        'hecho' => false,
        'actual'=> false,
      ],
    ];
    ?>

    <?php foreach ($pasosFlujo as $i => $paso): ?>
    <div style="display:flex;gap:14px;<?= $i < count($pasosFlujo)-1 ? 'margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #F3F4F6' : '' ?>">
      <div style="flex-shrink:0;width:34px;height:34px;border-radius:50%;background:<?= $paso['bg'] ?>;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:<?= $paso['color'] ?>">
        <?= $paso['icon'] ?>
      </div>
      <div style="flex:1;padding-top:4px">
        <div style="font-weight:700;font-size:.875rem;color:<?= !empty($paso['actual']) ? '#1E40AF' : ($paso['hecho'] ? '#065F46' : '#374151') ?>">
          <?= $paso['titulo'] ?>
          <?php if (!empty($paso['actual'])): ?>
          <span style="background:#DBEAFE;color:#1D4ED8;font-size:.68rem;padding:2px 7px;border-radius:999px;margin-left:6px;font-weight:600">EN ESPERA</span>
          <?php endif; ?>
        </div>
        <div style="font-size:.8rem;color:#6B7280;margin-top:2px"><?= $paso['desc'] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pedidoId): ?>
  <div style="display:flex;flex-direction:column;gap:10px">
    <a href="<?= BASE_URL ?>pedido/detalle/<?= $pedidoId ?>"
       style="display:block;padding:12px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:700;font-size:.95rem;text-align:center">
      Ver detalle del pedido
    </a>
    <a href="<?= BASE_URL ?>carrito/index"
       style="display:block;padding:10px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem;text-align:center">
      Hacer otro pedido
    </a>
    <a href="<?= BASE_URL ?>pedido/index"
       style="display:block;padding:10px;color:#6B7280;text-decoration:none;font-size:.875rem;text-align:center">
      Ver historial de pedidos
    </a>
  </div>
  <?php endif; ?>
</div>
