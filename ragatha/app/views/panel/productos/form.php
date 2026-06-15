<?php
// Variables: $producto (null = nuevo, array = editar), $categorias[]
$esEdicion = $producto !== null;
$accion    = $esEdicion
    ? BASE_URL . 'panel-producto/actualizar/' . $producto['id']
    : BASE_URL . 'panel-producto/guardar';
?>
<div style="max-width:860px">
  <a href="<?= BASE_URL ?>panel-producto/index"
     style="display:inline-flex;align-items:center;gap:6px;color:#6B7280;font-size:.875rem;text-decoration:none;margin-bottom:20px">
    ← Volver a productos
  </a>

  <form method="POST" action="<?= $accion ?>" enctype="multipart/form-data">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

      <!-- Columna izquierda -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
          <h3 style="font-size:.95rem;font-weight:700;color:#111827;margin:0 0 16px">Información general</h3>

          <div style="margin-bottom:14px">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Nombre *</label>
            <input type="text" name="nombre" required value="<?= htmlspecialchars($producto['nombre'] ?? '') ?>"
                   style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
          </div>

          <div style="margin-bottom:14px">
            <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Descripción</label>
            <textarea name="descripcion" rows="3"
                      style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;resize:vertical;outline:none"><?= htmlspecialchars($producto['descripcion'] ?? '') ?></textarea>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px">
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Categoría *</label>
              <select name="categoria_id" required style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box">
                <option value="">Seleccionar...</option>
                <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['id'] ?>" <?= ($producto['categoria_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cat['nombre']) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Unidad</label>
              <select name="unidad" style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box">
                <?php foreach (['kg','g','lt','ml','pza','caja','bolsa','rollo'] as $u): ?>
                <option value="<?= $u ?>" <?= ($producto['unidad'] ?? 'kg') === $u ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Precio base *</label>
              <input type="number" name="precio_base" step="0.01" min="0" required
                     value="<?= $producto['precio_base'] ?? '' ?>"
                     style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
            </div>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Presentación</label>
              <input type="text" name="presentacion" placeholder="Ej: Caja 10kg"
                     value="<?= htmlspecialchars($producto['presentacion'] ?? '') ?>"
                     style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
            </div>
          </div>
        </div>

        <!-- Inventario -->
        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
          <h3 style="font-size:.95rem;font-weight:700;color:#111827;margin:0 0 16px">Inventario</h3>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <?php if (!$esEdicion): ?>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Stock inicial</label>
              <input type="number" name="stock_inicial" min="0" value="0"
                     style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
            </div>
            <?php else: ?>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#6B7280;margin-bottom:5px">Stock actual</label>
              <div style="padding:8px 12px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;font-size:.875rem;font-weight:700;color:#111827">
                <?= $producto['stock'] ?? 0 ?> <?= htmlspecialchars($producto['unidad'] ?? '') ?>
              </div>
            </div>
            <?php endif; ?>
            <div>
              <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Umbral mínimo (alerta)</label>
              <input type="number" name="umbral_minimo" min="0"
                     value="<?= $producto['umbral_minimo'] ?? 10 ?>"
                     style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;box-sizing:border-box;outline:none">
            </div>
          </div>
          <?php if ($esEdicion): ?>
          <p style="margin:10px 0 0;font-size:.75rem;color:#9CA3AF">Para ajustar el stock usa el módulo de <a href="<?= BASE_URL ?>panel-inventario/index" style="color:var(--color-primary)">Inventario</a>.</p>
          <?php endif; ?>
        </div>
      </div>

      <!-- Columna derecha -->
      <div style="display:flex;flex-direction:column;gap:16px">

        <!-- Imagen -->
        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
          <h3 style="font-size:.95rem;font-weight:700;color:#111827;margin:0 0 16px">Imagen del producto</h3>
          <?php if (!empty($producto['imagen'])): ?>
          <img src="<?= htmlspecialchars($producto['imagen']) ?>"
               style="width:100%;height:160px;object-fit:cover;border-radius:8px;margin-bottom:12px;border:1px solid #E5E7EB">
          <?php endif; ?>
          <input type="file" name="imagen" accept=".jpg,.jpeg,.png,.webp"
                 style="width:100%;font-size:.8rem">
          <p style="margin:8px 0 0;font-size:.75rem;color:#9CA3AF">JPG, PNG o WebP · Máx. 2 MB</p>
        </div>

        <!-- Precios escalonados -->
        <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:20px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
            <h3 style="font-size:.95rem;font-weight:700;color:#111827;margin:0">Precios escalonados</h3>
            <button type="button" onclick="agregarFila()"
                    style="padding:4px 12px;background:#F3F4F6;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;cursor:pointer">+ Fila</button>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;margin-bottom:8px">
            <span style="font-size:.75rem;font-weight:600;color:#6B7280">Cant. mín</span>
            <span style="font-size:.75rem;font-weight:600;color:#6B7280">Cant. máx</span>
            <span style="font-size:.75rem;font-weight:600;color:#6B7280">Precio</span>
            <span></span>
          </div>
          <div id="escalonados">
            <?php if ($esEdicion && !empty($producto['escalonados'])): ?>
              <?php foreach ($producto['escalonados'] as $e): ?>
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;margin-bottom:6px" class="fila-esc">
                <input type="number" name="esc_cant_min[]" step="0.01" value="<?= $e['cantidad_min'] ?>"
                       placeholder="Mín" style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
                <input type="number" name="esc_cant_max[]" step="0.01" value="<?= $e['cantidad_max'] ?? '' ?>"
                       placeholder="Máx (opc)" style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
                <input type="number" name="esc_precio[]" step="0.01" value="<?= $e['precio'] ?>"
                       placeholder="Precio" style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
                <button type="button" onclick="this.closest('.fila-esc').remove()"
                        style="padding:6px 10px;background:#FEE2E2;border:none;border-radius:6px;color:#991B1B;cursor:pointer;font-size:.85rem">✕</button>
              </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;margin-bottom:6px" class="fila-esc">
                <input type="number" name="esc_cant_min[]" step="0.01" placeholder="Mín"
                       style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
                <input type="number" name="esc_cant_max[]" step="0.01" placeholder="Máx (opc)"
                       style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
                <input type="number" name="esc_precio[]" step="0.01" placeholder="Precio"
                       style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
                <button type="button" onclick="this.closest('.fila-esc').remove()"
                        style="padding:6px 10px;background:#FEE2E2;border:none;border-radius:6px;color:#991B1B;cursor:pointer;font-size:.85rem">✕</button>
              </div>
            <?php endif; ?>
          </div>
          <p style="margin:0;font-size:.75rem;color:#9CA3AF">Deja cantidad máx en blanco para "en adelante".</p>
        </div>

      </div>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px">
      <button type="submit"
              style="padding:10px 28px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
        <?= $esEdicion ? 'Guardar cambios' : 'Crear producto' ?>
      </button>
      <a href="<?= BASE_URL ?>panel-producto/index"
         style="padding:10px 20px;background:#F3F4F6;color:#374151;border-radius:8px;font-size:.875rem;text-decoration:none;font-weight:600">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
function agregarFila() {
  const cont = document.getElementById('escalonados');
  const div  = document.createElement('div');
  div.className = 'fila-esc';
  div.style.cssText = 'display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:6px;margin-bottom:6px';
  div.innerHTML = `
    <input type="number" name="esc_cant_min[]" step="0.01" placeholder="Mín" style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
    <input type="number" name="esc_cant_max[]" step="0.01" placeholder="Máx (opc)" style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
    <input type="number" name="esc_precio[]" step="0.01" placeholder="Precio" style="padding:6px 8px;border:1px solid #D1D5DB;border-radius:6px;font-size:.8rem;outline:none">
    <button type="button" onclick="this.closest('.fila-esc').remove()" style="padding:6px 10px;background:#FEE2E2;border:none;border-radius:6px;color:#991B1B;cursor:pointer;font-size:.85rem">✕</button>
  `;
  cont.appendChild(div);
}
</script>
