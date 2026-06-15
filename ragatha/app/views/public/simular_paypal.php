<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PayPal - Revisar y pagar (SIMULACIÓN)</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #F5F7FA; color: #2C2E2F; }
  </style>
</head>
<body>

<!-- PayPal Header -->
<nav style="background:#fff;border-bottom:2px solid #0070BA;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between;box-shadow:0 2px 4px rgba(0,0,0,.05)">
  <div style="display:flex;align-items:center;gap:8px">
    <svg width="28" height="28" viewBox="0 0 24 24" fill="#0070BA">
      <path d="M20.067 8.478c.492.88.556 2.014.3 3.327-.74 3.806-3.276 5.12-6.514 5.12h-.5a.805.805 0 00-.794.68l-.04.22-.63 3.993-.028.15a.805.805 0 01-.794.68H8.175c-.308 0-.542-.295-.485-.598l2.06-13.046a.962.962 0 01.95-.805h5.17c1.917 0 3.38.367 4.197 1.28z"/>
      <path d="M9.926 3.653a.962.962 0 01.95-.805h5.17c2.604 0 4.267.638 4.984 2.132.315.656.453 1.419.434 2.323l-.006.358c.038-.01.077-.018.117-.024v-.001c.56-.087 1.02-.13 1.411-.127.174.001.335.014.483.038a1.678 1.678 0 011.436 1.91c-.326 1.994-1.144 3.466-2.45 4.405-1.17.84-2.73 1.24-4.642 1.24h-.29a1.02 1.02 0 00-1.007.862l-.806 5.11a.762.762 0 01-.753.645H11.15c-.346 0-.61-.332-.546-.672l2.322-14.714z" opacity=".7"/>
    </svg>
    <span style="font-size:1.5rem;font-weight:700;color:#0070BA">PayPal</span>
  </div>
  <div style="background:#FFD140;color:#2C2E2F;padding:6px 14px;border-radius:6px;font-size:.75rem;font-weight:700;letter-spacing:.03em">
    🧪 MODO PRUEBA
  </div>
</nav>

<!-- Main Content -->
<div style="max-width:500px;margin:48px auto;padding:0 24px">

  <!-- Warning Box -->
  <div style="background:#FFF4E5;border:2px solid #FFB020;border-radius:12px;padding:20px;margin-bottom:24px">
    <div style="display:flex;gap:12px;align-items:flex-start">
      <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="#F59E0B" style="flex-shrink:0;margin-top:2px">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <div>
        <div style="font-weight:700;color:#92400E;margin-bottom:6px;font-size:.95rem">
          Simulación de PayPal
        </div>
        <div style="font-size:.85rem;color:#78350F;line-height:1.5">
          Esta es una simulación del flujo de pago. No se procesará ningún pago real.
          Los planes aún no tienen IDs de PayPal configurados.
        </div>
      </div>
    </div>
  </div>

  <!-- Payment Card -->
  <div style="background:#fff;border-radius:16px;box-shadow:0 2px 8px rgba(0,0,0,.1);padding:32px;margin-bottom:20px">

    <!-- Title -->
    <h1 style="font-size:1.5rem;font-weight:700;color:#2C2E2F;margin-bottom:24px">
      Revisar tu información
    </h1>

    <!-- Business Info -->
    <div style="border-bottom:1px solid #E5E7EB;padding-bottom:20px;margin-bottom:20px">
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:6px">PAGAR A</div>
      <div style="font-weight:700;color:#2C2E2F;font-size:1.05rem;margin-bottom:4px">
        <?= htmlspecialchars($appName) ?>
      </div>
      <div style="font-size:.85rem;color:#6B7280">
        <?= htmlspecialchars($datosEmpresa['razon_social']) ?>
      </div>
    </div>

    <!-- Subscription Details -->
    <div style="background:#F9FAFB;border-radius:10px;padding:18px;margin-bottom:24px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px">
        <div>
          <div style="font-weight:700;color:#2C2E2F;font-size:.95rem;margin-bottom:4px">
            Suscripción: <?= htmlspecialchars($plan['nombre']) ?>
          </div>
          <div style="font-size:.8rem;color:#6B7280">
            Ciclo: <?= $registro['ciclo'] === 'anual' ? 'Anual' : 'Mensual' ?>
          </div>
        </div>
        <div style="text-align:right">
          <div style="font-weight:800;color:#2C2E2F;font-size:1.2rem">
            <?php
            $precio = $registro['ciclo'] === 'anual'
              ? number_format($plan['precio_anual'] / 12, 2, '.', ',')
              : number_format($plan['precio_mensual'], 2, '.', ',');
            ?>
            $<?= $precio ?> MXN
          </div>
          <div style="font-size:.75rem;color:#6B7280">
            por mes
          </div>
        </div>
      </div>

      <?php if ($registro['ciclo'] === 'anual'): ?>
      <div style="border-top:1px solid #E5E7EB;padding-top:12px;font-size:.8rem;color:#059669">
        <strong>Ahorro anual:</strong> $<?= number_format($plan['precio_mensual'] * 12 - $plan['precio_anual'], 2) ?> MXN
      </div>
      <?php endif; ?>
    </div>

    <!-- Account Info -->
    <div style="margin-bottom:28px">
      <div style="font-size:.8rem;color:#6B7280;margin-bottom:10px">CUENTA</div>
      <div style="display:flex;align-items:center;gap:10px;padding:12px;background:#F9FAFB;border-radius:8px">
        <div style="width:40px;height:40px;background:#E0E7FF;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;color:#4338CA;font-size:1.1rem">
          <?= strtoupper(substr($registro['email'], 0, 1)) ?>
        </div>
        <div>
          <div style="font-weight:600;color:#2C2E2F;font-size:.9rem">
            <?= htmlspecialchars($registro['email']) ?>
          </div>
          <div style="font-size:.75rem;color:#6B7280">
            Cuenta de prueba
          </div>
        </div>
      </div>
    </div>

    <!-- Buttons -->
    <form method="POST" action="<?= BASE_URL ?>planes/aprobarPagoTest">
      <input type="hidden" name="reg_id" value="<?= $registro['id'] ?>">

      <button type="submit" name="accion" value="aprobar"
              style="width:100%;padding:14px;background:#0070BA;color:#fff;border:none;border-radius:24px;font-weight:700;font-size:.95rem;cursor:pointer;margin-bottom:12px">
        ✓ Aprobar y Continuar
      </button>

      <button type="submit" name="accion" value="cancelar"
              style="width:100%;padding:14px;background:#F3F4F6;color:#6B7280;border:none;border-radius:24px;font-weight:600;font-size:.85rem;cursor:pointer">
        Cancelar y volver
      </button>
    </form>
  </div>

  <!-- Footer Note -->
  <div style="text-align:center;font-size:.75rem;color:#9CA3AF;line-height:1.5">
    Esto es una simulación. En producción, serás redirigido al PayPal real<br>
    donde ingresarás tus credenciales y método de pago.
  </div>
</div>

</body>
</html>
