<?php
/**
 * Layout standalone para reportes del Repartidor.
 * Se usa en lugar del layout de empresa porque el repartidor opera en UI móvil/dark.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reporte semanal — CarniHub Repartidor</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root { --color-primary: #C8102E; }
    * { box-sizing: border-box; }
    body { margin: 0; background: #F3F4F6; color: #111827; font-family: 'Inter', system-ui, sans-serif; }
    .topbar { background: #1F2937; color: #F9FAFB; padding: 14px 18px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 5; }
    .topbar .brand { font-weight: 800; font-size: .95rem; }
    .topbar .sub { font-size: .72rem; color: #9CA3AF; margin-top: 2px; }
    .topbar a { color: #F9FAFB; text-decoration: none; font-size: .82rem; padding: 6px 12px; background: rgba(255,255,255,.1); border-radius: 6px; }
    .container { max-width: 1100px; margin: 0 auto; padding: 18px; }
  </style>
</head>
<body>

<div class="topbar">
  <div>
    <div class="brand">CarniHub Repartidor</div>
    <div class="sub">Reporte semanal de rendimiento</div>
  </div>
  <a href="<?= BASE_URL ?>repartidor/inicio">← Volver</a>
</div>

<div class="container">
  <?php if (!empty($flash)): ?>
  <div style="padding:12px;border-radius:8px;margin-bottom:12px;background:<?= $flash['type']==='error' ? '#FEE2E2;color:#991B1B' : '#D1FAE5;color:#065F46' ?>">
    <?= htmlspecialchars($flash['message']) ?>
  </div>
  <?php endif; ?>

  <?php require ROOT_PATH . '/app/views/reportes/tecnico.php'; ?>
</div>

</body>
</html>
