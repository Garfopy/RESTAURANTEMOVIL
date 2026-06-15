<?php
// Variables: $repartidores[], $empresas[]
?>
<div style="max-width:700px">
  <a href="<?= BASE_URL ?>panel-logistica/index"
     style="display:inline-flex;align-items:center;gap:6px;color:#6B7280;font-size:.875rem;text-decoration:none;margin-bottom:20px">
    ← Volver a logística
  </a>

  <div style="background:#fff;border:1px solid #E5E7EB;border-radius:12px;padding:24px">
    <h3 style="margin:0 0 20px;font-size:1rem;font-weight:700;color:#111827">Crear nueva ruta de entrega</h3>

    <form method="POST" action="<?= BASE_URL ?>panel-logistica/guardarRuta">

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Repartidor *</label>
          <select name="repartidor_id" required style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
            <option value="">Seleccionar repartidor...</option>
            <?php foreach ($repartidores as $rep): ?>
            <option value="<?= $rep['id'] ?>">
              <?= htmlspecialchars($rep['nombre'] . ' ' . $rep['apellido_paterno']) ?> — <?= htmlspecialchars($rep['empresa_nombre']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Empresa *</label>
          <select name="empresa_id" required id="sel-empresa"
                  onchange="cargarPedidos(this.value)"
                  style="width:100%;padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
            <option value="">Seleccionar empresa...</option>
            <?php foreach ($empresas as $emp): ?>
            <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['razon_social']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div style="margin-bottom:16px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:5px">Fecha de entrega *</label>
        <input type="date" name="fecha" required value="<?= date('Y-m-d') ?>"
               style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;outline:none">
      </div>

      <div style="margin-bottom:20px">
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:8px">
          Pedidos a asignar <span style="font-weight:400;color:#9CA3AF">(selecciona uno o más pedidos confirmados)</span>
        </label>
        <div id="lista-pedidos" style="border:1px solid #E5E7EB;border-radius:8px;min-height:80px;padding:12px;background:#F9FAFB">
          <p style="color:#9CA3AF;font-size:.875rem;margin:0">Selecciona una empresa para ver sus pedidos confirmados.</p>
        </div>
      </div>

      <div style="display:flex;gap:10px">
        <button type="submit"
                style="padding:10px 24px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:600;cursor:pointer">
          Crear ruta
        </button>
        <a href="<?= BASE_URL ?>panel-logistica/index"
           style="padding:10px 20px;background:#F3F4F6;color:#374151;border-radius:8px;font-size:.875rem;text-decoration:none;font-weight:600">
          Cancelar
        </a>
      </div>
    </form>
  </div>
</div>

<script>
function cargarPedidos(empresaId) {
  const cont = document.getElementById('lista-pedidos');
  if (!empresaId) {
    cont.innerHTML = '<p style="color:#9CA3AF;font-size:.875rem;margin:0">Selecciona una empresa para ver sus pedidos confirmados.</p>';
    return;
  }
  cont.innerHTML = '<p style="color:#9CA3AF;font-size:.875rem;margin:0">Cargando...</p>';

  fetch('<?= BASE_URL ?>api/pedidosConfirmados?empresa_id=' + empresaId)
    .then(r => r.json())
    .then(data => {
      if (!data.length) {
        cont.innerHTML = '<p style="color:#9CA3AF;font-size:.875rem;margin:0">No hay pedidos confirmados para esta empresa.</p>';
        return;
      }
      cont.innerHTML = data.map(p => `
        <label style="display:flex;align-items:center;gap:10px;padding:8px;border-radius:6px;cursor:pointer;margin-bottom:4px" onmouseover="this.style.background='#F3F4F6'" onmouseout="this.style.background='transparent'">
          <input type="checkbox" name="pedidos_ids[]" value="${p.id}">
          <div>
            <span style="font-weight:700;font-size:.875rem;color:#111827">${p.folio}</span>
            <span style="font-size:.75rem;color:#6B7280;margin-left:8px">${p.comprador_nombre} · $${parseFloat(p.total).toFixed(2)}</span>
          </div>
        </label>
      `).join('');
    })
    .catch(() => {
      cont.innerHTML = '<p style="color:#991B1B;font-size:.875rem;margin:0">Error al cargar pedidos.</p>';
    });
}
</script>
