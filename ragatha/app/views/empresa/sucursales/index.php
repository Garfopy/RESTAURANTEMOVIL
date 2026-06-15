<?php
// Vista: Sucursales de compradores
$totalSucursales = count($sucursales);
$activas   = count(array_filter($sucursales, fn($s) => $s['activo']));
$inactivas = $totalSucursales - $activas;
$totalCompradores = count($porComprador) - (isset($porComprador[0]) ? 1 : 0);
?>

<!-- Guía para el admin -->
<div style="background:#F0FDF4;border:1px solid #A7F3D0;border-radius:10px;padding:14px 16px;margin-bottom:20px;display:flex;gap:12px;align-items:flex-start">
  <span style="font-size:1.2rem;flex-shrink:0">💡</span>
  <div>
    <div style="font-size:.82rem;font-weight:700;color:#065F46;margin-bottom:2px">Sucursales de entrega</div>
    <div style="font-size:.78rem;color:#047857">Las sucursales las registran tus compradores desde su perfil. Aquí puedes ver todas sus ubicaciones de entrega agrupadas por comprador. Para ver o editar el perfil de un comprador, ve a <strong>Mi equipo</strong>.</div>
  </div>
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <p style="font-size:.875rem;color:#6B7280">Todas las sucursales registradas por tus compradores.</p>
  </div>
</div>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:20px">
  <?php foreach ([['Total sucursales',$totalSucursales,'#1E40AF','#DBEAFE'],['Activas',$activas,'#065F46','#D1FAE5'],['Inactivas',$inactivas,'#92400E','#FEF3C7']] as [$lbl,$val,$c,$bg]): ?>
  <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:16px;text-align:center">
    <div style="font-size:1.6rem;font-weight:800;color:<?= $c ?>"><?= $val ?></div>
    <div style="font-size:.78rem;color:#6B7280;font-weight:600;margin-top:2px"><?= $lbl ?></div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (empty($sucursales)): ?>
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:48px;text-align:center;color:#9CA3AF">
  <div style="font-size:2.5rem;margin-bottom:12px">🏪</div>
  <p style="font-weight:600;font-size:.95rem">No hay sucursales registradas</p>
  <p style="font-size:.85rem;margin-top:4px">Las sucursales aparecen aquí cuando tus compradores las agregan a su perfil.</p>
</div>
<?php else: ?>

<?php
// Iterar por comprador
foreach ($porComprador as $compradorId => $grupoSucursales):
  $primera = $grupoSucursales[0];
  $nombreComprador = $compradorId
    ? trim(($primera['comprador_nombre'] ?? '') . ' ' . ($primera['comprador_apellido'] ?? ''))
    : 'Sin comprador asignado';
?>
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px">

  <!-- Header comprador -->
  <div style="padding:14px 16px;border-bottom:1px solid #E5E7EB;display:flex;align-items:center;gap:10px;background:#F9FAFB">
    <div style="width:34px;height:34px;border-radius:50%;background:<?= $compradorId ? 'var(--color-primary)' : '#E5E7EB' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0">
      <?= $compradorId ? mb_strtoupper(mb_substr($primera['comprador_nombre'] ?? '?', 0, 1)) : '?' ?>
    </div>
    <div>
      <div style="font-weight:700;font-size:.9rem;color:#111827"><?= htmlspecialchars($nombreComprador) ?></div>
      <div style="font-size:.72rem;color:#6B7280"><?= count($grupoSucursales) ?> sucursal<?= count($grupoSucursales) !== 1 ? 'es' : '' ?></div>
    </div>
  </div>

  <!-- Sucursales del comprador -->
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:0;padding:0">
    <?php foreach ($grupoSucursales as $i => $s): ?>
    <div style="padding:16px;border-right:1px solid #F3F4F6;border-bottom:1px solid #F3F4F6;<?= !$s['activo'] ? 'opacity:.6' : '' ?>">

      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:10px">
        <div style="font-weight:700;font-size:.875rem;color:#111827;line-height:1.3"><?= htmlspecialchars($s['nombre']) ?></div>
        <span style="flex-shrink:0;font-size:.68rem;padding:2px 7px;border-radius:999px;font-weight:600;background:<?= $s['activo'] ? '#D1FAE5' : '#F3F4F6' ?>;color:<?= $s['activo'] ? '#065F46' : '#6B7280' ?>">
          <?= $s['activo'] ? 'Activa' : 'Inactiva' ?>
        </span>
      </div>

      <?php if ($s['direccion']): ?>
      <div style="font-size:.78rem;color:#6B7280;margin-bottom:6px;display:flex;gap:5px;align-items:flex-start">
        <span style="flex-shrink:0;margin-top:1px">📍</span>
        <span><?= htmlspecialchars($s['direccion']) ?></span>
      </div>
      <?php endif; ?>

      <?php if ($s['responsable']): ?>
      <div style="font-size:.78rem;color:#6B7280;margin-bottom:4px">
        👤 <?= htmlspecialchars($s['responsable']) ?>
      </div>
      <?php endif; ?>

      <?php if ($s['telefono']): ?>
      <div style="font-size:.78rem;color:#6B7280;margin-bottom:8px">
        📞 <?= htmlspecialchars($s['telefono']) ?>
      </div>
      <?php endif; ?>

      <?php if ($s['lat'] && $s['lng']): ?>
      <a href="https://www.google.com/maps?q=<?= (float)$s['lat'] ?>,<?= (float)$s['lng'] ?>" target="_blank"
         style="display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:600;color:var(--color-primary);text-decoration:none;padding:4px 8px;background:#EEF2FF;border-radius:6px">
        🗺 Ver en mapa
      </a>
      <?php else: ?>
      <span style="font-size:.72rem;color:#D1D5DB;font-style:italic">Sin ubicación GPS</span>
      <?php endif; ?>

    </div>
    <?php endforeach; ?>
  </div>

</div>
<?php endforeach; ?>

<?php endif; ?>
