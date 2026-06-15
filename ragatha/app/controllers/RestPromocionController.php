<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestPromocionController extends BaseController
{
    private RestPromocionModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRestaurante();
        $this->model = new RestPromocionModel();
    }

    // ── Listado ──────────────────────────────────────────────────

    public function index(?string $p = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        // La vista ahora carga datos vía JS/ApiClient.
        // Solo renderizamos la vista sin datos del servidor.
        $pageTitle  = 'Promociones';
        $activeMenu = 'rest_promociones';
        $this->render('restaurante/promociones/index', compact('pageTitle', 'activeMenu'));
    }

    // ── Formulario crear / editar ────────────────────────────────

    public function crear(?string $id = null): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $promocion     = null;
        $editando      = false;
        $asignados     = [];
        $comensales    = [];
        $promoId       = 0;

        if ($id !== null && is_numeric($id)) {
            $editando  = true;
            $promoId   = (int)$id;
        }

        $pageTitle  = $editando ? 'Editar Promoción' : 'Nueva Promoción';
        $activeMenu = 'rest_promociones';
        $this->render('restaurante/promociones/form', compact(
            'promocion', 'editando', 'comensales', 'asignados', 'promoId', 'pageTitle', 'activeMenu'
        ));
    }

    public function editar(?string $id = null): void
    {
        $this->crear($id);
    }

    // ── Guardar (POST) ───────────────────────────────────────────

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('rest-promocion/index');
        }

        $restauranteId = $this->restauranteId();
        $editId        = $this->post('id') ? (int)$this->post('id') : null;

        $data = [
            'titulo'          => trim((string)$this->post('titulo', '')),
            'descripcion'     => trim((string)$this->post('descripcion', '')),
            'tipo'            => $this->post('tipo', 'porcentaje'),
            'valor_descuento' => (float)$this->post('valor_descuento', 0),
            'fecha_inicio'    => $this->post('fecha_inicio', ''),
            'fecha_fin'       => $this->post('fecha_fin', ''),
            'activo'          => $this->post('activo') ? 1 : 0,
        ];

        $errores = [];
        if ($data['titulo'] === '') {
            $errores[] = 'El titulo es obligatorio.';
        }
        if (!in_array($data['tipo'], ['porcentaje', 'monto_fijo', 'envio_gratis'], true)) {
            $errores[] = 'Tipo de descuento invalido.';
        }
        if ($data['tipo'] !== 'envio_gratis' && $data['valor_descuento'] <= 0) {
            $errores[] = 'El valor del descuento debe ser mayor a 0.';
        }
        if ($data['fecha_inicio'] === '' || $data['fecha_fin'] === '') {
            $errores[] = 'Las fechas de inicio y fin son obligatorias.';
        } elseif ($data['fecha_fin'] < $data['fecha_inicio']) {
            $errores[] = 'La fecha de fin no puede ser anterior a la de inicio.';
        }
        if ($data['tipo'] === 'porcentaje' && $data['valor_descuento'] > 100) {
            $errores[] = 'El porcentaje no puede ser mayor a 100%.';
        }

        if (!empty($errores)) {
            $this->flash('error', implode(' ', $errores));
            $redirect = $editId ? 'rest-promocion/editar/' . $editId : 'rest-promocion/crear';
            $this->redirect($redirect);
            return;
        }

        try {
            if ($editId) {
                $existente = $this->model->find($editId, $restauranteId);
                if (!$existente) {
                    $this->flash('error', 'Promoción no encontrada.');
                    $this->redirect('rest-promocion/index');
                    return;
                }
                $this->model->actualizar($editId, $data);
                $promocionId = $editId;
                $mensaje = 'Promoción actualizada correctamente.';
            } else {
                $data['restaurante_id'] = $restauranteId;
                $promocionId = $this->model->crear($data);
                $mensaje = 'Promoción creada correctamente.';
            }

            $comensalesPost = $this->post('comensales', []);
            if (!is_array($comensalesPost)) {
                $comensalesPost = [];
            }
            $comensalIds = array_map('intval', $comensalesPost);
            $this->model->asignarComensales($promocionId, $comensalIds);

            // Sincronizar con App Movil
            $this->syncPromocionesConApp($restauranteId);

            $this->flash('success', $mensaje);
        } catch (\Throwable $e) {
            $this->flash('error', 'Error al guardar: ' . $e->getMessage());
        }

        $this->redirect('rest-promocion/index');
    }

    // ── Eliminar (POST) ──────────────────────────────────────────

    public function eliminar(?string $id = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('rest-promocion/index');
        }

        $restauranteId = $this->restauranteId();
        $promocionId   = (int)$id;

        if (!$promocionId) {
            $this->flash('error', 'ID invalido.');
            $this->redirect('rest-promocion/index');
            return;
        }

        $existente = $this->model->find($promocionId, $restauranteId);
        if (!$existente) {
            $this->flash('error', 'Promoción no encontrada.');
            $this->redirect('rest-promocion/index');
            return;
        }

        $this->model->eliminar($promocionId, $restauranteId);
        $this->syncPromocionesConApp($restauranteId);

        $this->flash('success', 'Promoción eliminada correctamente.');
        $this->redirect('rest-promocion/index');
    }

    // ── Sincronizacion con App Movil ─────────────────────────────

    private function syncPromocionesConApp(int $restauranteId): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT clave, valor FROM global_settings WHERE clave IN ('amare_api_url','amare_api_token') AND grupo = 'pagos'"
        );
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['clave']] = $row['valor'] ?? '';
        }

        $apiUrl = rtrim($settings['amare_api_url'] ?? '', '/');
        $token  = $settings['amare_api_token'] ?? '';

        if (empty($apiUrl) || empty($token)) {
            return;
        }

        $promociones = $this->model->listar($restauranteId, 1);
        $payload = [];
        foreach ($promociones as $promo) {
            $comensales = $this->model->getComensalesAsignados((int)$promo['id']);
            $payload[] = [
                'id'              => (int)$promo['id'],
                'titulo'          => $promo['titulo'],
                'descripcion'     => $promo['descripcion'],
                'tipo'            => $promo['tipo'],
                'valor_descuento' => (float)$promo['valor_descuento'],
                'fecha_inicio'    => $promo['fecha_inicio'],
                'fecha_fin'       => $promo['fecha_fin'],
                'activo'          => (bool)$promo['activo'],
                'comensales'      => $comensales,
            ];
        }

        $url = $apiUrl . '/branches/' . $restauranteId . '/promociones';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => 'PUT',
            CURLOPT_POSTFIELDS     => json_encode(['promociones' => $payload]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log('[syncPromocionesApp] cURL error: ' . $error);
            return;
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return;
        }

        error_log('[syncPromocionesApp] HTTP ' . $httpCode . ' — ' . $response);

        if ($httpCode === 401) {
            try {
                $db->prepare(
                    "INSERT INTO global_settings (clave, valor, grupo) VALUES ('amare_token_expirado', '1', 'pagos')
                     ON DUPLICATE KEY UPDATE valor = '1'"
                )->execute();
            } catch (\Throwable $e) {
                error_log('[syncPromocionesApp] No se pudo marcar token expirado: ' . $e->getMessage());
            }
        }
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function calcularEstado(array $promo, string $hoy): string
    {
        if (!$promo['activo']) {
            return 'inactiva';
        }
        if ($promo['fecha_fin'] < $hoy) {
            return 'expirada';
        }
        if ($promo['fecha_inicio'] > $hoy) {
            return 'programada';
        }
        return 'activa';
    }
}
