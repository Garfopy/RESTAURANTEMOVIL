<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestMermasController extends BaseController
{
    private RestMermasModel $model;
    private RestInventarioModel $inv;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();

        $rol = $_SESSION['usuario']['rol_slug'] ?? '';
        if (!in_array($rol, ['admin_restaurante', 'comprador', 'admin_local'], true)) {
            $this->redirect('restaurante/dashboard');
        }

        $this->model = new RestMermasModel();
        $this->inv   = new RestInventarioModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $desde = $this->get('desde', date('Y-m-d', strtotime('-30 days')));
        $hasta = $this->get('hasta', date('Y-m-d'));

        // Sanitizar fechas (Y-m-d)
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$desde) ? $desde : date('Y-m-d', strtotime('-30 days'));
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$hasta) ? $hasta : date('Y-m-d');

        $kpis        = $this->model->kpis($restauranteId, $desde, $hasta);
        $topMermas   = $this->model->topIngredientes($restauranteId, $desde, $hasta, 'DESC', 10);
        $menosMermas = $this->model->topIngredientes($restauranteId, $desde, $hasta, 'ASC', 10);
        $porMotivo   = $this->model->porMotivo($restauranteId, $desde, $hasta);
        $tendencia   = $this->model->tendenciaDiaria($restauranteId, $desde, $hasta);
        $detalle     = $this->model->detalle($restauranteId, $desde, $hasta, 500);
        $topRotacion = $this->model->topRotacion($restauranteId, $desde, $hasta, 5);
        $alertas     = $this->inv->alertasStockBajo($restauranteId);
        $ingredientes = $this->inv->getByRestaurante($restauranteId, true);

        // Restaurante (para header del reporte)
        $restModel   = new RestauranteModel();
        $restaurante = $restModel->find($restauranteId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Mermas';
        $activeMenu = 'rest_mermas';

        $this->render('restaurante/mermas/index', compact(
            'kpis', 'topMermas', 'menosMermas', 'porMotivo', 'tendencia',
            'detalle', 'topRotacion', 'alertas', 'ingredientes',
            'restaurante', 'desde', 'hasta', 'flash', 'pageTitle', 'activeMenu'
        ));
    }

    public function registrar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-mermas/index');

        $ingredienteId = (int)$this->post('ingrediente_id');
        $cantidad      = abs((float)$this->post('cantidad', 0));
        $motivo        = trim((string)$this->post('motivo', ''));

        if ($ingredienteId <= 0 || $cantidad <= 0) {
            $this->flash('error', 'Ingrediente y cantidad son obligatorios.');
            $this->redirect('rest-mermas/index');
        }

        try {
            $this->inv->ajustarStock(
                $ingredienteId,
                -$cantidad,
                'merma',
                $motivo,
                null,
                $this->restauranteId(),
                $this->usuarioId()
            );
            $this->flash('success', 'Merma registrada correctamente.');
        } catch (\Throwable $e) {
            $this->flash('error', 'No se pudo registrar la merma: ' . $e->getMessage());
        }

        $this->redirect('rest-mermas/index');
    }
}
