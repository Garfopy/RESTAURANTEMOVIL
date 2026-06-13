<?php
/**
 * social_update_profile.php — Standalone
 * POST /api_asocial/social_update_profile.php
 * GET  /api_asocial/social_update_profile.php
 *
 * MAPEO FRONTEND → BD:
 *   edad        → edad
 *   genero      → genero
 *   biografia   → descripcion
 *   gustos      → intereses
 *   instagram   → redes_sociales (JSON)
 *   tiktok      → redes_sociales (JSON)
 *   modo_social → is_social_active
 *
 * Headers: Authorization: Bearer <token>
 */
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/util.php';
require_once __DIR__ . '/lib/auth.php';

set_exception_handler(function ($ex) {
    $code = $ex->getCode() ?: 500;
    if (!in_array($code, [400, 401, 403, 404, 409, 422], true)) $code = 500;
    json_response(['detail' => $ex->getMessage()], $code);
});

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$userId = require_auth();

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) $body = [];

$edad         = isset($body['edad']) && $body['edad'] !== null ? (int)$body['edad'] : null;
$genero       = isset($body['genero']) ? trim((string)$body['genero']) : null;
$descripcion  = isset($body['biografia']) ? trim((string)$body['biografia'])
               : (isset($body['descripcion']) ? trim((string)$body['descripcion']) : null);
$intereses    = isset($body['gustos']) ? trim((string)$body['gustos'])
               : (isset($body['intereses']) ? trim((string)$body['intereses']) : null);
$redesSociales = null;
if (isset($body['instagram']) || isset($body['tiktok'])) {
    $rs = [];
    if (!empty($body['instagram'])) $rs['instagram'] = trim((string)$body['instagram']);
    if (!empty($body['tiktok']))    $rs['tiktok']    = trim((string)$body['tiktok']);
    $redesSociales = !empty($rs) ? json_encode($rs, JSON_UNESCAPED_UNICODE) : null;
}
$isSocialActive = isset($body['modo_social']) ? (int)$body['modo_social']
                : (isset($body['is_social_active']) ? (int)$body['is_social_active'] : null);

// ── Modo GET ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $user = db_one(
            "SELECT id, nombre, email, telefono, foto_url, edad, genero,
                    descripcion, intereses, redes_sociales, is_social_active
               FROM mobile_usuarios WHERE id = ?",
            [$userId]
        );
    } catch (Throwable $e) {
        json_response(['detail' => 'Error de base de datos: ' . $e->getMessage()], 500);
    }
    if (!$user) json_response(['detail' => 'Usuario no encontrado.'], 404);
    $user['id'] = (int)$user['id'];
    if ($user['edad'] !== null) $user['edad'] = (int)$user['edad'];
    if ($user['is_social_active'] !== null) $user['is_social_active'] = (bool)$user['is_social_active'];
    $user['biografia'] = $user['descripcion'];
    $user['gustos']    = $user['intereses'];
    if ($user['redes_sociales']) {
        $rs = json_decode($user['redes_sociales'], true);
        $user['instagram'] = $rs['instagram'] ?? null;
        $user['tiktok']    = $rs['tiktok'] ?? null;
    } else {
        $user['instagram'] = null;
        $user['tiktok']    = null;
    }
    unset($user['descripcion'], $user['intereses'], $user['redes_sociales']);
    json_response(['success' => true, 'data' => $user]);
}

// ── Construir SET dinámico ─────────────────────────────────────────────
$fields = [];
$params = [];

if ($edad !== null) {
    if ($edad < 1 || $edad > 120) json_response(['detail' => 'Edad inválida.'], 422);
    $fields[] = 'edad = ?';          $params[] = $edad;
}
if ($genero !== null)       { $fields[] = 'genero = ?';          $params[] = $genero; }
if ($descripcion !== null)  { $fields[] = 'descripcion = ?';     $params[] = $descripcion; }
if ($intereses !== null)    { $fields[] = 'intereses = ?';       $params[] = $intereses; }
if ($redesSociales !== null){ $fields[] = 'redes_sociales = ?';  $params[] = $redesSociales; }
if ($isSocialActive !== null){$fields[] = 'is_social_active = ?';$params[] = $isSocialActive; }

if (empty($fields)) {
    json_response(['detail' => 'No hay campos para actualizar.'], 422);
}

$fields[] = 'updated_at = ?';
$params[] = now_ts();
$params[] = $userId;

try {
    db_exec("UPDATE mobile_usuarios SET " . implode(', ', $fields) . " WHERE id = ?", $params);
} catch (Throwable $e) {
    json_response(['detail' => 'Error al actualizar: ' . $e->getMessage()], 500);
}

// ── Devolver perfil actualizado ────────────────────────────────────────
try {
    $user = db_one(
        "SELECT id, nombre, email, telefono, foto_url, edad, genero,
                descripcion, intereses, redes_sociales, is_social_active
           FROM mobile_usuarios WHERE id = ?",
        [$userId]
    );
} catch (Throwable $e) {
    json_response(['detail' => 'Error de base de datos: ' . $e->getMessage()], 500);
}
$user['id'] = (int)$user['id'];
if ($user['edad'] !== null) $user['edad'] = (int)$user['edad'];
if ($user['is_social_active'] !== null) $user['is_social_active'] = (bool)$user['is_social_active'];
$user['biografia'] = $user['descripcion'];
$user['gustos']    = $user['intereses'];
if ($user['redes_sociales']) {
    $rs = json_decode($user['redes_sociales'], true);
    $user['instagram'] = $rs['instagram'] ?? null;
    $user['tiktok']    = $rs['tiktok'] ?? null;
} else {
    $user['instagram'] = null;
    $user['tiktok']    = null;
}
unset($user['descripcion'], $user['intereses'], $user['redes_sociales']);

json_response(['success' => true, 'data' => $user]);</write_file>