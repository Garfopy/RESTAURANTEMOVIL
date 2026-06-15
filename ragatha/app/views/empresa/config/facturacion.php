<?php
$v = fn(string $k) => htmlspecialchars($cfg[$k] ?? '');
?>

<?php if (!empty($flash['success'])): ?>
<div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#166534;display:flex;align-items:center;gap:8px">
  <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
  <?= htmlspecialchars($flash['success']) ?>
</div>
<?php endif; ?>
<?php if (!empty($flash['error'])): ?>
<div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:12px 16px;margin-bottom:20px;font-size:.84rem;color:#B91C1C">
  <?= htmlspecialchars($flash['error']) ?>
</div>
<?php endif; ?>

<!-- ── Estado de avance ───────────────────────────────────────────── -->
<div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:18px 24px;margin-bottom:20px">
  <p style="font-size:.78rem;font-weight:700;color:#6B7280;text-transform:uppercase;letter-spacing:.05em;margin:0 0 14px">¿Qué falta configurar?</p>
  <div style="display:flex;gap:8px;align-items:stretch">
    <?php $steps = [
      ['key'=>'apikey','label'=>'API Key de FacturAPI','icon'=>'🔑'],
      ['key'=>'datos', 'label'=>'Datos de tu empresa','icon'=>'🏢'],
    ]; ?>
    <?php foreach ($steps as $s): ?>
    <div style="flex:1;text-align:center;padding:10px 8px;border-radius:8px;background:<?= $pasos[$s['key']] ? '#F0FDF4' : '#F9FAFB' ?>">
      <div style="font-size:1.3rem;margin-bottom:4px"><?= $s['icon'] ?></div>
      <div style="font-size:.72rem;font-weight:700;color:<?= $pasos[$s['key']] ? '#166534' : '#9CA3AF' ?>"><?= $s['label'] ?></div>
      <div style="font-size:.68rem;color:<?= $pasos[$s['key']] ? '#166534' : '#9CA3AF' ?>;margin-top:2px"><?= $pasos[$s['key']] ? '✓ Listo' : 'Pendiente' ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if ($pasos['apikey'] && $pasos['datos']): ?>
  <p style="margin:14px 0 0;font-size:.82rem;color:#166534;font-weight:600;text-align:center">
    ¡Todo listo! Ya puedes generar facturas.
    <a href="<?= BASE_URL ?>empresa-factura/index" style="color:#166534;margin-left:6px;text-decoration:underline">Ir a Facturas →</a>
  </p>
  <?php endif; ?>
</div>

<!-- ── Guía de registro ──────────────────────────────────────────── -->
<details style="margin-bottom:20px" <?= (!$pasos['apikey'] || !$pasos['datos']) ? 'open' : '' ?>>
  <summary style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:14px 18px;cursor:pointer;font-size:.875rem;font-weight:700;color:#1D4ED8;list-style:none;display:flex;align-items:center;gap:8px">
    <svg width="17" height="17" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01"/></svg>
    ¿Cómo activar la facturación? — Guía paso a paso
    <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" style="margin-left:auto"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
  </summary>
  <div style="background:#F8FAFF;border:1px solid #BFDBFE;border-top:none;border-radius:0 0 10px 10px;padding:22px 24px;font-size:.84rem;color:#1E3A5F;line-height:1.8">

    <!-- ¿Quién configura esto? -->
    <div style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:10px;padding:14px 18px;margin-bottom:18px;font-size:.82rem;color:#1E3A5F">
      <strong>¿Quién necesita hacer esta configuración?</strong><br>
      Solo tú — el <strong>dueño o administrador de la empresa distribuidora</strong>. Tus compradores no necesitan hacer nada; ellos simplemente verán sus facturas en su portal automáticamente.
    </div>

    <!-- Cómo funciona -->
    <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:14px 18px;margin-bottom:22px;font-size:.82rem;color:#14532D">
      <strong>¿Cómo funciona la facturación en CarniHub?</strong>
      <ol style="margin:8px 0 0;padding-left:18px;line-height:1.9">
        <li>Entregas un pedido a tu comprador</li>
        <li>Vas a <strong>Facturas CFDI</strong> → seleccionas el pedido → haces clic en <strong>"Generar factura"</strong></li>
        <li>El sistema genera y timbra el CFDI ante el SAT en segundos — te genera el XML y el PDF</li>
        <li>Tu comprador lo descarga desde su propio portal — sin que tú tengas que enviar nada</li>
      </ol>
    </div>

    <!-- PARTE A: Antes de empezar — SAT -->
    <p style="font-size:.8rem;font-weight:800;color:#374151;text-transform:uppercase;letter-spacing:.05em;margin:0 0 10px">Antes de empezar — Requisitos del SAT</p>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:22px">

      <div style="background:#FEF9C3;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px">
        <p style="margin:0 0 6px;font-size:.84rem;font-weight:800;color:#713F12">📋 ¿Qué necesitas del SAT?</p>
        <ul style="margin:0;padding-left:16px;font-size:.79rem;color:#713F12;line-height:1.8">
          <li>Tu <strong>RFC activo</strong> dado de alta en el SAT</li>
          <li>Tu <strong>e.firma vigente</strong> (archivos .cer y .key de FIEL)</li>
          <li>Tu <strong>CSD (Certificado de Sello Digital)</strong> — son otros .cer y .key distintos a la e.firma</li>
          <li>Tu RFC debe estar en la <strong>Lista de Contribuyentes Obligados (LCO)</strong> del SAT</li>
        </ul>
      </div>

      <div style="background:#FEF2F2;border:1px solid #FECACA;border-radius:10px;padding:14px 16px">
        <p style="margin:0 0 6px;font-size:.84rem;font-weight:800;color:#991B1B">⚠️ e.firma ≠ CSD — No los confundas</p>
        <p style="margin:0 0 6px;font-size:.79rem;color:#991B1B;line-height:1.7">Son dos pares de archivos diferentes del SAT:</p>
        <ul style="margin:0;padding-left:16px;font-size:.79rem;color:#991B1B;line-height:1.8">
          <li><strong>e.firma (FIEL)</strong> — archivo llamado <code style="font-size:.72rem">Claveprivada.key</code> — sirve para trámites</li>
          <li><strong>CSD</strong> — archivo con nombre largo de números — sirve para <strong>timbrar facturas</strong></li>
        </ul>
        <p style="margin:6px 0 0;font-size:.77rem;color:#991B1B">FacturAPI rechaza la e.firma — solo acepta el CSD.</p>
      </div>

    </div>

    <!-- Cómo obtener el CSD -->
    <div style="background:#F3F4F6;border-radius:10px;padding:14px 16px;margin-bottom:22px;font-size:.8rem;color:#374151">
      <p style="margin:0 0 8px;font-weight:800;color:#111827">📁 ¿Cómo obtengo mi CSD si no lo tengo?</p>
      <ol style="margin:0;padding-left:18px;line-height:2">
        <li>Descarga la app <strong>"Certifica"</strong> del SAT (sat.gob.mx → Descargas)</li>
        <li>Abre Certifica → selecciona <strong>"Solicitud de Certificado de Sello Digital (CSD)"</strong> → pon una contraseña → se generan <strong>dos archivos: .sdg y .key</strong></li>
        <li>Entra a <strong>CertiSAT WEB</strong> (certisat.sat.gob.mx) con tu e.firma → <strong>"Envío de solicitud"</strong> → adjunta el <strong>.sdg</strong></li>
        <li>En la misma página ve a <strong>"Recuperación de certificados"</strong> → busca por RFC → haz clic en el número de serie → se descarga tu <strong>.cer</strong></li>
        <li>Ahora tienes el par correcto: el <strong>.cer</strong> descargado + el <strong>.key</strong> generado en el paso 2 + la contraseña que pusiste</li>
      </ol>
      <p style="margin:8px 0 0;font-size:.77rem;color:#6B7280">⚠️ El .cer y el .key deben ser de la misma sesión de Certifica — si los mezclas de sesiones diferentes FacturAPI dirá "llave no coincide con certificado".</p>
    </div>

    <!-- PARTE B: FacturAPI -->
    <p style="font-size:.8rem;font-weight:800;color:#374151;text-transform:uppercase;letter-spacing:.05em;margin:0 0 10px">Configuración en FacturAPI (facturapi.io)</p>

    <div style="background:#7C3AED;border-radius:10px;padding:18px 20px;margin-bottom:22px">
      <p style="margin:0 0 14px;font-size:.88rem;font-weight:800;color:#fff">Pasos en el dashboard de FacturAPI para activar la Live Key</p>

      <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:16px">

        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start">
          <span style="background:#FDE68A;color:#713F12;font-weight:800;font-size:.78rem;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:2px">Paso 1</span>
          <div>
            <p style="margin:0 0 3px;color:#fff;font-size:.82rem;font-weight:700">Crea tu cuenta en facturapi.io</p>
            <p style="margin:0;color:#DDD6FE;font-size:.78rem;line-height:1.6">Regístrate con tu correo — el registro es inmediato y gratuito. Al entrar verás el dashboard con el selector <strong style="color:#fff">TEST / LIVE</strong> arriba a la izquierda.</p>
          </div>
        </div>

        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start">
          <span style="background:#FDE68A;color:#713F12;font-weight:800;font-size:.78rem;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:2px">Paso 2</span>
          <div>
            <p style="margin:0 0 3px;color:#fff;font-size:.82rem;font-weight:700">Organización → Certificados → sube tu CSD</p>
            <p style="margin:0;color:#DDD6FE;font-size:.78rem;line-height:1.6">Sube los dos archivos juntos: tu <strong style="color:#fff">.cer</strong> y tu <strong style="color:#fff">.key</strong> del CSD (no de la e.firma). Escribe la contraseña que pusiste en Certifica. El RFC se asigna automáticamente del certificado — no puedes escribirlo a mano.</p>
          </div>
        </div>

        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start">
          <span style="background:#FDE68A;color:#713F12;font-weight:800;font-size:.78rem;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:2px">Paso 3</span>
          <div>
            <p style="margin:0 0 3px;color:#fff;font-size:.82rem;font-weight:700">Organización → Datos fiscales → completa la información</p>
            <p style="margin:0;color:#DDD6FE;font-size:.78rem;line-height:1.6">Llena razón social, código postal y <strong style="color:#fff">régimen fiscal</strong>.<br>
            — Si tu RFC tiene <strong style="color:#fff">4 letras</strong> al inicio (ej. RARD...) → eres <strong style="color:#fff">persona física</strong> → elige "Personas Físicas con Actividades Empresariales" u otro de persona física.<br>
            — Si tu RFC tiene <strong style="color:#fff">3 letras</strong> al inicio (ej. IMD...) → eres <strong style="color:#fff">persona moral</strong> → elige "General de Ley Personas Morales (601)".</p>
          </div>
        </div>

        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start">
          <span style="background:#FDE68A;color:#713F12;font-weight:800;font-size:.78rem;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:2px">Paso 4</span>
          <div>
            <p style="margin:0 0 3px;color:#fff;font-size:.82rem;font-weight:700">Organización → Firma la Carta Manifiesto</p>
            <p style="margin:0;color:#DDD6FE;font-size:.78rem;line-height:1.6">FacturAPI te pide firmar una carta de responsabilidad. Léela y fírmala con tu e.firma electrónica.</p>
          </div>
        </div>

        <div style="background:rgba(255,255,255,.12);border-radius:8px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start">
          <span style="background:#FDE68A;color:#713F12;font-weight:800;font-size:.78rem;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:2px">Paso 5</span>
          <div>
            <p style="margin:0 0 3px;color:#fff;font-size:.82rem;font-weight:700">Activa el servicio de API de facturación (contrata un plan)</p>
            <p style="margin:0;color:#DDD6FE;font-size:.78rem;line-height:1.6">FacturAPI muestra una pantalla con checklist. El último paso es <strong style="color:#fff">"Activa el servicio de API de facturación"</strong> — ahí seleccionas y pagas el plan que necesites.</p>
          </div>
        </div>

        <div style="background:rgba(255,255,255,.15);border-radius:8px;padding:12px 16px;display:flex;gap:12px;align-items:flex-start;border:1px solid rgba(134,239,172,.4)">
          <span style="background:#86EFAC;color:#14532D;font-weight:800;font-size:.78rem;padding:2px 8px;border-radius:999px;flex-shrink:0;margin-top:2px">Paso 6</span>
          <div>
            <p style="margin:0 0 3px;color:#fff;font-size:.82rem;font-weight:700">Integraciones → API Keys → copia tu Live Secret Key</p>
            <p style="margin:0;color:#DDD6FE;font-size:.78rem;line-height:1.6">Una vez completados los 5 pasos anteriores, se desbloquea la <strong style="color:#fff">Live Secret Key</strong> — empieza con <code style="color:#fff;font-size:.73rem">sk_live_</code>.<br>
            Cópiala y pégala en el campo <strong style="color:#fff">"API Key"</strong> de abajo en CarniHub. Eso es todo — ya timbras facturas reales.</p>
          </div>
        </div>

      </div>

      <div style="background:rgba(255,255,255,.1);border-radius:8px;padding:10px 14px;font-size:.78rem;color:#DDD6FE">
        <strong style="color:#fff">Requisito del SAT:</strong> tu RFC debe estar en la <strong style="color:#fff">Lista de Contribuyentes Obligados (LCO)</strong>. Si al intentar timbrar te aparece el error "No se encontró el RFC en la LCO", ve al SAT (sat.gob.mx o presencial) y solicita que te agreguen la obligación de expedir CFDI.
      </div>

      <div style="margin-top:12px">
        <a href="https://www.facturapi.io" target="_blank" rel="noopener"
           style="display:inline-block;padding:9px 18px;background:#fff;color:#7C3AED;border-radius:8px;font-size:.82rem;font-weight:800;text-decoration:none">
          Ir a facturapi.io →
        </a>
      </div>
    </div>

    <!-- Resumen de llaves -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
      <div style="background:#FEF9C3;border:1px solid #FDE68A;border-radius:10px;padding:14px 16px">
        <p style="margin:0 0 6px;font-size:.84rem;font-weight:800;color:#713F12">🧪 Llave de pruebas (sk_test_...)</p>
        <p style="margin:0;font-size:.79rem;color:#713F12;line-height:1.7">Genera facturas de prueba — no tienen validez ante el SAT. Útil para verificar que todo funciona antes de pagar un plan. <strong>No tiene costo.</strong></p>
      </div>
      <div style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:14px 16px">
        <p style="margin:0 0 6px;font-size:.84rem;font-weight:800;color:#14532D">✅ Llave de producción (sk_live_...)</p>
        <p style="margin:0;font-size:.79rem;color:#14532D;line-height:1.7">Genera facturas reales con validez fiscal ante el SAT. Requiere completar los 6 pasos en FacturAPI y tener un plan activo.</p>
      </div>
    </div>

  </div>
</details>

<form method="POST" action="<?= BASE_URL ?>empresa-config/facturacion" enctype="multipart/form-data">

  <!-- ── Paso 1: API Key ───────────────────────────────────────── -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:16px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
      <span style="width:28px;height:28px;background:<?= $pasos['apikey'] ? '#DCFCE7' : '#F3F4F6' ?>;color:<?= $pasos['apikey'] ? '#166534' : '#6B7280' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0">1</span>
      <div>
        <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Tu API Key de FacturAPI</h2>
        <p style="font-size:.77rem;color:#6B7280;margin:2px 0 0">La obtienes en <a href="https://www.facturapi.io" target="_blank" rel="noopener" style="color:#7C3AED;font-weight:600">facturapi.io</a> → Desarrollador → API Keys (registro gratuito e inmediato)</p>
      </div>
    </div>

    <div>
      <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">API Key <span style="color:#EF4444">*</span></label>
      <input type="text" name="facturalo_apikey" value="<?= $v('facturalo_apikey') ?>"
             style="width:100%;padding:10px 14px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.84rem;font-family:monospace;box-sizing:border-box"
             placeholder="sk_test_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" maxlength="64" autocomplete="off" spellcheck="false">
      <p style="font-size:.74rem;color:#9CA3AF;margin:4px 0 0">
        Usa <strong>sk_test_...</strong> para hacer pruebas sin costo &nbsp;|&nbsp; Usa <strong>sk_live_...</strong> para facturas reales
      </p>
    </div>
  </div>

  <!-- ── Paso 2: Datos de la empresa ───────────────────────────── -->
  <div style="background:#fff;border-radius:12px;border:1px solid #E5E7EB;padding:24px;margin-bottom:24px">
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:18px">
      <span style="width:28px;height:28px;background:<?= $pasos['datos'] ? '#DCFCE7' : '#F3F4F6' ?>;color:<?= $pasos['datos'] ? '#166534' : '#6B7280' ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;flex-shrink:0">2</span>
      <div>
        <h2 style="font-size:.9rem;font-weight:700;color:#111827;margin:0">Datos de tu empresa</h2>
        <p style="font-size:.77rem;color:#6B7280;margin:2px 0 0">Los mismos que aparecen en tu constancia de situación fiscal del SAT</p>
      </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">RFC <span style="color:#EF4444">*</span></label>
        <input type="text" name="facturalo_rfc" value="<?= $v('facturalo_rfc') ?>"
               style="width:100%;padding:10px 14px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.84rem;font-family:monospace;box-sizing:border-box;text-transform:uppercase"
               placeholder="Ej. XAXX010101000" maxlength="13" spellcheck="false">
        <p style="font-size:.74rem;color:#9CA3AF;margin:4px 0 0">El RFC de tu empresa o el tuyo (si eres persona física)</p>
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Nombre o razón social <span style="color:#EF4444">*</span></label>
        <input type="text" name="facturalo_nombre" value="<?= $v('facturalo_nombre') ?>"
               style="width:100%;padding:10px 14px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.84rem;box-sizing:border-box"
               placeholder="Ej. MI EMPRESA SA DE CV" maxlength="200">
        <p style="font-size:.74rem;color:#9CA3AF;margin:4px 0 0">Tal como aparece en tu constancia del SAT</p>
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Código postal <span style="color:#EF4444">*</span></label>
        <input type="text" name="facturalo_cp" value="<?= $v('facturalo_cp') ?>"
               style="width:100%;padding:10px 14px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.84rem;box-sizing:border-box"
               placeholder="Ej. 76000" maxlength="10">
        <p style="font-size:.74rem;color:#9CA3AF;margin:4px 0 0">El de tu domicilio fiscal (ciudad donde está tu empresa)</p>
      </div>
      <div>
        <label style="display:block;font-size:.8rem;font-weight:600;color:#374151;margin-bottom:6px">Régimen fiscal</label>
        <select name="facturalo_regimen" style="width:100%;padding:10px 14px;border:1.5px solid #D1D5DB;border-radius:8px;font-size:.84rem;background:#fff;box-sizing:border-box">
          <?php $reg = $cfg['facturalo_regimen'] ?? '601'; ?>
          <option value="601" <?= $reg==='601' ? 'selected':'' ?>>Persona moral (empresa, SA de CV, etc.)</option>
          <option value="612" <?= $reg==='612' ? 'selected':'' ?>>Persona física con actividad empresarial</option>
          <option value="621" <?= $reg==='621' ? 'selected':'' ?>>Incorporación fiscal (antiguo RIF)</option>
          <option value="626" <?= $reg==='626' ? 'selected':'' ?>>Simplificado de confianza (RESICO)</option>
          <option value="625" <?= $reg==='625' ? 'selected':'' ?>>Actividades agrícolas, ganaderas o pesqueras</option>
        </select>
        <p style="font-size:.74rem;color:#9CA3AF;margin:4px 0 0">Lo encuentras en tu constancia de situación fiscal</p>
      </div>
    </div>
  </div>

  <div style="display:flex;gap:12px;align-items:center">
    <button type="submit"
            style="padding:11px 28px;background:var(--color-primary);color:#fff;border:none;border-radius:8px;font-size:.875rem;font-weight:700;cursor:pointer">
      Guardar
    </button>
    <a href="<?= BASE_URL ?>empresa-factura/index"
       style="padding:11px 20px;background:#F3F4F6;color:#374151;border-radius:8px;font-size:.875rem;font-weight:600;text-decoration:none">
      Ver mis facturas
    </a>
  </div>

</form>

<script>
['cerLabel','keyLabel'].forEach(function(id) {
  var el = document.getElementById(id);
  if (!el) return;
  el.addEventListener('dragover', function(e) { e.preventDefault(); el.style.borderColor='var(--color-primary)'; el.style.background='#FFF5F5'; });
  el.addEventListener('dragleave', function() { el.style.borderColor='#D1D5DB'; el.style.background='#FAFAFA'; });
  el.addEventListener('drop', function(e) {
    e.preventDefault(); el.style.borderColor='#D1D5DB'; el.style.background='#FAFAFA';
    var input = el.querySelector('input[type=file]');
    if (input && e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      var nameEl = el.querySelector('span[id]');
      if (nameEl) nameEl.textContent = e.dataTransfer.files[0].name;
    }
  });
});
</script>
