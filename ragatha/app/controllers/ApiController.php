<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

/**
 * ApiController — Endpoints AJAX (sin layout HTML)
 * Maneja: precios escalonados, GPS tracking, API v1 (CapiRest),
 *         Admin API v1 (JWT Bearer para el sitio web admin)
 */
class ApiController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    // ── Pedidos confirmados por empresa (para form de rutas) ──────────
    /** GET /api/pedidosConfirmados?empresa_id=X */
    public function pedidosConfirmados(?string $p = null): void
    {
        $this->requireAdmin();
        $empresaId = (int)$this->get('empresa_id', 0);
        if (!$empresaId) {
            $this->json([]);
        }

        $model   = new PedidoModel();
        $pedidos = $model->listadoConfirmadosPorEmpresa($empresaId);
        $this->json($pedidos);
    }

    // ── Precios escalonados ───────────────────────────────────────
    /** GET /api/precios/{producto_id}?cantidad=X */
    public function precios(?string $productoId = null): void
    {
        $this->requireEmpresa();
        $productoId = (int)$productoId;
        $cantidad   = (float)($this->get('cantidad', 0));

        if (!$productoId || $cantidad <= 0) {
            $this->json(['error' => 'Datos inválidos'], 400);
        }

        $model       = new ProductoModel();
        $compradorId = $this->usuarioId();
        $precio      = $model->getPrecioFinal($compradorId, $productoId, $cantidad);
        $escalonados = $model->getEscalonados($productoId);

        // Indicar si el precio aplicado es un precio especial (solo aplica < 10 kg)
        $esPrecioEspecial = false;
        if ($cantidad < 10.0) {
            $especial = $model->getPrecioEspecial($compradorId, $productoId);
            $esPrecioEspecial = ($especial !== null && abs($precio - $especial) < 0.001);
        }

        $this->json([
            'precio'             => $precio,
            'escalonados'        => $escalonados,
            'es_precio_especial' => $esPrecioEspecial,
        ]);
    }

    // ── Planes públicos (sin auth) ────────────────────────────────
    /** GET /api/planes — Devuelve planes activos para polling en landing */
    public function planes(?string $p = null): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $model  = new SuscripcionModel();
        $raw    = $model->getPlanesActivos();

        $planes = array_map(function (array $plan): array {
            $features = [];
            if (!empty($plan['features'])) {
                $features = is_array($plan['features'])
                    ? $plan['features']
                    : (json_decode($plan['features'], true) ?? []);
            }
            return [
                'id'             => (int)$plan['id'],
                'nombre'         => $plan['nombre'],
                'slug'           => $plan['slug'],
                'precio_mensual' => (float)$plan['precio_mensual'],
                'precio_anual'   => !empty($plan['precio_anual']) ? (float)$plan['precio_anual'] : null,
                'max_usuarios'   => (int)$plan['max_usuarios'],
                'max_productos'  => (int)$plan['max_productos'],
                'max_sucursales' => (int)$plan['max_sucursales'],
                'features'       => array_slice($features, 0, 6),
            ];
        }, $raw);

        $hash = md5(json_encode($planes));

        $this->json(['planes' => $planes, 'hash' => $hash]);
    }

    // ── GPS Tracking ──────────────────────────────────────────────

    /** GET /api/tracking/{pedido_id} — posición actual del repartidor */
    public function tracking(?string $pedidoId = null): void
    {
        $this->requireAuth();
        $pedidoId = (int)$pedidoId;
        if (!$pedidoId) $this->json(['error' => 'Pedido inválido'], 400);

        $db = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT rd.lat_actual, rd.lng_actual, rd.eta_minutos,
                    rd.estado, rd.tracking_activo,
                    s.lat AS dest_lat, s.lng AS dest_lng, s.nombre AS sucursal,
                    p.estado AS pedido_estado
               FROM ruta_detalle rd
               JOIN sucursales s ON s.id = rd.sucursal_id
               JOIN pedidos p ON p.id = rd.pedido_id
              WHERE rd.pedido_id = ? AND rd.tracking_activo = 1
           ORDER BY rd.orden LIMIT 1'
        );
        $stmt->execute([$pedidoId]);
        $row = $stmt->fetch();

        if (!$row) {
            // Sin tracking activo, devolver estado del pedido
            $stmt2 = $db->prepare('SELECT estado FROM pedidos WHERE id = ?');
            $stmt2->execute([$pedidoId]);
            $ped = $stmt2->fetch();
            $this->json(['tracking_activo' => false, 'estado' => $ped['estado'] ?? 'desconocido']);
        }

        $this->json([
            'tracking_activo' => (bool)$row['tracking_activo'],
            'lat'             => $row['lat_actual'],
            'lng'             => $row['lng_actual'],
            'eta_minutos'     => $row['eta_minutos'],
            'estado'          => $row['estado'],
            'pedido_estado'   => $row['pedido_estado'],
            'destino'         => ['lat' => $row['dest_lat'], 'lng' => $row['dest_lng'], 'nombre' => $row['sucursal']],
        ]);
    }

    /** POST /api/tracking/actualizar — repartidor envía su posición */
    public function actualizarTracking(?string $p = null): void
    {
        $this->requireRepartidor();

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $paradaId = (int)($body['ruta_detalle_id'] ?? 0);
        $lat      = (float)($body['lat'] ?? 0);
        $lng      = (float)($body['lng'] ?? 0);

        if (!$paradaId || !$lat || !$lng) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos'], 400);
        }

        $db = Database::getInstance();

        // Calcular ETA aproximado (distancia Haversine a la sucursal)
        $stmt = $db->prepare('SELECT s.lat, s.lng FROM ruta_detalle rd JOIN sucursales s ON s.id = rd.sucursal_id WHERE rd.id = ?');
        $stmt->execute([$paradaId]);
        $dest = $stmt->fetch();

        $etaMinutos = null;
        if ($dest && $dest['lat'] && $dest['lng']) {
            $distKm = $this->haversine($lat, $lng, (float)$dest['lat'], (float)$dest['lng']);
            $etaMinutos = (int)round(($distKm / 30) * 60); // ~30 km/h promedio urbano
        }

        $db->prepare(
            'UPDATE ruta_detalle SET lat_actual = ?, lng_actual = ?, eta_minutos = ?, tracking_activo = 1 WHERE id = ?'
        )->execute([$lat, $lng, $etaMinutos, $paradaId]);

        $this->json(['ok' => true, 'eta_minutos' => $etaMinutos]);
    }

    /** POST /api/tracking/iniciar */
    public function iniciarTracking(?string $paradaId = null): void
    {
        $this->requireRepartidor();
        $paradaId = (int)$paradaId;
        if (!$paradaId) $this->json(['ok' => false], 400);

        Database::getInstance()
            ->prepare('UPDATE ruta_detalle SET tracking_activo = 1 WHERE id = ?')
            ->execute([$paradaId]);

        $this->json(['ok' => true]);
    }

    /** POST /api/tracking/finalizar/{paradaId} */
    public function finalizarTracking(?string $paradaId = null): void
    {
        $this->requireRepartidor();
        $paradaId = (int)$paradaId;
        if (!$paradaId) $this->json(['ok' => false], 400);

        Database::getInstance()
            ->prepare('UPDATE ruta_detalle SET tracking_activo = 0 WHERE id = ?')
            ->execute([$paradaId]);

        $this->json(['ok' => true]);
    }

    // ── Chatbot de datos (sin IA externa) ─────────────────────────
    /** POST /api/chat */
    public function chat(?string $p = null): void
    {
        $this->requireAdminEmpresa();

        try {
            $body    = json_decode(file_get_contents('php://input'), true) ?? [];
            $mensaje = trim($body['mensaje'] ?? '');

            if (!$mensaje) {
                $this->json(['error' => 'Mensaje vacío'], 400);
                return;
            }

            $empresaId = (int)$this->empresaId();
            $respuesta = $this->resolverConsultaChat($empresaId, $mensaje);
            $this->json(['respuesta' => $respuesta]);

        } catch (\Throwable $e) {
            $this->json(['error' => 'Error interno: ' . $e->getMessage()], 500);
        }
    }

    /** Resuelve consultas del chatbot usando datos reales de la BD */
    private function resolverConsultaChat(int $empresaId, string $msg): string
    {
        $db   = Database::getInstance();
        $norm = strtr(mb_strtolower($msg, 'UTF-8'), [
            'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
        ]);

        // ── SALUDO / AYUDA ─────────────────────────────────────────
        if (preg_match('/^(hola|buenos|buenas|hey|que tal|que puedes|ayuda|como estas)/u', $norm)) {
            return "¡Hola! Soy tu asistente de datos. Puedo responder preguntas sobre:\n"
                 . "• Pedidos (hoy, esta semana, este mes, pendientes, cancelados)\n"
                 . "• Ventas y gasto acumulado del mes\n"
                 . "• Stock e inventario bajo mínimo\n"
                 . "• Productos más pedidos\n"
                 . "• Compradores más frecuentes\n"
                 . "• Equipo activo\n\n"
                 . "¿Qué quieres consultar?";
        }

        // ── PEDIDOS HOY ────────────────────────────────────────────
        if (preg_match('/pedido.*(hoy|dia de hoy|de hoy)/u', $norm)
            || preg_match('/(hoy|dia de hoy).*(pedido)/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto
                 FROM pedidos WHERE empresa_id=? AND DATE(created_at)=CURDATE()"
            );
            $stmt->execute([$empresaId]);
            $r = $stmt->fetch();
            return "Hoy llevas {$r['total']} pedido(s) registrado(s) con un monto total de $"
                 . number_format($r['monto'], 2) . " MXN.";
        }

        // ── PEDIDOS SEMANA ─────────────────────────────────────────
        if (preg_match('/pedido.*(semana|esta semana|7 dias|siete dias)/u', $norm)
            || preg_match('/(semana|esta semana).*(pedido)/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total, COALESCE(SUM(total),0) AS monto
                 FROM pedidos WHERE empresa_id=? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $stmt->execute([$empresaId]);
            $r = $stmt->fetch();
            return "En los últimos 7 días tuviste {$r['total']} pedido(s) por un total de $"
                 . number_format($r['monto'], 2) . " MXN.";
        }

        // ── PENDIENTES ─────────────────────────────────────────────
        if (preg_match('/pendiente|por aprobar|aprobacion/u', $norm)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND estado='pendiente'");
            $stmt->execute([$empresaId]);
            $total = (int)$stmt->fetchColumn();
            if ($total === 0) return "No tienes pedidos pendientes de aprobación. ¡Todo al día!";
            return "Tienes $total pedido(s) pendiente(s) de aprobación. Puedes revisarlos en la sección de Pedidos.";
        }

        // ── CANCELADOS ─────────────────────────────────────────────
        if (preg_match('/cancelad/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND estado='cancelado'
                 AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
            );
            $stmt->execute([$empresaId]);
            $total = (int)$stmt->fetchColumn();
            return "Este mes tienes $total pedido(s) cancelado(s).";
        }

        // ── EN RUTA ────────────────────────────────────────────────
        if (preg_match('/en ruta|en camino/u', $norm)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND estado='en_ruta'");
            $stmt->execute([$empresaId]);
            $total = (int)$stmt->fetchColumn();
            return "Ahora mismo hay $total pedido(s) en ruta hacia sus destinos.";
        }

        // ── ENTREGADOS ─────────────────────────────────────────────
        if (preg_match('/entregad/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND estado='entregado'
                 AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
            );
            $stmt->execute([$empresaId]);
            $total = (int)$stmt->fetchColumn();
            return "Este mes tienes $total pedido(s) entregado(s) exitosamente.";
        }

        // ── VENTAS / GASTO ─────────────────────────────────────────
        if (preg_match('/venta|gasto|cuanto vend|cuanto factur|monto|ingreso|facturaci/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT COALESCE(SUM(total),0) AS mes_actual, COUNT(*) AS total_pedidos
                 FROM pedidos WHERE empresa_id=? AND MONTH(created_at)=MONTH(NOW())
                 AND YEAR(created_at)=YEAR(NOW()) AND estado!='cancelado'"
            );
            $stmt->execute([$empresaId]);
            $r = $stmt->fetch();
            $stmt2 = $db->prepare(
                "SELECT COALESCE(SUM(total),0) FROM pedidos WHERE empresa_id=?
                 AND MONTH(created_at)=MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH))
                 AND YEAR(created_at)=YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) AND estado!='cancelado'"
            );
            $stmt2->execute([$empresaId]);
            $mesAnterior = (float)$stmt2->fetchColumn();
            $mesActual   = (float)$r['mes_actual'];
            $diff        = $mesActual - $mesAnterior;
            $diffStr     = ($diff >= 0 ? '+$' : '-$') . number_format(abs($diff), 2);
            return "Este mes llevas $" . number_format($mesActual, 2) . " MXN en ventas con {$r['total_pedidos']} pedido(s). "
                 . "El mes pasado fue $" . number_format($mesAnterior, 2) . " MXN (diferencia: $diffStr MXN).";
        }

        // ── STOCK / INVENTARIO ─────────────────────────────────────
        if (preg_match('/stock|inventario|sin existencia|producto.*bajo|minimo/u', $norm)) {
            try {
                $stmt = $db->prepare(
                    "SELECT p.nombre, p.presentacion, i.cantidad, i.minimo_stock
                     FROM inventario i JOIN productos p ON p.id=i.producto_id
                     WHERE p.empresa_id=? AND i.cantidad<=i.minimo_stock
                     ORDER BY i.cantidad ASC LIMIT 5"
                );
                $stmt->execute([$empresaId]);
                $rows = $stmt->fetchAll();
                if (empty($rows)) return "No hay productos con stock bajo. ¡Inventario al día!";
                $lista = array_map(
                    fn($row) => "• {$row['nombre']} ({$row['presentacion']}): {$row['cantidad']} / mínimo {$row['minimo_stock']}",
                    $rows
                );
                return "Tienes " . count($rows) . " producto(s) con stock bajo:\n" . implode("\n", $lista);
            } catch (\Throwable $e) {
                return "No pude consultar el inventario en este momento.";
            }
        }

        // ── TOP PRODUCTOS ──────────────────────────────────────────
        if (preg_match('/producto.*(mas|frecuente|popular|pide|vendid|top)|top.*producto|que se pide/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT pr.nombre, pr.presentacion,
                        COUNT(DISTINCT p.id) AS veces,
                        SUM(pd.cantidad) AS cantidad_total
                 FROM pedido_detalle pd
                 JOIN pedidos p ON p.id=pd.pedido_id
                 JOIN productos pr ON pr.id=pd.producto_id
                 WHERE p.empresa_id=? AND p.estado!='cancelado'
                 GROUP BY pr.id, pr.nombre, pr.presentacion
                 ORDER BY veces DESC, cantidad_total DESC LIMIT 5"
            );
            $stmt->execute([$empresaId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) return "Aún no hay datos suficientes de pedidos para mostrar el ranking de productos.";
            $lista = [];
            foreach ($rows as $i => $r) {
                $lista[] = ($i + 1) . ". {$r['nombre']} ({$r['presentacion']}) — {$r['veces']} pedido(s), "
                         . number_format($r['cantidad_total'], 1) . " uds. totales";
            }
            return "Top 5 productos más pedidos:\n" . implode("\n", $lista);
        }

        // ── COMPRADORES ────────────────────────────────────────────
        if (preg_match('/comprador|cliente|quien compra|quien pide|top.*client|mas frecuente/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT u.nombre, COUNT(DISTINCT p.id) AS total_pedidos, COALESCE(SUM(p.total),0) AS monto
                 FROM pedidos p JOIN usuarios u ON u.id=p.comprador_id
                 WHERE p.empresa_id=? AND p.estado!='cancelado'
                 GROUP BY u.id, u.nombre ORDER BY total_pedidos DESC LIMIT 5"
            );
            $stmt->execute([$empresaId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) return "Aún no hay datos de compradores con pedidos confirmados.";
            $lista = [];
            foreach ($rows as $i => $r) {
                $lista[] = ($i + 1) . ". {$r['nombre']} — {$r['total_pedidos']} pedido(s), $"
                         . number_format($r['monto'], 2) . " MXN";
            }
            return "Top 5 compradores más frecuentes:\n" . implode("\n", $lista);
        }

        // ── EQUIPO ─────────────────────────────────────────────────
        if (preg_match('/equipo|usuario|empleado|repartidor|supervisor|cuantos trabaj/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT rol, COUNT(*) AS total FROM usuarios
                 WHERE empresa_id=? AND activo=1 GROUP BY rol ORDER BY total DESC"
            );
            $stmt->execute([$empresaId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) return "No hay usuarios activos registrados en tu empresa.";
            $lista = array_map(fn($r) => "• {$r['rol']}: {$r['total']}", $rows);
            $total = array_sum(array_column($rows, 'total'));
            return "Tu equipo tiene $total usuario(s) activo(s):\n" . implode("\n", $lista);
        }

        // ── PEDIDOS RECIENTES ──────────────────────────────────────
        if (preg_match('/reciente|ultimo.*pedido|pedido.*reciente/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT p.folio, p.estado, p.total, u.nombre AS comprador
                 FROM pedidos p JOIN usuarios u ON u.id=p.comprador_id
                 WHERE p.empresa_id=? ORDER BY p.created_at DESC LIMIT 5"
            );
            $stmt->execute([$empresaId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) return "No hay pedidos registrados aún.";
            $lista = array_map(
                fn($r) => "• {$r['folio']} — {$r['comprador']}, estado: {$r['estado']}, $" . number_format($r['total'], 2),
                $rows
            );
            return "Últimos 5 pedidos:\n" . implode("\n", $lista);
        }

        // ── RESUMEN GENERAL ────────────────────────────────────────
        if (preg_match('/resumen|como vamos|estado del negocio|informe|panorama|general/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT COUNT(*) AS total_mes,
                        COALESCE(SUM(CASE WHEN estado!='cancelado' THEN total ELSE 0 END),0) AS gasto_mes
                 FROM pedidos WHERE empresa_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
            );
            $stmt->execute([$empresaId]);
            $resumen = $stmt->fetch();
            $stmt2 = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id=? AND estado='pendiente'");
            $stmt2->execute([$empresaId]);
            $pendientes = (int)$stmt2->fetchColumn();
            try {
                $stmt3 = $db->prepare(
                    "SELECT COUNT(*) FROM inventario i JOIN productos p ON p.id=i.producto_id
                     WHERE p.empresa_id=? AND i.cantidad<=i.minimo_stock"
                );
                $stmt3->execute([$empresaId]);
                $stockBajo = (int)$stmt3->fetchColumn();
            } catch (\Throwable $e) {
                $stockBajo = 0;
            }
            $stmt4 = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE empresa_id=? AND activo=1");
            $stmt4->execute([$empresaId]);
            $equipo = (int)$stmt4->fetchColumn();
            return "Resumen del negocio este mes:\n"
                 . "• Pedidos: {$resumen['total_mes']}\n"
                 . "• Ventas acumuladas: $" . number_format($resumen['gasto_mes'], 2) . " MXN\n"
                 . "• Pendientes de aprobación: $pendientes\n"
                 . "• Productos con stock bajo: $stockBajo\n"
                 . "• Usuarios activos en el equipo: $equipo";
        }

        // ── PEDIDOS ESTE MES (fallback "pedidos") ─────────────────
        if (preg_match('/pedido/u', $norm)) {
            $stmt = $db->prepare(
                "SELECT estado, COUNT(*) AS total FROM pedidos
                 WHERE empresa_id=? AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())
                 GROUP BY estado ORDER BY total DESC"
            );
            $stmt->execute([$empresaId]);
            $rows = $stmt->fetchAll();
            if (empty($rows)) return "No hay pedidos registrados este mes.";
            $lista = array_map(fn($r) => "• {$r['estado']}: {$r['total']}", $rows);
            $total = array_sum(array_column($rows, 'total'));
            return "Este mes tienes $total pedido(s) en total:\n" . implode("\n", $lista);
        }

        // ── FALLBACK ───────────────────────────────────────────────
        return "No entendí tu pregunta. Puedo consultarte sobre pedidos, ventas, inventario, "
             . "productos más pedidos, compradores o tu equipo. ¿Qué quieres saber?";
    }

    /** POST /api/guardarPosicion — guarda posición GPS en historial (cada ~60 s) */
    public function guardarPosicion(?string $p = null): void
    {
        $this->requireRepartidor();

        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $pedidoId = (int)($body['pedido_id'] ?? 0);
        $lat      = (float)($body['lat'] ?? 0);
        $lng      = (float)($body['lng'] ?? 0);

        if (!$pedidoId || !$lat || !$lng) {
            $this->json(['ok' => false, 'error' => 'Datos incompletos'], 400);
        }

        try {
            Database::getInstance()
                ->prepare('INSERT INTO tracking_posiciones (pedido_id, lat, lng) VALUES (?, ?, ?)')
                ->execute([$pedidoId, $lat, $lng]);
            $this->json(['ok' => true]);
        } catch (\Throwable $e) {
            $this->json(['ok' => false]);
        }
    }

    /** GET /api/historialTracking/{pedido_id} — devuelve trail para la vista de tracking */
    public function historialTracking(?string $pedidoId = null): void
    {
        $this->requireAuth();
        $pedidoId = (int)$pedidoId;
        if (!$pedidoId) {
            $this->json([]);
        }

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT lat, lng, ts FROM tracking_posiciones
                  WHERE pedido_id = ? ORDER BY ts ASC LIMIT 300'
            );
            $stmt->execute([$pedidoId]);
            $this->json($stmt->fetchAll());
        } catch (\Throwable $e) {
            $this->json([]);
        }
    }

    // ── Fórmula Haversine (distancia entre dos coordenadas en km) ─
    private function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R   = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2)**2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // =========================================================
    // REST API v1  —  para CapiRest (Bearer token, sin sesión)
    // =========================================================

    /**
     * Sub-router principal.
     */
    public function v1(?string $resource = null): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            http_response_code(204);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');

        $urlParam = trim($_GET['url'] ?? '', '/');
        $segs     = array_values(array_filter(explode('/', $urlParam)));
        if (count($segs) < 4) {
            $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
            $segs = array_values(array_filter(explode('/', trim($path, '/'))));
        }
        $id     = (isset($segs[3]) && ctype_digit((string)$segs[3])) ? (int)$segs[3] : null;
        $method = $_SERVER['REQUEST_METHOD'];

        $route = strtoupper($method) . ':' . ($resource ?? '') . ($id ? ':id' : '');

        switch ($route) {
            case 'GET:ping':
            case 'POST:ping':
                $token = $this->requireApiToken([]);
                $this->apiOk(['pong' => true, 'empresa_id' => (int)$token['empresa_id']]);
                break;

            case 'POST:pedidos':
                $token = $this->requireApiToken(['pedidos:crear']);
                $this->v1CrearPedido($token);
                break;

            case 'GET:pedidos:id':
                $token = $this->requireApiToken(['pedidos:leer']);
                $this->v1ConsultarPedido($token, $id);
                break;

            case 'GET:productos':
                $token = $this->requireApiToken(['productos:leer']);
                $this->v1BuscarProductos($token);
                break;

            case 'GET:productos:id':
                $token = $this->requireApiToken(['productos:leer']);
                $this->v1DetalleProducto($token, $id);
                break;

            default:
                $this->apiError('Recurso o método no encontrado', 404);
        }
    }

    // ── Helpers de la API v1 ───────────────────────────────────

    private function apiOk(array $data): void
    {
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    private function apiError(string $message, int $code = 400): void
    {
        http_response_code($code);
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }

    private function requireApiToken(array $scopesRequired): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
                  ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                  ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            $this->apiError('Authorization: Bearer <token> requerido', 401);
        }

        $rawToken = substr($header, 7);
        if ($rawToken === '') {
            $this->apiError('Token vacío', 401);
        }

        $hash = hash('sha256', $rawToken);
        $db   = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT id, empresa_id, comprador_id, nombre, scopes
               FROM api_tokens
              WHERE token = ? AND activo = 1
              LIMIT 1"
        );
        $stmt->execute([$hash]);
        $token = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$token) {
            $this->apiError('Token inválido o desactivado', 401);
        }

        $tokenScopes = json_decode($token['scopes'], true) ?? [];
        foreach ($scopesRequired as $scope) {
            if (!in_array($scope, $tokenScopes, true)) {
                $this->apiError("Permiso requerido: {$scope}", 403);
            }
        }

        try {
            $db->prepare(
                "INSERT INTO api_access_log (token_id, endpoint, metodo, ip, status)
                 VALUES (?, ?, ?, ?, 200)"
            )->execute([
                $token['id'],
                substr($_SERVER['REQUEST_URI'] ?? '', 0, 255),
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REMOTE_ADDR'] ?? '',
            ]);
            $db->prepare("UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = ?")
               ->execute([$token['id']]);
        } catch (\Throwable) {}

        return $token;
    }

    private function v1CrearPedido(array $token): void
    {
        $body  = json_decode(file_get_contents('php://input'), true) ?? [];
        $items = $body['items'] ?? [];
        $notas = trim($body['notas'] ?? '');

        if (empty($items) || !is_array($items)) {
            $this->apiError('El campo "items" es obligatorio y debe ser un array', 422);
        }

        $empresaId   = (int)$token['empresa_id'];
        $compradorId = (int)$token['comprador_id'];
        $db          = Database::getInstance();

        $lineas   = [];
        $subtotal = 0.0;

        foreach ($items as $idx => $item) {
            $productoId = (int)($item['producto_id'] ?? 0);
            $cantidad   = (float)($item['cantidad'] ?? 0);

            if (!$productoId || $cantidad <= 0) {
                $this->apiError("Item [{$idx}]: producto_id y cantidad son obligatorios", 422);
            }

            $stmt = $db->prepare(
                "SELECT id, nombre, precio_base, activo
                   FROM productos
                  WHERE id = ? AND empresa_id = ? AND activo = 1
                  LIMIT 1"
            );
            $stmt->execute([$productoId, $empresaId]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                $this->apiError("Producto {$productoId} no encontrado o inactivo", 404);
            }

            $precioUnit  = (float)$prod['precio_base'];
            $subLinea    = round($precioUnit * $cantidad, 2);
            $subtotal   += $subLinea;
            $lineas[]    = [
                'producto_id' => $productoId,
                'cantidad'    => $cantidad,
                'precio_unit' => $precioUnit,
                'subtotal'    => $subLinea,
            ];
        }

        $total = $subtotal;
        $folio = 'API-' . $empresaId . '-' . date('YmdHis') . '-' . rand(100, 999);

        try {
            $db->beginTransaction();

            $db->prepare(
                "INSERT INTO pedidos (folio, empresa_id, comprador_id, estado,
                                      subtotal, total, notas)
                 VALUES (?, ?, ?, 'pendiente', ?, ?, ?)"
            )->execute([$folio, $empresaId, $compradorId, $subtotal, $total, $notas ?: null]);

            $pedidoId = (int)$db->lastInsertId();

            $stmtDet = $db->prepare(
                "INSERT INTO pedido_detalle (pedido_id, producto_id, cantidad, precio_unit, subtotal)
                 VALUES (?, ?, ?, ?, ?)"
            );
            foreach ($lineas as $linea) {
                $stmtDet->execute([
                    $pedidoId,
                    $linea['producto_id'],
                    $linea['cantidad'],
                    $linea['precio_unit'],
                    $linea['subtotal'],
                ]);
            }

            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            $this->apiError('Error al crear el pedido: ' . $e->getMessage(), 500);
        }

        $this->apiOk([
            'pedido_id' => $pedidoId,
            'folio'     => $folio,
            'estado'    => 'pendiente',
            'subtotal'  => $subtotal,
            'total'     => $total,
            'items'     => count($lineas),
        ]);
    }

    private function v1ConsultarPedido(array $token, int $pedidoId): void
    {
        $empresaId = (int)$token['empresa_id'];
        $db        = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT id, folio, estado, subtotal, total, notas, created_at
               FROM pedidos
              WHERE id = ? AND empresa_id = ?
              LIMIT 1"
        );
        $stmt->execute([$pedidoId, $empresaId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido) {
            $this->apiError('Pedido no encontrado', 404);
        }

        $stmtDet = $db->prepare(
            "SELECT pd.producto_id, pr.nombre AS producto_nombre,
                    pd.cantidad, pd.precio_unit, pd.subtotal
               FROM pedido_detalle pd
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE pd.pedido_id = ?"
        );
        $stmtDet->execute([$pedidoId]);
        $detalle = $stmtDet->fetchAll(PDO::FETCH_ASSOC);

        $this->apiOk([
            'pedido_id'  => (int)$pedido['id'],
            'folio'      => $pedido['folio'],
            'estado'     => $pedido['estado'],
            'subtotal'   => (float)$pedido['subtotal'],
            'total'      => (float)$pedido['total'],
            'notas'      => $pedido['notas'],
            'created_at' => $pedido['created_at'],
            'items'      => array_map(static fn($row) => [
                'producto_id'     => (int)$row['producto_id'],
                'producto_nombre' => $row['producto_nombre'],
                'cantidad'        => (float)$row['cantidad'],
                'precio_unit'     => (float)$row['precio_unit'],
                'subtotal'        => (float)$row['subtotal'],
            ], $detalle),
        ]);
    }

    private function v1BuscarProductos(array $token): void
    {
        $empresaId = (int)$token['empresa_id'];
        $q         = trim($_GET['q'] ?? '');
        $catId     = (int)($_GET['categoria_id'] ?? 0);
        $limit     = min(max((int)($_GET['limit'] ?? 20), 1), 100);
        $db        = Database::getInstance();

        $sql    = "SELECT p.id, p.nombre, p.descripcion, p.presentacion,
                          p.precio_base, p.activo, c.nombre AS categoria
                     FROM productos p
                     LEFT JOIN categorias c ON c.id = p.categoria_id
                    WHERE p.empresa_id = ? AND p.activo = 1";
        $params = [$empresaId];

        if ($q !== '') {
            $sql     .= " AND p.nombre LIKE ?";
            $params[] = '%' . $q . '%';
        }
        if ($catId > 0) {
            $sql     .= " AND p.categoria_id = ?";
            $params[] = $catId;
        }

        $sql .= " ORDER BY p.nombre ASC LIMIT {$limit}";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $productos = array_map(static fn($r) => [
            'id'          => (int)$r['id'],
            'nombre'      => $r['nombre'],
            'descripcion' => $r['descripcion'],
            'presentacion'=> $r['presentacion'],
            'precio_base' => (float)$r['precio_base'],
            'categoria'   => $r['categoria'],
        ], $rows);

        $this->apiOk(['productos' => $productos, 'total' => count($productos)]);
    }

    private function v1DetalleProducto(array $token, int $productoId): void
    {
        $empresaId = (int)$token['empresa_id'];
        $db        = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT p.id, p.nombre, p.descripcion, p.presentacion,
                    p.precio_base, p.imagen, p.activo,
                    c.nombre AS categoria,
                    COALESCE(i.stock, 0) AS stock_actual
               FROM productos p
               LEFT JOIN categorias c ON c.id = p.categoria_id
               LEFT JOIN inventario i ON i.producto_id = p.id
              WHERE p.id = ? AND p.empresa_id = ?
              LIMIT 1"
        );
        $stmt->execute([$productoId, $empresaId]);
        $prod = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            $this->apiError('Producto no encontrado', 404);
        }

        $stmtEsc = $db->prepare(
            "SELECT cantidad_min, cantidad_max, precio
               FROM precios_escalonados
              WHERE producto_id = ?
              ORDER BY cantidad_min ASC"
        );
        $stmtEsc->execute([$productoId]);
        $escalonados = $stmtEsc->fetchAll(PDO::FETCH_ASSOC);

        $this->apiOk([
            'id'           => (int)$prod['id'],
            'nombre'       => $prod['nombre'],
            'descripcion'  => $prod['descripcion'],
            'presentacion' => $prod['presentacion'],
            'precio_base'  => (float)$prod['precio_base'],
            'imagen'       => $prod['imagen'],
            'categoria'    => $prod['categoria'],
            'stock_actual' => (float)$prod['stock_actual'],
            'precios_escalonados' => array_map(static fn($e) => [
                'cantidad_min' => (float)$e['cantidad_min'],
                'cantidad_max' => $e['cantidad_max'] !== null ? (float)$e['cantidad_max'] : null,
                'precio'       => (float)$e['precio'],
            ], $escalonados),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    // API — Integración CapiRest (Bearer token, sin sesión PHP)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Valida el Bearer token de la cabecera Authorization.
     */
    private function requireBearer(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
                  ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                  ?? $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION']
                  ?? '';

        error_log('[CarniHub API] requireBearer'
            . ' | URI='    . ($_SERVER['REQUEST_URI']    ?? '')
            . ' | METHOD=' . ($_SERVER['REQUEST_METHOD'] ?? '')
            . ' | HTTP_AUTHORIZATION='          . (isset($_SERVER['HTTP_AUTHORIZATION'])                        ? substr($_SERVER['HTTP_AUTHORIZATION'], 0, 40)                        : 'NO\_SET')
            . ' | REDIRECT_HTTP_AUTHORIZATION=' . (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])               ? substr($_SERVER['REDIRECT_HTTP_AUTHORIZATION'], 0, 40)               : 'NO\_SET')
            . ' | header_usado='                . (empty($header) ? 'VACIO' : substr($header, 0, 40))
        );

        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            http_response_code(401);
            header('Content-Type: application/json');
            error_log('[CarniHub API] requireBearer FALLO — header vacío o malformado'
                . ' | URI=' . ($_SERVER['REQUEST_URI'] ?? ''));
            echo json_encode(['ok' => false, 'error' => 'Token requerido']);
            exit;
        }

        $rawToken  = $m[1];
        $tokenHash = hash('sha256', $rawToken);

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT t.id, t.empresa_id, t.comprador_id, t.scopes, t.webhook_url, t.webhook_secret
               FROM api_tokens t
              WHERE t.token = ? AND t.activo = 1
              LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $tokenRow = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$tokenRow) {
            http_response_code(401);
            header('Content-Type: application/json');
            error_log('[CarniHub API] requireBearer FALLO — token no encontrado en BD'
                . ' | hash=' . $tokenHash
                . ' | raw_prefix=' . substr($rawToken, 0, 12));
            echo json_encode(['ok' => false, 'error' => 'Token inválido o inactivo']);
            exit;
        }

        $db->prepare('UPDATE api_tokens SET ultimo_uso = NOW() WHERE id = ?')
           ->execute([$tokenRow['id']]);

        try {
            $db->prepare(
                'INSERT INTO api_access_log (token_id, endpoint, metodo, ip, status) VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $tokenRow['id'],
                $_SERVER['REQUEST_URI'] ?? '',
                $_SERVER['REQUEST_METHOD'] ?? 'GET',
                $_SERVER['REMOTE_ADDR'] ?? '',
                200,
            ]);
        } catch (\Throwable $_) {}

        $tokenRow['scopes'] = json_decode($tokenRow['scopes'] ?? '[]', true) ?? [];
        return $tokenRow;
    }

    private function requireScope(array $tokenRow, string $scope): void
    {
        if (!in_array($scope, $tokenRow['scopes'], true)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => "Sin permiso: scope '$scope' requerido"]);
            exit;
        }
    }

    // ── POST /api/pedidos   — crear pedido desde CapiRest ────────────
    // ── GET  /api/pedidos/{id} — consultar estado de un pedido ───────
    public function pedidos(?string $id = null): void
    {
        $tokenRow  = $this->requireBearer();
        $empresaId = (int)$tokenRow['empresa_id'];
        $compradorId = (int)$tokenRow['comprador_id'];
        $method    = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'GET') {
            $this->requireScope($tokenRow, 'pedidos:leer');
            $pedidoId = (int)$id;
            if (!$pedidoId) {
                $this->json(['ok' => false, 'error' => 'ID de pedido requerido'], 400);
            }

            $db = Database::getInstance();
            try {
                $stmt = $db->prepare(
                    'SELECT id, capirest_pedido_id, folio, estado, subtotal, iva, total, created_at, updated_at
                       FROM pedidos
                      WHERE id = ? AND empresa_id = ?
                      LIMIT 1'
                );
                $stmt->execute([$pedidoId, $empresaId]);
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'updated_at')) {
                    $stmt = $db->prepare(
                        'SELECT id, capirest_pedido_id, folio, estado, subtotal, iva, total, created_at
                           FROM pedidos
                          WHERE id = ? AND empresa_id = ?
                          LIMIT 1'
                    );
                    $stmt->execute([$pedidoId, $empresaId]);
                } else {
                    throw $e;
                }
            }
            $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$pedido) {
                $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
            }

            $this->json(['ok' => true, 'pedido' => [
                'id'                 => (int)$pedido['id'],
                'capirest_pedido_id' => $pedido['capirest_pedido_id'] ? (int)$pedido['capirest_pedido_id'] : null,
                'folio'              => $pedido['folio'],
                'estado'             => $pedido['estado'],
                'subtotal'           => (float)$pedido['subtotal'],
                'iva'                => (float)$pedido['iva'],
                'total'              => (float)$pedido['total'],
                'created_at'         => $pedido['created_at'],
                'updated_at'         => $pedido['updated_at'] ?? null,
            ]]);
        }

        if ($method === 'POST') {
            $this->requireScope($tokenRow, 'pedidos:crear');

            $body = json_decode(file_get_contents('php://input'), true);
            if (!is_array($body)) {
                $this->json(['ok' => false, 'error' => 'Body JSON inválido'], 400);
            }

            $items              = $body['items'] ?? [];
            $capirestPedidoId   = isset($body['capirest_pedido_id']) ? (int)$body['capirest_pedido_id'] : null;
            $fechaEntrega       = !empty($body['fecha_entrega']) ? $body['fecha_entrega'] : null;
            $notas              = isset($body['notas']) ? substr(trim($body['notas']), 0, 500) : null;
            $compradorNombre    = isset($body['comprador_nombre'])    ? substr(trim((string)$body['comprador_nombre']), 0, 200)    : null;
            $compradorDireccion = isset($body['comprador_direccion']) ? substr(trim((string)$body['comprador_direccion']), 0, 500) : null;
            $compradorTelefono  = isset($body['comprador_telefono'])  ? substr(trim((string)$body['comprador_telefono']), 0, 30)   : null;
            $compradorLat       = isset($body['comprador_lat'])  && is_numeric($body['comprador_lat'])  ? (float)$body['comprador_lat']  : null;
            $compradorLng       = isset($body['comprador_lng'])  && is_numeric($body['comprador_lng'])  ? (float)$body['comprador_lng']  : null;

            if (empty($items) || !is_array($items)) {
                $this->json(['ok' => false, 'error' => 'Se requiere al menos un item'], 422);
            }

            $db     = Database::getInstance();
            $lineas = [];
            foreach ($items as $item) {
                $productoId = (int)($item['producto_id'] ?? 0);
                $cantidad   = (float)($item['cantidad'] ?? 0);
                $precioUnit = (float)($item['precio_unit'] ?? 0);

                if ($productoId <= 0 || $cantidad <= 0 || $precioUnit <= 0) {
                    $this->json(['ok' => false, 'error' => "Item inválido: producto_id=$productoId cantidad=$cantidad precio_unit=$precioUnit"], 422);
                }

                $stmt = $db->prepare(
                    'SELECT id FROM productos WHERE id = ? AND empresa_id = ? AND activo = 1 LIMIT 1'
                );
                $stmt->execute([$productoId, $empresaId]);
                if (!$stmt->fetch()) {
                    $this->json(['ok' => false, 'error' => "Producto $productoId no encontrado o inactivo"], 422);
                }

                $lineas[] = [
                    'producto_id' => $productoId,
                    'cantidad'    => $cantidad,
                    'precio_unit' => $precioUnit,
                    'subtotal'    => round($cantidad * $precioUnit, 2),
                ];
            }

            $model = new PedidoModel();
            try {
                $pedidoId = $model->crear(
                    [
                        'empresa_id'          => $empresaId,
                        'comprador_id'        => $compradorId,
                        'capirest_pedido_id'  => $capirestPedidoId,
                        'estado'              => 'pendiente',
                        'requiere_aprobacion' => 1,
                        'fecha_entrega'       => $fechaEntrega,
                        'notas'               => $notas,
                        'tipo'                => 'normal',
                        'comprador_direccion' => $compradorDireccion,
                        'comprador_telefono'  => $compradorTelefono,
                        'comprador_lat'       => $compradorLat,
                        'comprador_lng'       => $compradorLng,
                    ],
                    $lineas
                );
            } catch (\Throwable $e) {
                error_log('[ApiController::pedidos] Error al crear pedido: ' . $e->getMessage());
                $this->json(['ok' => false, 'error' => 'Error interno al crear pedido'], 500);
            }

            $this->json(['ok' => true, 'pedido_id' => $pedidoId], 201);
        }

        $this->json(['ok' => false, 'error' => 'Método no permitido'], 405);
    }

    // ── GET /api/productos — catálogo de la empresa del token ────────
    public function productos(?string $p = null): void
    {
        $tokenRow  = $this->requireBearer();
        $this->requireScope($tokenRow, 'productos:leer');

        $empresaId = (int)$tokenRow['empresa_id'];

        $buscar = substr(trim($this->get('buscar', $this->get('q', ''))), 0, 100);
        $page   = max(1, (int)$this->get('page', 1));
        $limit  = min(100, max(1, (int)$this->get('limit', 20)));
        $offset = ($page - 1) * $limit;

        $db = Database::getInstance();

        $where  = ['p.empresa_id = ?', 'p.activo = 1'];
        $params = [$empresaId];

        if ($buscar !== '') {
            $where[]  = '(p.nombre LIKE ? OR p.descripcion LIKE ?)';
            $t = '%' . $buscar . '%';
            array_push($params, $t, $t);
        }

        $sql = 'SELECT p.id, p.nombre, p.descripcion, p.presentacion, p.precio_base, p.imagen,
                       c.id AS categoria_id, c.nombre AS categoria
                  FROM productos p
                  LEFT JOIN categorias c ON c.id = p.categoria_id
                 WHERE ' . implode(' AND ', $where) . '
              ORDER BY p.nombre ASC
                 LIMIT ' . $limit . ' OFFSET ' . $offset;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $productos = array_map(static fn(array $r): array => [
            'id'           => (int)$r['id'],
            'nombre'       => $r['nombre'],
            'descripcion'  => $r['descripcion'],
            'presentacion' => $r['presentacion'],
            'precio_base'  => (float)$r['precio_base'],
            'imagen'       => $r['imagen'],
            'categoria_id' => $r['categoria_id'] !== null ? (int)$r['categoria_id'] : null,
            'categoria'    => $r['categoria'],
        ], $rows);

        $this->json(['ok' => true, 'page' => $page, 'limit' => $limit, 'productos' => $productos]);
    }

    // ══════════════════════════════════════════════════════════════════
    // Admin API v1 — Endpoints para el sitio web admin (JWT Bearer)
    // Autenticación: POST /api/auth/login devuelve JWT
    // Todas las demás requieren Authorization: Bearer <jwt>
    // Respuesta estándar: { success: true|false, message: "...", data: {...} }
    // ══════════════════════════════════════════════════════════════════

    private string $jwtSecret = 'amare_api_secret_key_2024_change_this_in_production_use_a_longer_random_string';

    /** POST /api/auth/login | GET /api/auth/token */
    public function auth(?string $subAction = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS: Permitir credenciales con origen específico
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            http_response_code(204); exit;
        }

        // GET /api/auth/token — Generar JWT si tienes sesión PHP activa
        if ($subAction === 'token' && $_SERVER['REQUEST_METHOD'] === 'GET') {
            // Verificar que hay sesión PHP activa con usuario logueado
            if (empty($_SESSION['usuario'])) {
                $this->adminApiError('No hay sesión activa. Por favor inicia sesión primero.', 401);
            }

            $usuario = $_SESSION['usuario'];
            
            // Solo admin puede generar JWT para APIs
            $rolValido = ($usuario['rol'] === 'admin' || ($usuario['rol_slug'] ?? '') === 'admin_restaurante');
            if (!$rolValido) {
                $this->adminApiError('Acceso denegado. Se requiere rol de administrador.', 403);
            }

            // Generar JWT y devolverlo
            $token = $this->generateJWT($usuario);
            $this->adminApiOk('Token generado', [
                'user'  => ['id' => (int)$usuario['id'], 'nombre' => $usuario['nombre'], 'email' => $usuario['email'], 'rol' => $usuario['rol']],
                'token' => $token,
            ]);
            return;
        }

        // POST /api/auth/login — Login con credenciales
        if ($subAction !== 'login' || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->adminApiError('Ruta no encontrada', 404);
        }
        $body     = json_decode(file_get_contents('php://input'), true) ?? [];
        $email    = trim($body['email'] ?? '');
        $password = $body['password'] ?? '';
        if (!$email || !$password) {
            $this->adminApiError('Email y contraseña son requeridos', 422);
        }
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario || !password_verify($password, $usuario['password'] ?? '')) {
            $this->adminApiError('Credenciales incorrectas', 401);
        }
        $rolValido = ($usuario['rol'] === 'admin' || ($usuario['rol_slug'] ?? '') === 'admin_restaurante');
        if (!$rolValido) {
            $this->adminApiError('Acceso denegado. Se requiere rol de administrador.', 403);
        }
        $token = $this->generateJWT($usuario);
        $this->adminApiOk('Login exitoso', [
            'user'  => ['id' => (int)$usuario['id'], 'nombre' => $usuario['nombre'], 'email' => $usuario['email'], 'rol' => $usuario['rol']],
            'token' => $token,
        ]);
    }

    /** Sub-router /api/admin/{resource} */
    public function admin(?string $resource = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        
        // CORS: Permitir credenciales desde el origen actual
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Credentials: true');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            http_response_code(204); exit;
        }
        
        // DEBUG: Log incoming request
        error_log('[admin] ' . $_SERVER['REQUEST_METHOD'] . ' ' . ($resource ?? 'null') . ' | Session: ' . (isset($_SESSION['usuario']) ? 'YES' : 'NO') . ' | Auth header: ' . (isset($_SERVER['HTTP_AUTHORIZATION']) ? 'YES' : 'NO'));
        
        $jwtUser = $this->requireAdminJWT();
        $method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }

        // Parsear resource compuesto: ej. "promotions/123/deactivate"
        $parts    = $resource ? array_values(array_filter(explode('/', $resource))) : [];
        $resType  = $parts[0] ?? null;
        $id       = (isset($parts[1]) && ctype_digit((string)$parts[1])) ? (int)$parts[1] : null;
        $subAct   = $parts[2] ?? null;

        switch ($resType) {
            case 'users':
                if ($method === 'GET') $this->adminListUsers($jwtUser);
                else $this->adminApiError('Método no permitido', 405);
                break;
            case 'promotions':
                $this->adminPromotionsRouter($method, $id, $subAct, $jwtUser);
                break;
            default:
                $this->adminApiError('Recurso no encontrado: ' . ($resType ?? 'null'), 404);
        }
    }

    /** PUT /api/branches/{id}/config */
    public function branches(?string $branchId = null): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Methods: PUT, OPTIONS');
            header('Access-Control-Allow-Headers: Authorization, Content-Type');
            http_response_code(204); exit;
        }
        $urlParam = trim($_GET['url'] ?? '', '/');
        $segs     = array_values(array_filter(explode('/', $urlParam)));
        $branchId = (isset($segs[2]) && ctype_digit((string)$segs[2])) ? (int)$segs[2] : null;
        $subAct   = $segs[3] ?? null;
        if (!$branchId || $subAct !== 'config' || $_SERVER['REQUEST_METHOD'] !== 'PUT') {
            $this->adminApiError('Ruta no encontrada', 404);
        }
        $jwtUser = $this->requireAdminJWT(false);
        $body    = json_decode(file_get_contents('php://input'), true) ?? [];
        $db      = Database::getInstance();
        $stmt = $db->prepare("SELECT id, empresa_id FROM sucursales WHERE id = ? LIMIT 1");
        $stmt->execute([$branchId]);
        $branch = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$branch) { $this->adminApiError('Sucursal no encontrada', 404); }
        if ((int)$branch['empresa_id'] !== (int)$jwtUser['empresa_id']) {
            $this->adminApiError('No tienes permiso para modificar esta sucursal', 403);
        }
        try {
            $sets = []; $params = [];
            if (isset($body['metodos_pago']))  { $sets[] = 'metodos_pago = ?';  $params[] = json_encode($body['metodos_pago']); }
            if (isset($body['tipos_entrega'])) { $sets[] = 'tipos_entrega = ?'; $params[] = json_encode($body['tipos_entrega']); }
            if (isset($body['costo_envio']))   { $sets[] = 'costo_envio = ?';   $params[] = (float)$body['costo_envio']; }
            if (isset($body['pedido_minimo'])) { $sets[] = 'pedido_minimo = ?'; $params[] = (float)$body['pedido_minimo']; }
            if (isset($body['activo']))        { $sets[] = 'activo = ?';        $params[] = $body['activo'] ? 1 : 0; }
            if (!empty($sets)) { $params[] = $branchId; $db->prepare("UPDATE sucursales SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params); }
            $this->adminApiOk('Configuración de sucursal actualizada correctamente');
        } catch (\Throwable $e) { $this->adminApiError('Error al actualizar: ' . $e->getMessage(), 500); }
    }

    // ── Admin API Helpers ────────────────────────────────────────

    private function adminApiOk(string $message, mixed $data = null): void
    {
        $resp = ['success' => true, 'message' => $message];
        if ($data !== null) $resp['data'] = $data;
        echo json_encode($resp); exit;
    }

    private function adminApiError(string $message, int $code = 400, ?array $errors = null): void
    {
        http_response_code($code);
        $resp = ['success' => false, 'message' => $message];
        if ($errors !== null) $resp['errors'] = $errors;
        echo json_encode($resp); exit;
    }

    private function requireAdminJWT(bool $requireAdmin = true): array
    {
        // 1) Si hay sesión PHP activa, usarla directamente (el usuario ya se autenticó via web)
        if (!empty($_SESSION['usuario'])) {
            $user = $_SESSION['usuario'];
            $rol = $user['rol'] ?? $user['rol_slug'] ?? '';
            if ($requireAdmin && !in_array($rol, ['admin', 'admin_restaurante', 'comprador', 'admin_local', 'superadmin'], true)) {
                $this->adminApiError('Acceso denegado. Se requiere rol de administrador.', 403);
            }
            return [
                'sub'        => (int)($user['id'] ?? 0),
                'nombre'     => $user['nombre'] ?? '',
                'email'      => $user['email'] ?? '',
                'rol'        => $rol,
                'empresa_id' => (int)($user['empresa_id'] ?? 0),
            ];
        }

        // 2) Fallback: validar JWT Bearer token
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($header), $m)) {
            $this->adminApiError('Token de autenticación requerido', 401);
        }
        $payload = $this->validateJWT($m[1]);
        if (!$payload) { $this->adminApiError('Token inválido o expirado', 401); }
        if ($requireAdmin && !in_array($payload['rol'] ?? '', ['admin', 'admin_restaurante'], true)) {
            $this->adminApiError('Acceso denegado. Se requiere rol de administrador.', 403);
        }
        return $payload;
    }

    public function generateJWT(array $user): string
    {
        error_log('WEB JWT SECRET=' . $this->jwtSecret);
        $header  = self::b64e(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $now     = time();
        $payload = self::b64e(json_encode([
            'sub' => (int)$user['id'], 'nombre' => $user['nombre'], 'email' => $user['email'],
            'rol' => $user['rol'] ?? $user['rol_slug'] ?? 'unknown', 'empresa_id' => (int)($user['empresa_id'] ?? 0),
            'iat' => $now, 'exp' => $now + 86400,
        ]));
        $sig = self::b64e(hash_hmac('sha256', "$header.$payload", $this->jwtSecret, true));
        return "$header.$payload.$sig";
    }

    private function validateJWT(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $payload, $signature] = $parts;
        $expected = self::b64e(hash_hmac('sha256', "$header.$payload", $this->jwtSecret, true));
        if (!hash_equals($expected, $signature)) return null;
        $data = json_decode(self::b64d($payload), true);
        if (!$data || ($data['exp'] ?? 0) < time()) return null;
        return $data;
    }

    private static function b64e(string $d): string { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
    private static function b64d(string $d): string { return base64_decode(strtr($d, '-_', '+/')); }

    // ── Admin: Users ─────────────────────────────────────────────

    private function adminListUsers(array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $search  = trim($this->get('search', ''));
        $page    = max(1, (int)$this->get('page', 1));
        $perPage = min(100, max(1, (int)$this->get('per_page', 50)));

        // Proxy: llamar a la API Amare para obtener usuarios móviles de esa sucursal
        $endpoint = "branches/{$branchId}/users?" . http_build_query([
            'search'   => $search,
            'page'     => $page,
            'per_page' => $perPage,
        ]);

        $result = $this->callAmareApi('GET', $endpoint);

        if (!$result['success']) {
            error_log('[adminListUsers] Falló API Amare: ' . ($result['error'] ?? 'Desconocido'));
            $this->adminApiError('No se pudieron obtener los usuarios de la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $data = $result['data'];
        // La API Amare responde { ok: true, data: { users: [...], pagination: {...} } }
        // o puede responder { ok: true, users: [...], pagination: {...} }
        $users      = $data['data']['users'] ?? $data['users'] ?? [];
        $pagination = $data['data']['pagination'] ?? $data['pagination'] ?? [];

        $this->adminApiOk('Usuarios obtenidos correctamente', [
            'users'      => $users,
            'pagination' => $pagination,
        ]);
    }

    // ── Admin: Promotions CRUD ───────────────────────────────────

    private function adminPromotionsRouter(string $method, ?int $id, ?string $subAction, array $jwtUser): void
    {
        if ($id === null) {
            match ($method) {
                'GET'  => $this->adminListPromotions($jwtUser),
                'POST' => $this->adminCreatePromotion($jwtUser),
                default => $this->adminApiError('Método no permitido', 405),
            };
            return;
        }
        match (true) {
            $method === 'GET'                               => $this->adminGetPromotion($id, $jwtUser),
            $method === 'PUT' && $subAction === 'deactivate' => $this->adminDeactivatePromotion($id, $jwtUser),
            $method === 'PUT'                               => $this->adminUpdatePromotion($id, $jwtUser),
            $method === 'DELETE'                            => $this->adminDeletePromotion($id, $jwtUser),
            default => $this->adminApiError('Método no permitido', 405),
        };
    }

    private function adminListPromotions(array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $page      = max(1, (int)$this->get('page', 1));
        $perPage   = min(100, max(1, (int)$this->get('per_page', 20)));
        $usuarioId = $this->get('usuario_id') ? (int)$this->get('usuario_id') : null;

        // Proxy: llamar a la API Amare para obtener promociones de esa sucursal
        $queryParams = [
            'page'     => $page,
            'per_page' => $perPage,
        ];
        if ($usuarioId) {
            $queryParams['usuario_id'] = $usuarioId;
        }

        $endpoint = "branches/{$branchId}/promotions?" . http_build_query($queryParams);

        $result = $this->callAmareApi('GET', $endpoint);

        if (!$result['success']) {
            error_log('[adminListPromotions] Falló API Amare: ' . ($result['error'] ?? 'Desconocido'));
            $this->adminApiError('No se pudieron obtener las promociones de la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $data = $result['data'];
        // La API Amare responde { ok: true, data: { promotions: [...], pagination: {...} } }
        $promotions = $data['data']['promotions'] ?? $data['promotions'] ?? [];
        $pagination = $data['data']['pagination'] ?? $data['pagination'] ?? [];

        $this->adminApiOk('Promociones obtenidas correctamente', [
            'promotions' => $promotions,
            'pagination' => $pagination,
        ]);
    }

    private function adminGetPromotion(int $id, array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $endpoint = "branches/{$branchId}/promotions/{$id}";
        $result = $this->callAmareApi('GET', $endpoint);

        if (!$result['success']) {
            if ($result['httpCode'] === 404) {
                $this->adminApiError('Promoción no encontrada', 404);
            }
            $this->adminApiError('No se pudo obtener la promoción de la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $data = $result['data'];
        $promotion = $data['data']['promotion'] ?? $data['promotion'] ?? $data;

        $this->adminApiOk('Promoción obtenida correctamente', $promotion);
    }

    private function adminCreatePromotion(array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $body   = $this->readAdminPromotionPayload();
        
        // Auto-llenar usuario_id desde JWT si no viene en el request
        if (empty($body['usuario_id'])) {
            $body['usuario_id'] = $jwtUser['sub'] ?? null;
        }
        
        $errors = $this->validatePromotionData($body, null);
        if (!empty($errors)) { $this->adminApiError('Error de validación', 422, $errors); }

        $endpoint = "branches/{$branchId}/promotions";
        $result = $this->callAmareApi('POST', $endpoint, $body);

        if (!$result['success']) {
            $data = $result['data'];
            if ($result['httpCode'] === 422 && !empty($data['errors'])) {
                $this->adminApiError($data['message'] ?? 'Error de validación', 422, $data['errors']);
            }
            if ($result['httpCode'] === 409 && str_contains($data['error'] ?? '', 'code')) {
                $this->adminApiError('Error de validación', 422, ['code' => ['El código ya está en uso por otra promoción.']]);
            }
            $this->adminApiError('No se pudo crear la promoción en la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $data = $result['data'];
        $promotion = $data['data']['promotion'] ?? $data['promotion'] ?? $data;

        $this->adminApiOk('Promoción creada correctamente', $promotion);
    }

    private function adminUpdatePromotion(int $id, array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $body   = $this->readAdminPromotionPayload();
        $errors = $this->validatePromotionData($body, $id);
        if (!empty($errors)) { $this->adminApiError('Error de validación', 422, $errors); }

        $endpoint = "branches/{$branchId}/promotions/{$id}";
        $result = $this->callAmareApi('PUT', $endpoint, $body);

        if (!$result['success']) {
            if ($result['httpCode'] === 404) {
                $this->adminApiError('Promoción no encontrada', 404);
            }
            $data = $result['data'];
            if ($result['httpCode'] === 422 && !empty($data['errors'])) {
                $this->adminApiError($data['message'] ?? 'Error de validación', 422, $data['errors']);
            }
            if ($result['httpCode'] === 409 && str_contains($data['error'] ?? '', 'code')) {
                $this->adminApiError('Error de validación', 422, ['code' => ['El código ya está en uso por otra promoción.']]);
            }
            $this->adminApiError('No se pudo actualizar la promoción en la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $data = $result['data'];
        $promotion = $data['data']['promotion'] ?? $data['promotion'] ?? $data;

        $this->adminApiOk('Promoción actualizada correctamente', $promotion);
    }

    private function adminDeletePromotion(int $id, array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $endpoint = "branches/{$branchId}/promotions/{$id}";
        $result = $this->callAmareApi('DELETE', $endpoint);

        if (!$result['success']) {
            if ($result['httpCode'] === 404) {
                $this->adminApiError('Promoción no encontrada', 404);
            }
            $this->adminApiError('No se pudo eliminar la promoción en la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $this->adminApiOk('Promoción eliminada correctamente');
    }

    private function adminDeactivatePromotion(int $id, array $jwtUser): void
    {
        $empresaId = (int)$jwtUser['empresa_id'];
        $branchId  = $this->getAmareBranchId($empresaId);

        if (!$branchId) {
            $this->adminApiError('No se encontró la sucursal vinculada para tu empresa en la API Amare.', 404);
        }

        $endpoint = "branches/{$branchId}/promotions/{$id}/deactivate";
        $result = $this->callAmareApi('PUT', $endpoint);

        if (!$result['success']) {
            if ($result['httpCode'] === 404) {
                $this->adminApiError('Promoción no encontrada', 404);
            }
            $this->adminApiError('No se pudo desactivar la promoción en la app móvil: ' . ($result['error'] ?? 'Error de conexión'), 502);
        }

        $this->adminApiOk('Promoción desactivada correctamente');
    }

    private function readAdminPromotionPayload(): array
    {
        if (!empty($_POST) || !empty($_FILES)) {
            $body = $_POST;
            unset($body['_method']);

            $imageValue = $this->handlePromotionImagePayload(
                $_FILES['imagen'] ?? null,
                !empty($body['remove_image'])
            );
            unset($body['remove_image']);

            if ($imageValue !== false) {
                $body['imagen'] = $imageValue;
            }

            return $this->normalizePromotionPayload($body);
        }

        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);
        return is_array($body) ? $this->normalizePromotionPayload($body) : [];
    }

    private function normalizePromotionPayload(array $body): array
    {
        foreach (['code', 'expires_at'] as $field) {
            if (array_key_exists($field, $body) && trim((string)$body[$field]) === '') {
                $body[$field] = null;
            }
        }

        if (isset($body['usuario_id'])) {
            $body['usuario_id'] = (int)$body['usuario_id'];
        }
        if (isset($body['activo'])) {
            $body['activo'] = (int)$body['activo'] ? 1 : 0;
        }

        return $body;
    }

    private function handlePromotionImagePayload(?array $file, bool $removeImage): string|null|false
    {
        if ($removeImage) {
            return null;
        }

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return false;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $this->adminApiError('No se pudo subir la imagen de la promoción.', 422, [
                'imagen' => ['La carga del archivo falló. Intenta nuevamente.'],
            ]);
        }

        if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
            $this->adminApiError('Error de validación', 422, [
                'imagen' => ['La imagen no debe exceder 5MB.'],
            ]);
        }

        $allowed = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
        ];

        $ext = strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
        $tmp = (string)($file['tmp_name'] ?? '');
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = (string)finfo_file($finfo, $tmp);
                finfo_close($finfo);
            }
        }

        if (!isset($allowed[$ext]) || ($mime !== '' && $mime !== $allowed[$ext])) {
            $this->adminApiError('Error de validación', 422, [
                'imagen' => ['La imagen debe ser JPG, PNG, WEBP o GIF.'],
            ]);
        }

        $uploadDir = ROOT_PATH . '/public/uploads/promociones';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            $this->adminApiError('No se pudo preparar el directorio de imágenes.', 500);
        }

        $filename = 'promo_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
        $dest = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($tmp, $dest)) {
            $this->adminApiError('No se pudo guardar la imagen de la promoción.', 500);
        }

        return rtrim(BASE_URL, '/') . '/public/uploads/promociones/' . $filename;
    }

    private function validatePromotionData(array $data, ?int $excludeId): array
    {
        $errors = [];
        
        // usuario_id es obligatorio en creación y edición (auto-llenado desde JWT en creación)
        if (empty($data['usuario_id'])) { 
            $errors['usuario_id'] = ['El usuario es obligatorio.']; 
        }
        
        // titulo es obligatorio en creación; en edición, es opcional (PUT permite actualización parcial)
        if ($excludeId === null && empty(trim($data['titulo'] ?? ''))) { 
            $errors['titulo'] = ['El título es obligatorio.']; 
        }
        elseif (isset($data['titulo']) && empty(trim($data['titulo']))) { 
            $errors['titulo'] = ['El título no puede estar vacío.']; 
        }
        elseif (isset($data['titulo']) && strlen(trim($data['titulo'])) > 255) { 
            $errors['titulo'] = ['El título no puede exceder los 255 caracteres.']; 
        }
        
        // code: validación de unicidad delegada a la API Amare (BD remota, validación 409)
        // Aquí solo validamos formato básico si es necesario. Amare retorna 409 si duplicado.
        if (!empty($data['code']) && !is_string($data['code'])) {
            $errors['code'] = ['El código debe ser una cadena de texto.'];
        }
        
        // expires_at: validar formato si viene (YYYY-MM-DD o YYYY-MM-DD HH:MM:SS)
        if (!empty($data['expires_at']) && !preg_match('/^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}:\d{2})?$/', $data['expires_at'])) {
            $errors['expires_at'] = ['Formato inválido. Use YYYY-MM-DD o YYYY-MM-DD HH:MM:SS.'];
        }
        
        return $errors;
    }

    // ══════════════════════════════════════════════════════════════════
    // Helpers para API Amare (App Móvil) — Proxy HTTP
    // ══════════════════════════════════════════════════════════════════

    /**
     * Obtiene la configuración de conexión con la API Amare.
     * @return array{url: string, token: string}|null null si no está configurada
     */
    private function getAmareConfig(): ?array
    {
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT clave, valor FROM global_settings WHERE clave IN ('amare_api_url','amare_api_token') AND grupo = 'pagos'"
        );
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['clave']] = $row['valor'] ?? '';
        }

        $url   = rtrim($settings['amare_api_url'] ?? '', '/');
        $token = $settings['amare_api_token'] ?? '';

        if (empty($url) || empty($token)) {
            return null;
        }

        return ['url' => $url, 'token' => $token];
    }

    /**
     * Obtiene el branch_id (sucursal) de Amare correspondiente al restaurante.
     */
    private function getAmareBranchId(int $empresaId): ?int
    {
        $db = Database::getInstance();

        // Intentar columna sucursal_id primero, luego sucursal_carnihub_id
        try {
            $stmt = $db->prepare("SHOW COLUMNS FROM `rest_restaurantes` LIKE 'sucursal_id'");
            $stmt->execute();
            $col = 'sucursal_id';
            if (!$stmt->fetch()) {
                $stmt2 = $db->prepare("SHOW COLUMNS FROM `rest_restaurantes` LIKE 'sucursal_carnihub_id'");
                $stmt2->execute();
                if ($stmt2->fetch()) {
                    $col = 'sucursal_carnihub_id';
                } else {
                    return null;
                }
            }
        } catch (\Throwable $e) {
            return null;
        }

        $stmt = $db->prepare("SELECT {$col} FROM rest_restaurantes WHERE empresa_id = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int)($row[$col] ?? 0) : null;
    }

    /**
     * Realiza una llamada HTTP a la API Amare.
     * @return array{success: bool, httpCode: int, data: array|null, error: string|null}
     */
    private function callAmareApi(string $method, string $endpoint, ?array $body = null): array
    {
        $config = $this->getAmareConfig();
        if (!$config) {
            return ['success' => false, 'httpCode' => 0, 'data' => null, 'error' => 'API Amare no configurada. Configura amare_api_url y amare_api_token en Configuración.'];
        }

        $url = $config['url'] . '/' . ltrim($endpoint, '/');

        $ch = curl_init($url);
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $config['token'],
            'Accept: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        if ($method === 'POST' || $method === 'PUT') {
            $opts[CURLOPT_CUSTOMREQUEST] = $method;
            if ($body !== null) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
            }
        } elseif ($method === 'DELETE') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $opts);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[callAmareApi] cURL error: ' . $error . ' | URL: ' . $url);
            return ['success' => false, 'httpCode' => 0, 'data' => null, 'error' => 'Error de conexión con la API Amare: ' . $error];
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return ['success' => false, 'httpCode' => $httpCode, 'data' => null, 'error' => 'Respuesta inválida de la API Amare (HTTP ' . $httpCode . ')'];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'httpCode' => $httpCode, 'data' => $decoded, 'error' => null];
        }

        return ['success' => false, 'httpCode' => $httpCode, 'data' => $decoded, 'error' => $decoded['error'] ?? $decoded['message'] ?? 'Error HTTP ' . $httpCode];
    }
}
