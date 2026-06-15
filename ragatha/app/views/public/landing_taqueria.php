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
  <title>Distribuidora de carne cerca de mí | Carne para taquerías y restaurantes Querétaro | <?= htmlspecialchars($appName) ?></title>
  <meta name="description" content="Encuentra una distribuidora de carne cerca de ti con precios competitivos, entrega garantizada y carne de calidad para taquerías, restaurantes y negocios de comida. Facturación expedita.">
  <meta name="keywords" content="Distribuidora de carne cerca de mí, Proveedor de carne para taquería, Precio de bistec de res por mayoreo, Carne a domicilio para negocio, Venta de carne por caja, Pastor preparado para taquería mayoreo, Carnicería con servicio a restaurantes, Proveedores de carne que den crédito, Precio de kilo de carne de res hoy, Donde comprar carne barata y buena para negocio">
  <link rel="canonical" href="<?= BASE_URL ?>distribuidora-carne-cerca-de-mi">
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
        "description": "Plataforma para conectar negocios de comida con proveedores de carne.",
        "knowsAbout": [
          "proveedor de carne para taquería",
          "venta de carne por caja",
          "carne por mayoreo",
          "pastor preparado",
          "carne para restaurantes"
        ]
      },
      {
        "@type": "Service",
        "name": "Distribuidora de carne cerca de mí",
        "provider": { "@type": "Organization", "name": "CarniHub" },
        "serviceType": "Distribución y conexión con proveedores de carne"
      },
      {
        "@type": "FAQPage",
        "mainEntity": [
          {
            "@type": "Question",
            "name": "¿Qué es una distribuidora de carne cerca de mí?",
            "acceptedAnswer": { "@type": "Answer", "text": "Es un proveedor local o regional que suministra carne para restaurantes, taquerías y negocios de comida con entregas programadas y compra por mayoreo." }
          },
          {
            "@type": "Question",
            "name": "¿Qué beneficios tiene comprar carne por mayoreo consolidado?",
            "acceptedAnswer": { "@type": "Answer", "text": "Reduce costos, mejora control de inventario y facilita la operación del negocio porque entre varios puntos aumentan el volumen de compra de insumos." }
          },
          {
            "@type": "Question",
            "name": "¿CarniHub vende carne directamente?",
            "acceptedAnswer": { "@type": "Answer", "text": "Sí, cuenta con una empacadora profesional de carne." }
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
      <span class="text-white/60 text-sm font-medium hidden md:block">Taquerías</span>
    </div>
    <div class="flex items-center gap-2">
      <a href="<?= BASE_URL ?>auth/login" class="text-sm font-semibold text-white/80 px-4 py-2 hover:text-white transition-colors">Iniciar sesión</a>
      <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer text-sm font-bold px-5 py-2.5 rounded-xl">Comenzar gratis</a>
    </div>
  </div>
</nav>

<!-- ══ SLIDER HERO ══ -->
<div class="slider-wrap pt-16" id="hero-slider">

  <!-- Slide 1: Proveedor de carne para taquería -->
  <div class="slide active" data-slide="0" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,color-mix(in srgb,var(--cp) 30%,transparent),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#1a2235 100%)">
    <div class="orb" style="width:500px;height:500px;background:var(--cp);top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:#f97316;bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip pulse-badge" style="background:color-mix(in srgb,var(--cp) 20%,transparent);border:1px solid color-mix(in srgb,var(--cp) 50%,transparent);color:var(--cp)">
          <span class="w-2 h-2 rounded-full bg-primary animate-pulse inline-block"></span>
          Proveedor de carne para taquería
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Encuentra proveedores de carne<br>
          <span class="text-gradient">confiables para tu taquería</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Conecta con distribuidoras especializadas. Calidad constante, precio competitivo y entregas garantizadas para negocios de comida.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold px-8 py-4 rounded-xl">Encontrar proveedor →</a>
      </div>
    </div>
  </div>

  <!-- Slide 2: Distribuidora de carne cerca de mí -->
  <div class="slide" data-slide="1" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(245,158,11,.28),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#1e1a10 100%)">
    <div class="orb" style="width:500px;height:500px;background:#f59e0b;top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:var(--cp);bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip" style="background:rgba(245,158,11,.18);border:1px solid rgba(245,158,11,.45);color:#f59e0b">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#f59e0b"></span>
          Distribuidora de carne cerca de mí
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Compra carne por mayoreo<br>
          <span style="background:linear-gradient(135deg,#fff 30%,#fcd34d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">con mejor precio y entrega garantizada</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Accede a precios de mayoreo consolidado y elimina intermediarios poco confiables. Precio de bistec de res, carne por caja y más.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="inline-block font-bold px-8 py-4 rounded-xl" style="background:#f59e0b;color:#fff">Ver precios mayoreo →</a>
      </div>
    </div>
  </div>

  <!-- Slide 3: Carne a domicilio para negocio -->
  <div class="slide" data-slide="2" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(34,197,94,.25),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#0f1e14 100%)">
    <div class="orb" style="width:500px;height:500px;background:#22c55e;top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:var(--cp);bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip" style="background:rgba(34,197,94,.18);border:1px solid rgba(34,197,94,.45);color:#22c55e">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#22c55e"></span>
          Carne a domicilio para negocio
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Facturación simplificada<br>
          <span style="background:linear-gradient(135deg,#fff 30%,#86efac);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">para taquerías y restaurantes</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Facturación expedita, crédito empresarial y entregas programadas con visibilidad en tiempo real para tu negocio.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="inline-block font-bold px-8 py-4 rounded-xl" style="background:#22c55e;color:#fff">Cotizar entrega →</a>
      </div>
    </div>
  </div>

  <!-- Slide 4: Venta de carne por caja -->
  <div class="slide" data-slide="3" style="background:radial-gradient(ellipse 80% 60% at 50% -10%,rgba(99,102,241,.28),transparent),linear-gradient(160deg,#0a0f1e 0%,#111827 55%,#0e1120 100%)">
    <div class="orb" style="width:500px;height:500px;background:#6366f1;top:-120px;right:-100px;"></div>
    <div class="orb" style="width:350px;height:350px;background:var(--cp);bottom:-80px;left:-60px;"></div>
    <div class="max-w-6xl mx-auto px-6 py-20 md:py-28 relative z-10">
      <div class="max-w-3xl">
        <div class="slide-chip" style="background:rgba(99,102,241,.18);border:1px solid rgba(99,102,241,.45);color:#a5b4fc">
          <span class="w-2 h-2 rounded-full inline-block" style="background:#a5b4fc"></span>
          Venta de carne por caja
        </div>
        <h2 class="text-4xl md:text-5xl lg:text-6xl font-black text-white leading-tight mb-5">
          Solución diseñada para negocios<br>
          <span style="background:linear-gradient(135deg,#fff 30%,#c7d2fe);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text">con sucursales</span>
        </h2>
        <p class="text-gray-400 text-lg mb-8 max-w-xl leading-relaxed">
          Pedidos multi-sucursal, inventarios centralizados, compras recurrentes y control operativo desde un solo sistema.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="inline-block font-bold px-8 py-4 rounded-xl" style="background:#6366f1;color:#fff">Ver solución multi-sucursal →</a>
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
      <span>Taquerías</span>
    </div>
    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
      Distribuidora de carne cerca de mí para taquerías
    </h1>
    <p class="text-gray-600 leading-relaxed max-w-3xl mb-4">
      Encontrar una distribuidora de carne cerca de mí que garantice calidad, precio competitivo y entregas
      puntuales es uno de los principales retos para dueños de taquerías y restaurantes, más si tienen sucursales
      y necesitan control de inventarios y logístico separado.
    </p>
    <p class="text-gray-600 leading-relaxed max-w-3xl mb-8">
      Con CarniHub, puedes conectar con proveedores profesionales de carne para negocio, comparar opciones
      y mejorar la operación de tu cocina sin depender de intermediarios poco confiables.
    </p>
    <div class="flex flex-col sm:flex-row gap-3">
      <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold text-base px-8 py-4 rounded-xl text-center">Encuentra tu proveedor →</a>
      <a href="#proveedor" class="inline-block border-2 border-gray-200 font-semibold text-base px-8 py-4 rounded-xl text-gray-700 hover:border-gray-400 transition-colors text-center">Ver cómo funciona</a>
    </div>
  </div>
</section>

<!-- ══ H2: Proveedor confiable ══ -->
<section id="proveedor" class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-14 items-center">
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Proveedor confiable</p>
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
          Proveedor de carne para taquería con precio competitivo y entrega garantizada
        </h2>
        <p class="text-gray-600 mb-6 leading-relaxed">
          Uno de los problemas más frecuentes en taquerías y negocios de comida es encontrar un proveedor
          confiable que mantenga calidad y precio estable. Con CarniHub, puedes conectar con proveedores profesionales
          de carne para negocio, comparar opciones y mejorar la operación de tu cocina sin depender de
          intermediarios poco confiables.
        </p>
        <ul class="space-y-3 mb-8">
          <?php foreach ([
            'Calidad constante en cada entrega',
            'Entregas puntuales programadas',
            'Precios competitivos por mayoreo',
            'Facilidad de compra en línea',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-700">
            <svg class="w-5 h-5 flex-shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold px-7 py-3.5 rounded-xl">Cotiza ahora</a>
      </div>
      <div class="reveal">
        <div class="grid grid-cols-2 gap-4">
          <?php foreach ([
            ['🌮','Taquerías atendidas','500+'],
            ['🚚','Entregas garantizadas','100%'],
            ['⏱','Tiempo de respuesta','24 h'],
            ['💳','Pago en linea','Sí'],
          ] as [$icon,$label,$val]): ?>
          <div class="bg-slate-50 rounded-2xl p-6 border border-gray-100 feat-card text-center">
            <div class="text-3xl mb-2"><?= $icon ?></div>
            <div class="text-2xl font-black text-gray-900 mb-1"><?= htmlspecialchars($val) ?></div>
            <div class="text-xs text-gray-500"><?= htmlspecialchars($label) ?></div>
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
      <p class="text-white/70 text-sm font-medium mb-1">Donde comprar carne barata y buena para negocio</p>
      <h3 class="text-2xl md:text-3xl font-extrabold text-white">¿Tu proveedor falla en entregas o cambia precios constantemente?</h3>
    </div>
    <a href="<?= BASE_URL ?>planes/registro"
       class="flex-shrink-0 bg-white font-bold px-8 py-4 rounded-xl text-sm hover:bg-gray-100 transition-colors whitespace-nowrap"
       style="color:var(--cp)">
      Cotiza en CarniHub →
    </a>
  </div>
</section>

<!-- ══ H2: Precio mayoreo ══ -->
<section class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="text-center mb-12 reveal">
      <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Precios competitivos</p>
      <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-4">
        Precio de bistec de res por mayoreo y carne por caja para negocio
      </h2>
      <p class="text-gray-500 max-w-2xl mx-auto">
        El costo de los insumos impacta directamente la rentabilidad de una taquería. Nuestro sistema de
        compras al mayoreo consolidadas te da acceso a mejores precios.
      </p>
    </div>
    <div class="grid md:grid-cols-2 gap-8 mb-10">
      <div class="bg-slate-50 rounded-2xl p-8 border border-gray-100 reveal">
        <h3 class="text-xl font-extrabold text-gray-900 mb-5">Lo que buscas</h3>
        <ul class="space-y-3">
          <?php foreach ([
            'Precio de bistec de res por mayoreo',
            'Venta de carne por caja',
            'Precio de kilo de carne de res hoy',
            'Carne barata y buena para negocio',
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
      <div class="rounded-2xl p-8 text-white reveal" style="background:linear-gradient(135deg,#1a2235,#111827)">
        <h3 class="text-xl font-extrabold mb-5">¿Por qué comprar por mayoreo mejora la rentabilidad?</h3>
        <ul class="space-y-3">
          <?php foreach ([
            'Negocia mejores precios con proveedores',
            'Reduce compras urgentes y de emergencia',
            'Controla el inventario de manera eficiente',
            'Mejora los márgenes de utilidad',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-300">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5" style="color:var(--cp)">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="mt-6 pt-5 border-t border-white/10 text-sm text-gray-400">
          Comprar carne en CarniHub permite reducir costos operativos y mantener estabilidad en inventarios por nuestro sistema de compras al mayoreo consolidadas.
        </p>
      </div>
    </div>
    <div class="text-center reveal">
      <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold text-base px-10 py-4 rounded-xl">
        Garantiza entregas puntuales y calidad constante →
      </a>
    </div>
  </div>
</section>

<!-- ══ H2: Pastor preparado ══ -->
<section class="py-20" style="background:linear-gradient(180deg,#f8fafc,#fff)">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-14 items-center">
      <div class="reveal">
        <div class="rounded-2xl p-8" style="background:linear-gradient(135deg,#1a2235,#111827)">
          <div class="text-4xl mb-4">🌮</div>
          <h3 class="text-xl font-extrabold text-white mb-4">Impacto del pastor en tu negocio</h3>
          <div class="space-y-4">
            <?php foreach ([
              ['Sabor','Consistente en cada servicio, cada día'],
              ['Recompra','Clientes que regresan por la experiencia'],
              ['Reputación','Posicionamiento sólido frente a la competencia'],
            ] as [$title,$desc]): ?>
            <div class="bg-white/5 rounded-xl p-4">
              <div class="font-bold text-white text-sm"><?= htmlspecialchars($title) ?></div>
              <div class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($desc) ?></div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Especialidad taquera</p>
        <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
          Pastor preparado para taquería mayoreo con calidad constante
        </h2>
        <p class="text-gray-600 mb-5 leading-relaxed">
          La calidad del pastor impacta directamente el sabor, la recompra y la reputación de tu negocio.
          Muchos negocios pierden clientes cuando cambian constantemente de proveedor.
        </p>
        <p class="text-gray-600 mb-8 leading-relaxed">
          CarniHub facilita encontrar proveedores especializados en <strong>pastor preparado para taquería mayoreo</strong>
          con procesos más estables y entregas programadas, ideales para taquerías con más de una sucursal.
        </p>
        <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold px-7 py-3.5 rounded-xl">
          Solicitar proveedor de pastor →
        </a>
      </div>
    </div>
  </div>
</section>

<!-- ══ BANNER 2 ══ -->
<section style="background:linear-gradient(135deg,#0a0f1e,#1a2235)" class="py-14">
  <div class="max-w-6xl mx-auto px-6 flex flex-col md:flex-row items-center justify-between gap-6 reveal">
    <div>
      <p class="text-xs font-bold uppercase tracking-widest mb-2" style="color:var(--cp)">Pastor preparado para taquería mayoreo</p>
      <h3 class="text-2xl md:text-3xl font-extrabold text-white">Estamos especializados en taquerías con más de 1 sucursal.</h3>
    </div>
    <a href="<?= BASE_URL ?>planes/registro" class="flex-shrink-0 btn-primary btn-shimmer font-bold px-8 py-4 rounded-xl text-sm whitespace-nowrap">
      Hablar con un experto →
    </a>
  </div>
</section>

<!-- ══ H2: Carne a domicilio + Crédito ══ -->
<section class="bg-white py-20">
  <div class="max-w-6xl mx-auto px-6">
    <div class="grid md:grid-cols-2 gap-10 items-start">
      <div class="reveal">
        <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Logística local</p>
        <h2 class="text-3xl font-extrabold text-gray-900 mb-5">Carne a domicilio para negocio y restaurantes</h2>
        <p class="text-gray-600 mb-6 leading-relaxed">
          La logística es uno de los mayores retos para restaurantes y taquerías. Con CarniHub puedes encontrar
          proveedores con cobertura local para eliminar interrupciones operativas.
        </p>
        <ul class="space-y-3 mb-6">
          <?php foreach ([
            'Carnicería con servicio a restaurantes',
            'Carne a domicilio para negocio',
            'Proveedores con cobertura local',
          ] as $item): ?>
          <li class="flex items-center gap-3 text-gray-700">
            <svg class="w-5 h-5 flex-shrink-0 text-primary" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
            </svg>
            <?= htmlspecialchars($item) ?>
          </li>
          <?php endforeach; ?>
        </ul>
        <p class="text-sm text-gray-400 italic">Esto reduce: tiempos muertos · compras de emergencia · interrupciones operativas</p>
      </div>

      <div class="reveal">
        <div class="bg-slate-50 rounded-2xl p-8 border border-gray-100">
          <h3 class="text-xl font-extrabold text-gray-900 mb-2">Proveedores de carne que den crédito para negocios</h3>
          <p class="text-gray-500 text-sm mb-6">Muchos negocios necesitan flujo operativo más flexible. CarniHub ofrece:</p>
          <ul class="space-y-4">
            <?php foreach ([
              ['💳','Crédito empresarial','Financiamiento para tu operación'],
              ['💵','Pago flexible','En efectivo o en línea'],
              ['📊','Reporte de compras','Historial de compras recurrentes'],
              ['🧾','Facturación inmediata','Online y expedita'],
              ['📍','Entregas con visibilidad','Rastreo en tiempo real'],
            ] as [$icon,$title,$desc]): ?>
            <li class="flex items-start gap-4">
              <span class="text-xl flex-shrink-0"><?= $icon ?></span>
              <div>
                <div class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($title) ?></div>
                <div class="text-gray-500 text-xs"><?= htmlspecialchars($desc) ?></div>
              </div>
            </li>
            <?php endforeach; ?>
          </ul>
          <div class="mt-6 pt-5 border-t border-gray-200">
            <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer block text-center font-bold py-3.5 rounded-xl">
              Encuentra la mejor opción para tu taquería →
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ══ H2: Comprar sin comprometer calidad ══ -->
<section class="py-20" style="background:linear-gradient(180deg,#f8fafc,#f1f5f9)">
  <div class="max-w-4xl mx-auto px-6 text-center reveal">
    <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Equilibrio precio-calidad</p>
    <h2 class="text-3xl md:text-4xl font-extrabold text-gray-900 mb-5">
      Dónde comprar carne barata y buena para negocio sin comprometer calidad
    </h2>
    <p class="text-gray-600 mb-8 max-w-2xl mx-auto leading-relaxed">
      Buscar únicamente el precio más bajo puede generar: baja calidad, mermas, problemas sanitarios y pérdida de clientes.
      La mejor estrategia es trabajar con proveedores que mantengan equilibrio entre precio, calidad y entrega.
    </p>
    <div class="grid md:grid-cols-3 gap-4 mb-10 text-left">
      <?php foreach ([
        ['🔍','Comparar opciones','Cotizaciones y precios de varios proveedores en un solo lugar'],
        ['🔄','Compras recurrentes','Optimiza abastecimiento con pedidos programados automáticos'],
        ['📦','Inventario simple','Simplifica inventarios y manejo administrativo de tu negocio'],
      ] as [$icon,$title,$desc]): ?>
      <div class="bg-white rounded-2xl p-6 border border-gray-100 feat-card">
        <div class="text-3xl mb-3"><?= $icon ?></div>
        <div class="font-bold text-gray-900 mb-1"><?= htmlspecialchars($title) ?></div>
        <div class="text-sm text-gray-500"><?= htmlspecialchars($desc) ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <p class="text-sm text-gray-400 mb-6">Ideal para: taquerías · restaurantes · cocinas industriales</p>
    <a href="<?= BASE_URL ?>planes/registro" class="btn-primary btn-shimmer inline-block font-bold text-base px-10 py-4 rounded-xl">
      Reduce costos comprando carne por mayoreo →
    </a>
  </div>
</section>

<!-- ══ FAQ ══ -->
<section class="bg-white py-20">
  <div class="max-w-3xl mx-auto px-6">
    <div class="text-center mb-10 reveal">
      <p class="text-xs font-bold uppercase tracking-widest text-primary mb-3">Preguntas frecuentes</p>
      <h2 class="text-3xl font-extrabold text-gray-900">Resolvemos tus dudas</h2>
    </div>
    <div class="space-y-4 reveal">
      <?php foreach ([
        ['¿Qué es una distribuidora de carne cerca de mí?',
         'Es un proveedor local o regional que suministra carne para restaurantes, taquerías y negocios de comida con entregas programadas y compra por mayoreo.'],
        ['¿Cómo encontrar un proveedor de carne confiable?',
         'Debes evaluar su cadena de frío, calidad, precios, tiempos de entrega y capacidad de abastecimiento constante.'],
        ['¿Qué beneficios tiene comprar carne por mayoreo consolidado?',
         'Reduce costos, mejora control de inventario y facilita la operación del negocio porque entre varios puntos aumentan el volumen de compra de insumos.'],
        ['¿Dónde comprar carne barata y buena para negocio?',
         'La mejor opción es trabajar con proveedores especializados en atención a restaurantes y taquerías que mantengan calidad constante, certificaciones TIF y equipo de refrigeración óptimo.'],
        ['¿CarniHub vende carne directamente?',
         'Sí, cuenta con una empacadora profesional de carne.'],
        ['¿Se puede comprar carne por caja?',
         'Sí, varios proveedores dentro de CarniHub ofrecen venta por caja y mayoreo.'],
        ['¿Existen proveedores que den crédito?',
         'Sí, puedes hacer tu solicitud de crédito directamente a través de CarniHub.'],
        ['¿CarniHub ayuda a conseguir mejores precios?',
         'Los garantiza por su infraestructura y modelo de venta al mayoreo consolidada.'],
      ] as [$q,$a]): ?>
      <div class="faq-item border border-gray-200 rounded-2xl overflow-hidden">
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
      Tu taquería merece<br>
      <span class="text-gradient">el mejor proveedor</span>
    </h2>
    <p class="text-gray-400 text-lg mb-10 max-w-xl mx-auto leading-relaxed">
      Únete a las taquerías que ya trabajan con proveedores confiables a través de CarniHub.
      Calidad, precio y entrega garantizados.
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
        <p class="text-sm text-gray-500 mt-2">Plataforma de abastecimiento de productos cárnicos para negocios de comida.</p>
      </div>
      <div>
        <h4 class="text-sm font-bold text-gray-400 uppercase tracking-wider mb-4">Soluciones</h4>
        <ul class="space-y-2">
          <li><a href="<?= BASE_URL ?>distribuidora-carne-cerca-de-mi"            class="text-sm text-white/60 font-semibold">→ Distribuidora de carne cerca de mí</a></li>
          <li><a href="<?= BASE_URL ?>carnihub/cortes-de-carne-para-restaurantes" class="text-sm text-gray-500 hover:text-white transition-colors">Cortes de carne para restaurantes</a></li>
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
// ── Navbar scroll ──
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
