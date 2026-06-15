<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class StaffAccesoController extends BaseController
{
    private RestauranteModel $restModel;

    public function __construct()
    {
        parent::__construct();
        $this->restModel = new RestauranteModel();
    }

    // GET /acceso/{slug}  — formulario comensal (nombre + email)
    public function index(?string $slug = null): void
    {
        $restaurante = $slug ? $this->restModel->getBySlug($slug) : null;
        $flash       = $this->getFlash();
        $pageTitle   = ($restaurante['nombre'] ?? 'CarniHub') . ' — Identificación';
        $returnParam = trim($this->get('return', ''));
        $this->render('staff/login', compact('restaurante', 'flash', 'pageTitle', 'slug', 'returnParam'));
    }

    // GET /acceso/{slug}/staff  — formulario staff (email + contraseña)
    public function staff(?string $slug = null): void
    {
        $restaurante = $slug ? $this->restModel->getBySlug($slug) : null;
        $flash       = $this->getFlash();
        $pageTitle   = 'Acceso Staff — ' . ($restaurante['nombre'] ?? 'CarniHub');
        $yaLogueado  = null;
        $this->render('staff/login_staff', compact('restaurante', 'flash', 'pageTitle', 'slug', 'yaLogueado'));
    }

    // POST /acceso/{slug}
    // POST /acceso/{slug}/entrarComensal
    public function entrarComensal(?string $slug = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('acceso/' . $slug);
        }
        $restaurante = $slug ? $this->restModel->getBySlug($slug) : null;
        if (!$restaurante) { $this->redirect('acceso/' . $slug); }

        $nombre = trim($this->post('nombre', ''));
        $email  = mb_strtolower(trim($this->post('email', '')));
        if (!$nombre || !$email) {
            $this->flash('error', 'Ingresa tu nombre y correo para continuar.');
            $this->redirect('acceso/' . $slug);
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Por favor ingresa un correo válido.');
            $this->redirect('acceso/' . $slug);
        }

        $clienteModel = new RestClienteModel();
        $comensalId   = $clienteModel->buscarOCrear((int)$restaurante['id'], $nombre, null, $email);

        setcookie(
            'comensal_' . $restaurante['id'],
            json_encode(['id' => $comensalId, 'nombre' => $nombre, 'email' => $email]),
            time() + 30 * 24 * 3600, '/'
        );

        // Redirigir a la URL de origen (preservada como hidden field para no perderla en POST)
        $return = trim($this->post('return_url', ''));
        if ($return && str_starts_with($return, 'menu/')) {
            $this->redirect($return);
            return;
        }

        $this->redirect('menu/' . $slug);
    }

    public function login(?string $slug = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('acceso/' . $slug);
        }

        $email    = trim($this->post('email', ''));
        $password = $this->post('password', '');
        $restaurante = $slug ? $this->restModel->getBySlug($slug) : null;

        if (!$email || !$password) {
            $this->flash('error', 'Completa todos los campos.');
            $this->redirect('acceso/' . $slug);
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             JOIN rest_staff rs ON rs.usuario_id = u.id
             WHERE u.email = ? AND u.activo = 1
               AND r.slug IN ('mesero','chef','portero')
               AND rs.activo = 1
               AND rs.restaurante_id = ?"
        );
        $stmt->execute([$email, (int)($restaurante['id'] ?? 0)]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$user || !password_verify($password, $user['password'])) {
            $this->flash('error', 'Credenciales incorrectas o no tienes acceso a este restaurante.');
            $this->redirect('acceso/' . $slug);
        }

        // Cambiar a la cookie de sesión propia del rol para que admin y otros
        // staff conserven su sesión en el mismo navegador.
        // IMPORTANTE: forzar un session_id NUEVO. PHP reusa el id actual al
        // hacer session_name + session_start, lo que provocaba que todas las
        // cookies por rol apuntaran al MISMO archivo de sesión (cada login
        // pisaba al anterior).
        $sessionData = [
            'id'           => $user['id'],
            'nombre'       => $user['nombre'],
            'email'        => $user['email'],
            'rol_id'       => $user['rol_id'],
            'rol_slug'     => $user['rol_slug'],
            'empresa_id'   => $user['empresa_id'] ?? null,
            'restaurante_id' => $restaurante['id'] ?? null,
        ];
        $activeRest = $restaurante['id'] ?? null;

        session_write_close();
        $cookieName = SESSION_NAME . '_' . $user['rol_slug'];
        session_name($cookieName);
        // SIEMPRE id fresco. Esto:
        //  1) evita que PHP reuse el id de la cookie _login (lo que provocaba
        //     que todas las cookies por rol apuntaran al mismo archivo).
        //  2) limpia "cookies corruptas" en navegadores que ya hicieron login
        //     con el bug anterior.
        session_id(session_create_id());
        session_start();
        $_SESSION                          = [];
        $_SESSION['usuario']               = $sessionData;
        $_SESSION['restaurante_activo_id'] = $activeRest;

        $this->redirectSegunRol($user['rol_slug']);
    }
}
