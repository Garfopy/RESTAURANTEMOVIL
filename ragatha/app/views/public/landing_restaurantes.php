<?php
/** @var string $appName */
/** @var string $appLogo */
/** @var string $colorPrimary */
/** @var string $contactEmail */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cortes de carne PREMIUM para restaurantes | Proveedor certificado TIF | <?= htmlspecialchars($appName) ?></title>
  <meta name="description" content="Encuentra cortes de carne para restaurantes con trazabilidad, entrega confiable y proveedores certificados TIF. Ideal para hoteles y comedores industriales.">
  <meta name="keywords" content="cortes de carne para restaurantes, Rastreo de transporte de alimentos, Trazabilidad de productos cárnicos, Logística de alimentos perecederos, Monitoreo de temperatura en transporte, Proveedor de carne certificado TIF, Venta de carne al mayoreo para hoteles, Cotización de carne para comedores, Proveedores de carne con crédito, Software de compras para restaurantes, Evidencia de entrega digital POD, Certificados sanitarios de carne, Reportes de consumo por sucursal, Sistema de control de calidad alimentos, Gestión de devoluciones alimentos, Pedidos multi-sucursal para negocios, Registro de incidencias logísticas">
  <link rel="canonical" href="<?= BASE_URL ?>carnihub/cortes-de-carne-para-restaurantes">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@graph": [
      {
        "@type": "Organization",
        "name": "CarniHub",
        "url": "<?= BASE_URL ?>",
        "description": "Plataforma para conectar restaurantes y hoteles con proveedores de carne certificados.",
        "knowsAbout": [
          "cortes de carne para restaurantes",
          "trazabilidad de productos cárnicos",
          "proveedor certificado TIF",
          "software de compras para restaurantes",
          "logística de alimentos perecederos"
        ]
      },
      {
        "@type": "Service",
        "name": "Cortes de carne para restaurantes",
        "provider": { "@type": "Organization", "name": "CarniHub" }
      },
      {
        "@type": "FAQPage",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "¿Qué es la trazabilidad de productos cárnicos?",
            "acceptedAnswer": { "@type": "Answer", "text": "Es el seguimiento del producto desde origen hasta entrega final, identificando condiciones de transporte y evidencia de entrega." }
          },
          {
            "@type": "Question",
            "name": "¿Qué es un proveedor certificado TIF?",
            "acceptedAnswer": { "@type": "Answer", "text": "Es un proveedor que cumple normas sanitarias oficiales para productos cárnicos, garantizando inocuidad alimentaria y documentación verificable." }
          },
          {
            "@type": "Question",
            "name": "¿Cómo mejorar la confiabilidad en entregas de carne para restaurantes?",
            "acceptedAnswer": { "@type": "Answer", "text": "Trabajando con proveedores que ofrezcan trazabilidad, evidencia digital de entrega y monitoreo logístico para reducir retrasos e incidencias." }
          }
        ]
      }
    ]
  }
  </script>
  <style>
    :root { --cp: <?= htmlspecialchars($colorPrimary) ?>; }
    * { scroll-behavior: smooth; }
    body { font-family: 'Inter', sans-serif; overflow-x: hidden; }
    .bg-primary   { background: var(--cp); }
    .text-primary { color: var(--cp); }
    .btn-primary  { background: var(--cp); color: #fff; transition: transform .2s, box-shadow .2s, opacity .2s; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px color-mix(in srgb, var(--cp) 50%, transparent); opacity: .92; }
    .btn-outline  { border: 2px solid rgba(255,255,255,.35); color: #fff; transition: background .2s, border-color .2s, transform .2s; }
    .btn-outline:hover { background: rgba(255,255,255,.12); border-color: rgba(255,255,255,.7); transform: translateY(-2px); }
    .hero-bg {
      background: radial-gradient(ellipse 80% 60% at 50% -10%, color-mix(in srgb, var(--cp) 30%, transparent), transparent),
                  linear-gradient(160deg, #0a0f1e 0%, #111827 55%, #1a2235 100%);
    }
    .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: .2; }
    .reveal { opacity:0; transform:translateY(32px); transition:opacity .7s ease, transform .7s ease; }
    .reveal.visible { opacity:1; transform:translateY(0); }
    .navbar { transition: background .3s, box-shadow .3s; }
    .navbar.scrolled { background: rgba(255,255,255,.97) !important; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
    .navbar.scrolled a:not(.btn-primary) { color: #374151 !important; }
    .navbar.scrolled a:not(.btn-primary):hover { color: #111827 !important; background: rgba(0,0,0,.04) !important; }
    .navbar.scrolled span { color: #374151 !important; }
    .btn-shimmer { position:relative; overflow:hidden; }
    .btn-shimmer::after { content:''; position:absolute; top:0; left:-100%; width:60%; height:100%; background: linear-gradient(90deg, transparent, rgba(255,255,255,.3), transparent); animation: shimmer 2.5s infinite; }
    @keyframes shimmer { 0%{left:-100%} 100%{left:200%} }
    @keyframes pulse-ring { 0%{box-shadow:0 0 0 0 color-mix(in srgb,var(--cp) 60%,transparent)} 100%{box-shadow:0 0 0 12px transparent} }
    .pulse-badge { animation: pulse-ring 2s infinite; }
    .feat-card { border: 1px solid #e2e8f0; transition: transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s, border-color .25s; }
    .feat-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(0,0,0,.08); border-color: color-mix(in srgb, var(--cp) 30%, transparent); }
    .banner-primary { background: linear-gradient(135deg, color-mix(in srgb, var(--cp) 90%, #000), var(--cp)); }
    .faq-item details summary { cursor: pointer; list-style: none; }
    .faq-item details summary::-webkit-details-marker { display: none; }
    .faq-arrow { transition: transform .2s; }
    .faq-item details[open] .faq-arrow { transform: rotate(180deg); }
    .text-gradient { background: linear-gradient(135deg, #fff 30%, color-mix(in srgb,var(--cp) 80%,#fff)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }
    .tif-badge { display:inline-flex; align-items:center; gap:.5rem; background:color-mix(in srgb,var(--cp) 10%,#fff); border:1px solid color-mix(in srgb,var(--cp) 30%,transparent); padding:.5rem 1rem; border-radius:9999px; font-size:.8rem; font-weight:700; color:var(--cp); }
    /* ── Slider ── */
    .slider-wrap { position:relative; overflow:hidden; min-height:82vh; }
    .slide { position:absolute; inset:0; opacity:0; pointer-events:none; transition:opacity .8s ease; }
    .slide.active { opacity:1; pointer-events:auto; }
    .slider-arrow { position:absolute; top:50%; transform:translateY(-50%); z-index:30; background:rgba(255,255,255,.13); border:1px solid rgba(255,255,255,.3); color:#fff; width:52px; height:52px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; transition:background .2s, border-color .2s, transform .2s; backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); }
    .slider-arrow:hover { background:rgba(255,255,255,.28); border-color:rgba(255,255,255,.7); transform:translateY(-50%) scale(1.08); }
    #slider-prev { left:20px; }
    #slider-next { right:20px; }
    .slider-dots { position:absolute; bottom:30px; left:50%; transform:translateX(-50%); display:flex; gap:10px; z-index:30; }
    .slider-dot { width:10px; height:10px; border-radius:50%; background:rgba(255,255,255,.35); border:none; cursor:pointer; transition:background .25s, transform .25s; padding:0; }
    .slider-dot.active { background:#fff; transform:scale(1.4); }
    .slide-chip { display:inline-flex; align-items:center; gap:.5rem; padding:.4rem 1.1rem; border-radius:9999px; font-size:.72rem; font-weight:800; letter-spacing:.1em; text-transform:uppercase; margin-bottom:1.25rem; }
    .slider-progress { position:absolute; bottom:0; left:0; height:3px; background:var(--cp); width:0%; z-index:30; }
    .slider-progress.running { width:100%; transition:width 5s linear; }
  </style>
</head>
<body class="bg-white text-gray-900">

<!-- ══ NAVBAR ══ -->
<nav class="navbar fixed top-0 w-full z-50 bg-transparent">
  <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
    <div class="flex items-center gap-3">
      <a href="<?= BASE_URL ?>" class="flex items-center gap-2 no-underline">
        <?php if ($appLogo): ?>
          <img src="<?= htmlspecialchars($appLogo) ?>" alt="Logo" class="h-9 object-contain">
        <?php else: ?>
          <span class="text-xl font-black text-white tracking-tight"><?= htmlspecialchars($appName) ?></span>
        <?php endif; ?>
      </a>
      <svg class="w-3.5 h-3.5 text-white/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
      <span class="text-white/60 text-sm font-medium hidden md:block">Restaurantes &amp; Hoteles</span>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= BASE_URL ?>auth/login" class="text-sm font-semibold text-white/80 px-4 py-2 hover:text-white transition-colors">Iniciar sesión</a>
      <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer text-sm font-bold px-5 py-2.5 rounded-xl">Comenzar gratis</a>
    </div>
  </div>
</nav>

<!-- ══ SLIDER HERO ══ -->
<div class="slider-wrap pt-16" id="hero-slider">

  <!-- Slide 1: Cortes de carne para restaurantes con trazabilidad y entrega garantizada -->
  <!-- alt-img: cortes-carne-restaurantes-trazabilidad -->
  <div class="slide active" data-slide="0" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,color-mix(in srgb,var(--cp) 30%,transparent),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#1a2235 100%)">
    <div class="orb" style="width:500px;height:500px;background:var(--cp);top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:#22c55e;bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip pulse-badge" style="background:color-mix(in srgb,var(--cp) 20%,transparent);border:1px solid color-mix(in srgb,var(--cp) 50%,transparent);color:var(--cp)">
          <span class="w-2 h-2 rounded-full bg-primary animate-pulse inline-block"></span>
          Cortes de carne para restaurantes
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Trazabilidad y<br>
          <span class="text-gradient">entrega garantizada</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Conecta con proveedores certificados TIF. Calidad constante, monitoreo logístico y entregas puntuales para tu restaurante u hotel.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold px-8 py-4 rounded-xl">Conectar con proveedor TIF →</a>
      </div>
    </div>
  </div>

  <!-- Slide 2: Proveedores certificados TIF para restaurantes y hoteles -->
  <!-- alt-img: proveedor-carne-certificado-tif -->
  <div class="slide" data-slide="1" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(34,197,94,.25),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#0f1e14 100%)">
    <div class="orb" style="width:500px;height:500px;background:#22c55e;top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:var(--cp);bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip" style="background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.45);color:#22c55e">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#22c55e"></span>
          Proveedor de carne certificado TIF
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Proveedores certificados TIF<br>
          <span style="background:linear-gradient(135deg,#fff 30%,#86efac);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">para restaurantes y hoteles</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Cumplimiento sanitario verificado, inocuidad alimentaria certificada y documentación verificable en cada entrega para hoteles y cadenas restauranteras.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="inline-block font-bold px-8 py-4 rounded-xl" style="background:#22c55e;color:#fff">Ver proveedores certificados →</a>
      </div>
    </div>
  </div>

  <!-- Slide 3: Controla evidencias de entrega desde un solo sistema -->
  <!-- alt-img: pedidos-multi-sucursal-restaurantes -->
  <div class="slide" data-slide="2" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(245,158,11,.28),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#1e1a10 100%)">
    <div class="orb" style="width:500px;height:500px;background:#f59e0b;top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:var(--cp);bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip" style="background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.45);color:#f59e0b">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#f59e0b"></span>
          Pedidos multi-sucursal para restaurantes
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Controla evidencias de entrega<br>
          <span style="background:linear-gradient(135deg,#fff 30%,#fcd34d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">desde un solo sistema</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          POD digital, pedidos multi-sucursal y trazabilidad centralizada para reducir errores operativos y mejorar el control de tu cadena de abastecimiento.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="inline-block font-bold px-8 py-4 rounded-xl" style="background:#f59e0b;color:#fff">Ver evidencia digital POD →</a>
      </div>
    </div>
  </div>

  <!-- Slide 4: CarniHub optimiza compras, logística y control de calidad -->
  <!-- alt-img: software-compras-restaurantes-carnihub -->
  <div class="slide" data-slide="3" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(99,102,241,.28),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#0e1120 100%)">
    <div class="orb" style="width:500px;height:500px;background:#6366f1;top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:var(--cp);bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip" style="background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.45);color:#a5b4fc">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#a5b4fc"></span>
          Software de compras para restaurantes
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          CarniHub optimiza compras,<br>
          <span style="background:linear-gradient(135deg,#fff 30%,#c7d2fe);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">logística y control de calidad</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Administra proveedores, valida entregas, gestiona devoluciones y genera reportes de consumo por sucursal desde una sola plataforma.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="inline-block font-bold px-8 py-4 rounded-xl" style="background:#6366f1;color:#fff">Ver software de compras →</a>
      </div>
    </div>
  </div>

  <!-- Flecha izquierda -->
  <button class="slider-arrow" id="slider-prev" aria-label="Diapositiva anterior">
    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
    </svg>
  </button>

  <!-- Flecha derecha -->
  <button class="slider-arrow" id="slider-next" aria-label="Diapositiva siguiente">
    <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
      <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
    </svg>
  </button>

  <!-- Dots de navegación -->
  <div class="slider-dots" id="slider-dots">
    <button class="slider-dot active" data-slide="0" aria-label="Diapositiva 1"></button>
    <button class="slider-dot" data-slide="1" aria-label="Diapositiva 2"></button>
    <button class="slider-dot" data-slide="2" aria-label="Diapositiva 3"></button>
    <button class="slider-dot" data-slide="3" aria-label="Diapositiva 4"></button>
  </div>

  <!-- Barra de progreso -->
  <div class="slider-progress" id="slider-progress"></div>
</div>

<!-- ══ H1 INTRO ══ -->
<section id="intro" class="bg-white py-16">
  <div class="max-w-6xl mx-auto px-6 reveal">
    <div class="flex items-center gap-2 text-sm text-gray-400 mb-5">
      <a href="<?= BASE_URL ?>" class="hover:text-gray-600 transition-colors">Inicio</a>
      <span>›</span>
      <span>Restaurantes &amp; Hoteles</span>
    </div>
    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
      Cortes de carne para restaurantes con trazabilidad, calidad y logística confiable
    </h1>
    <p class="text-gray-600 leading-relaxed max-w-3xl mb-4">
      Encontrar cortes de carne para restaurantes que mantengan calidad constante, trazabilidad y entregas
      puntuales es uno de los mayores retos para chefs ejecutivos, cadenas restauranteras y hoteles.
      Las operaciones gastronómicas modernas requieren mucho más que un proveedor tradicional.
    </p>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 mb-6 max-w-3xl">
      <?php foreach (['Entregas confiables','Monitoreo de temperatura','Evidencia digital de entrega','Control multi-sucursal','Cumplimiento sanitario','Logística inversa eficiente'] as $tag): ?>
      <span class="tif-badge text-xs"><?= htmlspecialchars($tag) ?></span>
      <?php endforeach; ?>
    </div>
    <p class="text-gray-600 leading-relaxed max-w-3xl mb-8">
      CarniHub conecta restaurantes, hoteles y comedores industriales con proveedores especializados de
      productos cárnicos certificados y herramientas digitales de control operativo.
    </p>
    <div class="flex flex-col sm:flex-row gap-3">
      <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold text-base px-8 py-4 rounded-xl text-center">Conectar con proveedor TIF →</a>
      <a href="#tif" class="inline-block border-2 border-gray-200 font-semibold text-base px-8 py-4 rounded-xl text-gray-700 hover:border-gray-400 transition-colors text-center">Ver trazabilidad</a>
    </div>
  </div>
</section>

<!-- ══ H2: Proveedor TIF ══ -->
<section id="tif" class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-14 items-center">
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Certificación TIF</p>
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
          Proveedor de carne certificado TIF para restaurantes y hoteles
        </h2>
        <p class="text-gray-600 mb-6 leading-relaxed">
          Uno de los factores más importantes para cadenas restauranteras y hoteles es trabajar con un
          <strong>proveedor de carne certificado TIF</strong> que garantice cumplimiento sanitario,
          inocuidad alimentaria y documentación verificable en cada entrega.
        </p>
        <ul class="space-y-3 mb-8">
          <?php foreach ([
            'Cumplimiento sanitario verificado',
            'Trazabilidad desde origen',
            'Inocuidad alimentaria certificada',
            'Estabilidad en calidad del producto',
            'Documentación verificable incluida',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-700">
            <svg class="w-5 h-5 flex-shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold px-7 py-3.5 rounded-xl">Ver proveedores certificados</a>
      </div>
      <div class="reveal">
        <div class="grid grid-cols-2 gap-4">
          <?php foreach ([
            ['🏅','Certificación TIF','Regulada por SENASICA'],
            ['📋','Documentación','Verificable en cada entrega'],
            ['🌡️','Control sanitario','Proceso regulado'],
            ['🍽️','Hoteles atendidos','Cadenas Premium'],
          ] as [$icon,$title,$sub]): ?>
          <div class="bg-slate-50 rounded-2xl p-6 border border-gray-100 feat-card text-center">
            <div class="text-3xl mb-2"><?= $icon ?></div>
            <div class="font-extrabold text-gray-900 text-sm mb-0.5"><?= htmlspecialchars($title) ?></div>
            <div class="text-xs text-gray-400"><?= htmlspecialchars($sub) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ BANNER 1 ══ -->
<section class="banner-primary py-14">
  <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6 reveal">
    <div>
      <p class="text-white/70 text-sm font-medium mb-1">Control de calidad</p>
      <h3 class="text-2xl md:text-3xl font-extrabold text-white">Trabaja con proveedores certificados y trazabilidad completa.</h3>
    </div>
    <a href="<?= BASE_URL ?>planes/registro"
       class="flex-shrink-0 bg-white font-bold px-8 py-4 rounded-xl text-sm hover:bg-gray-100 transition-colors whitespace-nowrap"
       style="color:var(--cp)">
      Sistema de control de calidad alimentos →
    </a>
  </div>
</section>

<!-- ══ H2: Trazabilidad ══ -->
<section class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-12 reveal">
      <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Trazabilidad total</p>
      <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">
        Trazabilidad de productos cárnicos y rastreo de transporte de alimentos
      </h2>
      <p class="text-gray-500 max-w-2xl mx-auto">
        La trazabilidad se ha convertido en un requisito operativo indispensable para hoteles,
        restaurantes premium, comedores industriales y cadenas multi-sucursal.
      </p>
    </div>
    <div class="grid md:grid-cols-2 gap-8 mb-12">
      <div class="bg-slate-50 rounded-2xl p-8 border border-gray-100 reveal">
        <h3 class="text-xl font-extrabold text-gray-900 mb-5">Con CarniHub mejoras</h3>
        <ul class="space-y-3">
          <?php foreach ([
            'Rastreo de transporte de alimentos',
            'Monitoreo de temperatura en transporte',
            'Registro de incidencias logísticas',
            'Control documental completo',
            'Validación digital de entregas',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-700">
            <svg class="w-5 h-5 flex-shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-5 pt-4 border-t border-gray-200 text-sm text-gray-500">
          La trazabilidad de productos cárnicos permite identificar origen, condiciones de transporte y evidencia
          de entrega para cumplir normas sanitarias.
        </p>
      </div>
      <div class="rounded-2xl p-8 text-white reveal" style="background:linear-gradient(135deg,#1a2235,#111827)">
        <h3 class="text-xl font-extrabold mb-2">¿Por qué el monitoreo de temperatura es crítico?</h3>
        <p class="text-gray-400 text-sm mb-5 leading-relaxed">
          Especialmente importante para hoteles y cadenas restauranteras con altos estándares operativos.
        </p>
        <ul class="space-y-3">
          <?php foreach ([
            'Reducir mermas en producto',
            'Evitar contaminación cruzada',
            'Mantener calidad del producto',
            'Cumplir normas del sector alimenticio',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-300">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--cp)">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
    </div>
  </div>
</section>

<!-- ══ H2: Venta mayoreo ══ -->
<section class="py-20" style="background:linear-gradient(180deg,#f8fafc,#fff)">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-14 items-center">
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Mayoreo garantizado</p>
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
          Venta de carne al mayoreo para hoteles y comedores industriales
        </h2>
        <p class="text-gray-600 mb-6 leading-relaxed">
          Los negocios con múltiples sucursales requieren abastecimiento constante, pedidos programados,
          precios competitivos y estabilidad operativa. CarniHub lo ofrece todo.
        </p>
        <div class="grid grid-cols-2 gap-3 mb-8">
          <?php foreach ([
            'Venta de carne al mayoreo para hoteles',
            'Cotización de carne para comedores',
            'Compras recurrentes programadas',
            'Pedidos escalables multi-sucursal',
            'Entregas garantizadas',
            'Logística inversa (devoluciones)',
          ] as $item): ?>
          <div class="bg-white rounded-xl p-3 border border-gray-100 text-sm text-gray-700 flex items-start gap-2">
            <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold px-7 py-3.5 rounded-xl">Solicitar cotización →</a>
      </div>
      <div class="reveal">
        <div class="rounded-2xl p-8" style="background:linear-gradient(135deg,#1a2235,#111827)">
          <div class="text-4xl mb-4">🏨</div>
          <h3 class="text-xl font-extrabold text-white mb-5">Lo que necesitas para operar</h3>
          <div class="space-y-4">
            <?php foreach ([
              ['Abastecimiento constante','Sin quiebres de inventario'],
              ['Pedidos programados','Automatización de compras recurrentes'],
              ['Precios competitivos','Mayoreo consolidado con mejores tarifas'],
              ['Estabilidad operativa','Proveedores con capacidad de respuesta'],
            ] as [$title,$desc]): ?>
            <div class="bg-white/5 rounded-xl p-4">
              <div class="font-bold text-white text-sm"><?= htmlspecialchars($title) ?></div>
              <div class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($desc) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ BANNER 2 ══ -->
<section style="background:linear-gradient(135deg,#0a0f1e,#1a2235)" class="py-14">
  <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6 reveal">
    <div>
      <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:var(--cp)">Un solo ecosistema</p>
      <h3 class="text-2xl md:text-3xl font-extrabold text-white">Controla compras, logística y abastecimiento desde un solo ecosistema.</h3>
    </div>
    <a href="<?= BASE_URL ?>planes/registro" class="flex-shrink-0 btn-primary btn-shimmer font-bold px-8 py-4 rounded-xl text-sm whitespace-nowrap">
      Proveedores de carne con crédito →
    </a>
  </div>
</section>

<!-- ══ H2: Software compras + POD ══ -->
<section class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-14 items-start">
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Software de compras</p>
        <h2 class="text-3xl font-extrabold text-gray-900 mb-5">
          Software de compras para restaurantes y pedidos multi-sucursal
        </h2>
        <p class="text-gray-600 mb-6 leading-relaxed">
          La administración de compras manuales genera errores operativos, retrasos, falta de control y
          diferencias en inventarios. CarniHub funciona como un <strong>software de compras para restaurantes</strong>.
        </p>
        <ul class="space-y-3 mb-6">
          <?php foreach ([
            'Pedidos multi-sucursal para negocios',
            'Validación digital de entregas',
            'Historial de consumo por período',
            'Control administrativo centralizado',
            'Evidencias digitales POD',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-700">
            <svg class="w-5 h-5 flex-shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <div class="reveal">
        <div class="bg-slate-50 rounded-2xl p-8 border border-gray-100">
          <div class="text-4xl mb-4">📱</div>
          <h3 class="text-xl font-extrabold text-gray-900 mb-2">Evidencia de entrega digital POD</h3>
          <p class="text-gray-500 text-sm mb-6 leading-relaxed">
            La evidencia de entrega digital POD documenta digitalmente entregas y ayuda a garantizar
            cumplimiento logístico y administrativo.
          </p>
          <ul class="space-y-3 mb-6">
            <?php foreach ([
              ['📝','Registrar entregas','Documentación automática'],
              ['✅','Validar recepción','Firma o foto digital'],
              ['⚖️','Reducir disputas','Evidencia verificable'],
              ['🔍','Mejorar auditorías','Historial completo'],
              ['📌','Cumplir políticas internas','Control corporativo'],
            ] as [$icon,$title,$sub]): ?>
            <li class="flex items-start gap-3">
              <span class="text-lg"><?= $icon ?></span>
              <div>
                <div class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($title) ?></div>
                <div class="text-gray-400 text-xs"><?= htmlspecialchars($sub) ?></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer block text-center font-bold py-3.5 rounded-xl">
            Activar evidencia digital POD →
          </a>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ H2: Devoluciones ══ -->
<section class="py-20" style="background:linear-gradient(180deg,#f8fafc,#fff)">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-14 items-center">
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Logística inversa</p>
        <h2 class="text-3xl font-extrabold text-gray-900 mb-5">
          Gestión de devoluciones alimentos y logística inversa ágil
        </h2>
        <p class="text-gray-600 mb-6 leading-relaxed">
          Uno de los problemas más complejos para restaurantes y hoteles es la gestión de devoluciones.
          CarniHub ayuda a mejorar los tiempos de respuesta y el control de reposiciones.
        </p>
        <div class="grid grid-cols-2 gap-3 mb-6">
          <?php foreach ([
            ['🔄','Gestión de devoluciones alimentos'],
            ['📊','Seguimiento de incidencias'],
            ['📦','Control de reposiciones'],
            ['⚠️','Registro de anomalías'],
          ] as [$icon,$item]): ?>
          <div class="bg-white rounded-xl p-4 border border-gray-100 feat-card flex items-center gap-3 text-sm text-gray-700">
            <span class="text-xl"><?= $icon ?></span>
            <?= htmlspecialchars($item) ?>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="text-sm text-gray-400">Esto reduce pérdidas operativas y mejora tiempos de respuesta.</p>
      </div>
      <div class="reveal">
        <div class="rounded-2xl p-8" style="background:linear-gradient(135deg,#1a2235,#111827)">
          <h2 class="text-xl font-extrabold text-white mb-5">Reportes de consumo por sucursal y control de calidad de alimentos</h2>
          <p class="text-gray-400 text-sm mb-5 leading-relaxed">
            Los grupos restauranteros necesitan visibilidad total sobre consumo, comportamiento de compras y rendimiento operativo.
          </p>
          <ul class="space-y-3">
            <?php foreach ([
              'Reportes de consumo por sucursal',
              'Sistema de control de calidad alimentos',
              'Seguimiento operativo en tiempo real',
              'Análisis de abastecimiento',
            ] as $item): ?>
            <li class="flex items-center gap-3 text-gray-300">
              <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--cp)">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
              </svg>
              <?= htmlspecialchars($item) ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ BANNER 3 ══ -->
<section class="banner-primary py-14">
  <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6 reveal">
    <div>
      <p class="text-white/70 text-sm font-medium mb-1">Auditorías y cumplimiento</p>
      <h3 class="text-2xl md:text-3xl font-extrabold text-white">Facilita auditorías, control sanitario y evidencias logísticas.</h3>
    </div>
    <a href="<?= BASE_URL ?>planes/registro"
       class="flex-shrink-0 bg-white font-bold px-8 py-4 rounded-xl text-sm hover:bg-gray-100 transition-colors whitespace-nowrap"
       style="color:var(--cp)">
      Certificados sanitarios de carne →
    </a>
  </div>
</section>

<!-- ══ Resumen de beneficios ══ -->
<section class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-12 reveal">
      <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Lo que resolvemos</p>
      <h2 class="text-3xl font-extrabold text-gray-900">¿Qué debe ofrecer un proveedor profesional de carne para restaurantes?</h2>
      <p class="text-gray-500 mt-3 max-w-xl mx-auto">Debe garantizar trazabilidad, calidad constante, entregas puntuales y cumplimiento sanitario.</p>
    </div>
    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ([
        ['✅','Entregas confiables',    ['Rastreo en tiempo real','Trazabilidad completa','POD digital validado']],
        ['💰','Calidad y precio',       ['Proveedores especializados','Compra recurrente programada','Mayoreo consolidado']],
        ['📁','Facilidades administrativas',['Historial de pedidos','Control documental','Validaciones digitales']],
        ['🏥','Cumplimiento normativo',  ['Certificados sanitarios','Monitoreo de temperatura','Registro de incidencias']],
        ['🔄','Logística inversa',       ['Devoluciones ágiles','Seguimiento de anomalías','Control operativo']],
        ['📊','Reportes y analítica',    ['Consumo por sucursal','Rendimiento de proveedores','Incidencias recurrentes']],
      ] as [$icon,$title,$items]): ?>
      <div class="bg-slate-50 rounded-2xl p-6 border border-gray-100 feat-card reveal">
        <div class="text-2xl mb-3"><?= $icon ?></div>
        <h3 class="font-extrabold text-gray-900 mb-3"><?= htmlspecialchars($title) ?></h3>
        <ul class="space-y-1.5">
          <?php foreach ($items as $i): ?>
          <li class="text-sm text-gray-500 flex items-center gap-2">
            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:var(--cp)"></span>
            <?= htmlspecialchars($i) ?>
          </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ FAQ ══ -->
<section class="py-20" style="background:linear-gradient(180deg,#f8fafc,#f1f5f9)">
  <div class="max-w-3xl mx-auto px-6">
    <div class="text-center mb-10 reveal">
      <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Preguntas frecuentes</p>
      <h2 class="text-3xl font-extrabold text-gray-900">Resolvemos tus dudas</h2>
    </div>
    <div class="space-y-4 reveal">
      <?php foreach ([
        ['¿Cómo mejorar la confiabilidad en entregas de carne para restaurantes?',
         'La mejor forma es trabajar con proveedores que ofrezcan trazabilidad, evidencia digital de entrega y monitoreo logístico para reducir retrasos e incidencias.'],
        ['¿Cómo garantizar calidad constante en cortes de carne para restaurantes?',
         'Trabajando con proveedores certificados TIF que cuenten con distribuidores especializados, control sanitario y abastecimiento recurrente. Esto facilita mantener estándares consistentes en operación gastronómica.'],
        ['¿Cómo administrar compras de carne en múltiples sucursales?',
         'Usando plataformas digitales que centralicen pedidos, consumos e incidencias operativas desde un solo sistema como CarniHub.'],
        ['¿Cómo reducir problemas administrativos y fiscales con proveedores de carne?',
         'Facilitamos evidencia de entrega digital POD, historial documental, gestión de devoluciones y validación operativa.'],
        ['¿Qué es la evidencia digital POD en logística de alimentos?',
         'Es un registro digital que documenta entregas y ayuda a validar cumplimiento operativo y administrativo.'],
        ['¿Cómo cumplir normas sanitarias y auditorías en restaurantes y hoteles?',
         'Con monitoreo de temperatura en transporte, certificados sanitarios de carne, registro de incidencias logísticas y trazabilidad de productos cárnicos.'],
        ['¿Qué beneficios tiene la trazabilidad de productos cárnicos?',
         'Permite identificar origen, condiciones de transporte y evidencia operativa para mejorar control sanitario y cumplimiento.'],
        ['¿Cómo evitar que las entregas tardías afecten la operación de mi restaurante?',
         'La falta de visibilidad logística genera retrasos operativos, pérdida de producción e incumplimiento interno. CarniHub facilita rastreo de transporte de alimentos, evidencia digital POD, monitoreo operativo y control de incidencias logísticas para mejorar trazabilidad y reducir incertidumbre en entregas críticas.'],
        ['¿Cómo mejorar logística inversa y devoluciones de alimentos?',
         'Cuando existen productos dañados, diferencias en pedidos o incidencias logísticas, muchos proveedores tardan demasiado en responder. CarniHub facilita la gestión de devoluciones de alimentos, seguimiento de incidencias, trazabilidad documental y validación operativa, mejorando tiempos de respuesta y control de reposiciones.'],
        ['¿Cómo encontrar proveedores de carne confiables para hoteles y restaurantes?',
         'El principal problema del mercado gastronómico no es solo el precio: es encontrar proveedores que mantengan calidad constante, entregas puntuales, documentación correcta y capacidad operativa. CarniHub ofrece proveedores certificados, distribuidores especializados, soluciones de trazabilidad y herramientas de control operativo para reducir riesgo y mejorar estabilidad operativa.'],
        ['¿Qué debe ofrecer un proveedor profesional de carne para restaurantes?',
         'Debe garantizar trazabilidad, calidad constante, entregas puntuales y cumplimiento sanitario. CarniHub ayuda a resolver entregas confiables (rastreo, trazabilidad, POD digital), calidad y precio (proveedores especializados, compra recurrente, mayoreo), facilidades administrativas (historial de pedidos, control documental, validaciones digitales), cumplimiento normativo (certificados sanitarios, monitoreo de temperatura) y logística inversa (devoluciones ágiles, seguimiento de anomalías, control operativo).'],
      ] as [$q,$a]): ?>
      <div class="faq-item border border-gray-200 rounded-2xl overflow-hidden bg-white">
        <details>
          <summary class="flex items-center justify-between p-5 font-semibold text-gray-900 hover:bg-slate-50 transition-colors">
            <?= htmlspecialchars($q) ?>
            <svg class="faq-arrow w-5 h-5 flex-shrink-0 ml-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
            </svg>
          </summary>
          <div class="px-5 pb-5 text-gray-600 text-sm leading-relaxed border-t border-gray-100 pt-4"><?= htmlspecialchars($a) ?></div>
        </details>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ══ CTA FINAL ══ -->
<section class="py-24 relative overflow-hidden" style="background:linear-gradient(135deg,#0a0f1e 0%,#111827 60%,#1a2235 100%)">
  <div class="orb" style="width:600px;height:600px;background:var(--cp);top:-200px;left:50%;transform:translateX(-50%);opacity:.12;filter:blur(100px);"></div>
  <div class="max-w-3xl mx-auto px-6 text-center relative z-10 reveal">
    <p class="text-xs font-bold uppercase tracking-widest text-primary mb-4">Empieza hoy</p>
    <h2 class="text-4xl md:text-5xl font-black text-white mb-6 leading-tight">
      Tu restaurante merece<br>
      <span class="text-gradient">proveedores de primer nivel</span>
    </h2>
    <p class="text-gray-400 text-lg mb-10 max-w-xl mx-auto leading-relaxed">
      Únete a los restaurantes y hoteles que ya operan con trazabilidad, calidad certificada
      y logística confiable a través de CarniHub.
    </p>
    <div class="flex flex-col sm:flex-row gap-4 justify-center">
      <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer font-bold text-lg px-10 py-4 rounded-2xl">Comenzar ahora →</a>
      <a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="btn-outline font-semibold text-base px-8 py-4 rounded-2xl">Hablar con ventas</a>
    </div>
  </div>
</section>

<!-- ══ FOOTER ══ -->
<footer class="bg-gray-950 py-12">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-8 border-b border-gray-800 pb-8 mb-8">
      <div>
        <span class="text-lg font-black text-white"><?= htmlspecialchars($appName) ?></span>
        <p class="text-sm text-gray-500 mt-2">Plataforma de abastecimiento cárnico con trazabilidad y logística para operaciones gastronómicas.</p>
      </div>
      <div>
        <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">Soluciones</h4>
        <ul class="space-y-2">
          <li><a href="<?= BASE_URL ?>distribuidora-carne-cerca-de-mi"            class="text-sm text-gray-500 hover:text-white transition-colors">Distribuidora de carne cerca de mí</a></li>
          <li><a href="<?= BASE_URL ?>carnihub/cortes-de-carne-para-restaurantes" class="text-sm text-white/60 font-semibold">→ Cortes de carne para restaurantes</a></li>
          <li><a href="<?= BASE_URL ?>carnihub"                                    class="text-sm text-gray-500 hover:text-white transition-colors">Software para CEDIS y carnicerias</a></li>
        </ul>
      </div>
      <div>
        <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">Contacto</h4>
        <ul class="space-y-2">
          <li><span class="text-sm text-gray-500">Querétaro, México</span></li>
          <li><a href="<?= BASE_URL ?>planes" class="text-sm text-gray-500 hover:text-white transition-colors">Ver planes</a></li>
          <li><a href="mailto:<?= htmlspecialchars($contactEmail) ?>" class="text-sm text-gray-500 hover:text-white transition-colors"><?= htmlspecialchars($contactEmail) ?></a></li>
        </ul>
      </div>
    </div>
    <div class="flex flex-col md:flex-row items-center justify-between gap-4 text-xs text-gray-600">
      <span>© <?= date('Y') ?> <?= htmlspecialchars($appName) ?> · Todos los derechos reservados</span>
      <a href="<?= BASE_URL ?>auth/login" class="hover:text-gray-400 transition-colors">Iniciar sesión</a>
    </div>
  </div>
</footer>

<script>
window.addEventListener('scroll', () => {
  document.querySelector('.navbar').classList.toggle('scrolled', window.scrollY > 40);
});
// ── Scroll reveal ──
const observer = new IntersectionObserver((entries) => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// ── Slider ──
(function () {
  const INTERVAL = 5000;
  const slides  = document.querySelectorAll('#hero-slider .slide');
  const dots    = document.querySelectorAll('#slider-dots .slider-dot');
  const bar     = document.getElementById('slider-progress');
  let current   = 0;
  let autoTimer = null;

  function goTo(index) {
    slides[current].classList.remove('active');
    dots[current].classList.remove('active');
    current = (index + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current].classList.add('active');
    resetProgress();
  }

  function resetProgress() {
    if (!bar) return;
    bar.classList.remove('running');
    bar.style.transition = 'none';
    bar.style.width = '0%';
    requestAnimationFrame(() => requestAnimationFrame(() => {
      bar.style.transition = '';
      bar.classList.add('running');
    }));
  }

  function startAuto() {
    clearInterval(autoTimer);
    autoTimer = setInterval(() => goTo(current + 1), INTERVAL);
  }

  document.getElementById('slider-prev').addEventListener('click', () => { goTo(current - 1); startAuto(); });
  document.getElementById('slider-next').addEventListener('click', () => { goTo(current + 1); startAuto(); });
  dots.forEach(dot => dot.addEventListener('click', () => { goTo(parseInt(dot.dataset.slide)); startAuto(); }));

  // Swipe support
  let touchStartX = 0;
  const wrap = document.getElementById('hero-slider');
  wrap.addEventListener('touchstart', e => { touchStartX = e.changedTouches[0].clientX; }, { passive: true });
  wrap.addEventListener('touchend', e => {
    const diff = touchStartX - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) { goTo(diff > 0 ? current + 1 : current - 1); startAuto(); }
  }, { passive: true });

  resetProgress();
  startAuto();
})();
</script>
</body>
</html>
