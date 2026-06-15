<?php
// Variables: $dias (array), $dirs (array), $totalSize, $totalAPurgar, $historial, $empresas, $flash
$fmtSize = function(int $b): string {
    if ($b >= 1073741824) return round($b/1073741824,1).' GB';
    if ($b >= 1048576)    return round($b/1048576,1).' MB';
    if ($b >= 1024)       return round($b/1024,1).' KB';
    return $b.' B';
};
?>

<!-- Cabecera -->
<div style="background:linear-gradient(135deg,#1E3A5F 0%,#C8102E 100%);color:#fff;border-radius:14px;padding:22px 26px;margin-bottom:22px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px">
  <div>
    <h2 style="margin:0 0 6px;font-size:1.1rem;font-weight:800">Políticas de retención de archivos</h2>
    <p style="margin:0;font-size:.85rem;opacity:.9">Configura cuántos días se conservan las imágenes en disco. Los registros de pedidos y evidencias <strong>nunca se borran</strong> — solo las fotos.</p>
  </div>
  <div style="text-align:right;white-space:nowrap">
    <div style="font-size:1.6rem;font-weight:800"><?= $fmtSize($totalSize ?? 0) ?></div>
    <div style="font-size:.75rem;opacity:.8">en disco ahora</div>
  </div>
</div>

<!-- Flash -->
<?php if (!empty($flash)): ?>
<div style="padding:12px 18px;border-radius:10px;margin-bottom:18px;font-size:.875rem;font-weight:600;
  <?= $flash['type']==='success' ? 'background:#D1FAE5;color:#065F46;border:1px solid #A7F3D0' : 'background:#FEE2E2;color:#991B1B;border:1px solid #FECACA' ?>">
  <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- KPIs -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px">
  <div style="background:#EFF6FF;border-radius:12px;padding:16px 20px">
    <div style="font-size:.72rem;font-weight:600;color:#1E40AF;margin-bottom:6px;text-transform:uppercase">Espacio total en disco</div>
    <div style="font-size:1.7rem;font-weight:800;color:#1E40AF"><?= $fmtSize($totalSize ?? 0) ?></div>
  </div>
  <div style="background:<?= ($totalAPurgar??0)>0?'#FFF7ED':'#F0FDF4' ?>;border-radius:12px;padding:16px 20px">
    <div style="font-size:.72rem;font-weight:600;color:<?= ($totalAPurgar??0)>0?'#9A3412':'#166534' ?>;margin-bottom:6px;text-transform:uppercase">Imágenes a purgar</div>
    <div style="font-size:1.7rem;font-weight:800;color:<?= ($totalAPurgar??0)>0?'#9A3412':'#166534' ?>"><?= number_format($totalAPurgar??0) ?></div>
  </div>
  <div style="background:#F5F3FF;border-radius:12px;padding:16px 20px">
    <div style="font-size:.72rem;font-weight:600;color:#5B21B6;margin-bottom:6px;text-transform:uppercase">Directorios monitoreados</div>
    <div style="font-size:1.7rem;font-weight:800;color:#5B21B6"><?= count(self::MANAGED_DIRS) ?></div>
  </div>
</div>

<!-- ── SECCIÓN 1: Configurar retención ── -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;margin-bottom:20px">
  <div style="padding:16px 20px;background:#F9FAFB;border-bottom:1px solid #E5E7EB">
    <h3 style="margin:0;font-size:.95rem;font-weight:700;color:#111827">Configurar tiempos de retención</h3>
    <p style="margin:4px 0 0;font-size:.8rem;color:#6B7280">Los archivos físicos más antiguos que estos días serán candidatos a purga. Los datos en BD nunca se eliminan.</p>
  </div>
  <form method="POST" action="<?= BASE_URL ?>admin-storage/guardar" style="padding:20px">
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
      <div>
        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:6px">
          Fotos de evidencias y firmas
          <span style="font-weight:400;color:#6B7280">(entregas + firmas)</span>
        </label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="number" name="retencion_fotos_evidencias_dias"
                 value="<?= htmlspecialchars((string)($dias['evidencias'] ?? 90)) ?>"
                 min="1" max="3650"
                 style="width:90px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.9rem;font-weight:700;color:#111827;text-align:center">
          <span style="font-size:.82rem;color:#6B7280">días</span>
        </div>
      </div>
      <div>
        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:6px">
          Fotos de pedidos y comprobantes
        </label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="number" name="retencion_fotos_pedidos_dias"
                 value="<?= htmlspecialchars((string)($dias['pedidos'] ?? 90)) ?>"
                 min="1" max="3650"
                 style="width:90px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.9rem;font-weight:700;color:#111827;text-align:center">
          <span style="font-size:.82rem;color:#6B7280">días</span>
        </div>
      </div>
      <div>
        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:6px">
          Registros de auditoría (logs)
        </label>
        <div style="display:flex;align-items:center;gap:8px">
          <input type="number" name="retencion_logs_dias"
                 value="<?= htmlspecialchars((string)($dias['logs'] ?? 365)) ?>"
                 min="30" max="3650"
                 style="width:90px;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.9rem;font-weight:700;color:#111827;text-align:center">
          <span style="font-size:.82rem;color:#6B7280">días</span>
        </div>
      </div>
    </div>
    <button type="submit"
            style="padding:9px 24px;background:#1E3A5F;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.875rem;cursor:pointer"
            onmouseenter="this.style.opacity='.85'" onmouseleave="this.style.opacity='1'">
      Guardar configuración
    </button>
  </form>
</div>

<!-- ── SECCIÓN 2: Estado por directorio ── -->
<?php foreach ($dirs as $slug => $info):
  $pctOld   = $info['count'] > 0 ? ($info['old_count'] / $info['count']) * 100 : 0;
  $barColor = $pctOld >= 50 ? '#EF4444' : ($pctOld >= 20 ? '#F59E0B' : '#10B981');
?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;margin-bottom:14px;overflow:hidden">
  <div style="padding:14px 20px;background:#F9FAFB;border-bottom:1px solid #E5E7EB;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div>
      <span style="font-weight:800;font-size:.95rem;color:#111827"><?= htmlspecialchars($info['label']) ?></span>
      <span style="margin-left:8px;font-size:.75rem;color:#6B7280;background:#E5E7EB;padding:2px 8px;border-radius:999px"><?= $slug ?>/</span>
    </div>
    <div style="display:flex;gap:20px;font-size:.82rem;color:#374151">
      <span>Total: <strong><?= $info['label_size'] ?></strong></span>
      <span>Archivos: <strong><?= number_format($info['count']) ?></strong></span>
      <span style="color:<?= $info['old_count'] > 0 ? '#B45309' : '#059669' ?>">
        &gt;<?= $info['dias'] ?>d: <strong><?= number_format($info['old_count']) ?></strong>
      </span>
    </div>
  </div>
  <div style="padding:14px 20px">
    <div style="display:flex;justify-content:space-between;font-size:.72rem;color:#6B7280;margin-bottom:5px">
      <span>Imágenes fuera de retención</span><span><?= round($pctOld) ?>%</span>
    </div>
    <div style="height:8px;background:#E5E7EB;border-radius:999px;overflow:hidden;margin-bottom:12px">
      <div style="height:100%;border-radius:999px;background:<?= $barColor ?>;width:<?= min(100,$pctOld) ?>%;transition:width .4s"></div>
    </div>
    <?php if ($info['oldest'] || $info['newest']): ?>
    <div style="font-size:.78rem;color:#6B7280;margin-bottom:10px">
      <?php if ($info['oldest']): ?>Más antigua: <strong><?= date('d/m/Y', strtotime($info['oldest'])) ?></strong><?php endif; ?>
      <?php if ($info['oldest'] && $info['newest']): ?> &nbsp;·&nbsp; <?php endif; ?>
      <?php if ($info['newest']): ?>Más reciente: <strong><?= date('d/m/Y', strtotime($info['newest'])) ?></strong><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($info['oldest10'])): ?>
    <details style="border:1px solid #E5E7EB;border-radius:8px;overflow:hidden">
      <summary style="padding:8px 14px;font-size:.8rem;font-weight:600;color:#374151;cursor:pointer;background:#F9FAFB;user-select:none">
        Ver <?= count($info['oldest10']) ?> archivos más antiguos
      </summary>
      <table style="width:100%;border-collapse:collapse;font-size:.78rem">
        <thead><tr style="background:#F3F4F6">
          <th style="padding:6px 12px;text-align:left;color:#6B7280">Archivo</th>
          <th style="padding:6px 12px;text-align:right;color:#6B7280">Tamaño</th>
          <th style="padding:6px 12px;text-align:left;color:#6B7280">Fecha</th>
        </tr></thead>
        <tbody>
          <?php foreach ($info['oldest10'] as $f): ?>
          <tr style="border-top:1px solid #F3F4F6">
            <td style="padding:6px 12px;color:#374151;font-family:monospace"><?= htmlspecialchars($f['name']) ?></td>
            <td style="padding:6px 12px;text-align:right;color:#6B7280"><?= $fmtSize($f['size']) ?></td>
            <td style="padding:6px 12px;color:#6B7280"><?= date('d/m/Y H:i', $f['mtime']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </details>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>

<!-- ── SECCIÓN 3: Exportar historial CSV ── -->
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden;margin-bottom:20px">
  <div style="padding:16px 20px;background:#F9FAFB;border-bottom:1px solid #E5E7EB">
    <h3 style="margin:0;font-size:.95rem;font-weight:700;color:#111827">Exportar historial de pedidos (CSV)</h3>
    <p style="margin:4px 0 0;font-size:.8rem;color:#6B7280">Descarga el historial completo de pedidos por rango de fechas. Los datos se conservan aunque las imágenes hayan sido purgadas.</p>
  </div>
  <form method="POST" action="<?= BASE_URL ?>admin-storage/exportarCsv" style="padding:20px">
    <div style="display:grid;grid-template-columns:2fr 1fr 1fr auto;gap:14px;align-items:end;flex-wrap:wrap">
      <div>
        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:5px">Empresa (opcional)</label>
        <select name="empresa_id" style="width:100%;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#111827;background:#fff">
          <option value="0">Todas las empresas</option>
          <?php foreach ($empresas as $emp): ?>
          <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:5px">Desde</label>
        <input type="date" name="fecha_desde" value="<?= date('Y-01-01') ?>"
               style="width:100%;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#111827;box-sizing:border-box">
      </div>
      <div>
        <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:5px">Hasta</label>
        <input type="date" name="fecha_hasta" value="<?= date('Y-m-d') ?>"
               style="width:100%;padding:9px 12px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.875rem;color:#111827;box-sizing:border-box">
      </div>
      <div>
        <button type="submit"
                style="padding:9px 20px;background:#059669;color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.875rem;cursor:pointer;white-space:nowrap"
                onmouseenter="this.style.opacity='.85'" onmouseleave="this.style.opacity='1'">
          Descargar CSV
        </button>
      </div>
    </div>
  </form>
</div>

<!-- ── SECCIÓN 4: Purga inteligente ── -->
<details style="border:2px solid #FEE2E2;border-radius:14px;overflow:hidden;margin-bottom:20px">
  <summary style="padding:14px 20px;background:#FEF2F2;font-size:.875rem;font-weight:700;color:#991B1B;cursor:pointer;user-select:none;display:flex;align-items:center;gap:8px;list-style:none">
    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
    Ejecutar purga de imágenes — acción irreversible en disco
  </summary>
  <div style="padding:20px">
    <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:8px;padding:14px;margin-bottom:16px;font-size:.83rem;color:#7F1D1D;line-height:1.6">
      <strong>¿Qué hace la purga?</strong> Elimina del disco las fotos de evidencias y pedidos que superan el tiempo de retención configurado.<br>
      Los registros en la base de datos se <strong>conservan intactos</strong> — solo se libera espacio en disco.<br>
      Requiere haber ejecutado la migración <code>019_retencion_politicas.sql</code>.
    </div>
    <form method="POST" action="<?= BASE_URL ?>admin-storage/purgar" id="formPurgar">
      <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <div>
          <label style="font-size:.78rem;font-weight:600;color:#374151;display:block;margin-bottom:5px">
            Escribe <strong>PURGAR</strong> para confirmar
          </label>
          <input type="text" name="confirmacion" id="inputPurgar" placeholder="PURGAR"
                 oninput="checkPurgar()"
                 style="padding:9px 14px;border:1.5px solid #FECACA;border-radius:8px;font-size:.9rem;font-weight:700;color:#991B1B;letter-spacing:.06em;outline:none;width:140px">
        </div>
        <button type="submit" id="btnPurgar" disabled
                style="margin-top:20px;padding:10px 24px;background:#EF4444;color:#fff;border:none;border-radius:9px;font-weight:700;font-size:.875rem;cursor:not-allowed;opacity:.4;transition:all .2s"
                onmouseenter="if(!this.disabled)this.style.opacity='.85'" onmouseleave="if(!this.disabled)this.style.opacity='1'">
          Purgar imágenes ahora
        </button>
      </div>
    </form>
  </div>
</details>

<!-- ── Historial ── -->
<?php if (!empty($historial)): ?>
<div style="background:#fff;border:1px solid #E5E7EB;border-radius:14px;overflow:hidden">
  <div style="padding:14px 20px;background:#F9FAFB;border-bottom:1px solid #E5E7EB">
    <h3 style="margin:0;font-size:.875rem;font-weight:700;color:#111827">Historial de acciones</h3>
  </div>
  <table style="width:100%;border-collapse:collapse;font-size:.82rem">
    <thead>
      <tr style="background:#F9FAFB">
        <th style="padding:8px 16px;text-align:left;color:#6B7280;font-weight:600">Acción</th>
        <th style="padding:8px;text-align:left;color:#6B7280;font-weight:600">Descripción</th>
        <th style="padding:8px;text-align:left;color:#6B7280;font-weight:600">Usuario</th>
        <th style="padding:8px;text-align:left;color:#6B7280;font-weight:600">Fecha</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($historial as $h): ?>
      <tr style="border-top:1px solid #F3F4F6">
        <td style="padding:8px 16px;font-weight:600;color:#111827"><?= htmlspecialchars($h['accion']) ?></td>
        <td style="padding:8px;color:#374151;max-width:320px"><?= htmlspecialchars($h['descripcion'] ?? '') ?></td>
        <td style="padding:8px;color:#374151"><?= htmlspecialchars($h['nombre'] ?? '—') ?></td>
        <td style="padding:8px;color:#6B7280"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
function checkPurgar() {
  var val = document.getElementById('inputPurgar').value;
  var btn = document.getElementById('btnPurgar');
  var ok  = val === 'PURGAR';
  btn.disabled      = !ok;
  btn.style.opacity = ok ? '1' : '.4';
  btn.style.cursor  = ok ? 'pointer' : 'not-allowed';
}
</script>
