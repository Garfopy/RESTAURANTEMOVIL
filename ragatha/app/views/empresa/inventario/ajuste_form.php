<?php
$baseUrl = BASE_URL;
?>

<div style="max-width:500px">
  <a href="<?= $baseUrl ?>empresa-inventario" style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:16px">
    ← Volver al stock
  </a>

  <!-- Info producto -->
  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;padding:16px;background:#F9FAFB;border-radius:10px;border:1px solid #E5E7EB">
    <?php if (!empty($producto['imagen'])): ?>
      <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:8px">
    <?php endif; ?>
    <div>
      <div style="font-weight:700;color:#111827"><?= htmlspecialchars($producto['nombre']) ?></div>
      <div style="font-size:.8rem;color:#6B7280">Stock actual: <strong><?= number_format((float)$producto['stock_actual'], 1) ?> <?= $producto['presentacion'] ?></strong></div>
    </div>
  </div>

  <p style="font-size:.85rem;color:#6B7280;padding:10px 14px;background:#FEF9C3;border:1px solid #FDE68A;border-radius:8px;margin-bottom:24px">
    El ajuste directo establece el stock exacto del producto. Se registra en el historial con el motivo que indiques.
  </p>

  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:28px">
    <?php if ($flash): ?>
    <div style="margin-bottom:16px;padding:12px;border-radius:8px;font-size:.875rem;font-weight:500;
      <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46' : 'background:#FEE2E2;color:#991B1B' ?>">
      <?= htmlspecialchars($flash['message']) ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?= $baseUrl ?>empresa-inventario/ajuste/<?= $producto['id'] ?>">
      <div style="margin-bottom:18px">
        <label style="display:block;font-size:.85rem;font-weight:700;color:#374151;margin-bottom:8px">
          Stock nuevo (cantidad exacta) <span style="color:#DC2626">*</span>
        </label>
        <input type="number" name="stock_nuevo" value="<?= number_format((float)$producto['stock_actual'], 1) ?>"
               min="0" step="0.1" required
               style="width:100%;padding:10px 14px;border:2px solid #D1D5DB;border-radius:8px;font-size:1.1rem;font-weight:700;box-sizing:border-box">
        <p style="margin-top:4px;font-size:.75rem;color:#9CA3AF">Este será el nuevo valor de stock del producto.</p>
      </div>
      <div style="margin-bottom:18px">
        <label style="display:block;font-size:.85rem;font-weight:700;color:#374151;margin-bottom:8px">
          Umbral mínimo de alerta
        </label>
        <input type="number" name="umbral_minimo" value="<?= number_format((float)$producto['umbral_minimo'], 1) ?>"
               min="0" step="0.1"
               style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box">
      </div>
      <div style="margin-bottom:24px">
        <label style="display:block;font-size:.85rem;font-weight:700;color:#374151;margin-bottom:8px">
          Motivo del ajuste
        </label>
        <input type="text" name="motivo" placeholder="Ej: Conteo físico, corrección de error..."
               style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box">
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit" style="flex:1;padding:12px;background:#D97706;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer">
          Guardar Ajuste
        </button>
        <a href="<?= $baseUrl ?>empresa-inventario" style="padding:12px 20px;border:1px solid #D1D5DB;border-radius:8px;color:#374151;text-decoration:none;font-size:.875rem;display:flex;align-items:center">
          Cancelar
        </a>
      </div>
    </form>
  </div>
</div>
