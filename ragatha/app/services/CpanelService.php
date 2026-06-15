<?php
/**
 * CpanelService
 * Integración con cPanel UAPI para gestión de usuarios FTP
 */
class CpanelService
{
    private string $host;
    private string $username;
    private string $token;
    private string $domain;
    private string $ftpDir;
    private int $ftpQuota;

    public function __construct()
    {
        $db = Database::getInstance();
        $get = fn(string $k) => $db->query("SELECT valor FROM global_settings WHERE clave = '$k' LIMIT 1")->fetchColumn() ?: '';

        $this->host     = $get('cpanel_host');
        $this->username = $get('cpanel_username');
        $this->token    = $get('cpanel_token');
        $this->domain   = $get('cpanel_domain');
        $this->ftpDir   = $get('cpanel_ftp_dir') ?: '/public_html/uploads/usuarios/';
        $this->ftpQuota = (int)($get('cpanel_ftp_quota') ?: 500);
    }

    /**
     * Crea un usuario FTP en cPanel
     *
     * @param string $username Username FTP (sin dominio)
     * @param string $password Contraseña
     * @return array ['success' => bool, 'username' => string, 'error' => string]
     */
    public function crearUsuarioFTP(string $username, string $password): array
    {
        // Validar configuración
        if (!$this->host || !$this->username || !$this->token) {
            return [
                'success'  => false,
                'username' => '',
                'error'    => 'Configuración de cPanel incompleta. Configura host, usuario y token.'
            ];
        }

        // Sanitizar username: solo a-z0-9_
        $username = preg_replace('/[^a-z0-9_]/', '', strtolower($username));
        if (strlen($username) > 16) {
            $username = substr($username, 0, 16);
        }

        // Verificar si username ya existe
        $existe = $this->verificarUsuarioExiste($username);
        if ($existe) {
            return [
                'success'  => false,
                'username' => '',
                'error'    => "El usuario FTP '$username' ya existe en el servidor."
            ];
        }

        // Crear directorio home para el usuario
        $homeDir = rtrim($this->ftpDir, '/') . '/' . $username;

        // Llamar a cPanel UAPI: Ftp::add_ftp
        $resultado = $this->uapiCall('Ftp', 'add_ftp', [
            'user'    => $username,
            'pass'    => $password,
            'quota'   => $this->ftpQuota,
            'homedir' => $homeDir
        ]);

        if ($resultado['success']) {
            return [
                'success'  => true,
                'username' => $username,
                'error'    => ''
            ];
        } else {
            error_log("[CpanelService] Error al crear FTP: " . $resultado['error']);
            return [
                'success'  => false,
                'username' => '',
                'error'    => 'No se pudo crear el usuario FTP. Verifica los logs del servidor.'
            ];
        }
    }

    /**
     * Cambia la contraseña de un usuario FTP existente
     *
     * @param string $username Username FTP
     * @param string $nuevaPassword Nueva contraseña
     * @return bool True si se cambió correctamente
     */
    public function cambiarPasswordFTP(string $username, string $nuevaPassword): bool
    {
        if (!$this->host || !$this->username || !$this->token) {
            return false;
        }

        $resultado = $this->uapiCall('Ftp', 'passwd', [
            'user' => $username,
            'pass' => $nuevaPassword
        ]);

        if (!$resultado['success']) {
            error_log("[CpanelService] Error al cambiar password FTP: " . $resultado['error']);
        }

        return $resultado['success'];
    }

    /**
     * Verifica si un usuario FTP existe en el servidor
     *
     * @param string $username Username FTP
     * @return bool True si existe
     */
    public function verificarUsuarioExiste(string $username): bool
    {
        if (!$this->host || !$this->username || !$this->token) {
            return false;
        }

        $resultado = $this->uapiCall('Ftp', 'list_ftp', []);

        if ($resultado['success'] && isset($resultado['data'])) {
            foreach ($resultado['data'] as $ftpUser) {
                if (isset($ftpUser['user']) && $ftpUser['user'] === $username) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Elimina un usuario FTP del servidor
     *
     * @param string $username Username FTP
     * @return bool True si se eliminó correctamente
     */
    public function eliminarUsuarioFTP(string $username): bool
    {
        if (!$this->host || !$this->username || !$this->token) {
            return false;
        }

        $resultado = $this->uapiCall('Ftp', 'delete_ftp', [
            'user' => $username,
            'destroy' => 1  // Eliminar también el directorio home
        ]);

        if (!$resultado['success']) {
            error_log("[CpanelService] Error al eliminar usuario FTP: " . $resultado['error']);
        }

        return $resultado['success'];
    }

    /**
     * Realiza una llamada a cPanel UAPI
     *
     * @param string $module Módulo de UAPI (ej: Ftp, Email, Mysql)
     * @param string $function Función a llamar
     * @param array $params Parámetros de la función
     * @return array ['success' => bool, 'data' => mixed, 'error' => string]
     */
    private function uapiCall(string $module, string $function, array $params): array
    {
        // Construir URL
        $url = 'https://' . $this->host . '/execute/' . $module . '/' . $function;

        // Agregar parámetros a la URL
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        // Configurar cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);  // Deshabilitar verificación SSL (necesario para IPs en hosting compartido)
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: cpanel ' . $this->username . ':' . $this->token
        ]);

        // Ejecutar request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        // Verificar errores de conexión
        if ($response === false) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => 'Error de conexión: ' . $error
            ];
        }

        // Parsear respuesta JSON
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => 'Respuesta JSON inválida del servidor cPanel'
            ];
        }

        // Verificar estructura de respuesta UAPI
        if (!isset($data['status'])) {
            return [
                'success' => false,
                'data'    => null,
                'error'   => 'Respuesta de cPanel en formato inesperado'
            ];
        }

        // cPanel UAPI retorna status = 1 para éxito
        $success = ($data['status'] == 1);
        $errorMsg = '';

        if (!$success && isset($data['errors'])) {
            $errorMsg = is_array($data['errors']) ? implode(', ', $data['errors']) : $data['errors'];
        }

        return [
            'success' => $success,
            'data'    => $data['data'] ?? null,
            'error'   => $errorMsg
        ];
    }
}
