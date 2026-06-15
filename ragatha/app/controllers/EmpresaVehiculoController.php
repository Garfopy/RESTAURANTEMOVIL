<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaVehiculoController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
    }

    public function index(?string $p = null): void
    {
        $empresaId    = $this->empresaId();
        $vehModel     = new VehiculoModel();
        $vehiculos    = $vehModel->getByEmpresa($empresaId);

        $usuarioModel = new UsuarioModel();
        $repartidores = $usuarioModel->getRepartidoresPorEmpresa($empresaId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Vehículos';
        $activeMenu = 'vehiculos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/vehiculos/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-vehiculo/index');
        }
        $empresaId = $this->empresaId();
        $modelo    = new VehiculoModel();
        $id = $modelo->crear([
            'empresa_id' => $empresaId,
            'placa'      => $this->post('placa'),
            'modelo'     => $this->post('modelo'),
            'capacidad'  => $this->post('capacidad'),
        ]);
        if ($repartidorId = (int)$this->post('repartidor_id')) {
            $modelo->asignarRepartidor($id, $repartidorId);
        }
        $this->flash('success', 'Vehículo registrado correctamente.');
        $this->redirect('empresa-vehiculo/index');
    }

    public function actualizar(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-vehiculo/index');
        }
        $empresaId = $this->empresaId();
        $id        = (int)$id;
        $modelo    = new VehiculoModel();
        if (!$modelo->pertenece($id, $empresaId)) {
            $this->redirect('empresa-vehiculo/index');
        }
        $modelo->actualizar($id, [
            'placa'     => $this->post('placa'),
            'modelo'    => $this->post('modelo'),
            'capacidad' => $this->post('capacidad'),
        ]);
        $repartidorId = (int)$this->post('repartidor_id');
        if ($repartidorId) {
            $modelo->asignarRepartidor($id, $repartidorId);
        } else {
            $modelo->desasignarRepartidor($id);
        }
        $this->flash('success', 'Vehículo actualizado.');
        $this->redirect('empresa-vehiculo/index');
    }

    public function toggleActivo(?string $id = null): void
    {
        (new VehiculoModel())->toggleActivo((int)$id, $this->empresaId());
        $this->redirect('empresa-vehiculo/index');
    }
}
