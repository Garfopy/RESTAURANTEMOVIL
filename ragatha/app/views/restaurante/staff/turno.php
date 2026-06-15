<?php ob_start(); ?>

<style>
  .zona-chip { display:inline-flex;align-items:center;gap:6px;padding:5px 12px;border-radius:20px;
               border:2px solid #E5E7EB;font-size:.82rem;font-weight:600;cursor:pointer;
               transition:border-color .15s,background .15s; user-select:none; }
  .zona-chip input[type=checkbox] { display:none; }
  .zona-chip.checked { border-color:#3B82F6;background:#DBEAFE;color:#1D4ED8; }
  .mesero-row { display:flex;align-items:flex-start;gap:16px;padding:14px 0;
                border-bottom:1px solid #F3F4F6; }
  .mesero-row:last-child { border-bottom:none; }
  .mesero-info { min-width:140px; }
  .mesero-zonas { display:flex;flex-wrap:wrap;gap:8px; }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <div>
    <h2 style="font-size:1.1rem;font-weight:700;color:#111827;margin:0">Turno de hoy</h2>
    <div style="font-size:.82rem;color:#6B7280;margin-top:2px"><?= date('d \d\e F \d\e Y') ?></div>
  </div>
  <a href="<?= BASE_URL ?>rest-staff/index" class="btn btn-outline btn-sm">← Volver al staff</a>
</div>

<?php if (empty($meseros)): ?>
<div class="empty-state">
  <div style="font-size:2rem;margin-bottom:8px">🧑‍💼</div>
  <div style="font-size:.95rem;font-weight:600;color:#374151;margin-bottom:4px">Sin meseros activos</div>
  <div>Crea meseros desde la sección <a href="<?= BASE_URL ?>rest-staff/index">Staff</a>.</div>
</div>
<?php elseif (empty($zonas)): ?>
<div class="empty-state">
  <div style="font-size:2rem;margin-bottom:8px">🗺️</div>
  <div style="font-size:.95rem;font-weight:600;color:#374151;margin-bottom:4px">Sin zonas definidas</div>
  <div>Crea zonas (Terraza, Salón, etc.) en la configuración del restaurante y luego vuelve aquí.</div>
</div>
<?php else: ?>

<div class="rst-card" style="padding:20px">
  <form method="POST" action="<?= BASE_URL ?>rest-staff/guardarTurno">

    <div id="meserosList">
      <?php foreach ($meseros as $mesero): ?>
      <div class="mesero-row">
        <div class="mesero-info">
          <div style="font-weight:700;font-size:.9rem;color:#111827">
            <?= htmlspecialchars($mesero['nombre']) ?>
          </div>
          <div style="font-size:.75rem;color:#9CA3AF;font-family:monospace">
            <?= htmlspecialchars($mesero['codigo'] ?? '') ?>
          </div>
        </div>
        <div class="mesero-zonas">
          <?php foreach ($zonas as $zona): ?>
            <?php
            $checked = in_array((int)$zona['id'], $asignaciones[(int)$mesero['id']] ?? []);
            ?>
            <label class="zona-chip <?= $checked ? 'checked' : '' ?>">
              <input type="checkbox"
                     name="asignaciones[<?= (int)$mesero['id'] ?>][]"
                     value="<?= (int)$zona['id'] ?>"
                     <?= $checked ? 'checked' : '' ?>
                     onchange="this.closest('.zona-chip').classList.toggle('checked', this.checked)">
              <?= htmlspecialchars($zona['nombre']) ?>
            </label>
          <?php endforeach; ?>
          <span style="font-size:.75rem;color:#9CA3AF;align-self:center">
            (ninguna = ve todo)
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div style="margin-top:20px;display:flex;gap:10px;justify-content:flex-end">
      <button type="submit" class="btn btn-primary">Guardar turno</button>
    </div>

  </form>
</div>

<?php endif; ?>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
