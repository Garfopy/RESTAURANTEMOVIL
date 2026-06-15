<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class PanelPedidoController extends BaseController
{
    private PedidoModel $pedidoModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->pedidoModel = new PedidoModel();
    }

    public function index(?string $p = null): void
    {
        $filtros = [
            'buscar'     => $this->get('buscar', ''),
            'estado'     => $this->get('estado', ''),
            'empresa_id' => $this->get('empresa_id', ''),
        ];
        $page       = max(1, (int)$this->get('page', 1));
        $resultado  = $this->pedidoModel->listadoGlobal($filtros, $page);
        $pedidos    = $resultado['data'];
        $paginacion = $resultado;

        $empresaModel = new EmpresaModel();
        $empresas     = $empresaModel->listadoSimple();

        $flash      = $this->getFlash();
        $pageTitle  = 'Pedidos — Plataforma';
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/panel/pedidos/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function detalle(?string $p = null): void
    {
        $pedido = $this->pedidoModel->conDetalle((int)$p);
        if (!$pedido) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('panel-pedido/index');
        }

        $flash      = $this->getFlash();
        $pageTitle  = 'Pedido ' . $pedido['folio'];
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/panel/pedidos/detalle.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function cambiarEstado(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->json(['ok' => false, 'msg' => 'Método no permitido'], 405);
        }

        $id     = (int)$this->post('pedido_id');
        $estado = trim($this->post('estado', ''));

        if (!$this->pedidoModel->cambiarEstado($id, $estado)) {
            $this->json(['ok' => false, 'msg' => 'Estado inválido o pedido no existe'], 400);
        }

        $this->log('Cambiar estado pedido', 'pedidos', "ID: $id → $estado");
        $this->json(['ok' => true, 'estado' => $estado]);
    }
}
