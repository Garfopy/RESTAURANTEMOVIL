<?php ob_start(); ?>
<?php
// Onboarding banner — checklist primera vez
$pasos = [
  ['ok' => !empty($restaurante['telefono']) && !empty($restaurante['direccion']),
   'label' => 'Completa la información del restaurante', 'url' => 'rest-config/index'],
  ['ok' => (int)($restaurante['total_mesas'] ?? 0) > 0,
   'label' => 'Crea al menos una mesa o silla',           'url' => 'rest-mesa/index'],
  ['ok' => (int)($restaurante['total_platillos'] ?? 0) > 0,
   'label' => 'Agrega platillos al menú',                  'url' => 'rest-menu/index'],
  ['ok' => (int)($restaurante['total_staff'] ?? 0) > 0,
   'label' => 'Invita a tu staff (mesero, chef, portero)', 'url' => 'rest-staff/index'],
];
$completados = count(array_filter($pasos, fn($p) => $p['ok']));
$totalPasos  = count($pasos);
?>
<?php if ($completados < $totalPasos): ?>
<div style="background:linear-gradient(135deg,#FEF3C7 0%,#FFFBEB 100%);border:1px solid #FDE68A;
            border-radius:14px;padding:20px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:8px">
    <div>
      <div style="font-weight:700;color:#92400E;font-size:1rem">🚀 Configura tu restaurante</div>
      <div style="font-size:.82rem;color:#78350F;margin-top:2px">
        Te faltan <strong><?= $totalPasos - $completados ?> paso<?= ($totalPasos-$completados)!==1?'s':'' ?></strong> para empezar a operar.
      </div>
    </div>
    <div style="font-size:.82rem;color:#92400E;font-weight:600">
      <?= $completados ?>/<?= $totalPasos ?>
    </div>
  </div>
  <div style="background:#FDE68A;height:6px;border-radius:3px;overflow:hidden;margin-bottom:14px">
    <div style="background:#F59E0B;height:100%;width:<?= ($completados/$totalPasos)*100 ?>%;transition:.3s"></div>
  </div>
  <div style="display:grid;gap:6px">
    <?php foreach ($pasos as $p): ?>
    <a href="<?= BASE_URL . $p['url'] ?>" style="display:flex;align-items:center;gap:10px;
            padding:8px 12px;border-radius:8px;text-decoration:none;
            background:<?= $p['ok'] ? '#D1FAE5' : '#fff' ?>;
            border:1px solid <?= $p['ok'] ? '#A7F3D0' : '#FDE68A' ?>;transition:.15s"
            onmouseover="this.style.transform='translateX(2px)'"
            onmouseout="this.style.transform=''">
      <span style="font-size:1rem"><?= $p['ok'] ? '✅' : '⏳' ?></span>
      <span style="flex:1;font-size:.85rem;color:<?= $p['ok'] ? '#065F46' : '#78350F' ?>;font-weight:500">
        <?= htmlspecialchars($p['label']) ?>
      </span>
      <?php if (!$p['ok']): ?>
      <span style="font-size:.78rem;color:#92400E;font-weight:600">Configurar →</span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- KPI Cards -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;margin-bottom:24px">
  <?php
  $gastosTotales = (float)($kpis['gastos'] ?? 0) + (float)($kpis['retiros'] ?? 0);
  $cards = [
    ['label'=>'Ingresos del mes', 'val'=>'$'.number_format($kpis['ingresos'],2), 'color'=>'#10B981'],
    ['label'=>'Gastos del mes',   'val'=>'$'.number_format($gastosTotales,2),   'color'=>'#EF4444',
     'hint'=>'Incluye $'.number_format($kpis['gastos'] ?? 0, 2).' en gastos y $'.number_format($kpis['retiros'] ?? 0, 2).' en retiros.'],
    ['label'=>'Utilidad neta',    'val'=>'$'.number_format($kpis['utilidad'],2),  'color'=>'#6366F1'],
    ['label'=>'Margen',           'val'=>$kpis['margen'].'%',                    'color'=>'#F59E0B'],
    ['label'=>'Ticket promedio',  'val'=>'$'.number_format($kpis['ticketPromedio'],2), 'color'=>'#0F766E'],
  ];
  foreach ($cards as $c): ?>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB"
       <?= !empty($c['hint']) ? 'title="'.htmlspecialchars($c['hint']).'"' : '' ?>>
    <div style="font-size:.8rem;color:#6B7280;margin-bottom:6px"><?= $c['label'] ?></div>
    <div style="font-size:1.5rem;font-weight:700;color:<?= $c['color'] ?>"><?= htmlspecialchars($c['val']) ?></div>
    <?php if (!empty($c['hint'])): ?>
    <div style="font-size:.68rem;color:#9CA3AF;margin-top:4px">Incluye gastos + retiros</div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px">
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Mesas activas</div>
    <div style="font-size:1.4rem;font-weight:700;color:#111827"><?= (int)($restaurante['mesas_ocupadas'] ?? 0) ?> / <?= (int)($restaurante['total_mesas'] ?? 0) ?></div>
  </div>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Pedidos activos</div>
    <div style="font-size:1.4rem;font-weight:700;color:#F59E0B"><?= count($activos) ?></div>
  </div>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Alertas inventario</div>
    <div style="font-size:1.4rem;font-weight:700;color:<?= count($alertas) > 0 ? '#EF4444' : '#10B981' ?>"><?= count($alertas) ?></div>
  </div>
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-size:.8rem;color:#6B7280">Pendiente por cobrar</div>
    <div style="font-size:1.4rem;font-weight:700;color:#EF4444">$<?= number_format($kpis['pendiente'],2) ?></div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
  <!-- Pedidos activos -->
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-weight:600;margin-bottom:14px">Pedidos en cocina</div>
    <?php if (empty($activos)): ?>
    <p style="color:#9CA3AF;font-size:.875rem">No hay pedidos activos.</p>
    <?php else: ?>
    <?php foreach (array_slice($activos, 0, 10) as $item): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #F3F4F6;font-size:.85rem">
      <span style="color:#374151"><strong><?= htmlspecialchars($item['folio'] ?? '') ?></strong> — <?= htmlspecialchars($item['mesa_nombre'] ?? '—') ?></span>
      <span style="padding:2px 8px;border-radius:99px;font-size:.75rem;font-weight:500;
        background:<?= $item['item_estado']==='en_preparacion' ? '#FEF3C7' : '#DBEAFE' ?>;
        color:<?= $item['item_estado']==='en_preparacion' ? '#92400E' : '#1E40AF' ?>">
        <?= htmlspecialchars($item['platillo_nombre']) ?>
      </span>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <!-- Próximas reservas -->
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="font-weight:600;margin-bottom:14px">Próximas reservaciones</div>
    <?php if (empty($proximas)): ?>
    <p style="color:#9CA3AF;font-size:.875rem">Sin reservaciones próximas.</p>
    <?php else: ?>
    <?php foreach ($proximas as $r): ?>
    <div style="padding:8px 0;border-bottom:1px solid #F3F4F6;font-size:.85rem">
      <div style="font-weight:500"><?= htmlspecialchars($r['nombre']) ?> — <?= $r['personas'] ?> personas</div>
      <div style="color:#6B7280"><?= date('d/m H:i', strtotime($r['fecha'].' '.$r['hora'])) ?> <?= $r['mesa_nombre'] ? '· '.$r['mesa_nombre'] : '' ?></div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<!-- Productos: más / menos vendidos -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:20px">
  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-weight:700;color:#111827">🔥 Más vendidos</div>
      <span style="font-size:.7rem;color:#9CA3AF">últimos 365 días</span>
    </div>
    <?php if (empty($topVendidos)): ?>
    <p style="color:#9CA3AF;font-size:.875rem;margin:0">Aún no hay ventas registradas.</p>
    <?php else: ?>
    <?php foreach ($topVendidos as $i => $p): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;
                border-bottom:1px solid #F3F4F6;font-size:.88rem;gap:10px">
      <div style="display:flex;align-items:center;gap:10px;min-width:0;flex:1">
        <span style="display:inline-flex;align-items:center;justify-content:center;
                     width:24px;height:24px;border-radius:8px;
                     background:<?= $i===0?'#FEF3C7':'#F3F4F6' ?>;
                     color:<?= $i===0?'#92400E':'#6B7280' ?>;
                     font-weight:800;font-size:.72rem"><?= $i+1 ?></span>
        <span style="color:#111827;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['nombre']) ?></span>
      </div>
      <div style="display:flex;align-items:center;gap:10px;white-space:nowrap">
        <span style="color:#10B981;font-weight:700">$<?= number_format((float)$p['precio'],2) ?></span>
        <span style="font-size:.72rem;color:#6B7280;background:#F3F4F6;border-radius:99px;padding:2px 8px;font-weight:600">
          <?= (int)$p['unidades_vendidas'] ?> vend.
        </span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div style="background:#fff;border-radius:12px;padding:20px;border:1px solid #E5E7EB">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
      <div style="font-weight:700;color:#111827">📉 Menos vendidos</div>
      <span style="font-size:.7rem;color:#9CA3AF">candidatos a oferta</span>
    </div>
    <?php if (empty($menosVendidos)): ?>
    <p style="color:#9CA3AF;font-size:.875rem;margin:0">Sin platillos activos.</p>
    <?php else: ?>
    <?php foreach ($menosVendidos as $p): ?>
    <div style="display:flex;justify-content:space-between;align-items:center;padding:9px 0;
                border-bottom:1px solid #F3F4F6;font-size:.88rem;gap:10px">
      <div style="display:flex;align-items:center;gap:8px;min-width:0;flex:1">
        <span style="color:#374151;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= htmlspecialchars($p['nombre']) ?></span>
        <?php if ((int)$p['unidades_vendidas'] === 0): ?>
        <span style="font-size:.65rem;color:#92400E;background:#FEF3C7;border:1px solid #FCD34D;border-radius:99px;padding:1px 7px;font-weight:700">sin ventas</span>
        <?php endif; ?>
      </div>
      <div style="display:flex;align-items:center;gap:10px;white-space:nowrap">
        <span style="color:#EF4444;font-weight:700">$<?= number_format((float)$p['precio'],2) ?></span>
        <span style="font-size:.72rem;color:#6B7280;background:#F3F4F6;border-radius:99px;padding:2px 8px;font-weight:600">
          <?= (int)$p['unidades_vendidas'] ?> vend.
        </span>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
function navegarCopiar(url, btn) {
  navigator.clipboard.writeText(url).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ Copiado';
    setTimeout(() => { btn.textContent = orig; }, 1500);
  });
}
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
