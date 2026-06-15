<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/services/PayPalSuscripcionService.php';

class SuscripcionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    // GET suscripcion/index
    public function index(?string $p = null): void
    {
        $model    = new SuscripcionModel();
        $filtros  = [
            'buscar'  => $this->get('buscar', ''),
            'plan_id' => $this->get('plan_id', ''),
            'estado'  => $this->get('estado', ''),
        ];
        $page      = max(1, (int)$this->get('page', 1));
        $resultado = $model->listado($filtros, $page);
        $planes    = $model->getPlanesActivos();

        $flash      = $this->getFlash();
        $pageTitle  = 'Suscripciones';
        $activeMenu = 'suscripciones';
        ob_start();
        require ROOT_PATH . '/app/views/panel/suscripciones/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    // GET suscripcion/configurar
    public function configurar(?string $p = null): void
    {
        $model  = new SuscripcionModel();
        $planes = $model->getPlanesActivos();

        $cfg        = new ConfigModel();
        $modoActivo = $cfg->get('paypal_mode', 'sandbox');

        $flash      = $this->getFlash();
        $pageTitle  = 'Configurar PayPal';
        $activeMenu = 'suscripciones';
        ob_start();
        require ROOT_PATH . '/app/views/panel/suscripciones/configurar.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    // POST suscripcion/sincronizarPlanes
    public function sincronizarPlanes(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('suscripcion/configurar');
        if (!$this->esSuperAdmin()) {
            $this->flash('error', 'Solo el superadmin puede sincronizar planes.');
            $this->redirect('suscripcion/configurar');
        }

        try {
            $model     = new SuscripcionModel();
            $planes    = $model->getPlanesActivos();
            $paypal    = new PayPalSuscripcionService();
            $resultado = $paypal->sincronizarPlanes($planes);
            $modo      = $paypal->getMode();

            foreach ($resultado as $planId => $ids) {
                if ($modo === 'live') {
                    if (!empty($ids['mensual'])) {
                        $model->guardarPaypalPlanIdLive($planId, $ids['mensual']);
                    }
                    if (!empty($ids['anual'])) {
                        $model->guardarPaypalPlanIdAnualLive($planId, $ids['anual']);
                    }
                } else {
                    if (!empty($ids['mensual'])) {
                        $model->guardarPaypalPlanId($planId, $ids['mensual']);
                    }
                    if (!empty($ids['anual'])) {
                        $model->guardarPaypalPlanIdAnual($planId, $ids['anual']);
                    }
                }
            }

            $this->log('Sincronizar planes PayPal', 'suscripcion');
            $this->flash('success', 'Planes sincronizados con PayPal correctamente (modo: ' . strtoupper($modo) . ').');
        } catch (\Throwable $e) {
            $this->flash('error', 'Error al sincronizar con PayPal: ' . $e->getMessage());
        }

        $this->redirect('suscripcion/configurar');
    }

    // POST suscripcion/cambiarPlan
    public function cambiarPlan(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('suscripcion/index');
        $susId  = (int)$this->post('suscripcion_id');
        $planId = (int)$this->post('plan_id');
        if ($susId && $planId) {
            $model = new SuscripcionModel();
            $model->cambiarPlan($susId, $planId);
            $this->log('Cambiar plan', 'suscripcion', "sus_id=$susId plan_id=$planId");
            $this->flash('success', 'Plan actualizado.');
        }
        $this->redirect('suscripcion/index');
    }

    // POST suscripcion/suspender
    public function suspender(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('suscripcion/index');
        $susId = (int)$this->post('suscripcion_id');
        if ($susId) {
            $model = new SuscripcionModel();
            $model->cambiarEstado($susId, 'suspendido');
            $this->log('Suspender suscripción', 'suscripcion', "sus_id=$susId");
            $this->flash('success', 'Suscripción suspendida.');
        }
        $this->redirect('suscripcion/index');
    }

    // POST suscripcion/activar
    public function activar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('suscripcion/index');
        $susId = (int)$this->post('suscripcion_id');
        if ($susId) {
            $model = new SuscripcionModel();
            $model->cambiarEstado($susId, 'activo');
            $this->log('Activar suscripción', 'suscripcion', "sus_id=$susId");
            $this->flash('success', 'Suscripción activada.');
        }
        $this->redirect('suscripcion/index');
    }

    // GET suscripcion/editarPlan/{id}
    public function editarPlan(?string $p = null): void
    {
        if (!$this->esSuperAdmin()) {
            $this->flash('error', 'Solo el superadmin puede editar planes.');
            $this->redirect('suscripcion/configurar');
        }
        $model = new SuscripcionModel();
        $plan  = $model->getPlanPorId((int)$p);
        if (!$plan) {
            $this->flash('error', 'Plan no encontrado.');
            $this->redirect('suscripcion/configurar');
        }

        $flash      = $this->getFlash();
        $pageTitle  = 'Editar plan: ' . $plan['nombre'];
        $activeMenu = 'suscripciones';
        ob_start();
        require ROOT_PATH . '/app/views/panel/suscripciones/editar_plan.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    // POST suscripcion/guardarPlan/{id}
    public function guardarPlan(?string $p = null): void
    {
        if (!$this->isPost() || !$this->esSuperAdmin()) {
            $this->redirect('suscripcion/configurar');
        }

        $planId = (int)$p;
        $model  = new SuscripcionModel();

        // Comparar precios antes de actualizar para detectar cambio
        $planAnterior    = $model->getPlanPorId($planId);
        $nuevoPrecioMes  = (float)str_replace(',', '', $this->post('precio_mensual', '0'));
        $nuevoPrecioAnual= (float)str_replace(',', '', $this->post('precio_anual', '0'));
        $cambioPrecios   = $planAnterior &&
            ((float)$planAnterior['precio_mensual'] !== $nuevoPrecioMes ||
             (float)$planAnterior['precio_anual']   !== $nuevoPrecioAnual);

        $maxUsuarios  = (int)$this->post('max_usuarios', 0);
        $maxProductos = (int)$this->post('max_productos', 0);
        $maxPedidos   = (int)$this->post('max_pedidos_mes', 0);
        $maxSucursales= (int)$this->post('max_sucursales', 0);

        $model->actualizarLimitesPlan($planId, [
            'nombre'         => trim($this->post('nombre')),
            'descripcion'    => trim($this->post('descripcion', '')),
            'precio_mensual' => $nuevoPrecioMes,
            'precio_anual'   => $nuevoPrecioAnual,
            'max_usuarios'   => $maxUsuarios,
            'max_productos'  => $maxProductos,
            'max_pedidos_mes'=> $maxPedidos,
            'max_sucursales' => $maxSucursales,
        ]);

        // Si cambiaron los precios, limpiar IDs de PayPal del modo activo
        if ($cambioPrecios) {
            $cfg  = new ConfigModel();
            $modo = $cfg->get('paypal_mode', 'sandbox');
            $model->limpiarPaypalPlanIds($planId, $modo);
            $this->log('Editar plan (precios cambiados)', 'suscripcion', "plan_id=$planId modo=$modo");
            $this->flash('success', 'Plan actualizado. Los precios cambiaron: sincroniza con PayPal para aplicar el nuevo precio en el checkout.');
        } else {
            $this->log('Editar plan', 'suscripcion', "plan_id=$planId");
            $this->flash('success', 'Plan actualizado correctamente.');
        }
        $this->redirect('suscripcion/configurar');
    }

    // POST suscripcion/webhook  ← sin autenticación de sesión
    public function webhook(?string $p = null): void
    {
        $rawBody = file_get_contents('php://input');
        $headers = PayPalSuscripcionService::getRequestHeaders();

        $configModel = new ConfigModel();
        $webhookId   = $configModel->get('paypal_webhook_id', '');

        $paypal = new PayPalSuscripcionService();
        if ($webhookId && !$paypal->verificarWebhook($headers, $rawBody, $webhookId)) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid signature']);
            exit;
        }

        $event   = json_decode($rawBody, true);
        $tipo    = $event['event_type'] ?? '';
        $model   = new SuscripcionModel();

        switch ($tipo) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $paypalSubId = $event['resource']['id'] ?? '';
                if ($paypalSubId) $model->activarDesdePaypal($paypalSubId);
                break;

            case 'PAYMENT.SALE.COMPLETED':
                $paypalSubId = $event['resource']['billing_agreement_id'] ?? '';
                if ($paypalSubId) {
                    $sus = $model->getByPaypalId($paypalSubId);
                    if ($sus) {
                        $dias    = ($sus['ciclo'] === 'anual') ? 365 : 30;
                        $base    = $sus['fecha_vencimiento'] ?? date('Y-m-d');
                        $nueva   = date('Y-m-d', strtotime($base . " +$dias days"));
                        $model->renovar($sus['id'], $nueva);
                    }
                }
                break;

            case 'BILLING.SUBSCRIPTION.SUSPENDED':
                $paypalSubId = $event['resource']['id'] ?? '';
                if ($paypalSubId) {
                    $sus = $model->getByPaypalId($paypalSubId);
                    if ($sus) $model->cambiarEstado($sus['id'], 'suspendido');
                }
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
                $paypalSubId = $event['resource']['id'] ?? '';
                if ($paypalSubId) {
                    $sus = $model->getByPaypalId($paypalSubId);
                    if ($sus) $model->cambiarEstado($sus['id'], 'cancelado');
                }
                break;
        }

        http_response_code(200);
        echo json_encode(['status' => 'ok']);
        exit;
    }
}
