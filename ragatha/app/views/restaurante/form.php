<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= ($restaurante ? 'Editar' : 'Crear') ?> Restaurante — CarniHub</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body style="background:#F9FAFB;font-family:system-ui,sans-serif;min-height:100vh;padding:40px 20px">
<div style="max-width:580px;margin:0 auto">
  <h1 style="font-size:1.4rem;font-weight:700;color:#111827;margin-bottom:24px">
    <?= ($restaurante ? 'Editar' : 'Crear') ?> Restaurante
  </h1>

  <?php if (!empty($flash)): ?>
  <div style="padding:12px 16px;border-radius:8px;margin-bottom:16px;font-size:.875rem;font-weight:500;
    background:<?= $flash['type'] === 'success' ? '#DCFCE7' : '#FEE2E2' ?>;
    color:<?= $flash['type'] === 'success' ? '#166534' : '#991B1B' ?>">
    <?= htmlspecialchars($flash['message']) ?>
  </div>
  <?php endif; ?>

  <div style="background:#fff;border-radius:16px;border:1px solid #E5E7EB;padding:28px">
    <form method="POST" action="<?= BASE_URL . ($restaurante ? 'restaurante/actualizar/'.$restaurante['id'] : 'restaurante/guardar') ?>">
      <div style="margin-bottom:16px">
        <label style="font-size:.85rem;font-weight:500">Nombre del restaurante *</label>
        <input type="text" name="nombre" value="<?= htmlspecialchars($restaurante['nombre'] ?? '') ?>" required
          style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
      </div>
      <div style="margin-bottom:16px">
        <label style="font-size:.85rem;font-weight:500">Descripción</label>
        <textarea name="descripcion" rows="2"
          style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem;resize:vertical"><?= htmlspecialchars($restaurante['descripcion'] ?? '') ?></textarea>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px">
        <div>
          <label style="font-size:.85rem;font-weight:500">Teléfono</label>
          <input type="text" name="telefono" value="<?= htmlspecialchars($restaurante['telefono'] ?? '') ?>"
            style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
        </div>
        <div>
          <label style="font-size:.85rem;font-weight:500">Color primario</label>
          <input type="color" name="color_primario" value="<?= htmlspecialchars($restaurante['color_primario'] ?? '#C8102E') ?>"
            style="height:40px;width:100%;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;padding:2px;cursor:pointer">
        </div>
      </div>
      <div style="margin-bottom:20px">
        <label style="font-size:.85rem;font-weight:500">Dirección</label>
        <input type="text" name="direccion" value="<?= htmlspecialchars($restaurante['direccion'] ?? '') ?>"
          style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem">
      </div>
      <div style="display:flex;gap:10px;justify-content:flex-end">
        <a href="<?= BASE_URL ?>restaurante/seleccionar"
          style="padding:8px 16px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;text-decoration:none;color:#374151">Cancelar</a>
        <button type="submit"
          style="padding:8px 24px;background:#C8102E;color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:500;cursor:pointer">
          <?= $restaurante ? 'Actualizar' : 'Crear Restaurante' ?>
        </button>
      </div>
    </form>
  </div>
</div>
</body>
</html>
