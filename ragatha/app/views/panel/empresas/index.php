<?php
// Vista: Listado de empresas cliente (panel admin)
?>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px">
  <form method="GET" style="display:flex;gap:8px">
    <input type="text" name="buscar" placeholder="Buscar empresa..." value="<?= htmlspecialchars($filtros['buscar'] ?? '') ?>"
           style="padding:8px 12px;border:1px solid #D1D5DB;border-radius:8px;font-size:.875rem">
    <button type="submit" style="padding:8px 16px;background:#374151;color:#fff;border:none;border-radius:8px;font-size:.875rem;cursor:pointer">Buscar</button>
  </form>
  <a href="<?= BASE_URL ?>panel-empresa/nueva"
     style="padding:9px 18px;background:var(--color-primary);color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.875rem">
    + Nueva empresa
  </a>
</div>

<?php if (empty($empresas)): ?>
<div style="background:#fff;border-radius:12px;padding:40px;text-align:center;border:1px solid #E5E7EB;color:#6B7280">
  No hay empresas registradas. <a href="<?= BASE_URL ?>panel-empresa/nueva" style="color:var(--color-primary);font-weight:600">Crear la primera</a>
</div>
<?php else: ?>
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden">
  <table style="width:100%;border-collapse:collapse;font-size:.875rem">
    <thead>
      <tr style="background:#F9FAFB">
        <th style="padding:12px 16px;text-align:left;color:#6B7280;font-weight:600">Empresa</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">RFC</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Tipo</th>
        <th style="padding:12px;text-align:center;color:#6B7280;font-weight:600">Usuarios</th>
        <th style="padding:12px;text-align:center;color:#6B7280;font-weight:600">Sucursales</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Crédito</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Plan</th>
        <th style="padding:12px;text-align:left;color:#6B7280;font-weight:600">Estado</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($empresas as $e): ?>
      <tr style="border-top:1px solid #F3F4F6">
        <td style="padding:12px 16px">
          <div style="font-weight:600;color:#111827"><?= htmlspecialchars($e['razon_social']) ?></div>
          <div style="font-size:.8rem;color:#6B7280"><?= htmlspecialchars($e['email'] ?? '') ?></div>
        </td>
        <td style="padding:12px;color:#374151;font-family:monospace;font-size:.8rem"><?= htmlspecialchars($e['rfc'] ?? '—') ?></td>
        <td style="padding:12px;color:#374151;text-transform:capitalize"><?= htmlspecialchars($e['tipo_negocio'] ?? '—') ?></td>
        <td style="padding:12px;text-align:center;color:#374151"><?= (int)$e['total_usuarios'] ?></td>
        <td style="padding:12px;text-align:center;color:#374151"><?= (int)$e['total_sucursales'] ?></td>
        <td style="padding:12px">
          <?php if ($e['credito_activo']): ?>
          <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Activo</span>
          <?php else: ?>
          <span style="background:#F3F4F6;color:#6B7280;padding:2px 8px;border-radius:999px;font-size:.75rem">—</span>
          <?php endif; ?>
        </td>
        <td style="padding:12px">
          <?php
          $planSlug   = $e['plan_slug'] ?? '';
          $planNombre = $e['plan_nombre'] ?? '';
          $planColores = ['basico'=>'background:#F3F4F6;color:#374151','pro'=>'background:#DBEAFE;color:#1D4ED8','empresa'=>'background:#EDE9FE;color:#6D28D9'];
          $planEstilo  = $planColores[$planSlug] ?? 'background:#FEE2E2;color:#991B1B';
          echo $planNombre
            ? "<span style=\"{$planEstilo};padding:2px 10px;border-radius:999px;font-size:.75rem;font-weight:600\">{$planNombre}</span>"
            : '<span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:999px;font-size:.75rem">Sin plan</span>';
          ?>
        </td>
        <td style="padding:12px">
          <?php if ($e['activo']): ?>
          <span style="background:#D1FAE5;color:#065F46;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Activa</span>
          <?php else: ?>
          <span style="background:#FEE2E2;color:#991B1B;padding:2px 8px;border-radius:999px;font-size:.75rem;font-weight:600">Inactiva</span>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
