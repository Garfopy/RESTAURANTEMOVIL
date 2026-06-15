<?php
// Vista: Formulario de combo (nuevo/editar)
$esEdicion = !empty($combo);
$accion    = $esEdicion
    ? BASE_URL . 'empresa-combo/actualizar/' . $combo['id']
    : BASE_URL . 'empresa-combo/guardar';

$comboItems      = $combo['items'] ?? [];
$comboCompradores = array_column($combo['compradores'] ?? [], 'comprador_id');
?>
<style>
.seccion-guia { background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:24px;margin-bottom:16px; }
.seccion-guia h3 { font-size:.95rem;font-weight:700;color:#111827;margin:0 0 4px 0; }
.campo-label { display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px; }
.input-base { width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:7px;font-size:.875rem;box-sizing:border-box;background:#fff; }
.item-row { display:grid;grid-template-columns:1fr 120px auto;gap:8px;align-items:end;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:10px;margin-bottom:6px; }
</style>

<div style="max-width:760px">
  <a href="<?= BASE_URL ?>empresa-combo/index"
     style="display:inline-flex;align-items:center;gap:4px;font-size:.875rem;color:#6B7280;text-decoration:none;margin-bottom:20px">
    ← Volver a combos
  </a>

  <div style="background:linear-gradient(135deg,#1F2937 0%,#374151 100%);border-radius:12px;padding:20px 24px;margin-bottom:20px;color:#fff">
    <h2 style="margin:0;font-size:1.05rem;font-weight:700"><?= $esEdicion ? 'Editar combo' : 'Nuevo combo' ?></h2>
    <p style="margin:4px 0 0 0;font-size:.78rem;opacity:.75">
      <?= $esEdicion ? 'Modifica los productos y compradores asignados.' : 'Define los productos del combo y a qué compradores aplica.' ?>
    </p>
  </div>

  <form method="POST" action="<?= $accion ?>">

    <!-- Sección 1: Datos generales -->
    <div class="seccion-guia">
      <h3>Datos del combo</h3>
      <p style="font-size:.78rem;color:#6B7280;margin-bottom:16px">Un combo es un pedido predefinido que el comprador puede cargar con un clic.</p>
      <div style="display:grid;gap:14px">
        <div>
          <label class="campo-label">Nombre del combo *</label>
          <input type="text" name="nombre" required class="input-base"
                 value="<?= htmlspecialchars($combo['nombre'] ?? '') ?>"
                 placeholder="Ej: Pedido semanal estándar · Kit restaurante · Surtido lunes">
        </div>
        <div>
          <label class="campo-label">Descripción (opcional)</label>
          <textarea name="descripcion" rows="2" class="input-base"
                    placeholder="Describe qué incluye este combo y cuándo usarlo."
                    style="resize:vertical"><?= htmlspecialchars($combo['descripcion'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="campo-label">Precio del combo (opcional)</label>
          <div style="display:flex;gap:8px;align-items:stretch;flex-wrap:wrap">
            <div style="position:relative;flex:1;min-width:180px">
              <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#6B7280;font-weight:600">$</span>
              <input type="number" id="comboPrecio" name="precio" min="0" step="0.01"
                     value="<?= isset($combo['precio']) && $combo['precio'] !== null ? htmlspecialchars((string)$combo['precio']) : '' ?>"
                     class="input-base" placeholder="0.00" style="padding-left:24px">
            </div>
            <button type="button" onclick="calcularPrecioCombo()"
                    title="Calcula la suma de los productos y aplica un 10% de descuento"
                    style="padding:9px 14px;border:1px solid var(--color-primary);border-radius:7px;background:#fff;color:var(--color-primary);font-size:.8rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:6px">
              <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 5a2 2 0 012-2h10a2 2 0 012 2v14a2 2 0 01-2 2H7a2 2 0 01-2-2V5z"/></svg>
              Calcular (-10%)
            </button>
          </div>
          <p style="font-size:.72rem;color:#6B7280;margin:6px 0 0">
            Déjalo vacío para usar la suma normal de los productos. El botón suma los precios base × cantidades y aplica un 10% de descuento.
          </p>
          <p id="comboPrecioDetalle" style="font-size:.72rem;color:#9CA3AF;margin:4px 0 0;display:none"></p>
        </div>
      </div>
    </div>

    <!-- Sección 2: Productos -->
    <div class="seccion-guia">
      <h3>Productos del combo</h3>
      <p style="font-size:.78rem;color:#6B7280;margin-bottom:14px">Agrega los productos y cantidades que forman este combo.</p>

      <div id="itemsContainer">
        <?php foreach ($comboItems as $i => $ci): ?>
        <div class="item-row">
          <div>
            <label style="font-size:.7rem;color:#6B7280;font-weight:600">Producto</label>
            <select name="producto_id[]" class="input-base" required>
              <option value="">— Seleccionar —</option>
              <?php foreach ($productos as $prod): ?>
              <option value="<?= $prod['id'] ?>" <?= $prod['id'] == $ci['producto_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($prod['nombre']) ?> (<?= $prod['presentacion'] ?>)
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label style="font-size:.7rem;color:#6B7280;font-weight:600">Cantidad</label>
            <input type="number" name="cantidad[]" min="0.1" step="0.1"
                   value="<?= $ci['cantidad'] ?>"
                   class="input-base" required>
          </div>
          <button type="button" onclick="this.closest('.item-row').remove()"
                  style="padding:7px 10px;border:1px solid #FCA5A5;border-radius:6px;background:#FEF2F2;color:#DC2626;cursor:pointer;margin-top:18px">✕</button>
        </div>
        <?php endforeach; ?>
        <?php if (empty($comboItems)): ?>
        <div id="itemsEmpty" style="padding:14px;background:#F9FAFB;border:1px dashed #E5E7EB;border-radius:8px;text-align:center;font-size:.8rem;color:#9CA3AF">
          Sin productos — usa el botón para agregar.
        </div>
        <?php endif; ?>
      </div>
      <button type="button" onclick="agregarItem()"
              style="margin-top:10px;padding:7px 16px;border:1px solid var(--color-primary);border-radius:7px;background:#fff;color:var(--color-primary);font-size:.8rem;cursor:pointer;font-weight:600">
        + Agregar producto
      </button>
    </div>

    <!-- Sección 3: Compradores -->
    <div class="seccion-guia">
      <h3>Compradores que verán este combo</h3>
      <p style="font-size:.78rem;color:#6B7280;margin-bottom:14px">Selecciona qué compradores pueden cargar este combo en su carrito.</p>
      <div style="display:flex;flex-direction:column;gap:8px;max-height:220px;overflow-y:auto;padding:2px">
        <?php if (empty($compradores)): ?>
        <p style="font-size:.8rem;color:#9CA3AF">No hay compradores registrados en tu empresa.</p>
        <?php else: ?>
        <?php foreach ($compradores as $c): ?>
        <label style="display:flex;align-items:center;gap:8px;font-size:.875rem;color:#374151;cursor:pointer;padding:8px 12px;border-radius:8px;border:1px solid #E5E7EB;background:#fff;hover:background:#F9FAFB">
          <input type="checkbox" name="comprador_id[]" value="<?= $c['id'] ?>"
                 <?= in_array($c['id'], $comboCompradores) ? 'checked' : '' ?>>
          <?= htmlspecialchars($c['nombre'] . ' ' . $c['apellido_paterno']) ?>
        </label>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Botones -->
    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit"
              style="padding:11px 28px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer">
        <?= $esEdicion ? 'Guardar cambios' : 'Crear combo' ?>
      </button>
      <a href="<?= BASE_URL ?>empresa-combo/index"
         style="padding:11px 20px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#374151;text-decoration:none;font-weight:500">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
const productosOpciones = <?php
    $opts = [];
    foreach ($productos as $prod) {
        $opts[] = ['id' => $prod['id'], 'nombre' => htmlspecialchars($prod['nombre']), 'presentacion' => $prod['presentacion']];
    }
    echo json_encode($opts);
?>;

const productosPrecios = <?php
    $precios = [];
    foreach ($productos as $prod) {
        $precios[(int)$prod['id']] = (float)($prod['precio_base'] ?? 0);
    }
    echo json_encode($precios);
?>;

function calcularPrecioCombo() {
    const cont = document.getElementById('itemsContainer');
    const filas = cont.querySelectorAll('.item-row');
    let subtotal = 0;
    let lineas = 0;
    filas.forEach(row => {
        const sel = row.querySelector('select[name="producto_id[]"]');
        const cant = row.querySelector('input[name="cantidad[]"]');
        if (!sel || !cant) return;
        const pid = parseInt(sel.value, 10);
        const c = parseFloat(cant.value);
        if (!pid || !c || isNaN(c)) return;
        const precio = productosPrecios[pid] || 0;
        subtotal += precio * c;
        lineas++;
    });
    const detalle = document.getElementById('comboPrecioDetalle');
    if (lineas === 0) {
        detalle.style.display = 'block';
        detalle.style.color = '#DC2626';
        detalle.textContent = 'Agrega al menos un producto con cantidad para calcular el precio.';
        return;
    }
    const conDescuento = subtotal * 0.9;
    document.getElementById('comboPrecio').value = conDescuento.toFixed(2);
    detalle.style.display = 'block';
    detalle.style.color = '#059669';
    detalle.textContent = `Subtotal: $${subtotal.toFixed(2)} − 10% = $${conDescuento.toFixed(2)} (${lineas} producto${lineas>1?'s':''})`;
}

function agregarItem() {
    const cont = document.getElementById('itemsContainer');
    const empty = document.getElementById('itemsEmpty');
    if (empty) empty.remove();

    const div = document.createElement('div');
    div.className = 'item-row';

    let optionsHtml = '<option value="">— Seleccionar —</option>';
    productosOpciones.forEach(p => {
        optionsHtml += `<option value="${p.id}">${p.nombre} (${p.presentacion})</option>`;
    });

    div.innerHTML = `
      <div>
        <label style="font-size:.7rem;color:#6B7280;font-weight:600">Producto</label>
        <select name="producto_id[]" class="input-base" required>${optionsHtml}</select>
      </div>
      <div>
        <label style="font-size:.7rem;color:#6B7280;font-weight:600">Cantidad</label>
        <input type="number" name="cantidad[]" min="0.1" step="0.1" class="input-base" required>
      </div>
      <button type="button" onclick="this.closest('.item-row').remove()"
              style="padding:7px 10px;border:1px solid #FCA5A5;border-radius:6px;background:#FEF2F2;color:#DC2626;cursor:pointer;margin-top:18px">✕</button>
    `;
    cont.appendChild(div);
}
</script>
