<?php ob_start(); ?>
<div>
  <a href="<?= BASE_URL ?>rest-menu/index"
     style="font-size:.85rem;color:#6B7280;text-decoration:none;display:inline-flex;align-items:center;gap:4px;margin-bottom:18px">
    ← Volver al menú
  </a>

  <!-- Indicador de pasos -->
  <div class="wizard-steps">
    <div class="wstep active" data-step="1">
      <div class="wstep-num">1</div>
      <div class="wstep-label">Información básica</div>
    </div>
    <div class="wstep-line"></div>
    <div class="wstep" data-step="2">
      <div class="wstep-num">2</div>
      <div class="wstep-label">Receta del platillo</div>
    </div>
    <div class="wstep-line"></div>
    <div class="wstep" data-step="3">
      <div class="wstep-num">3</div>
      <div class="wstep-label">Revisar y guardar</div>
    </div>
  </div>

  <div class="rst-card" style="padding:28px;margin-bottom:0">
    <form method="POST" action="<?= BASE_URL ?>rest-menu/guardar" id="formPlatillo" enctype="multipart/form-data">
      <input type="hidden" name="id" value="<?= (int)($platillo['id'] ?? 0) ?>">

      <!-- ── Paso 1: Información básica ── -->
      <div class="wpane active" data-pane="1">
        <div style="font-weight:700;color:#111827;font-size:1.05rem;margin-bottom:4px">¿Qué platillo vas a vender?</div>
        <div style="font-size:.85rem;color:#6B7280;margin-bottom:20px">
          Llena los datos básicos. La foto y la categoría ayudan al cliente a encontrarlo.
        </div>

        <div class="form-group">
          <label class="form-label">Nombre del platillo *</label>
          <input type="text" name="nombre" id="inpNombre" required
                 class="form-input" placeholder="Ej: Tacos al pastor"
                 value="<?= htmlspecialchars($platillo['nombre'] ?? '') ?>">
        </div>

        <!-- Imagen del platillo -->
        <div class="form-group">
          <label class="form-label">📸 Foto del platillo
            <span style="color:#9CA3AF;font-weight:400">(JPG/PNG, máx 3MB)</span>
          </label>
          <div style="display:flex;gap:14px;align-items:flex-start">
            <div id="imgPreviewBox"
                 style="width:110px;height:110px;border-radius:12px;border:2px dashed #D1D5DB;
                        display:flex;align-items:center;justify-content:center;font-size:2rem;
                        background:#F9FAFB;color:#9CA3AF;overflow:hidden;flex-shrink:0">
              <?php if (!empty($platillo['imagen'])): ?>
              <img id="imgPreview" src="<?= BASE_URL . htmlspecialchars($platillo['imagen']) ?>"
                   style="width:100%;height:100%;object-fit:cover">
              <?php else: ?>
              <img id="imgPreview" src="" style="display:none;width:100%;height:100%;object-fit:cover">
              <span id="imgPlaceholder">🍽</span>
              <?php endif; ?>
            </div>
            <div style="flex:1">
              <input type="file" name="imagen" id="inpImg" accept="image/jpeg,image/png,image/webp"
                     onchange="previewImg(this)"
                     style="font-size:.85rem;width:100%;padding:8px;border:1px dashed #D1D5DB;border-radius:8px;background:#fff;cursor:pointer">
              <div style="font-size:.74rem;color:#6B7280;margin-top:6px;line-height:1.4">
                La foto reemplaza al emoji 🍽 en la tarjeta del menú y se ve en grande al abrir el detalle.<br>
                <strong>Recomendado:</strong> imagen cuadrada de <strong>800×800 px</strong> (1:1). Las imágenes muy rectangulares se recortarán para entrar al espacio.
              </div>
              <?php if (!empty($platillo['imagen'])): ?>
              <label style="display:inline-flex;align-items:center;gap:5px;margin-top:8px;font-size:.78rem;color:#DC2626;cursor:pointer">
                <input type="checkbox" name="quitar_imagen" value="1"> Quitar imagen actual
              </label>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">Categoría</label>
            <select name="categoria_id" id="inpCat" class="form-input">
              <option value="">— Sin categoría —</option>
              <?php foreach ($categorias as $c): ?>
              <option value="<?= $c['id'] ?>" <?= ($platillo['categoria_id'] ?? '') == $c['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Precio al cliente *</label>
            <div style="position:relative">
              <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9CA3AF;font-weight:600">$</span>
              <input type="number" name="precio" id="inpPrecio" required min="0" step="0.01"
                     class="form-input" style="padding-left:26px"
                     value="<?= (float)($platillo['precio'] ?? 0) ?>">
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Descripción <span style="color:#9CA3AF;font-weight:400">(recomendado — la ven los comensales)</span></label>
          <textarea name="descripcion" id="inpDesc" rows="2" class="form-textarea"
                    placeholder="Ej: Servidos en tortilla de maíz con piña, cilantro y cebolla."><?= htmlspecialchars($platillo['descripcion'] ?? '') ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">⏱ Tiempo de preparación</label>
            <div style="display:flex;align-items:center;gap:8px">
              <input type="number" name="tiempo_preparacion_min" min="1"
                     class="form-input" style="flex:1"
                     value="<?= (int)($platillo['tiempo_preparacion_min'] ?? 15) ?>">
              <span style="color:#6B7280;font-size:.85rem">min</span>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">📦 Disponible ahora</label>
            <select name="disponible" class="form-input">
              <option value="1" <?= ($platillo['disponible'] ?? 1) ? 'selected' : '' ?>>Sí, ya se puede pedir</option>
              <option value="0" <?= !($platillo['disponible'] ?? 1) ? 'selected' : '' ?>>Aún no</option>
            </select>
          </div>
        </div>

        <!-- Info para el cliente -->
        <div style="border:1.5px solid #E0E7FF;border-radius:12px;padding:16px;margin-top:8px;background:#F5F3FF">
          <div style="font-weight:700;color:#4C1D95;font-size:.9rem;margin-bottom:4px">
            🏷 Información para el cliente
          </div>
          <div style="font-size:.78rem;color:#6D28D9;margin-bottom:14px;line-height:1.45">
            Lo que captures aquí se muestra al comensal en el menú público:
            los <strong>alérgenos</strong> aparecen como badges amarillos sobre el platillo,
            y <strong>«Contiene»</strong> se muestra al abrir el detalle del platillo.
            Sirve para que el cliente decida con confianza si lo puede comer.
          </div>

          <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:.82rem">Alérgenos
              <span style="font-weight:400;color:#9CA3AF">— clic para marcar/desmarcar</span>
            </label>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:4px">
              <?php
              $alergenosActivos = array_map('trim', explode(',', $platillo['alergenos'] ?? ''));
              $alergenosList = ['Gluten','Lactosa','Mariscos','Frutos secos','Huevo','Soya','Cacahuate','Mostaza'];
              $alergenoColor = ['Gluten'=>'#FEF3C7:#92400E','Lactosa'=>'#DBEAFE:#1E40AF','Mariscos'=>'#CCFBF1:#065F46','Frutos secos'=>'#FEE2E2:#991B1B','Huevo'=>'#FEF9C3:#713F12','Soya'=>'#F3E8FF:#6B21A8','Cacahuate'=>'#FFEDD5:#9A3412','Mostaza'=>'#D1FAE5:#064E3B'];
              foreach ($alergenosList as $al):
                $partes = explode(':', $alergenoColor[$al]);
                $bg = $partes[0]; $fg = $partes[1];
                $checked = in_array($al, $alergenosActivos) ? 'checked' : '';
              ?>
              <label style="display:inline-flex;align-items:center;gap:5px;cursor:pointer;
                            background:<?= $bg ?>;color:<?= $fg ?>;border-radius:8px;padding:4px 10px;
                            font-size:.78rem;font-weight:600;border:1.5px solid transparent;
                            transition:.1s" class="alergen-lbl">
                <input type="checkbox" name="alergenos[]" value="<?= $al ?>" <?= $checked ?>
                       style="display:none" class="alergen-chk">
                <?= $al ?>
              </label>
              <?php endforeach; ?>
            </div>
            <div style="font-size:.73rem;color:#6B7280;margin-top:4px">Aparecen como badges amarillos «⚠ Gluten», «⚠ Lactosa», etc. en la tarjeta del platillo.</div>
          </div>

          <div class="form-group" style="margin-bottom:10px">
            <label class="form-label" style="font-size:.82rem">Contiene <span style="font-weight:400;color:#9CA3AF">(ingredientes no medibles)</span></label>
            <input type="text" name="contiene" class="form-input"
                   placeholder="Ej: pimienta negra, cilantro, chile de árbol, comino"
                   value="<?= htmlspecialchars($platillo['contiene'] ?? '') ?>">
            <div style="font-size:.73rem;color:#6B7280;margin-top:4px">Se muestra dentro del modal del platillo, en la sección «Información del platillo». Útil para especias y condimentos que no entran en la receta.</div>
          </div>

          <div class="form-group" style="margin-bottom:0">
            <label class="form-label" style="font-size:.82rem">🍺 Ingrediente directo de inventario <span style="font-weight:400;color:#9CA3AF">(para bebidas / postres sin receta)</span></label>
            <select name="ingrediente_directo_id" class="form-input">
              <option value="">— Sin vínculo directo —</option>
              <?php foreach ($ingredientes as $ing): ?>
              <option value="<?= (int)$ing['id'] ?>"
                <?= ((int)($platillo['ingrediente_directo_id'] ?? 0)) === (int)$ing['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($ing['nombre']) ?> (<?= htmlspecialchars($ing['unidad_principal']) ?>)<?= $ing['proveedor_carnihub'] ? ' 🔗 CarniHub' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div style="font-size:.73rem;color:#6B7280;margin-top:4px">Al marcar «en preparación» en el KDS se descontará 1 unidad de este ingrediente (solo si el platillo no tiene receta)</div>
          </div>
        </div>

        <div class="wizard-nav">
          <a href="<?= BASE_URL ?>rest-menu/index" class="btn btn-outline">Cancelar</a>
          <button type="button" class="btn btn-primary" onclick="goStep(2)">
            Siguiente: Receta →
          </button>
        </div>
      </div>

      <!-- ── Paso 2: Receta ── -->
      <div class="wpane" data-pane="2">
        <div style="font-weight:700;color:#111827;font-size:1.05rem;margin-bottom:2px">Receta del platillo</div>
        <div style="font-size:.85rem;color:#6B7280;margin-bottom:18px;line-height:1.5">
          Define qué ingredientes lleva y cuánto. CarniHub
          <strong>descontará automáticamente del inventario</strong> cuando el chef marque el ítem como «en preparación».
          <span style="color:#DC2626;font-weight:600">Requerido para publicar.</span>
        </div>

        <?php if (empty($ingredientes)): ?>
        <div style="background:#FEF3C7;border:1px solid #FDE68A;border-radius:10px;padding:14px;margin-bottom:16px">
          <div style="font-weight:600;color:#92400E;font-size:.9rem;margin-bottom:4px">
            ⚠️ Aún no tienes ingredientes en tu inventario
          </div>
          <div style="font-size:.82rem;color:#78350F;margin-bottom:10px">
            Para registrar la receta primero crea ingredientes en tu inventario.
          </div>
          <a href="<?= BASE_URL ?>rest-inventario/index" target="_blank" class="btn btn-sm btn-outline">
            Ir a inventario ↗
          </a>
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
          <div class="form-group">
            <label class="form-label">Porciones que rinde</label>
            <input type="number" name="porciones_base" min="1" class="form-input"
                   value="<?= (int)($platillo['receta']['porciones_base'] ?? 1) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Notas de cocina</label>
            <input type="text" name="receta_notas" class="form-input"
                   placeholder="Ej: marinar 1h, servir caliente"
                   value="<?= htmlspecialchars($platillo['receta']['notas'] ?? '') ?>">
          </div>
        </div>

        <div style="font-weight:600;color:#374151;font-size:.85rem;margin:14px 0 8px">
          Ingredientes
          <span style="font-weight:400;color:#9CA3AF;font-size:.78rem"> — busca y selecciona del inventario</span>
        </div>

        <div id="ingredientes-lista">
          <?php foreach (($platillo['ingredientes'] ?? []) as $ing): ?>
          <div class="ing-row">
            <div class="ing-picker-wrap">
              <input type="text" class="form-input ing-search"
                     placeholder="Buscar ingrediente…" autocomplete="off"
                     value="<?= htmlspecialchars($ing['ingrediente_nombre'] ?? '') ?>"
                     oninput="ingFiltrar(this)" onfocus="ingAbrir(this)">
              <input type="hidden" name="ingrediente_id[]" class="ing-id-hidden"
                     value="<?= (int)$ing['ingrediente_id'] ?>">
              <div class="ing-dd"></div>
              <div class="ing-costo-hint"></div>
            </div>
            <input type="number" name="cantidad[]" step="0.001" min="0" placeholder="Cant."
                   value="<?= htmlspecialchars($ing['cantidad']) ?>" class="form-input"
                   oninput="calcRowCosto(this.closest('.ing-row'))">
            <select name="unidad[]" class="form-select ing-unidad"
                    onchange="calcRowCosto(this.closest('.ing-row'))">
              <?php
              $uOpts = ['g','kg','mg','L','ml','mL','pza','caja','bolsa'];
              foreach ($uOpts as $u):
              ?>
              <option value="<?= $u ?>" <?= ($ing['unidad'] ?? '') === $u ? 'selected' : '' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
            <label style="display:flex;align-items:center;gap:4px;font-size:.75rem;color:#6B7280;cursor:pointer;white-space:nowrap" title="No descuenta stock, solo aparece en la info del cliente">
              <input type="checkbox" name="es_informativo[]" value="<?= (int)$ing['ingrediente_id'] ?>"
                     <?= ($ing['es_informativo'] ?? 0) ? 'checked' : '' ?> style="cursor:pointer">
              Solo info
            </label>
            <button type="button" onclick="this.closest('.ing-row').remove();updateTotalCosto()"
                    class="btn-icon-danger">✕</button>
          </div>
          <?php endforeach; ?>
        </div>

        <button type="button" onclick="addIngrediente()"
                style="width:100%;padding:10px;border:2px dashed #D1D5DB;border-radius:10px;
                       background:#F9FAFB;color:#6B7280;font-size:.88rem;cursor:pointer;
                       margin-top:6px;transition:.15s"
                onmouseover="this.style.borderColor='var(--cp)';this.style.color='var(--cp)'"
                onmouseout="this.style.borderColor='#D1D5DB';this.style.color='#6B7280'">
          + Agregar ingrediente a la receta
        </button>

        <!-- Banner costo estimado -->
        <div id="costoTotalBanner" style="display:none;margin-top:12px;padding:12px 16px;
             background:#F0FDF4;border:1.5px solid #86EFAC;border-radius:10px;
             align-items:center;justify-content:space-between;gap:12px">
          <div style="font-size:.85rem;color:#166534;font-weight:600">Costo de ingredientes (estimado):</div>
          <div id="costoTotalTexto" style="font-size:.95rem;font-weight:800;color:#15803D"></div>
        </div>

        <div id="recetaError" style="display:none;background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;
             padding:10px 14px;margin-top:12px;font-size:.84rem;color:#991B1B">
          ⚠️ Agrega al menos un ingrediente a la receta antes de continuar.
        </div>

        <div class="wizard-nav">
          <button type="button" class="btn btn-outline" onclick="goStep(1)">← Atrás</button>
          <button type="button" class="btn btn-primary" onclick="goStep(3)">
            Siguiente: Revisar →
          </button>
        </div>
      </div>

      <!-- ── Paso 3: Revisar ── -->
      <div class="wpane" data-pane="3">
        <div style="font-weight:700;color:#111827;font-size:1.05rem;margin-bottom:4px">Revisar antes de guardar</div>
        <div style="font-size:.85rem;color:#6B7280;margin-bottom:20px">
          Verifica que todo esté correcto.
        </div>

        <div style="background:#F9FAFB;border-radius:12px;padding:18px;margin-bottom:18px">
          <div id="resumen" style="display:grid;gap:10px"></div>
        </div>

        <div class="wizard-nav">
          <button type="button" class="btn btn-outline" onclick="goStep(2)">← Atrás</button>
          <button type="submit" class="btn btn-primary">
            ✓ Guardar platillo
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<style>
  .wizard-steps{display:flex;align-items:center;gap:8px;margin-bottom:20px;background:#fff;
                border:1px solid #E5E7EB;border-radius:14px;padding:14px 18px}
  .wstep{display:flex;align-items:center;gap:10px;flex-shrink:0}
  .wstep-num{width:30px;height:30px;border-radius:50%;background:#E5E7EB;color:#6B7280;
             display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;
             transition:.25s}
  .wstep-label{font-size:.82rem;color:#6B7280;font-weight:500;transition:.25s}
  .wstep.active .wstep-num{background:var(--cp);color:#fff;transform:scale(1.08)}
  .wstep.active .wstep-label{color:#111827;font-weight:700}
  .wstep.done .wstep-num{background:#10B981;color:#fff}
  .wstep-line{flex:1;height:2px;background:#E5E7EB;border-radius:1px;min-width:30px}
  .wpane{display:none;animation:fadeIn .25s ease both}
  .wpane.active{display:block}
  @keyframes fadeIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
  .ing-row{display:grid;grid-template-columns:2fr 80px 80px auto auto;gap:6px;margin-bottom:8px;align-items:start}
  .ing-picker-wrap{position:relative}
  .ing-dd{display:none;position:absolute;top:calc(100% + 2px);left:0;right:0;
          z-index:200;background:#fff;border:1.5px solid #E5E7EB;border-radius:10px;
          box-shadow:0 6px 24px rgba(0,0,0,.12);max-height:220px;overflow-y:auto}
  .ing-opt{padding:8px 12px;cursor:pointer;display:flex;align-items:center;
           justify-content:space-between;gap:8px;border-bottom:1px solid #F3F4F6}
  .ing-opt:last-child{border-bottom:none}
  .ing-opt:hover{background:#F9FAFB}
  .ing-costo-hint{font-size:.72rem;color:#6B7280;padding:2px 2px 0;min-height:14px;line-height:1.3}
  .btn-icon-danger{padding:8px 12px;background:#FEE2E2;color:#991B1B;border:none;border-radius:8px;
                   cursor:pointer;font-size:.85rem;transition:.15s;align-self:start;margin-top:1px}
  .btn-icon-danger:hover{background:#FCA5A5;color:#7F1D1D}
  .wizard-nav{display:flex;gap:10px;justify-content:space-between;margin-top:24px;padding-top:18px;border-top:1px solid #F3F4F6}
  .alergen-lbl input:checked ~ * { /* handled via JS */ }
  .alergen-lbl.activo { outline:2px solid #4C1D95; }
</style>

<script>
const ingredientesArr = <?= json_encode(array_values($ingredientes)) ?>;
const catNames = <?= json_encode(array_column($categorias, 'nombre', 'id')) ?>;

// Alérgenos: toggle visual
document.querySelectorAll('.alergen-lbl').forEach(lbl => {
  const chk = lbl.querySelector('.alergen-chk');
  function sync() { lbl.style.opacity = chk.checked ? '1' : '.45'; lbl.style.outline = chk.checked ? '2px solid #7C3AED' : 'none'; }
  sync();
  chk.addEventListener('change', sync);
});

function goStep(n) {
  if (n > 1) {
    const nombre = document.getElementById('inpNombre').value.trim();
    const precio = parseFloat(document.getElementById('inpPrecio').value);
    if (!nombre) { alert('El nombre del platillo es obligatorio.'); return; }
    if (isNaN(precio) || precio <= 0) { alert('Indica un precio válido.'); return; }
  }
  if (n > 2) {
    const filled = [...document.querySelectorAll('#ingredientes-lista .ing-id-hidden')]
      .some(h => h.value && h.value !== '0' && h.value !== '');
    if (!filled) { document.getElementById('recetaError').style.display = 'block'; return; }
    document.getElementById('recetaError').style.display = 'none';
  }
  document.querySelectorAll('.wstep').forEach(s => {
    const num = parseInt(s.dataset.step);
    s.classList.toggle('active', num === n);
    s.classList.toggle('done', num < n);
  });
  document.querySelectorAll('.wpane').forEach(p => {
    p.classList.toggle('active', parseInt(p.dataset.pane) === n);
  });
  if (n === 3) renderResumen();
  window.scrollTo({top:0, behavior:'smooth'});
}

// ── Conversión de unidades ──
function convUnidadReceta(q, desde, hasta) {
  const d = desde.toLowerCase(), h = hasta.toLowerCase();
  if (d === h) return q;
  const m = {
    'g_kg':1e-3,'kg_g':1e3,'mg_g':1e-3,'g_mg':1e3,'mg_kg':1e-6,'kg_mg':1e6,
    'ml_l':1e-3,'l_ml':1e3,
  };
  return q * (m[d+'_'+h] || 1);
}

// ── Grupos de unidades por unidad principal ──
function ingUnidades(u) {
  u = (u || '').toLowerCase();
  if (['kg','g','mg'].includes(u)) return ['g','kg','mg'];
  if (['l','ml','lt','litro','ltr'].includes(u)) return ['ml','L'];
  if (u === 'pza') return ['pza'];
  if (u === 'caja') return ['caja'];
  if (u === 'bolsa') return ['bolsa'];
  return ['g','kg','mg','L','ml','pza','caja','bolsa'];
}

// ── Construir opciones del dropdown ──
function ingBuildOpts(query) {
  query = (query || '').toLowerCase().trim();
  const list = query
    ? ingredientesArr.filter(i => i.nombre.toLowerCase().includes(query))
    : ingredientesArr;
  if (!list.length) {
    return '<div style="padding:12px;color:#9CA3AF;font-size:.82rem;text-align:center">Sin resultados</div>';
  }
  return list.slice(0, 25).map(i => {
    const costoStr = parseFloat(i.costo_unitario) > 0
      ? `$${parseFloat(i.costo_unitario).toFixed(2)}/${i.unidad_principal}` : '';
    const catStr = i.categoria || '';
    const nombre = i.nombre.replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const cat    = catStr.replace(/</g,'&lt;').replace(/>/g,'&gt;');
    const chBadge = i.proveedor_carnihub ? `<span style="font-size:.65rem;font-weight:700;background:#EFF6FF;color:#1D4ED8;border:1px solid #BFDBFE;border-radius:4px;padding:1px 5px;margin-left:4px">🔗 CarniHub</span>` : '';
    return `<div class="ing-opt"
      onmousedown="ingSeleccionar(event,this)"
      data-id="${i.id}"
      data-nombre="${nombre.replace(/"/g,'&quot;')}"
      data-unidad="${(i.unidad_principal||'').replace(/"/g,'&quot;')}"
      data-costo="${parseFloat(i.costo_unitario)||0}"
      data-cat="${cat.replace(/"/g,'&quot;')}">
      <div style="display:flex;align-items:center;flex-wrap:wrap;gap:2px">
        <div style="font-weight:600;font-size:.85rem;color:#111827">${nombre}</div>
        ${chBadge}
        ${cat ? `<div style="font-size:.72rem;color:#9CA3AF;width:100%">${cat}</div>` : ''}
      </div>
      ${costoStr ? `<span style="font-size:.78rem;color:#6B7280;font-weight:600;white-space:nowrap">${costoStr}</span>` : ''}
    </div>`;
  }).join('');
}

function ingAbrir(input) {
  const wrap = input.closest('.ing-picker-wrap');
  const dd = wrap.querySelector('.ing-dd');
  dd.innerHTML = ingBuildOpts(input.value);
  dd.style.display = 'block';
}

function ingFiltrar(input) {
  const wrap = input.closest('.ing-picker-wrap');
  const dd = wrap.querySelector('.ing-dd');
  dd.innerHTML = ingBuildOpts(input.value);
  dd.style.display = 'block';
  wrap.querySelector('.ing-id-hidden').value = '';
  calcRowCosto(input.closest('.ing-row'));
}

function ingSeleccionar(e, card) {
  e.preventDefault();
  const wrap = card.closest('.ing-picker-wrap');
  const row  = wrap.closest('.ing-row');
  wrap.querySelector('.ing-search').value    = card.dataset.nombre;
  wrap.querySelector('.ing-id-hidden').value = card.dataset.id;
  wrap.querySelector('.ing-dd').style.display = 'none';
  // Populate unit options based on ingredient's unit
  const unidSel = row.querySelector('.ing-unidad');
  if (unidSel) {
    const units = ingUnidades(card.dataset.unidad);
    const main  = (card.dataset.unidad || 'g').toLowerCase();
    unidSel.innerHTML = units.map(u =>
      `<option value="${u}" ${u.toLowerCase() === main ? 'selected' : ''}>${u}</option>`
    ).join('');
  }
  calcRowCosto(row);
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
  if (!e.target.closest('.ing-picker-wrap')) {
    document.querySelectorAll('.ing-dd').forEach(dd => dd.style.display = 'none');
  }
});

// ── Costo por fila ──
function calcRowCosto(row) {
  if (!row) return;
  const wrap    = row.querySelector('.ing-picker-wrap');
  if (!wrap) return;
  const costoEl = wrap.querySelector('.ing-costo-hint');
  const ingId   = wrap.querySelector('.ing-id-hidden')?.value;
  const ing     = ingId ? ingredientesArr.find(i => i.id == ingId) : null;
  if (!ing || !parseFloat(ing.costo_unitario)) {
    if (costoEl) costoEl.innerHTML = '';
    updateTotalCosto();
    return;
  }
  const cant    = parseFloat(row.querySelector('input[name="cantidad[]"]')?.value) || 0;
  const unid    = row.querySelector('.ing-unidad')?.value || ing.unidad_principal;
  const cantConv = convUnidadReceta(cant, unid, ing.unidad_principal);
  const costo   = cantConv * parseFloat(ing.costo_unitario);
  if (costoEl) {
    costoEl.innerHTML = costo > 0
      ? `Costo: <strong>$${costo.toFixed(2)}</strong>`
        + (unid.toLowerCase() !== ing.unidad_principal.toLowerCase()
            ? ` <span style="color:#9CA3AF">(${cantConv.toFixed(4)} ${ing.unidad_principal})</span>` : '')
      : '';
  }
  updateTotalCosto();
}

function updateTotalCosto() {
  let total = 0;
  document.querySelectorAll('#ingredientes-lista .ing-row').forEach(row => {
    const ingId = row.querySelector('.ing-id-hidden')?.value;
    const ing   = ingId ? ingredientesArr.find(i => i.id == ingId) : null;
    if (!ing || !parseFloat(ing.costo_unitario)) return;
    const cant = parseFloat(row.querySelector('input[name="cantidad[]"]')?.value) || 0;
    const unid = row.querySelector('.ing-unidad')?.value || ing.unidad_principal;
    total += convUnidadReceta(cant, unid, ing.unidad_principal) * parseFloat(ing.costo_unitario);
  });
  const banner = document.getElementById('costoTotalBanner');
  if (total > 0) {
    const porciones = parseInt(document.querySelector('input[name="porciones_base"]')?.value) || 1;
    document.getElementById('costoTotalTexto').textContent =
      `$${total.toFixed(2)} total · $${(total/porciones).toFixed(2)}/porción`;
    banner.style.display = 'flex';
  } else {
    banner.style.display = 'none';
  }
}

// ── Resumen paso 3 ──
function renderResumen() {
  const fd   = new FormData(document.getElementById('formPlatillo'));
  const ings = [];
  let costoTotal = 0;
  document.querySelectorAll('#ingredientes-lista .ing-row').forEach(row => {
    const ingId = row.querySelector('.ing-id-hidden')?.value;
    if (!ingId || ingId === '0' || ingId === '') return;
    const ing   = ingredientesArr.find(x => x.id == ingId);
    const nombre = row.querySelector('.ing-search')?.value || (ing?.nombre ?? '?');
    const cant  = parseFloat(row.querySelector('input[name="cantidad[]"]')?.value) || 0;
    const unid  = row.querySelector('.ing-unidad')?.value || (ing?.unidad_principal ?? 'kg');
    let costoStr = '';
    if (ing && parseFloat(ing.costo_unitario)) {
      const c = convUnidadReceta(cant, unid, ing.unidad_principal) * parseFloat(ing.costo_unitario);
      costoTotal += c;
      costoStr = ` <span style="color:#6B7280;font-size:.75rem">→ $${c.toFixed(2)}</span>`;
    }
    ings.push(`${nombre} — ${cant} ${unid}${costoStr}`);
  });
  const porciones = parseInt(fd.get('porciones_base') || 1);
  const precio    = parseFloat(fd.get('precio') || 0);
  const cat       = fd.get('categoria_id');
  const alergs    = fd.getAll('alergenos[]');
  const margen    = precio - costoTotal;
  const margenPct = precio > 0 ? (margen / precio * 100).toFixed(1) : 0;
  let costoHtml = '';
  if (costoTotal > 0) {
    costoHtml = `
    <div style="margin-top:6px;padding:10px 14px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:8px">
      <div style="font-size:.82rem;font-weight:700;color:#166534;margin-bottom:6px">Calculadora de costos logísticos</div>
      <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px;font-size:.8rem">
        <div><div style="color:#9CA3AF">Costo ingredientes</div><strong style="color:#111827">$${costoTotal.toFixed(2)}</strong></div>
        <div><div style="color:#9CA3AF">Por porción</div><strong style="color:#111827">$${(costoTotal/porciones).toFixed(2)}</strong></div>
        ${precio > 0 ? `<div><div style="color:#9CA3AF">Margen estimado</div><strong style="color:${margen >= 0 ? '#16A34A' : '#EF4444'}">$${margen.toFixed(2)} (${margenPct}%)</strong></div>` : ''}
      </div>
    </div>`;
  }
  document.getElementById('resumen').innerHTML = `
    <div><strong>Nombre:</strong> ${fd.get('nombre') || '—'}</div>
    <div><strong>Categoría:</strong> ${cat ? (catNames[cat] || '—') : 'Sin categoría'}</div>
    <div><strong>Precio:</strong> $${parseFloat(fd.get('precio') || 0).toFixed(2)}</div>
    <div><strong>Tiempo:</strong> ${fd.get('tiempo_preparacion_min')} min</div>
    <div><strong>Disponible:</strong> ${fd.get('disponible') === '1' ? 'Sí' : 'No'}</div>
    <div><strong>Descripción:</strong> ${fd.get('descripcion') || '—'}</div>
    ${alergs.length ? `<div><strong>Alérgenos:</strong> ${alergs.join(', ')}</div>` : ''}
    ${fd.get('contiene') ? `<div><strong>Contiene:</strong> ${fd.get('contiene')}</div>` : ''}
    <div><strong>Ingredientes (${ings.length}):</strong><br>${ings.length ? ings.map(s => '— ' + s).join('<br>') : '<span style="color:#9CA3AF">Sin receta</span>'}</div>
    ${costoHtml}
  `;
}

function addIngrediente() {
  const row = document.createElement('div');
  row.className = 'ing-row';
  row.innerHTML = `
    <div class="ing-picker-wrap">
      <input type="text" class="form-input ing-search"
             placeholder="Buscar ingrediente…" autocomplete="off"
             oninput="ingFiltrar(this)" onfocus="ingAbrir(this)">
      <input type="hidden" name="ingrediente_id[]" class="ing-id-hidden" value="">
      <div class="ing-dd"></div>
      <div class="ing-costo-hint"></div>
    </div>
    <input type="number" name="cantidad[]" step="0.001" min="0" placeholder="Cant." class="form-input"
           oninput="calcRowCosto(this.closest('.ing-row'))">
    <select name="unidad[]" class="form-select ing-unidad"
            onchange="calcRowCosto(this.closest('.ing-row'))">
      <option value="g">g</option><option value="kg">kg</option><option value="mg">mg</option>
      <option value="L">L</option><option value="ml">ml</option>
      <option value="pza">pza</option><option value="caja">caja</option><option value="bolsa">bolsa</option>
    </select>
    <label style="display:flex;align-items:center;gap:4px;font-size:.75rem;color:#6B7280;cursor:pointer;white-space:nowrap;padding-top:8px"
           title="No descuenta stock, solo aparece en info del cliente">
      <input type="checkbox" name="es_informativo[]" value="_new" style="cursor:pointer">
      Solo info
    </label>
    <button type="button" onclick="this.closest('.ing-row').remove();updateTotalCosto()" class="btn-icon-danger">✕</button>
  `;
  document.getElementById('ingredientes-lista').appendChild(row);
  setTimeout(() => row.querySelector('.ing-search').focus(), 50);
}

// Calcular costos de filas pre-cargadas al cargar la página
document.querySelectorAll('#ingredientes-lista .ing-row').forEach(row => calcRowCosto(row));

function previewImg(input) {
  const file = input.files && input.files[0];
  if (!file) return;
  if (file.size > 3 * 1024 * 1024) {
    alert('La imagen excede 3MB. Elige una más pequeña.');
    input.value = '';
    return;
  }
  const reader = new FileReader();
  reader.onload = e => {
    const img = document.getElementById('imgPreview');
    const ph  = document.getElementById('imgPlaceholder');
    img.src = e.target.result;
    img.style.display = 'block';
    if (ph) ph.style.display = 'none';
    const box = document.getElementById('imgPreviewBox');
    if (box) box.style.borderStyle = 'solid';
  };
  reader.readAsDataURL(file);
}
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
