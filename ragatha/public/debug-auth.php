<?php
/**
 * DEBUG TEMPORAL — ELIMINAR DEL SERVIDOR DESPUÉS DE VERIFICAR
 *
 * Uso:
 *   curl -s -H "Authorization: Bearer TU_TOKEN" https://carnihub.digital/debug-auth.php
 *
 * Qué confirmar:
 *   - "HTTP_AUTHORIZATION" debe mostrar el token → .htaccess funciona
 *   - Si todos los campos muestran "(vacío)" → Apache ignora el .htaccess
 *     (AllowOverride All no está habilitado en httpd.conf / apache2.conf)
 */
header('Content-Type: application/json; charset=utf-8');

// Solo accesible desde IPs de confianza o con clave temporal
$allowedIPs = ['127.0.0.1', '::1'];
$debugKey   = 'crh-debug-2026';

$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
$key = $_GET['key'] ?? '';

if (!in_array($ip, $allowedIPs, true) && $key !== $debugKey) {
    http_response_code(403);
    echo json_encode(['error' => 'Acceso denegado']);
    exit;
}

$data = [
    'timestamp'                            => date('Y-m-d H:i:s'),
    'HTTP_AUTHORIZATION'                   => $_SERVER['HTTP_AUTHORIZATION']                        ?? '(vacío)',
    'REDIRECT_HTTP_AUTHORIZATION'          => $_SERVER['REDIRECT_HTTP_AUTHORIZATION']               ?? '(vacío)',
    'REDIRECT_REDIRECT_HTTP_AUTHORIZATION' => $_SERVER['REDIRECT_REDIRECT_HTTP_AUTHORIZATION']      ?? '(vacío)',
    'REQUEST_METHOD'                       => $_SERVER['REQUEST_METHOD']  ?? '',
    'REQUEST_URI'                          => $_SERVER['REQUEST_URI']     ?? '',
    'SERVER_SOFTWARE'                      => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'REMOTE_ADDR'                          => $ip,
    'getallheaders'                        => function_exists('getallheaders') ? getallheaders() : '(función no disponible)',
    'apache_request_headers'               => function_exists('apache_request_headers') ? apache_request_headers() : '(función no disponible)',
];

echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
