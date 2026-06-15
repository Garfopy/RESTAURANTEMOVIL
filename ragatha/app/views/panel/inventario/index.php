<?php
// Variables: $items[], $paginacion, $filtros
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <form method="GET" action="<?= BASE_URL ?>panel-inventario/index" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="buscar" value="<?= htmlspecialchars($filtros['buscar']) ?>"
           placeholder="Buscar producto..."
           style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;outline:none;min-width:200px">
    <label style="display:flex;align-items:center;gap:6px;font-size:.875rem;color:#374151;padding:0 4px">
      <input type="checkbox" name="stock_bajo" value="1" <?= !empty($filtros['stock_bajo']) ? 'checked' : '' ?>>
      Solo alertas de stock
    </label>
    <button type="submit"
            style="padding:7px 16px;background:#374151;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer">Filtrar</button>
  </form>
</div>

<!-- Modal ajuste de stock -->
<div id="modal-ajuste" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:1000;align-items:center;justify-content:center">
  <div style="background:#fff;border-radius:14px;padding:28px;width:380px;box-shadow:0 20px 60px rgba(0,0,0,.2)">
    <h3 style="margin:0 0 18px;font-size:1rem;font-weight:700;color:#111827">Ajuste de inventario</h3>
    <form method="POST" action="<?= BASE_URL ?>panel-inventario/ajustar">
      <input type="hidden" name="producto_id" id="ajuste_id">
      <p id="ajuste_nombre" style="font-weight:600;color:#374151;margin:0 0 14px;font-size:.875rem"></p>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Tipo de movimiento</label>
        <select name="tipo" style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
          <option value="entrada">Entrada (suma stock)</option>
          <option value="salida">Salida (resta stock)</option>
          <option value="ajuste">Ajuste directo (reemplaza stock)</option>
        </select>
      </div>
      <div style="margin-bottom:14px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Cantidad</label>
        <input type="number" name="cantidad" step="0.01" min="0.01" required
               style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
      </div>
      <div style="margin-bottom:18px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Notas (opcional)</label>
        <input type="text" name="notas" placeholder="Ej: Compra proveedor ABC"
               style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
      </div>
      <div style="display:flex;gap:10px">
        <button type="submit"
                style="flex:1;padding:10px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">Guardar</button>
        <button type="button" onclick="cerrarModal()"
                style="flex:1;padding:10px;background:#F3F4F6;color:#374151;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Producto</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Categoría</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Stock actual</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Umbral mínimo</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Estado</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Acción</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($items)): ?>
      <tr><td colspan="6" style="padding:40px;text-align:center;color:#9CA3AF;font-size:.875rem">No hay productos en inventario.</td></tr>
      <?php endif; ?>
      <?php foreach ($items as $item): ?>
      <?php $bajo = (int)$item['stock'] <= (int)$item['umbral_minimo']; ?>
      <tr style="border-bottom:1px solid #F3F4F6;<?= $bajo ? 'background:#FFFBEB' : '' ?>">
        <td style="padding:12px 16px">
          <div style="font-weight:600;font-size:.875rem;color:#111827"><?= htmlspecialchars($item['nombre']) ?></div>
          <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($item['presentacion'] ?? '') ?> · <?= htmlspecialchars($item['unidad']) ?></div>
        </td>
        <td style="padding:12px 16px;font-size:.875rem;color:#374151"><?= htmlspecialchars($item['categoria_nombre']) ?></td>
        <td style="padding:12px 16px;text-align:center;font-weight:700;font-size:1rem;color:<?= $bajo ? '#991B1B' : '#111827' ?>">
          <?= $item['stock'] ?>
        </td>
        <td style="padding:12px 16px;text-align:center;font-size:.875rem;color:#6B7280"><?= $item['umbral_minimo'] ?></td>
        <td style="padding:12px 16px;text-align:center">
          <?php if ($bajo): ?>
            <span style="background:#FEF3C7;color:#92400E;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">Stock bajo</span>
          <?php else: ?>
            <span style="background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">OK</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <button type="button"
                  onclick="abrirModal(<?= $item['id'] ?>, '<?= htmlspecialchars(addslashes($item['nombre'])) ?>')"
                  style="padding:5px 14px;background:var(--color-primary);color:#fff;border:none;border-radius:6px;font-size:.8rem;font-weight:600;cursor:pointer">
            Ajustar
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($paginacion['last_page'] > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
    <a href="?page=<?= $i ?>&buscar=<?= urlencode($filtros['buscar']) ?>&stock_bajo=<?= $filtros['stock_bajo'] ? '1' : '' ?>"
       style="padding:6px 12px;border-radius:6px;font-size:.875rem;text-decoration:none;<?= $i === $paginacion['current_page'] ? 'background:var(--color-primary);color:#fff;font-weight:700' : 'background:#fff;border:1px solid #D1D5DB;color:#374151' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>

<script>
function abrirModal(id, nombre) {
  document.getElementById('ajuste_id').value = id;
  document.getElementById('ajuste_nombre').textContent = nombre;
  const m = document.getElementById('modal-ajuste');
  m.style.display = 'flex';
}
function cerrarModal() {
  document.getElementById('modal-ajuste').style.display = 'none';
}
document.getElementById('modal-ajuste').addEventListener('click', function(e) {
  if (e.target === this) cerrarModal();
});
</script>
