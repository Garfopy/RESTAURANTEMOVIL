<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registro — <?= htmlspecialchars($appName) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background: #F9FAFB; color: #111827; }
    :root { --color-primary: <?= htmlspecialchars($colorPrimary) ?>; }
  </style>
</head>
<body>

<!-- Navbar -->
<nav style="background:#fff;border-bottom:1px solid #E5E7EB;padding:0 32px;height:60px;display:flex;align-items:center;justify-content:space-between">
  <div style="display:flex;align-items:center;gap:10px">
    <?php if ($appLogo): ?>
      <img src="<?= htmlspecialchars($appLogo) ?>" alt="Logo" style="height:36px;object-fit:contain">
    <?php endif; ?>
    <span style="font-weight:800;font-size:1.1rem;color:#111827"><?= htmlspecialchars($appName) ?></span>
  </div>
  <a href="<?= BASE_URL ?>planes"
     style="padding:8px 20px;background:transparent;color:#6B7280;border:1px solid #E5E7EB;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
    ← Ver planes
  </a>
</nav>

<!-- Main Content -->
<div style="max-width:600px;margin:48px auto;padding:0 24px">

  <?php if (!empty($flash['error'])): ?>
  <div style="background:#FEE2E2;border:1px solid #FCA5A5;color:#991B1B;padding:14px 18px;border-radius:8px;margin-bottom:24px;font-size:.875rem">
    <?= htmlspecialchars($flash['error']) ?>
  </div>
  <?php endif; ?>

  <!-- Card -->
  <div style="background:#fff;border-radius:18px;border:1px solid #E5E7EB;padding:40px">

    <!-- Header -->
    <div style="text-align:center;margin-bottom:32px">
      <h1 style="font-size:1.75rem;font-weight:900;color:#111827;margin-bottom:8px">
        Completa tu registro
      </h1>
      <p style="color:#6B7280;font-size:.95rem">
        Estás a un paso de empezar con <?= htmlspecialchars($appName) ?>
      </p>
    </div>

    <!-- Plan seleccionado -->
    <div style="background:linear-gradient(135deg, var(--color-primary), #7C3AED);border-radius:12px;padding:20px;margin-bottom:32px;color:#fff">
      <div style="font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;opacity:.9;margin-bottom:4px">
        Plan seleccionado
      </div>
      <div style="font-size:1.4rem;font-weight:900;margin-bottom:4px">
        <?= htmlspecialchars($plan['nombre']) ?>
      </div>
      <div style="font-size:.9rem;opacity:.95">
        <?php
        $precio = $ciclo === 'anual'
          ? number_format($plan['precio_anual'] / 12, 0, '.', ',')
          : number_format($plan['precio_mensual'], 0, '.', ',');
        ?>
        $<?= $precio ?> MXN/mes
        <?php if ($ciclo === 'anual'): ?>
          <span style="font-size:.8rem;opacity:.85">(facturado anualmente)</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Formulario -->
    <form method="POST" action="<?= BASE_URL ?>planes/checkout">
      <input type="hidden" name="plan_slug" value="<?= htmlspecialchars($planSlug) ?>">
      <input type="hidden" name="ciclo" value="<?= htmlspecialchars($ciclo) ?>">

      <!-- Razón Social -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-weight:600;font-size:.875rem;color:#374151;margin-bottom:6px">
          Razón Social <span style="color:#DC2626">*</span>
        </label>
        <input
          type="text"
          name="razon_social"
          required
          maxlength="150"
          style="width:100%;padding:12px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem;color:#111827"
          placeholder="Ej: Carnicería El Buen Sabor S.A. de C.V."
          value="<?= htmlspecialchars($this->post('razon_social', '')) ?>"
        >
      </div>

      <!-- RFC -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-weight:600;font-size:.875rem;color:#374151;margin-bottom:6px">
          RFC <span style="color:#DC2626">*</span>
        </label>
        <input
          type="text"
          name="rfc"
          required
          maxlength="13"
          style="width:100%;padding:12px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem;color:#111827;text-transform:uppercase"
          placeholder="Ej: ABC123456XYZ"
          value="<?= htmlspecialchars($this->post('rfc', '')) ?>"
          pattern="[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}"
        >
        <div style="font-size:.75rem;color:#6B7280;margin-top:4px">
          Formato mexicano (12-13 caracteres)
        </div>
      </div>

      <!-- Email -->
      <div style="margin-bottom:20px">
        <label style="display:block;font-weight:600;font-size:.875rem;color:#374151;margin-bottom:6px">
          Email <span style="color:#DC2626">*</span>
        </label>
        <input
          type="email"
          name="email"
          required
          maxlength="150"
          style="width:100%;padding:12px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem;color:#111827"
          placeholder="tu@email.com"
          value="<?= htmlspecialchars($this->post('email', '')) ?>"
        >
        <div style="font-size:.75rem;color:#6B7280;margin-top:4px">
          Te enviaremos tus credenciales a este correo
        </div>
      </div>

      <!-- Teléfono -->
      <div style="margin-bottom:32px">
        <label style="display:block;font-weight:600;font-size:.875rem;color:#374151;margin-bottom:6px">
          Teléfono (opcional)
        </label>
        <input
          type="tel"
          name="telefono"
          maxlength="15"
          style="width:100%;padding:12px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem;color:#111827"
          placeholder="Ej: +52 55 1234 5678"
          value="<?= htmlspecialchars($this->post('telefono', '')) ?>"
        >
      </div>

      <!-- Info Box -->
      <div style="background:#F0F9FF;border:1px solid #BAE6FD;border-radius:10px;padding:16px;margin-bottom:28px">
        <div style="display:flex;gap:12px;align-items:flex-start">
          <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="#0284C7" style="flex-shrink:0;margin-top:2px">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <div style="font-size:.825rem;color:#075985;line-height:1.5">
            <strong>Siguiente paso:</strong> Serás redirigido a PayPal para completar el pago de forma segura.
            Una vez confirmado, recibirás un correo con tus credenciales y un link de verificación.
          </div>
        </div>
      </div>

      <!-- Buttons -->
      <div style="display:flex;gap:12px">
        <a href="<?= BASE_URL ?>planes"
           style="flex:1;display:block;text-align:center;padding:14px;background:#F3F4F6;color:#374151;border-radius:10px;text-decoration:none;font-weight:700;font-size:.875rem">
          Cancelar
        </a>
        <button type="submit"
                style="flex:2;padding:14px;background:var(--color-primary);color:#fff;border:none;border-radius:10px;font-weight:800;font-size:.875rem;cursor:pointer">
          Proceder al pago →
        </button>
      </div>
    </form>
  </div>

  <!-- Footer Note -->
  <div style="text-align:center;margin-top:24px;font-size:.8rem;color:#9CA3AF">
    Pago seguro procesado por PayPal. Puedes cancelar tu suscripción en cualquier momento.
  </div>
</div>

</body>
</html>
