<?php
/**
 * lib/auth.php — Verificación de token Bearer (SHA-256 contra mobile_sesiones).
 *
 * Si la petición no trae token válido, responde 401 y termina.
 * Devuelve el user_id (int) en caso exitoso.
 */

function get_bearer_token(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($header, 'Bearer ') === 0) {
        return trim(substr($header, 7));
    }
    // Fallback para servidores sin Authorization header
    if (function_exists('apache_request_headers')) {
        $headers = array_change_key_case(apache_request_headers(), CASE_LOWER);
        if (isset($headers['authorization']) && stripos($headers['authorization'], 'Bearer ') === 0) {
            return trim(substr($headers['authorization'], 7));
        }
    }
    return null;
}

function require_auth(): int {
    $token = get_bearer_token();
    if (!$token) {
        json_response(['detail' => 'Token inválido o expirado.'], 401);
    }

    $tokenHash = sha256_hex($token);
    $row = db_one(
        "SELECT id, usuario_id, expires_at, activo
           FROM mobile_sesiones
          WHERE token_hash = ?
          LIMIT 1",
        [$tokenHash]
    );

    if (!$row || !$row['activo'] || strtotime($row['expires_at']) < time()) {
        json_response(['detail' => 'Token inválido o expirado.'], 401);
    }

    // Touch ultimo_uso
    db_exec(
        "UPDATE mobile_sesiones SET ultimo_uso = ? WHERE id = ?",
        [now_ts(), $row['id']]
    );

    return (int) $row['usuario_id'];
}