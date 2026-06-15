<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestClienteController extends BaseController
{
    private RestClienteModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestClienteModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $page      = (int)$this->get('page', 1);
        $resultado = $this->model->getByRestaurante($restauranteId, $page);
        $flash     = $this->getFlash();
        $pageTitle  = 'Comensales';
        $activeMenu = 'rest_clientes';
        $this->render('restaurante/clientes/index', array_merge($resultado, compact('flash','pageTitle','activeMenu')));
    }

    public function detalle(?string $id = null): void
    {
        $comensal = $this->model->getDetalle((int)$id);
        if (!$comensal) { $this->flash('error', 'Comensal no encontrado.'); $this->redirect('rest-cliente/index'); }
        $historial = $this->model->getHistorialVisitas((int)$id);
        $flash     = $this->getFlash();
        $pageTitle  = $comensal['nombre'] ?? 'Comensal';
        $activeMenu = 'rest_clientes';
        $this->render('restaurante/clientes/detalle', compact('comensal','historial','flash','pageTitle','activeMenu'));
    }

    public function topConsumo(?string $p = null): void
    {
        $top       = $this->model->topPorConsumo($this->restauranteId());
        $flash     = $this->getFlash();
        $pageTitle  = 'Top por Consumo';
        $activeMenu = 'rest_clientes';
        $this->render('restaurante/clientes/top', compact('top','flash','pageTitle','activeMenu'));
    }

    public function topVisitas(?string $p = null): void
    {
        $top       = $this->model->topPorVisitas($this->restauranteId());
        $flash     = $this->getFlash();
        $pageTitle  = 'Top por Visitas';
        $activeMenu = 'rest_clientes';
        $this->render('restaurante/clientes/top', compact('top','flash','pageTitle','activeMenu'));
    }
}
