<?php
// Vista: Formulario nueva empresa (panel admin)
$empresa  = $empresa  ?? [];
$planes   = $planes   ?? [];
$editando = !empty($empresa['id']);
?>
<div style="max-width:640px">
  <form method="POST" action="<?= BASE_URL ?><?= $editando ? 'panel-empresa/actualizar/'.$empresa['id'] : 'panel-empresa/guardar' ?>">

    <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:16px">
      <h2 style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:#111827">Datos de la empresa</h2>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
        <div style="grid-column:span 2">
          <label class="form-label">Razón Social *</label>
          <input type="text" name="razon_social" class="form-control" value="<?= htmlspecialchars($empresa['razon_social'] ?? '') ?>" required>
        </div>
        <div>
          <label class="form-label">RFC</label>
          <input type="text" name="rfc" class="form-control" value="<?= htmlspecialchars($empresa['rfc'] ?? '') ?>" maxlength="13" placeholder="AAA000101AAA">
        </div>
        <div>
          <label class="form-label">Tipo de negocio</label>
          <select name="tipo_negocio" class="form-control">
            <option value="">Selecciona...</option>
            <?php foreach (['taqueria'=>'Taquería','carniceria'=>'Carnicería','restaurante'=>'Restaurante','comedor'=>'Comedor industrial','otro'=>'Otro'] as $v => $l): ?>
            <option value="<?= $v ?>" <?= ($empresa['tipo_negocio'] ?? '') === $v ? 'selected' : '' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">Correo de contacto</label>
          <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($empresa['email'] ?? '') ?>">
        </div>
        <div>
          <label class="form-label">Teléfono</label>
          <input type="tel" name="telefono" class="form-control"
                 value="<?= htmlspecialchars($empresa['telefono'] ?? '') ?>"
                 maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                 placeholder="10 dígitos"
                 oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
        </div>
        <div style="grid-column:span 2">
          <label class="form-label">Dirección fiscal</label>
          <textarea name="direccion_fiscal" class="form-control" rows="2"><?= htmlspecialchars($empresa['direccion_fiscal'] ?? '') ?></textarea>
        </div>
      </div>
    </div>

    <?php if (!$editando && !empty($planes)): ?>
    <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:16px">
      <h2 style="font-size:.95rem;font-weight:700;margin-bottom:4px;color:#111827">Plan de suscripción</h2>
      <p style="font-size:.8rem;color:#6B7280;margin-bottom:14px">Selecciona el plan inicial de esta empresa.</p>

      <!-- Toggle Mensual / Anual -->
      <div style="display:inline-flex;border:1px solid #E5E7EB;border-radius:8px;overflow:hidden;margin-bottom:16px">
        <button type="button" id="btn-mensual"
                onclick="setCiclo('mensual')"
                style="padding:7px 20px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;background:var(--color-primary);color:#fff;transition:all .15s">
          Mensual
        </button>
        <button type="button" id="btn-anual"
                onclick="setCiclo('anual')"
                style="padding:7px 20px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;background:#F3F4F6;color:#6B7280;transition:all .15s">
          Anual <span style="font-size:.72rem;font-weight:400">(2 meses gratis)</span>
        </button>
      </div>
      <input type="hidden" name="ciclo" id="inp-ciclo" value="mensual">

      <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px">
        <?php foreach ($planes as $idx => $pl): ?>
        <label style="cursor:pointer">
          <input type="radio" name="plan_id" value="<?= $pl['id'] ?>"
                 <?= $idx === 0 ? 'checked' : '' ?>
                 style="display:none"
                 onchange="document.querySelectorAll('.plan-card').forEach(c=>c.classList.remove('selected'));this.closest('.plan-card').classList.add('selected')">
          <div class="plan-card <?= $idx === 0 ? 'selected' : '' ?>"
               data-mensual="<?= number_format($pl['precio_mensual'], 0, '.', ',') ?>"
               data-anual="<?= number_format($pl['precio_anual'], 0, '.', ',') ?>"
               style="border:2px solid <?= $idx === 0 ? 'var(--color-primary)' : '#E5E7EB' ?>;border-radius:10px;padding:14px;text-align:center;transition:border .15s"
               onclick="this.closest('label').querySelector('input').click();document.querySelectorAll('.plan-card').forEach(c=>c.style.borderColor='#E5E7EB');this.style.borderColor='var(--color-primary)'">
            <div style="font-weight:700;font-size:.9rem;color:#111827;margin-bottom:4px"><?= htmlspecialchars($pl['nombre']) ?></div>
            <div style="font-size:1.1rem;font-weight:800;color:var(--color-primary)">
              $<span class="plan-precio"><?= number_format($pl['precio_mensual'], 0, '.', ',') ?></span>
              <span class="plan-periodo" style="font-size:.7rem;font-weight:400;color:#6B7280">/mes</span>
            </div>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
    </div>
    <script>
    function setCiclo(ciclo) {
      document.getElementById('inp-ciclo').value = ciclo;
      const esMensual = ciclo === 'mensual';
      document.getElementById('btn-mensual').style.cssText = esMensual
        ? 'padding:7px 20px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;background:var(--color-primary);color:#fff;transition:all .15s'
        : 'padding:7px 20px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;background:#F3F4F6;color:#6B7280;transition:all .15s';
      document.getElementById('btn-anual').style.cssText = !esMensual
        ? 'padding:7px 20px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;background:var(--color-primary);color:#fff;transition:all .15s'
        : 'padding:7px 20px;font-size:.82rem;font-weight:700;border:none;cursor:pointer;background:#F3F4F6;color:#6B7280;transition:all .15s';
      document.querySelectorAll('.plan-card').forEach(card => {
        card.querySelector('.plan-precio').textContent = esMensual ? card.dataset.mensual : card.dataset.anual;
        card.querySelector('.plan-periodo').textContent = esMensual ? '/mes' : '/año';
      });
    }
    </script>
    <?php elseif ($editando): ?>
    <div style="background:#F9FAFB;border-radius:10px;padding:14px;margin-bottom:16px;font-size:.875rem;color:#6B7280;border:1px solid #E5E7EB">
      Para cambiar el plan de esta empresa ve a
      <a href="<?= BASE_URL ?>suscripcion/index" style="color:var(--color-primary);font-weight:600">Suscripciones</a>.
    </div>
    <?php endif; ?>

    <div style="display:flex;gap:10px">
      <button type="submit" style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-weight:600;font-size:.875rem;cursor:pointer">
        <?= $editando ? 'Guardar cambios' : 'Crear empresa' ?>
      </button>
      <a href="<?= BASE_URL ?>panel-empresa/index" style="padding:10px 20px;background:#F3F4F6;color:#374151;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
        Cancelar
      </a>
    </div>
  </form>
</div>
