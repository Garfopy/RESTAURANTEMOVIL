<?php
/**
 * Página pública de cancelación de reservación — accesible vía enlace sin login.
 *
 * @var array      $restaurante
 * @var string     $pageTitle
 * @var int        $reservaId
 * @var bool       $cancelada   true cuando la cancelación se procesó con éxito
 * @var array|null $flash
 */
$color  = htmlspecialchars($restaurante['color_primario'] ?? '#C8102E');
$nombre = htmlspecialchars($restaurante['nombre'] ?? 'el restaurante');
$logo   = $restaurante['logo'] ?? '';
$slug   = htmlspecialchars($restaurante['slug'] ?? '');

$flashMsg  = $flash['message'] ?? null;
$flashType = $flash['type']    ?? 'info';
$cancelada = $cancelada ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <style>
    :root { --cp: <?= $color ?>; }
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background: #F3F4F6;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .topbar {
      width: 100%;
      background: #fff;
      border-bottom: 1px solid #E5E7EB;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
    }
    .topbar img  { width: 36px; height: 36px; border-radius: 8px; object-fit: cover; }
    .topbar-name { font-weight: 700; font-size: 1rem; color: #111827; }
    .card {
      background: #fff;
      border-radius: 16px;
      box-shadow: 0 2px 12px rgba(0,0,0,.07);
      padding: 28px 24px;
      margin: 24px 16px;
      width: 100%;
      max-width: 460px;
    }
    .card-title {
      font-size: 1.15rem;
      font-weight: 700;
      color: #111827;
      margin-bottom: 4px;
    }
    .card-sub {
      font-size: .82rem;
      color: #6B7280;
      margin-bottom: 22px;
    }
    label {
      display: block;
      font-size: .82rem;
      font-weight: 600;
      color: #374151;
      margin-bottom: 4px;
    }
    input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid #D1D5DB;
      border-radius: 10px;
      font-size: .9rem;
      color: #111827;
      background: #fff;
      outline: none;
      transition: border-color .15s;
      margin-bottom: 14px;
    }
    input:focus { border-color: var(--cp); }
    .btn-cancel {
      width: 100%;
      padding: 13px;
      background: #DC2626;
      color: #fff;
      border: none;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      margin-top: 6px;
      transition: opacity .15s;
    }
    .btn-cancel:active { opacity: .85; }
    .btn-back {
      display: inline-block;
      padding: 10px 22px;
      border: 2px solid var(--cp);
      color: var(--cp);
      border-radius: 10px;
      font-size: .88rem;
      font-weight: 600;
      text-decoration: none;
      background: transparent;
    }
    .alert {
      border-radius: 10px;
      padding: 12px 14px;
      margin-bottom: 16px;
      font-size: .85rem;
      font-weight: 500;
    }
    .alert-error   { background: #FEE2E2; color: #991B1B; }
    .alert-success { background: #DCFCE7; color: #166534; }
    .result-box {
      text-align: center;
      padding: 12px 0 4px;
    }
    .result-icon  { font-size: 3rem; margin-bottom: 10px; }
    .result-title { font-size: 1.2rem; font-weight: 700; color: #111827; margin-bottom: 6px; }
    .result-sub   { font-size: .85rem; color: #6B7280; margin-bottom: 20px; }
    .info-box {
      background: #FEF3C7;
      border-radius: 10px;
      padding: 12px 14px;
      font-size: .83rem;
      color: #92400E;
      margin-bottom: 18px;
    }
  </style>
</head>
<body>

<div class="topbar">
  <?php if ($logo): ?>
    <img src="<?= htmlspecialchars($logo) ?>" alt="logo">
  <?php endif; ?>
  <span class="topbar-name"><?= $nombre ?></span>
</div>

<div class="card">

  <?php if ($cancelada): ?>
    <div class="result-box">
      <div class="result-icon">✅</div>
      <div class="result-title">Reservación cancelada</div>
      <div class="result-sub">
        Tu reservación ha sido cancelada exitosamente.<br>
        Esperamos verte pronto.
      </div>
      <a href="<?= BASE_URL ?>menu/<?= $slug ?>/reservar" class="btn-back">Hacer nueva reservación</a>
    </div>

  <?php else: ?>
    <div class="card-title">❌ Cancelar reservación</div>
    <div class="card-sub">en <?= $nombre ?></div>

    <?php if ($flashMsg): ?>
      <div class="alert alert-<?= $flashType === 'error' ? 'error' : 'success' ?>">
        <?= htmlspecialchars($flashMsg) ?>
      </div>
    <?php endif; ?>

    <div class="info-box">
      Para confirmar la cancelación, ingresa el teléfono que usaste al hacer la reservación.
    </div>

    <form method="POST" action="<?= BASE_URL ?>menu/<?= $slug ?>/cancelarReserva/<?= (int)$reservaId ?>">
      <label>Teléfono de confirmación</label>
      <input type="tel" name="telefono" placeholder="10 dígitos" required autocomplete="tel">

      <button type="submit" class="btn-cancel">Confirmar cancelación</button>
    </form>

    <div style="text-align:center;margin-top:18px">
      <a href="<?= BASE_URL ?>menu/<?= $slug ?>/reservar" class="btn-back">Volver</a>
    </div>

  <?php endif; ?>

</div>

</body>
</html>
