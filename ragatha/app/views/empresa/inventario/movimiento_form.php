<?php
$baseUrl = BASE_URL;
$tipoColor = match ($tipo) {
    'entrada' => '#059669',
    'salida'  => '#DC2626',
    'merma'   => '#D97706',
    default   => '#374151',
};
$tipoColorLight = match ($tipo) {
    'entrada' => '#D1FAE5',
    'salida'  => '#FEE2E2',
    'merma'   => '#FEF3C7',
    default   => '#F3F4F6',
};
$tipoColorBorder = match ($tipo) {
    'entrada' => '#A7F3D0',
    'salida'  => '#FECACA',
    'merma'   => '#FDE68A',
    default   => '#E5E7EB',
};
$tipoLabel = match ($tipo) {
    'entrada' => 'Entrada de Stock',
    'salida'  => 'Salida de Stock',
    'merma'   => 'Registro de Merma',
    default   => 'Movimiento',
};
$tipoDesc = match ($tipo) {
    'entrada' => 'Registra una compra a proveedor, transferencia u otra entrada al almacén.',
    'salida'  => 'Registra salida de productos (ventas directas, préstamos, etc.).',
    'merma'   => 'Registra productos perdidos por vencimiento, daño u otra causa.',
    default   => '',
};
$tipoTips = match ($tipo) {
    'entrada' => [
        'Indica el proveedor en el campo de motivo',
        'Usa la referencia para anotar el número de factura o remisión',
        'El stock se actualiza inmediatamente al guardar',
        'Puedes registrar varias entradas del mismo producto en días distintos',
    ],
    'salida' => [
        'Registra salidas por ventas directas o préstamos',
        'El stock no puede quedar negativo',
        'Anota el folio de venta en el campo de referencia',
        'Si el stock baja del umbral, se generará una alerta automática',
    ],
    'merma' => [
        'El motivo es obligatorio para mermas (trazabilidad)',
        'Registra mermas por vencimiento, daño físico o contaminación',
        'Las mermas aparecen en reportes separados de salidas normales',
        'Puedes adjuntar un número de incidente en el campo referencia',
    ],
    default => [],
};
$tipoIcPath = match ($tipo) {
    'entrada' => 'M12 4v16m8-8H4',
    'salida'  => 'M20 12H4',
    'merma'   => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
    default   => 'M12 4v16m8-8H4',
};
?>
<style>
.mov-input {
  width: 100%;
  padding: 10px 14px;
  border: 1.5px solid #E5E7EB;
  border-radius: 9px;
  font-size: .875rem;
  font-family: 'Inter', sans-serif;
  color: #111827;
  background: #fff;
  outline: none;
  transition: border-color .2s, box-shadow .2s;
  box-sizing: border-box;
}
.mov-input:focus { border-color: <?= $tipoColor ?>; box-shadow: 0 0 0 3px <?= $tipoColor ?>22; }
.mov-input::placeholder { color: #BFC4CE; }
.mov-label { display: block; font-size: .82rem; font-weight: 700; color: #374151; margin-bottom: 7px; }
.mov-required { color: #DC2626; }
.type-tab {
  flex: 1;
  padding: 11px 14px;
  text-align: center;
  border-radius: 10px;
  text-decoration: none;
  font-weight: 700;
  font-size: .84rem;
  font-family: 'Inter', sans-serif;
  transition: transform .15s, box-shadow .15s;
  border: 2px solid transparent;
}
.type-tab:hover { transform: translateY(-1px); }
.tip-item {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 10px 0;
  border-bottom: 1px solid rgba(255,255,255,.08);
  font-size: .82rem;
  color: #CBD5E1;
  line-height: 1.4;
}
.tip-item:last-child { border-bottom: none; }
</style>

<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:10px;font-size:.875rem;font-weight:500;display:flex;align-items:center;gap:8px;
  <?= $flash['type'] === 'success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:1fr 380px;gap:28px;align-items:start">

  <!-- ── Columna izquierda: formulario ── -->
  <div>

    <!-- Tabs de tipo -->
    <div style="display:flex;gap:8px;margin-bottom:22px">
      <?php foreach (['entrada' => ['#059669','#D1FAE5','#A7F3D0','Entrada'], 'salida' => ['#DC2626','#FEE2E2','#FECACA','Salida'], 'merma' => ['#D97706','#FEF3C7','#FDE68A','Merma']] as $t => [$c, $bg, $bd, $label]): ?>
      <a href="<?= $baseUrl ?>empresa-inventario/movimiento/<?= $t ?>"
         class="type-tab"
         style="<?= $tipo === $t ? "background:$c;color:#fff;border-color:$c;box-shadow:0 4px 14px {$c}44" : "background:$bg;color:$c;border-color:$bd" ?>">
        <?= $label ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Descripción -->
    <p style="font-size:.84rem;color:#6B7280;margin-bottom:20px;padding:12px 14px;background:#F9FAFB;border-radius:9px;border-left:3.5px solid <?= $tipoColor ?>;line-height:1.5">
      <?= $tipoDesc ?>
    </p>

    <!-- Formulario -->
    <div style="background:#fff;border-radius:14px;border:1px solid #E5E7EB;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.03)">
      <div style="display:flex;align-items:center;gap:10px;margin-bottom:24px;padding-bottom:18px;border-bottom:1px solid #F3F4F6">
        <div style="width:40px;height:40px;border-radius:10px;background:<?= $tipoColorLight ?>;border:1.5px solid <?= $tipoColorBorder ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="<?= $tipoColor ?>" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $tipoIcPath ?>"/></svg>
        </div>
        <div>
          <div style="font-weight:800;color:#111827;font-size:1rem"><?= $tipoLabel ?></div>
          <div style="font-size:.75rem;color:#9CA3AF">Completa los campos para registrar el movimiento</div>
        </div>
      </div>

      <form method="POST" action="<?= $baseUrl ?>empresa-inventario/guardarMovimiento">
        <input type="hidden" name="tipo" value="<?= htmlspecialchars($tipo) ?>">

        <!-- Producto -->
        <div style="margin-bottom:20px">
          <label class="mov-label">Producto <span class="mov-required">*</span></label>
          <select name="producto_id" id="selectProducto" required onchange="actualizarStock(this)" class="mov-input" style="cursor:pointer">
            <option value="">— Selecciona un producto —</option>
            <?php foreach ($productos as $p): ?>
            <option value="<?= $p['id'] ?>"
                    data-stock="<?= number_format((float)$p['stock_actual'], 1) ?>"
                    data-unidad="<?= htmlspecialchars($p['presentacion']) ?>"
                    data-nombre="<?= htmlspecialchars($p['nombre']) ?>">
              <?= htmlspecialchars($p['nombre']) ?> — <?= number_format((float)$p['stock_actual'], 1) ?> <?= $p['presentacion'] ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div id="stockInfo" style="display:none;margin-top:8px;padding:9px 13px;background:<?= $tipoColorLight ?>;border:1px solid <?= $tipoColorBorder ?>;border-radius:8px;font-size:.82rem;color:#374151">
            Stock actual: <strong id="stockValor" style="color:<?= $tipoColor ?>"></strong>
          </div>
        </div>

        <!-- Cantidad -->
        <div style="margin-bottom:20px">
          <label class="mov-label">Cantidad <span class="mov-required">*</span></label>
          <input type="number" name="cantidad" id="inputCantidad" min="0.01" step="0.01" required
                 onchange="calcularNuevoStock()" oninput="calcularNuevoStock()"
                 class="mov-input" placeholder="0.00">
          <div id="nuevoStockInfo" style="display:none;margin-top:8px;padding:9px 13px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;font-size:.82rem;color:#374151">
            Stock resultante: <strong id="nuevoStockValor" style="color:<?= $tipoColor ?>"></strong>
          </div>
          <div id="stockWarning" style="display:none;margin-top:8px;padding:9px 13px;background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;font-size:.82rem;color:#991B1B;font-weight:600">
            ⚠ Stock insuficiente. Máximo disponible: <strong id="stockWarningMax"></strong>
          </div>
        </div>

        <!-- Motivo -->
        <div style="margin-bottom:20px">
          <label class="mov-label">
            Motivo <?= $tipo === 'merma' ? '<span class="mov-required">*</span>' : '<span style="color:#9CA3AF;font-weight:400">(recomendado)</span>' ?>
          </label>
          <input type="text" name="motivo"
                 placeholder="<?= $tipo === 'entrada' ? 'Ej: Compra a Proveedor ABC' : ($tipo === 'salida' ? 'Ej: Venta directa local' : 'Ej: Producto vencido') ?>"
                 <?= $tipo === 'merma' ? 'required' : '' ?>
                 class="mov-input">
        </div>

        <!-- Referencia -->
        <div style="margin-bottom:28px">
          <label class="mov-label">Referencia <span style="color:#9CA3AF;font-weight:400">(opcional)</span></label>
          <input type="text" name="referencia"
                 placeholder="<?= $tipo === 'entrada' ? 'Ej: Factura A-0234, remisión...' : 'Ej: Folio de pedido...' ?>"
                 class="mov-input">
          <p style="margin-top:5px;font-size:.73rem;color:#9CA3AF">Útil para rastrear el origen o destino del movimiento.</p>
        </div>

        <div style="display:flex;gap:10px">
          <button type="submit" id="btnSubmit"
                  style="flex:1;padding:13px;background:<?= $tipoColor ?>;color:#fff;border:none;border-radius:10px;font-weight:700;font-size:.9rem;cursor:pointer;font-family:'Inter',sans-serif;display:flex;align-items:center;justify-content:center;gap:7px;transition:opacity .15s">
            <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $tipoIcPath ?>"/></svg>
            Registrar <?= $tipoLabel ?>
          </button>
          <a href="<?= $baseUrl ?>empresa-inventario"
             style="padding:13px 20px;border:1.5px solid #E5E7EB;border-radius:10px;color:#374151;text-decoration:none;font-size:.875rem;font-weight:600;display:flex;align-items:center;gap:6px;transition:background .15s" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background=''">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            Cancelar
          </a>
        </div>
      </form>
    </div>

  </div>

  <!-- ── Columna derecha: panel de contexto ── -->
  <div>

    <!-- Card de estado actual -->
    <div id="panelProducto" style="background:#fff;border-radius:14px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px;box-shadow:0 1px 4px rgba(0,0,0,.03);display:none">
      <div style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9CA3AF;margin-bottom:12px">Producto seleccionado</div>
      <div id="panelNombreProducto" style="font-weight:800;color:#111827;font-size:1rem;margin-bottom:6px"></div>
      <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:#F9FAFB;border-radius:9px;border:1px solid #F3F4F6">
        <div>
          <div style="font-size:.68rem;color:#9CA3AF;font-weight:600;text-transform:uppercase;letter-spacing:.05em">Stock actual</div>
          <div id="panelStockActual" style="font-size:1.6rem;font-weight:800;color:#111827;line-height:1.1;margin-top:2px"></div>
        </div>
        <svg width="32" height="32" fill="none" viewBox="0 0 24 24" stroke="<?= $tipoColor ?>" stroke-width="1.2" opacity=".4"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10"/></svg>
      </div>
      <div id="panelNuevoStock" style="display:none;margin-top:10px;padding:10px 14px;border-radius:9px;border:1.5px dashed <?= $tipoColor ?>;background:<?= $tipoColorLight ?>">
        <div style="font-size:.68rem;color:#6B7280;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px">Stock resultante</div>
        <div id="panelNuevoStockVal" style="font-size:1.4rem;font-weight:800;color:<?= $tipoColor ?>"></div>
      </div>
    </div>

    <!-- Tips -->
    <div style="background:linear-gradient(150deg,#1A1D23,#23272F);border-radius:14px;padding:22px;border:1px solid rgba(255,255,255,.05)">
      <div style="display:flex;align-items:center;gap:9px;margin-bottom:16px">
        <div style="width:30px;height:30px;border-radius:8px;background:<?= $tipoColor ?>33;border:1px solid <?= $tipoColor ?>55;display:flex;align-items:center;justify-content:center;flex-shrink:0">
          <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="<?= $tipoColor ?>" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="<?= $tipoIcPath ?>"/></svg>
        </div>
        <div style="font-size:.82rem;font-weight:700;color:#E5E7EB"><?= $tipoLabel ?></div>
      </div>
      <div>
        <?php foreach ($tipoTips as $tip): ?>
        <div class="tip-item">
          <span style="width:18px;height:18px;border-radius:50%;background:<?= $tipoColor ?>33;border:1px solid <?= $tipoColor ?>55;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px">
            <svg width="9" height="9" fill="none" viewBox="0 0 24 24" stroke="<?= $tipoColor ?>" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
          </span>
          <?= htmlspecialchars($tip) ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

</div>

<script>
function actualizarStock(select) {
  const opt = select.options[select.selectedIndex];
  const panel = document.getElementById('panelProducto');
  if (opt.value) {
    document.getElementById('stockInfo').style.display = 'block';
    document.getElementById('stockValor').textContent  = opt.dataset.stock + ' ' + opt.dataset.unidad;
    document.getElementById('stockValor').dataset.valor = opt.dataset.stock;
    document.getElementById('stockValor').dataset.unidad = opt.dataset.unidad;

    panel.style.display = 'block';
    document.getElementById('panelNombreProducto').textContent = opt.dataset.nombre;
    document.getElementById('panelStockActual').textContent = opt.dataset.stock + ' ' + opt.dataset.unidad;
  } else {
    document.getElementById('stockInfo').style.display = 'none';
    document.getElementById('nuevoStockInfo').style.display = 'none';
    panel.style.display = 'none';
  }
  calcularNuevoStock();
}

function calcularNuevoStock() {
  const select   = document.getElementById('selectProducto');
  const cantidad = parseFloat(document.getElementById('inputCantidad').value) || 0;
  const opt      = select.options[select.selectedIndex];
  if (!opt || !opt.value || cantidad <= 0) {
    document.getElementById('nuevoStockInfo').style.display = 'none';
    document.getElementById('panelNuevoStock').style.display = 'none';
    return;
  }
  const stockActual = parseFloat(opt.dataset.stock) || 0;
  const unidad      = opt.dataset.unidad;
  const tipo        = '<?= $tipo ?>';
  const nuevo       = tipo === 'entrada' ? stockActual + cantidad : Math.max(0, stockActual - cantidad);

  document.getElementById('nuevoStockInfo').style.display = 'block';
  document.getElementById('nuevoStockValor').textContent  = nuevo.toFixed(1) + ' ' + unidad;

  document.getElementById('panelNuevoStock').style.display = 'block';
  document.getElementById('panelNuevoStockVal').textContent = nuevo.toFixed(1) + ' ' + unidad;

  // Validación stock insuficiente para salida/merma
  const btnSubmit = document.getElementById('btnSubmit');
  if ((tipo === 'salida' || tipo === 'merma') && cantidad > stockActual) {
    document.getElementById('stockWarning').style.display = 'block';
    document.getElementById('stockWarningMax').textContent = stockActual.toFixed(1) + ' ' + unidad;
    if (btnSubmit) { btnSubmit.disabled = true; btnSubmit.style.opacity = '0.45'; btnSubmit.style.cursor = 'not-allowed'; }
  } else {
    document.getElementById('stockWarning').style.display = 'none';
    if (btnSubmit) { btnSubmit.disabled = false; btnSubmit.style.opacity = '1'; btnSubmit.style.cursor = 'pointer'; }
  }
}
</script>
