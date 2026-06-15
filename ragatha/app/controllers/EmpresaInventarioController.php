<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaInventarioController extends BaseController
{
    private ProductoModel $productoModel;
    private MovimientoInventarioModel $movModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireSupervisor(); // admin_empresa y supervisor (no comprador)
        $this->productoModel = new ProductoModel();
        $this->movModel      = new MovimientoInventarioModel();
    }

    // Dashboard principal de stock
    public function index(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $resumen   = $this->movModel->resumenStock($empresaId);
        $ultimos   = $this->movModel->ultimosMovimientos($empresaId, 8);

        $criticos = array_filter($resumen, fn($r) => in_array($r['estado_stock'], ['critico', 'agotado']));
        $bajos    = array_filter($resumen, fn($r) => $r['estado_stock'] === 'bajo');
        $ok       = array_filter($resumen, fn($r) => $r['estado_stock'] === 'ok');

        $flash      = $this->getFlash();
        $pageTitle  = 'Control de Stock';
        $activeMenu = 'inventario';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/inventario/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Formulario de entrada/salida rápida
    public function movimiento(?string $tipo = null): void
    {
        $tipo = in_array($tipo, ['entrada', 'salida', 'merma']) ? $tipo : 'entrada';

        $empresaId = $this->empresaId();
        $productos = $this->productoModel->listadoInventario(['empresa_id' => $empresaId], 1)['data'] ?? [];

        $flash     = $this->getFlash();
        $pageTitle = match ($tipo) {
            'entrada' => 'Registrar Entrada de Stock',
            'salida'  => 'Registrar Salida de Stock',
            'merma'   => 'Registrar Merma',
        };
        $activeMenu = 'inventario_entrada';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/inventario/movimiento_form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Procesar el movimiento
    public function guardarMovimiento(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-inventario');
        }

        $empresaId  = $this->empresaId();
        $productoId = (int)$this->post('producto_id');
        $tipo       = $this->post('tipo');
        $cantidad   = (float)$this->post('cantidad');
        $motivo     = trim($this->post('motivo', ''));
        $referencia = trim($this->post('referencia', ''));

        if ($productoId <= 0 || $cantidad <= 0 || !in_array($tipo, ['entrada', 'salida', 'merma', 'ajuste'])) {
            $this->flash('error', 'Datos inválidos. Verifica la cantidad y el tipo de movimiento.');
            $this->redirect('empresa-inventario/movimiento/' . $tipo);
        }

        // Verificar que el producto pertenece a la empresa
        if (!$this->productoModel->perteneceAEmpresa($productoId, $empresaId)) {
            $this->flash('error', 'Producto no válido.');
            $this->redirect('empresa-inventario');
        }

        // Validar stock suficiente para salida/merma
        if (in_array($tipo, ['salida', 'merma'])) {
            $stockActual = $this->movModel->stockActual($productoId);
            if ($cantidad > $stockActual) {
                $this->flash('error', 'Stock insuficiente. Disponible: ' . number_format($stockActual, 2) . '. No es posible registrar una salida mayor al stock actual.');
                $this->redirect('empresa-inventario/movimiento/' . $tipo);
            }
        }

        $result = $this->productoModel->ajustarStock($productoId, $tipo, $cantidad);

        $this->movModel->registrar([
            'empresa_id'   => $empresaId,
            'producto_id'  => $productoId,
            'tipo'         => $tipo,
            'cantidad'     => $cantidad,
            'stock_antes'  => $result['stock_antes'],
            'stock_despues'=> $result['stock_despues'],
            'motivo'       => $motivo ?: null,
            'referencia'   => $referencia ?: null,
            'usuario_id'   => $this->usuarioId(),
        ]);

        $etiqueta = match ($tipo) {
            'entrada' => 'Entrada',
            'salida'  => 'Salida',
            'merma'   => 'Merma',
            default   => 'Ajuste',
        };
        $this->log("$etiqueta inventario", 'inventario', "$tipo $cantidad uds — producto $productoId — $motivo");
        $this->flash('success', "$etiqueta registrada correctamente. Stock actual: {$result['stock_despues']}");
        $this->redirect('empresa-inventario');
    }

    // Ajuste directo de stock (admin_empresa y supervisor)
    public function ajuste(?string $productoId = null): void
    {
        $id = (int)$productoId;

        if (!$this->isPost()) {
            $empresaId = $this->empresaId();
            $producto  = $this->productoModel->conStockDetalleEmpresa($id, $empresaId);
            if (!$producto) {
                $this->redirect('empresa-inventario');
            }
            $flash     = $this->getFlash();
            $pageTitle = 'Ajuste de Stock — ' . $producto['nombre'];
            $activeMenu = 'inventario';
            ob_start();
            require ROOT_PATH . '/app/views/empresa/inventario/ajuste_form.php';
            $content = ob_get_clean();
            require ROOT_PATH . '/app/views/empresa/layouts/main.php';
            return;
        }

        $empresaId   = $this->empresaId();
        $stockNuevo  = (float)$this->post('stock_nuevo');
        $umbral      = (float)$this->post('umbral_minimo');
        $motivo      = trim($this->post('motivo', ''));

        if (!$this->productoModel->perteneceAEmpresa($id, $empresaId)) {
            $this->redirect('empresa-inventario');
        }

        $stockAntes = $this->movModel->stockActual($id);

        $this->productoModel->ajustarInventarioDirecto($id, $stockNuevo, $umbral);

        $this->movModel->registrar([
            'empresa_id'    => $empresaId,
            'producto_id'   => $id,
            'tipo'          => 'ajuste',
            'cantidad'      => abs($stockNuevo - $stockAntes),
            'stock_antes'   => $stockAntes,
            'stock_despues' => $stockNuevo,
            'motivo'        => $motivo ?: 'Ajuste manual',
            'referencia'    => null,
            'usuario_id'    => $this->usuarioId(),
        ]);

        $this->flash('success', 'Stock ajustado correctamente.');
        $this->redirect('empresa-inventario');
    }

    // Historial de movimientos de un producto
    public function historial(?string $productoId = null): void
    {
        $empresaId = $this->empresaId();
        $id        = (int)$productoId;
        $page      = max(1, (int)$this->get('page', 1));

        $producto = $this->productoModel->conStockDetalleEmpresa($id, $empresaId);
        if (!$producto) {
            $this->redirect('empresa-inventario');
        }

        $resultado  = $this->movModel->historialProducto($id, $empresaId, $page);
        $items      = $resultado['data'];
        $paginacion = $resultado;
        $flash      = $this->getFlash();
        $pageTitle  = 'Historial — ' . $producto['nombre'];
        $activeMenu = 'inventario';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/inventario/historial.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Log global de todos los movimientos de la empresa
    public function log_movimientos(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $page      = max(1, (int)$this->get('page', 1));
        $filtros   = [
            'tipo'        => $this->get('tipo', ''),
            'producto_id' => (int)$this->get('producto_id', 0),
            'fecha_desde' => $this->get('fecha_desde', ''),
            'fecha_hasta' => $this->get('fecha_hasta', ''),
        ];

        $resultado  = $this->movModel->historialEmpresa($empresaId, $filtros, $page);
        $items      = $resultado['data'];
        $paginacion = $resultado;
        $productos  = $this->productoModel->listadoInventario(['empresa_id' => $empresaId], 1)['data'] ?? [];
        $flash      = $this->getFlash();
        $pageTitle  = 'Log de Movimientos';
        $activeMenu = 'inventario';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/inventario/log.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }
}
