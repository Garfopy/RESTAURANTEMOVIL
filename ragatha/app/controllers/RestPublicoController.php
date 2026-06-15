<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestPublicoController extends BaseController
{
    private RestauranteModel    $restModel;
    private RestMenuModel        $menuModel;
    private RestPedidoModel      $pedidoModel;
    private RestVisitaModel      $visitaModel;
    private RestTicketModel      $ticketModel;
    private RestMesaModel        $mesaModel;
    private RestInventarioModel  $inventarioModel;

    public function __construct()
    {
        parent::__construct();
        $this->restModel       = new RestauranteModel();
        $this->menuModel       = new RestMenuModel();
        $this->pedidoModel     = new RestPedidoModel();
        $this->visitaModel     = new RestVisitaModel();
        $this->ticketModel     = new RestTicketModel();
        $this->mesaModel       = new RestMesaModel();
        $this->inventarioModel = new RestInventarioModel();
    }

    /**
     * Lee una clave de Stripe desde la variable de entorno o desde global_settings.
     * @param string $which 'public' | 'secret'
     */
    private function getStripeKey(string $which): string
    {
        $const = ($which === 'secret') ? STRIPE_SECRET_KEY : STRIPE_PUBLIC_KEY;
        if (!empty($const)) return $const;
        try {
            require_once ROOT_PATH . '/app/models/ConfigModel.php';
            $cfg = new ConfigModel();
            return $cfg->get(($which === 'secret') ? 'stripe_secret_key' : 'stripe_public_key', '');
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Obtiene los métodos de pago habilitados para comensales desde global_settings.
     * @return string[]  e.g. ['efectivo','tarjeta','transferencia','paypal']
     */
    private function getMetodosPago(): array
    {
        $defaults = ['efectivo','tarjeta','transferencia','paypal'];
        try {
            require_once ROOT_PATH . '/app/models/ConfigModel.php';
            $cfg = new ConfigModel();
            $val = $cfg->get('metodos_pago_habilitados', '');
            if (!empty($val)) {
                $arr = json_decode($val, true);
                if (is_array($arr) && !empty($arr)) return $arr;
            }
        } catch (\Throwable $e) {}
        return $defaults;
    }

    // /menu/{slug}  o  /menu/{slug}?mesa={qr_codigo}
    public function index(?string $slug = null): void
    {
        $slug = trim((string)($slug ?? ''));

        // Si no viene slug y hay un staff/admin logueado con restaurante activo,
        // redirigir al menú público de ese restaurante para evitar el 404.
        if ($slug === '' && isset($_SESSION['usuario']) && !empty($_SESSION['restaurante_activo_id'])) {
            $activo = $this->restModel->find((int)$_SESSION['restaurante_activo_id']);
            if ($activo && !empty($activo['slug'])) {
                $this->redirect('menu/' . $activo['slug']);
                return;
            }
        }

        $restaurante = $slug !== '' ? $this->restModel->getBySlug($slug) : null;
        if (!$restaurante) {
            http_response_code(404);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">';
            echo '<title>Restaurante no encontrado</title>';
            echo '<style>body{font-family:Inter,system-ui,sans-serif;background:#F9FAFB;color:#111827;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:24px}';
            echo '.box{max-width:420px;text-align:center;background:#fff;border:1px solid #E5E7EB;border-radius:14px;padding:32px 28px;box-shadow:0 10px 30px rgba(0,0,0,.06)}';
            echo 'h1{font-size:1.25rem;margin:0 0 8px}p{color:#6B7280;font-size:.92rem;margin:0 0 18px}';
            echo 'a{display:inline-block;background:#1D4ED8;color:#fff;text-decoration:none;padding:9px 18px;border-radius:8px;font-weight:600;font-size:.88rem}</style>';
            echo '</head><body><div class="box">';
            echo '<div style="font-size:2.4rem;margin-bottom:8px">🍽️</div>';
            echo '<h1>Restaurante no encontrado</h1>';
            echo '<p>La dirección que ingresaste no corresponde a un restaurante activo. Verifica el enlace o regresa al inicio.</p>';
            echo '<a href="' . BASE_URL . '">Ir al inicio</a>';
            echo '</div></body></html>';
            return;
        }

        // El menú público SIEMPRE es visible (visual). El login del comensal
        // (`requiere_login_comensal`) sólo se exige al ORDENAR, no al mirar el menú.
        // Pasamos la bandera a la vista para que decida si mostrar formulario o
        // redirigir al checkout/login al hacer click en "Ordenar".
        $requiereLoginComensal = (int)($restaurante['requiere_login_comensal'] ?? 0);

        $categorias = $this->menuModel->getCategorias((int)$restaurante['id'], true);
        $platillos  = $this->menuModel->getPlatillosDisponibles((int)$restaurante['id']);

        // ── Deduplicar categorías por nombre y normalizar IDs de platillos ──
        // Si hay dos filas con el mismo nombre (ej. dos "Bebidas"), conservar la
        // primera como canónica y reasignar los platillos que apunten a las duplicadas.
        $canonicalByName = [];   // nombre_lower → id canónico
        $idMap           = [];   // id_duplicado  → id canónico
        $categoriasUnicas = [];
        foreach ($categorias as $cat) {
            $key = mb_strtolower(trim($cat['nombre']));
            if (!isset($canonicalByName[$key])) {
                $canonicalByName[$key] = (int)$cat['id'];
                $categoriasUnicas[]    = $cat;
            } else {
                $idMap[(int)$cat['id']] = $canonicalByName[$key];
            }
        }
        $categorias = $categoriasUnicas;
        if (!empty($idMap)) {
            foreach ($platillos as &$p) {
                $cid = (int)($p['categoria_id'] ?? 0);
                if (isset($idMap[$cid])) {
                    $p['categoria_id'] = $idMap[$cid];
                }
            }
            unset($p);
        }
        // ────────────────────────────────────────────────────────────────────
        $recetaIngredientes = [];
        try {
            $recetaIngredientes = $this->menuModel->getIngredientesPorRestaurante((int)$restaurante['id']);
        } catch (\Throwable $e) {}

        $mesa = null;
        $mesaQr = $this->get('mesa');
        if ($mesaQr) {
            $mesa = (new RestMesaModel())->getByQr($mesaQr);
        }

        // Resolver mesero que atiende la mesa según zona y turno del día
        $meseroAtiende = null;
        if ($mesa && !empty($mesa['zona_id'])) {
            try {
                $stmtM = Database::getInstance()->prepare(
                    "SELECT u.nombre FROM rest_mesero_turno mt
                     JOIN usuarios u ON u.id = mt.usuario_id
                     WHERE mt.restaurante_id = ? AND mt.zona_id = ?
                       AND mt.turno_fecha = CURDATE() AND mt.activo = 1
                     LIMIT 1"
                );
                $stmtM->execute([(int)$restaurante['id'], (int)$mesa['zona_id']]);
                $rowM = $stmtM->fetch(PDO::FETCH_ASSOC);
                $meseroAtiende = $rowM ? $rowM['nombre'] : null;
            } catch (\Throwable $e) {}
        }

        // Recuperar visita previa de cookie (para agregar más pedidos a la misma visita)
        $cookieName = 'visita_' . $restaurante['id'];
        $visitaId   = (int)($_COOKIE[$cookieName] ?? 0);
        if ($visitaId) {
            $visita = $this->visitaModel->find($visitaId);
            // Si la visita ya terminó, ignorar cookie
            if (!$visita || in_array($visita['estado'], ['pagada','cancelada'])) {
                $visitaId = 0;
                setcookie($cookieName, '', ['expires' => time() - 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
            }
            // Si no hay mesa en la URL pero la visita activa sí tiene mesa, recuperarla
            elseif (!$mesa && !empty($visita['mesa_id'])) {
                $mesa = (new RestMesaModel())->find((int)$visita['mesa_id']);
            }
        }

        // Comensal logueado
        $comensal = null;
        $comensalCookie = $_COOKIE['comensal_' . $restaurante['id']] ?? null;
        if ($comensalCookie) {
            $decoded = json_decode($comensalCookie, true);
            if (is_array($decoded) && !empty($decoded['id'])) {
                $comensal = [
                    'id'     => (int)$decoded['id'],
                    'nombre' => $decoded['nombre'] ?? '',
                    'email'  => $decoded['email']  ?? '',
                ];
            }
        }

        // ── Lógica de modo (ordenar o solo ver) ──────────────────────────────
        // El menú público en URL directa (/menu/{slug}) es SIEMPRE visual (solo lectura).
        // El ordering SOLO es posible cuando el cliente llegó vía QR de mesa (?mesa=…).
        //
        //   Sin ?mesa  → visual siempre (no se puede ordenar, independiente del toggle)
        //   Con ?mesa  + toggle OFF → puede ordenar sin necesidad de login
        //   Con ?mesa  + toggle ON  → debe identificarse (email) antes de poder ordenar
        $tieneMesa = ($mesa !== null);

        if ($tieneMesa && $requiereLoginComensal && !$comensal) {
            // Preservar el QR en la URL de retorno para volver al modo interactivo tras login
            $returnUrl = 'menu/' . $restaurante['slug'] . '?mesa=' . urlencode($mesaQr ?? '');
            $this->redirect('acceso/' . $restaurante['slug'] . '?return=' . urlencode($returnUrl));
            return;
        }

        $puedeOrdenar = $tieneMesa && (!$requiereLoginComensal || (bool)$comensal);

        $pageTitle = $restaurante['nombre'];
        $this->render('publico/menu/index', compact('restaurante','categorias','platillos','recetaIngredientes','mesa','visitaId','meseroAtiende','pageTitle','requiereLoginComensal','comensal','puedeOrdenar'));
    }

    public function ordenar(?string $slug = null): void
    {
        if (!$this->isPost()) $this->redirect('menu/' . $slug);

        $restaurante = $this->restModel->getBySlug($slug ?? '');
        if (!$restaurante) { http_response_code(404); exit; }

        $restauranteId = (int)$restaurante['id'];

        $mesaQr        = $this->post('mesa_qr');
        $mesa          = $mesaQr ? (new RestMesaModel())->getByQr($mesaQr) : null;

        // Sin mesa → menú visual, no acepta pedidos
        if (!$mesa) {
            $this->redirect('menu/' . $slug);
            return;
        }

        // Resolver mesero asignado a la zona de esta mesa hoy
        $meseroId = null;
        if (!empty($mesa['zona_id'])) {
            try {
                $stmtM = Database::getInstance()->prepare(
                    "SELECT usuario_id FROM rest_mesero_turno
                     WHERE restaurante_id = ? AND zona_id = ?
                       AND turno_fecha = CURDATE() AND activo = 1
                     LIMIT 1"
                );
                $stmtM->execute([$restauranteId, (int)$mesa['zona_id']]);
                $rowM = $stmtM->fetch(PDO::FETCH_ASSOC);
                $meseroId = $rowM ? (int)$rowM['usuario_id'] : null;
            } catch (\Throwable $e) {}
        }

        $requiereLoginComensal = (int)($restaurante['requiere_login_comensal'] ?? 0);
        // Con mesa + toggle ON → comensal debe estar identificado
        if ($requiereLoginComensal && empty($_COOKIE['comensal_' . $restauranteId])) {
            $returnUrl = 'menu/' . $restaurante['slug'] . '?mesa=' . urlencode($mesaQr);
            $this->redirect('acceso/' . $restaurante['slug'] . '?return=' . urlencode($returnUrl));
            return;
        }
        $visitaId      = $this->post('visita_id') ?: null;

        // Validar que la visita pertenezca a este restaurante
        if ($visitaId) {
            $visitaExist = $this->visitaModel->find((int)$visitaId);
            if (!$visitaExist || (int)$visitaExist['restaurante_id'] !== $restauranteId
                || in_array($visitaExist['estado'], ['pagada','cancelada'])) {
                $visitaId = null;
            }
        }

        // Comensal logueado (opcional)
        $comensalId = null;
        $comensalCookie = $_COOKIE['comensal_' . $restauranteId] ?? null;
        if ($comensalCookie) {
            $decoded = json_decode($comensalCookie, true);
            if (is_array($decoded) && !empty($decoded['id'])) {
                $comensalId = (int)$decoded['id'];
            }
        }

        // Crear visita si no existe
        if (!$visitaId) {
            $visitaId = $this->visitaModel->crear(
                $restauranteId,
                $mesa ? (int)$mesa['id'] : null,
                $comensalId
            );
            // Guardar en cookie por 4 horas
            $cookieName = 'visita_' . $restauranteId;
            setcookie($cookieName, (string)$visitaId, ['expires' => time() + 4 * 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }

        $platillosIds = $this->post('platillo_id', []);
        $cantidades   = $this->post('cantidad', []);
        $exclusiones  = $this->post('exclusiones', []);  // keyed by platillo_id
        $notasItem    = $this->post('notas_item', []);   // keyed by platillo_id
        $extrasPost   = $this->post('extras', []);        // keyed by platillo_id → JSON string

        $items = [];
        foreach ($platillosIds as $k => $platilloId) {
            if (!$platilloId || empty($cantidades[$k])) continue;
            $platillo = $this->menuModel->find((int)$platilloId);
            if (!$platillo || !$platillo['disponible']) continue;
            $cant = max(1, (int)$cantidades[$k]);
            $excl = isset($exclusiones[$platilloId]) && is_array($exclusiones[$platilloId])
                ? implode(', ', array_filter(array_map('trim', $exclusiones[$platilloId])))
                : null;
            $nota = isset($notasItem[$platilloId]) ? trim($notasItem[$platilloId]) : null;

            // Extras: porción adicional de guarniciones (con costo)
            $extrasJson = null;
            $extrasCoste = 0.0;
            if (!empty($extrasPost[$platilloId])) {
                $extrasDecoded = json_decode($extrasPost[$platilloId], true);
                if (is_array($extrasDecoded)) {
                    // Cargar precios reales desde BD para no confiar en el cliente
                    $preciosExtrasDb = [];
                    $receta = $this->menuModel->getReceta((int)$platilloId);
                    if ($receta) {
                        foreach ($this->menuModel->getIngredientesReceta((int)$receta['id']) as $ri) {
                            $preciosExtrasDb[(int)$ri['ingrediente_id']] = (float)$ri['precio_extra'];
                        }
                    }
                    $extrasValidos = [];
                    foreach ($extrasDecoded as $e) {
                        if (!isset($e['ingrediente_id'], $e['nombre'], $e['cantidad'])) continue;
                        if ((int)$e['cantidad'] <= 0) continue;
                        $ingId = (int)$e['ingrediente_id'];
                        if (!array_key_exists($ingId, $preciosExtrasDb)) continue; // ingrediente no en receta
                        $e['precio_extra'] = $preciosExtrasDb[$ingId]; // precio siempre de BD
                        $extrasValidos[] = $e;
                    }
                    if ($extrasValidos) {
                        $extrasJson  = json_encode($extrasValidos);
                        $extrasCoste = array_sum(array_map(
                            fn($e) => (float)$e['precio_extra'] * (int)$e['cantidad'],
                            $extrasValidos
                        ));
                    }
                }
            }

            $precioUnit = (float)$platillo['precio'] + $extrasCoste;
            $items[] = [
                'platillo_id' => (int)$platilloId,
                'cantidad'    => $cant,
                'precio_unit' => $precioUnit,
                'subtotal'    => $precioUnit * $cant,
                'notas'       => $nota ?: null,
                'exclusiones' => $excl,
                'extras'      => $extrasJson,
            ];
        }

        if (empty($items)) {
            $this->redirect('menu/' . $slug . ($mesaQr ? '?mesa=' . urlencode($mesaQr) : ''));
        }

        $pedidoId = $this->pedidoModel->crear([
            'restaurante_id' => $restauranteId,
            'mesa_id'        => $mesa ? (int)$mesa['id'] : null,
            'visita_id'      => $visitaId,
            'mesero_id'      => $meseroId,
        ], $items);

        // Stock se descuenta cuando la cocina marca el ítem como "en_preparacion"
        // (RestChefController::marcarPreparacion) — no al hacer el pedido.
        $this->visitaModel->actualizarTotales((int)$visitaId);

        // Marcar mesa como ocupada
        if ($mesa) {
            $this->mesaModel->cambiarEstado((int)$mesa['id'], 'ocupada');
        }

        $this->redirect('menu/' . $slug . '/confirmacion/' . $visitaId);
    }

    public function confirmacion(?string $slug = null): void
    {
        $parts       = explode('/', $slug ?? '');
        $realSlug    = $parts[0] ?? '';
        $visitaId    = (int)($parts[1] ?? 0);
        $restaurante = $this->restModel->getBySlug($realSlug);
        $visita      = $this->visitaModel->find($visitaId);
        $pedidosBase = $this->pedidoModel->getByVisita($visitaId);

        // Enriquecer pedidos con sus ítems para la vista
        $pedidos = [];
        foreach ($pedidosBase as $ped) {
            $detalle = $this->pedidoModel->getConItems((int)$ped['id']);
            $pedidos[] = array_merge($ped, ['items' => $detalle['items'] ?? []]);
        }
        $ticket = $this->ticketModel->getByVisita($visitaId);
        // Recalcular si el ticket existe y no está pagado, por si se añadieron pedidos después
        if ($ticket && ($ticket['estado'] ?? '') !== 'pagado') {
            $this->ticketModel->recalcularSubtotal((int)$ticket['id'], $visitaId);
            $ticket = $this->ticketModel->find((int)$ticket['id']);
        }
        $pageTitle   = '¡Pedido recibido!';
        $this->render('publico/menu/confirmacion', compact('restaurante','visita','pedidos','ticket','pageTitle'));
    }

    // POST /menu/{slug}/generarTicket/{visitaId} — consolida ticket sin pagar, devuelve JSON
    public function generarTicket(?string $slug = null): void
    {
        $parts       = explode('/', $slug ?? '');
        $realSlug    = $parts[0] ?? '';
        $visitaId    = (int)($parts[1] ?? 0);
        $restaurante = $this->restModel->getBySlug($realSlug);
        $visita      = $this->visitaModel->find($visitaId);

        if (!$restaurante || !$visita
            || (int)$visita['restaurante_id'] !== (int)$restaurante['id']) {
            $this->json(['ok' => false, 'error' => 'Visita no válida']);
            return;
        }

        $ticket = $this->ticketModel->getByVisita($visitaId);
        if (!$ticket) {
            $ticketId = $this->ticketModel->consolidar($visitaId, 0);
            $ticket   = $this->ticketModel->find($ticketId);
        } elseif (($ticket['estado'] ?? '') === 'pendiente') {
            // Recalcular por si el comensal agregó más pedidos después de generar el ticket
            $this->ticketModel->recalcularSubtotal((int)$ticket['id'], $visitaId);
            $ticket = $this->ticketModel->find((int)$ticket['id']);
        }

        if (!$ticket) {
            $this->json(['ok' => false, 'error' => 'No hay pedidos para generar ticket']);
            return;
        }

        $pedidos = $this->pedidoModel->getByVisita($visitaId);
        $items   = [];
        foreach ($pedidos as $p) {
            $det = $this->pedidoModel->getConItems((int)$p['id']);
            foreach ($det['items'] ?? [] as $it) {
                if ($it['estado'] !== 'cancelado') {
                    $items[] = [
                        'nombre'   => $it['platillo_nombre'] ?? $it['nombre'] ?? '',
                        'cantidad' => (int)$it['cantidad'],
                        'subtotal' => (float)($it['subtotal'] ?? 0),
                    ];
                }
            }
        }

        $this->json([
            'ok'       => true,
            'folio'    => $ticket['folio']    ?? '',
            'subtotal' => (float)($ticket['subtotal'] ?? 0),
            'propina'  => (float)($ticket['propina']  ?? 0),
            'total'    => (float)($ticket['total']    ?? 0),
            'estado'   => $ticket['estado']   ?? '',
            'items'    => $items,
            'qr_code'  => $visita['qr_code']  ?? '',
        ]);
    }

    public function pagar(?string $slug = null): void
    {
        $parts       = explode('/', $slug ?? '');
        $realSlug    = $parts[0] ?? '';
        $visitaId    = (int)($parts[1] ?? 0);
        $restaurante = $this->restModel->getBySlug($realSlug);
        $visita      = $this->visitaModel->find($visitaId);
        $ticket      = $this->ticketModel->getByVisita($visitaId);
        if (!$ticket) {
            $propina  = (float)$this->get('propina', 0);
            $ticketId = $this->ticketModel->consolidar($visitaId, $propina);
            $ticket   = $this->ticketModel->find($ticketId);
        } elseif (($ticket['estado'] ?? '') === 'pendiente') {
            // Recalcular por si el comensal agregó más pedidos después de abrir el ticket
            $this->ticketModel->recalcularSubtotal((int)$ticket['id'], $visitaId);
            $ticket = $this->ticketModel->find((int)$ticket['id']);
        }

        // Cambiar mesa a estado 'pagando'
        if ($visita && !empty($visita['mesa_id']) && ($ticket['estado'] ?? '') !== 'pagado') {
            $this->mesaModel->cambiarEstado((int)$visita['mesa_id'], 'pagando');
        }

        // Cargar ítems de todos los pedidos para la vista dividir-cuenta
        $pedidos   = $this->pedidoModel->getByVisita($visitaId);
        $todoItems = [];
        foreach ($pedidos as $ped) {
            $detalle = $this->pedidoModel->getConItems((int)$ped['id']);
            if ($detalle && !empty($detalle['items'])) {
                foreach ($detalle['items'] as $item) {
                    if ($item['estado'] !== 'cancelado') {
                        $todoItems[] = $item;
                    }
                }
            }
        }

        // Si el ticket ya fue pagado, redirigir a confirmación (evita pago duplicado)
        if (($ticket['estado'] ?? '') === 'pagado') {
            $this->redirect('menu/' . $realSlug . '/confirmacion/' . $visitaId . '?pagado=1');
            return;
        }

        $mesaQr    = null;
        if (!empty($visita['mesa_id'])) {
            $mesaObj = $this->mesaModel->find((int)$visita['mesa_id']);
            $mesaQr  = $mesaObj['qr_code'] ?? null;
        }

        $stripePk         = $this->getStripeKey('public');
        $metodosHabilitados = $this->getMetodosPago();
        $pageTitle = 'Pagar cuenta';
        $this->render('publico/menu/pagar', compact('restaurante','ticket','todoItems','visita','visitaId','mesaQr','pageTitle','stripePk','metodosHabilitados'));
    }

    // POST /menu/{slug}/confirmarPago/{ticketId} — endpoint PÚBLICO (sin login)
    public function confirmarPago(?string $slug = null): void
    {
        if (!$this->isPost()) $this->redirect('menu/' . $slug);

        $parts    = explode('/', $slug ?? '');
        $realSlug = $parts[0] ?? '';
        $ticketId = (int)($parts[1] ?? 0);

        $restaurante = $this->restModel->getBySlug($realSlug);
        $ticket      = $this->ticketModel->find($ticketId);
        if (!$restaurante || !$ticket || (int)$ticket['restaurante_id'] !== (int)$restaurante['id']) {
            http_response_code(404);
            die('<h1>Ticket no válido</h1>');
        }

        // Si ya fue pagado, redirigir a confirmación (evita cobro duplicado)
        if (($ticket['estado'] ?? '') === 'pagado') {
            $this->redirect('menu/' . $realSlug . '/confirmacion/' . $ticket['visita_id'] . '?pagado=1');
            return;
        }

        $metodo  = $this->post('metodo_pago', 'efectivo');
        $metodo  = in_array($metodo, ['efectivo','tarjeta','transferencia','paypal'], true) ? $metodo : 'efectivo';
        $propina = max(0.0, (float)$this->post('propina', 0));

        // Si propina cambió, recalcular total
        if ($propina !== (float)$ticket['propina']) {
            $this->ticketModel->actualizarPropina($ticketId, $propina);
        }

        if ($metodo === 'paypal') {
            // Redirigir a PayPal Checkout
            $total    = (float)($ticket['subtotal'] ?? 0) + $propina;
            $returnUrl = BASE_URL . 'menu/' . $realSlug . '/paypalRetorno/' . $ticketId . '/' . urlencode($propina);
            $cancelUrl = BASE_URL . 'menu/' . $realSlug . '/pagar/' . $ticket['visita_id'];
            try {
                $paypal = new PayPalOrdenService();
                $orden  = $paypal->crearOrden($total, 'MXN', $returnUrl, $cancelUrl, $ticket['folio']);
                // Guardar ticket en sesión para verificación al retorno
                $_SESSION['paypal_ticket_' . $ticketId] = [
                    'ticket_id' => $ticketId,
                    'propina'   => $propina,
                    'slug'      => $realSlug,
                ];
                header('Location: ' . $orden['approvalUrl']);
                exit;
            } catch (\Throwable $e) {
                // Si falla PayPal, regresar a pagar con error
                $_SESSION['flash_error'] = 'Error al conectar con PayPal. Elige otro método.';
                $this->redirect('menu/' . $realSlug . '/pagar/' . $ticket['visita_id']);
            }
        }

        if ($metodo === 'tarjeta') {
            try {
                $stripeKey = $this->getStripeKey('secret');
                if (empty($stripeKey)) throw new \RuntimeException('Stripe no configurado');
                \Stripe\Stripe::setApiKey($stripeKey);

                $total    = (float)($ticket['total'] ?? 0);
                $centavos = (int)round($total * 100);

                error_log('[Stripe:confirmarPago] key=' . (empty($stripeKey) ? 'VACÍA' : 'OK') . ' | cents=' . $centavos . ' | ticket=' . $ticketId . ' | slug=' . $realSlug);

                $session = \Stripe\Checkout\Session::create([
                    'payment_method_types' => ['card'],
                    'mode'                 => 'payment',
                    'line_items'           => [[
                        'price_data' => [
                            'currency'     => 'mxn',
                            'unit_amount'  => max(1000, $centavos),
                            'product_data' => [
                                'name' => ($restaurante['nombre'] ?? 'Cuenta') . ' — ' . ($ticket['folio'] ?? ''),
                            ],
                        ],
                        'quantity' => 1,
                    ]],
                    'success_url' => BASE_URL . 'menu/stripeRetorno/' . $realSlug . '/' . $ticketId . '?cs={CHECKOUT_SESSION_ID}',
                    'cancel_url'  => BASE_URL . 'menu/' . $realSlug . '/pagar/' . $ticket['visita_id'],
                    'metadata'    => [
                        'ticket_id' => (string)$ticketId,
                        'folio'     => $ticket['folio'] ?? '',
                    ],
                ]);

                $_SESSION['stripe_checkout_' . $ticketId] = [
                    'session_id' => $session->id,
                    'ticket_id'  => $ticketId,
                    'slug'       => $realSlug,
                ];

                header('Location: ' . $session->url);
                exit;
            } catch (\Throwable $e) {
                error_log('[Stripe:confirmarPago] ERROR: ' . $e->getMessage() . ' | class=' . get_class($e) . ' | ticket=' . $ticketId);
                $_SESSION['flash_error'] = 'Error al iniciar pago con tarjeta. Elige otro método.';
                $this->redirect('menu/' . $realSlug . '/pagar/' . $ticket['visita_id']);
                return;
            }
        }

        $this->ticketModel->marcarPagado($ticketId, $metodo, null);
        $this->visitaModel->marcarPagada((int)$ticket['visita_id']);
        // La mesa se libera cuando el portero escanea el QR de salida, no aquí.

        $this->redirect('menu/' . $realSlug . '/confirmacion/' . $ticket['visita_id'] . '?pagado=1');
    }

    // GET /menu/{slug}/paypalRetorno/{ticketId}/{propina}
    public function paypalRetorno(?string $slug = null): void
    {
        $parts     = explode('/', $slug ?? '');
        $realSlug  = $parts[0] ?? '';
        $ticketId  = (int)($parts[1] ?? 0);
        $propina   = (float)($parts[2] ?? 0);
        $orderId   = $this->get('token') ?: $this->get('orderId');

        $restaurante = $this->restModel->getBySlug($realSlug);
        $ticket      = $this->ticketModel->find($ticketId);

        if (!$restaurante || !$ticket || !$orderId
            || (int)$ticket['restaurante_id'] !== (int)$restaurante['id']
            || ($ticket['estado'] ?? '') === 'pagado') {
            $this->redirect('menu/' . $realSlug);
        }

        // Verificar token de sesión anti-replay
        $sesKey = 'paypal_ticket_' . $ticketId;
        if (empty($_SESSION[$sesKey]) || $_SESSION[$sesKey]['slug'] !== $realSlug) {
            $_SESSION['flash_error'] = 'Sesión de pago expirada. Intenta de nuevo.';
            $this->redirect('menu/' . $realSlug . '/pagar/' . $ticket['visita_id']);
        }
        unset($_SESSION[$sesKey]);

        try {
            $paypal  = new PayPalOrdenService();
            $capture = $paypal->capturarOrden($orderId);
            $captureStatus = $capture['status'] ?? '';
            if ($captureStatus !== 'COMPLETED') {
                throw new \RuntimeException('PayPal capture status: ' . $captureStatus);
            }

            if ($propina !== (float)$ticket['propina']) {
                $this->ticketModel->actualizarPropina($ticketId, $propina);
            }
            $this->ticketModel->marcarPagado($ticketId, 'paypal', $orderId);
            $this->visitaModel->marcarPagada((int)$ticket['visita_id']);
            // La mesa se libera cuando el portero escanea el QR de salida, no aquí.
        } catch (\Throwable $e) {
            $_SESSION['flash_error'] = 'No se pudo confirmar el pago con PayPal. Contacta al staff.';
        }

        $this->redirect('menu/' . $realSlug . '/confirmacion/' . $ticket['visita_id'] . '?pagado=1');
    }

    // GET /menu/{slug}/paypalCancelar/{ticketId}
    public function paypalCancelar(?string $slug = null): void
    {
        $parts     = explode('/', $slug ?? '');
        $realSlug  = $parts[0] ?? '';
        $ticketId  = (int)($parts[1] ?? 0);
        $ticket    = $this->ticketModel->find($ticketId);

        // Limpiar sesión PayPal huérfana
        unset($_SESSION['paypal_ticket_' . $ticketId]);

        $_SESSION['flash_error'] = 'Pago con PayPal cancelado. Elige otro método.';
        $visitaId = $ticket['visita_id'] ?? 0;
        $this->redirect('menu/' . $realSlug . '/pagar/' . $visitaId);
    }

    // POST /menu/{slug}/llamarMesero
    public function llamarMesero(?string $slug = null): void
    {
        header('Content-Type: application/json');
        $restaurante = $this->restModel->getBySlug($slug ?? '');
        if (!$restaurante) { echo json_encode(['ok'=>false]); exit; }

        $mesaQr   = $this->post('mesa_qr');
        $visitaId = (int)$this->post('visita_id', 0);
        $mesa     = $mesaQr ? $this->mesaModel->getByQr($mesaQr) : null;
        $tipo     = $this->post('tipo', 'mesero');
        $tipo     = in_array($tipo, ['mesero','cuenta'], true) ? $tipo : 'mesero';

        $db = Database::getInstance();
        $db->prepare(
            'INSERT INTO rest_alertas (restaurante_id, tipo, mesa_id, visita_id) VALUES (?,?,?,?)'
        )->execute([
            (int)$restaurante['id'],
            $tipo,
            $mesa ? (int)$mesa['id'] : null,
            $visitaId ?: null,
        ]);

        echo json_encode(['ok' => true]);
        exit;
    }

    // POST /menu/{slug}/cancelarPedido/{pedidoId}
    public function cancelarPedido(?string $slug = null): void
    {
        header('Content-Type: application/json');
        $parts     = explode('/', $slug ?? '');
        $realSlug  = $parts[0] ?? '';
        $pedidoId  = (int)($parts[1] ?? 0);

        $restaurante = $this->restModel->getBySlug($realSlug);
        if (!$restaurante || !$pedidoId) { echo json_encode(['ok'=>false,'msg'=>'Inválido']); exit; }

        $pedido = $this->pedidoModel->find($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== (int)$restaurante['id']) {
            echo json_encode(['ok'=>false,'msg'=>'Pedido no encontrado']); exit;
        }
        if (!in_array($pedido['estado'], ['pendiente', 'en_preparacion', 'listo'])) {
            echo json_encode(['ok'=>false,'msg'=>'Este pedido ya no se puede cancelar']); exit;
        }

        $this->pedidoModel->cambiarEstadoPedido($pedidoId, 'cancelado');
        $this->pedidoModel->cancelarItemsActivos($pedidoId);
        $this->visitaModel->actualizarTotales((int)$pedido['visita_id']);

        echo json_encode(['ok' => true]);
        exit;
    }

    // GET /menu/{slug}/estadoPedido/{visitaId}  — polling JSON
    public function estadoPedido(?string $slug = null): void
    {
        header('Content-Type: application/json');
        $parts     = explode('/', $slug ?? '');
        $realSlug  = $parts[0] ?? '';
        $visitaId  = (int)($parts[1] ?? 0);

        $restaurante = $this->restModel->getBySlug($realSlug);
        if (!$restaurante || !$visitaId) { echo json_encode(['ok'=>false]); exit; }

        $pedidos = $this->pedidoModel->getByVisita($visitaId);
        $result  = [];
        $tiempoMax = 0;

        foreach ($pedidos as $ped) {
            $detalle = $this->pedidoModel->getConItems((int)$ped['id']);
            $items   = [];
            foreach (($detalle['items'] ?? []) as $it) {
                $items[] = [
                    'id'          => $it['id'],
                    'nombre'      => $it['platillo_nombre'] ?? $it['nombre'] ?? '',
                    'cantidad'    => (int)$it['cantidad'],
                    'estado'      => $it['estado'],
                    'tiempo_prep' => (int)($it['tiempo_preparacion_min'] ?? 0),
                ];
                if (in_array($it['estado'], ['pendiente','en_preparacion'])) {
                    $tiempoMax = max($tiempoMax, (int)($it['tiempo_preparacion_min'] ?? 0));
                }
            }
            $result[] = [
                'id'     => $ped['id'],
                'folio'  => $ped['folio'],
                'estado' => $ped['estado'],
                'items'  => $items,
            ];
        }

        $ticketRow     = $this->ticketModel->getByVisita($visitaId);
        $ticketEstado  = $ticketRow['estado'] ?? null;
        $visitaRow     = $this->visitaModel->find($visitaId);

        echo json_encode([
            'ok'            => true,
            'pedidos'       => $result,
            'tiempo_min'    => $tiempoMax,
            'ticket_estado' => $ticketEstado,
            'ticket_total'  => $ticketRow ? (float)$ticketRow['total']   : 0,
            'ticket_propina'=> $ticketRow ? (float)$ticketRow['propina'] : 0,
            'qr_code'       => $visitaRow['qr_code'] ?? '',
        ]);
        exit;
    }

    // POST /menu/{slug}/actualizarPropina/{ticketId}  — AJAX
    public function actualizarPropina(?string $slug = null): void
    {
        header('Content-Type: application/json');
        $parts    = explode('/', $slug ?? '');
        $realSlug = $parts[0] ?? '';
        $ticketId = (int)($parts[1] ?? 0);

        $restaurante = $this->restModel->getBySlug($realSlug);
        $ticket      = $this->ticketModel->find($ticketId);
        if (!$restaurante || !$ticket || (int)$ticket['restaurante_id'] !== (int)$restaurante['id']) {
            echo json_encode(['ok'=>false]); exit;
        }

        $propina   = max(0.0, (float)$this->post('propina', 0));
        $this->ticketModel->actualizarPropina($ticketId, $propina);
        $ticket    = $this->ticketModel->find($ticketId);

        echo json_encode([
            'ok'      => true,
            'propina' => (float)$ticket['propina'],
            'total'   => (float)$ticket['total'],
        ]);
        exit;
    }

    // GET /menu/scanPortero?qr={token}  — página pública de verificación de salida
    public function scanPortero(?string $p = null): void
    {
        $qr     = trim($this->get('qr', ''));
        $visita = $qr ? $this->visitaModel->getByQr($qr) : null;

        if (!$visita) {
            http_response_code(404);
            $this->render('publico/portero/scan_error');
            exit;
        }

        $restaurante = $this->restModel->find((int)$visita['restaurante_id']);

        // Salida ya registrada → directo a gracias
        if (!empty($visita['salida_at'])) {
            $this->redirect('menu/gracias?qr=' . urlencode($qr));
            return;
        }

        // Mostrar estado (solo lectura — la salida la registra el portero autenticado)
        $pageTitle = 'Verificar salida';
        $this->render('publico/portero/scan', compact('visita', 'restaurante', 'qr', 'pageTitle'));
    }

    // POST /menu/registrarSalidaPublica — requiere sesión de staff
    public function registrarSalidaPublica(?string $p = null): void
    {
        header('Content-Type: application/json');
        if (!isset($_SESSION['usuario'])) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
            exit;
        }
        $qr     = trim($this->post('qr', ''));
        $visita = $qr ? $this->visitaModel->getByQr($qr) : null;

        if (!$visita) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'mensaje' => 'QR no válido.']);
            exit;
        }

        if ($visita['estado'] !== 'pagada') {
            http_response_code(400);
            echo json_encode(['ok' => false, 'mensaje' => 'La cuenta no está pagada.']);
            exit;
        }

        if (!empty($visita['salida_at'])) {
            echo json_encode(['ok' => false, 'ya_salio' => true, 'mensaje' => 'Salida ya registrada.']);
            exit;
        }

        $this->visitaModel->marcarSalida((int)$visita['id']);

        if (!empty($visita['mesa_id'])) {
            $this->mesaModel->cambiarEstado((int)$visita['mesa_id'], 'disponible');
        }

        // Borrar cookie del comensal
        $restaurante = $this->restModel->find((int)$visita['restaurante_id']);
        if ($restaurante) {
            setcookie('visita_' . $restaurante['id'], '', ['expires' => time() - 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }

        echo json_encode([
            'ok'       => true,
            'mensaje'  => '¡Salida registrada! Mesa liberada.',
            'redirect' => BASE_URL . 'menu/gracias?qr=' . urlencode($visita['qr_code'] ?? ''),
        ]);
        exit;
    }

    // GET /menu/checkSalida?qr=TOKEN — polling del comensal para detectar salida
    public function checkSalida(?string $p = null): void
    {
        header('Content-Type: application/json');
        $qr     = trim($_GET['qr'] ?? '');
        $visita = $qr ? $this->visitaModel->getByQr($qr) : null;
        if (!$visita) {
            echo json_encode(['ok' => false, 'salida' => false]);
            exit;
        }
        $salida = !empty($visita['salida_at']);
        echo json_encode([
            'ok'      => true,
            'salida'  => $salida,
            'redirect'=> $salida ? BASE_URL . 'menu/gracias?qr=' . urlencode($qr) : null,
        ]);
        exit;
    }

    // POST /menu/stripeIntent/{slug}/{ticketId}
    public function stripeIntent(?string $slug = null): void
    {
        header('Content-Type: application/json');
        if (!$this->isPost()) { echo json_encode(['ok' => false]); exit; }

        $parts    = explode('/', $slug ?? '');
        $realSlug = $parts[0] ?? '';
        $ticketId = (int)($parts[1] ?? 0);

        $restaurante = $this->restModel->getBySlug($realSlug);
        $ticket      = $this->ticketModel->find($ticketId);

        if (!$restaurante || !$ticket
            || (int)$ticket['restaurante_id'] !== (int)$restaurante['id']
            || ($ticket['estado'] ?? '') === 'pagado') {
            echo json_encode(['ok' => false, 'error' => 'Ticket no válido']);
            exit;
        }

        try {
            $stripeKey = $this->getStripeKey('secret');
            if (empty($stripeKey)) {
                echo json_encode(['ok' => false, 'error' => 'Pago con tarjeta no configurado. Contacta al restaurante.']);
                exit;
            }
            \Stripe\Stripe::setApiKey($stripeKey);
            $centavos = (int)round((float)($ticket['total'] ?? 0) * 100);
            $intent = \Stripe\PaymentIntent::create([
                'amount'   => max(1000, $centavos),
                'currency' => 'mxn',
                'metadata' => [
                    'ticket_id'   => $ticketId,
                    'folio'       => $ticket['folio'] ?? '',
                    'restaurante' => $restaurante['nombre'] ?? '',
                ],
            ]);
            $_SESSION['stripe_intent_' . $ticketId] = $intent->id;
            echo json_encode(['ok' => true, 'clientSecret' => $intent->client_secret]);
        } catch (\Throwable $e) {
            echo json_encode(['ok' => false, 'error' => 'Error al crear pago con tarjeta']);
        }
        exit;
    }

    // GET /menu/stripeRetorno/{slug}/{ticketId}?cs={CHECKOUT_SESSION_ID}
    public function stripeRetorno(?string $slug = null): void
    {
        $parts     = explode('/', $slug ?? '');
        $realSlug  = $parts[0] ?? '';
        $ticketId  = (int)($parts[1] ?? 0);
        $sessionId = trim($this->get('cs', '') ?: $this->get('session_id', ''));

        $restaurante = $this->restModel->getBySlug($realSlug);
        $ticket      = $this->ticketModel->find($ticketId);

        if (!$restaurante || !$ticket || !$sessionId
            || (int)$ticket['restaurante_id'] !== (int)$restaurante['id']) {
            $this->redirect('menu/' . $realSlug);
            return;
        }

        // Idempotente: si ya fue pagado, redirigir directo
        if (($ticket['estado'] ?? '') === 'pagado') {
            $this->redirect('menu/' . $realSlug . '/confirmacion/' . $ticket['visita_id'] . '?pagado=1');
            return;
        }

        // Verificar token de sesión anti-replay
        $sesKey = 'stripe_checkout_' . $ticketId;
        if (empty($_SESSION[$sesKey])
            || $_SESSION[$sesKey]['slug']       !== $realSlug
            || $_SESSION[$sesKey]['session_id'] !== $sessionId) {
            $_SESSION['flash_error'] = 'Sesión de pago expirada. Intenta de nuevo.';
            $this->redirect('menu/' . $realSlug . '/pagar/' . $ticket['visita_id']);
            return;
        }
        unset($_SESSION[$sesKey]);

        try {
            $stripeKey = $this->getStripeKey('secret');
            if (empty($stripeKey)) throw new \RuntimeException('Stripe no configurado');
            \Stripe\Stripe::setApiKey($stripeKey);

            error_log('[Stripe:stripeRetorno] key=' . (empty($stripeKey) ? 'VACÍA' : 'OK') . ' | sessionId=' . $sessionId . ' | ticket=' . $ticketId);

            $session = \Stripe\Checkout\Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                throw new \RuntimeException('Pago no completado (estado: ' . $session->payment_status . ')');
            }
            if ((string)($session->metadata['ticket_id'] ?? '') !== (string)$ticketId) {
                throw new \RuntimeException('El pago no corresponde a este ticket');
            }
        } catch (\Throwable $e) {
            error_log('[Stripe:stripeRetorno] ERROR: ' . $e->getMessage() . ' | class=' . get_class($e) . ' | sessionId=' . $sessionId . ' | ticket=' . $ticketId);
            $_SESSION['flash_error'] = 'No se pudo verificar el pago. Contacta al restaurante.';
            $this->redirect('menu/' . $realSlug . '/pagar/' . $ticket['visita_id']);
            return;
        }

        $this->ticketModel->marcarPagado($ticketId, 'tarjeta', $session->payment_intent ?? null);
        $this->visitaModel->marcarPagada((int)$ticket['visita_id']);
        $this->redirect('menu/' . $realSlug . '/confirmacion/' . $ticket['visita_id'] . '?pagado=1');
    }

    // GET /menu/gracias?qr={token}
    public function gracias(?string $p = null): void
    {
        $qr     = trim($this->get('qr', ''));
        $visita = $qr ? $this->visitaModel->getByQr($qr) : null;

        $restaurante = null;
        if ($visita && !empty($visita['restaurante_id'])) {
            $restaurante = $this->restModel->find((int)$visita['restaurante_id']);
        }

        if (!$restaurante) {
            // QR inválido o expirado — mostrar página genérica
            $restaurante = ['nombre' => 'el restaurante', 'color_primario' => '#C8102E', 'logo' => ''];
        }

        // Limpiar cookie de visita
        setcookie('visita_' . ($restaurante['id'] ?? 0), '', ['expires' => time() - 1, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

        $pageTitle = '¡Gracias por tu visita!';
        $this->render('publico/menu/gracias', compact('restaurante', 'visita', 'pageTitle'));
    }

    // ── Reservaciones públicas por QR ────────────────────────────────────────

    // GET /menu/{slug}/reservar
    public function reservar(?string $param = null): void
    {
        $slug        = explode('/', $param ?? '')[0];
        $restaurante = $this->restModel->getBySlug($slug);
        if (!$restaurante) { http_response_code(404); die('<h1>Restaurante no encontrado</h1>'); }

        if (empty($restaurante['reservas_habilitadas'])) {
            $pageTitle = 'Reservaciones no disponibles';
            $this->render('publico/reservar', compact('restaurante', 'pageTitle'));
            return;
        }

        $ok         = $this->get('ok') === '1';
        $reservaId  = $ok ? (int)($this->get('ref') ?? 0) : 0;
        $flash      = $this->getFlash();
        $pageTitle  = 'Reservar mesa — ' . htmlspecialchars($restaurante['nombre']);
        $this->render('publico/reservar', compact('restaurante', 'pageTitle', 'ok', 'flash', 'reservaId'));
    }

    // POST /menu/{slug}/guardarReserva
    public function guardarReserva(?string $param = null): void
    {
        $slug        = explode('/', $param ?? '')[0];
        $restaurante = $this->restModel->getBySlug($slug);
        if (!$restaurante) { http_response_code(404); die('<h1>Restaurante no encontrado</h1>'); }

        if (!$this->isPost()) {
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }

        if (empty($restaurante['reservas_habilitadas'])) {
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }

        $nombre   = trim($this->post('nombre', ''));
        $telefono = trim($this->post('telefono', ''));
        $email    = trim($this->post('email', '')) ?: null;
        $fecha    = $this->post('fecha');
        $hora     = $this->post('hora');
        $personas = max(1, (int)$this->post('personas', 2));
        $notas    = $this->post('notas') ?: null;
        $mesaId   = (int)$this->post('mesa_id', 0);

        if (!$nombre || !$telefono || !$fecha || !$hora || !$mesaId) {
            $this->flash('error', 'Por favor completa todos los campos y selecciona una mesa.');
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }

        // Validar teléfono: exactamente 10 dígitos
        $telefonoDigitos = preg_replace('/\D/', '', $telefono);
        if (strlen($telefonoDigitos) !== 10) {
            $this->flash('error', 'El teléfono debe contener exactamente 10 dígitos.');
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }
        $telefono = $telefonoDigitos;

        // Validar email obligatorio y bien formado
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Ingresa un correo electrónico válido.');
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }

        // Validar mesa: capacidad suficiente, pertenece al restaurante, sin conflicto
        $reservaModel = new RestReservaModel();
        $db = Database::getInstance();
        $stmtMesa = $db->prepare(
            "SELECT id, nombre, capacidad FROM rest_mesas
             WHERE id = ? AND restaurante_id = ? AND activo = 1 LIMIT 1"
        );
        $stmtMesa->execute([$mesaId, (int)$restaurante['id']]);
        $mesa = $stmtMesa->fetch(PDO::FETCH_ASSOC);

        if (!$mesa) {
            $this->flash('error', 'La mesa seleccionada no está disponible. Elige otra.');
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }
        if ((int)$mesa['capacidad'] < $personas) {
            $this->flash('error', 'La mesa seleccionada no tiene capacidad para ' . $personas . ' personas.');
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }
        if ($reservaModel->hayConflicto($mesaId, $fecha, $hora)) {
            $this->flash('error', 'Esa mesa ya está reservada en ese horario. Elige otra.');
            $this->redirect('menu/' . $slug . '/reservar');
            return;
        }

        // Auto-asignar mesero según zona/turno
        $meseroId = $reservaModel->meseroAsignadoPorMesa($mesaId, (int)$restaurante['id']);

        $newId = $reservaModel->insert([
            'restaurante_id' => (int)$restaurante['id'],
            'mesa_id'        => $mesaId,
            'mesero_id'      => $meseroId,
            'nombre'         => $nombre,
            'telefono'       => $telefono,
            'email'          => $email,
            'fecha'          => $fecha,
            'hora'           => $hora,
            'personas'       => $personas,
            'notas'          => $notas,
            'estado'         => 'confirmada',
            'origen'         => 'comensal',
        ]);

        // Notificar al restaurante (silencioso si falla)
        try {
            $admin = $this->restModel->getAdminEmail((int)$restaurante['id']);
            if ($admin && !empty($admin['email'])) {
                (new EmailService())->enviarNuevaReserva(
                    $admin['email'],
                    $admin['nombre'],
                    $restaurante,
                    ['nombre' => $nombre, 'telefono' => $telefono, 'fecha' => $fecha,
                     'hora' => $hora, 'personas' => $personas, 'notas' => $notas ?? '']
                );
            }
        } catch (\Throwable $e) {
            error_log('[Reserva] Error enviando email al restaurante: ' . $e->getMessage());
        }

        // Confirmación al comensal (si dio email)
        if ($email && $newId) {
            try {
                $emailSvc = new EmailService();
                if (!$emailSvc->isConfigured()) {
                    error_log("[Reserva #$newId] SMTP no configurado — no se envía confirmación a $email");
                } else {
                    $cancelUrl = BASE_URL . 'menu/' . $slug . '/cancelarReserva/' . $newId;
                    $ok = $emailSvc->enviarConfirmacionReserva(
                        $email,
                        $restaurante,
                        ['nombre' => $nombre, 'fecha' => $fecha, 'hora' => $hora,
                         'personas' => $personas, 'mesa_nombre' => $mesa['nombre']],
                        $cancelUrl
                    );
                    if ($ok) {
                        $reservaModel->marcarConfirmacionEnviada((int)$newId);
                        error_log("[Reserva #$newId] Confirmación enviada a $email");
                    } else {
                        error_log("[Reserva #$newId] FALLO al enviar confirmación a $email (revisa logs SMTP)");
                    }
                }
            } catch (\Throwable $e) {
                error_log("[Reserva #$newId] Excepción enviando confirmación a $email: " . $e->getMessage());
            }
        }

        $this->redirect('menu/' . $slug . '/reservar?ok=1&ref=' . $newId);
    }

    // GET /menu/{slug}/mesasDisponibles?fecha=YYYY-MM-DD&hora=HH:MM&personas=N
    public function mesasDisponibles(?string $param = null): void
    {
        $slug        = explode('/', $param ?? '')[0];
        $restaurante = $this->restModel->getBySlug($slug);
        if (!$restaurante) { $this->json(['ok' => false, 'mesas' => []], 404); return; }

        $fecha    = (string)$this->get('fecha', '');
        $hora     = (string)$this->get('hora', '');
        $personas = max(1, (int)$this->get('personas', 2));

        if (!$fecha || !$hora) { $this->json(['ok' => false, 'mesas' => []]); return; }
        if (strlen($hora) === 5) $hora .= ':00';

        $reservaModel = new RestReservaModel();
        $mesas = $reservaModel->mesasDisponiblesParaCapacidad(
            (int)$restaurante['id'], $fecha, $hora, $personas
        );
        $this->json(['ok' => true, 'mesas' => $mesas]);
    }

    // GET  /menu/{slug}/cancelarReserva/{id}
    // POST /menu/{slug}/cancelarReserva/{id}
    public function cancelarReserva(?string $param = null): void
    {
        $parts       = explode('/', $param ?? '');
        $slug        = $parts[0];
        $reservaId   = isset($parts[1]) ? (int)$parts[1] : 0;
        $restaurante = $this->restModel->getBySlug($slug);

        if (!$restaurante || !$reservaId) {
            http_response_code(404);
            die('<h1>Reservación no encontrada</h1>');
        }

        $pageTitle = 'Cancelar reservación — ' . htmlspecialchars($restaurante['nombre']);

        if ($this->isPost()) {
            $telefono = trim($this->post('telefono', ''));
            if (!$telefono) {
                $flash = ['type' => 'error', 'message' => 'Ingresa el teléfono con el que hiciste la reservación.'];
                $this->render('publico/cancelar_reserva', compact('restaurante', 'pageTitle', 'reservaId', 'flash'));
                return;
            }

            $reservaModel = new RestReservaModel();
            $reserva      = $reservaModel->getParaCancelar($reservaId, (int)$restaurante['id'], $telefono);

            if (!$reserva) {
                $flash = ['type' => 'error', 'message' => 'No encontramos una reservación activa con ese teléfono. Verifica los datos.'];
                $this->render('publico/cancelar_reserva', compact('restaurante', 'pageTitle', 'reservaId', 'flash'));
                return;
            }

            $reservaModel->cambiarEstado($reservaId, 'cancelada');
            $cancelada = true;
            $flash     = null;
            $this->render('publico/cancelar_reserva', compact('restaurante', 'pageTitle', 'reservaId', 'cancelada', 'flash'));
            return;
        }

        $cancelada = false;
        $flash     = null;
        $this->render('publico/cancelar_reserva', compact('restaurante', 'pageTitle', 'reservaId', 'cancelada', 'flash'));
    }
}
