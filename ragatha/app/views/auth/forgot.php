<?php
$_cfgForgot = new ConfigModel();
$_appLogo   = $_cfgForgot->get('app_logo', '');
$_appName   = $_cfgForgot->get('app_name', APP_NAME);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($_appName) ?> — Recuperar contraseña</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/css/carnihub.css">
  <style>
    @keyframes loginCardIn {
      from { opacity: 0; transform: translateY(28px) scale(.98); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes glowPulse {
      0%, 100% { opacity: .30; transform: scale(1); }
      50%       { opacity: .55; transform: scale(1.10); }
    }

    .login-bg {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Inter', sans-serif;
      background:
        radial-gradient(ellipse 65% 55% at 25% 65%, rgba(200,16,46,.07) 0%, transparent 70%),
        radial-gradient(ellipse 45% 40% at 80% 20%, rgba(45,49,57,.10) 0%, transparent 70%),
        linear-gradient(155deg, #EAECEF 0%, #F0F1F3 55%, #E6E9EE 100%);
      padding: 24px 16px;
    }

    .login-card-wrap {
      display: flex;
      width: 100%;
      max-width: 1000px;
      min-height: 560px;
      border-radius: 24px;
      overflow: hidden;
      box-shadow:
        0 4px 6px rgba(0,0,0,.04),
        0 25px 50px rgba(0,0,0,.15),
        0 60px 120px rgba(0,0,0,.10);
      animation: loginCardIn .65s cubic-bezier(.22,1,.36,1) both;
    }

    .login-left {
      flex: 1;
      position: relative;
      overflow: hidden;
      background: linear-gradient(150deg, #1A1D23 0%, #23272F 50%, #2D3139 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      color: #fff;
    }
    .login-left::before {
      content: '';
      position: absolute; inset: 0;
      background-image: radial-gradient(circle, rgba(255,255,255,.11) 1px, transparent 1px);
      background-size: 22px 22px;
      pointer-events: none;
      z-index: 0;
    }
    .login-glow-top {
      position: absolute; top: -80px; right: -80px;
      width: 280px; height: 280px; border-radius: 50%;
      background: radial-gradient(circle, rgba(200,16,46,.40) 0%, transparent 70%);
      filter: blur(40px);
      animation: glowPulse 4s ease-in-out infinite;
      pointer-events: none; z-index: 0;
    }
    .login-glow-btm {
      position: absolute; bottom: -100px; left: -60px;
      width: 220px; height: 220px; border-radius: 50%;
      background: radial-gradient(circle, rgba(200,16,46,.18) 0%, transparent 70%);
      filter: blur(55px);
      animation: glowPulse 4s ease-in-out infinite reverse;
      pointer-events: none; z-index: 0;
    }
    .login-accent-bar {
      width: 44px; height: 4px; border-radius: 2px;
      background: linear-gradient(90deg, #C8102E, #FF2E52);
      margin-bottom: 18px;
      box-shadow: 0 2px 12px rgba(200,16,46,.5);
    }
    .login-tagline {
      position: absolute; bottom: 22px; left: 0; right: 0;
      text-align: center; font-size: .67rem;
      color: rgba(255,255,255,.22);
      letter-spacing: .08em; text-transform: uppercase; z-index: 1;
    }

    .login-right {
      flex: 1;
      background: #F8F9FB;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 52px 44px;
    }

    .input-wrap { position: relative; margin-bottom: 16px; }
    .input-icon-left {
      position: absolute; left: 13px; top: 50%;
      transform: translateY(-50%);
      color: #9CA3AF; pointer-events: none; display: flex;
      transition: color .2s;
    }
    .input-wrap:focus-within .input-icon-left { color: #C8102E; }
    .input-login {
      width: 100%; padding: 11px 44px;
      border: 1.5px solid #E2E5EB; border-radius: 10px;
      font-size: .875rem; color: #111827; background: #fff;
      outline: none; font-family: 'Inter', sans-serif;
      transition: border-color .2s, box-shadow .2s;
      box-sizing: border-box;
    }
    .input-login:focus {
      border-color: #C8102E;
      box-shadow: 0 0 0 3px rgba(200,16,46,.11);
    }
    .input-login::placeholder { color: #BFC4CE; }

    .btn-login-submit {
      width: 100%; padding: 13px; border: none; border-radius: 10px;
      font-size: .9375rem; font-weight: 700; font-family: 'Inter', sans-serif;
      color: #fff;
      background: linear-gradient(135deg, #C8102E 0%, #A00D24 100%);
      cursor: pointer; box-shadow: 0 4px 16px rgba(200,16,46,.38);
      transition: transform .2s, box-shadow .2s; letter-spacing: .015em;
    }
    .btn-login-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 28px rgba(200,16,46,.48); }
    .btn-login-submit:active { transform: translateY(0); box-shadow: 0 3px 10px rgba(200,16,46,.28); }

    .flash-box {
      display: flex; align-items: flex-start; gap: 10px;
      padding: 13px 14px; border-radius: 10px; margin-bottom: 20px;
      font-size: .85rem; line-height: 1.5;
    }
    .flash-box.is-error  { background:#FEE2E2; border-left:4px solid #C8102E; color:#7F1D1D; }
    .flash-box.is-success { background:#D1FAE5; border-left:4px solid #10B981; color:#064E3B; }
    .flash-box svg { flex-shrink: 0; margin-top: 1px; }

    .back-link {
      display: flex; align-items: center; gap: 6px;
      margin-top: 20px; text-align: center; justify-content: center;
      font-size: .8rem; color: #6B7280; text-decoration: none;
      transition: color .15s;
    }
    .back-link:hover { color: #374151; }

    @media (max-width: 768px) {
      .login-card-wrap { border-radius: 16px; min-height: auto; flex-direction: column; }
      .login-right { padding: 36px 24px; }
    }
  </style>
</head>
<body class="login-bg">

<div class="login-card-wrap">

  <!-- Panel izquierdo — branding -->
  <div class="login-left hide-mobile">
    <div class="login-glow-top"></div>
    <div class="login-glow-btm"></div>

    <div style="position:relative;z-index:1;width:100%;display:flex;flex-direction:column;align-items:center">
      <?php if ($_appLogo): ?>
        <img src="<?= htmlspecialchars($_appLogo) ?>" alt="<?= htmlspecialchars($_appName) ?>"
             style="height:60px;margin-bottom:20px;object-fit:contain;filter:brightness(0) invert(1)">
      <?php else: ?>
        <img src="<?= BASE_URL ?>public/img/logo.svg" alt="<?= htmlspecialchars($_appName) ?>"
             style="height:60px;margin-bottom:20px;filter:brightness(0) invert(1)">
      <?php endif; ?>

      <div class="login-accent-bar"></div>

      <h2 style="font-size:1.45rem;font-weight:800;margin-bottom:10px;text-align:center;line-height:1.25">
        Recupera tu acceso
      </h2>
      <p style="color:#94A3B8;text-align:center;font-size:.84rem;line-height:1.65;max-width:240px;margin:0 0 32px">
        Te enviaremos un link seguro a tu correo para que puedas crear una nueva contraseña.
      </p>

      <!-- Steps -->
      <div style="display:flex;flex-direction:column;gap:18px;width:100%;position:relative;z-index:1">
        <?php
          $steps = [
            ['01', 'Ingresa tu correo electrónico'],
            ['02', 'Revisa tu bandeja de entrada'],
            ['03', 'Haz clic en el link y crea tu nueva contraseña'],
          ];
          foreach ($steps as [$num, $text]): ?>
        <div style="display:flex;align-items:center;gap:14px">
          <div style="width:32px;height:32px;border-radius:50%;background:rgba(200,16,46,.25);border:1px solid rgba(200,16,46,.5);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.7rem;font-weight:800;color:#FF6B6B">
            <?= $num ?>
          </div>
          <span style="font-size:.84rem;color:#CBD5E1"><?= $text ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="login-tagline">Plataforma líder en abasto cárnico B2B</div>
  </div>

  <!-- Panel derecho — formulario -->
  <div class="login-right">
    <div style="width:100%;max-width:380px">

      <div style="text-align:center;margin-bottom:24px" class="hide-desktop">
        <?php if ($_appLogo): ?>
          <img src="<?= htmlspecialchars($_appLogo) ?>" alt="<?= htmlspecialchars($_appName) ?>"
               style="height:44px;margin-bottom:14px;object-fit:contain">
        <?php else: ?>
          <img src="<?= BASE_URL ?>public/img/logo.svg" alt="<?= htmlspecialchars($_appName) ?>"
               style="height:44px;margin-bottom:14px">
        <?php endif; ?>
      </div>

      <!-- Ícono de llave -->
      <div style="width:56px;height:56px;border-radius:14px;background:rgba(200,16,46,.08);border:1.5px solid rgba(200,16,46,.15);display:flex;align-items:center;justify-content:center;margin-bottom:20px">
        <svg width="26" height="26" fill="none" viewBox="0 0 24 24" stroke="#C8102E" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
        </svg>
      </div>

      <div style="margin-bottom:28px">
        <h1 style="font-size:1.5rem;font-weight:800;color:#111827;margin:0 0 6px">¿Olvidaste tu contraseña?</h1>
        <p style="color:#6B7280;font-size:.875rem;margin:0;line-height:1.5">
          Ingresa tu correo y te enviaremos un link para restablecerla.
        </p>
      </div>

      <?php if (!empty($flash)): ?>
      <div class="flash-box <?= $flash['type'] === 'error' ? 'is-error' : 'is-success' ?>">
        <?php if ($flash['type'] === 'error'): ?>
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        <?php else: ?>
          <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        <?php endif; ?>
        <span><?= htmlspecialchars($flash['message']) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>auth/sendReset">
        <div>
          <label class="form-label" style="display:block;margin-bottom:6px">Correo electrónico</label>
          <div class="input-wrap">
            <span class="input-icon-left">
              <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </span>
            <input type="email" name="email" class="input-login"
                   placeholder="ejemplo@empresa.com" required autocomplete="email">
          </div>
        </div>

        <button type="submit" class="btn-login-submit">Enviar link de recuperación</button>
      </form>

      <a href="<?= BASE_URL ?>auth/login" class="back-link">
        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
        </svg>
        Volver al inicio de sesión
      </a>

    </div>
  </div>
</div>

</body>
</html>
