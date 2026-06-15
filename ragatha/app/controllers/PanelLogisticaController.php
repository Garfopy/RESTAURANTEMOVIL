<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class PanelLogisticaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(?string $p = null): void
    {
        $pedidoModel = new PedidoModel();

        // Rutas activas (en ruta o en preparación)
        $rutasActivas = $pedidoModel->getRutasActivas();

        // Posiciones actuales de repartidores activos
        $posiciones = $pedidoModel->getPosicionesActivas();

        $flash      = $this->getFlash();
        $pageTitle  = 'Logística — Mapa Global';
        $activeMenu = 'logistica';

        ob_start();
        require ROOT_PATH . '/app/views/panel/logistica/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function nuevaRuta(?string $p = null): void
    {
        $usuarioModel = new UsuarioModel();
        $repartidores = $usuarioModel->getRepartidoresGlobal();

        $empresaModel = new EmpresaModel();
        $empresas     = $empresaModel->listadoSimple();

        $flash      = $this->getFlash();
        $pageTitle  = 'Nueva Ruta';
        $activeMenu = 'logistica';

        ob_start();
        require ROOT_PATH . '/app/views/panel/logistica/form_ruta.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function guardarRuta(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('panel-logistica/index');
        }

        $pedidoModel  = new PedidoModel();
        $repartidorId = (int)$this->post('repartidor_id');
        $empresaId    = (int)$this->post('empresa_id');
        $fecha        = trim($this->post('fecha'));
        $pedidosIds   = array_filter(array_map('intval', $_POST['pedidos_ids'] ?? []));

        if (!$repartidorId || !$empresaId || !$fecha || empty($pedidosIds)) {
            $this->flash('error', 'Completa todos los campos obligatorios.');
            $this->redirect('panel-logistica/nuevaRuta');
        }

        try {
            $rutaId = $pedidoModel->crearRuta($repartidorId, $empresaId, $fecha, $pedidosIds);
        } catch (\Throwable $e) {
            $this->flash('error', 'Error al crear la ruta: ' . $e->getMessage());
            $this->redirect('panel-logistica/nuevaRuta');
        }

        $this->log('Crear ruta', 'logistica', "Ruta $rutaId — repartidor $repartidorId");
        $this->flash('success', 'Ruta creada y pedidos asignados.');
        $this->redirect('panel-logistica/index');
    }
}
