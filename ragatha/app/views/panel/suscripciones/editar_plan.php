<?php
// Variables: $plan (array con datos del plan)
$ilimitado = fn(int $v) => $v === 0 ? 'Ilimitado' : $v;
?>
<div style="max-width:560px">
  <a href="<?= BASE_URL ?>suscripcion/configurar"
     style="display:inline-flex;align-items:center;gap:6px;color:#6B7280;font-size:.875rem;text-decoration:none;margin-bottom:20px">
    ← Volver a configurar
  </a>

  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:24px">
    <h3 style="margin:0 0 4px;font-size:1rem;font-weight:700;color:#111827">
      Editar plan: <?= htmlspecialchars($plan['nombre']) ?>
    </h3>
    <p style="margin:0 0 20px;font-size:.8rem;color:#6B7280">
      Los cambios afectan a todas las empresas nuevas que contraten este plan.
      Las empresas existentes mantienen sus límites actuales hasta su próxima renovación.
    </p>

    <form method="POST" action="<?= BASE_URL ?>suscripcion/guardarPlan/<?= (int)$plan['id'] ?>">

      <!-- Nombre y descripción -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div>
          <label class="form-label">Nombre del plan *</label>
          <input type="text" name="nombre" required class="form-control"
                 value="<?= htmlspecialchars($plan['nombre']) ?>">
        </div>
        <div>
          <label class="form-label">Descripción corta</label>
          <input type="text" name="descripcion" class="form-control"
                 value="<?= htmlspecialchars($plan['descripcion'] ?? '') ?>">
        </div>
      </div>

      <!-- Precios -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div>
          <label class="form-label">Precio mensual (MXN) *</label>
          <input type="number" name="precio_mensual" required min="0" step="0.01" class="form-control"
                 value="<?= number_format((float)$plan['precio_mensual'], 2, '.', '') ?>">
        </div>
        <div>
          <label class="form-label">Precio anual (MXN)</label>
          <input type="number" name="precio_anual" min="0" step="0.01" class="form-control"
                 value="<?= number_format((float)$plan['precio_anual'], 2, '.', '') ?>">
        </div>
      </div>

      <!-- Límites -->
      <p style="font-size:.8rem;font-weight:700;color:#374151;margin:20px 0 12px">
        Límites del plan
        <span style="font-weight:400;color:#6B7280">— Usa 0 para ilimitado</span>
      </p>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px">
        <div>
          <label class="form-label">Máx. usuarios</label>
          <input type="number" name="max_usuarios" min="0" class="form-control"
                 value="<?= (int)$plan['max_usuarios'] ?>">
          <p style="font-size:.72rem;color:#9CA3AF;margin:3px 0 0">Actual: <?= $ilimitado((int)$plan['max_usuarios']) ?></p>
        </div>
        <div>
          <label class="form-label">Máx. productos</label>
          <input type="number" name="max_productos" min="0" class="form-control"
                 value="<?= (int)$plan['max_productos'] ?>">
          <p style="font-size:.72rem;color:#9CA3AF;margin:3px 0 0">Actual: <?= $ilimitado((int)$plan['max_productos']) ?></p>
        </div>
        <div>
          <label class="form-label">Máx. pedidos/mes</label>
          <input type="number" name="max_pedidos_mes" min="0" class="form-control"
                 value="<?= (int)$plan['max_pedidos_mes'] ?>">
          <p style="font-size:.72rem;color:#9CA3AF;margin:3px 0 0">Actual: <?= $ilimitado((int)$plan['max_pedidos_mes']) ?></p>
        </div>
        <div>
          <label class="form-label">Máx. sucursales</label>
          <input type="number" name="max_sucursales" min="0" class="form-control"
                 value="<?= (int)$plan['max_sucursales'] ?>">
          <p style="font-size:.72rem;color:#9CA3AF;margin:3px 0 0">Actual: <?= $ilimitado((int)$plan['max_sucursales']) ?></p>
        </div>
      </div>

      <div style="display:flex;gap:10px;margin-top:20px">
        <button type="submit"
                style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
          Guardar cambios
        </button>
        <a href="<?= BASE_URL ?>suscripcion/configurar"
           style="padding:10px 20px;background:#F3F4F6;color:#374151;border-radius:8px;font-size:.875rem;text-decoration:none;font-weight:600">
          Cancelar
        </a>
      </div>
    </form>
  </div>
</div>
