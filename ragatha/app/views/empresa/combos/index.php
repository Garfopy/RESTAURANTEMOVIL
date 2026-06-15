<?php // Vista: Lista de combos ?>

<?php if ($flash): ?>
<div style="margin-bottom:16px;padding:12px 16px;border-radius:8px;font-size:.875rem;font-weight:500;
  <?= $flash['type']==='success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= $flash['message'] ?>
</div>
<?php endif; ?>

<!-- Info -->
<div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.8rem;color:#1E40AF;display:flex;align-items:flex-start;gap:10px">
  <span style="font-size:1rem;flex-shrink:0">💡</span>
  <div>
    <strong>Combos por comprador:</strong> Crea conjuntos de productos predefinidos y asígnalos a compradores específicos.
    El comprador verá el combo en su carrito y podrá cargarlo con un clic.
  </div>
</div>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
  <div style="font-size:.9rem;color:#6B7280"><?= count($combos) ?> combo(s) registrado(s)</div>
  <a href="<?= BASE_URL ?>empresa-combo/nuevo"
     style="padding:8px 18px;background:var(--color-primary);color:#fff;border-radius:6px;font-weight:700;text-decoration:none;font-size:.875rem">
    + Nuevo combo
  </a>
</div>

<?php if (empty($combos)): ?>
<div style="background:#fff;border-radius:12px;padding:48px;text-align:center;border:1px solid #E5E7EB;color:#6B7280">
  <p style="font-size:1.1rem;font-weight:600">Sin combos</p>
  <p style="font-size:.875rem;margin-top:4px">Crea el primer combo y asígnalo a un comprador.</p>
  <a href="<?= BASE_URL ?>empresa-combo/nuevo"
     style="display:inline-block;margin-top:16px;padding:10px 20px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600">
    + Nuevo combo
  </a>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:10px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse">
    <thead>
      <tr style="background:#F9FAFB;border-bottom:1px solid #E5E7EB">
        <th style="padding:12px 16px;text-align:left;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Nombre</th>
        <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Productos</th>
        <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Compradores asignados</th>
        <th style="padding:12px 16px;text-align:center;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Estado</th>
        <th style="padding:12px 16px;text-align:right;font-size:.7rem;color:#6B7280;font-weight:700;text-transform:uppercase">Acciones</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($combos as $c): ?>
      <tr style="border-bottom:1px solid #F3F4F6;<?= !$c['activo'] ? 'opacity:.65;background:#FAFAFA' : '' ?>">
        <td style="padding:12px 16px">
          <div style="font-weight:600;color:#111827;font-size:.875rem"><?= htmlspecialchars($c['nombre']) ?></div>
          <?php if ($c['descripcion']): ?>
          <div style="font-size:.75rem;color:#9CA3AF"><?= htmlspecialchars($c['descripcion']) ?></div>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <span style="padding:3px 10px;border-radius:999px;background:#EDE9FE;color:#5B21B6;font-size:.75rem;font-weight:700">
            <?= $c['total_items'] ?> producto(s)
          </span>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <?php if ($c['total_compradores'] > 0): ?>
          <span style="padding:3px 10px;border-radius:999px;background:#D1FAE5;color:#065F46;font-size:.75rem;font-weight:700">
            <?= $c['total_compradores'] ?> comprador(es)
          </span>
          <?php else: ?>
          <span style="font-size:.75rem;color:#9CA3AF">Sin asignar</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;text-align:center">
          <?php if ($c['activo']): ?>
          <span style="padding:3px 10px;border-radius:999px;background:#D1FAE5;color:#065F46;font-size:.72rem;font-weight:700">Activo</span>
          <?php else: ?>
          <span style="padding:3px 10px;border-radius:999px;background:#F3F4F6;color:#9CA3AF;font-size:.72rem;font-weight:700">Inactivo</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px 16px;text-align:right;white-space:nowrap">
          <a href="<?= BASE_URL ?>empresa-combo/editar/<?= $c['id'] ?>"
             style="font-size:.8rem;color:var(--color-primary);text-decoration:none;font-weight:600;margin-right:10px">Editar</a>
          <?php if ($c['activo']): ?>
          <a href="<?= BASE_URL ?>empresa-combo/eliminar/<?= $c['id'] ?>"
             onclick="return confirm('¿Desactivar este combo?')"
             style="font-size:.8rem;color:#D97706;text-decoration:none;font-weight:600">Desactivar</a>
          <?php else: ?>
          <a href="<?= BASE_URL ?>empresa-combo/activar/<?= $c['id'] ?>"
             style="font-size:.8rem;color:#059669;text-decoration:none;font-weight:600">Activar</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
