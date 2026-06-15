<?php
// Vista: Paso 3 — Resumen y confirmación del pedido
$metaSaved  = $meta ?? [];
$comprador  = $comprador ?? [];
$empresa    = $empresa ?? [];
$metaSaved['tipo_entrega'] = $metaSaved['tipo_entrega'] ?? 'pickup';

// Sucursales del comprador (para selector de entrega)
$sucursalModel  = new SucursalModel();
$misSucursales  = $sucursalModel->getByComprador($comprador['id'] ?? 0);

// Google Maps key
$configModel = new ConfigModel();
$gmKey = $configModel->get('google_maps_key', '');
?>
<!-- Pasos -->
<div style="display:flex;align-items:center;gap:0;margin-bottom:24px;font-size:.8rem">
  <?php
  $pasos = ['1'=>'Productos','2'=>'Resumen','3'=>'Confirmado'];
  foreach ($pasos as $num => $label):
    $activo = $num === '2';
    $hecho  = $num < '2';
  ?>
  <div style="display:flex;align-items:center;gap:6px;padding:8px 14px;background:<?= $activo ? 'var(--color-primary)' : ($hecho ? '#D1FAE5' : '#E5E7EB') ?>;color:<?= $activo ? '#fff' : ($hecho ? '#065F46' : '#9CA3AF') ?>;<?= $num === '1' ? 'border-radius:8px 0 0 8px' : ($num === '3' ? 'border-radius:0 8px 8px 0' : '') ?>">
    <span style="font-weight:700"><?= $hecho ? '✓' : $num ?></span>
    <?= $label ?>
  </div>
  <?php if ($num < '3'): ?>
  <div style="width:0;height:0;border-top:18px solid transparent;border-bottom:18px solid transparent;border-left:10px solid <?= $activo ? 'var(--color-primary)' : ($hecho ? '#D1FAE5' : '#E5E7EB') ?>"></div>
  <?php endif; ?>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">
  <!-- Productos -->
  <div>
    <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px">
      <div style="padding:14px 16px;border-bottom:1px solid #F3F4F6;font-weight:700;font-size:.9rem;color:#111827">
        Detalle del pedido
      </div>
      <table style="width:100%;border-collapse:collapse;font-size:.875rem">
        <thead>
          <tr style="background:#F9FAFB">
            <th style="padding:10px 16px;text-align:left;color:#6B7280;font-weight:600">Producto</th>
            <th style="padding:10px;text-align:center;color:#6B7280;font-weight:600">Cantidad</th>
            <th style="padding:10px;text-align:right;color:#6B7280;font-weight:600">Precio</th>
            <th style="padding:10px 16px;text-align:right;color:#6B7280;font-weight:600">Subtotal</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item):
            $itemPrecioBase = isset($item['precio_base']) ? (float)$item['precio_base'] : null;
            $itemHayDescto  = $itemPrecioBase !== null && $item['precio'] < $itemPrecioBase - 0.001;
            $itemEsEsp      = !empty($item['es_precio_especial']);
          ?>
          <tr style="border-top:1px solid #F3F4F6">
            <td style="padding:10px 16px;font-weight:600;color:#111827">
              <?= htmlspecialchars($item['nombre']) ?>
              <div style="font-size:.75rem;color:#9CA3AF;font-weight:400"><?= $item['presentacion'] ?></div>
            </td>
            <td style="padding:10px;text-align:center;color:#374151"><?= number_format($item['cantidad'], 2) ?></td>
            <td style="padding:10px;text-align:right">
              <?php if ($itemHayDescto): ?>
                <span style="text-decoration:line-through;color:#9CA3AF;font-size:.75rem">$<?= number_format($itemPrecioBase, 2) ?></span><br>
                <span style="color:#059669;font-weight:700">$<?= number_format($item['precio'], 2) ?></span>
                <span style="display:inline-block;font-size:.62rem;background:<?= $itemEsEsp ? '#D1FAE5' : '#ECFDF5' ?>;color:#065F46;padding:1px 5px;border-radius:999px;font-weight:700;margin-left:2px"><?= $itemEsEsp ? '★ especial · solo <10 kg' : '🏷 volumen' ?></span>
              <?php else: ?>
                <span style="color:#374151">$<?= number_format($item['precio'], 2) ?></span>
              <?php endif; ?>
            </td>
            <td style="padding:10px 16px;text-align:right;font-weight:700;color:#111827">$<?= number_format($item['subtotal'], 2) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <?php
            $ivaFila  = round($total / 1.16 * 0.16, 2);
            $baseFila = round($total - $ivaFila, 2);
          ?>
          <tr style="border-top:1px solid #E5E7EB;background:#F9FAFB">
            <td colspan="3" style="padding:8px 16px;text-align:right;color:#6B7280;font-size:.85rem">Base (sin IVA)</td>
            <td style="padding:8px 16px;text-align:right;color:#6B7280;font-size:.85rem">$<?= number_format($baseFila, 2) ?></td>
          </tr>
          <tr style="background:#F9FAFB">
            <td colspan="3" style="padding:8px 16px;text-align:right;color:#6B7280;font-size:.85rem">IVA (16%)</td>
            <td style="padding:8px 16px;text-align:right;color:#6B7280;font-size:.85rem">$<?= number_format($ivaFila, 2) ?></td>
          </tr>
          <tr style="border-top:2px solid #E5E7EB;background:#F9FAFB">
            <td colspan="3" style="padding:12px 16px;text-align:right;font-weight:700;color:#374151">TOTAL</td>
            <td style="padding:12px 16px;text-align:right;font-size:1.1rem;font-weight:800;color:var(--color-primary)">
              $<?= number_format($total, 2) ?>
            </td>
          </tr>
        </tfoot>
      </table>
    </div><!-- /products card -->

    <!-- ── Distribución por sucursal ───────────────────────────────────── -->
    <?php if (!empty($misSucursales)): ?>
    <div id="bloque-dist" style="display:none;background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden;margin-bottom:16px">
      <div style="padding:14px 16px;border-bottom:1px solid #F3F4F6;background:#F9FAFB">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
          <div>
            <span style="font-weight:700;font-size:.9rem;color:#111827">Distribución por sucursal</span>
            <span id="dist-badge" style="margin-left:8px;font-size:.72rem;font-weight:600;padding:2px 9px;border-radius:999px;background:#E5E7EB;color:#6B7280"></span>
          </div>
        </div>
        <p style="font-size:.78rem;color:#6B7280;margin:6px 0 0">Indica cuántos kg/piezas van a cada parada — usa los botones ± o escribe directamente. La suma debe igualar el total de cada producto.</p>
      </div>
      <div id="dist-cards" style="padding:14px 16px;display:flex;flex-direction:column;gap:12px"></div>
      <!-- Hidden celda-rest spans for submit validation -->
      <div id="dist-rest-spans" style="display:none">
        <?php foreach ($items as $it): ?>
        <span class="celda-rest" data-prodid="<?= (int)$it['producto_id'] ?>">0</span>
        <?php endforeach; ?>
      </div>
      <div id="dist-hint" style="padding:10px 16px;font-size:.78rem;color:#9CA3AF;border-top:1px solid #F3F4F6;display:flex;align-items:center;gap:6px">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Los precios por volumen se calculan sobre el total del pedido.
      </div>
    </div>
    <!-- Toast notificación (exceso/advertencia distribución) -->
    <div id="toast-dist" style="display:none;position:fixed;top:20px;left:50%;transform:translateX(-50%);background:#B45309;color:#fff;padding:12px 20px;border-radius:10px;font-size:.875rem;font-weight:600;z-index:9999;max-width:440px;width:90%;text-align:center;box-shadow:0 6px 20px rgba(0,0,0,.25)"></div>
    <!-- Modal error distribución (bloquea envío) -->
    <div id="modal-dist" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:10000;align-items:center;justify-content:center;padding:16px">
      <div style="background:#fff;border-radius:16px;padding:28px 24px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)">
        <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:18px">
          <div style="font-size:2.2rem;flex-shrink:0;line-height:1">⚠️</div>
          <div>
            <div id="modal-dist-title" style="font-size:1rem;font-weight:700;color:#111827;margin-bottom:6px">Distribución incompleta</div>
            <div id="modal-dist-body" style="font-size:.875rem;color:#6B7280;line-height:1.6"></div>
          </div>
        </div>
        <button onclick="document.getElementById('modal-dist').style.display='none'"
                style="width:100%;padding:11px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.9rem">
          Corregir distribución
        </button>
      </div>
    </div>
    <?php endif; ?>
  </div><!-- /left column -->
  <form method="POST" action="<?= BASE_URL ?>carrito/confirmar" id="form-pedido">
    <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:20px">
      <h3 style="font-size:.95rem;font-weight:700;color:#111827;margin-bottom:16px">Datos del pedido</h3>

      <!-- Fecha entrega -->
      <div style="margin-bottom:14px">
        <label style="font-size:.8rem;font-weight:600;color:#374151;display:block;margin-bottom:4px">Fecha de entrega *</label>
        <input type="date" name="fecha_entrega"
               value="<?= htmlspecialchars($metaSaved['fecha_entrega'] ?? '') ?>"
               min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
               required
               style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      </div>

      <!-- Método de pago -->
      <div style="margin-bottom:14px">
        <label style="font-size:.8rem;font-weight:600;color:#374151;display:block;margin-bottom:4px">Método de pago *</label>
        <select name="metodo_pago" required style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
          <?php foreach (['transferencia'=>'Transferencia bancaria','efectivo'=>'Efectivo en la empresa'] as $v => $l): ?>
          <option value="<?= $v ?>" <?= ($metaSaved['metodo_pago'] ?? 'transferencia') === $v ? 'selected' : '' ?>><?= $l ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Tipo de entrega -->
      <div style="margin-bottom:14px">
        <label style="font-size:.8rem;font-weight:600;color:#374151;display:block;margin-bottom:6px">Tipo de entrega *</label>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">
          <?php $teActual = $metaSaved['tipo_entrega'] ?? 'pickup'; ?>
          <label id="card-pickup" style="cursor:pointer;border:2px solid <?= $teActual==='pickup' ? 'var(--color-primary)' : '#E5E7EB' ?>;border-radius:10px;padding:12px 10px;text-align:center;background:<?= $teActual==='pickup' ? '#FFF5F5' : '#fff' ?>;transition:all .15s">
            <input type="radio" name="tipo_entrega" id="te_pickup" value="pickup" <?= $teActual==='pickup' ? 'checked' : '' ?> style="display:none">
            <div style="font-size:1.4rem;margin-bottom:4px">🏭</div>
            <div style="font-size:.8rem;font-weight:700;color:#111827">Recoger en bodega</div>
            <div style="font-size:.72rem;color:#6B7280;margin-top:2px">Sin costo de envío</div>
          </label>
          <label id="card-repartidor" style="cursor:pointer;border:2px solid <?= $teActual==='repartidor' ? 'var(--color-primary)' : '#E5E7EB' ?>;border-radius:10px;padding:12px 10px;text-align:center;background:<?= $teActual==='repartidor' ? '#FFF5F5' : '#fff' ?>;transition:all .15s">
            <input type="radio" name="tipo_entrega" id="te_repartidor" value="repartidor" <?= $teActual==='repartidor' ? 'checked' : '' ?> style="display:none">
            <div style="font-size:1.4rem;margin-bottom:4px">🚚</div>
            <div style="font-size:.8rem;font-weight:700;color:#111827">Envío a domicilio</div>
            <div style="font-size:.72rem;color:#6B7280;margin-top:2px">La empresa asigna costo</div>
          </label>
        </div>
      </div>

      <!-- Bloque: Pickup — dirección de la empresa -->
      <div id="bloque-pickup" style="margin-bottom:14px;<?= $teActual!=='pickup' ? 'display:none' : '' ?>">
        <div style="background:#F0FDF4;border:1px solid #A7F3D0;border-radius:8px;padding:12px">
          <div style="font-size:.75rem;font-weight:700;color:#065F46;margin-bottom:4px">PUNTO DE RETIRO</div>
          <?php if (!empty($empresa['direccion_fiscal'])): ?>
          <div style="font-size:.85rem;color:#064E3B"><?= htmlspecialchars($empresa['direccion_fiscal']) ?></div>
          <?php else: ?>
          <div style="font-size:.85rem;color:#6B7280">La empresa confirmará el punto de retiro al aprobar el pedido.</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ── Bloque: Envío a domicilio ────────────────────────────── -->
      <div id="bloque-direccion" style="margin-bottom:14px;<?= $teActual!=='repartidor' ? 'display:none' : '' ?>">

        <?php if (!empty($misSucursales)): ?>
        <!-- ── MODO MULTI-PARADA (tiene sucursales registradas) ──── -->
        <label style="font-size:.8rem;font-weight:600;color:#374151;display:block;margin-bottom:6px">
          Paradas de entrega *
          <span style="font-size:.72rem;font-weight:400;color:#9CA3AF"> — el repartidor visita cada parada</span>
        </label>

        <!-- Lista de paradas añadidas -->
        <div id="lista-paradas" style="display:flex;flex-direction:column;gap:6px;margin-bottom:8px"></div>

        <!-- Estado vacío -->
        <div id="paradas-empty" style="border:1.5px dashed #D1D5DB;border-radius:8px;padding:14px 12px;text-align:center;color:#9CA3AF;font-size:.8rem;margin-bottom:8px">
          Añade al menos una parada de entrega
        </div>

        <!-- Botones para añadir -->
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">
          <div style="position:relative">
            <button type="button" id="btn-toggle-dropdown"
                    style="padding:7px 14px;background:#F3F4F6;border:1px solid #D1D5DB;border-radius:8px;font-size:.82rem;font-weight:600;color:#374151;cursor:pointer">
              + Añadir sucursal ▾
            </button>
            <!-- Dropdown de sucursales disponibles -->
            <div id="dropdown-sucursales"
                 style="display:none;position:absolute;top:100%;left:0;margin-top:4px;background:#fff;border:1px solid #E5E7EB;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,.12);z-index:50;min-width:260px;overflow:hidden">
              <?php foreach ($misSucursales as $suc): ?>
              <div class="suc-option" data-id="<?= $suc['id'] ?>"
                   data-nombre="<?= htmlspecialchars($suc['nombre'], ENT_QUOTES) ?>"
                   data-dir="<?= htmlspecialchars($suc['direccion'], ENT_QUOTES) ?>"
                   data-lat="<?= (float)($suc['lat'] ?? 0) ?>"
                   data-lng="<?= (float)($suc['lng'] ?? 0) ?>"
                   style="padding:10px 14px;cursor:pointer;border-bottom:1px solid #F3F4F6;font-size:.85rem">
                <div style="font-weight:700;color:#111827"><?= htmlspecialchars($suc['nombre']) ?></div>
                <div style="font-size:.75rem;color:#6B7280"><?= htmlspecialchars($suc['direccion']) ?></div>
              </div>
              <?php endforeach; ?>
              <div id="dropdown-vacio" style="display:none;padding:10px 14px;font-size:.8rem;color:#9CA3AF;text-align:center">
                Ya añadiste todas tus sucursales
              </div>
            </div>
          </div>

          <button type="button" id="btn-add-manual"
                  style="padding:7px 14px;background:#F3F4F6;border:1px solid #D1D5DB;border-radius:8px;font-size:.82rem;font-weight:600;color:#374151;cursor:pointer">
            + Otra dirección
          </button>
        </div>

        <!-- Formulario dirección manual (oculto por defecto) -->
        <div id="panel-dir-manual" style="display:none;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:12px;margin-bottom:8px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
            <span style="font-size:.8rem;font-weight:700;color:#374151">Otra dirección de entrega</span>
            <button type="button" id="btn-remove-manual"
                    style="background:none;border:none;color:#EF4444;font-size:.8rem;font-weight:700;cursor:pointer">× Quitar</button>
          </div>
          <?php if ($gmKey): ?>
          <input type="text" id="input-dir-checkout" name="direccion_entrega"
                 placeholder="Escribe para buscar con Google Maps..."
                 value="<?= htmlspecialchars($comprador['direccion_entrega'] ?? '') ?>"
                 autocomplete="off"
                 style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;box-sizing:border-box;margin-bottom:6px">
          <?php else: ?>
          <textarea name="direccion_entrega" id="input-dir-checkout" rows="2"
                    placeholder="Calle, colonia, municipio..."
                    style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;resize:none;box-sizing:border-box;margin-bottom:6px"><?= htmlspecialchars($comprador['direccion_entrega'] ?? '') ?></textarea>
          <?php endif; ?>
          <input type="text" name="referencia_entrega" id="input-ref-checkout"
                 placeholder="Ej: Interior 3B, portón negro..."
                 value="<?= htmlspecialchars($comprador['referencia_entrega'] ?? '') ?>"
                 style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;box-sizing:border-box">
          <input type="hidden" name="lat_entrega" id="input-lat-checkout" value="<?= htmlspecialchars($comprador['lat_entrega'] ?? '') ?>">
          <input type="hidden" name="lng_entrega" id="input-lng-checkout" value="<?= htmlspecialchars($comprador['lng_entrega'] ?? '') ?>">
        </div>

        <!-- Inputs ocultos: IDs de sucursales añadidas (JS los genera) -->
        <div id="hidden-sucursales-ids"></div>

        <?php else: ?>
        <!-- ── MODO DIRECCIÓN ÚNICA (sin sucursales registradas) ──── -->
        <?php if (!empty($comprador['direccion_entrega'])): ?>
        <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:8px;padding:10px 12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:flex-start;gap:8px">
          <div>
            <div style="font-size:.75rem;font-weight:700;color:#1E40AF;margin-bottom:2px">DIRECCIÓN GUARDADA EN TU PERFIL</div>
            <div style="font-size:.85rem;color:#1D4ED8"><?= htmlspecialchars($comprador['direccion_entrega']) ?></div>
            <?php if (!empty($comprador['referencia_entrega'])): ?>
            <div style="font-size:.78rem;color:#3B82F6;margin-top:2px"><?= htmlspecialchars($comprador['referencia_entrega']) ?></div>
            <?php endif; ?>
          </div>
          <button type="button" onclick="toggleEditDireccion()" style="font-size:.75rem;color:#1D4ED8;background:none;border:1px solid #93C5FD;border-radius:6px;padding:4px 8px;cursor:pointer;white-space:nowrap">Cambiar</button>
        </div>
        <input type="hidden" name="direccion_entrega" id="hidden-dir" value="<?= htmlspecialchars($comprador['direccion_entrega'] ?? '') ?>">
        <input type="hidden" name="referencia_entrega" id="hidden-ref" value="<?= htmlspecialchars($comprador['referencia_entrega'] ?? '') ?>">
        <input type="hidden" name="lat_entrega" id="input-lat-checkout" value="<?= htmlspecialchars($comprador['lat_entrega'] ?? '') ?>">
        <input type="hidden" name="lng_entrega" id="input-lng-checkout" value="<?= htmlspecialchars($comprador['lng_entrega'] ?? '') ?>">
        <div id="edit-direccion" style="display:none">
        <?php endif; ?>

        <!-- Campos manuales -->
        <?php if ($gmKey): ?>
        <input type="text" id="input-dir-checkout"
               name="<?= !empty($comprador['direccion_entrega']) ? 'direccion_entrega_edit' : 'direccion_entrega' ?>"
               placeholder="Escribe para buscar con Google Maps..."
               value="<?= htmlspecialchars($comprador['direccion_entrega'] ?? '') ?>"
               autocomplete="off"
               style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;box-sizing:border-box;margin-bottom:6px"
               <?= empty($comprador['direccion_entrega']) && $teActual==='repartidor' ? 'required' : '' ?>>
        <?php else: ?>
        <textarea name="<?= !empty($comprador['direccion_entrega']) ? 'direccion_entrega_edit' : 'direccion_entrega' ?>"
                  id="input-dir-checkout" rows="2"
                  placeholder="Calle, colonia, municipio..."
                  style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;resize:none;box-sizing:border-box;margin-bottom:6px"
                  <?= empty($comprador['direccion_entrega']) && $teActual==='repartidor' ? 'required' : '' ?>><?= htmlspecialchars($comprador['direccion_entrega'] ?? '') ?></textarea>
        <?php endif; ?>
        <input type="text"
               name="<?= !empty($comprador['direccion_entrega']) ? 'referencia_entrega_edit' : 'referencia_entrega' ?>"
               id="input-ref-checkout"
               placeholder="Ej: Interior 3B, edificio azul..."
               value="<?= htmlspecialchars($comprador['referencia_entrega'] ?? '') ?>"
               style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;box-sizing:border-box">
        <?php if (!empty($comprador['lat_entrega']) && empty($comprador['direccion_entrega']) === false): ?>
        <input type="hidden" name="lat_entrega" id="input-lat-checkout" value="<?= htmlspecialchars($comprador['lat_entrega'] ?? '') ?>">
        <input type="hidden" name="lng_entrega" id="input-lng-checkout" value="<?= htmlspecialchars($comprador['lng_entrega'] ?? '') ?>">
        <?php else: ?>
        <input type="hidden" name="lat_entrega" id="input-lat-checkout" value="">
        <input type="hidden" name="lng_entrega" id="input-lng-checkout" value="">
        <?php endif; ?>

        <?php if (!empty($comprador['direccion_entrega'])): ?>
        </div><!-- /edit-direccion -->
        <?php endif; ?>

        <?php if (empty($comprador['direccion_entrega'])): ?>
        <div style="font-size:.75rem;color:#6B7280;margin-top:6px">
          Guarda tu dirección en tu <a href="<?= BASE_URL ?>cuenta/perfil" target="_blank" style="color:var(--color-primary)">perfil</a> para futuros pedidos.
        </div>
        <?php endif; ?>
        <?php endif; // fin sin sucursales ?>

      </div><!-- /bloque-direccion -->

      <!-- Notas -->
      <div style="margin-bottom:18px">
        <label style="font-size:.8rem;font-weight:600;color:#374151;display:block;margin-bottom:4px">
          Notas adicionales
          <span style="font-size:.72rem;color:#9CA3AF;font-weight:400"> — instrucciones especiales, cortes específicos, etc.</span>
        </label>
        <textarea name="notas" rows="3" placeholder="Ej: Entregar antes del mediodía, pedir al guardia que avise..."
                  style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;resize:vertical"><?= htmlspecialchars($metaSaved['notas'] ?? '') ?></textarea>
      </div>

      <!-- Total + costo envío -->
      <?php
        $ivaEstimado   = round($total / 1.16 * 0.16, 2);
        $subtotalBase  = round($total - $ivaEstimado, 2);
      ?>
      <div style="background:#F9FAFB;border-radius:8px;padding:14px;margin-bottom:16px">
        <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#6B7280;margin-bottom:4px">
          <span>Base (sin IVA)</span>
          <span>$<?= number_format($subtotalBase, 2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#6B7280;margin-bottom:6px;padding-left:12px">
          <span>IVA (16%)</span>
          <span>$<?= number_format($ivaEstimado, 2) ?></span>
        </div>
        <div style="display:flex;justify-content:space-between;font-size:.82rem;color:#9CA3AF;margin-bottom:10px" id="fila-envio">
          <span>Costo de envío</span>
          <span id="txt-costo-envio">— La empresa lo asigna —</span>
        </div>
        <div style="border-top:1px solid #E5E7EB;padding-top:10px;text-align:center">
          <div style="font-size:.8rem;color:#6B7280;margin-bottom:2px">Total del pedido</div>
          <div style="font-size:1.8rem;font-weight:800;color:var(--color-primary)">$<?= number_format($total, 2) ?></div>
          <div style="font-size:.72rem;color:#9CA3AF;margin-top:2px">IVA incluido · Envío se confirma al aprobar</div>
        </div>
      </div>

      <div style="display:flex;flex-direction:column;gap:8px">
        <button type="submit" id="btn-confirmar" style="padding:12px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.9rem;cursor:pointer;width:100%">
          Confirmar pedido
        </button>
        <a href="<?= BASE_URL ?>carrito/index" style="text-align:center;padding:10px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
          ← Volver al carrito
        </a>
      </div>
    </div>
  </form>
</div>

<?php
// Datos para JS: productos del carrito + mapa id→nombre de sucursales
$_cartJs = json_encode(array_values(array_map(function($it) {
    return ['id'=>(int)$it['producto_id'],'nombre'=>$it['nombre'],'presentacion'=>$it['presentacion']??'','cantidad'=>(float)$it['cantidad'],'precio'=>(float)$it['precio']];
}, $items)));
$_sucMapJs = '{}';
if (!empty($misSucursales)) {
    $_m = [];
    foreach ($misSucursales as $_s) { $_m[(int)$_s['id']] = $_s['nombre']; }
    $_sucMapJs = json_encode($_m);
}
?>
<script>
var CART_ITEMS    = <?= $_cartJs ?>;
var SUCURSALES_MAP = <?= $_sucMapJs ?>;
</script>

<script>
(function () {
  var primary = getComputedStyle(document.documentElement).getPropertyValue('--color-primary').trim() || '#DC2626';

  // ── Tipo de entrega cards ─────────────────────────────────────────────
  function actualizarCards() {
    var val = document.querySelector('[name="tipo_entrega"]:checked')?.value;
    ['pickup','repartidor'].forEach(function(v) {
      var card = document.getElementById('card-' + v);
      if (!card) return;
      var sel = (val === v);
      card.style.borderColor = sel ? primary : '#E5E7EB';
      card.style.background  = sel ? '#FFF5F5' : '#fff';
    });
    var bPickup = document.getElementById('bloque-pickup');
    var bDir    = document.getElementById('bloque-direccion');
    if (bPickup) bPickup.style.display = (val === 'pickup')     ? '' : 'none';
    if (bDir)    bDir.style.display    = (val === 'repartidor') ? '' : 'none';
    renderDistribucion();
  }
  document.querySelectorAll('[name="tipo_entrega"]').forEach(function(r) {
    r.addEventListener('change', actualizarCards);
    r.closest('label').addEventListener('click', function() { r.checked = true; actualizarCards(); });
  });
  actualizarCards();

  // ── Editar dirección guardada (modo sin sucursales) ───────────────────
  window.toggleEditDireccion = function() {
    var ed = document.getElementById('edit-direccion');
    var hd = document.getElementById('hidden-dir');
    var hr = document.getElementById('hidden-ref');
    if (!ed || !hd) return;
    var visible = ed.style.display !== 'none';
    ed.style.display = visible ? 'none' : '';
    hd.disabled = !visible;
    if (hr) hr.disabled = !visible;
  };

  // ── Multi-parada (solo si hay sucursales registradas) ─────────────────
  var listaParadas  = document.getElementById('lista-paradas');
  if (!listaParadas) return; // modo sin sucursales, salir

  var paradasEmpty  = document.getElementById('paradas-empty');
  var hiddenCont    = document.getElementById('hidden-sucursales-ids');
  var btnToggle     = document.getElementById('btn-toggle-dropdown');
  var dropdown      = document.getElementById('dropdown-sucursales');
  var dropdownVacio = document.getElementById('dropdown-vacio');
  var btnAddManual  = document.getElementById('btn-add-manual');
  var panelManual   = document.getElementById('panel-dir-manual');
  var btnRemManual  = document.getElementById('btn-remove-manual');

  // Estado
  var paradasIds = []; // array de sucursal_id (enteros)
  var manualActivo = false;

  // Abrir/cerrar dropdown sucursales
  btnToggle.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
  });
  document.addEventListener('click', function() {
    if (dropdown) dropdown.style.display = 'none';
  });
  dropdown.addEventListener('click', function(e) { e.stopPropagation(); });

  // Añadir sucursal desde dropdown
  dropdown.querySelectorAll('.suc-option').forEach(function(opt) {
    opt.addEventListener('click', function() {
      var id     = parseInt(this.dataset.id);
      var nombre = this.dataset.nombre;
      var dir    = this.dataset.dir;
      var lat    = parseFloat(this.dataset.lat) || 0;
      var lng    = parseFloat(this.dataset.lng) || 0;
      if (paradasIds.indexOf(id) !== -1) return; // ya añadida
      paradasIds.push(id);
      agregarParadaUI(id, nombre, dir, lat, lng);
      actualizarDropdown();
      sincronizarHiddens();
      renderDistribucion();
      dropdown.style.display = 'none';
    });
  });

  // Añadir dirección manual
  btnAddManual.addEventListener('click', function() {
    if (manualActivo) return;
    manualActivo = true;
    panelManual.style.display = '';
    btnAddManual.disabled = true;
    btnAddManual.style.opacity = '.4';
    paradasEmpty.style.display = 'none';
  });

  // Quitar dirección manual
  if (btnRemManual) {
    btnRemManual.addEventListener('click', function() {
      manualActivo = false;
      panelManual.style.display = 'none';
      btnAddManual.disabled = false;
      btnAddManual.style.opacity = '1';
      // Limpiar campos manuales
      var dirInput = document.getElementById('input-dir-checkout');
      var refInput = document.getElementById('input-ref-checkout');
      if (dirInput) dirInput.value = '';
      if (refInput) refInput.value = '';
      var latEl = document.getElementById('input-lat-checkout');
      var lngEl = document.getElementById('input-lng-checkout');
      if (latEl) latEl.value = '';
      if (lngEl) lngEl.value = '';
      actualizarEmptyState();
    });
  }

  function agregarParadaUI(id, nombre, dir, lat, lng) {
    var item = document.createElement('div');
    item.className  = 'parada-item';
    item.dataset.id = id;
    item.style.cssText = 'border:1px solid #E5E7EB;border-radius:8px;padding:10px 12px;display:flex;align-items:flex-start;gap:8px;background:#fff';
    var num = paradasIds.indexOf(id) + 1;
    item.innerHTML =
      '<div style="flex-shrink:0;width:22px;height:22px;border-radius:50%;background:var(--color-primary);color:#fff;font-size:.7rem;font-weight:700;display:flex;align-items:center;justify-content:center">' + num + '</div>' +
      '<div style="flex:1;min-width:0">' +
        '<div style="font-size:.85rem;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + htmlEsc(nombre) + '</div>' +
        '<div style="font-size:.75rem;color:#6B7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + htmlEsc(dir) + '</div>' +
      '</div>' +
      '<button type="button" data-rm="' + id + '" style="background:none;border:none;color:#9CA3AF;font-size:1rem;cursor:pointer;padding:0;line-height:1;flex-shrink:0">×</button>';
    item.querySelector('[data-rm]').addEventListener('click', function() {
      quitarParada(parseInt(this.dataset.rm));
    });
    listaParadas.appendChild(item);
    paradasEmpty.style.display = 'none';
  }

  function quitarParada(id) {
    var idx = paradasIds.indexOf(id);
    if (idx === -1) return;
    paradasIds.splice(idx, 1);
    // Quitar del DOM
    var item = listaParadas.querySelector('[data-id="' + id + '"]');
    if (item) item.remove();
    // Renumerar
    listaParadas.querySelectorAll('.parada-item').forEach(function(el, i) {
      var badge = el.querySelector('div[style*="border-radius:50%"]');
      if (badge) badge.textContent = i + 1;
    });
    actualizarDropdown();
    sincronizarHiddens();
    actualizarEmptyState();
    renderDistribucion();
  }

  function actualizarDropdown() {
    var alguno = false;
    dropdown.querySelectorAll('.suc-option').forEach(function(opt) {
      var id = parseInt(opt.dataset.id);
      var usada = paradasIds.indexOf(id) !== -1;
      opt.style.display = usada ? 'none' : '';
      if (!usada) alguno = true;
    });
    if (dropdownVacio) dropdownVacio.style.display = alguno ? 'none' : '';
    btnToggle.disabled = !alguno && !dropdownVacio;
  }

  function sincronizarHiddens() {
    hiddenCont.innerHTML = '';
    paradasIds.forEach(function(id) {
      var inp = document.createElement('input');
      inp.type  = 'hidden';
      inp.name  = 'sucursales_ids[]';
      inp.value = id;
      hiddenCont.appendChild(inp);
    });
  }

  function actualizarEmptyState() {
    var hayParadas = paradasIds.length > 0 || manualActivo;
    paradasEmpty.style.display = hayParadas ? 'none' : '';
  }

  // Validar al enviar: al menos una parada + distribución completa
  document.getElementById('form-pedido').addEventListener('submit', function(e) {
    var te = document.querySelector('[name="tipo_entrega"]:checked');
    if (!te || te.value !== 'repartidor') return;
    if (paradasIds.length === 0 && !manualActivo) {
      e.preventDefault();
      paradasEmpty.style.borderColor = '#EF4444';
      paradasEmpty.style.color = '#EF4444';
      paradasEmpty.textContent = 'Añade al menos una parada de entrega antes de confirmar';
      paradasEmpty.style.display = '';
      paradasEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    // Validar distribución completa
    var bloqueD = document.getElementById('bloque-dist');
    if (!bloqueD || bloqueD.style.display === 'none') return;
    var errores = [];
    CART_ITEMS.forEach(function(item) {
      var celda = document.querySelector('.celda-rest[data-prodid="' + item.id + '"]');
      if (!celda) return;
      var rest = parseFloat(celda.textContent) || 0;
      if (Math.abs(rest) >= 0.005) {
        var msg = rest > 0
          ? 'Faltan <strong>' + rest.toFixed(2) + ' ' + (item.presentacion||'') + '</strong> por asignar'
          : 'Excede en <strong>' + Math.abs(rest).toFixed(2) + ' ' + (item.presentacion||'') + '</strong>';
        errores.push('• ' + item.nombre + ': ' + msg);
      }
    });
    if (errores.length > 0) {
      e.preventDefault();
      var m = document.getElementById('modal-dist');
      document.getElementById('modal-dist-title').textContent = 'Distribución incompleta';
      document.getElementById('modal-dist-body').innerHTML =
        'Revisa estos productos antes de confirmar:<br><br>' + errores.join('<br>') +
        '<br><br>La suma de cada producto debe ser igual al total del pedido.';
      m.style.display = 'flex';
      document.getElementById('bloque-dist').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  });

  function htmlEsc(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }

  // ── Google Maps Autocomplete (campo dirección manual) ─────────────────
  window.initGoogleMapsCheckout = function() {
    var inputDir = document.getElementById('input-dir-checkout');
    if (!inputDir || typeof google === 'undefined') return;
    var ac = new google.maps.places.Autocomplete(inputDir, {
      componentRestrictions: { country: 'mx' },
      fields: ['geometry', 'formatted_address'],
    });
    ac.addListener('place_changed', function() {
      var place = ac.getPlace();
      if (!place.geometry) return;
      var pos = place.geometry.location;
      var latEl = document.getElementById('input-lat-checkout');
      var lngEl = document.getElementById('input-lng-checkout');
      if (latEl) latEl.value = pos.lat().toFixed(7);
      if (lngEl) lngEl.value = pos.lng().toFixed(7);
    });
  };

  // ── Distribución por sucursal ─────────────────────────────────────────
  var _toastTimer = null;
  function mostrarToast(msg) {
    var t = document.getElementById('toast-dist');
    if (!t) return;
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(_toastTimer);
    _toastTimer = setTimeout(function() { t.style.display = 'none'; }, 3500);
  }

  function renderDistribucion() {
    var bloqueD = document.getElementById('bloque-dist');
    if (!bloqueD || typeof CART_ITEMS === 'undefined') return;

    var teVal = document.querySelector('[name="tipo_entrega"]:checked');
    if (!teVal || teVal.value !== 'repartidor' || paradasIds.length < 1) {
      bloqueD.style.display = 'none';
      return;
    }
    bloqueD.style.display = '';

    var distCards = document.getElementById('dist-cards');
    var badge     = document.getElementById('dist-badge');
    if (badge) badge.textContent = paradasIds.length + ' parada' + (paradasIds.length > 1 ? 's' : '');

    // Guardar valores actuales antes de re-render
    var prevVals = {};
    if (distCards) {
      distCards.querySelectorAll('.dist-input').forEach(function(inp) {
        var key = inp.dataset.prodid + '_' + inp.dataset.sucid;
        prevVals[key] = inp.value;
      });
    }

    if (!distCards) return;

    var html = '';
    CART_ITEMS.forEach(function(item) {
      html += '<div style="background:#F9FAFB;border:1.5px solid #E5E7EB;border-radius:12px;overflow:hidden;transition:border-color .3s" id="dist-card-' + item.id + '">';
      // Cabecera del producto
      html += '<div style="padding:10px 14px;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">';
      html += '<div><span style="font-weight:700;color:#111827">' + htmlEsc(item.nombre) + '</span><span style="font-size:.75rem;color:#9CA3AF;margin-left:6px">' + htmlEsc(item.presentacion) + '</span></div>';
      html += '<div style="display:flex;align-items:center;gap:10px">';
      html += '<span style="font-size:.82rem;color:#374151">Total: <strong style="color:var(--color-primary)">' + nf(item.cantidad) + ' ' + htmlEsc(item.presentacion) + '</strong></span>';
      if (paradasIds.length > 1) {
        html += '<button type="button" onclick="repartirIgualStep3(' + item.id + ',' + item.cantidad + ',' + paradasIds.length + ')" style="padding:5px 12px;background:#fff;border:1.5px solid #D1D5DB;border-radius:7px;font-size:.75rem;font-weight:600;color:#374151;cursor:pointer;font-family:inherit;white-space:nowrap;transition:all .15s" onmouseenter="this.style.borderColor=\'var(--color-primary)\';this.style.color=\'var(--color-primary)\'" onmouseleave="this.style.borderColor=\'#D1D5DB\';this.style.color=\'#374151\'">⚡ Repartir igual</button>';
      }
      html += '</div></div>';
      // Barra de progreso
      html += '<div style="padding:8px 14px 0">';
      html += '<div style="height:5px;background:#E5E7EB;border-radius:999px;overflow:hidden;margin-bottom:10px"><div id="dist-bar-' + item.id + '" style="height:100%;background:#F59E0B;border-radius:999px;width:0%;transition:width .3s,background .3s"></div></div>';
      html += '</div>';
      // Inputs por parada
      html += '<div style="padding:0 12px 12px;display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:8px">';
      paradasIds.forEach(function(sid, idx) {
        var nom    = (SUCURSALES_MAP && SUCURSALES_MAP[sid]) ? SUCURSALES_MAP[sid] : ('Parada ' + (idx + 1));
        var key    = item.id + '_' + sid;
        var defVal = (prevVals[key] !== undefined) ? prevVals[key] : (idx === 0 ? item.cantidad.toFixed(2) : '0.00');
        html += '<div style="background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:10px">';
        html += '<div style="display:flex;align-items:center;gap:6px;margin-bottom:8px"><div style="width:20px;height:20px;border-radius:50%;background:var(--color-primary);color:#fff;font-size:.65rem;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">' + (idx+1) + '</div><div style="font-size:.8rem;font-weight:600;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + htmlEsc(nom) + '</div></div>';
        html += '<div style="display:flex;align-items:center;gap:4px">';
        html += '<button type="button" onclick="ajustarDistStep3(' + item.id + ',' + sid + ',-0.5,' + item.cantidad + ')" style="width:30px;height:30px;border:1px solid #D1D5DB;border-radius:6px;background:#F9FAFB;cursor:pointer;font-size:1rem;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:center;flex-shrink:0;user-select:none;transition:all .15s" onmouseenter="this.style.borderColor=\'var(--color-primary)\';this.style.color=\'var(--color-primary)\'" onmouseleave="this.style.borderColor=\'#D1D5DB\';this.style.color=\'#374151\'">−</button>';
        html += '<input type="number" form="form-pedido" name="dist[' + item.id + '][' + sid + ']" value="' + htmlEsc(defVal) + '" min="0" max="' + item.cantidad + '" step="0.01" class="dist-input" data-prodid="' + item.id + '" data-sucid="' + sid + '" data-total="' + item.cantidad + '" data-nombre="' + htmlEsc(item.nombre) + '" style="flex:1;padding:6px 4px;border:1.5px solid #D1D5DB;border-radius:6px;font-size:.9rem;text-align:center;font-weight:700;min-width:0;outline:none;transition:border-color .15s" onclick="this.select()" onfocus="this.style.borderColor=\'var(--color-primary)\'" onblur="this.style.borderColor=\'#D1D5DB\'">';
        html += '<button type="button" onclick="ajustarDistStep3(' + item.id + ',' + sid + ',0.5,' + item.cantidad + ')" style="width:30px;height:30px;border:1px solid #D1D5DB;border-radius:6px;background:#F9FAFB;cursor:pointer;font-size:1rem;font-weight:700;color:#374151;display:flex;align-items:center;justify-content:center;flex-shrink:0;user-select:none;transition:all .15s" onmouseenter="this.style.borderColor=\'var(--color-primary)\';this.style.color=\'var(--color-primary)\'" onmouseleave="this.style.borderColor=\'#D1D5DB\';this.style.color=\'#374151\'">+</button>';
        html += '</div>';
        html += '</div>';
      });
      html += '</div>';
      // Estado
      html += '<div style="padding:6px 14px 10px;display:flex;align-items:center;gap:6px"><span id="dist-status-icon-' + item.id + '" style="font-size:.85rem">⏳</span><span id="dist-status-txt-' + item.id + '" style="font-size:.78rem;font-weight:600;color:#6B7280">Asignando…</span></div>';
      html += '</div>';
    });

    distCards.innerHTML = html;

    distCards.querySelectorAll('.dist-input').forEach(function(inp) {
      inp.addEventListener('input', function() {
        var prodId  = parseInt(this.dataset.prodid);
        var total   = parseFloat(this.dataset.total) || 0;
        var val     = parseFloat(this.value);
        if (isNaN(val) || val < 0) { this.value = '0.00'; val = 0; }
        var sumOtros = 0;
        document.querySelectorAll('.dist-input[data-prodid="' + prodId + '"]').forEach(function(other) {
          if (other !== inp) sumOtros += parseFloat(other.value) || 0;
        });
        var maxPerm = Math.max(0, Math.round((total - sumOtros) * 1000) / 1000);
        if (val > maxPerm + 0.004) {
          mostrarToast('⚠ No puedes asignar más de ' + nf(maxPerm) + ' — el total de ' + this.dataset.nombre + ' es ' + nf(total));
          this.value = nf(maxPerm);
        }
        actualizarRestante(prodId);
      });
    });
    CART_ITEMS.forEach(function(item) { actualizarRestante(item.id); });
  }

  window.ajustarDistStep3 = function(prodId, sucId, delta, totalProd) {
    var input = document.querySelector('.dist-input[data-prodid="' + prodId + '"][data-sucid="' + sucId + '"]');
    if (!input) return;
    var val = parseFloat(input.value) || 0;
    var sumOtros = 0;
    document.querySelectorAll('.dist-input[data-prodid="' + prodId + '"]').forEach(function(other) {
      if (other !== input) sumOtros += parseFloat(other.value) || 0;
    });
    var maxPerm = Math.max(0, Math.round((totalProd - sumOtros) * 1000) / 1000);
    var nuevo = Math.max(0, Math.min(maxPerm, Math.round((val + delta) * 2) / 2));
    input.value = nuevo > 0 ? nuevo : '0.00';
    actualizarRestante(prodId);
  };

  window.repartirIgualStep3 = function(prodId, total, numParadas) {
    if (numParadas === 0) return;
    var inputs = document.querySelectorAll('.dist-input[data-prodid="' + prodId + '"]');
    var base   = Math.floor((total / numParadas) * 2) / 2;
    var suma   = 0;
    inputs.forEach(function(inp, idx) {
      if (idx < inputs.length - 1) { inp.value = base; suma += base; }
      else { var rest = Math.round((total - suma) * 2) / 2; inp.value = rest > 0 ? rest : 0; }
    });
    actualizarRestante(prodId);
  };

  function actualizarRestante(prodId) {
    var inputs = document.querySelectorAll('.dist-input[data-prodid="' + prodId + '"]');
    if (!inputs.length) return;
    var total  = parseFloat(inputs[0].dataset.total) || 0;
    var suma   = 0;
    inputs.forEach(function(inp) {
      var v = parseFloat(inp.value) || 0;
      if (v < 0) { inp.value = '0.00'; v = 0; }
      suma += v;
    });
    suma = Math.round(suma * 1000) / 1000;
    var completo = Math.abs(suma - total) < 0.005;
    var pct      = total > 0 ? Math.min(100, (suma / total) * 100) : 0;

    // Barra
    var bar = document.getElementById('dist-bar-' + prodId);
    if (bar) {
      bar.style.width      = pct + '%';
      bar.style.background = completo ? '#10B981' : '#F59E0B';
    }
    // Card border
    var card = document.getElementById('dist-card-' + prodId);
    if (card) card.style.borderColor = completo ? '#A7F3D0' : '#E5E7EB';

    // Estado texto
    var icon  = document.getElementById('dist-status-icon-' + prodId);
    var stTxt = document.getElementById('dist-status-txt-' + prodId);
    var rest  = Math.round((total - suma) * 1000) / 1000;
    if (completo) {
      if (icon)  icon.textContent  = '✅';
      if (stTxt) { stTxt.textContent = 'Distribución completa'; stTxt.style.color = '#059669'; }
    } else {
      if (icon)  icon.textContent  = '⏳';
      if (stTxt) { stTxt.textContent = 'Faltan ' + rest.toFixed(2) + ' por asignar'; stTxt.style.color = '#6B7280'; }
    }

    // celda-rest (usada por validación de submit)
    var celda = document.querySelector('.celda-rest[data-prodid="' + prodId + '"]');
    if (celda) {
      celda.textContent  = nf(rest);
      celda.style.color  = completo ? '#059669' : '#D97706';
    }

    // Hint global
    var todoBien = true;
    CART_ITEMS.forEach(function(item) {
      var c = document.querySelector('.dist-input[data-prodid="' + item.id + '"]');
      if (!c) return;
      var s2 = 0;
      document.querySelectorAll('.dist-input[data-prodid="' + item.id + '"]').forEach(function(x) { s2 += parseFloat(x.value)||0; });
      if (Math.abs(Math.round(s2*1000)/1000 - parseFloat(c.dataset.total)) >= 0.005) todoBien = false;
    });
    var hint = document.getElementById('dist-hint');
    if (hint) {
      if (todoBien) {
        hint.innerHTML = '<span style="color:#059669;font-weight:700">✅ Distribución completa. Puedes confirmar el pedido.</span>';
      } else {
        hint.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg> Los precios por volumen se calculan sobre el total del pedido.';
      }
    }
  }

  function nf(v) { return parseFloat(v).toFixed(2); }

  function htmlEsc(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
  }

  renderDistribucion();
})();
</script>

<?php if ($gmKey): ?>
<script async defer
  src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars($gmKey) ?>&libraries=places&callback=initGoogleMapsCheckout">
</script>
<?php endif; ?>
