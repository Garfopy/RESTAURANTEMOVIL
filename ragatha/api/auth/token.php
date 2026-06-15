<?php
/**
 * /api/auth/token.php — Generar JWT desde sesión PHP
 * Accede a: https://idactivos.digital/api/auth/token.php
 * 
 * Convierte sesión PHP existente a JWT para usar en llamadas API
 * Esperado: JSON con token JWT y datos del usuario
 */

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Cargar config, database y controladores
require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/app/controllers/ApiController.php';

// Iniciar sesión
ini_set('session.gc_maxlifetime', 31536000);
session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'samesite' => 'Lax']);
session_name(SESSION_NAME);
session_start();

header('Content-Type: application/json; charset=utf-8');

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, OPTIONS');
    header('Access-Control-Allow-Headers: Authorization, Content-Type');
    http_response_code(204);
    exit;
}

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Use GET.'
    ]);
    exit;
}

// Verificar sesión PHP
if (empty($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'No hay sesión activa. Por favor inicia sesión primero.'
    ]);
    exit;
}

$usuario = $_SESSION['usuario'];

// Validar rol (admin o admin_restaurante)
$rol = $usuario['rol'] ?? $usuario['rol_slug'] ?? '';
$rolValido = ($rol === 'admin' || $rol === 'admin_restaurante');
if (!$rolValido) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Acceso denegado. Se requiere rol de administrador.'
    ]);
    exit;
}

// Generar JWT usando ApiController
$apiController = new ApiController();
$token = $apiController->generateJWT($usuario);

// Retornar token
http_response_code(200);
echo json_encode([
    'success' => true,
    'message' => 'Token generado exitosamente',
    'data' => [
        'user' => [
            'id' => (int)$usuario['id'],
            'nombre' => $usuario['nombre'] ?? '',
            'email' => $usuario['email'] ?? '',
            'rol' => $usuario['rol'] ?? '',
            'rol_slug' => $usuario['rol_slug'] ?? '',
        ],
        'token' => $token,
    ],
    'timestamp' => date('Y-m-d H:i:s')
]);
