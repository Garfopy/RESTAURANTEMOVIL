<?php ob_start(); ?>
<div style="max-width:680px;margin:0 auto">

  <!-- QR principal del restaurante -->
  <div class="rst-card" style="text-align:center;padding:36px 28px;margin-bottom:20px">
    <div style="font-size:.75rem;color:#9CA3AF;font-weight:700;letter-spacing:.08em;
                text-transform:uppercase;margin-bottom:12px">Tu menú público</div>

    <div id="qrPrincipal" style="display:inline-block;padding:16px;background:#fff;
         border-radius:16px;border:2px solid #E5E7EB;margin-bottom:16px"></div>

    <div style="font-size:1.2rem;font-weight:800;color:#111827;margin-bottom:4px">
      <?= htmlspecialchars($restaurante['nombre']) ?>
    </div>
    <div style="font-family:monospace;font-size:.85rem;color:var(--cp);margin-bottom:16px">
      <?= BASE_URL ?>menu/<?= htmlspecialchars($restaurante['slug'] ?? '') ?>
    </div>

    <div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
      <button onclick="downloadQR()" class="btn btn-primary">
        <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        Descargar QR
      </button>
      <a href="<?= BASE_URL ?>menu/<?= htmlspecialchars($restaurante['slug'] ?? '') ?>"
         target="_blank" class="btn btn-outline">
        Ver menú ↗
      </a>
    </div>
  </div>

  <!-- Instrucciones de uso -->
  <div class="rst-card">
    <div style="font-weight:700;font-size:.95rem;margin-bottom:16px;display:flex;align-items:center;gap:8px">
      <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Cómo usar los QR
    </div>
    <div style="display:grid;gap:12px">
      <?php
      $pasos = [
        ['num'=>'1', 'titulo'=>'QR del local (este)',
         'desc'=>'Colócalo en la entrada, mesas o mostrador. Los clientes lo escanean para ver el menú completo.'],
        ['num'=>'2', 'titulo'=>'QR por mesa individual',
         'desc'=>'Ve a Mesas → cada mesa tiene su propio QR. Al escanear, el pedido queda vinculado a esa mesa.'],
        ['num'=>'3', 'titulo'=>'QR de visita (portero)',
         'desc'=>'El portero genera el QR al registrar la entrada. Al salir, escanea para verificar que el ticket esté pagado.'],
        ['num'=>'4', 'titulo'=>'Login de staff',
         'desc'=>'Tu equipo entra en ' . BASE_URL . 'acceso/' . ($restaurante['slug'] ?? '') . ' con su correo y contraseña.'],
      ];
      foreach ($pasos as $paso):
      ?>
      <div style="display:flex;gap:14px;align-items:flex-start">
        <div style="width:28px;height:28px;border-radius:50%;background:var(--cp-light);color:var(--cp);
                    display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.82rem;flex-shrink:0">
          <?= $paso['num'] ?>
        </div>
        <div>
          <div style="font-weight:600;font-size:.875rem;margin-bottom:2px"><?= $paso['titulo'] ?></div>
          <div style="font-size:.82rem;color:#6B7280;line-height:1.4"><?= $paso['desc'] ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- URL de acceso staff -->
  <div style="background:var(--cp-light);border:1px solid color-mix(in srgb,var(--cp) 20%,white);
              border-radius:12px;padding:16px;margin-top:0">
    <div style="font-weight:700;font-size:.875rem;color:var(--cp);margin-bottom:8px">
      URL de acceso para tu staff
    </div>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
      <code style="flex:1;background:#fff;border-radius:8px;padding:8px 12px;font-size:.85rem;
                   color:#111827;border:1px solid color-mix(in srgb,var(--cp) 20%,white)">
        <?= BASE_URL ?>acceso/<?= htmlspecialchars($restaurante['slug'] ?? '') ?>/staff
      </code>
      <button onclick="copiarURL()" class="btn btn-primary btn-sm" id="btnCopiar">Copiar</button>
    </div>
  </div>
</div>

<script src="https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const menuURL = '<?= addslashes(BASE_URL . 'menu/' . ($restaurante['slug'] ?? '')) ?>';
const qrInst  = new QRCode(document.getElementById('qrPrincipal'), {
  text: menuURL,
  width: 240, height: 240,
  colorDark: '<?= addslashes($restaurante['color_secundario'] ?? '#1f2937') ?>',
  colorLight: '#ffffff',
});

function downloadQR() {
  const canvas = document.querySelector('#qrPrincipal canvas');
  if (!canvas) return alert('Espera a que cargue el QR');
  const link    = document.createElement('a');
  link.download = 'qr-<?= htmlspecialchars($restaurante['slug'] ?? 'menu') ?>.png';
  link.href     = canvas.toDataURL('image/png');
  link.click();
}

function copiarURL() {
  navigator.clipboard.writeText('<?= addslashes(BASE_URL . 'acceso/' . ($restaurante['slug'] ?? '')) ?>')
    .then(() => {
      const btn = document.getElementById('btnCopiar');
      btn.textContent = '¡Copiado!';
      setTimeout(() => btn.textContent = 'Copiar', 2000);
    });
}
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
