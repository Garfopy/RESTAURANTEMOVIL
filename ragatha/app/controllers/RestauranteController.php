<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestauranteController extends BaseController
{
    private RestauranteModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRole(['comprador', 'admin_restaurante', 'admin_local']);
        $this->model = new RestauranteModel();
    }

    public function index(?string $p = null): void
    {
        $this->requireRestaurante();
        $this->redirect('restaurante/dashboard');
    }

    public function locales(?string $p = null): void
    {
        $this->requireRestaurante();
        $compradorId         = $this->usuarioId();
        $rol                 = $this->rolActual();
        $sucursales = $rol === 'admin_restaurante'
            ? $this->model->getByEmpresa((int)$this->empresaId())
            : $this->model->getByComprador($compradorId);
        $restauranteActivoId = $this->restauranteId();
        $menuModel           = new RestMenuModel();
        $invModel            = new RestInventarioModel();
        foreach ($sucursales as &$s) {
            $s['num_platillos']    = count($menuModel->getByRestaurante((int)$s['id'], true));
            $s['num_ingredientes'] = count($invModel->getByRestaurante((int)$s['id'], true));
        }
        unset($s);

        $sucursalesCarniHub = [];

        $pageTitle  = 'Mis Locales';
        $activeMenu = 'rest_locales';
        $flash      = $this->getFlash();
        $this->render('restaurante/locales', compact(
            'sucursales','restauranteActivoId','sucursalesCarniHub','pageTitle','activeMenu','flash'
        ));
    }

    public function vincularSucursal(?string $p = null): void
    {
        $this->requireRestaurante();
        if (!$this->isPost()) { $this->redirect('restaurante/locales'); return; }

        $localId    = (int)($_POST['local_id'] ?? 0);
        $sucursalId = (isset($_POST['sucursal_id']) && $_POST['sucursal_id'] !== '')
                      ? (int)$_POST['sucursal_id'] : null;

        if ($localId) {
            $local = $this->model->find($localId);
            if ($local && (int)$local['comprador_id'] === $this->usuarioId()) {
                $db   = \Database::getInstance();
                $stmt = $db->prepare("UPDATE rest_restaurantes SET sucursal_id = ? WHERE id = ?");
                $stmt->execute([$sucursalId, $localId]);
                $this->flash('success', 'Vinculación actualizada.');
            }
        }
        $this->redirect('restaurante/locales');
    }

    public function seleccionar(?string $p = null): void
    {
        $compradorId   = $this->usuarioId();
        $restaurantes  = $this->model->getByComprador($compradorId);

        if (count($restaurantes) === 1) {
            $_SESSION['restaurante_activo_id'] = $restaurantes[0]['id'];
            $this->redirect('restaurante/dashboard');
        }

        $flash     = $this->getFlash();
        $pageTitle = 'Mis Restaurantes';
        $this->render('restaurante/seleccionar', compact('restaurantes', 'flash', 'pageTitle'));
    }

    public function activar(?string $id = null): void
    {
        $restauranteId = (int)($id ?? $this->post('restaurante_id'));
        if (!$this->model->verificarAcceso($restauranteId, $this->usuarioId())) {
            $this->flash('error', 'Sin acceso a ese restaurante.');
            $this->redirect('restaurante/seleccionar');
        }
        $_SESSION['restaurante_activo_id'] = $restauranteId;
        $redirect = isset($_GET['redirect']) ? trim($_GET['redirect'], '/') : null;
        if ($redirect && preg_match('/^[a-zA-Z0-9\/_-]+$/', $redirect)) {
            $this->redirect($redirect);
        } else {
            $this->redirect('restaurante/dashboard');
        }
    }

    public function dashboard(?string $p = null): void
    {
        $this->requireRestaurante();
        $restauranteId = $this->restauranteId();
        $restaurante   = $this->model->getConStats($restauranteId);

        $finanzas   = new RestFinanzasModel();
        $hoy        = date('Y-m-d');
        $inicioMes  = date('Y-m-01');
        $kpis       = $finanzas->kpisDashboard($restauranteId, $inicioMes, $hoy);

        $pedidos    = new RestPedidoModel();
        $activos    = $pedidos->getKitchenQueue($restauranteId);

        $inventario = new RestInventarioModel();
        $alertas    = $inventario->alertasStockBajo($restauranteId);

        $reservas   = new RestReservaModel();
        $proximas   = $reservas->getProximas($restauranteId, 3);

        $menuModel     = new RestMenuModel();
        $topVendidos   = $menuModel->getTopVendidos($restauranteId, 5);
        $menosVendidos = $menuModel->getMenosVendidos($restauranteId, 5);

        $linkStaff  = BASE_URL . 'acceso/' . $restaurante['slug'] . '/staff';
        $linkMenu   = BASE_URL . 'menu/'   . $restaurante['slug'];
        $flash      = $this->getFlash();
        $pageTitle  = 'Dashboard — ' . $restaurante['nombre'];
        $activeMenu = 'rest_dashboard';
        $this->render('restaurante/dashboard', compact(
            'restaurante','kpis','activos','alertas','proximas',
            'topVendidos','menosVendidos',
            'linkStaff','linkMenu','flash','pageTitle','activeMenu'
        ));
    }

    public function crear(?string $p = null): void
    {
        $flash     = $this->getFlash();
        $pageTitle = 'Crear Restaurante';
        $this->render('restaurante/form', compact('flash', 'pageTitle'));
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('restaurante/crear');

        $nombre = trim($this->post('nombre', ''));
        if (!$nombre) {
            $this->flash('error', 'El nombre es obligatorio.');
            $this->redirect('restaurante/crear');
        }

        $compradorId = $this->usuarioId();
        $usuario     = $_SESSION['usuario'];
        $slug        = $this->model->generarSlugUnico($nombre);

        $id = $this->model->insert([
            'empresa_id'      => $usuario['empresa_id'],
            'comprador_id'    => $compradorId,
            'nombre'          => $nombre,
            'slug'            => $slug,
            'color_primario'  => $this->post('color_primario', '#C8102E'),
            'color_secundario'=> $this->post('color_secundario', '#1f2937'),
            'descripcion'     => $this->post('descripcion'),
            'telefono'        => $this->post('telefono'),
            'direccion'       => $this->post('direccion'),
            'horario_apertura'=> $this->post('horario_apertura') ?: null,
            'horario_cierre'  => $this->post('horario_cierre') ?: null,
        ]);

        $_SESSION['restaurante_activo_id'] = $id;
        $this->flash('success', 'Restaurante creado correctamente.');
        $this->redirect('restaurante/bienvenida');
    }

    public function bienvenida(?string $p = null): void
    {
        $this->requireRestaurante();
        $restauranteId = $this->restauranteId();
        $restaurante   = $this->model->find($restauranteId);
        $linkStaff     = BASE_URL . 'acceso/' . $restaurante['slug'] . '/staff';
        $linkMenu      = BASE_URL . 'menu/'   . $restaurante['slug'];
        $pageTitle     = '¡Restaurante creado!';
        $activeMenu    = 'rest_dashboard';
        $this->render('restaurante/bienvenida',
            compact('restaurante','linkStaff','linkMenu','pageTitle','activeMenu'));
    }

    public function editar(?string $id = null): void
    {
        $this->requireRestaurante();
        $restauranteId = $id ? (int)$id : $this->restauranteId();
        if (!$this->model->verificarAcceso($restauranteId, $this->usuarioId())) {
            $this->flash('error', 'Sin acceso.'); $this->redirect('restaurante/seleccionar');
        }
        $restaurante = $this->model->find($restauranteId);
        $flash       = $this->getFlash();
        $pageTitle   = 'Editar Restaurante';
        $activeMenu  = 'rest_config';
        $this->render('restaurante/form', compact('restaurante','flash','pageTitle','activeMenu'));
    }

    public function actualizar(?string $id = null): void
    {
        if (!$this->isPost()) $this->redirect('restaurante/seleccionar');
        $this->requireRestaurante();
        $restauranteId = (int)($id ?? $this->post('id'));

        if (!$this->model->verificarAcceso($restauranteId, $this->usuarioId())) {
            $this->flash('error', 'Sin acceso.'); $this->redirect('restaurante/seleccionar');
        }

        $this->model->update($restauranteId, [
            'nombre'          => trim($this->post('nombre', '')),
            'color_primario'  => $this->post('color_primario', '#C8102E'),
            'color_secundario'=> $this->post('color_secundario', '#1f2937'),
            'descripcion'     => $this->post('descripcion'),
            'telefono'        => $this->post('telefono'),
            'direccion'       => $this->post('direccion'),
            'horario_apertura'=> $this->post('horario_apertura') ?: null,
            'horario_cierre'  => $this->post('horario_cierre') ?: null,
        ]);

        $this->flash('success', 'Restaurante actualizado.');
        $this->redirect('restaurante/dashboard');
    }
}
