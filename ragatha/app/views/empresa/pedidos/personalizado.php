<?php
$baseUrl = BASE_URL;
?>

<div style="display:flex;gap:10px;margin-bottom:20px;align-items:center">
  <a href="<?= $baseUrl ?>empresa-pedido"
     style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px">
    ← Pedidos
  </a>
  <span style="color:#D1D5DB">/</span>
  <span style="font-size:.85rem;color:#111827;font-weight:600">Pedido Personalizado</span>
</div>

<!-- Flash -->
<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<p style="font-size:.85rem;color:#6B7280;padding:12px 16px;background:#F0F9FF;border:1px solid #BAE6FD;border-radius:8px;margin-bottom:24px">
  Crea un pedido en nombre de un comprador con precios negociados. El comprador lo verá en su historial como un pedido confirmado.
</p>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:28px;max-width:800px">
  <form method="POST" action="<?= $baseUrl ?>empresa-pedido/guardarPersonalizado" id="formPersonalizado">

    <!-- Comprador -->
    <div style="margin-bottom:20px">
      <label style="display:block;font-size:.875rem;font-weight:700;color:#374151;margin-bottom:8px">
        Comprador <span style="color:#DC2626">*</span>
      </label>
      <?php if (empty($compradores)): ?>
        <div style="padding:12px;background:#FEF3C7;border-radius:8px;font-size:.85rem;color:#92400E">
          No hay compradores registrados. <a href="<?= $baseUrl ?>empresa-usuario/nuevo" style="color:#D97706">Crear comprador</a>
        </div>
      <?php else: ?>
      <select name="comprador_id" required style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;background:#fff">
        <option value="">— Selecciona un comprador —</option>
        <?php foreach ($compradores as $c): ?>
        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido_paterno']) ?> (<?= htmlspecialchars($c['email']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <?php endif; ?>
    </div>

    <!-- Fecha de entrega -->
    <div style="margin-bottom:20px">
      <label style="display:block;font-size:.875rem;font-weight:700;color:#374151;margin-bottom:8px">Fecha de entrega (opcional)</label>
      <input type="date" name="fecha_entrega" min="<?= date('Y-m-d') ?>"
             style="padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
    </div>

    <!-- Líneas de productos -->
    <div style="margin-bottom:20px">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
        <label style="font-size:.875rem;font-weight:700;color:#374151">Productos <span style="color:#DC2626">*</span></label>
        <button type="button" onclick="agregarLinea()"
                style="padding:6px 14px;background:#059669;color:#fff;border:none;border-radius:6px;font-size:.8rem;font-weight:600;cursor:pointer">
          + Agregar producto
        </button>
      </div>

      <div id="lineasContainer">
        <!-- Línea 1 por defecto -->
        <div class="linea" style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:center">
          <select name="producto_id[]" onchange="completarPrecio(this)" required
                  style="padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.82rem;background:#fff">
            <option value="">— Producto —</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio_base'] ?>"><?= htmlspecialchars($p['nombre']) ?> (<?= $p['presentacion'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <input type="number" name="cantidad[]" placeholder="Cant." min="0.01" step="0.01" required
                 onchange="calcularTotal()"
                 style="padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;width:100%;box-sizing:border-box">
          <div style="position:relative">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem">$</span>
            <input type="number" name="precio_unit[]" placeholder="Precio" min="0.01" step="0.01" required
                   onchange="calcularTotal()"
                   style="padding:9px 12px 9px 20px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;width:100%;box-sizing:border-box">
          </div>
          <button type="button" onclick="this.closest('.linea').remove(); calcularTotal()"
                  style="padding:8px 10px;border:1px solid #FECACA;background:#FEF2F2;color:#DC2626;border-radius:8px;cursor:pointer;font-size:.9rem">✕</button>
        </div>
      </div>

      <!-- Header columnas -->
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;margin-bottom:4px">
        <div style="font-size:.7rem;color:#9CA3AF;padding:0 12px">Producto</div>
        <div style="font-size:.7rem;color:#9CA3AF;padding:0 12px">Cantidad</div>
        <div style="font-size:.7rem;color:#9CA3AF;padding:0 12px">Precio unit.</div>
        <div></div>
      </div>

      <!-- Total -->
      <div style="margin-top:12px;padding:12px 16px;background:#F9FAFB;border-radius:8px;display:flex;justify-content:space-between;align-items:center">
        <span style="font-size:.85rem;font-weight:600;color:#374151">Total del pedido</span>
        <span id="totalDisplay" style="font-size:1.2rem;font-weight:800;color:#111827">$0.00</span>
      </div>
    </div>

    <!-- Notas -->
    <div style="margin-bottom:24px">
      <label style="display:block;font-size:.875rem;font-weight:700;color:#374151;margin-bottom:8px">Nota interna (opcional)</label>
      <textarea name="notas" rows="2" placeholder="Condiciones especiales, descuentos acordados, etc..."
                style="width:100%;padding:10px 14px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;resize:vertical;box-sizing:border-box"></textarea>
    </div>

    <div style="display:flex;gap:12px">
      <button type="submit" style="flex:1;padding:12px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.9rem;cursor:pointer">
        Crear Pedido Personalizado
      </button>
      <a href="<?= $baseUrl ?>empresa-pedido"
         style="padding:12px 20px;border:1px solid #D1D5DB;border-radius:8px;color:#374151;text-decoration:none;font-size:.875rem;display:flex;align-items:center">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
// Plantilla de línea
const productosData = <?= json_encode(array_map(fn($p) => ['id'=>$p['id'],'nombre'=>$p['nombre'],'precio'=>$p['precio_base'],'presentacion'=>$p['presentacion']], $productos)) ?>;

function getSelectHtml() {
  let opts = '<option value="">— Producto —</option>';
  productosData.forEach(p => {
    opts += `<option value="${p.id}" data-precio="${p.precio}">${p.nombre} (${p.presentacion})</option>`;
  });
  return opts;
}

function agregarLinea() {
  const c = document.getElementById('lineasContainer');
  const div = document.createElement('div');
  div.className = 'linea';
  div.style.cssText = 'display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:8px;margin-bottom:8px;align-items:center';
  div.innerHTML = `
    <select name="producto_id[]" onchange="completarPrecio(this)" required
            style="padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.82rem;background:#fff">
      ${getSelectHtml()}
    </select>
    <input type="number" name="cantidad[]" placeholder="Cant." min="0.01" step="0.01" required
           onchange="calcularTotal()"
           style="padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;width:100%;box-sizing:border-box">
    <div style="position:relative">
      <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.8rem">$</span>
      <input type="number" name="precio_unit[]" placeholder="Precio" min="0.01" step="0.01" required
             onchange="calcularTotal()"
             style="padding:9px 12px 9px 20px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;width:100%;box-sizing:border-box">
    </div>
    <button type="button" onclick="this.closest('.linea').remove(); calcularTotal()"
            style="padding:8px 10px;border:1px solid #FECACA;background:#FEF2F2;color:#DC2626;border-radius:8px;cursor:pointer;font-size:.9rem">✕</button>
  `;
  c.appendChild(div);
}

function completarPrecio(select) {
  const opt = select.options[select.selectedIndex];
  const precio = opt.dataset.precio;
  const linea = select.closest('.linea');
  const precioInput = linea.querySelector('input[name="precio_unit[]"]');
  if (precio && precioInput && !precioInput.value) {
    precioInput.value = parseFloat(precio).toFixed(2);
    calcularTotal();
  }
}

function calcularTotal() {
  let total = 0;
  document.querySelectorAll('.linea').forEach(linea => {
    const cant  = parseFloat(linea.querySelector('input[name="cantidad[]"]')?.value) || 0;
    const prec  = parseFloat(linea.querySelector('input[name="precio_unit[]"]')?.value) || 0;
    total += cant * prec;
  });
  document.getElementById('totalDisplay').textContent = '$' + total.toFixed(2);
}
</script>
