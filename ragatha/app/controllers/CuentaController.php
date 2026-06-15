<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class CuentaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
    }

    public function perfil(?string $p = null): void
    {
        $model   = new UsuarioModel();
        $usuario = $model->find($this->usuarioId());
        $rol     = $this->rolActual();
        $flash   = $this->getFlash();
        $pageTitle  = 'Mi perfil';
        $activeMenu = 'cuenta';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/cuenta/perfil.php';
        $content = ob_get_clean();

        // Elegir layout según rol
        if ($rol === 'superadmin') {
            require ROOT_PATH . '/app/views/panel/layouts/main.php';
        } elseif ($rol === 'repartidor') {
            // Repartidor usa su propio layout
            echo $content;
        } else {
            require ROOT_PATH . '/app/views/empresa/layouts/main.php';
        }
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('cuenta/perfil');

        $model  = new UsuarioModel();
        $data   = [
            'nombre'           => trim($this->post('nombre', '')),
            'apellido_paterno' => trim($this->post('apellido_paterno', '')),
            'telefono'         => trim($this->post('telefono', '')),
        ];

        $model->update($this->usuarioId(), $data);
        $_SESSION['usuario'] = array_merge($_SESSION['usuario'], $data);

        $this->log('Actualizar perfil', 'cuenta');
        $this->flash('success', 'Perfil actualizado.');
        $this->redirect('cuenta/perfil');
    }

    public function subirAvatar(?string $p = null): void
    {
        if (!$this->isPost() || empty($_FILES['avatar']['name'])) {
            $this->redirect('cuenta/perfil');
        }

        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->flash('error', 'Error al subir el archivo.');
            $this->redirect('cuenta/perfil');
        }
        if ($file['size'] > 2 * 1024 * 1024) {
            $this->flash('error', 'La imagen no debe superar 2 MB.');
            $this->redirect('cuenta/perfil');
        }

        $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allow = ['jpg', 'jpeg', 'png', 'webp'];
        if (!in_array($ext, $allow, true)) {
            $this->flash('error', 'Solo se permiten imágenes JPG, PNG o WebP.');
            $this->redirect('cuenta/perfil');
        }

        $dir = UPLOAD_PATH . 'avatars/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre = 'avatar_' . $this->usuarioId() . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) {
            $this->flash('error', 'No se pudo guardar la imagen. Verifica permisos de la carpeta.');
            $this->redirect('cuenta/perfil');
        }

        $url   = UPLOAD_URL . 'avatars/' . $nombre;
        $model = new UsuarioModel();
        $model->update($this->usuarioId(), ['avatar' => $url]);
        $_SESSION['usuario']['avatar'] = $url;

        $this->log('Subir avatar', 'cuenta');
        $this->flash('success', 'Foto de perfil actualizada.');
        $this->redirect('cuenta/perfil');
    }

    public function quitarAvatar(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('cuenta/perfil');

        $model = new UsuarioModel();
        $model->update($this->usuarioId(), ['avatar' => null]);
        $_SESSION['usuario']['avatar'] = null;

        $this->log('Quitar avatar', 'cuenta');
        $this->flash('success', 'Foto de perfil eliminada.');
        $this->redirect('cuenta/perfil');
    }

    public function guardarDireccion(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('cuenta/perfil');

        $this->requireComprador();

        $data = [
            'direccion_entrega'  => trim($this->post('direccion_entrega', '')),
            'referencia_entrega' => trim($this->post('referencia_entrega', '')) ?: null,
            'lat_entrega'        => $this->post('lat_entrega') ?: null,
            'lng_entrega'        => $this->post('lng_entrega') ?: null,
        ];

        (new UsuarioModel())->update($this->usuarioId(), $data);
        $this->log('Guardar dirección de entrega', 'cuenta');
        $this->flash('success', 'Dirección de entrega guardada correctamente.');
        $this->redirect('cuenta/perfil');
    }

    public function cambiarPassword(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('cuenta/perfil');

        $actual  = $this->post('password_actual', '');
        $nuevo   = $this->post('password_nuevo', '');
        $confirm = $this->post('password_confirm', '');

        $model   = new UsuarioModel();
        $usuario = $model->find($this->usuarioId());

        if (!password_verify($actual, $usuario['password'])) {
            $this->flash('error', 'La contraseña actual es incorrecta.');
            $this->redirect('cuenta/perfil');
        }
        if ($nuevo !== $confirm || strlen($nuevo) < 8) {
            $this->flash('error', 'La nueva contraseña debe tener al menos 8 caracteres y coincidir.');
            $this->redirect('cuenta/perfil');
        }

        // Actualizar contraseña en BD
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE usuarios SET password = ? WHERE id = ?");
        $stmt->execute([password_hash($nuevo, PASSWORD_BCRYPT), $this->usuarioId()]);

        // Marcar primer login como completado
        $stmt = $db->prepare("UPDATE usuarios SET primer_login_completado = 1 WHERE id = ?");
        $stmt->execute([$this->usuarioId()]);
        $_SESSION['usuario']['primer_login_completado'] = 1;

        $this->log('Cambiar contraseña', 'cuenta');

        $this->flash('success', 'Contraseña actualizada correctamente.');
        $this->redirect('cuenta/perfil');
    }

    /**
     * Marca el primer login como completado (el usuario decidió "recordar después")
     */
    public function dismissFirstLogin(?string $p = null): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            exit;
        }

        $usuarioId = $_SESSION['usuario']['id'];

        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE usuarios SET primer_login_completado = 1 WHERE id = ?");
        $stmt->execute([$usuarioId]);

        // Actualizar sesión
        $_SESSION['usuario']['primer_login_completado'] = 1;

        echo json_encode(['success' => true]);
    }
}
