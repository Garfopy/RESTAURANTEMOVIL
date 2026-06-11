<?php
/**
 * lib/util.php — Utilidades comunes
 */

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_json_body(): array {
    global $body;
    return $body ?? [];
}

function require_field(string $key, ?array $haystack = null) {
    $bag = $haystack ?? get_json_body();
    if (!isset($bag[$key]) || $bag[$key] === '' || $bag[$key] === null) {
        throw new RuntimeException("Campo requerido: $key", 422);
    }
    return $bag[$key];
}

function sha256_hex(string $s): string {
    return hash('sha256', $s);
}

/**
 * Genera un token aleatorio seguro (equivalente a secrets.token_hex(32) en Python).
 */
function generate_token(int $bytes = 32): string {
    return bin2hex(random_bytes($bytes));
}

function has_social_profile(array $user): bool {
    return !empty($user['foto_url'])
        && $user['edad'] !== null
        && !empty($user['sexualidad'])
        && !empty($user['genero'])
        && !empty($user['descripcion']);
}

/** Devuelve el mes actual (timestamp UNIX) sin hora, o un valor por defecto */
function now_ts(): string {
    return (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
}