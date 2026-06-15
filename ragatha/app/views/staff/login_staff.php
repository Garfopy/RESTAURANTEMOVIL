<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle ?? 'Acceso Staff') ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/css/restaurant.css">
  <style>
    :root {
      --cp: <?= htmlspecialchars($restaurante['color_primario']  ?? '#C8102E') ?>;
      --cs: <?= htmlspecialchars($restaurante['color_secundario'] ?? '#1f2937') ?>;
    }
  </style>
</head>
<body>
<div class="staff-login-wrap">
  <div class="staff-login-card">

    <!-- Logo/marca del restaurante -->
    <div style="text-align:center;margin-bottom:22px">
      <?php if (!empty($restaurante['logo'])): ?>
      <img src="<?= BASE_URL . htmlspecialchars($restaurante['logo']) ?>" alt="Logo"
           style="height:52px;object-fit:contain;margin-bottom:10px;display:block;margin-inline:auto">
      <?php else: ?>
      <div style="width:52px;height:52px;border-radius:14px;background:var(--cp);
                  display:flex;align-items:center;justify-content:center;
                  font-size:1.5rem;font-weight:800;color:#fff;margin:0 auto 10px">
        <?= strtoupper(mb_substr($restaurante['nombre'] ?? 'R', 0, 1)) ?>
      </div>
      <?php endif; ?>
      <div style="font-weight:700;font-size:1.1rem;color:#111827">
        <?= htmlspecialchars($restaurante['nombre'] ?? 'CarniHub') ?>
      </div>
      <div style="font-size:.78rem;color:#9CA3AF;margin-top:3px">Portal de acceso staff</div>
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

    <form method="POST" action="<?= BASE_URL ?>acceso/<?= htmlspecialchars($slug ?? '') ?>/login" autocomplete="on">
      <div class="form-group">
        <label class="form-label">Correo electrónico</label>
        <input type="email" name="email" class="form-input" placeholder="tu@email.com"
               required autocomplete="email">
      </div>
      <div class="form-group" style="margin-bottom:20px">
        <label class="form-label">Contraseña</label>
        <div style="position:relative">
          <input type="password" name="password" id="pwdInput" class="form-input"
                 placeholder="••••••••" required autocomplete="current-password"
                 style="padding-right:42px">
          <button type="button" onclick="togglePwd()"
                  style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                         background:none;border:none;cursor:pointer;color:#9CA3AF;padding:4px">
            <svg id="eyeIcon" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg"
              style="width:100%;justify-content:center;border-radius:10px">
        Entrar
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
        </svg>
      </button>
    </form>
    <a href="<?= BASE_URL ?>menu/<?= htmlspecialchars($slug ?? '') ?>"
       style="display:block;text-align:center;margin-top:14px;font-size:.78rem;color:#9CA3AF;text-decoration:none">
      Ver el menú sin iniciar sesión →
    </a>

    <?php endif; ?>

    <div style="text-align:center;margin-top:20px;font-size:.75rem;color:#9CA3AF">
      Potenciado por <strong>CarniHub</strong>
    </div>
  </div>
</div>

<script>
function togglePwd() {
  const inp = document.getElementById('pwdInput');
  if (inp) inp.type = inp.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
