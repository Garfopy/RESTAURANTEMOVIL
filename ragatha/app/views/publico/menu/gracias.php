<?php
/**
 * Página de despedida — se muestra tras registrar la salida escaneando el QR.
 *
 * @var array       $restaurante
 * @var array|null  $visita
 * @var string      $pageTitle
 */
$color  = htmlspecialchars($restaurante['color_primario'] ?? '#C8102E');
$logo   = $restaurante['logo']   ?? '';
$nombre = htmlspecialchars($restaurante['nombre'] ?? 'el restaurante');
$mesa   = htmlspecialchars($visita['mesa_nombre']     ?? '');
$comensal = htmlspecialchars($visita['comensal_nombre'] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>¡Gracias por tu visita! — <?= $nombre ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root { --cp: <?= $color ?>; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: 'Inter', system-ui, sans-serif;
      background: #0d0d18;
      min-height: 100dvh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: max(32px, env(safe-area-inset-top)) 20px max(48px, env(safe-area-inset-bottom));
      overflow: hidden;
    }

    /* Fondo con glow suave del color primario */
    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: radial-gradient(ellipse 70% 55% at 50% 40%, color-mix(in srgb, var(--cp) 18%, transparent), transparent 70%);
      pointer-events: none;
    }

    .card {
      position: relative;
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.10);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border-radius: 28px;
      padding: 44px 32px 40px;
      max-width: 420px;
      width: 100%;
      text-align: center;
      animation: fadeUp .55s cubic-bezier(.22,1,.36,1) both;
    }

    @keyframes fadeUp {
      from { opacity:0; transform:translateY(24px); }
      to   { opacity:1; transform:translateY(0); }
    }

    /* Logo */
    .logo-wrap {
      margin-bottom: 20px;
    }
    .logo-wrap img {
      height: 52px;
      object-fit: contain;
      filter: drop-shadow(0 2px 8px rgba(0,0,0,.35));
    }
    .logo-fallback {
      width: 52px; height: 52px;
      border-radius: 14px;
      background: var(--cp);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
    }

    /* Ícono central */
    .icon-wrap {
      margin: 0 auto 20px;
      width: 88px; height: 88px;
      border-radius: 50%;
      background: color-mix(in srgb, var(--cp) 15%, transparent);
      border: 2px solid color-mix(in srgb, var(--cp) 40%, transparent);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.8rem;
      animation: pulse 2.5s ease-in-out infinite;
    }
    @keyframes pulse {
      0%, 100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--cp) 30%, transparent); }
      50%       { box-shadow: 0 0 0 14px color-mix(in srgb, var(--cp) 0%, transparent); }
    }

    /* Textos */
    .titulo {
      font-family: 'Playfair Display', Georgia, serif;
      font-size: 2rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.15;
      margin-bottom: 10px;
    }
    .subtitulo {
      font-size: .95rem;
      color: rgba(255,255,255,.65);
      line-height: 1.55;
      margin-bottom: 28px;
    }
    .subtitulo strong {
      color: var(--cp);
    }

    /* Separador */
    .sep {
      width: 48px; height: 3px;
      background: var(--cp);
      border-radius: 2px;
      margin: 0 auto 24px;
      opacity: .7;
    }

    /* Info de visita */
    .visit-info {
      background: rgba(0,0,0,.25);
      border-radius: 14px;
      padding: 14px 20px;
      margin-bottom: 28px;
      font-size: .82rem;
      color: rgba(255,255,255,.5);
      display: flex;
      gap: 16px;
      justify-content: center;
      flex-wrap: wrap;
    }
    .visit-info span { display: flex; align-items: center; gap: 5px; }
    .visit-info strong { color: rgba(255,255,255,.8); }

    /* Botón cerrar */
    .btn-cerrar {
      display: inline-block;
      padding: 13px 32px;
      background: var(--cp);
      color: #fff;
      font-weight: 700;
      font-size: .95rem;
      border-radius: 99px;
      border: none;
      cursor: pointer;
      text-decoration: none;
      transition: opacity .15s, transform .15s;
      box-shadow: 0 4px 20px color-mix(in srgb, var(--cp) 45%, transparent);
    }
    .btn-cerrar:hover  { opacity: .9; }
    .btn-cerrar:active { transform: scale(.97); }

    /* Estrellas decorativas */
    .stars {
      position: fixed;
      inset: 0;
      pointer-events: none;
      overflow: hidden;
      z-index: -1;
    }
    .star {
      position: absolute;
      width: 3px; height: 3px;
      border-radius: 50%;
      background: rgba(255,255,255,.35);
      animation: twinkle var(--d, 3s) ease-in-out infinite var(--del, 0s);
    }
    @keyframes twinkle {
      0%, 100% { opacity: .15; }
      50%       { opacity: .7; }
    }
  </style>
</head>
<body>

<!-- Estrellas decorativas -->
<div class="stars" aria-hidden="true">
  <?php for ($i = 0; $i < 30; $i++):
    $x   = rand(0, 100);
    $y   = rand(0, 100);
    $d   = rand(25, 55) / 10;
    $del = rand(0, 30) / 10;
  ?>
  <div class="star" style="left:<?= $x ?>%;top:<?= $y ?>%;--d:<?= $d ?>s;--del:<?= $del ?>s"></div>
  <?php endfor; ?>
</div>

<div class="card">

  <!-- Logo -->
  <div class="logo-wrap">
    <?php if ($logo): ?>
    <img src="<?= BASE_URL . htmlspecialchars($logo) ?>" alt="<?= $nombre ?>">
    <?php else: ?>
    <div class="logo-fallback">🍽️</div>
    <?php endif; ?>
  </div>

  <!-- Ícono -->
  <div class="icon-wrap">🙏</div>

  <!-- Título -->
  <h1 class="titulo">¡Gracias por tu visita!</h1>
  <div class="sep"></div>

  <!-- Subtítulo -->
  <p class="subtitulo">
    <strong><?= $nombre ?></strong> se despide y espera verte pronto.<br>
    ¡Fue un placer recibirte!
  </p>

  <!-- Info de visita -->
  <?php if ($mesa || $comensal): ?>
  <div class="visit-info">
    <?php if ($comensal): ?>
    <span>👤 <strong><?= $comensal ?></strong></span>
    <?php endif; ?>
    <?php if ($mesa): ?>
    <span>🪑 Mesa <strong><?= $mesa ?></strong></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Botón -->
  <button class="btn-cerrar" onclick="cerrar()">Cerrar</button>
</div>

<script>
function cerrar() {
  // Intentar cerrar la pestaña si fue abierta por JS; si no, ir al inicio
  if (window.opener || window.history.length <= 1) {
    window.close();
  } else {
    window.location.href = '/';
  }
}
// Auto-cierre después de 12 segundos
setTimeout(cerrar, 12000);
</script>
</body>
</html>
