<?php
// Vista: Listado de usuarios de la empresa
?>
<?php
$usuariosActivos   = array_filter($usuarios, fn($u) => $u['activo']);
$usuariosInactivos = array_filter($usuarios, fn($u) => !$u['activo']);
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <p style="color:#6B7280;font-size:.875rem">
    <?= count($usuariosActivos) ?> activo(s)<?= count($usuariosInactivos) ? ', ' . count($usuariosInactivos) . ' inactivo(s)' : '' ?> en tu empresa
  </p>
  <a href="<?= BASE_URL ?>empresa-usuario/nuevo"
     style="padding:9px 18px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
    + Agregar usuario
  </a>
</div>

<?php if (empty($usuarios)): ?>
<div style="background:#fff;border-radius:12px;padding:40px;text-align:center;border:1px solid #E5E7EB">
  <p style="color:#6B7280">Aún no has agregado usuarios. Crea supervisores, compradores y repartidores para tu empresa.</p>
  <a href="<?= BASE_URL ?>empresa-usuario/nuevo" style="display:inline-block;margin-top:16px;padding:10px 24px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600">Agregar primer usuario</a>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB">
        <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600">Usuario</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Rol</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Teléfono</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Estado</th>
        <th style="padding:12px"></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($usuarios as $u): ?>
      <tr style="border-top:1px solid #F3F4F6<?= $u['activo'] ? '' : ';opacity:.55' ?>" id="fila-usuario-<?= $u['id'] ?>">
        <td style="padding:12px 16px">
          <div style="font-weight:600;color:#111827"><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido_paterno']) ?></div>
          <div style="font-size:.8rem;color:#6B7280"><?= htmlspecialchars($u['email']) ?></div>
        </td>
        <td style="padding:12px">
          <?php
          $rolColors = [
            'admin_empresa' => ['#EFF6FF','#1E40AF'],
            'supervisor'    => ['#F0FDF4','#166534'],
            'comprador'     => ['#FFF7ED','#9A3412'],
            'repartidor'    => ['#F5F3FF','#5B21B6'],
          ];
          $rc = $rolColors[$u['rol_slug']] ?? ['#F3F4F6','#374151'];
          echo "<span style='background:{$rc[0]};color:{$rc[1]};padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600'>" . htmlspecialchars($u['rol_nombre']) . "</span>";
          ?>
        </td>
        <td style="padding:12px;color:#374151"><?= htmlspecialchars($u['telefono'] ?? '—') ?></td>
        <td style="padding:12px">
          <?php if ($u['activo']): ?>
            <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Activo</span>
          <?php else: ?>
            <span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Inactivo</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px;white-space:nowrap">
          <?php if ($u['activo']): ?>
          <a href="<?= BASE_URL ?>empresa-usuario/editar/<?= $u['id'] ?>"
             style="font-size:.8rem;color:#6B7280;text-decoration:none;margin-right:10px">Editar</a>
          <?php if ($u['rol_slug'] === 'comprador'): ?>
          <a href="<?= BASE_URL ?>empresa-usuario/precios/<?= $u['id'] ?>"
             style="font-size:.8rem;color:#7C3AED;text-decoration:none;margin-right:10px">Precios especiales</a>
          <?php endif; ?>
          <?php endif; ?>
          <button onclick="toggleUsuario(<?= $u['id'] ?>, this)"
                  style="font-size:.8rem;color:<?= $u['activo'] ? '#991B1B' : '#065F46' ?>;background:none;border:none;cursor:pointer;font-family:inherit"
                  data-activo="<?= $u['activo'] ? '1' : '0' ?>">
            <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
          </button>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
function toggleUsuario(id, btn) {
  fetch('<?= BASE_URL ?>empresa-usuario/toggleActivo/' + id, { method: 'POST' })
    .then(r => r.json())
    .then(d => {
      if (!d.ok) return;
      const activo = d.activo;
      const fila = document.getElementById('fila-usuario-' + id);
      if (fila) fila.style.opacity = activo ? '' : '0.55';
      btn.style.color = activo ? '#991B1B' : '#065F46';
      btn.textContent  = activo ? 'Desactivar' : 'Activar';
      btn.dataset.activo = activo ? '1' : '0';
      // Mostrar/ocultar acciones según estado
      const acciones = fila ? fila.querySelectorAll('a') : [];
      acciones.forEach(a => { a.style.display = activo ? '' : 'none'; });
    });
}
</script>
