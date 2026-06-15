<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaLogisticaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
    }

    public function index(?string $p = null): void
    {
        $empresaId   = $_SESSION['usuario']['empresa_id'] ?? 0;
        $pedidoModel = new PedidoModel();

        $rutasActivas = $pedidoModel->getRutasActivas($empresaId);

        $posiciones = $pedidoModel->getPosicionesActivas($empresaId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Logística — Mis Rutas';
        $activeMenu = 'logistica';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/logistica/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function nuevaRuta(?string $p = null): void
    {
        $empresaId    = $_SESSION['usuario']['empresa_id'] ?? 0;
        $usuarioModel = new UsuarioModel();
        $repartidores = $usuarioModel->getRepartidoresPorEmpresa($empresaId);

        $pedidoModel  = new PedidoModel();
        $pedidosDisp  = $pedidoModel->listadoConfirmadosPorEmpresa($empresaId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Nueva Ruta';
        $activeMenu = 'logistica';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/logistica/form_ruta.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function guardarRuta(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-logistica/index');
        }

        $empresaId    = $_SESSION['usuario']['empresa_id'] ?? 0;
        $pedidoModel  = new PedidoModel();
        $repartidorId = (int)$this->post('repartidor_id');
        $fecha        = trim($this->post('fecha'));
        $pedidosIds   = array_filter(array_map('intval', $_POST['pedidos_ids'] ?? []));

        if (!$repartidorId || !$fecha || empty($pedidosIds)) {
            $this->flash('error', 'Completa todos los campos obligatorios.');
            $this->redirect('empresa-logistica/nuevaRuta');
        }

        try {
            $rutaId = $pedidoModel->crearRuta($repartidorId, $empresaId, $fecha, $pedidosIds);
        } catch (\Throwable $e) {
            $this->flash('error', 'Error al crear la ruta: ' . $e->getMessage());
            $this->redirect('empresa-logistica/nuevaRuta');
        }

        $this->log('Crear ruta', 'logistica', "Ruta $rutaId — repartidor $repartidorId");
        $this->flash('success', 'Ruta creada y pedidos asignados.');
        $this->redirect('empresa-logistica/index');
    }
}
