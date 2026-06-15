<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

/**
 * CarritoController — Flujo de compra en 3 pasos.
 *
 * Paso 1 /carrito/index      — Selección de productos y cantidades
 * Paso 2 /carrito/resumen    — Revisión + fecha/notas
 * Paso 3 /carrito/confirmado — Pedido creado con folio
 *
 * Cada comprador representa un punto de entrega.
 * No hay distribución multi-sucursal en el MVP.
 */
class CarritoController extends BaseController
{
    private ProductoModel $productoModel;
    private PedidoModel   $pedidoModel;
    private ComboModel    $comboModel;
    private EmpresaModel  $empresaModel;
    private UsuarioModel  $usuarioModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireComprador();
        $this->productoModel = new ProductoModel();
        $this->pedidoModel   = new PedidoModel();
        $this->comboModel    = new ComboModel();
        $this->empresaModel  = new EmpresaModel();
        $this->usuarioModel  = new UsuarioModel();
    }

    // ── Paso 1: Selección de productos ────────────────────────────
    public function index(?string $p = null): void
    {
        $empresaId   = $this->empresaId();
        $compradorId = $this->usuarioId();
        $carrito     = $_SESSION['carrito']['items'] ?? [];

        // Cargar SOLO los productos que están en el carrito (sin paginación)
        $carritoIds = array_keys($carrito);
        $productos  = $this->productoModel->getByIdsForCart($carritoIds, $empresaId);
        foreach ($productos as &$prod) {
            $prod['escalonados'] = $this->productoModel->getEscalonados((int)$prod['id']);
        }
        unset($prod);

        $db = Database::getInstance();
        $categorias = $db->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre')->fetchAll();

        // Cargar límites activos para mostrarlos en la vista
        $stmtLim = $db->prepare(
            'SELECT producto_id, limite_kg, limite_monto, periodo FROM limites_compra WHERE empresa_id=? AND activo=1 AND producto_id IS NOT NULL'
        );
        $stmtLim->execute([$empresaId]);
        $limitePorProducto = [];
        foreach ($stmtLim->fetchAll() as $lim) {
            $limitePorProducto[(int)$lim['producto_id']] = $lim;
        }

        $combos = $this->comboModel->getCombosParaComprador($compradorId, $empresaId);

        // Precios especiales del comprador: map producto_id => precio
        $preciosEspeciales = [];
        foreach ($this->productoModel->getPreciosEspecialesComprador($compradorId, $empresaId) as $pe) {
            $preciosEspeciales[(int)$pe['producto_id']] = (float)$pe['precio'];
        }
        $flash      = $this->getFlash();
        $pageTitle  = 'Hacer pedido — Paso 1: Productos';
        $activeMenu = 'carrito';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/carrito/paso1.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Guarda los ítems del carrito (POST desde paso 1)
    public function actualizar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('carrito/index');
        }

        $compradorId = $this->usuarioId();
        $empresaId   = $this->empresaId();
        $cantidades  = $_POST['cantidad'] ?? [];
        $items = [];

        foreach ($cantidades as $productoId => $cantidad) {
            $productoId = (int)$productoId;
            $cantidad   = (float)str_replace(',', '.', $cantidad);
            if ($cantidad <= 0) continue;

            $producto = $this->productoModel->find($productoId);
            if (!$producto || !$producto['activo']) continue;
            if ((int)$producto['empresa_id'] !== $empresaId) continue;

            $precio    = $this->productoModel->getPrecioFinal($compradorId, $productoId, $cantidad);
            $precioBase = (float)$producto['precio_base'];
            $esPrecioEsp = $cantidad < 10.0
                && $precio < $precioBase
                && $this->productoModel->getPrecioEspecial($compradorId, $productoId) !== null;
            $items[$productoId] = [
                'producto_id'       => $productoId,
                'nombre'            => $producto['nombre'],
                'presentacion'      => $producto['presentacion'],
                'cantidad'          => $cantidad,
                'precio'            => $precio,
                'precio_base'       => $precioBase,
                'es_precio_especial'=> $esPrecioEsp,
                'subtotal'          => round($precio * $cantidad, 2),
            ];
        }

        if (empty($items)) {
            $this->flash('error', 'Agrega al menos un producto con cantidad mayor a 0.');
            $this->redirect('carrito/index');
        }

        // Validar stock disponible
        $movimientoModel = new MovimientoInventarioModel();
        $erroresStock = [];
        foreach ($items as $item) {
            $stockDisponible = $movimientoModel->getStockActual((int)$item['producto_id']);
            if ($stockDisponible !== null && $item['cantidad'] > $stockDisponible) {
                $disponibleStr = number_format($stockDisponible, 0);
                $erroresStock[] = "{$item['nombre']}: solicitado {$item['cantidad']}, disponible {$disponibleStr}";
            }
        }
        if (!empty($erroresStock)) {
            $this->flash('error', 'Stock insuficiente — ' . implode(' | ', $erroresStock) . '. Ajusta las cantidades.');
            $this->redirect('carrito/index');
        }

        // Validar límites de compra por pedido
        $db2 = Database::getInstance();
        $stmtLimUpd = $db2->prepare(
            "SELECT limite_kg, limite_monto, periodo FROM limites_compra WHERE empresa_id=? AND producto_id=? AND activo=1 AND periodo='por_pedido' LIMIT 1"
        );
        $erroresLimite = [];
        foreach ($items as $item) {
            $stmtLimUpd->execute([$empresaId, $item['producto_id']]);
            $lim = $stmtLimUpd->fetch();
            if ($lim) {
                if ($lim['limite_kg'] && $item['cantidad'] > (float)$lim['limite_kg']) {
                    $erroresLimite[] = "{$item['nombre']}: máx. {$lim['limite_kg']} kg por pedido (solicitado: {$item['cantidad']})";
                }
            }
        }
        if (!empty($erroresLimite)) {
            $this->flash('error', 'Límite superado — ' . implode(' | ', $erroresLimite));
            $this->redirect('carrito/index');
        }

        $_SESSION['carrito']['items'] = $items;
        unset($_SESSION['carrito']['meta']);

        $this->redirect('carrito/resumen');
    }

    // Paso de sucursales eliminado — compatibilidad de URLs
    public function sucursales(?string $p = null): void
    {
        $this->redirect('carrito/resumen');
    }

    public function guardarSucursales(?string $p = null): void
    {
        $this->redirect('carrito/resumen');
    }

    // ── Paso 2: Resumen y confirmación ────────────────────────────
    public function resumen(?string $p = null): void
    {
        $items = $_SESSION['carrito']['items'] ?? [];

        if (empty($items)) {
            $this->redirect('carrito/index');
        }

        $total = array_sum(array_column($items, 'subtotal'));

        $comprador = $this->usuarioModel->find($this->usuarioId());
        $empresa   = $this->empresaModel->find($this->empresaId());

        $flash      = $this->getFlash();
        $meta       = $_SESSION['carrito']['meta'] ?? [];
        $pageTitle  = 'Hacer pedido — Paso 2: Resumen';
        $activeMenu = 'carrito';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/carrito/paso3.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Crea el pedido en BD (POST desde resumen)
    public function confirmar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('carrito/resumen');
        }

        $items = $_SESSION['carrito']['items'] ?? [];

        if (empty($items)) {
            $this->redirect('carrito/index');
        }

        $fechaEntrega = trim($this->post('fecha_entrega', ''));
        $notas        = trim($this->post('notas', ''));
        $tipoEntrega  = $this->post('tipo_entrega');
        $metodoPago   = $this->post('metodo_pago');

        if (!in_array($tipoEntrega, ['pickup', 'repartidor'], true)) {
            $tipoEntrega = 'pickup';
        }
        if (!in_array($metodoPago, ['transferencia', 'efectivo'], true)) {
            $metodoPago = 'transferencia';
        }

        if (empty($fechaEntrega)) {
            $this->flash('error', 'Selecciona la fecha de entrega.');
            $this->redirect('carrito/resumen');
        }

        $compradorId = $this->usuarioId();

        // Revalidar precios con precios especiales aplicados
        $sucursalModel = new SucursalModel();
        $itemsDB = [];
        foreach ($items as $prodId => $item) {
            $precio = $this->productoModel->getPrecioFinal($compradorId, (int)$prodId, $item['cantidad']);
            $itemsDB[] = [
                'producto_id' => $prodId,
                'cantidad'    => $item['cantidad'],
                'precio_unit' => $precio,
                'subtotal'    => round($precio * $item['cantidad'], 2),
            ];
        }

        $pedidoData = [
            'empresa_id'          => $this->empresaId(),
            'comprador_id'        => $compradorId,
            'estado'              => 'pendiente',
            'requiere_aprobacion' => 0,
            'fecha_entrega'       => $fechaEntrega,
            'metodo_pago'         => $metodoPago,
            'tipo_entrega'        => $tipoEntrega,
            'notas'               => $notas ?: null,
        ];

        // IDs de sucursales multi-parada (0, 1 o varias)
        $sucursalesIds = [];

        if ($tipoEntrega === 'repartidor') {
            $comprador = $this->usuarioModel->find($compradorId);

            // Recoger array del picker multi-parada
            $rawIds = $_POST['sucursales_ids'] ?? [];
            if (is_array($rawIds)) {
                foreach ($rawIds as $sid) {
                    $sid = (int)$sid;
                    if ($sid > 0 && $sucursalModel->perteneceAComprador($sid, $compradorId)) {
                        $sucursalesIds[] = $sid;
                    }
                }
            }

            if (!empty($sucursalesIds)) {
                // Dirección del pedido = primera parada
                $primera = $sucursalModel->find($sucursalesIds[0]);
                if ($primera) {
                    $pedidoData['direccion_entrega']  = $primera['direccion'];
                    $pedidoData['lat_entrega']        = $primera['lat'] ?? null;
                    $pedidoData['lng_entrega']        = $primera['lng'] ?? null;
                    $pedidoData['referencia_entrega'] = null;
                }
            } else {
                // Dirección manual o del perfil (sin sucursales / "Otra dirección")
                $pedidoData['direccion_entrega']  = trim($this->post('direccion_entrega', '')) ?: ($comprador['direccion_entrega'] ?? null);
                $pedidoData['referencia_entrega'] = trim($this->post('referencia_entrega', '')) ?: ($comprador['referencia_entrega'] ?? null);
                $pedidoData['lat_entrega']        = $this->post('lat_entrega') ?: ($comprador['lat_entrega'] ?? null);
                $pedidoData['lng_entrega']        = $this->post('lng_entrega') ?: ($comprador['lng_entrega'] ?? null);
            }
        }

        try {
            $pedidoId = $this->pedidoModel->crear($pedidoData, $itemsDB, $sucursalesIds);

            // Guardar distribución de kg por producto × sucursal
            $distData = $_POST['dist'] ?? [];
            if (!empty($distData) && !empty($sucursalesIds)) {
                $this->pedidoModel->guardarDistribucion($pedidoId, $distData);
            }

            $pedido   = $this->pedidoModel->find($pedidoId);

            $this->log('crear_pedido', 'carrito', "Pedido {$pedido['folio']} creado");

            $_SESSION['carrito'] = [];
            $_SESSION['ultimo_folio'] = $pedido['folio'];
            $_SESSION['ultimo_pedido_id'] = $pedidoId;

            $this->redirect('carrito/confirmado');
        } catch (\Throwable $e) {
            $this->flash('error', 'Error al crear el pedido. Intenta de nuevo.');
            $this->redirect('carrito/resumen');
        }
    }

    // ── Paso 3: Confirmado ────────────────────────────────────────
    public function confirmado(?string $p = null): void
    {
        $folio    = $_SESSION['ultimo_folio'] ?? null;
        $pedidoId = $_SESSION['ultimo_pedido_id'] ?? null;

        if (!$folio) {
            $this->redirect('pedido/index');
        }

        unset($_SESSION['ultimo_folio'], $_SESSION['ultimo_pedido_id']);

        $pedido     = $pedidoId ? $this->pedidoModel->find((int)$pedidoId) : null;
        $flash      = $this->getFlash();
        $pageTitle  = 'Pedido confirmado';
        $activeMenu = 'carrito';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/carrito/paso4.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // AJAX: agrega un solo producto al carrito desde el catálogo
    public function agregarProducto(?string $p = null): void
    {
        header('Content-Type: application/json');
        if (!$this->isPost()) {
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }

        $productoId  = (int)$this->post('producto_id');
        $cantidad    = (float)str_replace(',', '.', $this->post('cantidad', 0));
        $compradorId = $this->usuarioId();
        $empresaId   = $this->empresaId();

        if ($cantidad <= 0 || $productoId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Cantidad inválida']);
            return;
        }

        $producto = $this->productoModel->find($productoId);
        if (!$producto || !$producto['activo'] || (int)$producto['empresa_id'] !== $empresaId) {
            echo json_encode(['ok' => false, 'msg' => 'Producto no disponible']);
            return;
        }

        // Validar stock disponible
        $movimientoModel = new MovimientoInventarioModel();
        $stockDisponible = $movimientoModel->getStockActual($productoId);
        $carritoActual   = $_SESSION['carrito']['items'][$productoId]['cantidad'] ?? 0;
        $totalSolicitado = $carritoActual + $cantidad;
        if ($stockDisponible !== null && $totalSolicitado > $stockDisponible) {
            $disponible = max(0, $stockDisponible - $carritoActual);
            echo json_encode(['ok' => false, 'msg' => "Stock insuficiente. Disponible: " . number_format($stockDisponible, 0) . " (ya tienes " . number_format($carritoActual, 0) . " en el carrito)"]);
            return;
        }

        // Validar límite de compra (por pedido)
        $stmtLimAgr = Database::getInstance()->prepare(
            "SELECT limite_kg, limite_monto, periodo FROM limites_compra WHERE empresa_id=? AND producto_id=? AND activo=1 AND periodo='por_pedido' LIMIT 1"
        );
        $stmtLimAgr->execute([$empresaId, $productoId]);
        $limiteAgr = $stmtLimAgr->fetch();
        if ($limiteAgr && $limiteAgr['limite_kg'] && $totalSolicitado > (float)$limiteAgr['limite_kg']) {
            echo json_encode(['ok' => false, 'msg' => "🔒 Límite superado: máximo {$limiteAgr['limite_kg']} {$producto['presentacion']} de {$producto['nombre']} por pedido."]);
            return;
        }

        $carrito = $_SESSION['carrito']['items'] ?? [];

        if (isset($carrito[$productoId])) {
            $nuevaCant   = $carrito[$productoId]['cantidad'] + $cantidad;
            $precio      = $this->productoModel->getPrecioFinal($compradorId, $productoId, $nuevaCant);
            $precioBase  = (float)$producto['precio_base'];
            $esPrecioEsp = $nuevaCant < 10.0
                && $precio < $precioBase
                && $this->productoModel->getPrecioEspecial($compradorId, $productoId) !== null;
            $carrito[$productoId] = array_merge($carrito[$productoId], [
                'cantidad'          => $nuevaCant,
                'precio'            => $precio,
                'precio_base'       => $precioBase,
                'es_precio_especial'=> $esPrecioEsp,
                'subtotal'          => round($precio * $nuevaCant, 2),
            ]);
        } else {
            $precio      = $this->productoModel->getPrecioFinal($compradorId, $productoId, $cantidad);
            $precioBase  = (float)$producto['precio_base'];
            $esPrecioEsp = $cantidad < 10.0
                && $precio < $precioBase
                && $this->productoModel->getPrecioEspecial($compradorId, $productoId) !== null;
            $carrito[$productoId] = [
                'producto_id'       => $productoId,
                'nombre'            => $producto['nombre'],
                'presentacion'      => $producto['presentacion'],
                'cantidad'          => $cantidad,
                'precio'            => $precio,
                'precio_base'       => $precioBase,
                'es_precio_especial'=> $esPrecioEsp,
                'subtotal'          => round($precio * $cantidad, 2),
            ];
        }

        $_SESSION['carrito']['items'] = $carrito;

        echo json_encode([
            'ok'          => true,
            'msg'         => "Agregado: {$producto['nombre']}.",
            'total_items' => count($carrito),
        ]);
    }

    public function quitarProducto(?string $p = null): void
    {
        header('Content-Type: application/json');
        if (!$this->isPost()) {
            echo json_encode(['ok' => false, 'msg' => 'Método no permitido']);
            return;
        }
        $productoId = (int)$this->post('producto_id');
        if ($productoId <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'ID inválido']);
            return;
        }
        $carrito = $_SESSION['carrito']['items'] ?? [];
        unset($carrito[$productoId]);
        $_SESSION['carrito']['items'] = $carrito;
        echo json_encode(['ok' => true, 'total_items' => count($carrito)]);
    }

    public function vaciar(?string $p = null): void
    {
        $_SESSION['carrito'] = [];
        $this->redirect('carrito/index');
    }

    public function cargarCombo(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('carrito/index');
        }

        $comboId     = (int)$this->post('combo_id');
        $compradorId = $this->usuarioId();
        $empresaId   = $this->empresaId();

        if (!$this->comboModel->perteneceAEmpresa($comboId, $empresaId) ||
            !$this->comboModel->estaAsignadoAComprador($comboId, $compradorId)) {
            $this->flash('error', 'Combo no disponible.');
            $this->redirect('carrito/index');
        }

        $items = $this->comboModel->getItems($comboId);
        if (empty($items)) {
            $this->flash('error', 'Este combo no tiene productos.');
            $this->redirect('carrito/index');
        }

        $carrito = $_SESSION['carrito']['items'] ?? [];

        foreach ($items as $ci) {
            $prodId   = (int)$ci['producto_id'];
            $cantidad = (float)$ci['cantidad'];
            $producto = $this->productoModel->find($prodId);
            if (!$producto || !$producto['activo']) continue;

            $precio = $this->productoModel->getPrecioFinal($compradorId, $prodId, $cantidad);

            if (isset($carrito[$prodId])) {
                $nuevaCant = $carrito[$prodId]['cantidad'] + $cantidad;
                $carrito[$prodId]['cantidad'] = $nuevaCant;
                $carrito[$prodId]['precio']   = $this->productoModel->getPrecioFinal($compradorId, $prodId, $nuevaCant);
                $carrito[$prodId]['subtotal'] = round($carrito[$prodId]['precio'] * $nuevaCant, 2);
            } else {
                $carrito[$prodId] = [
                    'producto_id'  => $prodId,
                    'nombre'       => $producto['nombre'],
                    'presentacion' => $producto['presentacion'],
                    'cantidad'     => $cantidad,
                    'precio'       => $precio,
                    'subtotal'     => round($precio * $cantidad, 2),
                ];
            }
        }

        $_SESSION['carrito']['items'] = $carrito;
        $this->flash('success', 'Combo cargado en tu pedido.');
        $this->redirect('carrito/index');
    }
}
