<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaComboController extends BaseController
{
    private ComboModel $comboModel;
    private ProductoModel $productoModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
        $this->comboModel    = new ComboModel();
        $this->productoModel = new ProductoModel();
    }

    public function index(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $combos    = $this->comboModel->listadoEmpresa($empresaId);
        $flash     = $this->getFlash();
        $pageTitle = 'Combos por Comprador';
        $activeMenu = 'combos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/combos/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function nuevo(?string $p = null): void
    {
        $empresaId  = $this->empresaId();
        $productos  = $this->productoModel->listadoAdmin(['empresa_id' => $empresaId], 1)['data'] ?? [];
        $usuarioModel = new UsuarioModel();
        $compradores = $usuarioModel->getByRolEmpresa('comprador', $empresaId);
        $combo      = null;
        $flash      = $this->getFlash();
        $pageTitle  = 'Nuevo Combo';
        $activeMenu = 'combos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/combos/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-combo');
        }

        $empresaId = $this->empresaId();
        $nombre    = trim($this->post('nombre'));
        if (!$nombre) {
            $this->flash('error', 'El nombre del combo es obligatorio.');
            $this->redirect('empresa-combo/nuevo');
        }

        $precioRaw = trim((string)$this->post('precio', ''));
        $precio    = $precioRaw === '' ? null : round((float)$precioRaw, 2);

        $id = $this->comboModel->insert([
            'empresa_id'  => $empresaId,
            'nombre'      => $nombre,
            'descripcion' => trim($this->post('descripcion', '')) ?: null,
            'precio'      => $precio,
            'activo'      => 1,
        ]);

        $this->comboModel->guardarItems(
            $id,
            (array)($this->post('producto_id', []) ?: $_POST['producto_id'] ?? []),
            (array)($this->post('cantidad', []) ?: $_POST['cantidad'] ?? [])
        );

        $this->comboModel->guardarCompradores(
            $id,
            (array)($_POST['comprador_id'] ?? [])
        );

        $this->log('Crear combo', 'combos', "ID: $id — $nombre");
        $this->flash('success', "Combo \"$nombre\" creado correctamente.");
        $this->redirect('empresa-combo');
    }

    public function editar(?string $p = null): void
    {
        $empresaId  = $this->empresaId();
        $id         = (int)$p;
        $combo      = $this->comboModel->getConDetalle($id);

        if (!$combo || $combo['empresa_id'] != $empresaId) {
            $this->flash('error', 'Combo no encontrado.');
            $this->redirect('empresa-combo');
        }

        $productos  = $this->productoModel->listadoAdmin(['empresa_id' => $empresaId], 1)['data'] ?? [];
        $usuarioModel = new UsuarioModel();
        $compradores = $usuarioModel->getByRolEmpresa('comprador', $empresaId);
        $flash      = $this->getFlash();
        $pageTitle  = 'Editar: ' . $combo['nombre'];
        $activeMenu = 'combos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/combos/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function actualizar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-combo');
        }

        $empresaId = $this->empresaId();
        $id        = (int)$p;

        if (!$this->comboModel->perteneceAEmpresa($id, $empresaId)) {
            $this->redirect('empresa-combo');
        }

        $nombre = trim($this->post('nombre'));
        if (!$nombre) {
            $this->flash('error', 'El nombre del combo es obligatorio.');
            $this->redirect("empresa-combo/editar/$id");
        }

        $precioRaw = trim((string)$this->post('precio', ''));
        $precio    = $precioRaw === '' ? null : round((float)$precioRaw, 2);

        $this->comboModel->update($id, [
            'nombre'      => $nombre,
            'descripcion' => trim($this->post('descripcion', '')) ?: null,
            'precio'      => $precio,
        ]);

        $this->comboModel->guardarItems(
            $id,
            (array)($_POST['producto_id'] ?? []),
            (array)($_POST['cantidad'] ?? [])
        );

        $this->comboModel->guardarCompradores(
            $id,
            (array)($_POST['comprador_id'] ?? [])
        );

        $this->log('Editar combo', 'combos', "ID: $id — $nombre");
        $this->flash('success', "Combo actualizado correctamente.");
        $this->redirect('empresa-combo');
    }

    public function eliminar(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $id        = (int)$p;

        if (!$this->comboModel->perteneceAEmpresa($id, $empresaId)) {
            $this->redirect('empresa-combo');
        }

        $this->comboModel->update($id, ['activo' => 0]);
        $this->log('Desactivar combo', 'combos', "ID: $id");
        $this->flash('success', 'Combo desactivado.');
        $this->redirect('empresa-combo');
    }

    public function activar(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $id        = (int)$p;

        if (!$this->comboModel->perteneceAEmpresa($id, $empresaId)) {
            $this->redirect('empresa-combo');
        }

        $this->comboModel->update($id, ['activo' => 1]);
        $this->log('Activar combo', 'combos', "ID: $id");
        $this->flash('success', 'Combo activado.');
        $this->redirect('empresa-combo');
    }
}
