<?php

declare(strict_types=1);

namespace Amare\Api\Middleware;

use Amare\Api\Config\Database;
use Amare\Api\Config\Environment;
use Amare\Api\Helpers\Response;
use Amare\Api\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class AuthMiddleware
{
    private const ACCOUNT_SUSPENDED_CODE = 'ACCOUNT_SUSPENDED';

    private static function getSecret(): string
    {
        $secret = Environment::value('JWT_SECRET', '') ?? '';

        if (strlen($secret) < 48) {
            error_log('JWT_SECRET missing or shorter than 48 characters.');
            Response::serverError('La autenticacion no esta disponible temporalmente.');
        }

        return $secret;
    }

    public static function authenticate(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            Response::unauthorized('Token no proporcionado');
        }

        $token = substr($authHeader, 7);
        $secret = self::getSecret();

        try {

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            // Compatibilidad con tokens que guardan la información en "data"
            if (isset($decoded->data)) {

                $user = $decoded->data;

                // Compatibilidad: usar sub como id
                if (isset($user->sub) && !isset($user->id)) {
                    $user->id = (int)$user->sub;
                }

                self::assertUserCanUseToken($user);

                return $user;
            }

            // Compatibilidad con tokens planos
            if (isset($decoded->sub) && !isset($decoded->id)) {
                $decoded->id = (int)$decoded->sub;
            }

            self::assertUserCanUseToken($decoded);

            return $decoded;

        } catch (\Exception $e) {

            error_log('JWT ERROR: ' . get_class($e));
            error_log('JWT ERROR MSG: ' . $e->getMessage());

            Response::unauthorized('Token inválido o expirado');
        }
    }

    private static function assertUserCanUseToken(object $user): void
    {
        $userId = isset($user->id) ? (int)$user->id : 0;
        if ($userId <= 0) {
            Response::unauthorized('Token invalido');
        }

        $authSource = isset($user->auth_source) ? (string)$user->auth_source : null;
        if (User::findAuthenticated($userId, $authSource)) {
            return;
        }

        // Solo hacemos la consulta adicional cuando la autenticacion fallo para
        // distinguir una cuenta suspendida de un usuario inexistente.
        if ($authSource !== 'staff') {
            $storedUser = User::findById($userId);
            if ($storedUser && array_key_exists('activo', $storedUser) && (int)$storedUser['activo'] !== 1) {
                self::accountSuspendedResponse($userId, 401);
            }
        }

        Response::unauthorized('Cuenta desactivada o no disponible');
    }

    /**
     * Requiere permisos de administrador
     */
    public static function requireAdmin(): object
    {
        $user = self::authenticate();

        $rol = $user->rol ?? '';

        if ($rol !== 'admin' && $rol !== 'admin_restaurante') {
            Response::error(
                'Acceso denegado. Se requieren permisos de administrador.',
                403
            );
        }

        return $user;
    }

    public static function optional(): ?object
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            return null;
        }

        $token = substr($authHeader, 7);
        $secret = self::getSecret();

        try {

            $decoded = JWT::decode($token, new Key($secret, 'HS256'));

            if (isset($decoded->data)) {

                $user = $decoded->data;

                if (isset($user->sub) && !isset($user->id)) {
                    $user->id = (int)$user->sub;
                }

                return $user;
            }

            if (isset($decoded->sub) && !isset($decoded->id)) {
                $decoded->id = (int)$decoded->sub;
            }

            return $decoded;

        } catch (\Exception $e) {

            return null;
        }
    }

    public static function generateToken(array $data): string
    {
        $authSource = isset($data['auth_source']) ? (string)$data['auth_source'] : 'mobile';
        if ($authSource === 'mobile') {
            $userId = isset($data['id']) ? (int)$data['id'] : 0;
            $user = $userId > 0 ? User::findById($userId) : null;
            if (!$user || (array_key_exists('activo', $user) && (int)$user['activo'] !== 1)) {
                self::accountSuspendedResponse($userId, 403);
            }
        }

        $secret = self::getSecret();
        $expiry = (int)($_ENV['JWT_EXPIRY'] ?? 720);

        $issuedAt = time();
        $expireAt = $issuedAt + ($expiry * 60 * 60);

        $payload = [
            'iss' => $_ENV['APP_URL'] ?? 'amare-api',
            'aud' => $_ENV['APP_URL'] ?? 'amare-api',
            'iat' => $issuedAt,
            'nbf' => $issuedAt,
            'exp' => $expireAt,
            'data' => (object)$data
        ];

        return JWT::encode($payload, $secret, 'HS256');
    }

    private static function accountSuspendedResponse(int $userId, int $statusCode): void
    {
        $notice = self::buildSuspensionNotice($userId);

        Response::json([
            'success' => false,
            'message' => $notice['explanation'],
            'code' => self::ACCOUNT_SUSPENDED_CODE,
            'data' => $notice,
        ], $statusCode);
    }

    private static function buildSuspensionNotice(int $userId): array
    {
        $report = self::latestReportForSuspendedUser($userId);
        $reasonCode = is_array($report) ? (string)($report['reason'] ?? '') : '';
        $reason = self::formatSuspensionReason($reasonCode);
        $details = is_array($report) ? trim((string)($report['details'] ?? '')) : '';

        if ($reason === '') {
            $reason = 'Cuenta desactivada por moderacion';
        }

        $explanation = 'Tu cuenta fue suspendida y no puede acceder a la app en este momento. ';
        $explanation .= 'Si consideras que fue un error, contacta al restaurante para solicitar una revision.';

        return [
            'title' => 'Cuenta suspendida',
            'reason_code' => $reasonCode !== '' ? $reasonCode : null,
            'reason' => $reason,
            'details' => $details !== '' ? substr($details, 0, 600) : null,
            'explanation' => $explanation,
            'support_hint' => 'Contacta al restaurante para revisar tu caso.',
        ];
    }

    private static function latestReportForSuspendedUser(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $candidates = [];
        try {
            $report = Database::queryOne(
                "SELECT reason, details, status, created_at
                   FROM social_reports
                  WHERE reported_user_id = :user_id
                  ORDER BY created_at DESC
                  LIMIT 1",
                [':user_id' => $userId]
            );
            if ($report) $candidates[] = $report;
        } catch (\Throwable $e) {
            // La moderacion de fotos puede seguir aportando el motivo.
        }

        try {
            $photoModeration = Database::queryOne(
                "SELECT 'inappropriate_content' AS reason,
                        COALESCE(NULLIF(review_notes, ''), 'Una foto del perfil incumplio las reglas de la comunidad.') AS details,
                        status,
                        COALESCE(reviewed_at, created_at) AS created_at
                   FROM social_photo_moderation
                  WHERE user_id = :user_id
                    AND status = 'rejected'
                    AND COALESCE(review_notes, '') NOT IN ('account_deleted', 'deleted_by_user')
                  ORDER BY COALESCE(reviewed_at, created_at) DESC
                  LIMIT 1",
                [':user_id' => $userId]
            );
            if ($photoModeration) $candidates[] = $photoModeration;
        } catch (\Throwable $e) {
            // La tabla puede no existir en instalaciones anteriores.
        }

        if (empty($candidates)) return null;
        usort(
            $candidates,
            static fn(array $left, array $right): int => strcmp(
                (string)($right['created_at'] ?? ''),
                (string)($left['created_at'] ?? '')
            )
        );
        return $candidates[0];
    }

    private static function formatSuspensionReason(string $reasonCode): string
    {
        return match ($reasonCode) {
            'harassment' => 'Acoso o conducta ofensiva',
            'inappropriate_content' => 'Contenido inapropiado',
            'fake_profile' => 'Perfil falso o suplantacion',
            'safety' => 'Riesgo de seguridad',
            'spam' => 'Spam o uso indebido',
            'other' => 'Otro motivo reportado',
            default => '',
        };
    }
}
