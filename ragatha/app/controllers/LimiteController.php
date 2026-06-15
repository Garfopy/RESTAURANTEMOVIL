<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class LimiteController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
    }

    public function index(?string $p = null): void
    {
        $empresaId  = $this->empresaId();
        $modelo     = new LimiteModel();
        $limites    = $modelo->getByEmpresa($empresaId);

        $db        = Database::getInstance();
        $stmtProds = $db->prepare('SELECT id, nombre, presentacion AS unidad FROM productos WHERE empresa_id=? AND activo=1 ORDER BY nombre');
        $stmtProds->execute([$empresaId]);
        $productos = $stmtProds->fetchAll();

        $flash      = $this->getFlash();
        $pageTitle  = 'Límites de compra';
        $activeMenu = 'limites';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/limites/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('limite/index');
        }
        $empresaId = $this->empresaId();
        (new LimiteModel())->crear([
            'empresa_id'  => $empresaId,
            'producto_id' => $this->post('producto_id'),
            'limite_kg'   => $this->post('limite_kg'),
            'periodo'     => $this->post('periodo') ?: 'por_pedido',
            'created_by'  => $this->usuarioId(),
        ]);
        $this->flash('success', 'Límite creado correctamente.');
        $this->redirect('limite/index');
    }

    public function actualizar(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('limite/index');
        }
        $empresaId = $this->empresaId();
        (new LimiteModel())->actualizar((int)$id, [
            'empresa_id'  => $empresaId,
            'producto_id' => $this->post('producto_id'),
            'limite_kg'   => $this->post('limite_kg'),
            'periodo'     => $this->post('periodo') ?: 'por_pedido',
        ]);
        $this->flash('success', 'Límite actualizado.');
        $this->redirect('limite/index');
    }

    public function toggleActivo(?string $id = null): void
    {
        (new LimiteModel())->toggleActivo((int)$id, $this->empresaId());
        $this->redirect('limite/index');
    }

    public function eliminar(?string $id = null): void
    {
        (new LimiteModel())->eliminar((int)$id, $this->empresaId());
        $this->flash('success', 'Límite eliminado.');
        $this->redirect('limite/index');
    }
}
