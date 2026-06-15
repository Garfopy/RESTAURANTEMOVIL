<?php
// Vista: Mis sucursales — listado del comprador
$suscripcion   = $suscripcion ?? [];
$maxSucursales = $maxSucursales ?? 3;
$usadas        = $usadas ?? 0;
$ilimitado     = $maxSucursales <= 0;
$porcentaje    = $ilimitado ? 0 : min(100, round($usadas / $maxSucursales * 100));
?>

<!-- Header -->
<div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;flex-wrap:wrap;gap:12px">
  <div>
    <h2 style="font-size:1.1rem;font-weight:700;color:#111827;margin-bottom:4px">Mis sucursales</h2>
    <p style="font-size:.8rem;color:#6B7280">Puntos de entrega donde recibes tus pedidos.</p>
  </div>
  <?php if ($ilimitado || $usadas < $maxSucursales): ?>
  <a href="<?= BASE_URL ?>comprador-sucursal/nueva"
     style="padding:9px 18px;background:var(--color-primary);color:#fff;border-radius:8px;font-size:.875rem;font-weight:600;text-decoration:none">
    + Nueva sucursal
  </a>
  <?php endif; ?>
</div>

<!-- Barra de uso del plan -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:16px 20px;margin-bottom:20px">
  <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
    <span style="font-size:.8rem;font-weight:600;color:#374151">Sucursales usadas</span>
    <span style="font-size:.8rem;font-weight:700;color:<?= ($porcentaje >= 100 && !$ilimitado) ? '#DC2626' : '#111827' ?>">
      <?= $usadas ?> / <?= $ilimitado ? '∞' : $maxSucursales ?>
    </span>
  </div>
  <?php if (!$ilimitado): ?>
  <div style="background:#F3F4F6;border-radius:999px;height:6px;overflow:hidden">
    <div style="height:6px;border-radius:999px;background:<?= $porcentaje >= 100 ? '#DC2626' : 'var(--color-primary)' ?>;width:<?= $porcentaje ?>%;transition:width .3s"></div>
  </div>
  <?php if ($porcentaje >= 100): ?>
  <p style="font-size:.75rem;color:#DC2626;margin-top:6px">Límite alcanzado. Pide a tu proveedor actualizar el plan para agregar más sucursales.</p>
  <?php endif; ?>
  <?php else: ?>
  <p style="font-size:.75rem;color:#059669;margin-top:4px">Tu plan permite sucursales ilimitadas.</p>
  <?php endif; ?>
</div>

<?php if (empty($sucursales)): ?>
<div style="background:#fff;border:2px dashed #E5E7EB;border-radius:12px;padding:48px;text-align:center">
  <div style="font-size:2.5rem;margin-bottom:12px">📍</div>
  <div style="font-size:1rem;font-weight:600;color:#374151;margin-bottom:6px">Sin sucursales registradas</div>
  <div style="font-size:.875rem;color:#6B7280;margin-bottom:20px">Agrega tus puntos de entrega para distribuir pedidos entre ellos.</div>
  <?php if ($ilimitado || $usadas < $maxSucursales): ?>
  <a href="<?= BASE_URL ?>comprador-sucursal/nueva"
     style="display:inline-block;padding:10px 24px;background:var(--color-primary);color:#fff;border-radius:8px;font-weight:600;text-decoration:none;font-size:.875rem">
    Agregar primera sucursal
  </a>
  <?php endif; ?>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px">
  <?php foreach ($sucursales as $s): ?>
  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:16px;display:flex;flex-direction:column;gap:10px">

    <!-- Header sucursal -->
    <div style="display:flex;justify-content:space-between;align-items:flex-start">
      <div>
        <div style="font-size:.95rem;font-weight:700;color:#111827"><?= htmlspecialchars($s['nombre']) ?></div>
        <?php if (!empty($s['responsable'])): ?>
        <div style="font-size:.78rem;color:#6B7280;margin-top:2px">Responsable: <?= htmlspecialchars($s['responsable']) ?></div>
        <?php endif; ?>
      </div>
      <span style="font-size:.7rem;padding:2px 8px;border-radius:999px;background:<?= $s['activo'] ? '#D1FAE5' : '#F3F4F6' ?>;color:<?= $s['activo'] ? '#065F46' : '#6B7280' ?>;font-weight:600">
        <?= $s['activo'] ? 'Activa' : 'Inactiva' ?>
      </span>
    </div>

    <!-- Dirección -->
    <div style="font-size:.82rem;color:#374151;line-height:1.4">
      📍 <?= htmlspecialchars($s['direccion']) ?>
    </div>

    <!-- Coordenadas / mapa -->
    <?php if (!empty($s['lat']) && !empty($s['lng'])): ?>
    <div style="border-radius:8px;overflow:hidden;height:140px;background:#F3F4F6;position:relative">
      <?php if (!empty($gmKey)): ?>
      <iframe
        style="width:100%;height:100%;border:0;display:block"
        loading="lazy"
        allowfullscreen
        referrerpolicy="no-referrer-when-downgrade"
        src="https://www.google.com/maps/embed/v1/place?key=<?= htmlspecialchars($gmKey) ?>&q=<?= (float)$s['lat'] ?>,<?= (float)$s['lng'] ?>&zoom=16">
      </iframe>
      <?php endif; ?>
      <!-- Botón "Ver en Maps" flotante sobre el mapa -->
      <a href="https://maps.google.com/?q=<?= (float)$s['lat'] ?>,<?= (float)$s['lng'] ?>" target="_blank" rel="noopener"
         style="position:absolute;top:8px;right:8px;background:#fff;border:1px solid #D1D5DB;border-radius:6px;padding:4px 10px;font-size:.72rem;font-weight:700;color:#374151;text-decoration:none;box-shadow:0 1px 4px rgba(0,0,0,.15)">
        Maps ↗
      </a>
    </div>
    <?php elseif (!empty($s['direccion'])): ?>
    <!-- Sin coords: link de texto a búsqueda por dirección -->
    <a href="https://maps.google.com/?q=<?= urlencode($s['direccion']) ?>" target="_blank" rel="noopener"
       style="display:flex;align-items:center;justify-content:center;height:48px;background:#F9FAFB;border:1px dashed #D1D5DB;border-radius:8px;font-size:.8rem;color:var(--color-primary);text-decoration:none;gap:4px">
      📍 Ver en Google Maps
    </a>
    <?php endif; ?>

    <?php if (!empty($s['telefono'])): ?>
    <div style="font-size:.78rem;color:#6B7280">📞 <?= htmlspecialchars($s['telefono']) ?></div>
    <?php endif; ?>

    <!-- Acciones -->
    <div style="display:flex;gap:8px;padding-top:4px;border-top:1px solid #F3F4F6">
      <a href="<?= BASE_URL ?>comprador-sucursal/editar/<?= $s['id'] ?>"
         style="flex:1;padding:7px;text-align:center;border:1px solid #D1D5DB;border-radius:7px;font-size:.8rem;font-weight:600;color:#374151;text-decoration:none">
        Editar
      </a>
      <a href="<?= BASE_URL ?>comprador-sucursal/toggleActivo/<?= $s['id'] ?>"
         onclick="return confirm('<?= $s['activo'] ? '¿Desactivar esta sucursal?' : '¿Activar esta sucursal?' ?>')"
         style="flex:1;padding:7px;text-align:center;border:1px solid #D1D5DB;border-radius:7px;font-size:.8rem;font-weight:600;color:<?= $s['activo'] ? '#DC2626' : '#059669' ?>;text-decoration:none">
        <?= $s['activo'] ? 'Desactivar' : 'Activar' ?>
      </a>
      <a href="<?= BASE_URL ?>comprador-sucursal/eliminar/<?= $s['id'] ?>"
         onclick="return confirm('¿Eliminar esta sucursal permanentemente? Esta acción no se puede deshacer.')"
         style="padding:7px 10px;text-align:center;border:1px solid #FCA5A5;border-radius:7px;font-size:.8rem;font-weight:600;color:#DC2626;text-decoration:none;background:#FFF5F5">
        Eliminar
      </a>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
