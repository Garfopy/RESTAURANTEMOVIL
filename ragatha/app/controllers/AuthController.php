<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class AuthController extends BaseController
{
    private const MAX_INTENTOS  = 5;
    private const BLOQUEO_MINS  = 2;

    public function index(?string $p = null): void
    {
        $this->redirect('auth/login');
    }

    public function login(?string $p = null): void
    {
        if (isset($_SESSION['usuario'])) {
            $this->redirectSegunRol($_SESSION['usuario']['rol_slug'] ?? '');
        }
        $pageTitle = 'Iniciar Sesión';
        $flash     = $this->getFlash();
        $this->render('auth/login', compact('pageTitle', 'flash'));
    }

    public function doLogin(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('auth/login');
        }

        $email    = trim($this->post('email', ''));
        $password = $this->post('password', '');
        $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (!$email || !$password) {
            $this->flash('error', 'Por favor ingresa tu correo y contraseña.');
            $this->redirect('auth/login');
        }

        // Brute-force check: contar intentos fallidos en últimos N minutos desde esta IP
        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT COUNT(*) FROM login_intentos
             WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$ip, self::BLOQUEO_MINS]);
        $intentos = (int)$stmt->fetchColumn();

        if ($intentos >= self::MAX_INTENTOS) {
            $this->flash('error', 'Demasiados intentos fallidos. Espera ' . self::BLOQUEO_MINS . ' minutos e intenta de nuevo.');
            $this->redirect('auth/login');
        }

        $usuarioModel = new UsuarioModel();
        $usuario      = $usuarioModel->getByEmail($email);

        // Cuenta desactivada: mensaje específico, sin contar intento fallido
        if ($usuario && !(int)$usuario['activo']) {
            $this->flash('error', 'Tu cuenta ha sido desactivada. Ponte en contacto con el administrador para reactivarla.');
            $this->redirect('auth/login');
        }

        if (!$usuario || !password_verify($password, $usuario['password'])) {
            // Registrar intento fallido
            $stmt = $db->prepare("INSERT INTO login_intentos (ip, email) VALUES (?, ?)");
            $stmt->execute([$ip, $email]);

            $restantes = self::MAX_INTENTOS - $intentos - 1;
            $msg = 'Credenciales incorrectas.';
            if ($restantes > 0) {
                $msg .= " Te quedan $restantes intentos antes del bloqueo temporal.";
            }
            $this->flash('error', $msg);
            $this->redirect('auth/login');
        }

        // Verificar que el email esté verificado
        if (empty($usuario['email_verificado'])) {
            $this->flash('error', 'Debes verificar tu email antes de iniciar sesión. Revisa tu bandeja de entrada (y spam) y haz clic en el link de verificación.');
            $this->redirect('auth/login');
        }

        // Cuenta inactiva
        if (empty($usuario['activo'])) {
            $this->flash('error', 'Tu cuenta está deshabilitada. Por favor comunícate con un administrador para volver a activarla.');
            $this->redirect('auth/login');
        }

        // Login exitoso — limpiar intentos fallidos de esta IP
        $stmt = $db->prepare("DELETE FROM login_intentos WHERE ip = ?");
        $stmt->execute([$ip]);

        $_SESSION['usuario'] = $usuario;

        if (!empty($usuario['empresa_id'])) {
            $empresaModel = new EmpresaModel();
            $_SESSION['empresa'] = $empresaModel->find($usuario['empresa_id']);
        }

        $this->log('Login exitoso', 'auth');

        // Verificar si es primer login después de verificación
        if ($usuario['email_verificado'] && empty($usuario['primer_login_completado'])) {
            $this->flash('first_login', '¡Bienvenido! Te recomendamos cambiar tu contraseña para mayor seguridad.');
        }

        $this->redirectSegunRol($usuario['rol_slug']);
    }

    public function verificar(?string $p = null): void
    {
        $token = trim($_GET['token'] ?? '');

        error_log("[AuthController::verificar] Iniciando verificación. Token presente: " . ($token ? 'SÍ' : 'NO'));

        if (!$token) {
            error_log("[AuthController::verificar] ERROR: Token vacío o no proporcionado");
            $this->flash('error', 'Token de verificación inválido.');
            $this->redirect('auth/login');
        }

        $db = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id, email, nombre, apellido_paterno, token_expira, email_verificado
             FROM usuarios
             WHERE token_verificacion = ?
             LIMIT 1"
        );
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        // Si encontramos un usuario existente, procesar verificación normal
        if ($usuario) {
            error_log("[AuthController::verificar] Usuario encontrado: {$usuario['email']} (ID: {$usuario['id']})");

            // Verificar si ya está verificado
            if ($usuario['email_verificado']) {
                error_log("[AuthController::verificar] Email ya verificado previamente para: {$usuario['email']}");
                $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellido_paterno'];
                $this->flash('success', "Tu email ya está verificado, $nombreCompleto. Puedes iniciar sesión.");
                $this->redirect('auth/login');
            }

            // Verificar si el token expiró
            $expira = strtotime($usuario['token_expira']);
            if ($expira < time()) {
                error_log("[AuthController::verificar] ERROR: Token expirado para: {$usuario['email']}");
                $this->flash('error', 'El link de verificación ha expirado. Contacta al administrador para reenviar el email.');
                $this->redirect('auth/login');
            }

            // Marcar email como verificado
            $stmt = $db->prepare(
                "UPDATE usuarios
                 SET email_verificado = 1,
                     token_verificacion = NULL,
                     token_expira = NULL
                 WHERE id = ?"
            );
            $stmt->execute([$usuario['id']]);

            error_log("[AuthController::verificar] Email verificado exitosamente para: {$usuario['email']}");
            $this->log('Email verificado', 'auth', "Usuario ID: {$usuario['id']}");

            $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellido_paterno'];
            $this->flash('success', "¡Email verificado correctamente! Hola $nombreCompleto, ya puedes iniciar sesión.");
            $this->redirect('auth/login');
        }

        // Si no es un usuario existente, el token no corresponde a nada válido
        // (el flujo de registro pendiente SaaS no aplica en despliegue standalone).
        error_log("[AuthController::verificar] Token no corresponde a ningún usuario");
        $this->flash('error', 'El link de verificación no es válido o ya fue usado.');
        $this->redirect('auth/login');
    }

    public function logout(?string $p = null): void
    {
        $this->log('Logout', 'auth');
        session_destroy();
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }

    // Cierra solo la cookie de sesión del rol indicado (chef|mesero|portero|staff|comensal)
    // manteniendo intactas las otras sesiones (admin u otros staff).
    public function logoutStaff(?string $rol = null): void
    {
        $rol = strtolower($rol ?? '');
        if (!in_array($rol, ['chef', 'mesero', 'portero', 'staff', 'comensal'], true)) {
            header('Location: ' . BASE_URL); exit;
        }
        // Capturar slug del restaurante ANTES de destruir la sesión para
        // poder redirigir al login del restaurante correcto.
        $restSlug = null;
        $restId = $_SESSION['restaurante_activo_id'] ?? ($_SESSION['usuario']['restaurante_id'] ?? null);
        if ($restId) {
            try {
                $rest = (new RestauranteModel())->find((int)$restId);
                $restSlug = $rest['slug'] ?? null;
            } catch (\Throwable $e) { /* fallback abajo */ }
        }

        // La cookie correcta ya fue abierta por index.php (auth/logoutStaff/{rol}).
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();

        $target = $restSlug ? ('acceso/' . $restSlug . '/staff') : '';
        header('Location: ' . BASE_URL . $target);
        exit;
    }

    public function forgot(?string $p = null): void
    {
        if (isset($_SESSION['usuario'])) {
            $this->redirectSegunRol($_SESSION['usuario']['rol_slug'] ?? '');
        }
        $flash = $this->getFlash();
        $this->render('auth/forgot', compact('flash'));
    }

    public function sendReset(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('auth/forgot');
        }

        $email = trim($this->post('email', ''));

        // Mensaje genérico siempre — no revelar si el email existe
        $this->flash('success', 'Si existe una cuenta con ese correo, recibirás un link para restablecer tu contraseña en los próximos minutos.');

        if (!$email) {
            $this->redirect('auth/forgot');
        }

        $usuarioModel = new UsuarioModel();
        $usuario      = $usuarioModel->getByEmail($email);

        if ($usuario && !empty($usuario['email_verificado'])) {
            $token  = bin2hex(random_bytes(32));
            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $db = Database::getInstance();
            $stmt = $db->prepare(
                'UPDATE usuarios SET token_verificacion = ?, token_expira = ? WHERE id = ?'
            );
            $stmt->execute([$token, $expira, $usuario['id']]);

            require_once ROOT_PATH . '/app/services/EmailService.php';
            $emailSvc = new EmailService();
            $enviado  = $emailSvc->enviarResetPassword($usuario, $token);
            error_log("[AuthController::sendReset] Email reset " . ($enviado ? 'enviado' : 'FALLÓ') . " para: $email");
        }

        $this->redirect('auth/forgot');
    }

    public function reset(?string $p = null): void
    {
        $token = trim($_GET['token'] ?? '');

        if (!$token) {
            $this->flash('error', 'Link de recuperación inválido.');
            $this->redirect('auth/login');
        }

        $usuarioModel = new UsuarioModel();
        $usuario      = $usuarioModel->getByResetToken($token);

        if (!$usuario) {
            $this->flash('error', 'El link de recuperación no es válido o ya fue utilizado.');
            $this->redirect('auth/login');
        }

        if (strtotime($usuario['token_expira']) < time()) {
            $this->flash('error', 'El link de recuperación ha expirado. Solicita uno nuevo.');
            $this->redirect('auth/forgot');
        }

        $flash = $this->getFlash();
        $this->render('auth/reset', compact('flash', 'token'));
    }

    public function doReset(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('auth/login');
        }

        $token           = trim($this->post('token', ''));
        $password        = $this->post('password', '');
        $passwordConfirm = $this->post('password_confirm', '');

        if (!$token) {
            $this->flash('error', 'Token inválido.');
            $this->redirect('auth/login');
        }

        if ($password !== $passwordConfirm) {
            $this->flash('error', 'Las contraseñas no coinciden.');
            $this->redirect('auth/reset?token=' . urlencode($token));
        }

        require_once ROOT_PATH . '/app/helpers/PasswordHelper.php';
        if (!PasswordHelper::validar($password)) {
            $this->flash('error', 'La contraseña debe tener mínimo 8 caracteres, una mayúscula, una minúscula y un número.');
            $this->redirect('auth/reset?token=' . urlencode($token));
        }

        $usuarioModel = new UsuarioModel();
        $usuario      = $usuarioModel->getByResetToken($token);

        if (!$usuario) {
            $this->flash('error', 'El link de recuperación no es válido o ya fue utilizado.');
            $this->redirect('auth/login');
        }

        if (strtotime($usuario['token_expira']) < time()) {
            $this->flash('error', 'El link de recuperación ha expirado. Solicita uno nuevo.');
            $this->redirect('auth/forgot');
        }

        $usuarioModel->actualizarPassword($usuario['id'], $password);
        $this->log('Contraseña restablecida', 'auth', "Usuario ID: {$usuario['id']}");

        $this->flash('success', '¡Contraseña actualizada correctamente! Ya puedes iniciar sesión.');
        $this->redirect('auth/login');
    }
}
