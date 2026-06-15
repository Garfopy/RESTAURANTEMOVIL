<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class ConfigController extends BaseController
{
    private ConfigModel $cfg;

    public function __construct()
    {
        parent::__construct();
        $this->requireSuperAdmin();
        $this->cfg = new ConfigModel();
    }

    public function index(?string $p = null): void
    {
        $this->redirect('config/general');
    }

    // ── General + Estilos + Contacto ──────────────────────────────────────────
    public function general(?string $p = null): void
    {
        if ($this->isPost()) {
            if (!empty($_FILES['logo']['name'])) {
                $url = $this->subirLogo($_FILES['logo']);
                if ($url) {
                    $this->cfg->set('app_logo', $url);
                } else {
                    $this->flash('error', 'El logo debe ser JPG, PNG, WebP o SVG y no superar 2 MB.');
                    $this->redirect('config/general');
                }
            }

            $this->cfg->set('app_name',          trim($this->post('app_name', 'CarniHub')));
            $this->cfg->set('color_primary',     trim($this->post('color_primary', '#C8102E')));
            $this->cfg->set('color_secondary',   trim($this->post('color_secondary', '#1f2937')));
            $this->cfg->set('telefono_contacto', trim($this->post('telefono_contacto', '')));
            $this->cfg->set('horarios_atencion', trim($this->post('horarios_atencion', '')));

            $this->log('Guardar config general', 'configuracion');
            $this->flash('success', 'Configuración general guardada.');
            $this->redirect('config/general');
        }

        $settings   = array_merge(
            $this->asClave($this->cfg->getGrupo('general')),
            $this->asClave($this->cfg->getGrupo('estilos')),
            $this->asClave($this->cfg->getGrupo('contacto'))
        );
        $flash      = $this->getFlash();
        $pageTitle  = 'Configuración — General';
        $activeMenu = 'config';
        $seccion    = 'general';

        ob_start();
        require ROOT_PATH . '/app/views/panel/config/general.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    // ── APIs y servicios externos ─────────────────────────────────────────────
    public function apis(?string $p = null): void
    {
        if ($this->isPost()) {
            $campos = [
                'google_maps_key',
                'qr_api_url', 'qr_api_key',
                'whatsapp_api_token', 'whatsapp_phone_id', 'whatsapp_numero_contacto',
                'gemini_api_key',
                'traccar_url', 'traccar_user', 'traccar_pass',
                'facturalo_token', 'facturalo_rfc',
                'facturalo_apikey', 'facturalo_ambiente', 'facturalo_plantilla',
                'facturalo_nombre', 'facturalo_regimen', 'facturalo_cp',
                'facturalo_key_pem', 'facturalo_cer_pem', 'facturalo_csd_pass',
                'paypal_client_id', 'paypal_secret', 'paypal_mode',
                'paypal_client_id_sandbox', 'paypal_secret_sandbox',
                'paypal_client_id_live',    'paypal_secret_live',
                'shelly_api_url', 'shelly_auth_key',
                'hikvision_host', 'hikvision_user', 'hikvision_pass',
                'firebase_api_key', 'firebase_auth_domain',
                'firebase_database_url', 'firebase_project_id', 'firebase_app_id',
            ];
            foreach ($campos as $c) {
                $this->cfg->set($c, trim($this->post($c, '')));
            }

            $this->log('Guardar config APIs', 'configuracion');
            $this->flash('success', 'Claves de API guardadas.');
            $this->redirect('config/apis');
        }

        $settings = $this->cfg->getAll();
        $flash      = $this->getFlash();
        $pageTitle  = 'Configuración — APIs y servicios';
        $activeMenu = 'config';
        $seccion    = 'apis';

        ob_start();
        require ROOT_PATH . '/app/views/panel/config/apis.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    // ── Correo SMTP ───────────────────────────────────────────────────────────
    public function correo(?string $p = null): void
    {
        if ($this->isPost()) {
            $campos = [
                'smtp_host',
                'smtp_port',
                'smtp_encryption',
                'smtp_username',
                'smtp_password',
                'smtp_from_email',
                'smtp_from_name'
            ];

            foreach ($campos as $c) {
                $this->cfg->set($c, trim($this->post($c, '')));
            }

            $this->log('Guardar config correo', 'configuracion');
            $this->flash('success', 'Configuración de correo guardada correctamente.');
            $this->redirect('config/correo');
        }

        $settings   = $this->asClave($this->cfg->getGrupo('email'));
        $flash      = $this->getFlash();
        $pageTitle  = 'Configuración — Correo';
        $activeMenu = 'config';
        $seccion    = 'correo';

        ob_start();
        require ROOT_PATH . '/app/views/panel/config/correo.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    // ── Upload logo ───────────────────────────────────────────────────────────
    private function subirLogo(array $file): ?string
    {
        if ($file['error'] !== UPLOAD_ERR_OK) return null;
        if ($file['size'] > 2 * 1024 * 1024) return null;

        $ext   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'svg'], true)) return null;

        $dir = UPLOAD_PATH . 'logos/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $nombre = 'logo_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) return null;

        return UPLOAD_URL . 'logos/' . $nombre;
    }

    // ── Helper: rows → [clave => valor] ──────────────────────────────────────
    private function asClave(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $out[$row['clave']] = $row['valor'];
        }
        return $out;
    }
}
