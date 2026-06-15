<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/services/PayPalSuscripcionService.php';

class EmpresaSuscripcionController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
    }

    // GET empresa-suscripcion/miPlan
    public function miPlan(?string $p = null): void
    {
        $plan = $this->getPlanActual();

        $flash      = $this->getFlash();
        $pageTitle  = 'Mi suscripción';
        $activeMenu = 'suscripcion';
        ob_start();
        require ROOT_PATH . '/app/views/empresa/suscripcion/mi_plan.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // GET empresa-suscripcion/planes
    public function planes(?string $p = null): void
    {
        $model  = new SuscripcionModel();
        $planes = $model->getPlanesActivos();
        $plan   = $this->getPlanActual();

        $flash      = $this->getFlash();
        $pageTitle  = 'Planes disponibles';
        $activeMenu = 'suscripcion';
        ob_start();
        require ROOT_PATH . '/app/views/empresa/suscripcion/planes.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // POST empresa-suscripcion/checkout
    public function checkout(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('empresa-suscripcion/planes');

        $planSlug = $this->post('plan_slug', '');
        $ciclo    = $this->post('ciclo', 'mensual');
        $model    = new SuscripcionModel();
        $planData = $model->getPlanPorSlug($planSlug);

        if (!$planData) {
            $this->flash('error', 'Plan no válido.');
            $this->redirect('empresa-suscripcion/planes');
        }

        if (empty($planData['paypal_plan_id']) && empty($planData['paypal_plan_id_anual'])
            && empty($planData['paypal_plan_id_live']) && empty($planData['paypal_plan_id_anual_live'])) {
            $this->flash('error', 'Este plan aún no tiene configurado el pago con PayPal. Contacta a soporte.');
            $this->redirect('empresa-suscripcion/planes');
        }

        try {
            $returnUrl = BASE_URL . 'empresa-suscripcion/retorno';
            $cancelUrl = BASE_URL . 'empresa-suscripcion/cancelado';

            // Seleccionar ID de PayPal según modo activo
            $cfg  = new ConfigModel();
            $modo = $cfg->get('paypal_mode', 'sandbox');

            if ($modo === 'live') {
                $paypalPlanId = ($ciclo === 'anual' && !empty($planData['paypal_plan_id_anual_live']))
                    ? $planData['paypal_plan_id_anual_live']
                    : ($planData['paypal_plan_id_live'] ?? '');
            } else {
                $paypalPlanId = ($ciclo === 'anual' && !empty($planData['paypal_plan_id_anual']))
                    ? $planData['paypal_plan_id_anual']
                    : ($planData['paypal_plan_id'] ?? '');
            }

            if (empty($paypalPlanId)) {
                $this->flash('error', 'El plan no está sincronizado con PayPal para el modo actual. Contacta a soporte.');
                $this->redirect('empresa-suscripcion/planes');
            }

            $paypal   = new PayPalSuscripcionService();
            $resultado = $paypal->crearSuscripcion($paypalPlanId, $returnUrl, $cancelUrl);

            // Guardar suscripcion en BD con estado pendiente
            $empresaId = $this->empresaId();
            $susModel  = new SuscripcionModel();
            $susActual = $susModel->getByEmpresa($empresaId);

            if ($susActual) {
                // Actualizar suscripcion existente
                $susModel->guardarPaypalId($susActual['id'], $resultado['id'], $resultado['status']);
                $susModel->cambiarEstado($susActual['id'], 'pendiente_paypal');
                $susModel->cambiarPlan($susActual['id'], $planData['id']);
            } else {
                $susId = $susModel->crear([
                    'empresa_id'             => $empresaId,
                    'plan_id'                => $planData['id'],
                    'estado'                 => 'pendiente_paypal',
                    'ciclo'                  => 'mensual',
                    'fecha_inicio'           => date('Y-m-d'),
                    'paypal_subscription_id' => $resultado['id'],
                    'paypal_status'          => $resultado['status'],
                    'created_by'             => $this->usuarioId(),
                ]);
            }

            // Guardar el ID de PayPal en sesión para verificar al retorno
            $_SESSION['paypal_pending_sub_id'] = $resultado['id'];

            header('Location: ' . $resultado['approve_link']);
            exit;

        } catch (\Throwable $e) {
            $this->flash('error', 'Error al conectar con PayPal: ' . $e->getMessage());
            $this->redirect('empresa-suscripcion/planes');
        }
    }

    // GET empresa-suscripcion/retorno?subscription_id=I-XXX
    public function retorno(?string $p = null): void
    {
        $paypalSubId = $this->get('subscription_id', '');

        if (!$paypalSubId) {
            $this->flash('error', 'No se recibió confirmación de PayPal.');
            $this->redirect('empresa-suscripcion/planes');
        }

        try {
            $paypal = new PayPalSuscripcionService();
            $data   = $paypal->obtenerSuscripcion($paypalSubId);
            $status = $data['status'] ?? '';

            $model = new SuscripcionModel();

            if (in_array($status, ['ACTIVE', 'APPROVED'], true)) {
                $model->activarDesdePaypal($paypalSubId);
                unset($_SESSION['paypal_pending_sub_id']);
                $plan = $model->getByPaypalId($paypalSubId);

                $flash      = $this->getFlash();
                $pageTitle  = '¡Suscripción activada!';
                $activeMenu = 'suscripcion';
                ob_start();
                require ROOT_PATH . '/app/views/empresa/suscripcion/confirmacion.php';
                $content = ob_get_clean();
                require ROOT_PATH . '/app/views/empresa/layouts/main.php';
            } else {
                $this->flash('error', 'PayPal aún no confirmó el pago. Intenta de nuevo en unos minutos.');
                $this->redirect('empresa-suscripcion/planes');
            }
        } catch (\Throwable $e) {
            $this->flash('error', 'No se pudo verificar el pago: ' . $e->getMessage());
            $this->redirect('empresa-suscripcion/planes');
        }
    }

    // GET empresa-suscripcion/cancelado
    public function cancelado(?string $p = null): void
    {
        $this->flash('error', 'Cancelaste el proceso de pago en PayPal. Puedes intentarlo cuando quieras.');
        $this->redirect('empresa-suscripcion/planes');
    }

    // GET empresa-suscripcion/suspendida  ← NO requiere suscripcion activa
    public function suspendida(?string $p = null): void
    {
        $plan = $this->getPlanActual();

        $flash      = $this->getFlash();
        $pageTitle  = 'Cuenta suspendida';
        $activeMenu = '';
        ob_start();
        require ROOT_PATH . '/app/views/empresa/suscripcion/suspendida.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }
}
