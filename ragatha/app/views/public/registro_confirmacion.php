<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>¡Registro exitoso! — <?= htmlspecialchars($appName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #F9FAFB; color: #111827; }
    :root { --color-primary: <?= htmlspecialchars($colorPrimary) ?>; }
    @keyframes fadeInUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }
    .fade-in-up { animation: fadeInUp 0.6s ease-out; }
  </style>
</head>
<body>

<!-- Navbar -->
<nav style="background:#fff;border-bottom:1px solid #E5E7EB;padding:0 32px;height:60px;display:flex;align-items:center">
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($appLogo): ?>
      <img src="<?= htmlspecialchars($appLogo) ?>" alt="Logo" style="height:36px;object-fit:contain">
    <?php endif; ?>
    <span style="font-weight:800;font-size:1.1rem;color:#111827"><?= htmlspecialchars($appName) ?></span>
  </div>
</nav>

<!-- Main Content -->
<div style="max-width:650px;margin:64px auto;padding:0 24px">

  <!-- Success Card -->
  <div class="fade-in-up" style="background:#fff;border-radius:20px;border:1px solid #E5E7EB;padding:48px;text-align:center">

    <!-- Success Icon -->
    <div style="display:inline-flex;align-items:center;justify-content:center;width:80px;height:80px;background:linear-gradient(135deg, #10B981, #059669);border-radius:50%;margin-bottom:24px">
      <svg width="44" height="44" fill="none" viewBox="0 0 24 24" stroke="#fff" style="stroke-width:2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
      </svg>
    </div>

    <!-- Title -->
    <h1 style="font-size:2rem;font-weight:900;color:#111827;margin-bottom:12px">
      ¡Pago exitoso!
    </h1>

    <p style="font-size:1rem;color:#6B7280;margin-bottom:32px;line-height:1.6">
      Tu suscripción ha sido confirmada. Ahora solo falta un paso más.
    </p>

    <!-- Divider -->
    <div style="height:1px;background:#E5E7EB;margin:32px 0"></div>

    <!-- Instructions -->
    <div style="text-align:left;background:#F9FAFB;border-radius:12px;padding:24px;margin-bottom:28px">
      <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:20px">
        <div style="flex-shrink:0;width:32px;height:32px;background:var(--color-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.875rem">
          1
        </div>
        <div style="flex:1">
          <div style="font-weight:700;color:#111827;margin-bottom:4px;font-size:.95rem">
            Revisa tu correo electrónico
          </div>
          <div style="font-size:.875rem;color:#6B7280;line-height:1.5">
            Hemos enviado un correo a <strong style="color:#111827"><?= htmlspecialchars($email) ?></strong>
            con tus credenciales de acceso y un link de verificación.
          </div>
        </div>
      </div>

      <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:20px">
        <div style="flex-shrink:0;width:32px;height:32px;background:var(--color-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.875rem">
          2
        </div>
        <div style="flex:1">
          <div style="font-weight:700;color:#111827;margin-bottom:4px;font-size:.95rem">
            Haz clic en el link de verificación
          </div>
          <div style="font-size:.875rem;color:#6B7280;line-height:1.5">
            El correo incluye un botón para verificar tu cuenta. Haz clic en él para activar
            tu acceso al sistema.
          </div>
        </div>
      </div>

      <div style="display:flex;gap:16px;align-items:flex-start">
        <div style="flex-shrink:0;width:32px;height:32px;background:var(--color-primary);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.875rem">
          3
        </div>
        <div style="flex:1">
          <div style="font-weight:700;color:#111827;margin-bottom:4px;font-size:.95rem">
            ¡Empieza a usar <?= htmlspecialchars($appName) ?>!
          </div>
          <div style="font-size:.875rem;color:#6B7280;line-height:1.5">
            Una vez verificado, serás redirigido automáticamente a tu panel de control.
          </div>
        </div>
      </div>
    </div>

    <!-- Warning Box -->
    <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:16px;margin-bottom:32px;text-align:left">
      <div style="display:flex;gap:12px;align-items:flex-start">
        <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#D97706" style="flex-shrink:0;margin-top:2px">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
        </svg>
        <div>
          <div style="font-weight:700;color:#92400E;margin-bottom:4px;font-size:.875rem">
            El link expira en 24 horas
          </div>
          <div style="font-size:.8rem;color:#78350F;line-height:1.5">
            Si no verificas tu cuenta en ese tiempo, tendrás que contactar a soporte.
          </div>
        </div>
      </div>
    </div>

    <!-- Action Button -->
    <a href="<?= BASE_URL ?>auth/login"
       style="display:inline-block;padding:14px 32px;background:var(--color-primary);color:#fff;border-radius:10px;text-decoration:none;font-weight:800;font-size:.9rem;margin-bottom:16px">
      Ir al inicio de sesión
    </a>

    <?php if (isset($verifyUrl)): ?>
    <!-- Link directo para pruebas en sandbox -->
    <div style="margin-top:16px;padding:14px 16px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;font-size:.8rem;color:#166534;text-align:left">
      <strong>Sandbox / Pruebas:</strong> Si el email no llega, usa este link directamente:<br>
      <a href="<?= htmlspecialchars($verifyUrl) ?>" style="color:#166534;word-break:break-all"><?= htmlspecialchars($verifyUrl) ?></a>
    </div>
    <?php endif; ?>

    <!-- Help Text -->
    <div style="font-size:.8rem;color:#9CA3AF;margin-top:20px">
      ¿No recibiste el correo? Revisa tu carpeta de spam o correo no deseado.
    </div>
  </div>

  <!-- Additional Info -->
  <div style="text-align:center;margin-top:32px;padding:20px;background:#fff;border-radius:12px;border:1px solid #E5E7EB">
    <div style="font-weight:700;color:#374151;margin-bottom:8px;font-size:.9rem">
      📧 El correo fue enviado desde
    </div>
    <div style="font-size:.875rem;color:#6B7280">
      noreply@idactivos.digital
    </div>
  </div>
</div>

</body>
</html>
