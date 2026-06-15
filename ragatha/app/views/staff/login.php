<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Acceso') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/css/restaurant.css">
</head>
<body>
<?php $returnParam = $returnParam ?? ''; ?>
<div class="staff-login-wrap">
  <div class="staff-login-card">

    <!-- Logo/marca del restaurante -->
    <div style="text-align:center;margin-bottom:22px">
      <?php if (!empty($restaurante['logo'])): ?>
      <img src="<?= BASE_URL . htmlspecialchars($restaurante['logo']) ?>" alt="Logo"
           style="height:52px;object-fit:contain;margin-bottom:10px;display:block;margin-inline:auto">
      <?php else: ?>
      <div style="width:52px;height:52px;border-radius:14px;background:var(--cp, #C8102E);
                  display:flex;align-items:center;justify-content:center;
                  font-size:1.5rem;font-weight:800;color:#fff;margin:0 auto 10px">
        <?= strtoupper(mb_substr($restaurante['nombre'] ?? 'R', 0, 1)) ?>
      </div>
      <?php endif; ?>
      <div style="font-weight:700;font-size:1.1rem;color:#111827">
        <?= htmlspecialchars($restaurante['nombre'] ?? 'CarniHub') ?>
      </div>
      <div style="font-size:.78rem;color:#9CA3AF;margin-top:3px">Identifícate para ordenar</div>
    </div>

    <!-- Flash -->
    <?php if (!empty($flash)): ?>
    <div class="flash flash-<?= $flash['type'] === 'success' ? 'success' : 'error' ?>"
         style="position:static;max-width:none;margin-bottom:16px;animation:none">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if (!$restaurante): ?>
    <div class="flash flash-error" style="position:static;max-width:none;animation:none">
      Restaurante no encontrado. Verifica la URL con tu administrador.
    </div>
    <?php else: ?>

    <p style="font-size:.82rem;color:#6B7280;margin-bottom:18px;line-height:1.5">
      Ingresa tu nombre y correo para identificarte. Así guardamos tu historial y pedidos.
    </p>
    <form method="POST" action="<?= BASE_URL ?>acceso/<?= htmlspecialchars($slug ?? '') ?>/entrarComensal" autocomplete="on">
      <?php if ($returnParam): ?>
      <input type="hidden" name="return_url" value="<?= htmlspecialchars($returnParam) ?>">
      <?php endif; ?>
      <div class="form-group">
        <label class="form-label">Tu nombre *</label>
        <input type="text" name="nombre" class="form-input" placeholder="Ej: María López"
               required autocomplete="name" style="font-size:1rem">
      </div>
      <div class="form-group" style="margin-bottom:20px">
        <label class="form-label">Correo electrónico *</label>
        <input type="email" name="email" class="form-input" placeholder="tucorreo@ejemplo.com"
               required autocomplete="email" style="font-size:1rem">
      </div>
      <button type="submit" class="btn btn-primary btn-lg"
              style="width:100%;justify-content:center;border-radius:10px">
        Entrar al menú
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
        </svg>
      </button>
    </form>
    <div style="margin-top:14px;padding:10px 12px;background:#F9FAFB;border-radius:8px;font-size:.75rem;color:#9CA3AF;line-height:1.4">
      No usamos contraseña. Tu correo y nombre solo se guardan en este restaurante para tu historial de pedidos.
    </div>

    <?php endif; ?>

    <div style="text-align:center;margin-top:20px;font-size:.75rem;color:#9CA3AF">
      Potenciado por <strong>CarniHub</strong>
    </div>
  </div>
</div>
</body>
</html>
