<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestInventarioController extends BaseController
{
    private RestInventarioModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestInventarioModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId    = $this->restauranteId();
        $ingredientes     = $this->model->getByRestaurante($restauranteId, true);
        $alertas          = $this->model->alertasStockBajo($restauranteId);
        $flash            = $this->getFlash();

        // Recent movements (last 10)
        $movRecientes = [];
        try {
            $resultado = $this->model->getMovimientos($restauranteId, 1);
            $movRecientes = array_slice($resultado['movimientos'] ?? [], 0, 10);
        } catch (\Throwable $e) {}

        $pageTitle        = 'Ingredientes';
        $activeMenu       = 'rest_inventario';

        // Obtener la empresa proveedora vinculada al restaurante
        $empresaProveedorId   = null;
        $empresaProveedorNombre = null;
        try {
            $db   = Database::getInstance();
            $stmtRest = $db->prepare(
                "SELECT rr.empresa_proveedor_id, e.razon_social
                 FROM rest_restaurantes rr
                 LEFT JOIN empresas e ON e.id = rr.empresa_proveedor_id
                 WHERE rr.id = ?"
            );
            $stmtRest->execute([$restauranteId]);
            $restRow = $stmtRest->fetch(PDO::FETCH_ASSOC);
            if ($restRow) {
                $empresaProveedorId     = $restRow['empresa_proveedor_id'] ? (int)$restRow['empresa_proveedor_id'] : null;
                $empresaProveedorNombre = $restRow['razon_social'] ?? null;
            }
        } catch (\Throwable $e) {}

        // Productos CarniHub disponibles para vincular a ingredientes
        $productosCarnihub = [];
        $carnihubDebug     = [];   // Diagnóstico: ?debug_carnihub=1 lo muestra
        if (defined('RESTAURANTE_STANDALONE') && RESTAURANTE_STANDALONE) {
            // Standalone: obtener catálogo vía API de CarniHub.
            // Estrategia: primero intentamos paginación normal (page=1..N).
            // Si el servidor remoto ignora paginación (bug histórico) y devuelve
            // siempre los mismos productos, completamos con búsqueda por letra.
            try {
                $apiService  = new CarniHubApiService();
                $grupNombre  = $empresaProveedorNombre ?? 'CarniHub';
                $vistos      = [];
                $perPage     = 100;

                $extraerLote = function($result) {
                    if (!empty($result['productos']) && is_array($result['productos'])) return $result['productos'];
                    if (!empty($result['data']['productos']) && is_array($result['data']['productos'])) return $result['data']['productos'];
                    if (!empty($result['data']) && is_array($result['data']) && array_is_list($result['data'])) return $result['data'];
                    return [];
                };
                $acumular = function(array $lote) use (&$productosCarnihub, &$vistos, $grupNombre) {
                    $nuevos = 0;
                    foreach ($lote as $prod) {
                        $pid = (int)($prod['id'] ?? 0);
                        if ($pid <= 0 || isset($vistos[$pid])) continue;
                        $precio = (float)($prod['precio_comprador'] ?? $prod['precio_base'] ?? $prod['precio'] ?? 0);
                        $vistos[$pid] = true;
                        $productosCarnihub[] = [
                            'id'             => $pid,
                            'nombre'         => $prod['nombre'] ?? '',
                            'unidad'         => $prod['presentacion'] ?? $prod['unidad'] ?? '',
                            'empresa_nombre' => $grupNombre,
                            'precio'         => $precio,
                            'categoria'      => $prod['categoria'] ?? '',
                        ];
                        $nuevos++;
                    }
                    return $nuevos;
                };

                // 1) Paginación normal: hasta 20 páginas o hasta que no haya nuevos
                for ($page = 1; $page <= 20; $page++) {
                    $result = $apiService->buscarProducto($restauranteId, '', '', $page, $perPage);
                    if (isset($result['success']) && $result['success'] === false) {
                        $carnihubDebug[] = ['page' => $page, 'api_error' => $result['error'] ?? 'unknown'];
                        error_log('[RestInventario CarniHub API] ' . ($result['error'] ?? 'sin detalle'));
                        break;
                    }
                    $lote   = $extraerLote($result);
                    $antes  = count($productosCarnihub);
                    $nuevos = $acumular($lote);
                    $carnihubDebug[] = [
                        'page' => $page, 'lote_count' => count($lote),
                        'nuevos' => $nuevos, 'acumulado' => count($productosCarnihub),
                    ];
                    if (empty($lote) || $nuevos === 0) break;
                }

                // 2) Fallback por letra si la paginación no entregó suficiente variedad
                if (count($productosCarnihub) < $perPage) {
                    $consultas = [
                        'a','b','c','d','e','f','g','h','i','j','k','l','m',
                        'n','o','p','q','r','s','t','u','v','w','x','y','z',
                        'á','é','í','ó','ú','ñ','0','1','2','3','4','5','6','7','8','9',
                    ];
                    foreach ($consultas as $q) {
                        $result = $apiService->buscarProducto($restauranteId, $q, '', 1, $perPage);
                        $lote   = $extraerLote($result);
                        $nuevos = $acumular($lote);
                        $carnihubDebug[] = [
                            'q' => $q, 'lote_count' => count($lote),
                            'nuevos' => $nuevos, 'acumulado' => count($productosCarnihub),
                        ];
                    }
                }

                $carnihubDebug[] = ['TOTAL_UNICOS' => count($productosCarnihub)];
                usort($productosCarnihub, function($a, $b){
                    return strcasecmp($a['nombre'] ?? '', $b['nombre'] ?? '');
                });
            } catch (\Throwable $e) {
                $carnihubDebug[] = ['exception' => $e->getMessage()];
                error_log('[RestInventario CarniHub] ' . $e->getMessage());
            }
        } else {
            // Instalación integrada: leer catálogo de la BD local.
            // IMPORTANTE: traemos SIEMPRE todos los productos activos (no
            // sólo los de la empresa proveedora asignada al restaurante),
            // para que la lista del modal y el match-exacto contemplen
            // cualquier producto disponible en CarniHub. Si hay empresa
            // preferida, la ordenamos primero.
            try {
                if (!isset($db)) $db = Database::getInstance();
                if ($empresaProveedorId) {
                    $stmt = $db->prepare(
                        "SELECT p.id, p.nombre, p.presentacion AS unidad, p.precio_base AS precio,
                                e.razon_social AS empresa_nombre
                         FROM productos p
                         LEFT JOIN empresas e ON e.id = p.empresa_id
                         WHERE p.activo = 1
                         ORDER BY (p.empresa_id = ?) DESC, p.nombre ASC, p.id ASC"
                    );
                    $stmt->execute([$empresaProveedorId]);
                } else {
                    $stmt = $db->query(
                        "SELECT p.id, p.nombre, p.presentacion AS unidad, p.precio_base AS precio,
                                e.razon_social AS empresa_nombre
                         FROM productos p
                         LEFT JOIN empresas e ON e.id = p.empresa_id
                         WHERE p.activo = 1
                         ORDER BY p.nombre ASC, p.id ASC"
                    );
                }
                $productosCarnihub = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (\Throwable $e) {}
        }

        $inactivos = $this->model->getInactivos($restauranteId);

        // Diagnóstico opcional: agregar ?debug_carnihub=1 a la URL
        if (!empty($_GET['debug_carnihub'])) {
            header('Content-Type: text/plain; charset=utf-8');
            echo "=== Diagnóstico CarniHub (modo " . ((defined('RESTAURANTE_STANDALONE') && RESTAURANTE_STANDALONE) ? 'STANDALONE' : 'INTEGRADO') . ") ===\n";
            echo "Productos cargados al modal: " . count($productosCarnihub) . "\n\n";
            echo "Páginas pedidas al API:\n";
            echo json_encode($carnihubDebug, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
            return;
        }

        $this->render('restaurante/inventario/index', compact(
            'ingredientes','alertas','productosCarnihub','empresaProveedorId','empresaProveedorNombre',
            'movRecientes','flash','pageTitle','activeMenu','inactivos'
        ));
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-inventario/index');
        $restauranteId = $this->restauranteId();

        $id              = (int)$this->post('id');
        $esCarnihub      = (int)(bool)$this->post('proveedor_carnihub', 0);
        $carnihubProdId  = $esCarnihub ? ((int)$this->post('carnihub_producto_id') ?: null) : null;
        $costoPosteado   = (float)$this->post('costo_unitario', 0);
        $precioRemoto    = 0.0;

        // Si viene de CarniHub, resolver nombre y unidad
        if ($esCarnihub && $carnihubProdId) {
            if (!defined('RESTAURANTE_STANDALONE') || !RESTAURANTE_STANDALONE) {
                // Instalación B2B: la tabla 'productos' existe localmente
                $db   = Database::getInstance();
                $stmt = $db->prepare("SELECT nombre, presentacion, precio_base FROM productos WHERE id = ? AND activo = 1");
                $stmt->execute([$carnihubProdId]);
                $prod   = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                $nombre = $prod['nombre'] ?? $this->post('nombre', '');
                $unidad = $prod['presentacion'] ?? $this->post('unidad_principal', 'kg');
                $precioRemoto = (float)($prod['precio_base'] ?? 0);
            } else {
                // Standalone: nombre y unidad vienen del formulario (JS los llena al seleccionar)
                $nombre = trim($this->post('nombre', ''));
                $unidad = $this->post('unidad_principal', 'kg');

                try {
                    require_once ROOT_PATH . '/app/services/CarniHubApiService.php';
                    $apiService   = new CarniHubApiService();
                    $detalle      = $apiService->detalleProducto($restauranteId, (int)$carnihubProdId);
                    $precioRemoto = $this->extraerPrecioDetalleProducto($detalle);
                } catch (\Throwable $e) {
                    error_log('[guardar ingrediente CarniHub] ' . $e->getMessage());
                }
            }
        } else {
            $nombre = trim($this->post('nombre', ''));
            $unidad = $this->post('unidad_principal', 'kg');
        }

        $stockMinimo = $esCarnihub
            ? (float)$this->post('stock_minimo_ch', 0)
            : (float)$this->post('stock_minimo', 0);
        $stockInicial = $esCarnihub
            ? (float)$this->post('stock_inicial_ch', 0)
            : (float)$this->post('stock_inicial', 0);

        $data = [
            'restaurante_id'      => $restauranteId,
            'nombre'              => $nombre,
            'codigo'              => $this->post('codigo') ?: null,
            'tipo'                => $this->post('tipo') ?: null,
            'unidad_principal'    => $unidad,
            'costo_unitario'      => $costoPosteado,
            'stock_minimo'        => $stockMinimo,
            'categoria'           => $this->post('categoria') ?: null,
            'proveedor_carnihub'  => $esCarnihub,
            'carnihub_producto_id'=> $carnihubProdId,
            'proveedor_nombre'    => !$esCarnihub ? ($this->post('proveedor_nombre') ?: null) : null,
        ];

        if ($precioRemoto > 0) {
            $data['costo_unitario'] = $precioRemoto;
        }

        if ($id) {
            $this->model->update($id, array_diff_key($data, ['restaurante_id' => '']));
        } else {
            $ingId = $this->model->insert($data);
            // Registrar stock inicial si > 0
            if ($stockInicial > 0) {
                $this->model->ajustarStock(
                    $ingId, $stockInicial, 'entrada',
                    'Stock inicial', null, $restauranteId, $this->usuarioId()
                );
            }
        }

        $this->flash('success', 'Ingrediente guardado.');
        $this->redirect('rest-inventario/index');
    }

    public function movimiento(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-inventario/index');

        $ingredienteId = (int)$this->post('ingrediente_id');
        $tipo          = $this->post('tipo', 'entrada');
        $cantidad      = abs((float)$this->post('cantidad', 0));
        $delta         = in_array($tipo, ['salida','merma']) ? -$cantidad : $cantidad;

        $this->model->ajustarStock(
            $ingredienteId,
            $delta,
            $tipo,
            $this->post('motivo', ''),
            null,
            $this->restauranteId(),
            $this->usuarioId()
        );

        $this->flash('success', 'Movimiento registrado.');
        $this->redirect('rest-inventario/index');
    }

    public function movimientos(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $page          = (int)($this->get('page', 1));
        $resultado     = $this->model->getMovimientos($restauranteId, $page);
        $flash         = $this->getFlash();
        $pageTitle     = 'Movimientos de Inventario';
        $activeMenu    = 'rest_inventario';
        $this->render('restaurante/inventario/movimientos', array_merge($resultado, compact('flash','pageTitle','activeMenu')));
    }

    public function eliminar(?string $id = null): void
    {
        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $restauranteId = $this->restauranteId();
        $ing = $this->model->find((int)$id);

        if (!$ing || (int)($ing['restaurante_id'] ?? 0) !== $restauranteId) {
            if ($isAjax) $this->json(['ok' => false, 'error' => 'No autorizado']);
            $this->flash('error', 'Ingrediente no encontrado.');
            $this->redirect('rest-inventario/index');
            return;
        }

        $this->model->update((int)$id, ['activo' => 0]);

        if ($isAjax) $this->json(['ok' => true]);

        $this->flash('success', 'Ingrediente eliminado.');
        $this->redirect('rest-inventario/index');
    }

    public function reactivar(?string $id = null): void
    {
        $restauranteId = $this->restauranteId();
        $ing = $this->model->find((int)$id);
        if (!$ing || (int)($ing['restaurante_id'] ?? 0) !== $restauranteId) {
            $this->redirect('rest-inventario/index');
        }
        $this->model->update((int)$id, ['activo' => 1]);
        $this->flash('success', 'Ingrediente restaurado al inventario.');
        $this->redirect('rest-inventario/index');
    }

    /** Endpoint JSON — devuelve stocks actuales para polling en tiempo real */
    public function stocks(?string $p = null): void
    {
        $rows = $this->model->getByRestaurante($this->restauranteId(), true);
        $this->json(array_map(fn($r) => [
            'id'               => (int)$r['id'],
            'stock'            => (float)$r['stock'],
            'stock_minimo'     => (float)$r['stock_minimo'],
            'unidad_principal' => $r['unidad_principal'],
        ], $rows));
    }

    // ── SISTEMA DE FORECAST Y PEDIDOS AUTOMÁTICOS ────────────────

    /**
     * Dashboard de proyección inteligente de inventario.
     */
    public function proyecciones(?string $p = null): void
    {
        require_once ROOT_PATH . '/app/services/RestForecastService.php';
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';

        $restauranteId = $this->restauranteId();
        $ingredientes  = $this->model->getByRestaurante($restauranteId, true);

        $forecast     = new RestForecastService();
        $analisis     = $forecast->analizarIngredientes($ingredientes, $restauranteId);
        $criticos     = array_filter($analisis, fn($i) => $i['nivel_alerta'] === 'critico');
        $advertencias = array_filter($analisis, fn($i) => $i['nivel_alerta'] === 'advertencia');
        $analisisParaPedido = $this->filtrarAnalisisPedidosAbiertos($analisis, $restauranteId);

        // ── AUTO-GENERAR PEDIDO ─────────────────────────────────────────
        $comprobante    = null;
        $ultimoPedidoAt = null;
        $forzar         = (bool)$this->get('forzar', 0);
        $db = \Database::getInstance();

        // Cooldown 12h: incluir pedidos ya convertidos (enviados a CarniHub) para evitar duplicados
        $stCheck = $db->prepare(
            "SELECT MAX(created_at) FROM rest_pedidos_sugeridos
             WHERE restaurante_id = ?
               AND estado IN ('sugerido','aprobado','convertido')
               AND created_at >= DATE_SUB(NOW(), INTERVAL 12 HOUR)"
        );
        $stCheck->execute([$restauranteId]);
        $ultimoPedidoAt = $stCheck->fetchColumn() ?: null;

        if (!$ultimoPedidoAt || $forzar) {
            $grupos = $forecast->agruparPorEmpresa($analisisParaPedido);
            if (!empty($grupos)) {
                $comprobante = $this->_autoGenerarPedidos($restauranteId, $grupos);
                if (!empty($comprobante)) $ultimoPedidoAt = date('Y-m-d H:i:s');
            }
        }

        $flash      = $this->getFlash();
        $pageTitle  = 'Proyección de Inventario';
        $activeMenu = 'rest_inventario';

        $this->render('restaurante/inventario/proyecciones', compact(
            'analisis', 'criticos', 'advertencias', 'comprobante', 'ultimoPedidoAt',
            'flash', 'pageTitle', 'activeMenu'
        ));
    }

    /**
     * Historial de pedidos generados automáticamente por el sistema de forecast.
     */
    public function pedidosSugeridos(?string $p = null): void
    {
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        $restauranteId = $this->restauranteId();
        $pedidoModel   = new RestPedidoSugeridoModel();
        $pedidos       = $pedidoModel->getByRestaurante($restauranteId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Historial de Pedidos Automáticos';
        $activeMenu = 'rest_inventario';

        $this->render('restaurante/inventario/pedidos_sugeridos', compact(
            'pedidos', 'flash', 'pageTitle', 'activeMenu'
        ));
    }

    /**
     * GET /rest-inventario/detalleSugerido/{id}
     * Devuelve pedido + items para el modal informativo.
     */
    public function detalleSugerido(?string $id = null): void
    {
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';

        $pedidoId      = (int)$id;
        $restauranteId = $this->restauranteId();
        $pedidoModel   = new RestPedidoSugeridoModel();

        $pedido = $pedidoModel->findConItems($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== $restauranteId) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $items = array_map(static function (array $it): array {
            $cant = (float)($it['cantidad_aprobada'] ?? $it['cantidad_sugerida'] ?? 0);
            $precio = (float)($it['precio_unit_estimado'] ?? 0);
            return [
                'id' => (int)($it['id'] ?? 0),
                'ingrediente_id' => (int)($it['ingrediente_id'] ?? 0),
                'nombre' => $it['ingrediente_nombre'] ?? '',
                'unidad' => $it['unidad'] ?? ($it['unidad_principal'] ?? ''),
                'cantidad' => $cant,
                'precio_unit' => $precio,
                'subtotal' => round($cant * $precio, 2),
            ];
        }, $pedido['items'] ?? []);

        $canCancel = in_array($pedido['estado'], ['sugerido', 'aprobado', 'convertido'], true)
            && !in_array($pedido['estado_carnihub'] ?? '', ['aprobado', 'en_camino', 'entregado'], true);

        $this->json([
            'ok' => true,
            'pedido' => [
                'id' => (int)$pedido['id'],
                'estado' => (string)$pedido['estado'],
                'estado_carnihub' => $pedido['estado_carnihub'] ?? null,
                'pedido_carnihub_id' => (int)($pedido['pedido_carnihub_id'] ?? 0),
                'empresa_nombre' => $pedido['empresa_nombre'] ?? null,
                'total_estimado' => (float)($pedido['total_estimado'] ?? 0),
                'created_at' => $pedido['created_at'] ?? null,
                'aprobado_at' => $pedido['aprobado_at'] ?? null,
                'notas' => $pedido['notas'] ?? null,
                'can_cancel' => $canCancel,
                'items' => $items,
            ],
        ]);
    }

    /**
     * Genera pedidos forzados vía AJAX (sin cooldown). Responde JSON.
     */
    public function generarPedidoAutomatico(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->json(['ok' => false, 'error' => 'Método no permitido'], 405);
        }

        require_once ROOT_PATH . '/app/services/RestForecastService.php';
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';

        $restauranteId = $this->restauranteId();
        $ingredientes  = $this->model->getByRestaurante($restauranteId, true);

        $forecast = new RestForecastService();
        $analisis = $forecast->analizarIngredientes($ingredientes, $restauranteId);
        $analisis = $this->filtrarAnalisisPedidosAbiertos($analisis, $restauranteId);
        $grupos   = $forecast->agruparPorEmpresa($analisis);

        if (empty($grupos)) {
            $this->json(['ok' => false, 'error' => 'No hay ingredientes críticos con proveedor CarniHub vinculado.']);
        }

        $creados = $this->_autoGenerarPedidos($restauranteId, $grupos);

        if (empty($creados)) {
            $this->json(['ok' => false, 'error' => 'No se pudieron crear los pedidos. Verifica los ingredientes vinculados.']);
        }

        $this->json(['ok' => true, 'pedidos' => $creados]);
    }

    /**
     * Crea pedidos Y los envía inmediatamente a CarniHub (flujo 100 % automático).
     * Si la llamada a la API falla, el pedido queda en 'sugerido' para reintento manual.
     *
     * @param int   $restauranteId
     * @param array $grupos  resultado de RestForecastService::agruparPorEmpresa()
     * @return array pedidos creados [{pedido_sugerido_id, folio, empresa, total, enviado, items, carnihub_error?}]
     */
    private function _autoGenerarPedidos(int $restauranteId, array $grupos): array
    {
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        require_once ROOT_PATH . '/app/services/CarniHubApiService.php';
        $pedidoModel = new RestPedidoSugeridoModel();
        $apiService  = new CarniHubApiService();
        $compradorId = $this->usuarioId();

        // Datos del restaurante para la dirección de entrega en CarniHub
        $db    = \Database::getInstance();
        $stRest = $db->prepare(
            "SELECT nombre, direccion, telefono, lat, lng FROM rest_restaurantes WHERE id = ? LIMIT 1"
        );
        $stRest->execute([$restauranteId]);
        $restData = $stRest->fetch(\PDO::FETCH_ASSOC) ?: [];
        $compradorInfo = array_filter([
            'comprador_nombre'    => $restData['nombre']    ?? '',
            'comprador_direccion' => $restData['direccion'] ?? '',
            'comprador_telefono'  => $restData['telefono']  ?? '',
            'comprador_lat'       => $restData['lat']       ?? null,
            'comprador_lng'       => $restData['lng']       ?? null,
        ], fn($v) => $v !== null && $v !== '');

        $creados = [];
        $precioCacheCarnihub = [];
        $ingredientesBloqueados = array_fill_keys(
            $pedidoModel->getIngredientesConPedidoAbierto($restauranteId),
            true
        );

        foreach ($grupos as $empresaId => $grupo) {
            $items    = [];
            $subtotal = 0.0;

            foreach ($grupo['items'] as $ing) {
                $ingredienteId = (int)$ing['id'];
                if (isset($ingredientesBloqueados[$ingredienteId])) {
                    continue;
                }

                $carnihubProductoId = (int)($ing['carnihub_producto_id'] ?? 0);
                $precioRemoto = 0.0;
                if ($carnihubProductoId > 0) {
                    if (array_key_exists($carnihubProductoId, $precioCacheCarnihub)) {
                        $precioRemoto = (float)$precioCacheCarnihub[$carnihubProductoId];
                    } else {
                        try {
                            $detalle = $apiService->detalleProducto($restauranteId, $carnihubProductoId);
                            $precioRemoto = $this->extraerPrecioDetalleProducto($detalle);
                        } catch (\Throwable $e) {
                            $precioRemoto = 0.0;
                            error_log('[_autoGenerarPedidos] detalleProducto error prod #' . $carnihubProductoId . ': ' . $e->getMessage());
                        }
                        $precioCacheCarnihub[$carnihubProductoId] = $precioRemoto;
                    }
                }

                $precio = $precioRemoto > 0
                    ? $precioRemoto
                    : (float)($ing['empresa']['precio_base'] ?? $ing['costo_unitario'] ?? 0);
                $cant   = (float)$ing['cantidad_sugerida'];
                $sub    = round($cant * $precio, 2);
                $subtotal += $sub;
                $items[] = [
                    'ingrediente_id'       => (int)$ing['id'],
                    'carnihub_producto_id' => $carnihubProductoId,
                    'cantidad_sugerida'    => $cant,
                    'unidad'               => $ing['unidad_principal'],
                    'precio_unit_estimado' => $precio,
                    'subtotal_estimado'    => $sub,
                    '_nombre'              => $ing['nombre'] ?? ('Ingrediente #' . $ing['id']),
                ];
            }

            if (empty($items)) continue;

            try {
                $notas    = 'Pedido automático · Forecast · ' . date('d/m/Y H:i');
                $pedidoId = $pedidoModel->crear([
                    'restaurante_id'      => $restauranteId,
                    'carnihub_empresa_id' => $empresaId,
                    'notas'               => $notas,
                    'usuario_id'          => $compradorId,
                ], $items);

                // ── Envío inmediato a CarniHub ──────────────────────────────
                $apiItems = [];
                foreach ($items as $it) {
                    $prodId = (int)$it['carnihub_producto_id'];
                    $cant   = (float)$it['cantidad_sugerida'];
                    $precio = (float)$it['precio_unit_estimado'];
                    if ($prodId > 0 && $cant > 0 && $precio > 0) {
                        $apiItems[] = ['producto_id' => $prodId, 'cantidad' => $cant, 'precio_unit' => $precio];
                    }
                }

                $folio         = null;
                $chPedidoId    = 0;
                $carnihubError = null;

                if (!empty($apiItems)) {
                    $resultado = $apiService->crearPedido($restauranteId, $apiItems, $notas, $compradorInfo, $pedidoId);
                    if ($resultado['success'] ?? false) {
                        $chPedidoId = (int)($resultado['pedido_id'] ?? $resultado['id'] ?? 0);
                        $folio      = $resultado['folio'] ?? ('CH-' . $chPedidoId);
                        $pedidoModel->marcarConvertido($pedidoId, $chPedidoId);
                    } else {
                        $carnihubError = $resultado['error'] ?? 'Error desconocido';
                        error_log('[_autoGenerarPedidos] Error API CarniHub (pedido #' . $pedidoId . '): ' . $carnihubError);
                    }
                } else {
                    $carnihubError = 'Ningún item tiene producto CarniHub vinculado con precio válido';
                }

                // Items para el ticket en vista proyecciones
                $ticketItems = array_map(fn($it) => [
                    'nombre'   => $it['_nombre'],
                    'cantidad' => $it['cantidad_sugerida'],
                    'unidad'   => $it['unidad'],
                    'precio'   => $it['precio_unit_estimado'],
                    'subtotal' => $it['subtotal_estimado'],
                ], $items);

                $entrada = [
                    'pedido_sugerido_id' => $pedidoId,
                    'empresa'            => $grupo['empresa']['razon_social'] ?? 'CarniHub',
                    'folio'              => $folio ?? ('LOCAL-' . $pedidoId),
                    'total'              => $subtotal,
                    'items_count'        => count($items),
                    'enviado'            => ($folio !== null),
                    'items'              => $ticketItems,
                ];
                if ($carnihubError) {
                    $entrada['carnihub_error'] = $carnihubError;
                }
                $creados[] = $entrada;

                foreach ($items as $it) {
                    $ingredientesBloqueados[(int)$it['ingrediente_id']] = true;
                }

            } catch (\Throwable $e) {
                error_log('[_autoGenerarPedidos] Error: ' . $e->getMessage());
            }
        }

        return $creados;
    }

    /**
     * Endpoint JSON: retorna análisis de forecast para un ingrediente específico.
     */
    public function forecastJson(?string $id = null): void
    {
        require_once ROOT_PATH . '/app/services/RestForecastService.php';

        $ingredienteId = (int)$id;
        $restauranteId = $this->restauranteId();

        $ing = $this->model->find($ingredienteId);
        if (!$ing || (int)$ing['restaurante_id'] !== $restauranteId) {
            $this->json(['ok' => false], 404);
        }

        $forecast      = new RestForecastService();
        $cpd           = $forecast->calcularConsumoPromedioDiario($ingredienteId, $restauranteId, 7);
        $movil         = $forecast->calcularPromedioMovil($ingredienteId, $restauranteId, 3);
        $diasRestantes = $forecast->calcularDiasRestantes((float)$ing['stock'], $cpd);
        $proyeccion    = $forecast->proyeccionSemanal($ingredienteId, $restauranteId, (float)$ing['stock'], 7);

        $this->json([
            'ok'             => true,
            'cpd'            => round($cpd, 4),
            'promedio_movil' => $movil['promedio'],
            'dias_restantes' => $diasRestantes === INF ? null : round($diasRestantes, 1),
            'proyeccion_7d'  => $proyeccion,
            'dias_consumo'   => $movil['dias'],
        ]);
    }

    // ── APROBACIÓN Y ENVÍO A CARNIHUB ─────────────────────────────

    /**
     * POST /rest-inventario/aprobarSugerido/{id}
     * Cambia estado de 'sugerido' → 'aprobado'.
     */
    public function aprobarSugerido(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->json(['ok' => false, 'error' => 'Método no permitido'], 405);
        }

        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        $pedidoModel   = new RestPedidoSugeridoModel();
        $pedidoId      = (int)$id;
        $restauranteId = $this->restauranteId();

        $pedido = $pedidoModel->find($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== $restauranteId) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
        }
        if ($pedido['estado'] !== 'sugerido') {
            $this->json(['ok' => false, 'error' => 'El pedido no está en estado sugerido (estado actual: ' . $pedido['estado'] . ')']);
        }

        $pedidoModel->cambiarEstado($pedidoId, 'aprobado', $this->usuarioId());
        $this->json(['ok' => true, 'message' => 'Pedido aprobado. Ya puedes enviarlo a CarniHub.']);
    }

    /**
     * POST /rest-inventario/enviarACarnihub/{id}
     * Envía el pedido a CarniHub. Acepta estado 'aprobado' o 'sugerido'
     * (reintento para pedidos que fallaron al enviarse automáticamente).
     */
    public function enviarACarnihub(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->json(['ok' => false, 'error' => 'Método no permitido'], 405);
        }

        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        require_once ROOT_PATH . '/app/services/CarniHubApiService.php';
        $pedidoModel   = new RestPedidoSugeridoModel();
        $pedidoId      = (int)$id;
        $restauranteId = $this->restauranteId();

        $pedido = $pedidoModel->findConItems($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== $restauranteId) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
        }
        if (!in_array($pedido['estado'], ['sugerido', 'aprobado'])) {
            $this->json(['ok' => false, 'error' => 'No se puede enviar un pedido con estado: ' . $pedido['estado']]);
        }

        // Dirección del restaurante para informar a CarniHub
        $db     = \Database::getInstance();
        $stRest = $db->prepare(
            "SELECT nombre, direccion, telefono, lat, lng FROM rest_restaurantes WHERE id = ? LIMIT 1"
        );
        $stRest->execute([$restauranteId]);
        $restData     = $stRest->fetch(\PDO::FETCH_ASSOC) ?: [];
        $compradorInfo = array_filter([
            'comprador_nombre'    => $restData['nombre']    ?? '',
            'comprador_direccion' => $restData['direccion'] ?? '',
            'comprador_telefono'  => $restData['telefono']  ?? '',
            'comprador_lat'       => $restData['lat']       ?? null,
            'comprador_lng'       => $restData['lng']       ?? null,
        ], fn($v) => $v !== null && $v !== '');

        // Mapear items al formato de CarniHubApiService
        $apiItems = [];
        foreach ($pedido['items'] as $item) {
            $cant   = (float)($item['cantidad_aprobada'] ?? $item['cantidad_sugerida']);
            $precio = (float)$item['precio_unit_estimado'];
            $prodId = (int)($item['carnihub_producto_id'] ?? 0);
            if ($prodId <= 0 || $cant <= 0 || $precio <= 0) continue;
            $apiItems[] = [
                'producto_id' => $prodId,
                'cantidad'    => $cant,
                'precio_unit' => $precio,
            ];
        }

        if (empty($apiItems)) {
            $this->json(['ok' => false, 'error' => 'Ningún item tiene producto CarniHub vinculado con precio y cantidad válidos']);
        }

        $apiService = new CarniHubApiService();
        $resultado  = $apiService->crearPedido(
            $restauranteId,
            $apiItems,
            $pedido['notas'] ?? 'Pedido generado automáticamente por sistema de forecast',
            $compradorInfo
        );

        if (!($resultado['success'] ?? false)) {
            error_log('[enviarACarnihub] Error API: ' . ($resultado['error'] ?? 'desconocido'));
            $this->json(['ok' => false, 'error' => $resultado['error'] ?? 'Error al comunicarse con CarniHub']);
        }

        $pedidoExternoId = (int)($resultado['pedido_id'] ?? $resultado['id'] ?? 0);
        error_log('[CarniHub:enviarACarnihub] resultado=' . json_encode($resultado) . ' | pedidoExternoId=' . $pedidoExternoId);
        $pedidoModel->marcarConvertido($pedidoId, $pedidoExternoId);

        // ── Calcular monto total del pedido ───────────────────────────────────
        $montoTotal = 0.0;
        foreach ($apiItems as $item) {
            $montoTotal += (float)$item['cantidad'] * (float)$item['precio_unit'];
        }
        $montoTotal = round($montoTotal, 2);

        // ── Leer configuración de pago CarniHub ───────────────────────────────
        $metodoPago          = 'transferencia';
        $instrTransferencia  = '';
        try {
            $stCh = $db->prepare(
                "SELECT * FROM carnihub_api_config WHERE restaurante_id = ? LIMIT 1"
            );
            $stCh->execute([$restauranteId]);
            $chCfg            = $stCh->fetch(\PDO::FETCH_ASSOC) ?: [];
            $metodoPago       = $chCfg['metodo_pago'] ?? 'transferencia';
            $instrTransferencia = $chCfg['instrucciones_transferencia'] ?? '';
        } catch (\Throwable $e) { /* columnas aún no aplicadas */ }

        // ── Guardar monto y método en el pedido ───────────────────────────────
        try {
            $db->prepare(
                "UPDATE rest_pedidos_sugeridos
                 SET monto_total = ?, metodo_pago = ?, estado_pago = 'pendiente'
                 WHERE id = ?"
            )->execute([$montoTotal, $metodoPago, $pedidoId]);
        } catch (\Throwable $e) { /* columnas aún no aplicadas */ }

        // ── Preparar respuesta de pago según método ───────────────────────────
        $pagoData = ['metodo' => $metodoPago, 'monto' => $montoTotal];

        if ($metodoPago === 'stripe') {
            try {
                require_once ROOT_PATH . '/app/models/ConfigModel.php';
                $cfg       = new ConfigModel();
                $stripeKey = defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== ''
                    ? STRIPE_SECRET_KEY
                    : $cfg->get('stripe_secret_key', '');
                if (empty($stripeKey)) throw new \RuntimeException('Stripe no configurado');
                \Stripe\Stripe::setApiKey($stripeKey);
                $centavos = (int)round($montoTotal * 100);
                $metadata = [
                    'pedido_sugerido_id' => (string)$pedidoId,
                    'pedido_carnihub_id' => (string)$pedidoExternoId,
                    'restaurante_id'     => (string)$restauranteId,
                ];

                $savedPm  = $chCfg['stripe_payment_method_id'] ?? null;
                $savedCus = $chCfg['stripe_customer_id'] ?? null;

                if ($savedPm && $savedCus) {
                    // Cobro automático off-session — sin necesidad de modal
                    $intent = \Stripe\PaymentIntent::create([
                        'amount'         => max(1000, $centavos),
                        'currency'       => 'mxn',
                        'customer'       => $savedCus,
                        'payment_method' => $savedPm,
                        'confirm'        => true,
                        'off_session'    => true,
                        'metadata'       => $metadata,
                    ]);
                    if ($intent->status === 'succeeded') {
                        try {
                            $db->prepare(
                                "UPDATE rest_pedidos_sugeridos
                                 SET estado_pago = 'pagado', pago_referencia = ?, pagado_at = NOW()
                                 WHERE id = ?"
                            )->execute([$intent->id, $pedidoId]);
                        } catch (\Throwable $_e) {}
                        $pagoData['auto']       = true;
                        $pagoData['referencia'] = $intent->id;
                    } elseif ($intent->status === 'requires_action') {
                        $pagoData['action_url'] = $intent->next_action->redirect_to_url->url ?? null;
                    } else {
                        throw new \RuntimeException('Estado inesperado de Stripe: ' . $intent->status);
                    }
                } else {
                    // Sin tarjeta guardada → flujo manual con modal
                    $intent = \Stripe\PaymentIntent::create([
                        'amount'   => max(1000, $centavos),
                        'currency' => 'mxn',
                        'metadata' => $metadata,
                    ]);
                    $pagoData['clientSecret'] = $intent->client_secret;
                }
            } catch (\Throwable $e) {
                error_log('[enviarACarnihub] Stripe error: ' . $e->getMessage());
                $pagoData['error'] = 'No se pudo crear el cargo Stripe: ' . $e->getMessage();
                $pagoData['metodo'] = 'transferencia'; // fallback
                $pagoData['instrucciones'] = $instrTransferencia;
            }
        } elseif ($metodoPago === 'paypal') {
            try {
                $returnUrl = BASE_URL . 'rest-inventario/confirmarPagoCarnihub/' . $pedidoId . '/paypal?status=ok';
                $cancelUrl = BASE_URL . 'rest-inventario/pedidosSugeridos';
                require_once ROOT_PATH . '/app/services/PayPalOrdenService.php';
                $paypal    = new PayPalOrdenService();
                $orden     = $paypal->crearOrden($montoTotal, 'MXN', $returnUrl, $cancelUrl, 'CarnHub-' . $pedidoExternoId);
                $pagoData['approvalUrl'] = $orden['approvalUrl'];
                $pagoData['paypalOrderId'] = $orden['id'] ?? null;
            } catch (\Throwable $e) {
                error_log('[enviarACarnihub] PayPal error: ' . $e->getMessage());
                $pagoData['error'] = 'No se pudo crear orden PayPal: ' . $e->getMessage();
                $pagoData['metodo'] = 'transferencia';
                $pagoData['instrucciones'] = $instrTransferencia;
            }
        } else {
            // transferencia
            $pagoData['instrucciones'] = $instrTransferencia;
        }

        $this->json([
            'ok'                 => true,
            'message'            => 'Pedido enviado a CarniHub correctamente.',
            'pedido_carnihub_id' => $pedidoExternoId,
            'folio'              => $resultado['folio'] ?? null,
            'pago'               => $pagoData,
        ]);
    }

    /**
     * POST /rest-inventario/confirmarPagoCarnihub/{id}/{metodo}
     * Confirma el pago de un pedido sugerido enviado a CarniHub.
     * Llamado desde la vista al completar pago Stripe, o al ingresar referencia de transferencia.
     * También actúa como retorno de PayPal (GET con ?status=ok).
     */
    public function confirmarPagoCarnihub(?string $params = null): void
    {
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        $parts        = explode('/', $params ?? '');
        $pedidoId     = (int)($parts[0] ?? 0);
        $metodo       = $parts[1] ?? 'transferencia';
        $restauranteId = $this->restauranteId();

        $pedidoModel = new RestPedidoSugeridoModel();
        $pedido      = $pedidoModel->find($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== $restauranteId) {
            if ($this->isPost()) {
                $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
            } else {
                $this->redirect('rest-inventario/pedidosSugeridos');
            }
            return;
        }

        $referencia = '';
        $errMsg     = null;

        if ($metodo === 'stripe') {
            $intentId = trim((string)$this->post('payment_intent_id', ''));
            unset($_SESSION['stripe_pedido_intent_' . $pedidoId]); // limpiar sesión antigua

            if (!$intentId) {
                $this->json(['ok' => false, 'error' => 'No se recibió confirmación de pago.']);
                return;
            }
            try {
                require_once ROOT_PATH . '/app/models/ConfigModel.php';
                $cfg       = new ConfigModel();
                $stripeKey = defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== ''
                    ? STRIPE_SECRET_KEY
                    : $cfg->get('stripe_secret_key', '');
                \Stripe\Stripe::setApiKey($stripeKey);
                $intent = \Stripe\PaymentIntent::retrieve($intentId);
                if ($intent->status !== 'succeeded') throw new \RuntimeException('Estado: ' . $intent->status);
                // Seguridad: verificar que el intent pertenece a este pedido
                if ((int)($intent->metadata['pedido_sugerido_id'] ?? 0) !== $pedidoId) {
                    throw new \RuntimeException('El intento de pago no corresponde a este pedido');
                }
                $referencia = $intentId;
            } catch (\Throwable $e) {
                $this->json(['ok' => false, 'error' => 'Pago Stripe no completado: ' . $e->getMessage()]);
                return;
            }

        } elseif ($metodo === 'paypal') {
            // Retorno de PayPal (puede ser GET)
            $paypalOrderId = trim((string)($this->get('token') ?: $this->post('paypal_order_id', '')));
            $status        = $this->get('status', '');
            if ($status !== 'ok' || empty($paypalOrderId)) {
                // Cancelado o inválido
                $this->flash('error', 'Pago PayPal cancelado o no completado.');
                $this->redirect('rest-inventario/pedidosSugeridos');
                return;
            }
            try {
                require_once ROOT_PATH . '/app/services/PayPalOrdenService.php';
                $paypal  = new PayPalOrdenService();
                $capture = $paypal->capturarOrden($paypalOrderId);
                if (!$capture['success']) throw new \RuntimeException($capture['error'] ?? 'Error PayPal');
                $referencia = $paypalOrderId;
            } catch (\Throwable $e) {
                $errMsg = 'PayPal no completó el pago: ' . $e->getMessage();
                error_log('[confirmarPagoCarnihub] PayPal error: ' . $e->getMessage());
            }

        } else {
            // transferencia — referencia manual
            $referencia = trim((string)$this->post('referencia', ''));
        }

        // Marcar como pagado en la DB
        $db = \Database::getInstance();
        try {
            $db->prepare(
                "UPDATE rest_pedidos_sugeridos
                 SET estado_pago     = 'pagado',
                     pago_referencia = ?,
                     pagado_at       = NOW()
                 WHERE id = ?"
            )->execute([$referencia ?: null, $pedidoId]);
        } catch (\Throwable $e) { /* columnas aún no aplicadas */ }

        if ($metodo === 'paypal' && !$this->isPost()) {
            // Retorno GET de PayPal
            $this->flash('success', 'Pago PayPal confirmado.');
            $this->redirect('rest-inventario/pedidosSugeridos');
            return;
        }

        $this->json(['ok' => true, 'message' => 'Pago registrado correctamente.', 'referencia' => $referencia]);
    }

    /**
     * POST /rest-inventario/cancelarSugerido/{id}
     * Cancela un pedido local. Si ya fue enviado a CarniHub (estado 'convertido'),
     * primero intenta cancelarlo en la API remota.
     */
    public function cancelarSugerido(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->json(['ok' => false, 'error' => 'Método no permitido'], 405);
        }

        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        require_once ROOT_PATH . '/app/services/CarniHubApiService.php';
        $pedidoModel   = new RestPedidoSugeridoModel();
        $pedidoId      = (int)$id;
        $restauranteId = $this->restauranteId();

        $pedido = $pedidoModel->find($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== $restauranteId) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $estado = $pedido['estado'];

        if ($estado === 'cancelado') {
            $this->json(['ok' => false, 'error' => 'Este pedido ya está cancelado']);
        }

        if (!in_array($estado, ['sugerido', 'aprobado', 'convertido'])) {
            $this->json(['ok' => false, 'error' => 'No se puede cancelar un pedido con estado: ' . $estado]);
        }

        // Si ya fue enviado a CarniHub, intentar cancelar en la API remota primero
        if ($estado === 'convertido') {
            $chId = (int)($pedido['pedido_carnihub_id'] ?? 0);
            if ($chId > 0) {
                $apiService = new CarniHubApiService();
                $res = $apiService->cancelarPedido($restauranteId, $chId);
                if (!($res['success'] ?? false)) {
                    $errMsg = $res['error'] ?? 'CarniHub rechazó la cancelación';
                    $this->json([
                        'ok'    => false,
                        'error' => $errMsg . ' — El pedido puede ya estar aprobado o en proceso en CarniHub.',
                    ]);
                }
            }
        }

        $pedidoModel->cambiarEstado($pedidoId, 'cancelado', $this->usuarioId());
        $this->json(['ok' => true, 'message' => 'Pedido cancelado correctamente.']);
    }

    /**
     * Filtra ingredientes de forecast que ya tienen un pedido abierto.
     */
    private function filtrarAnalisisPedidosAbiertos(array $analisis, int $restauranteId): array
    {
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        $pedidoModel = new RestPedidoSugeridoModel();
        $bloqueados  = array_fill_keys($pedidoModel->getIngredientesConPedidoAbierto($restauranteId), true);

        if (empty($bloqueados)) {
            return $analisis;
        }

        return array_values(array_filter(
            $analisis,
            static fn($ing) => !isset($bloqueados[(int)($ing['id'] ?? 0)])
        ));
    }

    /**
     * Extrae precio de respuesta de CarniHub con tolerancia de formato.
     * Prioridad: precio_comprador > precio_base > precio.
     */
    private function extraerPrecioDetalleProducto(array $respuesta): float
    {
        $candidatos = [];

        if (isset($respuesta['producto']) && is_array($respuesta['producto'])) {
            $candidatos[] = $respuesta['producto'];
        }
        if (isset($respuesta['data']) && is_array($respuesta['data'])) {
            $candidatos[] = $respuesta['data'];
            if (isset($respuesta['data']['producto']) && is_array($respuesta['data']['producto'])) {
                $candidatos[] = $respuesta['data']['producto'];
            }
        }

        $candidatos[] = $respuesta;

        foreach ($candidatos as $row) {
            foreach (['precio_comprador', 'precio_base', 'precio'] as $campo) {
                if (isset($row[$campo])) {
                    $valor = (float)$row[$campo];
                    if ($valor > 0) {
                        return $valor;
                    }
                }
            }
        }

        return 0.0;
    }

    /**
     * GET /rest-inventario/precioProductoCarnihub/{id}
     * Devuelve el precio actual y datos básicos de un producto CarniHub.
     */
    public function precioProductoCarnihub(?string $id = null): void
    {
        $productoId = (int)$id;
        if ($productoId <= 0) {
            $this->json(['ok' => false, 'error' => 'Producto inválido'], 400);
        }

        try {
            if (defined('RESTAURANTE_STANDALONE') && RESTAURANTE_STANDALONE) {
                require_once ROOT_PATH . '/app/services/CarniHubApiService.php';

                $api = new CarniHubApiService();
                $res = $api->detalleProducto($this->restauranteId(), $productoId);

                if (!($res['success'] ?? false)) {
                    $this->json(['ok' => false, 'error' => $res['error'] ?? 'No se pudo consultar producto'], 502);
                }

                $producto = [];
                if (isset($res['producto']) && is_array($res['producto'])) {
                    $producto = $res['producto'];
                } elseif (isset($res['data']['producto']) && is_array($res['data']['producto'])) {
                    $producto = $res['data']['producto'];
                } elseif (isset($res['data']) && is_array($res['data'])) {
                    $producto = $res['data'];
                }

                $this->json([
                    'ok' => true,
                    'producto_id' => $productoId,
                    'nombre' => (string)($producto['nombre'] ?? $producto['name'] ?? ''),
                    'unidad' => (string)($producto['presentacion'] ?? $producto['unidad'] ?? $producto['unit'] ?? 'kg'),
                    'precio' => $this->extraerPrecioDetalleProducto($res),
                    'fuente' => 'remote',
                ]);
            }

            $db = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT p.id, p.nombre, p.presentacion AS unidad, p.precio_base
                   FROM productos p
                  WHERE p.id = ? AND p.activo = 1
                  LIMIT 1"
            );
            $stmt->execute([$productoId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                $this->json(['ok' => false, 'error' => 'Producto no encontrado'], 404);
            }

            $this->json([
                'ok' => true,
                'producto_id' => (int)$row['id'],
                'nombre' => (string)$row['nombre'],
                'unidad' => (string)($row['unidad'] ?? 'kg'),
                'precio' => (float)($row['precio_base'] ?? 0),
                'fuente' => 'local',
            ]);
        } catch (\Throwable $e) {
            error_log('[RestInventarioController::precioProductoCarnihub] ' . $e->getMessage());
            $this->json(['ok' => false, 'error' => 'No se pudo obtener el precio'], 500);
        }
    }

    /**
     * GET /rest-inventario/seguimientoPedido/{id}
     * Consulta el estado actual del pedido en CarniHub y actualiza estado_carnihub local.
     */
    public function seguimientoPedido(?string $id = null): void
    {
        require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
        require_once ROOT_PATH . '/app/services/CarniHubApiService.php';
        $pedidoModel   = new RestPedidoSugeridoModel();
        $pedidoId      = (int)$id;
        $restauranteId = $this->restauranteId();

        $pedido = $pedidoModel->find($pedidoId);
        if (!$pedido || (int)$pedido['restaurante_id'] !== $restauranteId) {
            $this->json(['ok' => false, 'error' => 'Pedido no encontrado'], 404);
        }

        $chId = (int)($pedido['pedido_carnihub_id'] ?? 0);
        if ($chId <= 0) {
            $this->json(['ok' => false, 'error' => 'Este pedido aún no fue enviado a CarniHub']);
        }

        $apiService = new CarniHubApiService();
        $res = $apiService->consultarPedido($restauranteId, $chId);

        if (!($res['success'] ?? false)) {
            $this->json(['ok' => false, 'error' => $res['error'] ?? 'No se pudo consultar CarniHub']);
        }

        $pedidoData     = $res['pedido'] ?? $res;
        $estadoCarnihub = $pedidoData['estado'] ?? $pedidoData['status'] ?? 'desconocido';

        $pedidoModel->syncEstadoCarnihub($pedidoId, $estadoCarnihub);

        $this->json([
            'ok'              => true,
            'estado_carnihub' => $estadoCarnihub,
            'pedido'          => $pedidoData,
        ]);
    }

}
