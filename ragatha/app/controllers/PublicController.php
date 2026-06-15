<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class PublicController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        // Sin restricción — página pública
    }

    // GET / → landing pública
    public function landing(?string $p = null): void
    {
        // Si ya hay sesión activa, redirigir al portal correspondiente
        if (isset($_SESSION['usuario'])) {
            $this->redirectSegunRol($_SESSION['usuario']['rol_slug'] ?? '');
        }

        $config       = new ConfigModel();
        $appName      = $config->get('app_name',     APP_NAME);
        $appLogo      = $config->get('app_logo',      '');
        $colorPrimary = $config->get('color_primary', '#C8102E');
        $contactEmail = $config->get('smtp_user',     'contacto@carnihub.mx');

        $susModel = new SuscripcionModel();
        $planes   = $susModel->getPlanesActivos();

        require ROOT_PATH . '/app/views/public/landing_carnihub.php';
    }

    // GET /taqueria → landing audiencia taquerías
    public function taqueria(?string $p = null): void
    {
        $config       = new ConfigModel();
        $appName      = $config->get('app_name',     APP_NAME);
        $appLogo      = $config->get('app_logo',      '');
        $colorPrimary = $config->get('color_primary', '#C8102E');
        $contactEmail = $config->get('smtp_user',     'contacto@carnihub.mx');

        require ROOT_PATH . '/app/views/public/landing_taqueria.php';
    }

    // GET /restaurantes → landing audiencia restaurantes
    public function restaurantes(?string $p = null): void
    {
        $config       = new ConfigModel();
        $appName      = $config->get('app_name',     APP_NAME);
        $appLogo      = $config->get('app_logo',      '');
        $colorPrimary = $config->get('color_primary', '#C8102E');
        $contactEmail = $config->get('smtp_user',     'contacto@carnihub.mx');

        require ROOT_PATH . '/app/views/public/landing_restaurantes.php';
    }

    // GET /carnihub → hub CarniHub (selector de audiencia + planes)
    public function carnihub(?string $p = null): void
    {
        if (isset($_SESSION['usuario'])) {
            $this->redirectSegunRol($_SESSION['usuario']['rol_slug'] ?? '');
        }

        $config       = new ConfigModel();
        $appName      = $config->get('app_name',     APP_NAME);
        $appLogo      = $config->get('app_logo',      '');
        $colorPrimary = $config->get('color_primary', '#C8102E');
        $contactEmail = $config->get('smtp_user',     'contacto@carnihub.mx');

        $susModel = new SuscripcionModel();
        $planes   = $susModel->getPlanesActivos();

        require ROOT_PATH . '/app/views/public/landing_carnihub.php';
    }

    // GET planes/index
    public function index(?string $p = null): void
    {
        $model   = new SuscripcionModel();
        $planes  = $model->getPlanesActivos();
        $config  = new ConfigModel();
        $appName = $config->get('app_name', APP_NAME);
        $appLogo = $config->get('app_logo', '');
        $colorPrimary = $config->get('color_primary', '#C8102E');
        $whatsapp = $config->get('whatsapp_api_token', '') ? $config->get('whatsapp_phone_id', '') : '';
        $contactEmail = $config->get('smtp_user', 'contacto@carnihub.mx');

        require ROOT_PATH . '/app/views/public/planes.php';
    }

    // GET planes/registro?plan=pro&ciclo=mensual
    public function registro(?string $p = null): void
    {
        $planSlug = $this->get('plan', '');
        $ciclo    = $this->get('ciclo', 'mensual');

        // Validar plan
        $model = new SuscripcionModel();
        $plan  = $model->getPlanPorSlug($planSlug);

        if (!$plan || !$plan['activo']) {
            $this->flash('error', 'Plan no válido.');
            $this->redirect('planes');
        }

        // En modo prueba, permitir planes sin paypal_plan_id
        $modoPrueba = defined('PAYPAL_TEST_MODE') ? PAYPAL_TEST_MODE : true;

        if (empty($plan['paypal_plan_id']) && !$modoPrueba) {
            $this->flash('error', 'Este plan aún no está disponible para registro en línea. Contacta a soporte.');
            $this->redirect('planes');
        }

        // Cargar configuración
        $config       = new ConfigModel();
        $appName      = $config->get('app_name', APP_NAME);
        $appLogo      = $config->get('app_logo', '');
        $colorPrimary = $config->get('color_primary', '#C8102E');
        $flash        = $this->getFlash();

        require ROOT_PATH . '/app/views/public/registro.php';
    }

    // POST planes/checkout
    public function checkout(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('planes');
        }

        // Obtener datos del formulario
        $email        = trim($this->post('email', ''));
        $razonSocial  = trim($this->post('razon_social', ''));
        $rfc          = strtoupper(trim($this->post('rfc', '')));
        $telefono     = trim($this->post('telefono', ''));
        $planSlug     = $this->post('plan_slug', '');
        $ciclo        = $this->post('ciclo', 'mensual');

        // Validaciones
        $errores = [];

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Email inválido.';
        }

        if (empty($razonSocial)) {
            $errores[] = 'Razón social es requerida.';
        }

        if (empty($rfc) || !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
            $errores[] = 'RFC inválido (formato mexicano).';
        }

        // Validar plan
        $model = new SuscripcionModel();
        $plan  = $model->getPlanPorSlug($planSlug);

        // Modo prueba
        $modoPrueba = defined('PAYPAL_TEST_MODE') ? PAYPAL_TEST_MODE : true;

        if (!$plan || !$plan['activo']) {
            $errores[] = 'Plan no válido o no disponible.';
        } elseif (empty($plan['paypal_plan_id']) && empty($plan['paypal_plan_id_anual']) && !$modoPrueba) {
            $errores[] = 'Plan no disponible para registro en línea.';
        }

        // Verificar que el email no esté ya registrado
        if (!empty($email)) {
            $userModel = new UsuarioModel();
            if ($userModel->getByEmail($email)) {
                $errores[] = 'Este email ya está registrado. Intenta iniciar sesión.';
            }

            // Verificar registros pendientes activos
            $regModel = new RegistroPendienteModel();
            if ($regModel->getByEmailActivo($email)) {
                $errores[] = 'Ya existe un registro en proceso para este email. Revisa tu correo.';
            }
        }

        if (!empty($errores)) {
            $this->flash('error', implode(' ', $errores));
            $this->redirect('planes/registro?plan=' . urlencode($planSlug) . '&ciclo=' . urlencode($ciclo));
        }

        try {
            // Crear registro pendiente
            $regModel = new RegistroPendienteModel();
            $regId = $regModel->crear([
                'email'         => $email,
                'plan_id'       => $plan['id'],
                'ciclo'         => $ciclo,
                'datos_empresa' => json_encode([
                    'razon_social' => $razonSocial,
                    'rfc'          => $rfc,
                    'telefono'     => $telefono,
                ]),
                'estado' => 'pendiente_pago',
            ]);

            // Modo prueba o PayPal real
            $modoPrueba = defined('PAYPAL_TEST_MODE') ? PAYPAL_TEST_MODE : true;

            if ($modoPrueba) {
                // Simular PayPal - crear ID simulado
                $subscriptionId = 'TEST-SUB-' . strtoupper(substr(md5($email . time()), 0, 12));

                // Actualizar registro con ID simulado
                $regModel->actualizarPaypalStatus($regId, 'APPROVAL_PENDING', $subscriptionId);

                // Guardar en sesión
                $_SESSION['registro_pendiente_id'] = $regId;
                $_SESSION['test_mode'] = true;

                // Redirigir a página de simulación de PayPal
                $this->redirect('planes/simularPago?reg_id=' . $regId);
            } else {
                // Flujo real de PayPal
                $returnUrl = BASE_URL . 'planes/retorno';
                $cancelUrl = BASE_URL . 'planes/cancelado';

                $paypal    = new PayPalSuscripcionService();
                $paypalPlanId = ($ciclo === 'anual' && !empty($plan['paypal_plan_id_anual']))
                    ? $plan['paypal_plan_id_anual']
                    : $plan['paypal_plan_id'];
                error_log('[checkout] Iniciando crearSuscripcion — plan_id=' . $paypalPlanId . ' | ciclo=' . $ciclo . ' | email=' . $email . ' | returnUrl=' . $returnUrl);
                $resultado = $paypal->crearSuscripcion($paypalPlanId, $returnUrl, $cancelUrl);

                // Guardar ID de PayPal en el registro
                $regModel->actualizarPaypalStatus($regId, $resultado['status'], $resultado['id']);

                // Guardar en sesión para verificar en retorno
                $_SESSION['registro_pendiente_id'] = $regId;

                // Redirigir a PayPal
                header('Location: ' . $resultado['approve_link']);
                exit;
            }

        } catch (\Throwable $e) {
            error_log('[checkout] ERROR: ' . $e->getMessage() . ' | archivo: ' . $e->getFile() . ':' . $e->getLine());
            error_log('[checkout] Stack trace: ' . $e->getTraceAsString());
            $this->flash('error', 'Error al procesar el pago: ' . $e->getMessage());
            $this->redirect('planes/registro?plan=' . urlencode($planSlug) . '&ciclo=' . urlencode($ciclo));
        }
    }

    // GET planes/retorno?subscription_id=I-XXX
    public function retorno(?string $p = null): void
    {
        $paypalSubId = $this->get('subscription_id', '');

        if (!$paypalSubId) {
            $this->flash('error', 'No se recibió confirmación de PayPal.');
            $this->redirect('planes');
        }

        $modoPrueba = isset($_SESSION['test_mode']) || strpos($paypalSubId, 'TEST-SUB-') === 0;

        try {
            // Validar con PayPal API (aceptar cualquier estado post-aprobación)
            if (!$modoPrueba) {
                $paypal = new PayPalSuscripcionService();
                $data   = $paypal->obtenerSuscripcion($paypalSubId);
                $status = $data['status'] ?? '';

                // APPROVAL_PENDING es válido: el usuario acaba de aprobar en PayPal
                if (!in_array($status, ['ACTIVE', 'APPROVED', 'APPROVAL_PENDING'], true)) {
                    $this->flash('error', 'El pago no fue confirmado por PayPal (estado: ' . $status . ').');
                    $this->redirect('planes');
                }
            }

            // Buscar registro pendiente
            $regModel = new RegistroPendienteModel();
            $registro = $regModel->getByPaypalId($paypalSubId);

            if (!$registro) {
                $this->flash('error', 'No se encontró el registro asociado a este pago.');
                $this->redirect('planes');
            }

            // Evitar reprocesar si ya tiene token generado
            if (!empty($registro['token_verificacion']) && $registro['estado'] === 'pendiente_verificacion') {
                $token         = $registro['token_verificacion'];
                $passwordPlano = null; // ya fue enviado antes
            } else {
                require_once ROOT_PATH . '/app/helpers/PasswordHelper.php';
                $passwordPlano = PasswordHelper::generar(14);
                $passwordHash  = password_hash($passwordPlano, PASSWORD_BCRYPT);
                $token         = bin2hex(random_bytes(32));
                $regModel->actualizarTokenYPassword($registro['id'], $token, $passwordHash);
            }

            // Enviar email de verificación
            if ($passwordPlano !== null) {
                $datosEmpresa = json_decode($registro['datos_empresa'], true);
                require_once ROOT_PATH . '/app/services/EmailService.php';
                $emailService = new EmailService();
                $emailService->enviarCredenciales(
                    ['email' => $registro['email'], 'nombre' => $datosEmpresa['razon_social'] ?? 'Usuario'],
                    $passwordPlano,
                    null,
                    $token
                );
            }

            // Limpiar sesión
            unset($_SESSION['registro_pendiente_id']);
            unset($_SESSION['test_mode']);

            // Mostrar página de confirmación
            $config       = new ConfigModel();
            $appName      = $config->get('app_name', APP_NAME);
            $appLogo      = $config->get('app_logo', '');
            $colorPrimary = $config->get('color_primary', '#C8102E');
            $email        = $registro['email'];
            $verifyUrl    = BASE_URL . 'auth/verificar?token=' . $token;

            require ROOT_PATH . '/app/views/public/registro_confirmacion.php';

        } catch (\Throwable $e) {
            error_log('[retorno] ERROR: ' . $e->getMessage() . ' | archivo: ' . $e->getFile() . ':' . $e->getLine());
            error_log('[retorno] Stack trace: ' . $e->getTraceAsString());
            $this->flash('error', 'No se pudo verificar el pago: ' . $e->getMessage());
            $this->redirect('planes');
        }
    }

    // GET planes/cancelado
    public function cancelado(?string $p = null): void
    {
        // Eliminar registro pendiente si existe en sesión
        if (isset($_SESSION['registro_pendiente_id'])) {
            $regModel = new RegistroPendienteModel();
            $regModel->cancelar((int)$_SESSION['registro_pendiente_id']);
            unset($_SESSION['registro_pendiente_id']);
        }

        $this->flash('error', 'Cancelaste el proceso de registro. Puedes intentarlo cuando quieras.');
        $this->redirect('planes');
    }

    // GET planes/simularPago?reg_id=X (MODO PRUEBA)
    public function simularPago(?string $p = null): void
    {
        $regId = (int)$this->get('reg_id', 0);

        if (!$regId || !isset($_SESSION['test_mode'])) {
            $this->flash('error', 'Sesión inválida.');
            $this->redirect('planes');
        }

        $regModel = new RegistroPendienteModel();
        $registro = $regModel->find($regId);

        if (!$registro) {
            $this->flash('error', 'Registro no encontrado.');
            $this->redirect('planes');
        }

        // Obtener plan
        $model = new SuscripcionModel();
        $plan  = $model->find($registro['plan_id']);

        $datosEmpresa = json_decode($registro['datos_empresa'], true);

        // Cargar configuración
        $config       = new ConfigModel();
        $appName      = $config->get('app_name', APP_NAME);
        $appLogo      = $config->get('app_logo', '');
        $colorPrimary = $config->get('color_primary', '#C8102E');

        require ROOT_PATH . '/app/views/public/simular_paypal.php';
    }

    // POST planes/aprobarPagoTest
    public function aprobarPagoTest(?string $p = null): void
    {
        if (!$this->isPost() || !isset($_SESSION['test_mode'])) {
            $this->redirect('planes');
        }

        $regId  = (int)$this->post('reg_id', 0);
        $accion = $this->post('accion', '');

        if ($accion === 'cancelar') {
            $this->cancelado();
            return;
        }

        // Simular aprobación
        $regModel = new RegistroPendienteModel();
        $registro = $regModel->find($regId);

        if (!$registro) {
            $this->flash('error', 'Registro no encontrado.');
            $this->redirect('planes');
        }

        // Actualizar estado como aprobado
        $db = Database::getInstance();
        $db->prepare(
            "UPDATE registros_pendientes
             SET paypal_status = 'ACTIVE',
                 estado = 'pendiente_verificacion'
             WHERE id = ?"
        )->execute([$regId]);

        // Redirigir al retorno con subscription_id simulado
        $this->redirect('planes/retorno?subscription_id=' . urlencode($registro['paypal_subscription_id']));
    }
}
