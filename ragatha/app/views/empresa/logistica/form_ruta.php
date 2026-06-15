<?php
// Vista: Formulario nueva ruta (admin_empresa)
$baseUrl = BASE_URL;
?>

<div style="max-width:680px">
  <a href="<?= $baseUrl ?>empresa-logistica/index"
     style="display:inline-flex;align-items:center;gap:4px;font-size:.875rem;color:#6B7280;text-decoration:none;margin-bottom:20px">
    ← Volver a Logística
  </a>

  <form method="POST" action="<?= $baseUrl ?>empresa-logistica/guardarRuta">
    <!-- Repartidor -->
    <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
      <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:12px">Datos de la ruta</h3>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Repartidor *</label>
          <select name="repartidor_id" required style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem">
            <option value="">Selecciona repartidor...</option>
            <?php foreach ($repartidores as $rep): ?>
              <option value="<?= $rep['id'] ?>"><?= htmlspecialchars($rep['nombre'] . ' ' . $rep['apellido_paterno']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($repartidores)): ?>
            <p style="font-size:.75rem;color:#D97706;margin-top:4px">No tienes repartidores activos. <a href="<?= $baseUrl ?>empresa-usuario/nuevo" style="color:var(--color-primary)">Crear uno</a></p>
          <?php endif; ?>
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:4px">Fecha de entrega *</label>
          <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"
                 style="width:100%;padding:9px 12px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;box-sizing:border-box">
        </div>
      </div>
    </div>

    <!-- Pedidos disponibles -->
    <div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;padding:20px;margin-bottom:16px">
      <h3 style="font-size:.9rem;font-weight:700;color:#111827;margin-bottom:4px">Pedidos confirmados disponibles</h3>
      <p style="font-size:.75rem;color:#6B7280;margin-bottom:14px">Selecciona los pedidos que incluirás en esta ruta.</p>

      <?php if (empty($pedidosDisp)): ?>
        <div style="padding:24px;text-align:center;color:#9CA3AF;background:#F9FAFB;border-radius:8px">
          No hay pedidos confirmados pendientes de ruta.
        </div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:8px">
        <?php foreach ($pedidosDisp as $ped): ?>
        <label style="display:flex;align-items:center;gap:12px;padding:12px;border:1px solid #E5E7EB;border-radius:8px;cursor:pointer">
          <input type="checkbox" name="pedidos_ids[]" value="<?= $ped['id'] ?>">
          <div style="flex:1">
            <div style="font-size:.875rem;font-weight:600;color:#111827"><?= htmlspecialchars($ped['folio']) ?></div>
            <div style="font-size:.75rem;color:#6B7280">
              <?= htmlspecialchars($ped['comprador_nombre'] ?? '') ?> ·
              $<?= number_format($ped['total'], 2) ?> ·
              <?= date('d/m/Y', strtotime($ped['created_at'])) ?>
            </div>
          </div>
          <span style="font-size:.75rem;padding:3px 8px;background:#D1FAE5;color:#065F46;border-radius:999px;font-weight:600">
            Confirmado
          </span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

    <div style="display:flex;gap:12px">
      <button type="submit" <?= empty($pedidosDisp) || empty($repartidores) ? 'disabled' : '' ?>
              style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer;<?= empty($pedidosDisp) || empty($repartidores) ? 'opacity:.5' : '' ?>">
        Crear ruta
      </button>
      <a href="<?= $baseUrl ?>empresa-logistica/index"
         style="padding:10px 20px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#374151;text-decoration:none">
        Cancelar
      </a>
    </div>
  </form>
</div>
