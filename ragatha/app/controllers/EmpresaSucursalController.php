<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaSucursalController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
    }

    public function index(?string $p = null): void
    {
        $empresaId     = $this->empresaId();
        $sucursalModel = new SucursalModel();
        $sucursales    = $sucursalModel->getByEmpresa($empresaId);

        // Agrupar por comprador (comprador_id puede ser NULL para sucursales de la empresa)
        $porComprador = [];
        foreach ($sucursales as $s) {
            $key = $s['comprador_id'] ?? 0;
            $porComprador[$key][] = $s;
        }

        $flash      = $this->getFlash();
        $pageTitle  = 'Sucursales de compradores';
        $activeMenu = 'sucursales';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/sucursales/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }
}
