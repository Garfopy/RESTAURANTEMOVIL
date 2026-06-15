<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class PanelUsuarioController extends BaseController
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(?string $p = null): void
    {
        $filtros = [
            'buscar'   => $this->get('buscar', ''),
            'rol_slug' => $this->get('rol_slug', ''),
        ];
        $page       = max(1, (int)$this->get('page', 1));
        $resultado  = $this->usuarioModel->listadoConRol($filtros, $page);
        $usuarios   = $resultado['data'];
        $paginacion = $resultado;
        $roles      = $this->esSuperAdmin()
            ? $this->usuarioModel->rolesPermitidosPorSuperAdmin()
            : $this->usuarioModel->rolesPermitidosPorAdmin();
        $flash      = $this->getFlash();
        $pageTitle  = 'Usuarios';
        $activeMenu = 'usuarios';

        ob_start();
        require ROOT_PATH . '/app/views/panel/usuarios/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function nuevo(?string $p = null): void
    {
        $roles = $this->esSuperAdmin()
            ? $this->usuarioModel->rolesPermitidosPorSuperAdmin()
            : $this->usuarioModel->rolesPermitidosPorAdmin();
        $empresaModel = new EmpresaModel();
        $empresas     = $empresaModel->listadoSimple();
        $usuario      = null;
        $flash        = $this->getFlash();
        $pageTitle    = 'Nuevo Usuario';
        $activeMenu   = 'usuarios';

        ob_start();
        require ROOT_PATH . '/app/views/panel/usuarios/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('panel-usuario/nuevo');
        }

        $rolSlug   = $this->post('rol_slug');
        $empresaId = (int)$this->post('empresa_id') ?: null;
        $email     = trim($this->post('email'));

        // Solo superadmin puede crear superadmin/admin
        $rolesPermitidos = $this->esSuperAdmin()
            ? ['superadmin', 'admin', 'admin_empresa']
            : ['admin_empresa'];

        if (!in_array($rolSlug, $rolesPermitidos, true)) {
            $this->flash('error', 'No tienes permiso para crear este tipo de usuario.');
            $this->redirect('panel-usuario/nuevo');
        }

        // admin_empresa requiere empresa_id; superadmin/admin no
        if ($rolSlug === 'admin_empresa' && !$empresaId) {
            $this->flash('error', 'Debes seleccionar una empresa para Admin Empresa.');
            $this->redirect('panel-usuario/nuevo');
        }

        $rolRow = $this->usuarioModel->getRolPorSlug($rolSlug);
        if (!$rolRow) {
            $this->flash('error', 'Rol no válido.');
            $this->redirect('panel-usuario/nuevo');
        }

        // Validar email duplicado antes de intentar insertar
        if ($this->usuarioModel->existeEmail($email)) {
            $this->flash('error', 'El correo "' . $email . '" ya está registrado. No se puede crear el usuario.');
            $this->redirect('panel-usuario/nuevo');
        }

        $password = PasswordHelper::generar(14);

        $db = Database::getInstance();
        $db->beginTransaction();

        try {
            $tokenVerificacion = bin2hex(random_bytes(32));
            $tokenExpira = date('Y-m-d H:i:s', strtotime('+24 hours'));

            $id = $this->usuarioModel->crear([
                'nombre'              => trim($this->post('nombre')),
                'apellido_paterno'    => trim($this->post('apellido_paterno', '')),
                'apellido_materno'    => trim($this->post('apellido_materno', '')),
                'email'               => $email,
                'telefono'            => trim($this->post('telefono', '')),
                'rol_id'              => $rolRow['id'],
                'empresa_id'          => ($rolSlug === 'admin_empresa') ? $empresaId : null,
                'activo'              => 1,
                'email_verificado'    => 0,
                'token_verificacion'  => $tokenVerificacion,
                'token_expira'        => $tokenExpira,
                'created_by'          => $this->usuarioId(),
            ], $password);

            $emailService  = new EmailService();
            $usuarioCreado = $this->usuarioModel->find($id);
            $emailEnviado  = $emailService->enviarCredenciales($usuarioCreado, $password, null, $tokenVerificacion);

            $db->commit();
            $this->log('Crear usuario', 'usuarios', "ID: $id rol: $rolSlug email: $email");

            if ($emailEnviado) {
                $this->flash('success', "Usuario creado. Se envio el correo con credenciales a $email.");
            } else {
                $this->flash('success', "Usuario creado. No se pudo enviar el correo (revisa la configuracion SMTP). Contraseña temporal: $password");
            }
            $this->redirect('panel-usuario/index');

        } catch (\Exception $e) {
            $db->rollBack();
            error_log('[PanelUsuarioController] ' . $e->getMessage());
            $this->flash('error', 'No se pudo crear el usuario: ' . $e->getMessage());
            $this->redirect('panel-usuario/nuevo');
        }
    }

    public function editar(?string $p = null): void
    {
        $usuario = $this->usuarioModel->getConRol((int)$p);
        if (!$usuario) {
            $this->flash('error', 'Usuario no encontrado.');
            $this->redirect('panel-usuario/index');
        }

        $roles = $this->esSuperAdmin()
            ? $this->usuarioModel->rolesPermitidosPorSuperAdmin()
            : $this->usuarioModel->rolesPermitidosPorAdmin();
        $empresaModel = new EmpresaModel();
        $empresas     = $empresaModel->listadoSimple();
        $flash        = $this->getFlash();
        $pageTitle    = 'Editar Usuario';
        $activeMenu   = 'usuarios';

        ob_start();
        require ROOT_PATH . '/app/views/panel/usuarios/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function actualizar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('panel-usuario/index');
        }

        $id    = (int)$p;
        $data  = [
            'nombre'           => trim($this->post('nombre')),
            'apellido_paterno' => trim($this->post('apellido_paterno', '')),
            'apellido_materno' => trim($this->post('apellido_materno', '')),
            'telefono'         => trim($this->post('telefono', '')),
        ];

        $nuevoPass = trim($this->post('password', ''));
        if ($nuevoPass !== '') {
            if (strlen($nuevoPass) < 6) {
                $this->flash('error', 'La nueva contraseña debe tener al menos 6 caracteres.');
                $this->redirect("panel-usuario/editar/$id");
            }
            $confirmarPass = trim($this->post('password_confirm', ''));
            if ($nuevoPass !== $confirmarPass) {
                $this->flash('error', 'Las contraseñas no coinciden. Verifica e intenta de nuevo.');
                $this->redirect("panel-usuario/editar/$id");
            }
            $data['password'] = password_hash($nuevoPass, PASSWORD_BCRYPT);
        }

        $this->usuarioModel->update($id, $data);
        $this->log('Editar usuario', 'usuarios', "ID: $id");
        $this->flash('success', 'Usuario actualizado.');
        $this->redirect('panel-usuario/index');
    }

    public function toggle(?string $p = null): void
    {
        $id      = (int)$p;
        $usuario = $this->usuarioModel->getConRol($id);
        if (!$usuario) {
            $this->flash('error', 'Usuario no encontrado.');
            $this->redirect('panel-usuario/index');
        }

        // No se puede desactivar ninguna cuenta de superadmin
        if ($usuario['rol_slug'] === 'superadmin') {
            $this->flash('error', 'No es posible desactivar una cuenta de superadmin.');
            $this->redirect('panel-usuario/index');
        }

        $nuevoEstado = $usuario['activo'] ? 0 : 1;
        $this->usuarioModel->update($id, ['activo' => $nuevoEstado]);
        $this->log('Toggle usuario', 'usuarios', "ID: $id activo: $nuevoEstado");
        $accion = $nuevoEstado ? 'activado' : 'desactivado';
        $this->flash('success', "Usuario $accion.");
        $this->redirect('panel-usuario/index');
    }
}
