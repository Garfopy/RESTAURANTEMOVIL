<?php
$pageTitle = 'Iniciar Sesión';
$_cfgLogin = new ConfigModel();
$_appLogo  = $_cfgLogin->get('app_logo', '');
$_appName  = $_cfgLogin->get('app_name', APP_NAME);
$_waNumero = $_cfgLogin->get('whatsapp_numero_contacto', '');
$_telefono = $_waNumero ?: $_cfgLogin->get('telefono_contacto', '');
$_waPhone  = preg_replace('/[^0-9]/', '', $_telefono);
$_isRest   = defined('RESTAURANTE_STANDALONE') && RESTAURANTE_STANDALONE;
if ($_isRest) { $_appName = 'Restaurante'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($_appName) ?> — Iniciar Sesión</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>public/css/carnihub.css">
  <style>
    :root {
      --gold-primary: #D4AF37;
      --gold-light: #FBE18D;
      --gold-dark: #AA771C;
      --black-bg: #050505;
      --panel-dark: #0A0A0A;
    }

    @keyframes textShimmer {
      0%   { background-position: 200% center; }
      100% { background-position: -200% center; }
    }
    .shimmer-heading {
      background: linear-gradient(
        90deg,
        var(--gold-primary) 30%,
        #FFFFFF 46%,
        var(--gold-light) 50%,
        #FFFFFF 54%,
        var(--gold-primary) 70%
      );
      background-size: 200% auto;
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      animation: textShimmer 4s linear infinite;
    }

    @keyframes loginCardIn {
      from { opacity: 0; transform: translateY(32px) scale(.97); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }
    @keyframes glowPulse {
      0%, 100% { opacity: .15; transform: scale(1); }
      50%      { opacity: .30; transform: scale(1.12); }
    }
    @keyframes bgShift {
      0%, 100% { background-position: 0% 50%; }
      50%      { background-position: 100% 50%; }
    }

    .login-bg {
      min-height: 100vh;
      display: flex; align-items: center; justify-content: center;
      font-family: 'Inter', sans-serif;
      background: var(--black-bg);
      position: relative;
      padding: 24px 16px;
      overflow: hidden;
    }
    /* Atmospheric blobs behind the card */
    .login-bg::before {
      content: '';
      position: fixed; inset: 0; pointer-events: none;
      background:
        radial-gradient(ellipse 55% 45% at 12% 25%, rgba(212,175,55,.08) 0%, transparent 65%),
        radial-gradient(ellipse 45% 55% at 88% 75%, rgba(255,255,255,.03) 0%, transparent 65%),
        radial-gradient(ellipse 70% 35% at 50% 110%, rgba(212,175,55,.05) 0%, transparent 60%);
    }

    .login-card-wrap {
      display: flex;
      width: 100%;
      max-width: 1000px;
      min-height: 640px;
      border-radius: 24px;
      overflow: hidden;
      border: 1px solid rgba(212,175,55,.15);
      box-shadow:
        0 0 0 1px rgba(255,255,255,.02),
        0 32px 80px rgba(0,0,0,.85),
        0 80px 160px rgba(0,0,0,.60),
        0 0 120px rgba(212,175,55,.08);
      animation: loginCardIn .8s cubic-bezier(.22,1,.36,1) both;
      position: relative; z-index: 1;
    }

    /* ── Left panel ── */
    .login-left {
      flex: 1;
      position: relative;
      overflow: hidden;
      background: linear-gradient(150deg, #111111 0%, var(--panel-dark) 45%, #000000 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 48px 40px;
      color: #fff;
    }
    /* Dot grid */
    .login-left::before {
      content: '';
      position: absolute; inset: 0;
      background-image: radial-gradient(circle, rgba(212,175,55,.08) 1px, transparent 1px);
      background-size: 24px 24px;
      pointer-events: none;
      z-index: 0;
    }
    /* Diagonal stripe overlay — premium texture */
    .login-left::after {
      content: '';
      position: absolute; inset: 0;
      background: repeating-linear-gradient(
        -52deg,
        transparent,
        transparent 28px,
        rgba(255,255,255,.015) 28px,
        rgba(255,255,255,.015) 29px
      );
      pointer-events: none;
      z-index: 0;
    }
    .login-glow-top {
      position: absolute;
      top: -100px; right: -100px;
      width: 340px; height: 340px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212,175,55,.2) 0%, transparent 70%);
      filter: blur(50px);
      animation: glowPulse 5s ease-in-out infinite;
      pointer-events: none;
      z-index: 0;
    }
    .login-glow-btm {
      position: absolute;
      bottom: -120px; left: -70px;
      width: 260px; height: 260px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(212,175,55,.1) 0%, transparent 70%);
      filter: blur(60px);
      animation: glowPulse 5s ease-in-out infinite reverse;
      pointer-events: none;
      z-index: 0;
    }
    .login-accent-bar {
      width: 44px; height: 3px;
      border-radius: 2px;
      background: linear-gradient(90deg, var(--gold-primary), var(--gold-dark));
      margin-bottom: 18px;
      box-shadow: 0 2px 12px rgba(212,175,55,.4);
    }

    .login-features {
      display: flex; flex-direction: column;
      gap: 14px; width: 100%;
      margin-top: 28px;
    }
    .login-feature {
      display: flex; align-items: center;
      gap: 12px; font-size: .84rem;
      color: #D1D5DB; line-height: 1.3;
      font-weight: 500;
    }
    .login-feature-dot {
      width: 24px; height: 24px; border-radius: 50%;
      background: transparent;
      border: 1px solid rgba(212,175,55,.4);
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .login-tagline {
      position: absolute;
      bottom: 22px; left: 0; right: 0;
      text-align: center;
      font-size: .67rem;
      color: rgba(212,175,55,.4);
      letter-spacing: .1em;
      text-transform: uppercase;
      z-index: 1;
      font-weight: 600;
    }

    /* ── Right panel ── */
    .login-right {
      flex: 1;
      background: #FFFFFF;
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 52px 44px;
      position: relative;
    }

    /* Elegant gold top accent line */
    .login-right::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--gold-dark) 0%, var(--gold-light) 50%, var(--gold-dark) 100%);
      background-size: 200% 100%;
      animation: bgShift 4s ease infinite;
    }

    /* ── Inputs ── */
    .input-wrap {
      position: relative;
      margin-bottom: 16px;
    }
    .input-icon-left {
      position: absolute; left: 13px; top: 50%;
      transform: translateY(-50%);
      color: #9CA3AF;
      pointer-events: none; display: flex;
      transition: color .3s ease;
    }
    .input-wrap:focus-within .input-icon-left { color: var(--gold-dark); }
    .input-login {
      width: 100%;
      padding: 12px 44px;
      border: 1.5px solid #E5E7EB;
      border-radius: 10px;
      font-size: .875rem;
      color: #111827;
      background: #FAFAFA;
      outline: none;
      font-family: 'Inter', sans-serif;
      transition: all .3s ease;
      box-sizing: border-box;
    }
    .input-login:focus {
      background: #FFFFFF;
      border-color: var(--gold-primary);
      box-shadow: 0 0 0 4px rgba(212,175,55,.1);
    }
    .input-login::placeholder { color: #9CA3AF; font-weight: 400; }

    @media (max-width: 768px) {
      .login-card-wrap {
        border-radius: 20px;
        min-height: auto;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,.6), 0 0 60px rgba(212,175,55,.1);
      }
      .login-right {
        padding: 36px 24px;
      }
    }

    /* ── Password toggle icons ── */
    .pw-toggle-btn {
      position: absolute; right: 11px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none; cursor: pointer;
      padding: 0; display: flex; align-items: center; justify-content: center;
      color: #9CA3AF; transition: color .2s;
      width: 22px; height: 22px;
    }
    .pw-toggle-btn:hover { color: var(--gold-dark); }
    .icon-eye, .icon-eye-off {
      position: absolute; top: 0; left: 0;
      transition: opacity .2s ease, transform .2s ease;
    }
    .icon-eye-off { opacity: 0; transform: scale(.7); }
    .pw-wrap.pw-shown .icon-eye     { opacity: 0; transform: scale(.7); }
    .pw-wrap.pw-shown .icon-eye-off { opacity: 1; transform: scale(1); }

    /* ── Minimalist Premium Button ── */
    .btn-login-submit {
      width: 100%; padding: 13px;
      border: 1.5px solid var(--gold-primary); 
      border-radius: 10px;
      font-size: .9375rem; font-weight: 700;
      font-family: 'Inter', sans-serif;
      color: var(--gold-primary);
      background: #050505;
      cursor: pointer;
      box-shadow: 0 4px 16px rgba(0,0,0,.1);
      transition: all .3s ease;
      letter-spacing: .02em;
      margin-top: 4px;
    }
    .btn-login-submit:hover {
      background: var(--gold-primary);
      color: #050505;
      box-shadow: 0 8px 24px rgba(212,175,55,.25);
      transform: translateY(-2px);
    }
    .btn-login-submit:active {
      transform: translateY(0);
      box-shadow: 0 3px 10px rgba(212,175,55,.15);
    }

    /* ── Flash messages ── */
    .flash-box {
      display: flex; align-items: flex-start;
      gap: 10px; padding: 13px 14px;
      border-radius: 10px; margin-bottom: 20px;
      font-size: .85rem; line-height: 1.5;
    }
    .flash-box.is-error {
      background: #FAFAFA;
      border-left: 4px solid var(--gold-dark);
      color: #111827;
      border-top: 1px solid #E5E7EB;
      border-right: 1px solid #E5E7EB;
      border-bottom: 1px solid #E5E7EB;
    }
    .flash-box.is-success {
      background: #FAFAFA;
      border-left: 4px solid #10B981;
      color: #111827;
      border-top: 1px solid #E5E7EB;
      border-right: 1px solid #E5E7EB;
      border-bottom: 1px solid #E5E7EB;
    }
    .flash-box svg { flex-shrink: 0; margin-top: 1px; color: var(--gold-dark); }
    .flash-box.is-success svg { color: #10B981; }

    /* ── Forgot link ── */
    .forgot-link {
      display: block; text-align: right;
      font-size: .8rem; color: #6B7280;
      text-decoration: none; font-weight: 500;
      margin-top: -8px; margin-bottom: 24px;
      transition: color .2s;
    }
    .forgot-link:hover { color: var(--gold-dark); }
  </style>
</head>
<body class="login-bg">

<div class="login-card-wrap">

  <div class="login-left hide-mobile">

    <div class="login-glow-top"></div>
    <div class="login-glow-btm"></div>

    <div style="position:relative;z-index:1;width:100%;display:flex;flex-direction:column;align-items:center">

      <div class="login-accent-bar"></div>

      <img src="<?= BASE_URL ?>public/img/fondo-amare.png" alt="AMARE" style="height:200px;margin-bottom:20px;object-fit:contain">

      <h2 class="shimmer-heading" style="font-size:1.5rem;font-weight:800;margin-bottom:12px;text-align:center;line-height:1.25">
        <?php if ($_isRest): ?>
          Gestión de Restaurantes<br>y Flujo de Ventas
        <?php else: ?>
          Abasto Inteligente<br>de Carne
        <?php endif; ?>
      </h2>
      <p style="color:#A3A3A3;text-align:center;font-size:.875rem;line-height:1.65;max-width:280px;margin:0">
        <?php if ($_isRest): ?>
          Controla operación, ventas y cocina en un solo flujo en tiempo real.
        <?php else: ?>
          La plataforma B2B que conecta tu negocio con el mejor abasto cárnico.
        <?php endif; ?>
      </p>

      <div class="login-features">
        <?php
          $features = $_isRest ? [
            ['Mesas, reservas y comandas en vivo', 'M5 13l4 4L19 7'],
            ['Menú digital y pedidos por QR',      'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['Flujo de ventas y cobros integrado', 'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
            ['Reportes de ventas y propinas',       'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
          ] : [
            ['Precios escalonados dinámicos', 'M5 13l4 4L19 7'],
            ['Pedidos multi-sucursal',        'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2'],
            ['Logística inteligente',         'M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0'],
            ['Análisis de consumo',           'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
          ];
          foreach ($features as [$label, $path]): ?>
        <div class="login-feature">
          <div class="login-feature-dot">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="var(--gold-primary)" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
              <path d="<?= $path ?>"/>
            </svg>
          </div>
          <span><?= $label ?></span>
        </div>
        <?php endforeach; ?>
      </div>

    </div>

    <div class="login-tagline"><?= $_isRest ? 'Sistema integral de gestión gastronómica' : 'Plataforma líder en abasto cárnico B2B' ?></div>
  </div>

  <div class="login-right">
    <div style="width:100%;max-width:380px">

      <div style="text-align:center;margin-bottom:24px" class="hide-desktop">
        <?php if ($_appLogo): ?>
          <img src="<?= htmlspecialchars($_appLogo) ?>" alt="<?= htmlspecialchars($_appName) ?>"
               style="height:90px;margin-bottom:14px;object-fit:contain">
        <?php else: ?>
          <img src="<?= BASE_URL ?>public/img/logo-carnisync.svg" alt="<?= htmlspecialchars($_appName) ?>"
               style="height:90px;margin-bottom:14px">
        <?php endif; ?>
      </div>

      <div style="margin-bottom:32px">
        <h1 style="font-size:1.75rem;font-weight:800;color:#050505;margin:0 0 8px;letter-spacing:-0.02em">Iniciar sesión</h1>
        <p style="color:#6B7280;font-size:.9rem;margin:0">
          Accede a tu panel de <strong style="color:#111827;font-weight:600"><?= htmlspecialchars($_appName) ?></strong>
        </p>
      </div>

      <?php if (!empty($flash)): ?>
      <div class="flash-box <?= $flash['type'] === 'error' ? 'is-error' : 'is-success' ?>">
        <?php if ($flash['type'] === 'error'): ?>
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        <?php else: ?>
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        <?php endif; ?>
        <span><?= htmlspecialchars($flash['message']) ?></span>
      </div>
      <?php endif; ?>

      <form method="POST" action="<?= BASE_URL ?>auth/doLogin">

        <div>
          <label class="form-label" style="display:block;margin-bottom:8px;font-size:0.875rem;font-weight:500;color:#374151">Correo electrónico</label>
          <div class="input-wrap">
            <span class="input-icon-left">
              <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
              </svg>
            </span>
            <input type="email" name="email" class="input-login"
                   placeholder="ejemplo@restaurante.com" required autocomplete="email">
          </div>
        </div>

        <div>
          <label class="form-label" style="display:block;margin-bottom:8px;font-size:0.875rem;font-weight:500;color:#374151">Contraseña</label>
          <div class="input-wrap pw-wrap" id="pwWrap">
            <span class="input-icon-left">
              <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <rect x="5" y="11" width="14" height="10" rx="2" ry="2"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M8 11V7a4 4 0 018 0v4"/>
              </svg>
            </span>
            <input type="password" name="password" id="passwordInput" class="input-login"
                   placeholder="••••••••" required autocomplete="current-password">
            <button type="button" onclick="togglePassword()" class="pw-toggle-btn" aria-label="Mostrar contraseña">
              <svg class="icon-eye" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <svg class="icon-eye-off" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
              </svg>
            </button>
          </div>
        </div>

        <a href="<?= BASE_URL ?>auth/forgot" class="forgot-link">¿Olvidaste tu contraseña?</a>

        <button type="submit" class="btn-login-submit">Ingresar al Sistema</button>
      </form>

      <p style="margin-top:28px;text-align:center;font-size:.8rem;color:#9CA3AF;line-height:1.5">
        ¿Problemas para acceder?
        <?php if ($_waPhone): ?>
          <a href="https://wa.me/<?= htmlspecialchars($_waPhone) ?>?text=<?= urlencode('Hola, necesito ayuda para acceder al sistema.') ?>"
             target="_blank" rel="noopener"
             style="color:var(--gold-dark);font-weight:600;text-decoration:none">
            Contacta al administrador
          </a>
        <?php else: ?>
          <br>Contacta al administrador de tu restaurante.
        <?php endif; ?>
      </p>

    </div>
  </div>
</div>

<script>
function togglePassword() {
  const input = document.getElementById('passwordInput');
  const wrap  = document.getElementById('pwWrap');
  input.type  = input.type === 'password' ? 'text' : 'password';
  wrap.classList.toggle('pw-shown', input.type === 'text');
}
</script>
</body>
</html>