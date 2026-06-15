<?php ob_start(); ?>
<div style="max-width:700px">
  <a href="<?= BASE_URL ?>rest-pedido/index" style="font-size:.85rem;color:#6B7280;text-decoration:none;margin-bottom:20px;display:inline-block">← Pedidos</a>

  <?php if ($mesa): ?>
  <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.875rem;color:#166534">
    🪑 Mesa: <strong><?= htmlspecialchars($mesa['nombre']) ?></strong>
  </div>
  <?php endif; ?>

  <form method="POST" action="<?= BASE_URL ?>rest-pedido/crear">
    <input type="hidden" name="mesa_id" value="<?= (int)($mesa['id'] ?? 0) ?>">
    <div id="items-lista">
      <div class="item-row" style="background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:16px;margin-bottom:12px">
        <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:center">
          <select name="platillo_id[]" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem">
            <option value="">-- Platillo --</option>
            <?php foreach ($menu as $p): ?>
            <option value="<?= $p['id'] ?>" data-precio="<?= (float)$p['precio'] ?>">
              <?= htmlspecialchars($p['nombre']) ?> — $<?= number_format((float)$p['precio'],2) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="cantidad[]" value="1" min="1"
            style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem">
          <button type="button" onclick="this.closest('.item-row').remove()"
            style="padding:6px 12px;background:#FEE2E2;color:#991B1B;border:none;border-radius:8px;cursor:pointer;font-size:.9rem">✕</button>
        </div>
        <input type="text" name="notas_item[]" placeholder="Notas (opcional)"
          style="width:100%;padding:6px 12px;border:1px solid #E5E7EB;border-radius:8px;margin-top:8px;font-size:.85rem">
      </div>
    </div>

    <button type="button" onclick="addItem()"
      style="padding:8px 16px;border:1px dashed #D1D5DB;border-radius:8px;font-size:.85rem;cursor:pointer;background:#F9FAFB;margin-bottom:20px">
      + Agregar platillo
    </button>

    <div style="margin-bottom:16px">
      <label style="font-size:.85rem;font-weight:500">Notas del pedido</label>
      <textarea name="notas" rows="2"
        style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;margin-top:4px;font-size:.9rem;resize:vertical"></textarea>
    </div>

    <button type="submit"
      style="padding:10px 28px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer">
      Enviar Pedido →
    </button>
  </form>
</div>

<script>
const platillosOpts = `<?php foreach ($menu as $p): ?><option value="<?= $p['id'] ?>" data-precio="<?= (float)$p['precio'] ?>"><?= htmlspecialchars($p['nombre']) ?> — $<?= number_format((float)$p['precio'],2) ?></option><?php endforeach; ?>`;

function addItem() {
  const row = document.createElement('div');
  row.className = 'item-row';
  row.style.cssText = 'background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:16px;margin-bottom:12px';
  row.innerHTML = `
    <div style="display:grid;grid-template-columns:2fr 1fr auto;gap:12px;align-items:center">
      <select name="platillo_id[]" style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem">
        <option value="">-- Platillo --</option>${platillosOpts}
      </select>
      <input type="number" name="cantidad[]" value="1" min="1"
        style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.9rem">
      <button type="button" onclick="this.closest('.item-row').remove()"
        style="padding:6px 12px;background:#FEE2E2;color:#991B1B;border:none;border-radius:8px;cursor:pointer;font-size:.9rem">✕</button>
    </div>
    <input type="text" name="notas_item[]" placeholder="Notas (opcional)"
      style="width:100%;padding:6px 12px;border:1px solid #E5E7EB;border-radius:8px;margin-top:8px;font-size:.85rem">
  `;
  document.getElementById('items-lista').appendChild(row);
}
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
