<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

/**
 * PedidoController — Gestión de pedidos desde el portal empresa.
 *
 * Todos los roles empresa pueden ver pedidos.
 * Solo comprador/admin_empresa pueden crear (via CarritoController).
 * Solo supervisor/admin_empresa pueden aprobar.
 */
class PedidoController extends BaseController
{
    private PedidoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireEmpresa();
        $this->model = new PedidoModel();
    }

    // ── Historial de pedidos ──────────────────────────────────────
    public function index(?string $p = null): void
    {
        $filtros = [
            'estado' => $this->get('estado', ''),
            'buscar' => $this->get('buscar', ''),
        ];
        $page = max(1, (int)$this->get('page', 1));

        $resultado  = $this->model->listadoEmpresa($this->empresaId(), $filtros, $page);
        $pedidos    = $resultado['data'];
        $paginacion = $resultado;

        $flash      = $this->getFlash();
        $pageTitle  = 'Mis pedidos';
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/pedidos/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // ── Detalle de un pedido ──────────────────────────────────────
    public function detalle(?string $id = null): void
    {
        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/index');
        }

        $pedido = $this->model->conDetalle($pedidoId);
        $historial = $this->model->getHistorial($pedidoId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Pedido ' . $pedido['folio'];
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/pedidos/detalle.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // ── Vista de aprobación (supervisor + admin_empresa) ──────────
    public function aprobacion(?string $p = null): void
    {
        $this->requireSupervisor();

        $pendientes = $this->model->pendientesAprobacion($this->empresaId());

        $flash      = $this->getFlash();
        $pageTitle  = 'Aprobaciones pendientes';
        $activeMenu = 'aprobacion';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/pedidos/aprobacion.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // ── Aprobar pedido ────────────────────────────────────────────
    public function aprobar(?string $id = null): void
    {
        $this->requireSupervisor();

        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/aprobacion');
        }

        $ok = $this->model->aprobar($pedidoId, $this->usuarioId());
        if ($ok) {
            $pedido = $this->model->find($pedidoId);
            $this->log('aprobar_pedido', 'pedidos', "Aprobado {$pedido['folio']}");
            $this->flash('success', 'Pedido aprobado correctamente.');
        } else {
            $this->flash('error', 'No se pudo aprobar. Verifica que el pedido esté pendiente.');
        }

        $this->redirect('pedido/aprobacion');
    }

    // ── Rechazar pedido ───────────────────────────────────────────
    public function rechazar(?string $id = null): void
    {
        $this->requireSupervisor();

        if (!$this->isPost()) {
            $this->redirect('pedido/aprobacion');
        }

        $pedidoId = (int)$id;
        $motivo   = trim($this->post('motivo', ''));

        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/aprobacion');
        }

        if (empty($motivo)) {
            $this->flash('error', 'Debes indicar el motivo del rechazo.');
            $this->redirect('pedido/aprobacion');
        }

        $ok = $this->model->rechazar($pedidoId, $this->usuarioId(), $motivo);
        if ($ok) {
            $pedido = $this->model->find($pedidoId);
            $this->log('rechazar_pedido', 'pedidos', "Rechazado {$pedido['folio']}: {$motivo}");
            $this->flash('success', 'Pedido rechazado.');
        } else {
            $this->flash('error', 'No se pudo rechazar el pedido.');
        }

        $this->redirect('pedido/aprobacion');
    }

    // ── Tracking GPS en tiempo real ───────────────────────────────
    public function tracking(?string $id = null): void
    {
        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/index');
        }

        $pedido    = $this->model->conDetalle($pedidoId);
        $tracking  = $this->model->getTrackingActivo($pedidoId);
        $sucursales = $this->model->getSucursalesPedido($pedidoId);

        $cfgModel = new ConfigModel();
        $firebaseConfig = [
            'apiKey'      => $cfgModel->get('firebase_api_key', ''),
            'authDomain'  => $cfgModel->get('firebase_auth_domain', ''),
            'databaseURL' => $cfgModel->get('firebase_database_url', ''),
            'projectId'   => $cfgModel->get('firebase_project_id', ''),
            'appId'       => $cfgModel->get('firebase_app_id', ''),
        ];
        $firebaseActivo = !empty($firebaseConfig['apiKey']) && !empty($firebaseConfig['databaseURL']);

        $flash      = $this->getFlash();
        $pageTitle  = 'Seguimiento — ' . $pedido['folio'];
        $activeMenu = 'pedidos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/pedidos/tracking.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // ── Imprimir / exportar PDF de un pedido ─────────────────────
    public function pdf(?string $id = null): void
    {
        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/index');
        }

        $pedido       = $this->model->conDetalle($pedidoId);
        $empresa      = (new EmpresaModel())->find($this->empresaId());
        $configModel  = new ConfigModel();
        $appLogo      = $configModel->get('app_logo', '');
        $colorPrimary = $configModel->get('color_primary', '#C8102E');

        require ROOT_PATH . '/app/views/empresa/pedidos/pdf.php';
    }

    // ── Cancelar pedido (solo comprador, solo si está pendiente) ──
    public function cancelar(?string $id = null): void
    {
        $this->requireComprador();

        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/index');
        }

        $ok = $this->model->cancelar($pedidoId, $this->usuarioId());
        $this->flash($ok ? 'success' : 'error', $ok ? 'Pedido cancelado.' : 'No se puede cancelar este pedido.');
        $this->redirect('pedido/detalle/' . $pedidoId);
    }

    // ── Subir comprobante de pago (comprador) ─────────────────────
    public function subirComprobante(?string $id = null): void
    {
        $this->requireComprador();

        if (!$this->isPost()) {
            $this->redirect('pedido/index');
        }

        $pedidoId = (int)$id;
        if (!$pedidoId || !$this->model->verificarPertenece($pedidoId, $this->empresaId())) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('pedido/index');
        }

        $pedido = $this->model->find($pedidoId);
        if (!$pedido || $pedido['comprador_id'] != $this->usuarioId()) {
            $this->flash('error', 'No tienes permiso para modificar este pedido.');
            $this->redirect('pedido/index');
        }

        if (empty($_FILES['comprobante']['tmp_name'])) {
            $this->flash('error', 'Selecciona una imagen de comprobante.');
            $this->redirect('pedido/detalle/' . $pedidoId);
        }

        $dir     = $_SERVER['DOCUMENT_ROOT'] . '/public/uploads/evidencias/';
        $ext     = strtolower(pathinfo($_FILES['comprobante']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($ext, $allowed, true)) {
            $this->flash('error', 'Formato no permitido. Usa JPG, PNG, WEBP o PDF.');
            $this->redirect('pedido/detalle/' . $pedidoId);
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $filename = 'comp_' . $pedidoId . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($_FILES['comprobante']['tmp_name'], $dir . $filename)) {
            $this->flash('error', 'Error al guardar el archivo. Contacta al administrador.');
            $this->redirect('pedido/detalle/' . $pedidoId);
        }

        $path = '/public/uploads/evidencias/' . $filename;
        $this->model->subirComprobante($pedidoId, $path);
        $this->log('subir_comprobante', 'pedidos', "Pedido $pedidoId — comprobante subido");
        $this->flash('success', 'Comprobante enviado. La empresa lo verificará.');
        $this->redirect('pedido/detalle/' . $pedidoId);
    }
}
