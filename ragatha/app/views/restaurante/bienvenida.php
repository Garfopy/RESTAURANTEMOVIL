<?php ob_start(); ?>
<div style="max-width:820px;margin:0 auto">

  <!-- Hero éxito -->
  <div class="rst-card" style="text-align:center;padding:36px 24px;
       background:linear-gradient(135deg,#ECFDF5 0%,#FFFFFF 100%);border:1px solid #BBF7D0">
    <div style="font-size:3rem;margin-bottom:8px">🎉</div>
    <div style="font-size:1.5rem;font-weight:800;color:#065F46;margin-bottom:4px">
      ¡Tu restaurante está listo!
    </div>
    <div style="color:#047857;font-size:.95rem">
      <strong><?= htmlspecialchars($restaurante['nombre']) ?></strong> ya forma parte de CarniHub.
    </div>
  </div>

  <!-- Link de acceso del staff -->
  <div class="rst-card">
    <div style="font-weight:700;font-size:1rem;color:#111827;margin-bottom:6px">
      🔑 Link de acceso para tu equipo
    </div>
    <div style="font-size:.85rem;color:#6B7280;margin-bottom:16px;line-height:1.5">
      Comparte este link con tus meseros, chef y portero. Todos inician sesión
      en el portal centralizado de tu restaurante usando su correo y contraseña.
    </div>

    <div style="display:flex;gap:8px;align-items:stretch;margin-bottom:14px;flex-wrap:wrap">
      <input type="text" id="linkStaff" readonly value="<?= htmlspecialchars($linkStaff) ?>"
             style="flex:1;min-width:240px;padding:12px 14px;border:2px solid #E5E7EB;
                    border-radius:10px;font-family:monospace;font-size:.85rem;background:#F9FAFB">
      <button type="button" onclick="copiar('linkStaff', this)"
              class="btn btn-primary" style="padding:0 18px">
        Copiar
      </button>
      <a href="<?= htmlspecialchars($linkStaff) ?>" target="_blank"
         class="btn btn-outline" style="padding:0 18px;display:inline-flex;align-items:center">
        Abrir ↗
      </a>
    </div>

    <div style="display:grid;grid-template-columns:160px 1fr;gap:20px;align-items:center;
                background:#F9FAFB;border-radius:12px;padding:16px">
      <div id="qrStaff" style="background:#fff;padding:8px;border-radius:8px"></div>
      <div>
        <div style="font-weight:600;color:#111827;margin-bottom:4px">QR del portal staff</div>
        <div style="font-size:.82rem;color:#6B7280;line-height:1.5">
          Imprime este QR y pégalo en la cocina o área de personal.
          Cualquier integrante lo escanea desde su celular para entrar al portal.
        </div>
      </div>
    </div>
  </div>

  <!-- Próximos pasos -->
  <div class="rst-card">
    <div style="font-weight:700;font-size:1rem;color:#111827;margin-bottom:14px">
      🚀 Próximos pasos
    </div>
    <div style="display:grid;gap:10px">
      <a href="<?= BASE_URL ?>rest-staff/index" class="rst-step">
        <div class="rst-step-num">1</div>
        <div>
          <div style="font-weight:600;color:#111827">Crea a tu equipo</div>
          <div style="font-size:.82rem;color:#6B7280">Mesero, chef y portero — cada uno con su correo y contraseña.</div>
        </div>
        <div style="color:#9CA3AF">→</div>
      </a>
      <a href="<?= BASE_URL ?>rest-mesa/index" class="rst-step">
        <div class="rst-step-num">2</div>
        <div>
          <div style="font-weight:600;color:#111827">Configura tus mesas</div>
          <div style="font-size:.82rem;color:#6B7280">Genera el QR de cada mesa para que el comensal ordene desde su celular.</div>
        </div>
        <div style="color:#9CA3AF">→</div>
      </a>
      <a href="<?= BASE_URL ?>rest-menu/index" class="rst-step">
        <div class="rst-step-num">3</div>
        <div>
          <div style="font-weight:600;color:#111827">Carga tu menú</div>
          <div style="font-size:.82rem;color:#6B7280">Platillos, precios, fotos y recetas con gramaje para descontar inventario automático.</div>
        </div>
        <div style="color:#9CA3AF">→</div>
      </a>
      <a href="<?= BASE_URL ?>rest-config/index" class="rst-step">
        <div class="rst-step-num">4</div>
        <div>
          <div style="font-weight:600;color:#111827">Personaliza tu marca</div>
          <div style="font-size:.82rem;color:#6B7280">Logo, colores y modos de operación (mesas, reservas, portero, propinas).</div>
        </div>
        <div style="color:#9CA3AF">→</div>
      </a>
    </div>

    <div style="margin-top:18px;text-align:right">
      <a href="<?= BASE_URL ?>restaurante/dashboard" class="btn btn-primary">
        Ir al dashboard →
      </a>
    </div>
  </div>

  <!-- Link público del menú -->
  <div class="rst-card" style="background:#FFFBEB;border:1px solid #FDE68A">
    <div style="font-weight:700;color:#92400E;margin-bottom:6px">
      📱 Link público del menú
    </div>
    <div style="font-size:.85rem;color:#78350F;margin-bottom:10px;line-height:1.5">
      Este es el link que ven tus clientes al escanear el QR de la mesa.
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <input type="text" id="linkMenu" readonly value="<?= htmlspecialchars($linkMenu) ?>"
             style="flex:1;min-width:240px;padding:10px 12px;border:1px solid #FDE68A;
                    border-radius:8px;font-family:monospace;font-size:.82rem;background:#fff">
      <button type="button" onclick="copiar('linkMenu', this)"
              class="btn btn-outline btn-sm">Copiar</button>
    </div>
  </div>
</div>

<style>
  .rst-step{display:grid;grid-template-columns:36px 1fr 20px;gap:12px;align-items:center;
            padding:12px;border:1px solid #E5E7EB;border-radius:10px;background:#fff;
            text-decoration:none;transition:.15s}
  .rst-step:hover{border-color:var(--cp);background:#FAFAFA;transform:translateY(-1px)}
  .rst-step-num{width:36px;height:36px;border-radius:50%;background:var(--cp);color:#fff;
                display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.9rem}
</style>

<script>
function copiar(id, btn) {
  const el = document.getElementById(id);
  el.select(); el.setSelectionRange(0, 99999);
  navigator.clipboard.writeText(el.value).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ Copiado';
    setTimeout(() => { btn.textContent = orig; }, 1500);
  });
}

(function() {
  const s = document.createElement('script');
  s.src = 'https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js';
  s.onload = function() {
    new QRCode(document.getElementById('qrStaff'), {
      text: '<?= addslashes($linkStaff) ?>',
      width: 144, height: 144,
      colorDark: '<?= addslashes($restaurante['color_secundario'] ?? '#1f2937') ?>',
      colorLight: '#ffffff',
    });
  };
  document.head.appendChild(s);
})();
</script>
<?php
$content = ob_get_clean();
require ROOT_PATH . '/app/views/restaurante/layouts/main.php';
