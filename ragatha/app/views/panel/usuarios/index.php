<?php
// Variables: $usuarios[], $paginacion, $roles[], $filtros
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <form method="GET" action="<?= BASE_URL ?>panel-usuario/index" style="display:flex;gap:8px;flex-wrap:wrap">
    <input type="text" name="buscar" value="<?= htmlspecialchars($filtros['buscar']) ?>"
           placeholder="Buscar por nombre o email..."
           style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem;outline:none;min-width:220px">
    <select name="rol_slug" style="padding:7px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
      <option value="">Todos los roles</option>
      <?php foreach ($roles as $r): ?>
      <option value="<?= $r['slug'] ?>" <?= $filtros['rol_slug'] === $r['slug'] ? 'selected' : '' ?>>
        <?= htmlspecialchars($r['nombre']) ?>
      </option>
      <?php endforeach; ?>
    </select>
    <button type="submit"
            style="padding:7px 16px;background:#374151;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer">Filtrar</button>
  </form>
  <a href="<?= BASE_URL ?>panel-usuario/nuevo"
     style="padding:8px 18px;background:var(--color-primary);color:#fff;border-radius:8px;font-size:.875rem;text-decoration:none;font-weight:600;white-space:nowrap">
    + Nuevo usuario
  </a>
</div>

<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Usuario</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Rol</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Empresa</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Estado</th>
        <th style="padding:12px 16px;text-align:left;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Creado</th>
        <th style="padding:12px 16px;text-align:center;font-size:.75rem;font-weight:700;color:#6B7280;text-transform:uppercase">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($usuarios)): ?>
      <tr><td colspan="6" style="padding:40px;text-align:center;color:#9CA3AF;font-size:.875rem">No hay usuarios.</td></tr>
      <?php endif; ?>
      <?php foreach ($usuarios as $u): ?>
      <tr style="border-bottom:1px solid #F3F4F6" onmouseover="this.style.background='#F9FAFB'" onmouseout="this.style.background='#fff'">
        <td style="padding:12px 16px">
          <div style="font-weight:600;font-size:.875rem;color:#111827">
            <?= htmlspecialchars($u['nombre'] . ' ' . ($u['apellido_paterno'] ?? '')) ?>
          </div>
          <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($u['email']) ?></div>
        </td>
        <td style="padding:12px 16px">
          <span style="background:#EDE9FE;color:#5B21B6;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">
            <?= htmlspecialchars($u['rol_nombre']) ?>
          </span>
        </td>
        <td style="padding:12px 16px;font-size:.875rem;color:#374151">
          <?= htmlspecialchars($u['empresa_nombre'] ?? '—') ?>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <?php if ($u['activo']): ?>
            <span style="background:#D1FAE5;color:#065F46;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">Activo</span>
          <?php else: ?>
            <span style="background:#F3F4F6;color:#6B7280;padding:3px 10px;border-radius:999px;font-size:.75rem;font-weight:600">Inactivo</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;font-size:.8rem;color:#6B7280"><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
        <td style="padding:12px 16px;text-align:center;white-space:nowrap">
          <a href="<?= BASE_URL ?>panel-usuario/editar/<?= $u['id'] ?>"
             style="color:var(--color-primary);font-size:.8rem;font-weight:600;text-decoration:none;margin-right:10px">Editar</a>
          <?php if ($u['rol_slug'] !== 'superadmin'): ?>
          <a href="<?= BASE_URL ?>panel-usuario/toggle/<?= $u['id'] ?>"
             onclick="return confirm('¿<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> este usuario?')"
             style="color:<?= $u['activo'] ? '#6B7280' : '#059669' ?>;font-size:.8rem;font-weight:600;text-decoration:none">
            <?= $u['activo'] ? 'Desactivar' : 'Activar' ?>
          </a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php if ($paginacion['last_page'] > 1): ?>
<div style="display:flex;justify-content:center;gap:6px;margin-top:20px">
  <?php for ($i = 1; $i <= $paginacion['last_page']; $i++): ?>
    <a href="?page=<?= $i ?>&buscar=<?= urlencode($filtros['buscar']) ?>&rol_slug=<?= urlencode($filtros['rol_slug']) ?>"
       style="padding:6px 12px;border-radius:6px;font-size:.875rem;text-decoration:none;<?= $i === $paginacion['current_page'] ? 'background:var(--color-primary);color:#fff;font-weight:700' : 'background:#fff;border:1px solid #D1D5DB;color:#374151' ?>">
      <?= $i ?>
    </a>
  <?php endfor; ?>
</div>
<?php endif; ?>
