<?php ob_start();
// Slug del restaurante para el QR
$_resQr  = (new RestauranteModel())->find($_SESSION['restaurante_activo_id'] ?? 0);
$_qrSlug = $_resQr['slug'] ?? '';
$_qrUrl  = BASE_URL . 'menu/' . $_qrSlug . '/reservar';

// Solo reservaciones del comensal (QR)
$_delComensal = array_values(array_filter($data ?? [], fn($r) => ($r['origen'] ?? 'restaurante') === 'comensal'));

// Badge de estado — muestra 'llegó' durante la ventana activa y 'completada' al finalizar
$_badge = function(string $estado, string $fecha = '', string $hora = ''): string {
    // Estado visual dinámico para reservaciones confirmadas
    if ($estado === 'confirmada' && $fecha && $hora) {
        $reservaTs = strtotime("$fecha $hora");
        $now       = time();
        if ($now >= $reservaTs && $now <= $reservaTs + 3 * 3600) {
            return "<span style='padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:600;background:#DBEAFE;color:#1D4ED8'>llegó \u{1F4CD}</span>";
        } elseif ($now > $reservaTs + 3 * 3600) {
            return "<span style='padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:600;background:#F3F4F6;color:#374151'>completada</span>";
        }
    }
    $map = [
        'pendiente'  => ['#FEF3C7','#92400E'],
        'confirmada' => ['#DCFCE7','#166534'],
        'cancelada'  => ['#FEE2E2','#991B1B'],
        'completada' => ['#F3F4F6','#374151'],
    ];
    [$bg, $fg] = $map[$estado] ?? ['#F3F4F6','#374151'];
    return "<span style='padding:2px 8px;border-radius:99px;font-size:.72rem;font-weight:600;background:$bg;color:$fg'>$estado</span>";
};
?>

<!-- ── Header ─────────────────────────────────────────────── -->
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <?php if ($_qrSlug): ?>
  <button onclick="document.getElementById('modalQr').classList.add('open')"
    style="padding:8px 14px;background:#fff;color:#374151;border:1px solid #D1D5DB;border-radius:8px;font-size:.85rem;font-weight:500;cursor:pointer">
    📱 QR reservas
  </button>
  <?php else: ?>
  <div></div>
  <?php endif; ?>
</div>

<!-- ── Filtro de fechas ─────────────────────────────────────── -->
<form method="GET" action="<?= BASE_URL ?>rest-reserva/index" style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:12px;padding:16px;margin-bottom:16px">
  <div style="display:flex;flex-wrap:wrap;gap:12px;align-items:end">
    <div style="flex:1;min-width:140px">
      <label style="display:block;font-size:.75rem;font-weight:600;color:#6B7280;margin-bottom:4px">Desde</label>
      <input type="date" name="fecha_desde" value="<?= htmlspecialchars($fecha_desde ?? '') ?>"
        style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem">
    </div>
    <div style="flex:1;min-width:140px">
      <label style="display:block;font-size:.75rem;font-weight:600;color:#6B7280;margin-bottom:4px">Hasta</label>
      <input type="date" name="fecha_hasta" value="<?= htmlspecialchars($fecha_hasta ?? '') ?>"
        style="width:100%;padding:8px 10px;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem">
    </div>
    <button type="submit"
      style="padding:8px 16px;background:#C8102E;color:#fff;border:none;border-radius:6px;font-size:.875rem;font-weight:600;cursor:pointer">
      Filtrar
    </button>
    <?php if (!empty($fecha_desde) || !empty($fecha_hasta)): ?>
    <a href="<?= BASE_URL ?>rest-reserva/index"
      style="padding:8px 16px;background:#fff;color:#374151;border:1px solid #D1D5DB;border-radius:6px;font-size:.875rem;text-decoration:none">
      Limpiar
    </a>
    <?php endif; ?>
  </div>
  <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
    <button type="button" onclick="filtroRapido('hoy')"
      style="padding:6px 12px;background:#fff;color:#374151;border:1px solid #D1D5DB;border-radius:6px;font-size:.75rem;font-weight:500;cursor:pointer">
      📅 Hoy
    </button>
    <button type="button" onclick="filtroRapido('semana')"
      style="padding:6px 12px;background:#fff;color:#374151;border:1px solid #D1D5DB;border-radius:6px;font-size:.75rem;font-weight:500;cursor:pointer">
      📆 Esta semana
    </button>
    <button type="button" onclick="filtroRapido('mes')"
      style="padding:6px 12px;background:#fff;color:#374151;border:1px solid #D1D5DB;border-radius:6px;font-size:.75rem;font-weight:500;cursor:pointer">
      🗓️ Este mes
    </button>
  </div>
</form>

<script>
function filtroRapido(periodo) {
  const hoy = new Date();
  const formatFecha = d => d.toISOString().split('T')[0];
  let desde, hasta;

  if (periodo === 'hoy') {
    desde = hasta = formatFecha(hoy);
  } else if (periodo === 'semana') {
    const inicioSemana = new Date(hoy);
    inicioSemana.setDate(hoy.getDate() - hoy.getDay()); // Domingo
    const finSemana = new Date(inicioSemana);
    finSemana.setDate(inicioSemana.getDate() + 6); // Sábado
    desde = formatFecha(inicioSemana);
    hasta = formatFecha(finSemana);
  } else if (periodo === 'mes') {
    desde = formatFecha(new Date(hoy.getFullYear(), hoy.getMonth(), 1));
    hasta = formatFecha(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0));
  }

  document.querySelector('[name="fecha_desde"]').value = desde;
  document.querySelector('[name="fecha_hasta"]').value = hasta;
  document.querySelector('form').submit();
}
</script>

<!-- ══ Solicitudes del comensal (vía QR) ════════════════════ -->
<div>
  <div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
    <span style="font-size:.95rem;font-weight:700;color:#111827">📱 Reservaciones</span>
    <?php $pendQr = count(array_filter($_delComensal, fn($r) => $r['estado'] === 'pendiente')); ?>
    <?php if ($pendQr > 0): ?>
      <span style="background:#FEF3C7;color:#92400E;font-size:.72rem;font-weight:700;padding:2px 9px;border-radius:99px">
        <?= $pendQr ?> pendiente<?= $pendQr > 1 ? 's' : '' ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if (empty($_delComensal)): ?>
    <div style="background:#fff;border:1px dashed #D1D5DB;border-radius:12px;padding:32px;text-align:center;color:#9CA3AF;font-size:.88rem">
      <div style="font-size:2rem;margin-bottom:10px">📅</div>
      Aún no hay reservaciones. Comparte el QR para que los comensales reserven desde su celular.
    </div>
  <?php else: ?>
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
    <table style="width:100%;border-collapse:collapse;font-size:.875rem">
      <thead>
        <tr style="background:#FFFBEB;border-bottom:1px solid #FDE68A">
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151">Comensal</th>
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151">Fecha / Hora</th>
          <th style="padding:10px 14px;text-align:center;font-weight:600;color:#374151">Pax</th>
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151">Mesa</th>
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151">Estado</th>
          <th style="padding:10px 14px;text-align:left;font-weight:600;color:#374151">Acciones</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($_delComensal as $r): ?>
      <tr style="border-bottom:1px solid #F3F4F6">
        <td style="padding:11px 14px">
          <div style="font-weight:600"><?= htmlspecialchars($r['nombre']) ?></div>
          <?php if ($r['telefono']): ?>
            <a href="tel:<?= preg_replace('/\D/', '', $r['telefono']) ?>"
               style="font-size:.76rem;color:#6B7280;text-decoration:none">
              <?= htmlspecialchars($r['telefono']) ?>
            </a>
          <?php endif; ?>
          <?php if ($r['email'] ?? ''): ?>
            <div style="font-size:.74rem;color:#9CA3AF"><?= htmlspecialchars($r['email']) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding:11px 14px">
          <div style="font-weight:600"><?= date('d/m/Y', strtotime($r['fecha'])) ?></div>
          <div style="color:#6B7280;font-size:.85rem"><?= substr($r['hora'],0,5) ?></div>
        </td>
        <td style="padding:11px 14px;text-align:center;font-weight:600"><?= (int)$r['personas'] ?></td>
        <td style="padding:11px 14px">
          <?= $r['mesa_nombre'] ? htmlspecialchars($r['mesa_nombre']) : '<span style="color:#9CA3AF;font-size:.82rem">—</span>' ?>
        </td>
        <td style="padding:11px 14px"><?= $_badge($r['estado'], $r['fecha'] ?? '', $r['hora'] ?? '') ?></td>
        <td style="padding:11px 14px">
          <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
            <!-- Ver detalles -->
            <button type="button"
              data-r='<?= htmlspecialchars(json_encode([
                'id'           => (int)$r['id'],
                'nombre'       => $r['nombre']       ?? '',
                'telefono'     => $r['telefono']     ?? '',
                'email'        => $r['email']        ?? '',
                'fecha'        => $r['fecha']        ?? '',
                'hora'         => $r['hora']         ?? '',
                'personas'     => (int)($r['personas'] ?? 0),
                'mesa_nombre'  => $r['mesa_nombre']  ?? '',
                'notas'        => $r['notas']        ?? '',
                'estado'       => $r['estado']       ?? '',
              ]), ENT_QUOTES) ?>'
              onclick="abrirDetalle(JSON.parse(this.dataset.r))"
              class="btn btn-outline btn-sm">
              👁 Ver
            </button>
            <!-- Cancelar (solo si no está ya cancelada/completada) -->
            <?php if (!in_array($r['estado'], ['cancelada','completada'])): ?>
            <form method="POST" action="<?= BASE_URL ?>rest-reserva/cambiarEstado/<?= $r['id'] ?>"
                  onsubmit="return confirm('¿Cancelar esta reservación?')">
              <input type="hidden" name="estado" value="cancelada">
              <button type="submit"
                style="font-size:.75rem;padding:4px 10px;border:1px solid #EF4444;color:#EF4444;background:none;border-radius:6px;cursor:pointer;white-space:nowrap">
                ✕ Cancelar
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ── Modal detalle ──────────────────────────────────────── -->
<div id="modal-reserva-det" class="rst-modal-backdrop">
  <div class="rst-modal rst-modal-sm">
    <div class="rst-modal-header">
      <div class="rst-modal-title">Detalle de reservación</div>
      <button class="rst-modal-close"
              onclick="document.getElementById('modal-reserva-det').classList.remove('open')">✕</button>
    </div>
    <div id="modal-reserva-body"></div>
  </div>
</div>

<!-- ── Modal QR ───────────────────────────────────────────── -->
<div id="modalQr" class="rst-modal-backdrop">
  <div class="rst-modal rst-modal-sm" style="text-align:center">
    <div class="rst-modal-header" style="justify-content:flex-end">
      <button class="rst-modal-close"
              onclick="document.getElementById('modalQr').classList.remove('open')">✕</button>
    </div>
    <h3 style="font-weight:700;margin-bottom:6px">📱 QR de reservaciones</h3>
    <p style="font-size:.82rem;color:#6B7280;margin-bottom:16px">
      Muéstralo en tu local para que los comensales reserven sin app
    </p>
    <?php if ($_qrSlug): ?>
    <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=<?= urlencode($_qrUrl) ?>&ecc=M"
         alt="QR Reservaciones"
         style="border:1px solid #E5E7EB;border-radius:10px;padding:8px;width:220px;height:220px">
    <div style="margin-top:12px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:8px 10px;font-size:.72rem;color:#6B7280;word-break:break-all">
      <?= htmlspecialchars($_qrUrl) ?>
    </div>
    <div style="display:flex;gap:10px;margin-top:16px;justify-content:center">
      <a href="<?= htmlspecialchars($_qrUrl) ?>" target="_blank" class="btn btn-outline btn-sm">
        Abrir enlace
      </a>
    </div>
    <?php else: ?>
    <div style="padding:24px;color:#9CA3AF;font-size:.88rem">No hay restaurante activo.</div>
    <?php endif; ?>
  </div>
</div>

<script>
function abrirDetalle(r) {
  const fmtFecha = r.fecha ? r.fecha.split('-').reverse().join('/') : '—';
  const hora     = (r.hora || '').substring(0, 5) || '—';

  // Calcular estado visual según hora actual
  function estadoVisual(estado, fecha, hora) {
    if (estado === 'confirmada' && fecha && hora) {
      const reservaTs = new Date(fecha + 'T' + hora).getTime();
      const now = Date.now();
      if (now >= reservaTs && now <= reservaTs + 3 * 3600 * 1000)
        return { label: 'lleg\u00f3 \uD83D\uDCCD', bg: '#DBEAFE', fg: '#1D4ED8' };
      if (now > reservaTs + 3 * 3600 * 1000)
        return { label: 'completada', bg: '#F3F4F6', fg: '#374151' };
    }
    const estadoMap = {
      pendiente:  { label: 'pendiente',  bg: '#FEF3C7', fg: '#92400E' },
      confirmada: { label: 'confirmada', bg: '#DCFCE7', fg: '#166534' },
      cancelada:  { label: 'cancelada',  bg: '#FEE2E2', fg: '#991B1B' },
      completada: { label: 'completada', bg: '#F3F4F6', fg: '#374151' },
    };
    return estadoMap[estado] || { label: estado, bg: '#F3F4F6', fg: '#374151' };
  }

  const ev = estadoVisual(r.estado, r.fecha, r.hora);
  const { bg, fg } = ev;

  document.getElementById('modal-reserva-body').innerHTML = `
    <div style="display:grid;gap:16px">
      <div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Nombre</div>
        <div style="font-weight:700;margin-top:2px;font-size:1rem">${r.nombre || '—'}</div>
      </div>
      ${r.telefono ? `<div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Teléfono</div>
        <div style="margin-top:2px"><a href="tel:${r.telefono}" style="color:var(--color-primary);font-weight:600">${r.telefono}</a></div>
      </div>` : ''}
      ${r.email ? `<div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Correo</div>
        <div style="margin-top:2px;font-size:.88rem">${r.email}</div>
      </div>` : ''}
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Fecha</div>
          <div style="font-weight:600;margin-top:2px">${fmtFecha}</div>
        </div>
        <div>
          <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Hora</div>
          <div style="font-weight:600;margin-top:2px">${hora}</div>
        </div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div>
          <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Personas</div>
          <div style="font-weight:600;margin-top:2px">${r.personas || '—'}</div>
        </div>
        <div>
          <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Mesa</div>
          <div style="font-weight:600;margin-top:2px">${r.mesa_nombre || '—'}</div>
        </div>
      </div>
      ${r.notas ? `<div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Notas</div>
        <div style="margin-top:2px;font-style:italic;color:#6B7280">${r.notas}</div>
      </div>` : ''}
      <div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Estado</div>
        <div style="margin-top:4px">
          <span style="padding:3px 10px;border-radius:99px;font-size:.78rem;font-weight:600;background:${bg};color:${fg}">${ev.label}</span>
        </div>
      </div>
    </div>
  `;
  document.getElementById('modal-reserva-det').classList.add('open');
}

// Cerrar al hacer click en el backdrop
document.querySelectorAll('.rst-modal-backdrop').forEach(bd => {
  bd.addEventListener('click', e => { if (e.target === bd) bd.classList.remove('open'); });
});
</script>

<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
