<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestFinanzasController extends BaseController
{
    private RestFinanzasModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestFinanzasModel();
    }

    public function dashboard(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $desde  = $this->get('desde', date('Y-m-01'));
        $hasta  = $this->get('hasta', date('Y-m-d'));

        $kpis   = $this->model->kpisDashboard($restauranteId, $desde, $hasta);
        $grafica = $this->model->ingresosVsEgresosGrafica($restauranteId, $desde, $hasta);
        $catGastos = $this->model->gastosPorCategoria($restauranteId, $desde, $hasta);
        $metodos = $this->model->metodosPago($restauranteId, $desde, $hasta);
        $reciente = $this->model->actividadReciente($restauranteId);

        $flash  = $this->getFlash();
        $pageTitle  = 'Financiero';
        $activeMenu = 'rest_finanzas';
        $this->render('restaurante/finanzas/dashboard', compact(
            'kpis','grafica','catGastos','metodos','reciente','desde','hasta','flash','pageTitle','activeMenu'
        ));
    }

    public function gastos(?string $p = null): void
    {
        $this->redirect('rest-finanzas/egresos?tab=gastos');
    }

    public function retiros(?string $p = null): void
    {
        $this->redirect('rest-finanzas/egresos?tab=retiros');
    }

    public function egresos(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $tab      = $this->get('tab', 'gastos');
        $tab      = in_array($tab, ['gastos','retiros'], true) ? $tab : 'gastos';
        $page     = (int)$this->get('page', 1);

        $resGastos  = $this->model->getGastos($restauranteId, $page);
        $resRetiros = $this->model->getRetiros($restauranteId, $page);

        $flash     = $this->getFlash();
        $pageTitle = 'Gastos y Retiros';
        $activeMenu = 'rest_egresos';
        $this->render('restaurante/finanzas/egresos', compact(
            'resGastos','resRetiros','tab','flash','pageTitle','activeMenu'
        ));
    }

    public function guardarGasto(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-finanzas/egresos?tab=gastos');
        $this->model->insertGasto([
            'restaurante_id' => $this->restauranteId(),
            'categoria'      => $this->post('categoria', 'otros'),
            'descripcion'    => trim($this->post('descripcion', '')),
            'monto'          => (float)$this->post('monto', 0),
            'fecha'          => $this->post('fecha', date('Y-m-d')),
            'comprobante'    => $this->post('comprobante') ?: null,
            'usuario_id'     => $this->usuarioId(),
        ]);
        $this->flash('success', 'Gasto registrado.');
        $this->redirect('rest-finanzas/egresos?tab=gastos');
    }

    public function guardarRetiro(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-finanzas/egresos?tab=retiros');
        $this->model->insertRetiro([
            'restaurante_id' => $this->restauranteId(),
            'descripcion'    => trim($this->post('descripcion', '')),
            'monto'          => (float)$this->post('monto', 0),
            'usuario_id'     => $this->usuarioId(),
        ]);
        $this->flash('success', 'Retiro registrado.');
        $this->redirect('rest-finanzas/egresos?tab=retiros');
    }

    public function cortes(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $page     = (int)$this->get('page', 1);
        $resultado = $this->model->getCortes($restauranteId, $page);
        $flash    = $this->getFlash();
        $pageTitle = 'Cortes de Caja';
        $activeMenu = 'rest_cortes';
        $this->render('restaurante/finanzas/cortes', array_merge($resultado, compact('flash','pageTitle','activeMenu')));
    }

    public function guardarCorte(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-finanzas/cortes');
        $restauranteId = $this->restauranteId();
        $desde  = $this->post('desde', date('Y-m-d'));
        $hasta  = $this->post('hasta', date('Y-m-d'));
        $kpis   = $this->model->kpisDashboard($restauranteId, $desde, $hasta);

        $this->model->insertCorte([
            'restaurante_id' => $restauranteId,
            'turno'          => $this->post('turno', 'General'),
            'usuario_id'     => $this->usuarioId(),
            'ingresos'       => $kpis['ingresos'],
            'gastos'         => $kpis['gastos'],
            'retiros'        => $kpis['retiros'],
            'propinas'       => $kpis['propinas'],
            'utilidad_neta'  => $kpis['utilidad'],
            'notas'          => $this->post('notas'),
        ]);
        $this->flash('success', 'Corte de caja registrado.');
        $this->redirect('rest-finanzas/cortes');
    }
}
