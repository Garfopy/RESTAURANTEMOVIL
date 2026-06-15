<?php
// ═══ EARLY LOGGING: Rastrear si /api/auth/token llega a PHP ═══
error_log('[EARLY] REQUEST_URI=' . ($_SERVER['REQUEST_URI'] ?? 'NULL'));
error_log('[EARLY] SCRIPT_NAME=' . ($_SERVER['SCRIPT_NAME'] ?? 'NULL'));
error_log('[EARLY] REQUEST_METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? 'NULL'));
if (strpos($_SERVER['REQUEST_URI'] ?? '', 'api/auth/token') !== false) {
    error_log('[!CRITICAL] /api/auth/token detected — llegó a index.php');
}

/**
 * CapiRest — Front Controller / Router v1.0
 *
 * URL pattern: /{controller}/{action}/{param}
 * Portales:
 *   /restaurante/  → Admin local del restaurante
 *   /rest-{mod}/   → Módulos del restaurante (menú, pedidos, inventario, etc.)
 *   /rest-mesero/  → Portal mesero
 *   /rest-chef/    → Portal chef
 *   /rest-portero/ → Portal portero
 *   /menu/         → Menú público (sin login)
 *   /acceso/       → Login de staff por slug
 */

define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// ── Composer autoload ─────────────────────────────────────────────────────────
if (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// ── Session ───────────────────────────────────────────────────────────────────
// Una cookie de sesión por ROL para que admin + chef + mesero + portero +
// comensal puedan estar logueados simultáneamente en el mismo navegador.
// Sin esto, todos los logins comparten capirest_session y el staff se pierde.
$_earlyPath     = trim($_GET['url'] ?? '', '/');
$_earlySegments = array_values(array_filter(explode('/', $_earlyPath)));
$_earlyCtrl     = strtolower($_earlySegments[0] ?? '');
$_earlyAction   = strtolower($_earlySegments[1] ?? '');

$_roleCookies = [
    'rest-chef'     => '_chef',
    'rest-mesero'   => '_mesero',
    'rest-portero'  => '_portero',
    'menu'          => '_comensal',
    'acceso'        => '_login',
];
$_cookieSuffix = $_roleCookies[$_earlyCtrl] ?? '';

// auth/logoutStaff/{rol} destruye SOLO la cookie de ese rol
if ($_earlyCtrl === 'auth' && $_earlyAction === 'logoutstaff') {
    $_logoutRol = strtolower($_earlySegments[2] ?? '');
    if (in_array($_logoutRol, ['chef', 'mesero', 'portero', 'staff', 'comensal', 'login'], true)) {
        $_cookieSuffix = '_' . $_logoutRol;
    }
}

// Sesión sin expiración automática por inactividad
ini_set('session.gc_maxlifetime', 31536000); // 1 año en segundos
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'samesite' => 'Lax']);
session_name(SESSION_NAME . $_cookieSuffix);
session_start();

// ── Parse URL ─────────────────────────────────────────────────────────────────
$path     = trim($_GET['url'] ?? '', '/');
$segments = array_values(array_filter(explode('/', $path)));

$ctrlSlug = strtolower($segments[0] ?? 'auth');
$action   = $segments[1] ?? 'index';
$param    = $segments[2] ?? null;

// Rutas públicas con slug en URL: /menu/{slug}, /menu/{slug}/ordenar, /menu/{slug}/pagar/{visitaId}, /acceso/{slug}
// Convención esperada por los controllers: param = "slug" o "slug/visitaId" (concat de segmentos restantes)
if (in_array($ctrlSlug, ['menu', 'acceso'], true)) {
    $knownActions = ['index','ordenar','pagar','confirmarPago','confirmacion','login','staff',
                     'llamarMesero','cancelarPedido','estadoPedido','actualizarPropina','generarTicket',
                     'paypalCrear','paypalRetorno','paypalCancelar','entrarComensal',
                     'scanPortero','registrarSalidaPublica','checkSalida','gracias','stripeIntent','stripeRetorno',
                     'reservar','guardarReserva','cancelarReserva','mesasDisponibles'];
    if ($action !== '' && in_array($action, $knownActions, true)) {
        // Forma /menu/{accion}/{slug}/{...} — concatenar segmentos a partir del 2
        $rest  = array_slice($segments, 2);
        $param = $rest ? implode('/', $rest) : null;
    } else {
        // Forma /menu/{slug}/{accion?}/{...} — el slug viene primero
        $slug  = $action;
        $sub   = $segments[2] ?? '';
        if ($sub && in_array($sub, $knownActions, true)) {
            $action = $sub;
            $rest   = array_slice($segments, 3);
            $param  = $slug . ($rest ? '/' . implode('/', $rest) : '');
        } else {
            // /menu/{slug} → index del slug
            $action = 'index';
            $param  = $slug;
        }
    }
}

// ── API Routing: concatenar segmentos extra como param ─────────────────────
// /api/auth/login → ctrl=api, action=auth, param=login
// /api/admin/promotions/123/deactivate → ctrl=api, action=admin, param=promotions/123/deactivate
if ($ctrlSlug === 'api' && $action !== 'index') {
    $rest  = array_slice($segments, 1);
    $action = array_shift($rest);
    $param  = $rest ? implode('/', $rest) : null;
}
// ── Ruta raíz → landing AMARE ────────────────────────────────────────────────
if ($path === '') {
    $ctrlSlug = 'landing';
    $action   = 'index';
}
// ── Route map: URL slug → Controller class ────────────────────────────────────
$routes = [
    // Landing AMARE (público)
    'landing'       => 'LandingController',
    // Auth (público)
    'auth'          => 'AuthController',
    // Portal admin del restaurante
    'restaurante'   => 'RestauranteController',
    'rest-config'   => 'RestConfigController',
    'rest-mesa'     => 'RestMesaController',
    'rest-menu'     => 'RestMenuController',
    'rest-inventario' => 'RestInventarioController',
    'rest-mermas'   => 'RestMermasController',
    'rest-pedido'   => 'RestPedidoController',
    'rest-finanzas' => 'RestFinanzasController',
    'rest-cliente'  => 'RestClienteController',
    'rest-reserva'  => 'RestReservaController',
    'rest-promocion'=> 'RestPromocionController',
    'rest-ticket'   => 'RestTicketController',
    // Portales staff
    'rest-mesero'   => 'RestMeseroController',
    'rest-chef'     => 'RestChefController',
    'rest-portero'  => 'RestPorteroController',
    'rest-staff'    => 'RestStaffController',
    'rest-propinas' => 'RestPropinaController',
    // Menú público (sin login)
    'menu'          => 'RestPublicoController',
    // Login de staff por slug de restaurante
    'acceso'        => 'StaffAccesoController',
    // Webhook entrante de CarniHub (sin login)
    'carnihub'      => 'CarnihubController',
    // API v1 REST — autenticación por Bearer token (CapiRest, etc.)
    'api'           => 'ApiController',
];

// ── Auth guard ────────────────────────────────────────────────────────────────
$publicPaths = [
    'landing/index',
    'auth/login',
    'auth/dologin',
    'auth/index',
    'auth/verificar',
    'auth/forgot',
    'auth/sendreset',
    'auth/reset',
    'auth/doreset',
    // Menú público del restaurante
    'menu/index',
    'menu/ordenar',
    'menu/pagar',
    'menu/confirmarPago',
    'menu/confirmacion',
    'menu/llamarMesero',
    'menu/cancelarPedido',
    'menu/estadoPedido',
    'menu/actualizarPropina',
    'menu/paypalCrear',
    'menu/paypalRetorno',
    'menu/paypalCancelar',
    // Verificación pública de salida (QR del portero)
    'menu/scanPortero',
    'menu/registrarSalidaPublica',
    'menu/gracias',
    // Acceso staff (login por slug de restaurante)
    'acceso/index',
    'acceso/login',
    'acceso/entrarComensal',
    // Webhook entrante de CarniHub (autenticación por Bearer, no por sesión)
    'carnihub/webhook',
// API v1 REST (autenticación por Bearer token, sin sesión PHP)
    'api/v1',
    'api/v1/ping',
    'api/v1/pedidos',
    'api/v1/productos',
    'api/v1/promociones',
    // Admin API (JWT Bearer — el guard de sesión no aplica, el controller maneja auth)
    'api/auth',
    'api/auth/login',
    'api/auth/token',
    'api/admin',
    'api/admin/users',
    'api/admin/promotions',
    'api/branches',
    'api/branches/config',
    // API genérica (pedidos, productos, etc.)
    'api/pedidos',
    'api/pedidosconfirmados',
    'api/productos',
    'api/precios',
    'api/chat',
    'api/tracking',
    'api/guardarposicion',
    'api/historialtracking',
    'api/actualizartracking',
    'api/iniciartracking',
    'api/finalizartracking',
    'api/planes',
    'api/staff',
    'api/staff/pedidos',
    'api/staff/mesas',
    'api/comensales',
];

$currentPath = strtolower($ctrlSlug . '/' . $action);

// DEBUG: Log para /api/auth/token
if (strpos($_SERVER['REQUEST_URI'] ?? '', 'api/auth/token') !== false) {
    error_log('[DEBUG] /api/auth/token request detected');
    error_log('[DEBUG] ctrlSlug=' . $ctrlSlug . ', action=' . $action . ', param=' . ($param ?? 'NULL'));
    error_log('[DEBUG] currentPath=' . $currentPath);
    error_log('[DEBUG] in_array check: ' . (in_array($currentPath, $publicPaths, true) ? 'TRUE' : 'FALSE'));
}

    if (
        !isset($_SESSION['usuario']) &&
        $ctrlSlug !== 'api' &&
        $ctrlSlug !== 'menu' &&
        $ctrlSlug !== 'acceso' &&
        !in_array($currentPath, $publicPaths, true)
    ) {
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }

// ── Redirect root to correct portal ──────────────────────────────────────────
if ($ctrlSlug === 'auth' && $action === 'index' && isset($_SESSION['usuario'])) {
    $rol = $_SESSION['usuario']['rol_slug'] ?? '';
    if (in_array($rol, ['admin_local', 'superadmin'], true)) {
        header('Location: ' . BASE_URL . 'restaurante/seleccionar'); exit;
    }
    if ($rol === 'mesero') {
        header('Location: ' . BASE_URL . 'rest-mesero/inicio'); exit;
    }
    if ($rol === 'chef') {
        header('Location: ' . BASE_URL . 'rest-chef/inicio'); exit;
    }
    if ($rol === 'portero') {
        header('Location: ' . BASE_URL . 'rest-portero/inicio'); exit;
    }
    header('Location: ' . BASE_URL . 'auth/login'); exit;
}

// ── Dispatch ──────────────────────────────────────────────────────────────────
if (!array_key_exists($ctrlSlug, $routes)) {
    http_response_code(404);
    echo '<h1>404 — Página no encontrada</h1>';
    exit;
}

$controllerClass = $routes[$ctrlSlug];
$controllerFile  = ROOT_PATH . '/app/controllers/' . $controllerClass . '.php';

if (!file_exists($controllerFile)) {
    http_response_code(501);
    echo '<h1>Módulo en construcción: ' . htmlspecialchars($controllerClass) . '</h1>';
    exit;
}

// ── Autoload models ───────────────────────────────────────────────────────────
foreach (glob(ROOT_PATH . '/app/models/*.php') as $model) {
    require_once $model;
}
foreach (glob(ROOT_PATH . '/app/services/*.php') as $service) {
    require_once $service;
}
foreach (glob(ROOT_PATH . '/app/helpers/*.php') as $helper) {
    require_once $helper;
}

require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once $controllerFile;

$controller = new $controllerClass();

if (!method_exists($controller, $action)) {
    http_response_code(404);
    echo '<h1>Acción no encontrada: ' . htmlspecialchars($action) . '</h1>';
    exit;
}

$controller->$action($param);
