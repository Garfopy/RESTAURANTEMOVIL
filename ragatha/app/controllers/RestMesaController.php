<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestMesaController extends BaseController
{
    private RestMesaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestMesaModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $filtro = $_GET['estado'] ?? 'activas';
        $activo = $filtro === 'inactivas' ? false : ($filtro === 'todas' ? null : true);
        $mesas  = $this->model->getByRestaurante($restauranteId, $activo);
        $countActivas   = count($this->model->getByRestaurante($restauranteId, true));
        $countInactivas = count($this->model->getByRestaurante($restauranteId, false));
        $zonas  = $this->model->getZonas($restauranteId);
        $rest   = (new RestauranteModel())->find($restauranteId);
        $reservasTiempoHoy = $this->model->reservacionesActivasHoy($restauranteId);
        $flash  = $this->getFlash();
        $pageTitle  = 'Mesas';
        $activeMenu = 'rest_mesas';
        $this->render('restaurante/mesas/index', compact('mesas','zonas','rest','flash','pageTitle','activeMenu','filtro','countActivas','countInactivas','reservasTiempoHoy'));
    }

    public function layout(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $mesas  = $this->model->getByRestaurante($restauranteId, true);
        $zonas  = $this->model->getZonas($restauranteId);
        $rest   = (new RestauranteModel())->find($restauranteId);
        $flash  = $this->getFlash();
        $pageTitle  = 'Layout de Mesas';
        $activeMenu = 'rest_mesas';
        $this->render('restaurante/mesas/layout', compact('mesas','zonas','rest','flash','pageTitle','activeMenu'));
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-mesa/index');
        $restauranteId = $this->restauranteId();

        $id = (int)$this->post('id');
        $data = [
            'restaurante_id' => $restauranteId,
            'zona_id'        => $this->post('zona_id') ?: null,
            'nombre'         => trim($this->post('nombre', '')),
            'capacidad'      => (int)$this->post('capacidad', 4),
        ];

        if ($id) {
            $this->model->update($id, array_diff_key($data, ['restaurante_id' => '']));
        } else {
            $newId = $this->model->insert(array_merge($data, ['qr_codigo' => '']));
            $qr    = $this->model->generarQr($restauranteId, $newId);
            $this->model->update($newId, ['qr_codigo' => $qr]);
        }

        $this->flash('success', 'Mesa guardada.');
        $this->redirect('rest-mesa/index');
    }

    public function eliminar(?string $id = null): void
    {
        $mesa = $this->model->find((int)$id);
        $estado = strtolower((string)($mesa['estado'] ?? 'disponible'));
        if (in_array($estado, ['ocupada','pagando','reservada'], true)) {
            $this->flash('error', 'No se puede desactivar una mesa con un proceso en curso (' . $estado . ').');
            $this->redirect('rest-mesa/index');
            return;
        }
        $this->model->update((int)$id, ['activo' => 0]);
        $this->flash('success', 'Mesa desactivada.');
        $this->redirect('rest-mesa/index');
    }

    /** Endpoint JSON para polling en vivo de estados de mesas */
    public function estados(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $mesas = $this->model->getByRestaurante($restauranteId, true);
        $this->json(array_map(fn($m) => [
            'id'     => (int)$m['id'],
            'estado' => $m['estado'] ?? 'disponible',
        ], $mesas));
    }

    public function activar(?string $id = null): void
    {
        $this->model->update((int)$id, ['activo' => 1]);
        $this->flash('success', 'Mesa reactivada.');
        $this->redirect('rest-mesa/index?estado=inactivas');
    }

    public function borrar(?string $id = null): void
    {
        $this->model->delete((int)$id);
        $this->flash('success', 'Mesa eliminada permanentemente.');
        $this->redirect('rest-mesa/index?estado=inactivas');
    }

    public function actualizarPosicion(?string $p = null): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?? [];
        foreach ($data as $item) {
            $this->model->actualizarPosicion((int)$item['id'], (int)$item['x'], (int)$item['y']);
        }
        $this->json(['ok' => true]);
    }

    public function guardarZona(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-mesa/index');
        $this->model->crearZona([
            'restaurante_id' => $this->restauranteId(),
            'nombre'         => trim($this->post('nombre', 'Nueva zona')),
            'descripcion'    => $this->post('descripcion'),
        ]);
        $this->flash('success', 'Zona creada.');
        $this->redirect('rest-mesa/index');
    }
}
