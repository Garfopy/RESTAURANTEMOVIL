<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaPedidoController extends BaseController
{
    private PedidoModel $pedidoModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireSupervisor();
        $this->pedidoModel = new PedidoModel();
    }

    // Lista de pedidos de la empresa con filtros
    public function index(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $page      = max(1, (int)$this->get('page', 1));
        $filtros   = [
            'empresa_id' => $empresaId,
            'estado'     => $this->get('estado', ''),
            'tipo'       => $this->get('tipo', ''),
            'buscar'     => $this->get('buscar', ''),
            'fecha_desde'=> $this->get('fecha_desde', ''),
            'fecha_hasta'=> $this->get('fecha_hasta', ''),
        ];

        $resultado  = $this->pedidoModel->listadoEmpresa($empresaId, $filtros, $page);
        $items      = $resultado['data'];
        $paginacion = $resultado;

        $countPendientes    = $this->pedidoModel->countPendientes($empresaId);
        $countConComprobante = $this->pedidoModel->countConComprobantePendiente($empresaId);

        $usuarioModel = new UsuarioModel();
        $repartidores = $usuarioModel->getByRolEmpresa('repartidor', $empresaId);

        $empresaModel = new EmpresaModel();
        $empresaInfo  = $empresaModel->find($empresaId);

        $configModel  = new ConfigModel();
        $gmKey        = $configModel->get('google_maps_key', '');

        $flash      = $this->getFlash();
        $pageTitle  = 'Pedidos';
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/pedidos/empresa_index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Devuelve JSON con items de un pedido (para el modal de revisión)
    public function itemsJson(?string $id = null): void
    {
        header('Content-Type: application/json');
        $pedidoId = (int)$id;

        if (!$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            echo json_encode([]);
            return;
        }

        echo json_encode([
            'items'      => $this->pedidoModel->getItemsPedido($pedidoId),
            'sucursales' => $this->pedidoModel->getSucursalesPedido($pedidoId),
        ]);
    }

    // Cambiar estado de un pedido (modal rápido)
    public function cambiarEstado(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido');
        }

        $pedidoId = (int)($id ?? $this->post('pedido_id'));
        $estado   = $this->post('estado');
        $nota     = trim($this->post('nota', ''));
        $estados  = ['pendiente', 'confirmado', 'en_preparacion', 'en_ruta', 'entregado', 'cancelado'];

        if ($pedidoId <= 0 || !in_array($estado, $estados)) {
            $this->flash('error', 'Datos inválidos.');
            $this->redirect('empresa-pedido');
        }

        if (!$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->redirect('empresa-pedido');
        }

        if ($estado === 'entregado' && !empty($_FILES['foto']['tmp_name'])) {
            $this->_procesarFotoEntrega($pedidoId);
        } else {
            $this->pedidoModel->cambiarEstado($pedidoId, $estado);
        }

        $this->log('Cambiar estado pedido', 'pedidos', "Pedido $pedidoId → $estado. $nota");
        $this->flash('success', 'Estado del pedido actualizado.');
        $this->redirect('pedido/detalle/' . $pedidoId);
    }

    // Asignar tipo de entrega + repartidor + costo envío
    public function asignarEntrega(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido');
        }

        $pedidoId    = (int)($id ?? $this->post('pedido_id'));
        $tipoEntrega = $this->post('tipo_entrega');
        $repartidorId = (int)$this->post('repartidor_asignado_id') ?: null;
        $costoEnvio  = (float)$this->post('costo_envio', 0);
        $notaEmpresa = trim($this->post('nota_empresa', ''));

        if ($pedidoId <= 0 || !in_array($tipoEntrega, ['pickup', 'repartidor'])) {
            $this->flash('error', 'Tipo de entrega inválido.');
            $this->redirect('empresa-pedido');
        }

        if (!$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->redirect('empresa-pedido');
        }

        $this->pedidoModel->asignarEntrega($pedidoId, $tipoEntrega, $repartidorId, $costoEnvio, $notaEmpresa);
        $this->log('Asignar entrega', 'pedidos', "Pedido $pedidoId — $tipoEntrega — envío $$costoEnvio");
        $this->flash('success', 'Entrega asignada. Ahora aprueba o rechaza el pedido.');
        $this->redirect('empresa-pedido');
    }

    // Aprobar pedido → estado confirmado, acepta ajustes de precio (solo a la baja)
    // También guarda entrega si se envían los campos (cuando se aprueba sin "Guardar asignación" antes)
    public function aprobar(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido');
        }

        $pedidoId = (int)($id ?? $this->post('pedido_id'));

        if (!$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->redirect('empresa-pedido');
        }

        // Guardar entrega si viene en el POST (flujo directo sin "Guardar asignación")
        $tipoEntrega = $this->post('tipo_entrega');
        if (in_array($tipoEntrega, ['pickup', 'repartidor'], true)) {
            $repartidorId = (int)$this->post('repartidor_asignado_id') ?: null;
            $costoEnvio   = $tipoEntrega === 'pickup' ? 0.0 : (float)$this->post('costo_envio', 0);
            $notaEmpresa  = trim($this->post('nota_empresa', ''));
            $this->pedidoModel->asignarEntrega($pedidoId, $tipoEntrega, $repartidorId, $costoEnvio, $notaEmpresa);
        }

        $ajustes = (array)($_POST['ajustes'] ?? []);

        $this->pedidoModel->aprobarPedido($pedidoId, $this->usuarioId(), $ajustes);
        $this->log('Aprobar pedido', 'pedidos', "Pedido $pedidoId aprobado");

        // Descontar stock de inventario al aprobar
        $pedido = $this->pedidoModel->find($pedidoId);
        if ($pedido) {
            try {
                $movimientoModel = new MovimientoInventarioModel();
                $movimientoModel->descontarStockPedido($pedidoId, $this->empresaId(), $this->usuarioId(), $pedido['folio']);
            } catch (\Throwable $e) {
                // Non-fatal: approval already happened; stock error logged below
                $this->log('Stock error al aprobar', 'pedidos', "Pedido $pedidoId: " . $e->getMessage());
            }
        }

        $this->flash('success', 'Pedido aprobado. El comprador recibirá la confirmación.');
        $this->redirect('empresa-pedido');
    }

    // Rechazar pedido → estado cancelado + nota
    public function rechazar(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido');
        }

        $pedidoId = (int)($id ?? $this->post('pedido_id'));
        $nota     = trim($this->post('nota_rechazo', 'Pedido rechazado por la empresa.'));

        if (!$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->redirect('empresa-pedido');
        }

        $this->pedidoModel->rechazarPedido($pedidoId, $nota);
        $this->log('Rechazar pedido', 'pedidos', "Pedido $pedidoId rechazado: $nota");
        $this->flash('success', 'Pedido rechazado.');
        $this->redirect('empresa-pedido');
    }

    // Subir foto de entrega (empresa o repartidor)
    public function subirFotoEntrega(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido');
        }

        $pedidoId = (int)($id ?? $this->post('pedido_id'));

        if (!$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId()) || empty($_FILES['foto']['tmp_name'])) {
            $this->flash('error', 'Pedido no encontrado o foto no enviada.');
            $this->redirect('empresa-pedido');
        }

        $this->_procesarFotoEntrega($pedidoId);
        $this->flash('success', 'Foto de entrega registrada. Pedido marcado como entregado.');
        $this->redirect('empresa-pedido');
    }

    // Pedido personalizado: formulario
    public function personalizado(?string $p = null): void
    {
        $empresaId     = $this->empresaId();
        $productoModel = new ProductoModel();
        $usuarioModel  = new UsuarioModel();

        $compradores = $usuarioModel->getByRolEmpresa('comprador', $empresaId);
        $productos  = $productoModel->listadoAdmin(['empresa_id' => $empresaId], 1)['data'] ?? [];
        $flash      = $this->getFlash();
        $pageTitle  = 'Pedido Personalizado';
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/pedidos/personalizado.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Guardar pedido personalizado
    public function guardarPersonalizado(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido/personalizado');
        }

        $empresaId    = $this->empresaId();
        $compradorId  = (int)$this->post('comprador_id');
        $nota         = trim($this->post('notas', ''));
        $fechaEntrega = $this->post('fecha_entrega') ?: null;
        $productosIds = (array)$this->post('producto_id', []);
        $cantidades   = (array)$this->post('cantidad', []);
        $precios      = (array)$this->post('precio_unit', []);

        if ($compradorId <= 0 || empty($productosIds)) {
            $this->flash('error', 'Selecciona un comprador y al menos un producto.');
            $this->redirect('empresa-pedido/personalizado');
        }

        $usuarioModel = new UsuarioModel();
        if (!$usuarioModel->compradorValido($compradorId, $empresaId)) {
            $this->flash('error', 'Comprador no válido.');
            $this->redirect('empresa-pedido/personalizado');
        }

        $lineas   = [];
        $subtotal = 0;
        foreach ($productosIds as $i => $pid) {
            $pid   = (int)$pid;
            $cant  = (float)($cantidades[$i] ?? 0);
            $precio = (float)($precios[$i] ?? 0);
            if ($pid <= 0 || $cant <= 0 || $precio <= 0) continue;
            $sub       = round($cant * $precio, 2);
            $subtotal += $sub;
            $lineas[]  = ['producto_id' => $pid, 'cantidad' => $cant, 'precio_unit' => $precio, 'subtotal' => $sub];
        }

        if (empty($lineas)) {
            $this->flash('error', 'Agrega al menos un producto con cantidad y precio válidos.');
            $this->redirect('empresa-pedido/personalizado');
        }

        $folio = $this->pedidoModel->generarFolio();
        $this->pedidoModel->crearPersonalizado(
            $empresaId, $compradorId, $folio, $nota, $fechaEntrega, $lineas, $subtotal, $this->usuarioId()
        );

        $this->log('Pedido personalizado', 'pedidos', "Folio $folio — Comprador $compradorId — Total $$subtotal");
        $this->flash('success', "Pedido personalizado creado con folio <strong>$folio</strong>.");
        $this->redirect('empresa-pedido');
    }

    // ── Imprimir / exportar PDF de un pedido ─────────────────────
    public function pdf(?string $id = null): void
    {
        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('empresa-pedido');
        }

        $pedido       = $this->pedidoModel->conDetalle($pedidoId);
        $empresa      = (new EmpresaModel())->find($this->empresaId());
        $configModel  = new ConfigModel();
        $appLogo      = $configModel->get('app_logo', '');
        $colorPrimary = $configModel->get('color_primary', '#C8102E');

        require ROOT_PATH . '/app/views/empresa/pedidos/pdf.php';
    }

    // Asignar costos de envío por parada (pedido multi-sucursal)
    public function asignarCostoEnvio(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-pedido');
        }
        $pedidoId = (int)$this->post('pedido_id');
        if ($pedidoId <= 0 || !$this->pedidoModel->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('empresa-pedido');
        }
        $envios = (array)($_POST['envio'] ?? []);
        $this->pedidoModel->asignarCostosEnvioParadas($pedidoId, $envios);
        $this->log('Asignar costo envío paradas', 'pedidos', "Pedido $pedidoId — " . json_encode($envios));
        $this->flash('success', 'Costos de envío guardados.');
        $this->redirect('pedido/detalle/' . $pedidoId);
    }

    // Helper privado: procesa upload de foto de entrega
    private function _procesarFotoEntrega(int $pedidoId): void    {
        $dir     = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/evidencias/';
        $ext     = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allowed, true)) {
            $this->flash('error', 'Formato de imagen no permitido. Usa JPG, PNG o WEBP.');
            $this->redirect('empresa-pedido');
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'ped_' . $pedidoId . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['foto']['tmp_name'], $dir . $filename)) {
            $this->flash('error', 'Error al guardar la imagen. Verifica permisos del servidor.');
            $this->redirect('empresa-pedido');
        }
        $path = '/public/uploads/evidencias/' . $filename;
        $this->pedidoModel->subirFotoEntrega($pedidoId, $path);
    }
}
