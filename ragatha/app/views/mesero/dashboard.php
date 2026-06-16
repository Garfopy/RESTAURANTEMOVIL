<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mesero — <?= htmlspecialchars($restaurante['nombre'] ?? '') ?></title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { background: #F3F4F6; font-family: system-ui, -apple-system, sans-serif; min-height: 100vh; }

    /* ── Topbar ── */
    .topbar {
      background: #fff; border-bottom: 1px solid #E5E7EB;
      padding: 0 20px; height: 56px; position: sticky; top: 0; z-index: 20;
      display: flex; align-items: center; justify-content: space-between;
    }
    .topbar-brand { font-weight: 700; font-size: 1rem; color: #111827; display: flex; align-items: center; gap: 10px; }
    .topbar-right  { display: flex; align-items: center; gap: 10px; }
    .badge-cnt {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 22px; height: 22px; padding: 0 6px; border-radius: 11px;
      font-size: .72rem; font-weight: 700; line-height: 1;
    }
    .bc-rojo   { background: #FEE2E2; color: #991B1B; }
    .bc-verde  { background: #DCFCE7; color: #166534; }
    .bc-gris   { background: #F3F4F6; color: #6B7280; }
    .btn-top { padding: 8px 14px; border-radius: 8px; font-size: .83rem; font-weight: 600; text-decoration: none; }
    .btn-primario { background: #C8102E; color: #fff; }
    .exit-link { color: #6B7280; font-size: .78rem; text-decoration: none; }

    /* ── Sección ── */
    .section { padding: 0 16px 20px; }
    .section-title { font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em;
                     color: #9CA3AF; margin: 20px 0 10px; }

    /* ── Panel alertas ── */
    #alertasBanner {
      margin: 12px 16px 0;
      background: #FFFBEB; border: 1px solid #FCD34D; border-radius: 14px; padding: 14px;
      animation: slideIn .3s ease;
    }
    #alertasBanner.hidden { display: none; }
    @keyframes slideIn { from { opacity:0; transform:translateY(-8px); } to { opacity:1; transform:translateY(0); } }
    .alerta-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 8px 0; border-bottom: 1px solid #FEF3C7;
    }
    .alerta-row:last-child { border-bottom: none; }

    /* ── Listos panel ── */
    #listosBanner {
      margin: 12px 16px 0;
      background: #F0FDF4; border: 1px solid #86EFAC; border-radius: 14px; padding: 14px;
    }
    #listosBanner.hidden { display: none; }
    .listo-card {
      background: #fff; border: 1px solid #D1FAE5; border-radius: 10px;
      padding: 12px; margin-bottom: 8px;
      display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;
    }
    .listo-card:last-child { margin-bottom: 0; }

    /* ── Grid de mesas ── */
    .mesas-grid {
      display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px;
      padding: 0 16px;
    }
    .mesa-card {
      background: #fff; border: 2px solid #E5E7EB; border-radius: 14px;
      padding: 14px 10px; text-align: center; cursor: pointer;
      transition: transform .15s, border-color .2s;
      position: relative;
    }
    .mesa-card:active { transform: scale(.96); }
    .mesa-card.disponible { border-color: #10B981; }
    .mesa-card.ocupada    { border-color: #F59E0B; }
    .mesa-card.pagando    { border-color: #EF4444; }
    .mesa-card.reservada  { border-color: #6366F1; }
    .mesa-card.mi-zona    { box-shadow: 0 0 0 3px #BFDBFE; }
    .mesa-estado-dot {
      display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px;
    }
    .dot-disponible { background: #10B981; }
    .dot-ocupada    { background: #F59E0B; }
    .dot-pagando    { background: #EF4444; }
    .dot-reservada  { background: #6366F1; }
    .pedidos-badge {
      position: absolute; top: 8px; right: 8px;
      background: #F59E0B; color: #fff; font-size: .65rem; font-weight: 700;
      padding: 1px 6px; border-radius: 10px; display: none;
    }
    .cuenta-badge {
      position: absolute; top: 8px; left: 8px;
      background: #EF4444; color: #fff; font-size: .65rem; font-weight: 700;
      padding: 1px 6px; border-radius: 10px; display: none;
      z-index: 2;
    }

    /* ── Panel cuentas pendientes ── */
    #cuentasBanner {
      margin: 12px 16px 0;
      background: #FEF2F2; border: 1.5px solid #FCA5A5; border-radius: 14px; padding: 14px;
      animation: slideIn .3s ease;
    }
    #cuentasBanner.hidden { display: none; }

    /* ── Botón flotante Tomar mis listos ── */
    #btnTomarZona {
      position: fixed; bottom: 24px; right: 20px;
      background: #2563EB; color: #fff; border: none; border-radius: 50px;
      padding: 14px 22px; font-size: .88rem; font-weight: 700; cursor: pointer;
      box-shadow: 0 4px 20px rgba(37,99,235,.45); display: none;
      transition: transform .15s, filter .15s; z-index: 40;
    }
    #btnTomarZona:hover { filter: brightness(1.1); transform: translateY(-2px); }
    #btnTomarZona:active { transform: scale(.96); }

    /* ── Botones ── */
    .btn-sm {
      padding: 6px 14px; border-radius: 8px; border: none;
      font-size: .78rem; font-weight: 600; cursor: pointer;
    }
    .btn-atender  { background: #F59E0B; color: #fff; }
    .btn-entregar { background: #10B981; color: #fff; }
    .btn-sm:disabled { opacity: .5; cursor: not-allowed; }

    /* ── Modal ── */
    #modal-overlay {
      position: fixed; inset: 0; background: rgba(0,0,0,.45);
      display: flex; align-items: flex-end; justify-content: center;
      z-index: 50; opacity: 0; pointer-events: none; transition: opacity .25s;
    }
    #modal-overlay.open { opacity: 1; pointer-events: all; }
    #modal-sheet {
      background: #fff; border-radius: 20px 20px 0 0; padding: 24px 20px 32px;
      width: 100%; max-width: 480px; max-height: 80vh; overflow-y: auto;
      transform: translateY(100%); transition: transform .3s cubic-bezier(.34,1.56,.64,1);
    }
    #modal-overlay.open #modal-sheet { transform: translateY(0); }

    /* ── Toast ── */
    #m-toast {
      position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(80px);
      background: #111827; color: #fff; padding: 12px 22px; border-radius: 30px;
      font-size: .85rem; font-weight: 600; opacity: 0; z-index: 99;
      transition: transform .35s cubic-bezier(.34,1.56,.64,1), opacity .35s;
    }
    #m-toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }
  </style>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="topbar-brand">
    🍽 Mesero
    <span style="font-size:.82rem;color:#6B7280;font-weight:400"><?= htmlspecialchars($restaurante['nombre'] ?? '') ?></span>
  </div>
  <div class="topbar-right">
    <span id="badge-alertas" class="badge-cnt bc-gris" title="Alertas">—</span>
    <span id="badge-listos"  class="badge-cnt bc-gris" title="Listos">—</span>
    <a href="<?= BASE_URL ?>auth/logoutStaff/mesero" class="exit-link">Salir</a>
  </div>
</div>

<!-- Cuentas pendientes (alertas tipo=cuenta) -->
<div id="cuentasBanner" class="hidden">
  <div style="font-weight:700;color:#991B1B;font-size:.88rem;margin-bottom:10px;display:flex;align-items:center;gap:6px">
    💳 Cuentas pendientes <span id="cnt-cuentas-text"></span>
  </div>
  <div id="cuentasList"></div>
</div>

<!-- Alertas de comensales -->
<div id="alertasBanner" class="hidden">
  <div style="font-weight:700;color:#92400E;font-size:.88rem;margin-bottom:10px;display:flex;align-items:center;gap:6px">
    🔔 Solicitudes de comensales <span id="cnt-alertas-text"></span>
  </div>
  <div id="alertasList"></div>
</div>

<!-- Órdenes listas para entregar -->
<div id="listosBanner" class="hidden">
  <div style="font-weight:700;color:#166534;font-size:.88rem;margin-bottom:10px;display:flex;align-items:center;gap:6px">
    ✅ Mis mesas — listas para entregar <span id="cnt-listos-text"></span>
  </div>
  <div id="listosList"></div>

  <!-- Otras mesas (colapsable) -->
  <div id="otrasSection" class="hidden" style="margin-top:14px">
    <button onclick="toggleOtras()" id="btnOtras"
      style="background:none;border:none;color:#6B7280;font-size:.8rem;font-weight:600;cursor:pointer;padding:0;display:flex;align-items:center;gap:4px">
      ▶ Otras mesas <span id="cnt-otras-text"></span>
    </button>
    <div id="otrasList" class="hidden" style="margin-top:8px"></div>
  </div>
</div>

<!-- Mesas -->
<div class="section">
  <div class="section-title" style="padding-left:0">
    Mesas
    <?php if (!empty($misZonas)): ?>
      <span style="font-size:.68rem;font-weight:500;color:#6B7280;text-transform:none;letter-spacing:0;margin-left:6px">
        — 🟢 borde azul = tu zona
      </span>
    <?php endif; ?>
  </div>
</div>
<div class="mesas-grid">
  <?php foreach ($mesas as $m): ?>
  <div class="mesa-card <?= htmlspecialchars($m['estado']) ?> <?= $m['es_mi_zona'] ? 'mi-zona' : '' ?>"
       onclick="abrirMesa(<?= (int)$m['id'] ?>, '<?= htmlspecialchars(addslashes($m['nombre'])) ?>', '<?= htmlspecialchars($m['estado']) ?>')"
       id="mesa-card-<?= (int)$m['id'] ?>">
    <div id="badge-pedidos-<?= (int)$m['id'] ?>" class="pedidos-badge"></div>
    <div id="badge-cuenta-<?= (int)$m['id'] ?>" class="cuenta-badge">💳</div>
    <?php if ($m['es_mi_zona']): ?>
      <div style="position:absolute;top:6px;left:6px;font-size:.6rem;background:#DBEAFE;color:#1D4ED8;padding:1px 5px;border-radius:6px;font-weight:700">MI ZONA</div>
    <?php endif; ?>
    <div style="font-size:1.6rem;margin-bottom:6px">🪑</div>
    <div style="font-weight:700;font-size:.92rem;color:#111827"><?= htmlspecialchars($m['nombre']) ?></div>
    <div style="font-size:.7rem;color:#9CA3AF;margin-top:2px"><?= (int)$m['capacidad'] ?> personas</div>
    <div style="font-size:.7rem;font-weight:700;margin-top:6px;display:flex;align-items:center;justify-content:center">
      <span class="mesa-estado-dot dot-<?= htmlspecialchars($m['estado']) ?>"></span>
      <?= strtoupper($m['estado']) ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Modal detalle de mesa -->
<div id="modal-overlay" onclick="cerrarModal(event)">
  <div id="modal-sheet">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <div>
        <div id="modal-mesa-nombre" style="font-size:1.1rem;font-weight:700"></div>
        <div id="modal-mesa-estado" style="font-size:.78rem;color:#6B7280;margin-top:2px"></div>
      </div>
      <a id="modal-nuevo-pedido" href="#" style="padding:8px 14px;background:#C8102E;color:#fff;border-radius:8px;font-size:.82rem;font-weight:600;text-decoration:none">+ Pedido</a>
    </div>
    <div id="modal-pedidos" style="font-size:.85rem;color:#6B7280;text-align:center;padding:20px 0">Cargando...</div>
  </div>
</div>

<!-- Reservas próximas en mis zonas -->
<div class="section" style="padding-bottom:4px">
  <div class="section-title" style="padding-left:0">📅 Próximas reservas (hoy y mañana)</div>
</div>
<div id="reservasHoy" style="padding:0 16px 28px;min-height:40px">
  <div style="font-size:.82rem;color:#9CA3AF">Cargando...</div>
</div>

<!-- Modal detalle reservación -->
<div id="modal-reserva-det" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:60;align-items:center;justify-content:center;padding:16px">
  <div style="background:#fff;border-radius:16px;padding:24px;width:100%;max-width:380px;max-height:90vh;overflow-y:auto">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
      <h3 style="font-weight:700;color:#111827;font-size:1rem">Detalle de reservación</h3>
      <button onclick="document.getElementById('modal-reserva-det').style.display='none'"
              style="background:none;border:none;font-size:1.3rem;cursor:pointer;color:#6B7280">✕</button>
    </div>
    <div id="modal-reserva-body"></div>
  </div>
</div>

<button id="btnTomarZona" onclick="tomarZona(this)">⚡ Tomar mis listos <span id="cnt-tomar">0</span></button>

<div id="m-toast"></div>

<script>
const BASE = '<?= BASE_URL ?>';
const TIPO_LABEL = { mesero: '🙋 Llama al mesero', cuenta: '💳 Pide la cuenta' };
const ESTADO_LABEL = { pendiente:'Recibido', en_preparacion:'Preparando', listo:'¡Listo!', entregado:'Entregado', cancelado:'Cancelado' };
const ESTADO_COLOR = { pendiente:'#FEF3C7', en_preparacion:'#DBEAFE', listo:'#DCFCE7', entregado:'#F3F4F6', cancelado:'#FEE2E2' };
const ESTADO_TEXT  = { pendiente:'#92400E', en_preparacion:'#1E40AF', listo:'#166534', entregado:'#6B7280', cancelado:'#991B1B' };

// ── Toast ───────────────────────────────────────────────────────────────────
let toastT;
function toast(msg) {
  const t = document.getElementById('m-toast');
  t.textContent = msg; clearTimeout(toastT);
  t.classList.add('show');
  toastT = setTimeout(() => t.classList.remove('show'), 3000);
}

// ── Vibrar ──────────────────────────────────────────────────────────────────
function vibrar() { try { navigator.vibrate && navigator.vibrate(200); } catch {} }

// ── Alertas polling ─────────────────────────────────────────────────────────
let prevAlertasCount = 0;

function pollAlertas() {
  fetch(BASE + 'rest-mesero/alertas')
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;

      const cuentas = d.alertas.filter(a => a.tipo === 'cuenta');
      const meseros = d.alertas.filter(a => a.tipo !== 'cuenta');
      const cntTotal = d.alertas.length;

      // Topbar badge
      const badge = document.getElementById('badge-alertas');
      badge.textContent = cntTotal;
      badge.className   = 'badge-cnt ' + (cntTotal > 0 ? 'bc-rojo' : 'bc-gris');

      if (cntTotal > prevAlertasCount && prevAlertasCount > 0) vibrar();
      prevAlertasCount = cntTotal;

      // ── Panel cuentas pendientes (prioridad alta) ──
      const cuentaBanner = document.getElementById('cuentasBanner');
      if (cuentas.length) {
        document.getElementById('cnt-cuentas-text').textContent = `(${cuentas.length})`;
        document.getElementById('cuentasList').innerHTML = cuentas.map(a =>
          `<div class="alerta-row" style="border-color:#FEE2E2">
            <span style="font-size:.85rem;color:#991B1B">
              <strong>💳 Pide la cuenta</strong>
              ${a.mesa_nombre ? ' · Mesa <strong>' + a.mesa_nombre + '</strong>' : ''}
            </span>
            <button class="btn-sm" style="background:#EF4444;color:#fff" onclick="atenderAlerta(${a.id},this)">Atendido ✓</button>
          </div>`
        ).join('');
        cuentaBanner.classList.remove('hidden');
      } else {
        cuentaBanner.classList.add('hidden');
      }

      // ── Panel alertas mesero ──
      const banner = document.getElementById('alertasBanner');
      if (!meseros.length) {
        banner.classList.add('hidden');
      } else {
        document.getElementById('cnt-alertas-text').textContent = `(${meseros.length})`;
        document.getElementById('alertasList').innerHTML = meseros.map(a =>
          `<div class="alerta-row">
            <span style="font-size:.85rem;color:#78350F">
              <strong>${TIPO_LABEL[a.tipo] ?? a.tipo}</strong>
              ${a.mesa_nombre ? ' · Mesa <strong>' + a.mesa_nombre + '</strong>' : ''}
            </span>
            <button class="btn-sm btn-atender" onclick="atenderAlerta(${a.id},this)">Atendido ✓</button>
          </div>`
        ).join('');
        banner.classList.remove('hidden');
      }

      // ── Badge 💳 en tarjeta de mesa ──
      // Limpiar badges anteriores
      document.querySelectorAll('.cuenta-badge').forEach(el => el.style.display = 'none');
      // Mesa con alerta de cuenta activa
      const mesasConCuenta = new Set(cuentas.map(a => a.mesa_id).filter(Boolean));
      mesasConCuenta.forEach(mesaId => {
        const el = document.getElementById('badge-cuenta-' + mesaId);
        if (el) el.style.display = '';
      });
    })
    .catch(() => {});
}

function atenderAlerta(id, btn) {
  btn.disabled = true;
  fetch(`${BASE}rest-mesero/atenderAlerta/${id}`, { method: 'POST' })
    .then(r => r.json())
    .then(d => { if (d.ok) pollAlertas(); else btn.disabled = false; })
    .catch(() => { btn.disabled = false; });
}

// ── Listos polling ──────────────────────────────────────────────────────────
let prevListosIds = new Set();
let otrasExpanded = false;

function toggleOtras() {
  otrasExpanded = !otrasExpanded;
  const lista = document.getElementById('otrasList');
  const btn   = document.getElementById('btnOtras');
  lista.classList.toggle('hidden', !otrasExpanded);
  btn.textContent = (otrasExpanded ? '▼ ' : '▶ ') + 'Otras mesas ' + btn.textContent.replace(/^[▼▶]\s*Otras mesas\s*/,'');
}

function serializeActionId(value) {
  return JSON.stringify(String(value ?? ''));
}

function buildListoCard(p) {
  const itemsText = (p.items || []).map(i => `${i.cantidad}× ${i.nombre}`).join(', ');
  const tipoBadge = p.es_regalo_social
    ? `<span style="font-size:.68rem;background:#FCE7F3;color:#9D174D;padding:1px 7px;border-radius:999px;font-weight:700">🎁 Regalo</span>`
    : '';

  if (p.reclamado_otro) {
    // Otro mesero ya lo reclamó — mostrar chip informativo
    return `<div class="listo-card" id="listo-${p.id}" style="opacity:.7">
      <div style="flex:1">
        <div style="font-weight:700;font-size:.9rem;color:#111827">${p.folio} · Mesa ${p.mesa_nombre || '—'} ${tipoBadge}</div>
        ${itemsText ? `<div style="font-size:.78rem;color:#6B7280;margin-top:3px">${itemsText}</div>` : ''}
      </div>
      <span style="font-size:.75rem;font-weight:600;padding:4px 10px;border-radius:20px;background:#FEF3C7;color:#92400E;white-space:nowrap">
        🚶 En camino
      </span>
    </div>`;
  }

  if (p.es_mi_reclamo) {
    // Yo lo reclamé — mostrar Entregado directo
    return `<div class="listo-card" id="listo-${p.id}" style="border-color:#BFDBFE">
      <div style="flex:1">
        <div style="font-weight:700;font-size:.9rem;color:#111827">${p.folio} · Mesa ${p.mesa_nombre || '—'} ${tipoBadge} <span style="font-size:.7rem;background:#DBEAFE;color:#1D4ED8;padding:1px 6px;border-radius:8px;font-weight:700">RECLAMADO</span></div>
        ${itemsText ? `<div style="font-size:.78rem;color:#6B7280;margin-top:3px">${itemsText}</div>` : ''}
      </div>
      <button class="btn-sm btn-entregar" onclick="marcarEntregado(${serializeActionId(p.id)},this)">Entregado ✓</button>
    </div>`;
  }

  // Disponible — entregar directo
  return `<div class="listo-card" id="listo-${p.id}">
    <div style="flex:1">
      <div style="font-weight:700;font-size:.9rem;color:#111827">${p.folio} · Mesa ${p.mesa_nombre || '—'} ${tipoBadge}</div>
      ${itemsText ? `<div style="font-size:.78rem;color:#6B7280;margin-top:3px">${itemsText}</div>` : ''}
    </div>
    <div style="display:flex;flex-direction:column;gap:5px;align-items:flex-end">
      <button class="btn-sm btn-entregar" onclick="marcarEntregado(${serializeActionId(p.id)},this)">Entregado ✓</button>
    </div>
  </div>`;
}

function pollListos() {
  fetch(BASE + 'rest-mesero/listos')
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      const listos = d.listos;

      const misMesas   = listos.filter(p => p.es_mi_zona && !p.reclamado_otro);
      const otrosMesas = listos.filter(p => !p.es_mi_zona || p.reclamado_otro);
      const otrosRegalos = otrosMesas.filter(p => p.es_regalo_social);
      const cntMias    = misMesas.length;
      const cntTotal   = listos.length;

      // Badge topbar (solo los pendientes de mis mesas)
      const badge = document.getElementById('badge-listos');
      badge.textContent = cntMias || cntTotal;
      badge.className   = 'badge-cnt ' + (cntTotal > 0 ? 'bc-verde' : 'bc-gris');

      const banner = document.getElementById('listosBanner');
      if (!cntTotal) { banner.classList.add('hidden'); prevListosIds = new Set(); return; }

      // Detectar nuevos en mis mesas
      const newIds = new Set(misMesas.map(l => l.id));
      const hayNuevos = [...newIds].some(id => !prevListosIds.has(id));
      if (hayNuevos && prevListosIds.size > 0) { vibrar(); toast('🔔 ¡Pedido listo en tu zona!'); }
      prevListosIds = newIds;

      // Contar sin los "en camino"
      const listosMios = misMesas.filter(p => !p.es_mi_reclamo || p.es_mi_reclamo).length;
      document.getElementById('cnt-listos-text').textContent = listosMios ? `(${listosMios})` : '';
      document.getElementById('listosList').innerHTML = misMesas.length
        ? misMesas.map(buildListoCard).join('')
        : '<div style="font-size:.82rem;color:#9CA3AF;padding:4px 0">Sin pedidos en tus mesas por ahora.</div>';

      // Sección "Otras mesas"
      const otrasSection = document.getElementById('otrasSection');
      if (otrosMesas.length) {
        // Si hay regalos sociales fuera de mi zona, no los escondemos en una sección colapsada.
        if (otrosRegalos.length > 0 || (misMesas.length === 0 && otrosMesas.length > 0)) {
          otrasExpanded = true;
        }

        document.getElementById('cnt-otras-text').textContent = `(${otrosMesas.length})`;
        document.getElementById('otrasList').innerHTML = otrosMesas.map(buildListoCard).join('');
        otrasSection.classList.remove('hidden');
        // Actualizar texto del botón preservando estado expand
        const btn = document.getElementById('btnOtras');
        btn.childNodes[0].textContent = (otrasExpanded ? '▼ ' : '▶ ') + `Otras mesas (${otrosMesas.length})`;
        document.getElementById('otrasList').classList.toggle('hidden', !otrasExpanded);
      } else {
        otrasSection.classList.add('hidden');
        otrasExpanded = false;
      }

      banner.classList.remove('hidden');

      // Actualizar botón flotante: pedidos 'listo' de mis zonas sin reclamar
      const sinReclamar = misMesas.filter(p => p.estado === 'listo' && !p.es_mi_reclamo && !p.reclamado_otro).length;
      _actualizarBtnTomar(sinReclamar);
    })
    .catch(() => {});
}

function marcarEntregado(pedidoId, btn) {
  btn.disabled = true; btn.textContent = '...';
  fetch(`${BASE}rest-mesero/marcarEntregado/${pedidoId}`, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (d.ok) {
        const card = document.getElementById('modal-pedido-' + pedidoId)
                  || document.getElementById('listo-' + pedidoId);
        if (card) {
          card.style.opacity = '0';
          card.style.transition = 'opacity .3s';
          setTimeout(() => {
            card.remove();
            pollListos();
            const cont = document.getElementById('modal-pedidos');
            if (cont && !cont.querySelector('[id^="modal-pedido-"]')) {
              cont.innerHTML = '<div style="text-align:center;padding:20px;color:#9CA3AF">Sin pedidos activos en esta mesa.</div>';
            }
          }, 300);
        } else {
          pollListos();
        }
        toast('✅ Pedido marcado como entregado');
      } else {
        btn.disabled = false; btn.textContent = 'Entregado ✓';
        toast('⚠️ ' + (d.msg || 'No se pudo marcar como entregado'));
      }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Entregado ✓'; });
}

// ── Modal mesa ───────────────────────────────────────────────────────────────
let modalMesaId = null;

function abrirMesa(mesaId, nombre, estado) {
  modalMesaId = mesaId;
  document.getElementById('modal-mesa-nombre').textContent = 'Mesa: ' + nombre;
  document.getElementById('modal-mesa-estado').textContent = 'Estado: ' + estado;
  document.getElementById('modal-nuevo-pedido').href = BASE + 'rest-pedido/nuevo/' + mesaId;
  document.getElementById('modal-pedidos').innerHTML = '<div style="text-align:center;padding:20px;color:#9CA3AF">Cargando pedidos...</div>';
  document.getElementById('modal-overlay').classList.add('open');

  fetch(BASE + 'rest-mesero/pedidosMesa/' + mesaId)
    .then(r => r.json())
    .then(d => {
      const cont = document.getElementById('modal-pedidos');
      if (!d.ok || !d.pedidos.length) {
        cont.innerHTML = '<div style="text-align:center;padding:20px;color:#9CA3AF">Sin pedidos activos en esta mesa.</div>';
        return;
      }
      cont.innerHTML = d.pedidos.map(p => {
        const col = ESTADO_COLOR[p.estado] || '#F3F4F6';
        const txt = ESTADO_TEXT[p.estado]  || '#374151';
        const tipoBadge = p.es_regalo_social
          ? `<span style="font-size:.66rem;background:#FCE7F3;color:#9D174D;padding:2px 7px;border-radius:999px;font-weight:700;margin-left:6px">🎁 Regalo</span>`
          : '';
        const itemsHtml = (p.items || []).map(it =>
          `<div style="padding:6px 0;border-bottom:1px solid #F3F4F6;display:flex;justify-content:space-between;align-items:center">
            <span style="font-size:.85rem">${it.cantidad}× ${it.nombre}</span>
            <span style="font-size:.72rem;font-weight:600;padding:2px 8px;border-radius:10px;background:${ESTADO_COLOR[it.estado]||'#F3F4F6'};color:${ESTADO_TEXT[it.estado]||'#374151'}">${ESTADO_LABEL[it.estado]||it.estado}</span>
          </div>`
        ).join('');
        return `<div id="modal-pedido-${p.id}" style="background:#F9FAFB;border:1px solid #E5E7EB;border-radius:10px;padding:12px;margin-bottom:10px">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
            <span style="font-weight:700;font-size:.88rem">${p.folio}${tipoBadge}</span>
            <span style="font-size:.72rem;font-weight:600;padding:2px 10px;border-radius:10px;background:${col};color:${txt}">${ESTADO_LABEL[p.estado]||p.estado}</span>
          </div>
          ${itemsHtml}
          ${(p.estado === 'listo' || (p.estado === 'reclamado' && p.es_mi_reclamo))
            ? `<button class="btn-sm btn-entregar" style="width:100%;margin-top:8px" onclick="marcarEntregado(${serializeActionId(p.id)},this)">Entregado ✓</button>`
            : (p.estado === 'reclamado' && !p.es_mi_reclamo)
              ? `<div style="font-size:.75rem;color:#92400E;margin-top:8px;text-align:center">🚶 Reclamado por ${p.reclamado_por_nombre || 'otro mesero'}</div>`
              : ''}
        </div>`;
      }).join('');
    })
    .catch(() => {
      document.getElementById('modal-pedidos').innerHTML = '<div style="text-align:center;padding:20px;color:#9CA3AF">No se pudieron cargar los pedidos.</div>';
    });
}

function cerrarModal(e) {
  if (e.target === document.getElementById('modal-overlay')) {
    document.getElementById('modal-overlay').classList.remove('open');
    modalMesaId = null;
  }
}

// ── Reservas hoy + mañana ────────────────────────────────────────────────────
let _reservaData = {};
function cargarReservasHoy() {
  fetch(BASE + 'rest-mesero/reservasHoy')
    .then(r => r.json())
    .then(d => {
      const cont = document.getElementById('reservasHoy');
      if (!d.ok || !d.reservas.length) {
        cont.innerHTML = '<div style="font-size:.82rem;color:#9CA3AF">Sin reservas próximas en tus zonas.</div>';
        return;
      }
      _reservaData = {};
      d.reservas.forEach(r => { _reservaData[r.id] = r; });
      const HOY    = '<?= date('Y-m-d') ?>';
      const MANANA = '<?= date('Y-m-d', strtotime('+1 day')) ?>';
      const hoyList    = d.reservas.filter(r => r.fecha === HOY);
      const mananaList = d.reservas.filter(r => r.fecha === MANANA);
      function renderGroup(list) {
        return list.map(r => {
          const bg   = r.estado === 'confirmada' ? '#DCFCE7' : '#FEF3C7';
          const col  = r.estado === 'confirmada' ? '#166534' : '#92400E';
          const hora = (r.hora || '').substring(0, 5);
          const mesa = r.mesa_nombre || 'Sin mesa';
          return `<div style="background:#fff;border:1px solid #E5E7EB;border-radius:10px;padding:12px;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;gap:8px">
            <div style="flex:1;min-width:0">
              <div style="font-weight:700;font-size:.9rem">${hora} — ${r.nombre}</div>
              <div style="font-size:.75rem;color:#6B7280">${r.personas} personas · ${mesa}</div>
            </div>
            <div style="display:flex;align-items:center;gap:6px">
              <span style="font-size:.72rem;font-weight:600;padding:3px 9px;border-radius:10px;background:${bg};color:${col};white-space:nowrap">${r.estado}</span>
              <button onclick="abrirDetalleReserva(_reservaData[${r.id}])"
                style="font-size:.72rem;padding:4px 9px;border:1px solid #9CA3AF;color:#6B7280;background:none;border-radius:6px;cursor:pointer;white-space:nowrap">👁</button>
            </div>
          </div>`;
        }).join('');
      }
      let html = '';
      if (hoyList.length) {
        html += '<div style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.06em;margin:8px 0 6px">— Hoy —</div>';
        html += renderGroup(hoyList);
      }
      if (mananaList.length) {
        html += '<div style="font-size:.7rem;font-weight:700;color:#9CA3AF;text-transform:uppercase;letter-spacing:.06em;margin:8px 0 6px">— Mañana —</div>';
        html += renderGroup(mananaList);
      }
      cont.innerHTML = html || '<div style="font-size:.82rem;color:#9CA3AF">Sin reservas próximas.</div>';
    })
    .catch(() => {});
}

function abrirDetalleReserva(r) {
  if (!r) return;
  const fmtFecha = r.fecha ? r.fecha.split('-').reverse().join('/') : '—';
  const hora = (r.hora || '').substring(0, 5) || '—';
  const estadoMap = {
    pendiente:  ['#FEF3C7','#92400E'],
    confirmada: ['#DCFCE7','#166534'],
    cancelada:  ['#FEE2E2','#991B1B'],
    completada: ['#F3F4F6','#374151']
  };
  const [bg, fg] = estadoMap[r.estado] || ['#F3F4F6','#374151'];
  document.getElementById('modal-reserva-body').innerHTML = `
    <div style="display:grid;gap:14px">
      <div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Nombre</div>
        <div style="font-weight:700;margin-top:2px;font-size:1rem">${r.nombre || '—'}</div>
      </div>
      ${r.telefono ? `<div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Teléfono</div>
        <div style="margin-top:2px"><a href="tel:${r.telefono}" style="color:#1D4ED8;font-weight:600">${r.telefono}</a></div>
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
          <div style="font-weight:600;margin-top:2px">${r.mesa_nombre || '<span style="color:#EF4444">Sin asignar</span>'}</div>
        </div>
      </div>
      ${r.notas ? `<div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Notas</div>
        <div style="margin-top:2px;font-style:italic;color:#6B7280">${r.notas}</div>
      </div>` : ''}
      <div>
        <div style="font-size:.72rem;color:#9CA3AF;text-transform:uppercase;letter-spacing:.05em">Estado</div>
        <div style="margin-top:4px"><span style="padding:3px 10px;border-radius:99px;font-size:.78rem;font-weight:600;background:${bg};color:${fg}">${r.estado}</span></div>
      </div>
    </div>
  `;
  document.getElementById('modal-reserva-det').style.display = 'flex';
}

// ── Iniciar polling ──────────────────────────────────────────────────────────
pollAlertas();
pollListos();
cargarReservasHoy();
setInterval(pollAlertas, 5000);
setInterval(pollListos,  5000);

// ── Botón flotante: Tomar mis listos ────────────────────────────────────────
// El contador se alimenta desde pollListos() — pedidos 'listo' en mis zonas no reclamados
function _actualizarBtnTomar(count) {
  const btn = document.getElementById('btnTomarZona');
  const cnt = document.getElementById('cnt-tomar');
  if (!btn) return;
  if (count > 0) {
    cnt.textContent = count;
    btn.style.display = 'block';
  } else {
    btn.style.display = 'none';
  }
}

function tomarZona(btn) {
  const orig = btn.innerHTML;
  btn.disabled = true; btn.textContent = '⏳ Tomando...';
  fetch(BASE + 'rest-mesero/tomarZona', { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      btn.disabled = false; btn.innerHTML = orig;
      if (d.ok) {
        toast(`✅ ${d.count > 0 ? d.count + ' pedido(s) reclamados' : 'Ya estaban reclamados'}`);
        pollListos();
      } else {
        toast('⚠️ ' + (d.msg || 'Error'));
      }
    })
    .catch(() => { btn.disabled = false; btn.innerHTML = orig; toast('⚠️ Sin conexión'); });
}
</script>
</body>
</html>
