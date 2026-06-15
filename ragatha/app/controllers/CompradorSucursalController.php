<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class CompradorSucursalController extends BaseController
{
    private SucursalModel     $sucursalModel;
    private SuscripcionModel  $suscripcionModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireRole(['comprador']);
        $this->sucursalModel    = new SucursalModel();
        $this->suscripcionModel = new SuscripcionModel();
    }

    public function index(?string $p = null): void
    {
        $compradorId = $this->usuarioId();
        $empresaId   = $this->empresaId();

        $sucursales  = $this->sucursalModel->getAllByComprador($compradorId);
        $suscripcion = $this->suscripcionModel->getByEmpresa($empresaId);
        $maxSucursales = (int)($suscripcion['max_sucursales'] ?? 3);
        $usadas        = $this->sucursalModel->contarPorComprador($compradorId);

        $configModel = new ConfigModel();
        $gmKey       = $configModel->get('google_maps_key', '');

        $flash      = $this->getFlash();
        $pageTitle  = 'Mis sucursales';
        $activeMenu = 'mis_sucursales';

        ob_start();
        require ROOT_PATH . '/app/views/comprador/sucursales/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function nueva(?string $p = null): void
    {
        $compradorId = $this->usuarioId();
        $empresaId   = $this->empresaId();

        $suscripcion   = $this->suscripcionModel->getByEmpresa($empresaId);
        $maxSucursales = (int)($suscripcion['max_sucursales'] ?? 3);
        $usadas        = $this->sucursalModel->contarPorComprador($compradorId);

        if ($maxSucursales > 0 && $usadas >= $maxSucursales) {
            $this->flash('error', "Tu plan permite máximo {$maxSucursales} sucursales. Actualiza tu plan para agregar más.");
            $this->redirect('comprador-sucursal/index');
        }

        $sucursal   = null;
        $flash      = $this->getFlash();
        $pageTitle  = 'Nueva sucursal';
        $activeMenu = 'mis_sucursales';

        ob_start();
        require ROOT_PATH . '/app/views/comprador/sucursales/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('comprador-sucursal/index');
        }

        $compradorId = $this->usuarioId();
        $empresaId   = $this->empresaId();

        $suscripcion   = $this->suscripcionModel->getByEmpresa($empresaId);
        $maxSucursales = (int)($suscripcion['max_sucursales'] ?? 3);
        $usadas        = $this->sucursalModel->contarPorComprador($compradorId);

        if ($maxSucursales > 0 && $usadas >= $maxSucursales) {
            $this->flash('error', "Límite de sucursales alcanzado ({$maxSucursales}).");
            $this->redirect('comprador-sucursal/index');
        }

        $nombre    = trim($this->post('nombre', ''));
        $direccion = trim($this->post('direccion', ''));

        if (!$nombre || !$direccion) {
            $this->flash('error', 'El nombre y la dirección son obligatorios.');
            $this->redirect('comprador-sucursal/nueva');
        }

        $telefono = preg_replace('/\D/', '', trim($this->post('telefono', '')));
        if ($telefono !== '' && strlen($telefono) !== 10) {
            $this->flash('error', 'El teléfono debe tener exactamente 10 dígitos.');
            $this->redirect('comprador-sucursal/nueva');
        }

        $this->sucursalModel->crear([
            'empresa_id'  => $empresaId,
            'comprador_id'=> $compradorId,
            'nombre'      => $nombre,
            'direccion'   => $direccion,
            'lat'         => $this->post('lat', ''),
            'lng'         => $this->post('lng', ''),
            'responsable' => trim($this->post('responsable', '')),
            'telefono'    => $telefono,
        ]);

        $this->flash('success', "Sucursal '{$nombre}' agregada.");
        $this->redirect('comprador-sucursal/index');
    }

    public function editar(?string $p = null): void
    {
        $id          = (int)$p;
        $compradorId = $this->usuarioId();

        $sucursal = $this->sucursalModel->find($id);
        if (!$sucursal || !$this->sucursalModel->perteneceAComprador($id, $compradorId)) {
            $this->redirect('comprador-sucursal/index');
        }

        $flash      = $this->getFlash();
        $pageTitle  = 'Editar sucursal';
        $activeMenu = 'mis_sucursales';

        ob_start();
        require ROOT_PATH . '/app/views/comprador/sucursales/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function actualizar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('comprador-sucursal/index');
        }

        $id          = (int)$this->post('id');
        $compradorId = $this->usuarioId();

        if (!$this->sucursalModel->perteneceAComprador($id, $compradorId)) {
            $this->redirect('comprador-sucursal/index');
        }

        $nombre    = trim($this->post('nombre', ''));
        $direccion = trim($this->post('direccion', ''));

        if (!$nombre || !$direccion) {
            $this->flash('error', 'El nombre y la dirección son obligatorios.');
            $this->redirect("comprador-sucursal/editar/{$id}");
        }

        $telefono = preg_replace('/\D/', '', trim($this->post('telefono', '')));
        if ($telefono !== '' && strlen($telefono) !== 10) {
            $this->flash('error', 'El teléfono debe tener exactamente 10 dígitos.');
            $this->redirect("comprador-sucursal/editar/{$id}");
        }

        $this->sucursalModel->actualizar($id, [
            'nombre'      => $nombre,
            'direccion'   => $direccion,
            'lat'         => $this->post('lat', ''),
            'lng'         => $this->post('lng', ''),
            'responsable' => trim($this->post('responsable', '')),
            'telefono'    => $telefono,
        ]);

        $this->flash('success', 'Sucursal actualizada.');
        $this->redirect('comprador-sucursal/index');
    }

    public function toggleActivo(?string $p = null): void
    {
        $id          = (int)$p;
        $compradorId = $this->usuarioId();

        if ($this->sucursalModel->perteneceAComprador($id, $compradorId)) {
            $this->sucursalModel->toggleActivo($id);
        }

        $this->redirect('comprador-sucursal/index');
    }

    public function eliminar(?string $p = null): void
    {
        $id          = (int)$p;
        $compradorId = $this->usuarioId();

        if ($id > 0 && $this->sucursalModel->perteneceAComprador($id, $compradorId)) {
            $this->sucursalModel->delete($id);
            $this->flash('success', 'Sucursal eliminada.');
        } else {
            $this->flash('error', 'No se pudo eliminar la sucursal.');
        }

        $this->redirect('comprador-sucursal/index');
    }
}
