<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestReservaController extends BaseController
{
    private RestReservaModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestReservaModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $db            = Database::getInstance();
        $estado        = $this->get('estado', '');
        $fecha_desde   = $this->get('fecha_desde', '');
        $fecha_hasta   = $this->get('fecha_hasta', '');
        $page          = (int)$this->get('page', 1);
        $resultado     = $this->model->getByRestaurante($restauranteId, $page, $estado ?: null, $fecha_desde ?: null, $fecha_hasta ?: null);

        // Mesas activas para el select del modal
        $stmtM = $db->prepare(
            "SELECT id, nombre, capacidad FROM rest_mesas
             WHERE restaurante_id = ? AND activo = 1 ORDER BY nombre ASC"
        );
        $stmtM->execute([$restauranteId]);
        $mesas = $stmtM->fetchAll(PDO::FETCH_ASSOC);

        $flash     = $this->getFlash();
        $pageTitle  = 'Reservaciones';
        $activeMenu = 'rest_reservas';
        $this->render('restaurante/reservas/index',
            array_merge($resultado, compact('mesas','flash','pageTitle','activeMenu','estado','fecha_desde','fecha_hasta')));
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-reserva/index');

        $restauranteId = $this->restauranteId();
        $restaurante   = (new RestauranteModel())->find($restauranteId);

        // Validar que las reservaciones estén habilitadas
        if (empty($restaurante['reservas_habilitadas'])) {
            $this->flash('error', 'Las reservaciones están deshabilitadas en la configuración del restaurante.');
            $this->redirect('rest-reserva/index');
            return;
        }

        $id     = (int)$this->post('id');
        $mesaId = $this->post('mesa_id') ? (int)$this->post('mesa_id') : null;
        $fecha  = $this->post('fecha');
        $hora   = $this->post('hora');

        // Validar conflicto de mesa (solo si se eligió una mesa)
        if ($mesaId && $fecha && $hora) {
            if ($this->model->hayConflicto($mesaId, $fecha, $hora, $id ?: null)) {
                $this->flash('error', 'Esa mesa ya tiene una reservación en ese horario (±2 horas).');
                $this->redirect('rest-reserva/index');
                return;
            }
        }

        // Auto-asignar mesero según zona de la mesa (solo en creaciones nuevas o sin mesero)
        $meseroId = null;
        if ($mesaId) {
            $meseroId = $this->model->meseroAsignadoPorMesa($mesaId, $restauranteId);
        }

        $data = [
            'restaurante_id' => $restauranteId,
            'mesa_id'        => $mesaId,
            'nombre'         => trim($this->post('nombre', '')),
            'telefono'       => $this->post('telefono') ?: null,
            'email'          => $this->post('email') ?: null,
            'fecha'          => $fecha,
            'hora'           => $hora,
            'personas'       => (int)$this->post('personas', 2),
            'notas'          => $this->post('notas') ?: null,
            'origen'         => 'restaurante',
        ];

        if ($id) {
            // En edición: solo actualizar mesero_id si aún está vacío
            $this->model->update($id, $data);
            if ($meseroId) {
                $db = Database::getInstance();
                $db->prepare(
                    "UPDATE rest_reservaciones SET mesero_id = ? WHERE id = ? AND mesero_id IS NULL"
                )->execute([$meseroId, $id]);
            }
        } else {
            $data['mesero_id'] = $meseroId;
            $this->model->insert($data);
        }

        $this->flash('success', 'Reservación guardada.' . ($meseroId ? ' Mesero auto-asignado.' : ''));
        $this->redirect('rest-reserva/index');
    }

    // GET /rest-reserva/meseroDeZona/{mesaId}  — JSON: mesero asignado a la zona de esa mesa hoy
    public function meseroDeZona(?string $mesaId = null): void
    {
        $meseroId = $this->model->meseroAsignadoPorMesa((int)$mesaId, $this->restauranteId());
        if (!$meseroId) {
            $this->json(['ok' => true, 'mesero' => null]);
            return;
        }
        $db   = Database::getInstance();
        $stmt = $db->prepare("SELECT id, nombre FROM usuarios WHERE id = ? LIMIT 1");
        $stmt->execute([$meseroId]);
        $this->json(['ok' => true, 'mesero' => $stmt->fetch(PDO::FETCH_ASSOC)]);
    }

    // POST /rest-reserva/asignar/{id}  — asigna mesa y mesero a una reservación del comensal
    public function asignar(?string $id = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-reserva/index');

        $reservaId     = (int)$id;
        $restauranteId = $this->restauranteId();
        $mesaId        = $this->post('mesa_id') ? (int)$this->post('mesa_id') : null;
        $meseroId      = $this->post('mesero_id') ? (int)$this->post('mesero_id') : null;

        // Verificar que la reservación pertenezca a este restaurante
        $reserva = $this->model->find($reservaId);
        if (!$reserva || (int)$reserva['restaurante_id'] !== $restauranteId) {
            $this->flash('error', 'Reservación no encontrada.');
            $this->redirect('rest-reserva/index');
            return;
        }

        // Validar conflicto si se asigna mesa
        if ($mesaId && !empty($reserva['fecha']) && !empty($reserva['hora'])) {
            if ($this->model->hayConflicto($mesaId, $reserva['fecha'], $reserva['hora'], $reservaId)) {
                $this->flash('error', 'Esa mesa ya tiene una reservación en ese horario (±2 horas).');
                $this->redirect('rest-reserva/index');
                return;
            }
        }

        // Auto-asignar mesero por zona si no se eligió uno manualmente
        if (!$meseroId && $mesaId) {
            $meseroId = $this->model->meseroAsignadoPorMesa($mesaId, $restauranteId);
        }

        $this->model->asignar($reservaId, $mesaId, $meseroId);

        // Confirmar automáticamente si estaba pendiente
        if ($reserva['estado'] === 'pendiente') {
            $this->model->cambiarEstado($reservaId, 'confirmada');
        }

        $this->flash('success', 'Mesa y mesero asignados. Reservación confirmada.');
        $this->redirect('rest-reserva/index');
    }

    public function cambiarEstado(?string $id = null): void
    {
        $estado = $this->post('estado') ?? $this->get('estado');
        $this->model->cambiarEstado((int)$id, $estado);
        $this->flash('success', 'Estado actualizado.');
        $this->redirect('rest-reserva/index');
    }

    public function eliminar(?string $id = null): void
    {
        $this->model->delete((int)$id);
        $this->flash('success', 'Reservación eliminada.');
        $this->redirect('rest-reserva/index');
    }
}
