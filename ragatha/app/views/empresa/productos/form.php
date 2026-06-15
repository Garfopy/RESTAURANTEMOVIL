<?php
// Vista: Formulario alta/edición de producto — UI guiada por secciones
$esEdicion = !empty($producto);
$accion    = $esEdicion ? BASE_URL . 'empresa-producto/actualizar/' . $producto['id'] : BASE_URL . 'empresa-producto/guardar';

// Calcular si ya tiene escalonados para saber si mostrar la sección abierta
$escalonados = $producto['escalonados'] ?? [];
$tieneEscalonados = !empty($escalonados);
?>
<style>
.seccion-guia {
  background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:24px;margin-bottom:16px;
}
.seccion-guia h3 {
  font-size:.95rem;font-weight:700;color:#111827;margin:0 0 4px 0;
}
.seccion-guia .hint {
  font-size:.78rem;color:#6B7280;margin-bottom:18px;line-height:1.5;
}
.campo-label {
  display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px;
}
.campo-hint { font-size:.72rem;color:#9CA3AF;margin-top:3px; }
.input-base {
  width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:7px;
  font-size:.875rem;box-sizing:border-box;background:#fff;
}
.input-base:focus { outline:none;border-color:var(--color-primary);box-shadow:0 0 0 2px rgba(0,0,0,.06); }
.precio-preview {
  background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px;padding:12px 14px;
  font-size:.8rem;color:#166534;margin-top:10px;
}
.escalon-row {
  display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:end;
  background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:12px;margin-bottom:8px;
}
.badge-paso {
  display:inline-flex;align-items:center;justify-content:center;
  width:22px;height:22px;border-radius:50%;background:var(--color-primary);
  color:#fff;font-size:.7rem;font-weight:700;margin-right:8px;flex-shrink:0;
}
</style>

<div style="max-width:820px">
  <a href="<?= BASE_URL ?>empresa-producto/index"
     style="display:inline-flex;align-items:center;gap:4px;font-size:.875rem;color:#6B7280;text-decoration:none;margin-bottom:20px">
    ← Volver al catálogo
  </a>

  <!-- Encabezado con guía visual -->
  <div style="background:linear-gradient(135deg,#1F2937 0%,#374151 100%);border-radius:12px;padding:20px 24px;margin-bottom:20px;color:#fff">
    <div style="display:flex;align-items:center;gap:12px">
      <div>
        <h2 style="margin:0;font-size:1.05rem;font-weight:700"><?= $esEdicion ? 'Editar producto' : 'Nuevo producto' ?></h2>
        <p style="margin:4px 0 0 0;font-size:.78rem;opacity:.75">
          <?= $esEdicion ? 'Modifica los datos de ' . htmlspecialchars($producto['nombre']) : 'Completa las 3 secciones para dejar el producto listo para venta' ?>
        </p>
      </div>
    </div>
    <?php if (!$esEdicion): ?>
    <div style="display:flex;gap:6px;margin-top:14px;flex-wrap:wrap">
      <?php foreach (['1 Información básica','2 Precios y rangos','3 Stock inicial'] as $paso): ?>
      <div style="font-size:.72rem;background:rgba(255,255,255,.12);border-radius:20px;padding:4px 10px;font-weight:600"><?= $paso ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <form method="POST" action="<?= $accion ?>" enctype="multipart/form-data">

    <!-- ═══ SECCIÓN 1: Información básica ═══ -->
    <div class="seccion-guia">
      <h3><span class="badge-paso">1</span>Información básica del producto</h3>
      <p class="hint">Estos datos son los que verán tus compradores en el catálogo. Una buena descripción y foto aumentan los pedidos.</p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <!-- Nombre -->
        <div style="grid-column:1/-1">
          <label class="campo-label">Nombre del producto *</label>
          <input type="text" name="nombre" required class="input-base"
                 value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>"
                 placeholder="Ej: Costilla de res premium · Falda marinada · Lomo de cerdo">
          <p class="campo-hint">Sé específico: incluye el corte, tipo de carne y calidad.</p>
        </div>

        <!-- Categoría -->
        <div>
          <label class="campo-label">Categoría *</label>
          <select name="categoria_id" required class="input-base">
            <option value="">Selecciona una categoría...</option>
            <?php foreach ($categorias as $cat): ?>
              <option value="<?= $cat['id'] ?>" <?= ($producto['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($cat['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Presentación -->
        <div>
          <label class="campo-label">Unidad de venta *</label>
          <select name="presentacion" class="input-base">
            <option value="kg"    <?= ($producto['presentacion'] ?? 'kg') === 'kg'    ? 'selected' : '' ?>>Kilogramos (kg)</option>
            <option value="caja"  <?= ($producto['presentacion'] ?? '') === 'caja'  ? 'selected' : '' ?>>Caja</option>
            <option value="pieza" <?= ($producto['presentacion'] ?? '') === 'pieza' ? 'selected' : '' ?>>Pieza</option>
          </select>
          <p class="campo-hint">Define cómo se mide y vende este producto.</p>
        </div>

        <!-- Descripción -->
        <div style="grid-column:1/-1">
          <label class="campo-label">Descripción</label>
          <textarea name="descripcion" rows="2" class="input-base"
                    placeholder="Ej: Corte de primera, refrigerado mismo día. Ideal para taquerías y restaurantes."
                    style="resize:vertical"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
        </div>

        <!-- Imagen -->
        <div style="grid-column:1/-1">
          <label class="campo-label">Imagen del producto</label>
          <?php if (!empty($producto['imagen'])): ?>
          <div style="margin-bottom:8px;display:flex;align-items:center;gap:10px">
            <img src="<?= htmlspecialchars($producto['imagen']) ?>" alt="" id="imgPreview"
                 style="width:64px;height:64px;border-radius:8px;object-fit:cover;border:1px solid #E5E7EB">
            <span style="font-size:.75rem;color:#6B7280">Imagen actual. Sube otra para reemplazarla.</span>
          </div>
          <?php else: ?>
          <div style="margin-bottom:8px">
            <img src="" alt="" id="imgPreview" style="width:64px;height:64px;border-radius:8px;object-fit:cover;border:1px dashed #D1D5DB;display:none">
          </div>
          <?php endif; ?>
          <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp" id="inputImagen"
                 style="font-size:.875rem;color:#374151" onchange="previewImg(this)">
          <p class="campo-hint">JPG, PNG o WebP · máx 2 MB. La imagen aparece en el catálogo del comprador.</p>
        </div>
      </div>
    </div>

    <!-- ═══ SECCIÓN 2: Precios ═══ -->
    <div class="seccion-guia">
      <h3><span class="badge-paso">2</span>Precios de venta</h3>
      <p class="hint">
        Define el <strong>precio base</strong> (precio estándar de lista) y opcionalmente agrega
        <strong>rangos de descuento por volumen</strong> para compradores que piden más cantidad.
        Si un comprador tiene precio especial acordado, ese prevalecerá sobre todo.
      </p>

      <!-- Precio base -->
      <div style="display:grid;grid-template-columns:200px 1fr;gap:16px;align-items:start;margin-bottom:20px">
        <div>
          <label class="campo-label">Precio base (MXN) *</label>
          <div style="position:relative">
            <span style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-size:.875rem">$</span>
            <input type="number" name="precio_base" required min="0" step="0.01" id="precioBase"
                   value="<?= $producto['precio_base'] ?? '' ?>"
                   placeholder="0.00"
                   oninput="actualizarPreview()"
                   style="width:100%;padding:9px 12px 9px 22px;border:1px solid #D1D5DB;border-radius:7px;font-size:.875rem;box-sizing:border-box">
          </div>
          <p class="campo-hint">Por <?= htmlspecialchars($producto['presentacion'] ?? 'unidad') ?>. Este precio aplica cuando no hay rango de volumen que coincida.</p>
        </div>
        <div id="precioPreview" style="display:none" class="precio-preview">
          <strong>Vista previa:</strong> El comprador verá "<strong id="prevNombre">este producto</strong>"
          a <strong id="prevPrecio">$0.00</strong> por <span id="prevPresentacion">kg</span>.
        </div>
      </div>

      <!-- Precios escalonados -->
      <div style="border-top:1px solid #F3F4F6;padding-top:18px">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px">
          <div>
            <div style="font-size:.85rem;font-weight:700;color:#374151">Precios por volumen (opcional)</div>
            <div style="font-size:.72rem;color:#9CA3AF">Ej: 1–10 kg a $180 · 11–50 kg a $165 · 51+ kg a $150</div>
          </div>
          <button type="button" onclick="agregarEscalon()"
                  style="padding:6px 14px;border:1px solid var(--color-primary);border-radius:7px;background:#fff;color:var(--color-primary);font-size:.8rem;cursor:pointer;font-weight:600">
            + Agregar rango
          </button>
        </div>

        <!-- Leyenda de columnas -->
        <?php if (!empty($escalonados)): ?>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;padding:0 12px;margin-bottom:4px">
          <span style="font-size:.7rem;color:#9CA3AF;font-weight:600">Cantidad mínima</span>
          <span style="font-size:.7rem;color:#9CA3AF;font-weight:600">Cantidad máxima</span>
          <span style="font-size:.7rem;color:#9CA3AF;font-weight:600">Precio MXN por unidad</span>
          <span></span>
        </div>
        <?php endif; ?>

        <div id="escalonados">
          <?php foreach ($escalonados as $i => $esc): ?>
          <div class="escalon-row">
            <div>
              <?php if ($i === 0): ?><label style="font-size:.7rem;color:#6B7280;font-weight:600">Desde (min)</label><?php endif; ?>
              <input type="number" name="esc_cant_min[]" min="0" step="0.1" value="<?= $esc['cantidad_min'] ?>"
                     placeholder="Ej: 1"
                     style="width:100%;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.85rem;box-sizing:border-box">
            </div>
            <div>
              <?php if ($i === 0): ?><label style="font-size:.7rem;color:#6B7280;font-weight:600">Hasta (vacío = sin límite)</label><?php endif; ?>
              <input type="number" name="esc_cant_max[]" min="0" step="0.1" value="<?= $esc['cantidad_max'] ?? '' ?>"
                     placeholder="Dejar vacío para infinito"
                     style="width:100%;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.85rem;box-sizing:border-box">
            </div>
            <div>
              <?php if ($i === 0): ?><label style="font-size:.7rem;color:#6B7280;font-weight:600">Precio ($MXN)</label><?php endif; ?>
              <input type="number" name="esc_precio[]" min="0" step="0.01" value="<?= $esc['precio'] ?>"
                     placeholder="Ej: 165.00"
                     style="width:100%;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.85rem;box-sizing:border-box">
            </div>
            <button type="button" onclick="this.closest('.escalon-row').remove()"
                    style="padding:7px 10px;border:1px solid #FCA5A5;border-radius:6px;background:#FEF2F2;color:#DC2626;cursor:pointer;font-size:.85rem;margin-top:<?= $i === 0 ? '14px' : '0' ?>">✕</button>
          </div>
          <?php endforeach; ?>
        </div>

        <?php if (empty($escalonados)): ?>
        <div id="escalonadosEmpty" style="padding:14px;background:#F9FAFB;border:1px dashed #E5E7EB;border-radius:8px;text-align:center;font-size:.8rem;color:#9CA3AF">
          Sin rangos de descuento — aplica el precio base para todas las cantidades.<br>
          <button type="button" onclick="agregarEscalon()" style="margin-top:6px;padding:5px 12px;border:1px solid #D1D5DB;border-radius:6px;background:#fff;font-size:.78rem;cursor:pointer;color:#374151">
            + Agregar primer rango de descuento
          </button>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ═══ SECCIÓN 3: Inventario ═══ -->
    <?php if (!$esEdicion): ?>
    <div class="seccion-guia">
      <h3><span class="badge-paso">3</span>Stock inicial</h3>
      <p class="hint">
        El stock se usa internamente para que tú sepas cuánto tienes disponible.
        <strong>Los compradores no ven el stock</strong> — solo ven el catálogo y piden lo que necesitan.
        Podrás registrar entradas y salidas desde el módulo de Control de Stock.
      </p>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <label class="campo-label">Stock inicial</label>
          <input type="number" name="stock_inicial" min="0" step="0.1" value="0" class="input-base">
          <p class="campo-hint">Unidades con las que arranca este producto en tu inventario.</p>
        </div>
        <div>
          <label class="campo-label">Alerta de stock bajo</label>
          <input type="number" name="umbral_minimo" min="0" step="0.1" value="10" class="input-base">
          <p class="campo-hint">Cuando el stock caiga por debajo de este número, aparecerá en rojo en tu inventario.</p>
        </div>
      </div>
    </div>
    <?php else: ?>
    <div class="seccion-guia">
      <h3>Alerta de stock bajo</h3>
      <p class="hint">Define cuándo el sistema te debe alertar que el stock de este producto es crítico.</p>
      <div style="max-width:220px">
        <label class="campo-label">Umbral de alerta</label>
        <input type="number" name="umbral_minimo" min="0" step="0.1"
               value="<?= $producto['umbral_minimo'] ?? 10 ?>" class="input-base">
      </div>
      <div style="margin-top:12px;padding:10px 14px;background:#F0F9FF;border:1px solid #BAE6FD;border-radius:7px;font-size:.78rem;color:#0369A1">
        Para ajustar el stock, usa <a href="<?= BASE_URL ?>empresa-inventario/movimiento/<?= $producto['id'] ?>" style="color:#0369A1;font-weight:700">Control de Stock</a>.
      </div>
    </div>
    <?php endif; ?>

    <!-- Botones -->
    <div style="display:flex;gap:12px;margin-top:8px">
      <button type="submit"
              style="padding:11px 28px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.9rem;font-weight:700;cursor:pointer">
        <?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?>
      </button>
      <a href="<?= BASE_URL ?>empresa-producto/index"
         style="padding:11px 20px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#374151;text-decoration:none;font-weight:500">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
function previewImg(input) {
  const img = document.getElementById('imgPreview');
  if (input.files && input.files[0]) {
    img.src = URL.createObjectURL(input.files[0]);
    img.style.display = 'block';
  }
}

function actualizarPreview() {
  const precio = parseFloat(document.getElementById('precioBase').value) || 0;
  const preview = document.getElementById('precioPreview');
  const nombre = document.querySelector('[name=nombre]').value || 'este producto';
  const presentacion = document.querySelector('[name=presentacion]').value || 'unidad';

  if (precio > 0) {
    preview.style.display = 'block';
    document.getElementById('prevNombre').textContent = nombre;
    document.getElementById('prevPrecio').textContent = '$' + precio.toLocaleString('es-MX', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('prevPresentacion').textContent = presentacion;
  } else {
    preview.style.display = 'none';
  }
}

// Actualizar preview cuando cambia nombre o presentación
document.addEventListener('DOMContentLoaded', function() {
  const campos = ['nombre', 'presentacion', 'precio_base'];
  campos.forEach(n => {
    const el = document.querySelector('[name='+n+']');
    if (el) el.addEventListener('input', actualizarPreview);
    if (el) el.addEventListener('change', actualizarPreview);
  });
  actualizarPreview();
});

function agregarEscalon() {
  const cont = document.getElementById('escalonados');
  const empty = document.getElementById('escalonadosEmpty');
  if (empty) empty.remove();

  const div = document.createElement('div');
  div.className = 'escalon-row';
  div.innerHTML = `
    <div><input type="number" name="esc_cant_min[]" min="0" step="0.1" placeholder="Ej: 1"
           style="width:100%;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.85rem;box-sizing:border-box"></div>
    <div><input type="number" name="esc_cant_max[]" min="0" step="0.1" placeholder="Vacío = sin límite"
           style="width:100%;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.85rem;box-sizing:border-box"></div>
    <div><input type="number" name="esc_precio[]" min="0" step="0.01" placeholder="Ej: 165.00"
           style="width:100%;padding:7px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.85rem;box-sizing:border-box"></div>
    <button type="button" onclick="this.closest('.escalon-row').remove()"
            style="padding:7px 10px;border:1px solid #FCA5A5;border-radius:6px;background:#FEF2F2;color:#DC2626;cursor:pointer;font-size:.85rem">✕</button>
  `;
  cont.appendChild(div);
}
</script>
