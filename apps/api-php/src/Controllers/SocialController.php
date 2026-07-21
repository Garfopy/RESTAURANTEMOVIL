<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\ImageUploadHelper;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Models\Order;
use Amare\Api\Models\User;
use Amare\Api\Services\FirebaseMessagingService;
use Amare\Api\Services\RewardsService;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Amare\Api\Services\StripeConfig;

class SocialController
{
    private const DEFAULT_RESTAURANT_LOGO = 'public/uploads/restaurantes/rest_logo_1_1781280185.png';
    private const MAX_SOCIAL_PHOTOS = 6;
    private const SOCIAL_CONSENT_VERSION = 'social-v1-2026-06-16';
    private const SOCIAL_REPORT_REASONS = [
        'harassment',
        'inappropriate_content',
        'fake_profile',
        'safety',
        'spam',
        'other',
    ];

    public function updateStatus(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (!array_key_exists('is_social_active', $input)) {
            Response::validationError(['is_social_active' => ['El campo is_social_active es obligatorio']]);
        }

        $isActive = (bool)$input['is_social_active'];
        $restaurantId = isset($input['current_restaurante_id']) && $input['current_restaurante_id'] !== null
            ? (int)$input['current_restaurante_id']
            : null;
        $mesa = array_key_exists('mesa', $input) ? $this->sanitizeNullableString($input['mesa']) : null;

        if ($isActive && !$restaurantId) {
            Response::validationError([
                'current_restaurante_id' => ['Selecciona una sucursal antes de activar el modo social'],
            ]);
        }

        $acceptsSocialPrivacy = !empty($input['accepts_social_privacy']);
        $currentProfile = null;
        $shouldStoreConsent = false;

        if ($isActive) {
            if (!$this->hasMesaColumn()) {
                Response::serverError('La base de datos aún no tiene la columna mesa. Ejecuta primero el SQL de la fase 1.');
            }

            if ($mesa === null) {
                Response::validationError([
                    'mesa' => ['Ingresa tu número de mesa para activar el modo social'],
                ]);
            }

            $currentProfile = $this->fetchSocialProfile($user->id);
            if (!$currentProfile || !$this->hasSocialProfile($currentProfile)) {
                Response::error('Debes completar tu perfil social antes de activar el modo social.', 400);
            }

            if (!$this->hasCurrentSocialConsent($currentProfile)) {
                if (!$acceptsSocialPrivacy) {
                    Response::validationError([
                        'accepts_social_privacy' => ['Acepta el aviso de privacidad social para activar el modo social'],
                    ]);
                }

                if (!$this->hasSocialConsentColumns()) {
                    Response::serverError('La base de datos aún no tiene las columnas de consentimiento social. Ejecuta la migración 024.');
                }

                $shouldStoreConsent = true;
            }

            $resolvedMesa = $this->resolveMesaForRestaurant($restaurantId, $mesa);
            if ($resolvedMesa !== null) {
                $this->assertCanUseTableSession(
                    (int)$user->id,
                    $restaurantId,
                    (int)$resolvedMesa['id'],
                    $resolvedMesa['label'] ?? $mesa
                );
            }
        }

        $setClauses = [
            'is_social_active = :active',
            'current_restaurante_id = :restaurant_id',
            'social_updated_at = NOW()',
            'updated_at = NOW()',
        ];
        $params = [
            ':active' => $isActive ? 1 : 0,
            ':restaurant_id' => $isActive ? $restaurantId : null,
            ':user_id' => $user->id,
        ];

        if ($this->hasMesaColumn()) {
            $setClauses[] = 'mesa = :mesa';
            $params[':mesa'] = $isActive ? $mesa : null;
        }
        if ($shouldStoreConsent) {
            $setClauses[] = 'social_consent_accepted_at = NOW()';
            $setClauses[] = 'social_consent_version = :social_consent_version';
            $params[':social_consent_version'] = self::SOCIAL_CONSENT_VERSION;
        }

        Database::rowCount(
            "UPDATE mobile_usuarios
                SET " . implode(",\n                    ", $setClauses) . "
              WHERE id = :user_id",
            $params
        );

        $updated = Database::queryOne(
            "SELECT id AS user_id, nombre, is_social_active, current_restaurante_id, social_updated_at" . ($this->hasSocialConsentColumns() ? ", social_consent_accepted_at, social_consent_version" : "") . ($this->hasMesaColumn() ? ", mesa" : "") . "
               FROM mobile_usuarios
              WHERE id = :id",
            [':id' => $user->id]
        );

        if (!$updated) {
            Response::notFound('Usuario no encontrado');
        }

        Response::success([
            'user_id' => (int)$updated['user_id'],
            'nombre' => $updated['nombre'],
            'is_social_active' => (bool)$updated['is_social_active'],
            'modo_social' => (bool)$updated['is_social_active'],
            'current_restaurante_id' => $updated['current_restaurante_id'] !== null ? (int)$updated['current_restaurante_id'] : null,
            'mesa' => $updated['mesa'] ?? null,
            'social_updated_at' => $updated['social_updated_at'],
            'social_consent_accepted_at' => $updated['social_consent_accepted_at'] ?? null,
            'social_consent_version' => $updated['social_consent_version'] ?? null,
            'requires_social_consent' => !$this->hasCurrentSocialConsent($updated),
        ]);
    }

    public function activeDiners(int $restaurantId): void
    {
        $user = AuthMiddleware::authenticate();

        $sql = "SELECT id AS user_id, nombre, foto_url, edad, genero, sexualidad, descripcion, intereses, que_busca, redes_sociales" . ($this->hasSocialPhotosColumn() ? ", social_photos_json" : "") . ($this->hasMesaColumn() ? ", mesa" : "") . "
                  FROM mobile_usuarios
                 WHERE is_social_active = 1
                   AND current_restaurante_id = :restaurant_id
                   AND id != :current_user_id";

        $params = [
            ':restaurant_id' => $restaurantId,
            ':current_user_id' => $user->id,
        ];
        $blockedUsersSql = $this->blockedUsersSql('id', ':current_user_id_blocker', ':current_user_id_blocked');
        if ($blockedUsersSql !== '') {
            $sql .= $blockedUsersSql;
            $params[':current_user_id_blocker'] = $user->id;
            $params[':current_user_id_blocked'] = $user->id;
        }

        if (!empty($_GET['edad_min'])) {
            $sql .= " AND edad >= :edad_min";
            $params[':edad_min'] = (int)$_GET['edad_min'];
        }
        if (!empty($_GET['edad_max'])) {
            $sql .= " AND edad <= :edad_max";
            $params[':edad_max'] = (int)$_GET['edad_max'];
        }
        if (!empty($_GET['genero'])) {
            $sql .= " AND genero = :genero";
            $params[':genero'] = (string)$_GET['genero'];
        }
        if (!empty($_GET['sexualidad'])) {
            $sql .= " AND sexualidad = :sexualidad";
            $params[':sexualidad'] = (string)$_GET['sexualidad'];
        }

        $sql .= " ORDER BY social_updated_at DESC";
        $diners = Database::query($sql, $params);

        Response::success(array_map(function (array $row) use ($user): array {
            $profile = $this->normalizeProfileRow($row, false);
            $profile['relationship_status'] = $this->getRelationshipStatus((int)$user->id, (int)$profile['user_id']);
            return $profile;
        }, $diners));
    }

    public function likeDiner(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $likedUserId = isset($input['liked_user_id']) ? (int)$input['liked_user_id'] : 0;
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;

        if ($likedUserId <= 0) {
            Response::validationError(['liked_user_id' => ['Selecciona un comensal válido']]);
        }
        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal válida']]);
        }
        if ($likedUserId === (int)$user->id) {
            Response::validationError(['liked_user_id' => ['No puedes darte like a ti mismo']]);
        }
        if (!$this->tableExists('social_likes')) {
            Response::serverError('La tabla social_likes aún no existe. Ejecuta la migración 027.');
        }

        $sender = $this->fetchSocialProfile((int)$user->id);
        if (
            !$sender ||
            !$this->hasSocialProfile($sender) ||
            !(bool)($sender['is_social_active'] ?? false) ||
            (int)($sender['current_restaurante_id'] ?? 0) !== $restaurantId
        ) {
            Response::error('Activa tu modo social en la sucursal actual antes de dar like.', 400);
        }

        $target = $this->fetchSocialProfile($likedUserId);
        if (!$target || !$this->hasSocialProfile($target)) {
            Response::notFound('Comensal no encontrado o sin perfil social completo');
        }
        if ($this->isBlockedBetween((int)$user->id, $likedUserId)) {
            Response::error('No puedes interactuar con este perfil.', 403);
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            Database::rowCount(
                "INSERT INTO social_likes (liker_user_id, liked_user_id, restaurante_id, created_at, updated_at)
                 VALUES (:liker_user_id, :liked_user_id, :restaurante_id, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE restaurante_id = VALUES(restaurante_id), updated_at = NOW()",
                [
                    ':liker_user_id' => (int)$user->id,
                    ':liked_user_id' => $likedUserId,
                    ':restaurante_id' => $restaurantId,
                ]
            );

            $reverseLike = Database::queryOne(
                "SELECT id
                   FROM social_likes
                  WHERE liker_user_id = :liked_user_id
                    AND liked_user_id = :liker_user_id
                  LIMIT 1",
                [
                    ':liked_user_id' => $likedUserId,
                    ':liker_user_id' => (int)$user->id,
                ]
            );

            $matched = $reverseLike !== null;
            if ($matched) {
                Database::rowCount(
                    "UPDATE social_likes
                        SET matched_at = COALESCE(matched_at, NOW()), updated_at = NOW()
                      WHERE (liker_user_id = :user_id_a AND liked_user_id = :liked_user_id_a)
                         OR (liker_user_id = :liked_user_id_b AND liked_user_id = :user_id_b)",
                    [
                        ':user_id_a' => (int)$user->id,
                        ':liked_user_id_a' => $likedUserId,
                        ':liked_user_id_b' => $likedUserId,
                        ':user_id_b' => (int)$user->id,
                    ]
                );
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('SocialController::likeDiner ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo guardar el like en este momento.');
        }

        $matchProfile = null;
        if ($matched) {
            $target = $this->fetchSocialProfile($likedUserId);
            if ($target) {
                $matchProfile = $this->normalizeProfileRow($target, false);
                $matchProfile['relationship_status'] = 'matched';
                $matchProfile['matched_at'] = $this->getMatchDate((int)$user->id, $likedUserId);
            }
        }

        Response::success([
            'liked' => true,
            'matched' => $matched,
            'relationship_status' => $matched ? 'matched' : 'liked',
            'match' => $matchProfile,
        ], $matched ? 'Hicieron match' : 'Like enviado');
    }

    public function unlikeDiner(int $likedUserId): void
    {
        $user = AuthMiddleware::authenticate();

        if ($likedUserId <= 0) {
            Response::validationError(['liked_user_id' => ['Selecciona un comensal válido']]);
        }
        if ($likedUserId === (int)$user->id) {
            Response::validationError(['liked_user_id' => ['No puedes quitarte un like a ti mismo']]);
        }
        if (!$this->tableExists('social_likes')) {
            Response::success(['liked' => false, 'matched' => false, 'relationship_status' => 'none'], 'Like eliminado');
        }

        $like = Database::queryOne(
            "SELECT matched_at FROM social_likes
              WHERE liker_user_id = :liker_user_id AND liked_user_id = :liked_user_id LIMIT 1",
            [':liker_user_id' => (int)$user->id, ':liked_user_id' => $likedUserId]
        );
        if (!$like) {
            Response::success(['liked' => false, 'matched' => false, 'relationship_status' => 'none'], 'Like eliminado');
        }
        if (!empty($like['matched_at'])) {
            Response::error('No puedes quitar el like de un match desde aquí.', 409);
        }

        Database::rowCount(
            "DELETE FROM social_likes
              WHERE liker_user_id = :liker_user_id AND liked_user_id = :liked_user_id AND matched_at IS NULL",
            [':liker_user_id' => (int)$user->id, ':liked_user_id' => $likedUserId]
        );
        Response::success(['liked' => false, 'matched' => false, 'relationship_status' => 'none'], 'Like eliminado');
    }

    public function reportDiner(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $reportedUserId = isset($input['reported_user_id']) ? (int)$input['reported_user_id'] : 0;
        $reason = $this->sanitizeNullableString($input['reason'] ?? null);
        $details = $this->sanitizeNullableString($input['details'] ?? null);

        if ($reportedUserId <= 0 || $reportedUserId === (int)$user->id) {
            Response::validationError(['reported_user_id' => ['Selecciona un perfil valido para reportar']]);
        }
        if ($reason === null) {
            Response::validationError(['reason' => ['Selecciona un motivo del reporte']]);
        }
        if (!in_array($reason, self::SOCIAL_REPORT_REASONS, true)) {
            Response::validationError(['reason' => ['Selecciona un motivo valido del reporte']]);
        }
        if ($details === null || strlen($details) < 10) {
            Response::validationError(['details' => ['Describe brevemente que paso']]);
        }
        if (!$this->tableExists('social_reports')) {
            Response::serverError('La tabla social_reports aun no existe. Ejecuta la migracion 051.');
        }
        if (!$this->fetchSocialProfile($reportedUserId)) {
            Response::notFound('Perfil social no encontrado');
        }

        Database::rowCount(
            'INSERT INTO social_reports
                (reporter_user_id, reported_user_id, reason, details, status, created_at)
             VALUES
                (:reporter_user_id, :reported_user_id, :reason, :details, :status, NOW())',
            [
                ':reporter_user_id' => (int)$user->id,
                ':reported_user_id' => $reportedUserId,
                ':reason' => substr($reason, 0, 80),
                ':details' => $details !== null ? substr($details, 0, 2000) : null,
                ':status' => 'open',
            ]
        );

        Response::success(['reported' => true], 'Reporte recibido');
    }

    public function blockDiner(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $blockedUserId = isset($input['blocked_user_id']) ? (int)$input['blocked_user_id'] : 0;
        $reason = $this->sanitizeNullableString($input['reason'] ?? null);

        if ($blockedUserId <= 0 || $blockedUserId === (int)$user->id) {
            Response::validationError(['blocked_user_id' => ['Selecciona un perfil valido para bloquear']]);
        }
        if (!$this->tableExists('social_blocks')) {
            Response::serverError('La tabla social_blocks aun no existe. Ejecuta la migracion 051.');
        }
        if (!$this->fetchSocialProfile($blockedUserId)) {
            Response::notFound('Perfil social no encontrado');
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            Database::rowCount(
                'INSERT INTO social_blocks (blocker_user_id, blocked_user_id, reason, created_at)
                 VALUES (:blocker_user_id, :blocked_user_id, :reason, NOW())
                 ON DUPLICATE KEY UPDATE reason = VALUES(reason)',
                [
                    ':blocker_user_id' => (int)$user->id,
                    ':blocked_user_id' => $blockedUserId,
                    ':reason' => $reason !== null ? substr($reason, 0, 80) : null,
                ]
            );

            if ($this->tableExists('social_likes')) {
                Database::rowCount(
                    'DELETE FROM social_likes
                      WHERE (liker_user_id = :current_a AND liked_user_id = :blocked_a)
                         OR (liker_user_id = :blocked_b AND liked_user_id = :current_b)',
                    [
                        ':current_a' => (int)$user->id,
                        ':blocked_a' => $blockedUserId,
                        ':blocked_b' => $blockedUserId,
                        ':current_b' => (int)$user->id,
                    ]
                );
            }

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('SocialController::blockDiner ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo bloquear el perfil en este momento.');
        }

        Response::success(['blocked' => true], 'Perfil bloqueado');
    }

    public function unblockDiner(int $blockedUserId): void
    {
        $user = AuthMiddleware::authenticate();

        if ($blockedUserId <= 0 || $blockedUserId === (int)$user->id) {
            Response::validationError(['blocked_user_id' => ['Selecciona un perfil valido para desbloquear']]);
        }
        if ($this->tableExists('social_blocks')) {
            Database::rowCount(
                'DELETE FROM social_blocks
                  WHERE blocker_user_id = :blocker_user_id AND blocked_user_id = :blocked_user_id',
                [
                    ':blocker_user_id' => (int)$user->id,
                    ':blocked_user_id' => $blockedUserId,
                ]
            );
        }

        Response::success(['blocked' => false], 'Perfil desbloqueado');
    }

    public function matches(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!$this->tableExists('social_likes')) {
            Response::success(['matches' => []]);
        }

        $blockedUsersSql = $this->blockedUsersSql('mu.id', ':user_id_blocker', ':user_id_blocked');
        $params = [':user_id' => (int)$user->id];
        if ($blockedUsersSql !== '') {
            $params[':user_id_blocker'] = (int)$user->id;
            $params[':user_id_blocked'] = (int)$user->id;
        }

        $rows = Database::query(
            "SELECT mu.id AS user_id, mu.nombre, mu.foto_url, mu.edad, mu.sexualidad, mu.genero,
                    mu.descripcion, mu.intereses, mu.que_busca, mu.redes_sociales,
                    mu.is_social_active, mu.current_restaurante_id, mu.social_updated_at,
                    sl.matched_at, sl.restaurante_id AS match_restaurante_id" .
                    ($this->hasSocialPhotosColumn() ? ", mu.social_photos_json" : "") .
                    ($this->hasMesaColumn() ? ", mu.mesa" : "") . "
               FROM social_likes sl
               JOIN mobile_usuarios mu ON mu.id = sl.liked_user_id
              WHERE sl.liker_user_id = :user_id
                AND sl.matched_at IS NOT NULL
                " . $blockedUsersSql . "
           ORDER BY sl.matched_at DESC",
            $params
        );

        $matches = array_map(function (array $row): array {
            $profile = $this->normalizeProfileRow($row, false);
            $profile['relationship_status'] = 'matched';
            $profile['matched_at'] = $row['matched_at'] ?? null;
            $profile['match_restaurante_id'] = isset($row['match_restaurante_id']) && $row['match_restaurante_id'] !== null
                ? (int)$row['match_restaurante_id']
                : null;
            return $profile;
        }, $rows);

        Response::success(['matches' => $matches]);
    }

    public function accountNotifications(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$this->tableExists('social_account_notifications')) {
            Response::success(['notifications' => []]);
        }

        $rows = Database::query(
            "SELECT id, actor_user_id, type, title, body, payload_json, read_at, created_at
               FROM social_account_notifications
              WHERE user_id = :user_id
                AND read_at IS NULL
              ORDER BY created_at DESC
              LIMIT 10",
            [':user_id' => (int)$user->id]
        );

        $notifications = [];
        $staleNotificationIds = [];
        foreach ($rows as $row) {
            $payload = $this->notificationPayload($row);
            $notification = [
                'id' => (int)$row['id'],
                'actor_user_id' => isset($row['actor_user_id']) ? (int)$row['actor_user_id'] : null,
                'type' => (string)$row['type'],
                'title' => (string)$row['title'],
                'body' => (string)$row['body'],
                'payload' => $payload,
                'read_at' => $row['read_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];

            if ($this->isAccountNotificationActionableForUser($notification, (int)$user->id)) {
                $notifications[] = $notification;
            } else {
                $staleNotificationIds[] = (int)$row['id'];
            }
        }

        if (!empty($staleNotificationIds)) {
            $this->markAccountNotificationIdsRead($staleNotificationIds, (int)$user->id);
        }

        Response::success(['notifications' => $notifications]);
    }

    private function notificationPayload(array $row): array
    {
        if (empty($row['payload_json'])) {
            return [];
        }

        $decoded = json_decode((string)$row['payload_json'], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function notificationNumberPayload(array $notification, string $key): ?int
    {
        $payload = $notification['payload'] ?? [];
        if (!is_array($payload) || !array_key_exists($key, $payload)) {
            return null;
        }

        $value = $payload[$key];
        if (is_numeric($value) && (int)$value > 0) {
            return (int)$value;
        }

        return null;
    }

    private function isAccountNotificationActionableForUser(array $notification, int $userId): bool
    {
        if ((string)($notification['type'] ?? '') !== 'social_gift_received') {
            return true;
        }

        $giftId = $this->notificationNumberPayload($notification, 'gift_id');
        if ($giftId === null || !$this->tableExists('social_gift_orders')) {
            return false;
        }

        $gift = Database::queryOne(
            'SELECT id, recipient_user_id, status
               FROM social_gift_orders
              WHERE id = :id AND recipient_user_id = :user_id
              LIMIT 1',
            [
                ':id' => $giftId,
                ':user_id' => $userId,
            ]
        );

        if (!$gift) {
            return false;
        }

        $status = strtolower(trim((string)($gift['status'] ?? '')));
        return $status === '' || in_array($status, ['listo', 'reclamado'], true);
    }

    private function markAccountNotificationIdsRead(array $notificationIds, int $userId): void
    {
        $notificationIds = array_values(array_unique(array_filter(
            array_map('intval', $notificationIds),
            static fn(int $id): bool => $id > 0
        )));
        if (empty($notificationIds) || !$this->tableExists('social_account_notifications')) {
            return;
        }

        $placeholders = [];
        $params = [':user_id' => $userId];
        foreach ($notificationIds as $index => $notificationId) {
            $key = ':notification_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $notificationId;
        }

        Database::rowCount(
            'UPDATE social_account_notifications
                SET read_at = COALESCE(read_at, NOW())
              WHERE user_id = :user_id
                AND id IN (' . implode(',', $placeholders) . ')',
            $params
        );
    }

    public function markAccountNotificationRead(int $notificationId): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$this->tableExists('social_account_notifications')) {
            Response::success(['ok' => true]);
        }

        Database::rowCount(
            'UPDATE social_account_notifications
                SET read_at = COALESCE(read_at, NOW())
              WHERE id = :id AND user_id = :user_id',
            [
                ':id' => $notificationId,
                ':user_id' => (int)$user->id,
            ]
        );

        Response::success(['ok' => true]);
    }

    public function receivedLikes(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!$this->tableExists('social_likes')) {
            Response::success(['likes' => []]);
        }

        $blockedUsersSql = $this->blockedUsersSql('mu.id', ':user_id_blocker', ':user_id_blocked');
        $params = [
            ':user_id' => (int)$user->id,
            ':user_id_for_mine' => (int)$user->id,
        ];
        if ($blockedUsersSql !== '') {
            $params[':user_id_blocker'] = (int)$user->id;
            $params[':user_id_blocked'] = (int)$user->id;
        }

        $rows = Database::query(
            "SELECT mu.id AS user_id, mu.nombre, mu.foto_url, mu.edad, mu.sexualidad, mu.genero,
                    mu.descripcion, mu.intereses, mu.que_busca, mu.redes_sociales,
                    mu.is_social_active, mu.current_restaurante_id, mu.social_updated_at,
                    sl.created_at AS liked_at, sl.restaurante_id AS like_restaurante_id" .
                    ($this->hasSocialPhotosColumn() ? ", mu.social_photos_json" : "") .
                    ($this->hasMesaColumn() ? ", mu.mesa" : "") . "
               FROM social_likes sl
               JOIN mobile_usuarios mu ON mu.id = sl.liker_user_id
              WHERE sl.liked_user_id = :user_id
                AND sl.matched_at IS NULL
                " . $blockedUsersSql . "
                AND NOT EXISTS (
                    SELECT 1
                      FROM social_likes mine
                     WHERE mine.liker_user_id = :user_id_for_mine
                       AND mine.liked_user_id = sl.liker_user_id
                     LIMIT 1
                )
           ORDER BY sl.created_at DESC",
            $params
        );

        $likes = array_map(function (array $row): array {
            $profile = $this->normalizeProfileRow($row, false);
            $profile['relationship_status'] = 'none';
            $profile['liked_at'] = $row['liked_at'] ?? null;
            $profile['like_restaurante_id'] = isset($row['like_restaurante_id']) && $row['like_restaurante_id'] !== null
                ? (int)$row['like_restaurante_id']
                : null;
            return $profile;
        }, $rows);

        Response::success(['likes' => $likes]);
    }

    public function sentLikes(): void
    {
        $user = AuthMiddleware::authenticate();
        if (!$this->tableExists('social_likes')) {
            Response::success(['likes' => []]);
        }

        $blockedUsersSql = $this->blockedUsersSql('mu.id', ':user_id_blocker', ':user_id_blocked');
        $params = [':user_id' => (int)$user->id];
        if ($blockedUsersSql !== '') {
            $params[':user_id_blocker'] = (int)$user->id;
            $params[':user_id_blocked'] = (int)$user->id;
        }

        $rows = Database::query(
            "SELECT mu.id AS user_id, mu.nombre, mu.foto_url, mu.edad, mu.sexualidad, mu.genero,
                    mu.descripcion, mu.intereses, mu.que_busca, mu.redes_sociales,
                    mu.is_social_active, mu.current_restaurante_id, mu.social_updated_at,
                    sl.created_at AS liked_at, sl.restaurante_id AS like_restaurante_id" .
                    ($this->hasSocialPhotosColumn() ? ", mu.social_photos_json" : "") .
                    ($this->hasMesaColumn() ? ", mu.mesa" : "") . "
              FROM social_likes sl
               JOIN mobile_usuarios mu ON mu.id = sl.liked_user_id
              WHERE sl.liker_user_id = :user_id AND sl.matched_at IS NULL
                " . $blockedUsersSql . "
           ORDER BY sl.created_at DESC",
            $params
        );

        $likes = array_map(function (array $row): array {
            $profile = $this->normalizeProfileRow($row, false);
            $profile['relationship_status'] = 'liked';
            $profile['liked_at'] = $row['liked_at'] ?? null;
            $profile['like_restaurante_id'] = isset($row['like_restaurante_id']) && $row['like_restaurante_id'] !== null
                ? (int)$row['like_restaurante_id']
                : null;
            return $profile;
        }, $rows);

        Response::success(['likes' => $likes]);
    }

    public function getProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        $profile = $this->fetchSocialProfile($user->id);

        if (!$profile) {
            Response::notFound('Perfil social no encontrado');
        }

        Response::success($this->normalizeProfileRow($profile));
    }

    public function updateProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        if (empty($input)) {
            Response::error('No se recibieron datos para actualizar el perfil social', 400);
        }

        $updateData = [];

        if (array_key_exists('nombre', $input)) {
            $nombre = $this->sanitizeNullableString($input['nombre']);
            if ($nombre === null) {
                Response::validationError(['nombre' => ['El nombre es obligatorio']]);
            }
            $updateData['nombre'] = substr($nombre, 0, 100);
        }
        if (array_key_exists('edad', $input)) {
            $updateData['edad'] = $input['edad'] !== null && $input['edad'] !== '' ? (int)$input['edad'] : null;
        }
        if (array_key_exists('sexualidad', $input)) {
            $updateData['sexualidad'] = $this->sanitizeNullableString($input['sexualidad']);
        }
        if (array_key_exists('genero', $input)) {
            $updateData['genero'] = $this->sanitizeNullableString($input['genero']);
        }
        if (array_key_exists('descripcion', $input)) {
            $updateData['descripcion'] = $this->sanitizeNullableString($input['descripcion']);
        }
        if (array_key_exists('intereses', $input)) {
            $updateData['intereses'] = $this->sanitizeNullableString($input['intereses']);
        }
        if (array_key_exists('que_busca', $input)) {
            $updateData['que_busca'] = $this->sanitizeNullableString($input['que_busca']);
        }
        if (array_key_exists('redes_sociales', $input)) {
            $updateData['redes_sociales'] = $this->sanitizeNullableString($input['redes_sociales']);
        }

        if (!$this->updateUserAllowingNulls($user->id, $updateData)) {
            Response::serverError('No se pudo actualizar el perfil social');
        }

        $profile = $this->fetchSocialProfile($user->id);
        if (!$profile) {
            Response::notFound('Perfil social no encontrado');
        }

        Response::success($this->normalizeProfileRow($profile), 'Perfil social actualizado');
    }

    public function deleteProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        $profile = $this->fetchSocialProfile($user->id);

        if (!$profile) {
            Response::notFound('Perfil social no encontrado');
        }

        $photos = $this->normalizeSocialPhotos($profile['social_photos_json'] ?? null, $profile['foto_url'] ?? null);
        $updateData = [
            'foto_url' => null,
            'edad' => null,
            'sexualidad' => null,
            'genero' => null,
            'descripcion' => null,
            'intereses' => null,
            'que_busca' => null,
            'redes_sociales' => null,
            'is_social_active' => 0,
            'current_restaurante_id' => null,
        ];

        if ($this->hasSocialPhotosColumn()) {
            $updateData['social_photos_json'] = null;
        }
        if ($this->hasMesaColumn()) {
            $updateData['mesa'] = null;
        }
        if ($this->hasSocialConsentColumns()) {
            $updateData['social_consent_accepted_at'] = null;
            $updateData['social_consent_version'] = null;
        }

        if (!$this->updateUserAllowingNulls($user->id, $updateData)) {
            Response::serverError('No se pudo eliminar el perfil social');
        }

        foreach ($photos as $photoUrl) {
            ImageUploadHelper::deleteLocalUploadFromUrl(
                $photoUrl,
                __DIR__ . '/../../uploads/',
                'social-' . $user->id . '-'
            );
        }

        $deletedProfile = $this->fetchSocialProfile($user->id);
        if (!$deletedProfile) {
            Response::notFound('Perfil social no encontrado');
        }

        Response::success($this->normalizeProfileRow($deletedProfile), 'Perfil social eliminado');
    }

    public function uploadPhoto(): void
    {
        $user = AuthMiddleware::authenticate();

        $file = null;
        if (isset($_FILES['photo'])) {
            $file = $_FILES['photo'];
        } elseif (isset($_FILES['file'])) {
            $file = $_FILES['file'];
        }

        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('No se recibió ninguna imagen', 400);
        }

        try {
            ImageUploadHelper::inspectUploadedImage(
                $file,
                ['image/jpeg', 'image/png', 'image/webp'],
                8 * 1024 * 1024,
                240,
                240
            );
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        }
        if (!$this->hasSocialPhotosColumn()) {
            Response::serverError('La base de datos aún no tiene social_photos_json. Ejecuta la migración 023.');
        }

        $profile = $this->fetchSocialProfile($user->id);
        if (!$profile) {
            Response::notFound('Perfil social no encontrado');
        }

        $currentPhotos = $this->normalizeSocialPhotos($profile['social_photos_json'] ?? null, $profile['foto_url'] ?? null);
        if (count($currentPhotos) >= self::MAX_SOCIAL_PHOTOS) {
            Response::error('Solo puedes tener hasta ' . self::MAX_SOCIAL_PHOTOS . ' fotos en tu perfil social.', 409);
        }

        $uploadDir = __DIR__ . '/../../uploads/social/';
        try {
            $filename = ImageUploadHelper::saveCompressedUpload(
                $file,
                $uploadDir,
                'social-' . $user->id . '-' . time() . '-' . count($currentPhotos),
                1280,
                1280,
                78
            );
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            Response::serverError($exception->getMessage());
        }

        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://amarerestaurant.club/api_restaurante', '/');
        $fotoUrl = $baseUrl . '/uploads/social/' . $filename;
        $photos = $this->uniquePhotoList(array_merge($currentPhotos, [$fotoUrl]));
        $primaryPhoto = $photos[0] ?? $fotoUrl;

        if (!$this->updateUserAllowingNulls($user->id, [
            'foto_url' => $primaryPhoto,
            'social_photos_json' => json_encode($photos, JSON_UNESCAPED_SLASHES),
        ])) {
            ImageUploadHelper::deleteLocalUploadFromUrl($fotoUrl, __DIR__ . '/../../uploads/', 'social-' . $user->id . '-');
            Response::serverError('No se pudo actualizar la foto del perfil social');
        }

        Response::success([
            'foto_url' => $primaryPhoto,
            'social_photos' => $photos,
            'uploaded_photo_url' => $fotoUrl,
        ]);
    }

    public function deletePhoto(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $photoUrl = $this->sanitizeNullableString($input['photo_url'] ?? $input['url'] ?? null);

        if ($photoUrl === null) {
            Response::validationError(['photo_url' => ['La foto es obligatoria']]);
        }

        if (!$this->hasSocialPhotosColumn()) {
            Response::serverError('La base de datos aún no tiene social_photos_json. Ejecuta la migración 023.');
        }

        $profile = $this->fetchSocialProfile($user->id);
        if (!$profile) {
            Response::notFound('Perfil social no encontrado');
        }

        $currentPhotos = $this->normalizeSocialPhotos($profile['social_photos_json'] ?? null, $profile['foto_url'] ?? null);
        $photos = array_values(array_filter(
            $currentPhotos,
            fn(string $photo): bool => !$this->photoMatches($photo, $photoUrl)
        ));

        if (count($photos) === count($currentPhotos)) {
            Response::notFound('Foto no encontrada en tu perfil social');
        }

        $primaryPhoto = $photos[0] ?? null;
        if (!$this->updateUserAllowingNulls($user->id, [
            'foto_url' => $primaryPhoto,
            'social_photos_json' => !empty($photos) ? json_encode($photos, JSON_UNESCAPED_SLASHES) : null,
        ])) {
            Response::serverError('No se pudo eliminar la foto del perfil social');
        }

        ImageUploadHelper::deleteLocalUploadFromUrl(
            $photoUrl,
            __DIR__ . '/../../uploads/',
            'social-' . $user->id . '-'
        );

        Response::success([
            'foto_url' => $primaryPhoto,
            'social_photos' => $photos,
        ], 'Foto eliminada');
    }

    public function setPrimaryPhoto(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $photoUrl = $this->sanitizeNullableString($input['photo_url'] ?? $input['url'] ?? null);

        if ($photoUrl === null) {
            Response::validationError(['photo_url' => ['La foto es obligatoria']]);
        }

        if (!$this->hasSocialPhotosColumn()) {
            Response::serverError('La base de datos aún no tiene social_photos_json. Ejecuta la migración 023.');
        }

        $profile = $this->fetchSocialProfile($user->id);
        if (!$profile) {
            Response::notFound('Perfil social no encontrado');
        }

        $photos = $this->normalizeSocialPhotos($profile['social_photos_json'] ?? null, $profile['foto_url'] ?? null);
        $matchedPhoto = null;
        foreach ($photos as $photo) {
            if ($this->photoMatches($photo, $photoUrl)) {
                $matchedPhoto = $photo;
                break;
            }
        }

        if ($matchedPhoto === null) {
            Response::notFound('Foto no encontrada en tu perfil social');
        }

        $orderedPhotos = $this->uniquePhotoList(array_merge(
            [$matchedPhoto],
            array_values(array_filter($photos, fn(string $photo): bool => !$this->photoMatches($photo, $matchedPhoto)))
        ));

        if (!$this->updateUserAllowingNulls($user->id, [
            'foto_url' => $orderedPhotos[0] ?? null,
            'social_photos_json' => json_encode($orderedPhotos, JSON_UNESCAPED_SLASHES),
        ])) {
            Response::serverError('No se pudo actualizar la foto principal');
        }

        Response::success([
            'foto_url' => $orderedPhotos[0] ?? null,
            'social_photos' => $orderedPhotos,
        ], 'Foto principal actualizada');
    }

    public function publicProfile(int $userId): void
    {
        $user = AuthMiddleware::authenticate();
        if ($this->isBlockedBetween((int)$user->id, $userId)) {
            Response::notFound('Usuario no encontrado o sin perfil social publico');
        }

        $profile = Database::queryOne(
            "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero, descripcion, intereses, que_busca, redes_sociales" . ($this->hasSocialPhotosColumn() ? ", social_photos_json" : "") . ($this->hasMesaColumn() ? ", mesa" : "") . "
               FROM mobile_usuarios
              WHERE id = :id
                AND is_social_active = 1",
            [':id' => $userId]
        );

        if (!$profile) {
            Response::notFound('Usuario no encontrado o sin perfil social publico');
        }

        $result = $this->normalizeProfileRow($profile, false);
        $result['relationship_status'] = $this->getRelationshipStatus((int)$user->id, (int)$result['user_id']);

        Response::success($result);
    }

    public function dinerAccount(int $targetUserId): void
    {
        $user = AuthMiddleware::authenticate();
        $restaurantId = (int)($_GET['restaurant_id'] ?? 0);
        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }

        [$sender, $senderMesaLabel, $senderMesa, $recipient, $recipientMesaLabel, $recipientMesa] = $this->resolveSocialCoverContext(
            (int)$user->id,
            $targetUserId,
            $restaurantId
        );

        $consumption = $this->findOpenDinerConsumption($restaurantId, $targetUserId, (int)$recipientMesa['id']);
        if ($consumption === null) {
            Response::success([
                'available' => false,
                'recipient' => [
                    'user_id' => $targetUserId,
                    'nombre' => $recipient['nombre'] ?? 'Comensal',
                    'mesa' => $recipientMesaLabel,
                ],
                'message' => 'Este comensal no tiene consumo pendiente por cubrir.',
            ]);
        }

        Response::success([
            'available' => true,
            'recipient' => [
                'user_id' => $targetUserId,
                'nombre' => $recipient['nombre'] ?? 'Comensal',
                'mesa' => $recipientMesaLabel,
            ],
            'payer' => [
                'user_id' => (int)$user->id,
                'nombre' => $sender['nombre'] ?? 'Comensal',
            ],
            'account' => $this->socialConsumptionSummary($consumption),
        ]);
    }

    public function coverDinerAccount(int $targetUserId): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $paymentMode = strtolower(trim((string)($input['payment_mode'] ?? 'account')));
        $requestKey = trim((string)($input['request_key'] ?? ''));
        $usePoints = false;

        $errors = [];
        if ($restaurantId <= 0) $errors['restaurant_id'] = ['Selecciona una sucursal valida'];
        if ($targetUserId <= 0) $errors['recipient_user_id'] = ['Selecciona un comensal valido'];
        if ($targetUserId === (int)$user->id) $errors['recipient_user_id'] = ['No puedes cubrir tu propia cuenta desde social'];
        if (!in_array($paymentMode, ['account', 'stripe', 'wallet'], true)) $errors['payment_mode'] = ['Selecciona una forma de pago valida'];
        if (!preg_match('/^[A-Za-z0-9_-]{16,80}$/', $requestKey)) $errors['request_key'] = ['La clave de solicitud no es valida'];
        if ($errors) Response::validationError($errors);

        $this->ensureSocialAccountCoverTables();

        [$sender, $senderMesaLabel, $senderMesa, $recipient, $recipientMesaLabel, $recipientMesa] =
            $this->resolveSocialCoverContext((int)$user->id, $targetUserId, $restaurantId);

        $consumption = $this->findOpenDinerConsumption($restaurantId, $targetUserId, (int)$recipientMesa['id']);
        if ($consumption === null) {
            Response::error('Este comensal no tiene consumo pendiente por cubrir.', 409);
        }

        $summary = $this->socialConsumptionSummary($consumption);
        if ((float)$summary['total_mxn'] <= 0) {
            Response::error('La cuenta del comensal no tiene importe pendiente.', 409);
        }
        $walletQuote = null;
        if ($paymentMode === 'wallet') {
            $walletQuote = (new RewardsService())->quote((int)$user->id, (float)$summary['total_mxn'], $usePoints, 'food', [], 'wallet');
            if (empty($walletQuote['can_pay'])) {
                Response::error('Tu Saldo Amare no alcanza para enviar esta solicitud de pago.', 409);
            }
        }

        $existing = Database::queryOne(
            'SELECT * FROM social_account_covers WHERE payer_user_id = :payer_id AND payment_request_key = :request_key LIMIT 1',
            [':payer_id' => (int)$user->id, ':request_key' => $requestKey]
        );
        if ($existing) {
            if ((int)$existing['covered_user_id'] !== $targetUserId || (int)$existing['restaurante_id'] !== $restaurantId) {
                Response::error('La clave de solicitud ya pertenece a otra cuenta social.', 409);
            }
            if (in_array((string)$existing['status'], ['pending_approval', 'approved', 'pending_payment'], true)) {
                Response::success([
                    'cover' => $this->socialAccountCoverResponse($existing),
                    'account' => $summary,
                    'approval_required' => true,
                ], 'La solicitud ya fue enviada.');
            }
            if ($paymentMode === 'account' && $existing['status'] === 'charged_to_account') {
                Response::success([
                    'cover' => $this->socialAccountCoverResponse($existing),
                    'account' => $summary,
                    'charged_to_account' => true,
                ], 'La cuenta ya fue agregada a tu consumo.');
            }
        }

        $activeCover = $this->findActiveSocialAccountCover(
            $restaurantId,
            $targetUserId,
            (string)$consumption['consumo_id']
        );
        if ($activeCover && (!$existing || (int)$activeCover['id'] !== (int)$existing['id'])) {
            Response::error('Esta cuenta ya fue cubierta o esta en proceso de pago.', 409, 'SOCIAL_ACCOUNT_ALREADY_COVERED');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $activeCover = $this->findActiveSocialAccountCover(
                $restaurantId,
                $targetUserId,
                (string)$consumption['consumo_id'],
                true
            );
            if ($activeCover && (!$existing || (int)$activeCover['id'] !== (int)$existing['id'])) {
                throw new \DomainException('Esta cuenta ya fue cubierta o esta en proceso de pago.');
            }

            $cover = $existing ?: $this->createSocialAccountCoverRecord(
                $restaurantId,
                (int)$user->id,
                $targetUserId,
                (int)$senderMesa['id'],
                (int)$recipientMesa['id'],
                $senderMesaLabel,
                $recipientMesaLabel,
                $consumption['consumo_id'],
                $paymentMode,
                'pending_approval',
                (float)$summary['total_mxn'],
                (int)$summary['items_count'],
                $requestKey,
                null,
                $this->buildCoverRequestMessage((string)($sender['nombre'] ?? 'Alguien'), (float)$summary['total_mxn'], $paymentMode),
                $pdo
            );

            $this->recordSocialAccountNotification(
                $pdo,
                $targetUserId,
                (int)$user->id,
                'social_account_cover_request',
                'Quieren pagar tu cuenta',
                $this->buildCoverRequestMessage((string)($sender['nombre'] ?? 'Alguien'), (float)$summary['total_mxn'], $paymentMode),
                [
                    'cover_id' => (int)$cover['id'],
                    'restaurant_id' => $restaurantId,
                    'payer_user_id' => (int)$user->id,
                    'payer_name' => (string)($sender['nombre'] ?? 'Alguien'),
                    'payment_mode' => $paymentMode,
                    'amount_mxn' => (float)$summary['total_mxn'],
                    'covered_mesa' => $recipientMesaLabel,
                    'wallet_quote' => $walletQuote,
                    'use_points' => $usePoints,
                ]
            );
            $pdo->commit();

            Response::success([
                'cover' => $this->socialAccountCoverResponse($cover),
                'account' => $summary,
                'approval_required' => true,
                'wallet_quote' => $walletQuote,
            ], 'Solicitud enviada. El comensal debe aceptar antes de cobrar.', 201);
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SocialController::coverDinerAccount ACCOUNT ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo agregar la cuenta del comensal a tu consumo.');
        }
    }

    public function respondAccountCoverRequest(int $coverId): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = strtolower(trim((string)($input['action'] ?? '')));
        if (!in_array($action, ['accept', 'approve', 'reject', 'decline'], true)) {
            Response::validationError(['action' => ['Elige aceptar o rechazar la solicitud']]);
        }

        $this->ensureSocialAccountCoverTables();
        $cover = Database::queryOne(
            'SELECT * FROM social_account_covers WHERE id = :id AND covered_user_id = :user_id LIMIT 1',
            [':id' => $coverId, ':user_id' => (int)$user->id]
        );
        if (!$cover) Response::notFound('Solicitud de cobertura no encontrada');
        if (!in_array((string)$cover['status'], ['pending_approval', 'approved'], true)) {
            Response::error('Esta solicitud ya fue respondida.', 409);
        }

        $accept = in_array($action, ['accept', 'approve'], true);
        $payer = $this->fetchSocialProfile((int)$cover['payer_user_id']) ?? [];
        $recipient = $this->fetchSocialProfile((int)$cover['covered_user_id']) ?? [];

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            if (!$accept) {
                $this->updateSocialAccountCover($pdo, $coverId, ['status' => 'rejected']);
                $this->recordSocialAccountNotification(
                    $pdo,
                    (int)$cover['payer_user_id'],
                    (int)$user->id,
                    'social_account_cover_rejected',
                    'Solicitud rechazada',
                    (string)($recipient['nombre'] ?? 'El comensal') . ' rechazo que cubrieras su cuenta.',
                    [
                        'cover_id' => $coverId,
                        'amount_mxn' => (float)$cover['amount_mxn'],
                        'payment_mode' => (string)$cover['payment_mode'],
                    ]
                );
                $pdo->commit();
                $cover['status'] = 'rejected';
                Response::success(['cover' => $this->socialAccountCoverResponse($cover)], 'Solicitud rechazada.');
            }

            $consumption = $this->findConsumptionById(
                (int)$cover['restaurante_id'],
                (int)$cover['covered_user_id'],
                (string)$cover['covered_consumo_id']
            );
            if ($consumption === null) {
                throw new \DomainException('La cuenta original ya no esta disponible.');
            }

            $paymentMode = (string)$cover['payment_mode'];
            if ($paymentMode === 'stripe') {
                $this->updateSocialAccountCover($pdo, $coverId, ['status' => 'approved']);
                $this->recordSocialAccountNotification(
                    $pdo,
                    (int)$cover['payer_user_id'],
                    (int)$user->id,
                    'social_account_cover_approved',
                    'Solicitud aceptada',
                    (string)($recipient['nombre'] ?? 'El comensal') . ' acepto que pagues su cuenta. Termina el pago con tarjeta.',
                    [
                        'cover_id' => $coverId,
                        'restaurant_id' => (int)$cover['restaurante_id'],
                        'covered_user_id' => (int)$cover['covered_user_id'],
                        'covered_name' => (string)($recipient['nombre'] ?? 'Comensal'),
                        'payment_mode' => 'stripe',
                        'amount_mxn' => (float)$cover['amount_mxn'],
                    ]
                );
                $pdo->commit();
                $cover['status'] = 'approved';
                Response::success(['cover' => $this->socialAccountCoverResponse($cover)], 'Aceptaste la solicitud. Le avisamos para terminar el pago.');
            }

            if ($paymentMode === 'wallet') {
                $reward = (new RewardsService())->charge(
                    $pdo,
                    (int)$cover['payer_user_id'],
                    (float)$cover['amount_mxn'],
                    false,
                    'food',
                    'social_account_cover',
                    $coverId,
                    'Pago social de cuenta con Saldo Amare'
                );
                $paymentResult = $this->processDirectSocialCoverPayment($pdo, $cover, $consumption, 'social_cover');
                $this->updateSocialAccountCover($pdo, $coverId, [
                    'status' => 'paid',
                    'paid_at' => date('Y-m-d H:i:s'),
                ]);
                $payload = [
                    'cover_id' => $coverId,
                    'amount_mxn' => (float)$cover['amount_mxn'],
                    'payment_mode' => 'wallet',
                    'covered_order_id' => $paymentResult['covered_order_id'],
                    'covered_visit_id' => $paymentResult['covered_visit_id'],
                    'post_payment_action_required' => true,
                    'wallet' => $reward,
                ];
                $this->recordSocialAccountNotification(
                    $pdo,
                    (int)$cover['covered_user_id'],
                    (int)$cover['payer_user_id'],
                    'social_account_paid',
                    'Tu cuenta fue pagada',
                    $this->buildCoverMessage((string)($payer['nombre'] ?? 'Alguien'), (float)$cover['amount_mxn'], 'wallet'),
                    $payload
                );
                $this->recordSocialAccountNotification(
                    $pdo,
                    (int)$cover['payer_user_id'],
                    (int)$cover['covered_user_id'],
                    'social_account_cover_paid',
                    'Cuenta pagada',
                    'El comensal acepto y pagaste su cuenta con Saldo Amare.',
                    [
                        'cover_id' => $coverId,
                        'amount_mxn' => (float)$cover['amount_mxn'],
                        'payment_mode' => 'wallet',
                    ]
                );
                $pdo->commit();

                $cover['status'] = 'paid';
                $cover['paid_at'] = date('Y-m-d H:i:s');
                Response::success([
                    'cover' => $this->socialAccountCoverResponse($cover),
                    'covered_order_id' => $paymentResult['covered_order_id'],
                    'covered_visit_id' => $paymentResult['covered_visit_id'],
                    'post_payment_action_required' => true,
                    'wallet' => $reward,
                ], 'Aceptaste la solicitud. Tu cuenta fue pagada.');
            }

            $payerOrderId = $this->createCoveredConsumptionOrder(
                $pdo,
                (int)$cover['restaurante_id'],
                (int)$cover['payer_user_id'],
                (string)($payer['nombre'] ?? 'Comensal'),
                (int)($cover['payer_mesa_id'] ?? 0),
                (string)($cover['payer_mesa'] ?? ''),
                $recipient,
                (string)($cover['covered_mesa'] ?? ''),
                $consumption,
                true,
                null,
                null
            );
            $this->markConsumptionCovered($pdo, $consumption, 'social_cover');
            $accountCoverVisit = $this->markSocialCoverTicketsPaid($pdo, $consumption, 'social_cover');
            $pendingGiftCharges = $this->pendingSocialGiftChargesForConsumption(
                $consumption,
                (int)$cover['covered_user_id'],
                (int)$cover['restaurante_id']
            );
            $coveredExitPass = null;
            $coveredOrderId = (int)($consumption['order_ids'][0] ?? 0);
            $this->updateSocialAccountCover($pdo, $coverId, [
                'payer_pedido_id' => $payerOrderId,
                'status' => 'charged_to_account',
            ]);
            $message = $this->buildCoverMessage((string)($payer['nombre'] ?? 'Alguien'), (float)$cover['amount_mxn'], 'account');
            $payload = [
                'cover_id' => $coverId,
                'amount_mxn' => (float)$cover['amount_mxn'],
                'payment_mode' => 'account',
                'exit_pass' => $coveredExitPass,
                'covered_order_id' => $coveredOrderId,
                'covered_visit_id' => $accountCoverVisit['visit_id'],
                'post_payment_action_required' => true,
                'pending_social_gifts' => $pendingGiftCharges,
            ];
            $this->recordSocialAccountNotification(
                $pdo,
                (int)$cover['covered_user_id'],
                (int)$cover['payer_user_id'],
                'social_account_covered',
                'Cuenta cubierta',
                $message,
                $payload
            );
            $this->recordSocialAccountNotification(
                $pdo,
                (int)$cover['payer_user_id'],
                (int)$cover['covered_user_id'],
                'social_account_cover_approved',
                'Solicitud aceptada',
                'La cuenta se agrego a tu consumo.',
                [
                    'cover_id' => $coverId,
                    'amount_mxn' => (float)$cover['amount_mxn'],
                    'payment_mode' => 'account',
                ]
            );
            $pdo->commit();

            $cover['payer_pedido_id'] = $payerOrderId;
            $cover['status'] = 'charged_to_account';
            Response::success([
                'cover' => $this->socialAccountCoverResponse($cover),
                'covered_exit_pass' => $coveredExitPass,
                'covered_order_id' => $coveredOrderId,
                'covered_visit_id' => $accountCoverVisit['visit_id'],
                'post_payment_action_required' => true,
                'pending_social_gifts' => $pendingGiftCharges,
            ], 'Aceptaste la solicitud. Tu cuenta fue cubierta.');
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SocialController::respondAccountCoverRequest ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo responder la solicitud.');
        }
    }

    public function prepareAccountCoverPayment(int $coverId): void
    {
        $user = AuthMiddleware::authenticate();
        $this->ensureSocialAccountCoverTables();

        $cover = Database::queryOne(
            'SELECT * FROM social_account_covers WHERE id = :id AND payer_user_id = :payer_id LIMIT 1',
            [':id' => $coverId, ':payer_id' => (int)$user->id]
        );
        if (!$cover) Response::notFound('Cobertura social no encontrada');
        if ((string)$cover['payment_mode'] !== 'stripe') Response::error('Esta cobertura no requiere Stripe.', 409);
        if ((string)$cover['status'] !== 'approved' && (string)$cover['status'] !== 'pending_payment') {
            Response::error('El comensal debe aceptar la solicitud antes de pagar.', 409);
        }

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intentId = (string)($cover['stripe_payment_intent_id'] ?? '');
            $intent = null;
            if ($intentId !== '') {
                try {
                    $intent = PaymentIntent::retrieve($intentId);
                } catch (\Stripe\Exception\InvalidRequestException $exception) {
                    if (stripos((string)$exception->getMessage(), 'No such payment_intent') === false) {
                        throw $exception;
                    }
                    $intentId = '';
                }
            }
            if ($intent === null) {
                $intent = PaymentIntent::create([
                    'amount' => (int)round((float)$cover['amount_mxn'] * 100),
                    'currency' => 'mxn',
                    'description' => 'Cuenta social cubierta',
                    'metadata' => [
                        'social_account_cover_id' => (string)$cover['id'],
                        'payer_user_id' => (string)$user->id,
                        'covered_user_id' => (string)$cover['covered_user_id'],
                    ],
                    'automatic_payment_methods' => ['enabled' => true],
                ], ['idempotency_key' => 'social_account_cover_' . (string)$cover['payment_request_key']]);
            }

            Database::rowCount(
                "UPDATE social_account_covers
                    SET stripe_payment_intent_id = :intent_id, status = 'pending_payment', updated_at = NOW()
                  WHERE id = :id",
                [':intent_id' => $intent->id, ':id' => $coverId]
            );
            $cover['stripe_payment_intent_id'] = $intent->id;
            $cover['status'] = 'pending_payment';

            Response::success([
                'cover' => $this->socialAccountCoverResponse($cover),
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
            ], 'Pago de cuenta preparado.');
        } catch (\Throwable $exception) {
            error_log('SocialController::prepareAccountCoverPayment STRIPE ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo iniciar el pago de la cuenta.');
        }
    }

    public function confirmAccountCoverPayment(int $coverId): void
    {
        $user = AuthMiddleware::authenticate();
        $this->ensureSocialAccountCoverTables();

        $cover = Database::queryOne(
            'SELECT * FROM social_account_covers WHERE id = :id AND payer_user_id = :payer_id LIMIT 1',
            [':id' => $coverId, ':payer_id' => (int)$user->id]
        );
        if (!$cover) Response::notFound('Cobertura social no encontrada');
        if ((string)$cover['payment_mode'] !== 'stripe') Response::error('Esta cobertura no requiere confirmacion Stripe.', 409);
        if ((string)$cover['status'] === 'paid') {
            Response::success(['cover' => $this->socialAccountCoverResponse($cover)], 'Cuenta ya pagada.');
        }
        if ((string)$cover['status'] !== 'pending_payment') {
            Response::error('El comensal debe aceptar la solicitud antes de confirmar el pago.', 409);
        }

        $intentId = (string)($cover['stripe_payment_intent_id'] ?? '');
        if ($intentId === '') Response::error('La cobertura no tiene intento de pago.', 409);

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intent = PaymentIntent::retrieve($intentId);
            $expectedCents = (int)round((float)$cover['amount_mxn'] * 100);
            if (
                (int)$intent->amount !== $expectedCents ||
                strtolower((string)$intent->currency) !== 'mxn' ||
                (int)($intent->metadata['social_account_cover_id'] ?? 0) !== $coverId ||
                (int)($intent->metadata['payer_user_id'] ?? 0) !== (int)$user->id
            ) {
                Response::error('La informacion del pago no coincide con la cuenta social.', 409);
            }
            if ($intent->status !== 'succeeded') {
                Response::error('Stripe aun no confirma el pago de la cuenta.', 409);
            }
            if ((bool)$intent->livemode !== StripeConfig::isLiveMode()) {
                Response::error('El entorno del pago no coincide con el servidor.', 409);
            }
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            if (stripos((string)$exception->getMessage(), 'No such payment_intent') !== false) {
                Response::error('Stripe no encuentra este intento. Reabre la accion e intenta de nuevo.', 409);
            }
            error_log('SocialController::confirmAccountCoverPayment STRIPE ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo verificar el pago con Stripe.');
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('SocialController::confirmAccountCoverPayment STRIPE ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo verificar el pago con Stripe.');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $recipient = $this->fetchSocialProfile((int)$cover['covered_user_id']) ?? [];
            $consumption = $this->findConsumptionById(
                (int)$cover['restaurante_id'],
                (int)$cover['covered_user_id'],
                (string)$cover['covered_consumo_id']
            );
            if ($consumption === null) {
                throw new \DomainException('La cuenta original ya no esta disponible.');
            }
            $activeCover = $this->findActiveSocialAccountCover(
                (int)$cover['restaurante_id'],
                (int)$cover['covered_user_id'],
                (string)$cover['covered_consumo_id'],
                true
            );
            if ($activeCover && (int)$activeCover['id'] !== $coverId) {
                throw new \DomainException('Esta cuenta ya fue cubierta por otra solicitud.');
            }

            $paymentResult = $this->processDirectSocialCoverPayment($pdo, $cover, $consumption, 'social_cover');
            $pendingGiftCharges = $this->pendingSocialGiftChargesForConsumption(
                $consumption,
                (int)$cover['covered_user_id'],
                (int)$cover['restaurante_id']
            );
            $coveredExitPass = null;
            $coveredOrderId = (int)$paymentResult['covered_order_id'];
            $this->updateSocialAccountCover($pdo, $coverId, [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
            $this->recordSocialAccountNotification(
                $pdo,
                (int)$cover['covered_user_id'],
                (int)$user->id,
                'social_account_paid',
                'Cuenta pagada',
                $this->buildCoverMessage((string)($user->nombre ?? 'Alguien'), (float)$cover['amount_mxn'], 'stripe'),
                [
                    'cover_id' => $coverId,
                    'amount_mxn' => (float)$cover['amount_mxn'],
                    'exit_pass' => $coveredExitPass,
                    'covered_order_id' => $coveredOrderId,
                    'covered_visit_id' => $paymentResult['covered_visit_id'],
                    'payment_mode' => 'stripe',
                    'post_payment_action_required' => true,
                    'pending_social_gifts' => $pendingGiftCharges,
                ]
            );
            $pdo->commit();

            $cover['status'] = 'paid';
            $cover['paid_at'] = date('Y-m-d H:i:s');
            Response::success([
                'cover' => $this->socialAccountCoverResponse($cover),
                'covered_exit_pass' => $coveredExitPass,
                'covered_order_id' => $coveredOrderId,
                'covered_visit_id' => $paymentResult['covered_visit_id'],
                'post_payment_action_required' => true,
                'pending_social_gifts' => $pendingGiftCharges,
            ], 'Cuenta pagada y comensal avisado.');
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SocialController::confirmAccountCoverPayment ERROR: ' . $exception->getMessage());
            Response::serverError('Stripe cobro la cuenta, pero no pudimos registrar la cobertura.');
        }
    }

    public function reconcileAccountCoverFromWebhook(int $coverId, int $payerUserId): void
    {
        $this->ensureSocialAccountCoverTables();
        $cover = Database::queryOne(
            'SELECT * FROM social_account_covers WHERE id = :id AND payer_user_id = :payer_id LIMIT 1',
            [':id' => $coverId, ':payer_id' => $payerUserId]
        );
        if (!$cover || (string)($cover['payment_mode'] ?? '') !== 'stripe') return;
        if ((string)($cover['status'] ?? '') === 'paid') return;
        if ((string)($cover['status'] ?? '') !== 'pending_payment') {
            throw new \DomainException('La cobertura social no esta lista para pago.');
        }

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $locked = Database::queryOne(
                'SELECT * FROM social_account_covers WHERE id = :id AND payer_user_id = :payer_id LIMIT 1 FOR UPDATE',
                [':id' => $coverId, ':payer_id' => $payerUserId]
            );
            if (!$locked || (string)$locked['status'] === 'paid') {
                $pdo->commit();
                return;
            }

            $consumption = $this->findConsumptionById(
                (int)$locked['restaurante_id'],
                (int)$locked['covered_user_id'],
                (string)$locked['covered_consumo_id']
            );
            if ($consumption === null) throw new \DomainException('La cuenta original ya no esta disponible.');

            $activeCover = $this->findActiveSocialAccountCover(
                (int)$locked['restaurante_id'],
                (int)$locked['covered_user_id'],
                (string)$locked['covered_consumo_id'],
                true
            );
            if ($activeCover && (int)$activeCover['id'] !== $coverId) {
                throw new \DomainException('Esta cuenta ya fue cubierta por otra solicitud.');
            }

            $paymentResult = $this->processDirectSocialCoverPayment($pdo, $locked, $consumption, 'social_cover');
            $pendingGiftCharges = $this->pendingSocialGiftChargesForConsumption(
                $consumption,
                (int)$locked['covered_user_id'],
                (int)$locked['restaurante_id']
            );
            $payer = $this->fetchSocialProfile($payerUserId) ?? [];
            $this->updateSocialAccountCover($pdo, $coverId, [
                'status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);
            $this->recordSocialAccountNotification(
                $pdo,
                (int)$locked['covered_user_id'],
                $payerUserId,
                'social_account_paid',
                'Cuenta pagada',
                $this->buildCoverMessage((string)($payer['nombre'] ?? 'Alguien'), (float)$locked['amount_mxn'], 'stripe'),
                [
                    'cover_id' => $coverId,
                    'amount_mxn' => (float)$locked['amount_mxn'],
                    'covered_order_id' => (int)$paymentResult['covered_order_id'],
                    'covered_visit_id' => $paymentResult['covered_visit_id'],
                    'payment_mode' => 'stripe',
                    'post_payment_action_required' => true,
                    'pending_social_gifts' => $pendingGiftCharges,
                ]
            );
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function giftProducts(): void
    {
        $restaurantId = (int)($_GET['restaurant_id'] ?? 0);
        $tableName = $this->detectGiftProductsTable();

        $result = [];
        if ($tableName !== null) {
            $productColumns = $this->getTableColumns($tableName);
            $categorySelect = in_array('categoria', $productColumns, true) ? ', categoria' : '';
            $products = Database::query(
                "SELECT id, nombre, descripcion, precio, icono, color, es_regalo, imagen, orden{$categorySelect}
                   FROM {$tableName}
               ORDER BY orden ASC, nombre ASC"
            );

            $result = array_map(
                static function (array $item): array {
                    return [
                        'id' => (int)$item['id'],
                        'tipo' => 'gift',
                        'nombre' => $item['nombre'],
                        'descripcion' => $item['descripcion'] ?? null,
                        'precio' => (float)($item['precio'] ?? 0),
                        'icono' => $item['icono'] ?? null,
                        'color' => $item['color'] ?? '#B71C1C',
                        'es_regalo' => (bool)($item['es_regalo'] ?? true),
                        'categoria' => $item['categoria'] ?? null,
                        'imagen' => $item['imagen'] ?? null,
                        'orden' => (int)($item['orden'] ?? 0),
                    ];
                },
                $products
            );
        }

        if (empty($result) && $restaurantId > 0) {
            $result = $this->menuGiftProducts($restaurantId);
        }

        Response::success($result);
    }

    public function sendGift(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];

        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;
        $giftProductId = isset($input['gift_product_id']) ? (int)$input['gift_product_id'] : 0;
        $recipientUserId = isset($input['recipient_user_id']) ? (int)$input['recipient_user_id'] : 0;

        $errors = [];

        if ($restaurantId <= 0) {
            $errors['restaurant_id'] = ['Selecciona una sucursal válida'];
        }
        if ($giftProductId <= 0) {
            $errors['gift_product_id'] = ['Selecciona un regalo válido'];
        }
        if ($recipientUserId <= 0) {
            $errors['recipient_user_id'] = ['Selecciona un comensal válido'];
        }
        if ($recipientUserId === (int)$user->id) {
            $errors['recipient_user_id'] = ['No puedes enviarte un regalo a ti mismo'];
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        if (!$this->tableExists('social_gift_orders')) {
            Response::serverError('La tabla social_gift_orders aún no existe. Ejecuta primero la migración de regalos sociales.');
        }

        $sender = $this->fetchSocialProfile((int)$user->id);
        if (!$sender || !$this->hasSocialProfile($sender)) {
            Response::error('Completa tu perfil social antes de enviar regalos.', 400);
        }

        $senderRestaurantId = isset($sender['current_restaurante_id']) && $sender['current_restaurante_id'] !== null
            ? (int)$sender['current_restaurante_id']
            : 0;
        $senderMesa = $this->sanitizeNullableString($sender['mesa'] ?? null);

        if (!(bool)($sender['is_social_active'] ?? false) || $senderRestaurantId !== $restaurantId) {
            Response::error('Activa tu modo social en la sucursal actual antes de enviar regalos.', 400);
        }

        if ($senderMesa === null) {
            Response::error('Necesitas seleccionar tu mesa antes de enviar un regalo.', 400);
        }

        $recipient = $this->fetchSocialProfile($recipientUserId);
        if (
            !$recipient ||
            !(bool)($recipient['is_social_active'] ?? false) ||
            (int)($recipient['current_restaurante_id'] ?? 0) !== $restaurantId
        ) {
            Response::error('Este comensal ya no está disponible en la sucursal seleccionada.', 409);
        }

        $recipientMesa = $this->sanitizeNullableString($recipient['mesa'] ?? null);
        if ($recipientMesa === null) {
            Response::error('No pudimos ubicar la mesa del comensal seleccionado.', 409);
        }

        $mesa = $this->resolveMesaForRestaurant($restaurantId, $recipientMesa);
        if ($mesa === null) {
            Response::error('No encontramos la mesa del comensal en la sucursal actual.', 409);
        }

        $giftProduct = $this->findGiftProduct($giftProductId);
        if ($giftProduct === null) {
            Response::notFound('Regalo no encontrado');
        }

        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            $giftOrderId = Database::execute(
                "INSERT INTO social_gift_orders (
                    restaurante_id,
                    mesa_id,
                    gift_product_id,
                    sender_user_id,
                    recipient_user_id,
                    sender_nombre,
                    recipient_nombre,
                    sender_mesa,
                    recipient_mesa,
                    gift_nombre,
                    gift_descripcion,
                    gift_precio,
                    gift_imagen,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :restaurante_id,
                    :mesa_id,
                    :gift_product_id,
                    :sender_user_id,
                    :recipient_user_id,
                    :sender_nombre,
                    :recipient_nombre,
                    :sender_mesa,
                    :recipient_mesa,
                    :gift_nombre,
                    :gift_descripcion,
                    :gift_precio,
                    :gift_imagen,
                    'listo',
                    NOW(),
                    NOW()
                )",
                [
                    ':restaurante_id' => $restaurantId,
                    ':mesa_id' => $mesa['id'],
                    ':gift_product_id' => $giftProductId,
                    ':sender_user_id' => (int)$user->id,
                    ':recipient_user_id' => $recipientUserId,
                    ':sender_nombre' => $sender['nombre'] ?? 'Comensal',
                    ':recipient_nombre' => $recipient['nombre'] ?? 'Comensal',
                    ':sender_mesa' => $senderMesa,
                    ':recipient_mesa' => $recipientMesa,
                    ':gift_nombre' => $giftProduct['nombre'] ?? 'Regalo',
                    ':gift_descripcion' => $giftProduct['descripcion'] ?? null,
                    ':gift_precio' => isset($giftProduct['precio']) ? (float)$giftProduct['precio'] : 0,
                    ':gift_imagen' => $giftProduct['imagen'] ?? null,
                ]
            );

            if ($giftOrderId <= 0) {
                throw new \RuntimeException('No se pudo crear el registro del regalo');
            }

            $folio = $this->buildGiftFolio($giftOrderId);
            Database::rowCount(
                "UPDATE social_gift_orders
                    SET folio = :folio, updated_at = NOW()
                  WHERE id = :id",
                [
                    ':folio' => $folio,
                    ':id' => $giftOrderId,
                ]
            );

            $pdo->commit();

            Response::success([
                'id' => $giftOrderId,
                'folio' => $folio,
                'mesa_id' => $mesa['id'],
                'mesa_label' => $mesa['label'],
                'gift_nombre' => $giftProduct['nombre'] ?? 'Regalo',
                'recipient_nombre' => $recipient['nombre'] ?? 'Comensal',
                'sender_nombre' => $sender['nombre'] ?? 'Comensal',
                'recipient_mesa' => $recipientMesa,
                'giftName' => $giftProduct['nombre'] ?? 'Regalo',
                'recipientName' => $recipient['nombre'] ?? 'Comensal',
                'mesaLabel' => $mesa['label'],
            ], 'Regalo enviado al equipo de meseros.', 201);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('SocialController::sendGift ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo enviar el regalo en este momento.');
        }
    }

    public function createGiftPayment(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $giftProductId = (int)($input['gift_product_id'] ?? 0);
        $recipientUserId = (int)($input['recipient_user_id'] ?? 0);
        $requestKey = trim((string)($input['request_key'] ?? ''));
        $giftType = $this->sanitizeGiftType($input['gift_type'] ?? null);
        $paymentMode = strtolower(trim((string)($input['payment_mode'] ?? 'account')));

        $errors = [];
        if ($restaurantId <= 0) $errors['restaurant_id'] = ['Selecciona una sucursal válida'];
        if ($giftProductId <= 0) $errors['gift_product_id'] = ['Selecciona un regalo válido'];
        if ($recipientUserId <= 0) $errors['recipient_user_id'] = ['Selecciona un comensal válido'];
        if ($recipientUserId === (int)$user->id) $errors['recipient_user_id'] = ['No puedes enviarte un regalo a ti mismo'];
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestKey)) {
            $errors['request_key'] = ['La clave de solicitud no es válida'];
        }
        if (!in_array($paymentMode, ['account', 'stripe', 'wallet'], true)) {
            $errors['payment_mode'] = ['Selecciona una forma de pago valida'];
        }
        if ($errors) Response::validationError($errors);

        if ($paymentMode === 'stripe') {
            $this->createGiftStripePayment();
            return;
        }

        foreach (['social_gift_orders', 'social_gift_account_products', 'rest_pedidos', 'rest_pedido_items'] as $table) {
            if (!$this->tableExists($table)) {
                Response::serverError('Ejecuta la migración 033 antes de enviar regalos a la cuenta.');
            }
        }
        $giftColumns = $this->getTableColumns('social_gift_orders');
        foreach (['sender_mesa_id', 'pedido_id', 'pedido_item_id', 'cargado_cuenta_at'] as $column) {
            if (!in_array($column, $giftColumns, true)) {
                Response::serverError('Ejecuta la migración 033 antes de enviar regalos a la cuenta.');
            }
        }

        $sender = $this->fetchSocialProfile((int)$user->id);
        if (!$sender || !$this->hasSocialProfile($sender)) {
            Response::error('Completa tu perfil social antes de enviar regalos.', 400);
        }
        $senderMesaLabel = $this->sanitizeNullableString($sender['mesa'] ?? null);
        if (!(bool)($sender['is_social_active'] ?? false) || (int)($sender['current_restaurante_id'] ?? 0) !== $restaurantId) {
            Response::error('Activa tu modo social en la sucursal actual antes de enviar regalos.', 400);
        }
        if ($senderMesaLabel === null) Response::error('Necesitas seleccionar tu mesa antes de enviar un regalo.', 400);
        $senderMesa = $this->resolveMesaForRestaurant($restaurantId, $senderMesaLabel);
        if ($senderMesa === null) Response::error('No encontramos tu mesa en la sucursal actual.', 409);

        $recipient = $this->fetchSocialProfile($recipientUserId);
        if (!$recipient || !(bool)($recipient['is_social_active'] ?? false) || (int)($recipient['current_restaurante_id'] ?? 0) !== $restaurantId) {
            Response::error('Este comensal ya no está disponible en la sucursal seleccionada.', 409);
        }
        $recipientMesaLabel = $this->sanitizeNullableString($recipient['mesa'] ?? null);
        if ($recipientMesaLabel === null) Response::error('No pudimos ubicar la mesa del comensal seleccionado.', 409);
        $recipientMesa = $this->resolveMesaForRestaurant($restaurantId, $recipientMesaLabel);
        if ($recipientMesa === null) Response::error('No encontramos la mesa del comensal en la sucursal actual.', 409);

        $giftProduct = $this->findGiftProduct($giftProductId, $restaurantId, $giftType);
        if ($giftProduct === null) Response::notFound('Regalo no encontrado');
        $price = round((float)($giftProduct['precio'] ?? 0), 2);
        if ($price <= 0) Response::error('Este regalo no tiene un precio válido.', 409);

        if ($paymentMode === 'wallet') {
            $this->createGiftWalletPayment(
                $user,
                $input,
                $restaurantId,
                $giftProductId,
                $recipientUserId,
                $requestKey,
                $sender,
                $senderMesaLabel,
                $senderMesa,
                $recipient,
                $recipientMesaLabel,
                $recipientMesa,
                $giftProduct,
                $price,
                $giftColumns
            );
            return;
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $existing = $pdo->prepare(
                'SELECT * FROM social_gift_orders
                  WHERE sender_user_id = :sender_id AND payment_request_key = :request_key
                  LIMIT 1 FOR UPDATE'
            );
            $existing->execute([':sender_id' => (int)$user->id, ':request_key' => $requestKey]);
            $gift = $existing->fetch();
            if ($gift) {
                if ((int)$gift['restaurante_id'] !== $restaurantId ||
                    (int)$gift['recipient_user_id'] !== $recipientUserId ||
                    (int)$gift['gift_product_id'] !== $giftProductId) {
                    throw new \DomainException('La clave de solicitud ya pertenece a otro regalo.');
                }
                if (empty($gift['pedido_item_id'])) {
                    throw new \DomainException('Esta solicitud pertenece a un flujo de pago anterior. Inicia un nuevo envío.');
                }
                $pdo->commit();
                Response::success([
                    'gift' => $this->giftPaymentResponse($gift),
                    'charged_to_account' => true,
                    'account' => ['pedido_id' => (int)$gift['pedido_id'], 'pedido_item_id' => (int)$gift['pedido_item_id']],
                ], 'Regalo ya cargado a la cuenta.');
            }

            if ($this->tableExists('rest_cuenta_divisiones')) {
                $split = $pdo->prepare(
                    "SELECT id FROM rest_cuenta_divisiones
                      WHERE restaurante_id = :restaurant_id AND mesa_id = :table_id AND estado = 'activa'
                      LIMIT 1 FOR UPDATE"
                );
                $split->execute([':restaurant_id' => $restaurantId, ':table_id' => (int)$senderMesa['id']]);
                if ($split->fetch()) {
                    throw new \DomainException('No puedes enviar regalos mientras se cobran cuentas separadas en tu mesa.');
                }
            }

            $giftId = $this->insertDynamicRow($pdo, 'social_gift_orders', [
                'restaurante_id' => $restaurantId,
                'mesa_id' => (int)$recipientMesa['id'],
                'sender_mesa_id' => (int)$senderMesa['id'],
                'gift_product_id' => $giftProductId,
                'sender_user_id' => (int)$user->id,
                'recipient_user_id' => $recipientUserId,
                'sender_nombre' => $sender['nombre'] ?? 'Comensal',
                'recipient_nombre' => $recipient['nombre'] ?? 'Comensal',
                'sender_mesa' => $senderMesaLabel,
                'recipient_mesa' => $recipientMesaLabel,
                'gift_nombre' => $giftProduct['nombre'] ?? 'Regalo',
                'gift_descripcion' => $giftProduct['descripcion'] ?? null,
                'gift_precio' => $price,
                'gift_imagen' => $giftProduct['imagen'] ?? null,
                'status' => 'listo',
                'moneda' => 'MXN',
                'payment_request_key' => $requestKey,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($giftId <= 0) throw new \RuntimeException('No se pudo crear el regalo.');

            $account = $this->chargeGiftToTableAccount(
                $pdo,
                $restaurantId,
                (int)$senderMesa['id'],
                (int)$user->id,
                (string)($sender['nombre'] ?? 'Comensal'),
                $giftId,
                $giftProductId,
                $giftProduct,
                $price,
                (string)($recipient['nombre'] ?? 'Comensal'),
                $recipientMesaLabel
            );
            error_log('SocialController::createGiftPayment ACCOUNT OK gift_id=' . $giftId .
                ' pedido_id=' . ($account['pedido_id'] ?? 0) .
                ' pedido_item_id=' . ($account['pedido_item_id'] ?? 0) .
                ' consumo_id=' . (string)($account['consumo_id'] ?? ''));
            $folio = $this->buildGiftFolio($giftId);
            $giftUpdateSet = [
                'folio = :folio',
                'pedido_id = :order_id',
                'pedido_item_id = :item_id',
                'cargado_cuenta_at = NOW()',
                'updated_at = NOW()',
            ];
            $giftUpdateParams = [
                ':folio' => $folio,
                ':order_id' => $account['pedido_id'],
                ':item_id' => $account['pedido_item_id'],
                ':id' => $giftId,
            ];
            if (in_array('consumo_id', $giftColumns, true) && !empty($account['consumo_id'])) {
                $giftUpdateSet[] = 'consumo_id = :consumo_id';
                $giftUpdateParams[':consumo_id'] = $account['consumo_id'];
            }
            $update = $pdo->prepare(
                'UPDATE social_gift_orders
                    SET ' . implode(', ', $giftUpdateSet) . '
                  WHERE id = :id'
            );
            $update->execute($giftUpdateParams);
            $read = $pdo->prepare('SELECT * FROM social_gift_orders WHERE id = :id');
            $read->execute([':id' => $giftId]);
            $gift = $read->fetch();
            $this->recordSocialAccountNotification(
                $pdo,
                $recipientUserId,
                (int)$user->id,
                'social_gift_received',
                'Regalo recibido',
                sprintf(
                    '%s te envio %s.',
                    (string)($sender['nombre'] ?? 'Alguien'),
                    (string)($giftProduct['nombre'] ?? 'un regalo')
                ),
                [
                    'gift_id' => $giftId,
                    'folio' => $folio,
                    'gift_nombre' => $giftProduct['nombre'] ?? 'Regalo',
                    'sender_nombre' => $sender['nombre'] ?? 'Comensal',
                    'recipient_mesa' => $recipientMesaLabel,
                    'payment_mode' => 'account',
                ]
            );
            $pdo->commit();

            Response::success([
                'gift' => $this->giftPaymentResponse($gift),
                'charged_to_account' => true,
                'account' => $account,
            ], 'Regalo enviado y cargado a tu cuenta.', 201);
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SocialController::createGiftPayment ERROR: ' . $exception->getMessage());
            error_log('SocialController::createGiftPayment CONTEXT restaurant_id=' . $restaurantId .
                ' sender_user_id=' . (int)$user->id .
                ' recipient_user_id=' . $recipientUserId .
                ' gift_product_id=' . $giftProductId .
                ' payment_mode=' . $paymentMode .
                ' request_key=' . $requestKey);
            error_log('SocialController::createGiftPayment TRACE: ' . $exception->getTraceAsString());
            Response::serverError('No se pudo cargar el regalo a la cuenta.');
        }
    }

    private function createGiftWalletPayment(
        object $user,
        array $input,
        int $restaurantId,
        int $giftProductId,
        int $recipientUserId,
        string $requestKey,
        array $sender,
        string $senderMesaLabel,
        array $senderMesa,
        array $recipient,
        string $recipientMesaLabel,
        array $recipientMesa,
        array $giftProduct,
        float $price,
        array $giftColumns
    ): void {
        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $existing = $pdo->prepare(
                'SELECT * FROM social_gift_orders
                  WHERE sender_user_id = :sender_id AND payment_request_key = :request_key
                  LIMIT 1 FOR UPDATE'
            );
            $existing->execute([':sender_id' => (int)$user->id, ':request_key' => $requestKey]);
            $gift = $existing->fetch();
            if ($gift) {
                if ((int)$gift['restaurante_id'] !== $restaurantId ||
                    (int)$gift['recipient_user_id'] !== $recipientUserId ||
                    (int)$gift['gift_product_id'] !== $giftProductId) {
                    throw new \DomainException('La clave de solicitud ya pertenece a otro regalo.');
                }
                if (empty($gift['pedido_item_id'])) {
                    throw new \DomainException('Esta solicitud pertenece a un flujo de pago anterior. Inicia un nuevo envío.');
                }
                $pdo->commit();
                Response::success([
                    'gift' => $this->giftPaymentResponse($gift),
                    'paid_with_wallet' => true,
                ], 'Regalo ya pagado con Saldo Amare.');
            }

            $giftId = $this->insertDynamicRow($pdo, 'social_gift_orders', [
                'restaurante_id' => $restaurantId,
                'mesa_id' => (int)$recipientMesa['id'],
                'sender_mesa_id' => (int)$senderMesa['id'],
                'gift_product_id' => $giftProductId,
                'sender_user_id' => (int)$user->id,
                'recipient_user_id' => $recipientUserId,
                'sender_nombre' => $sender['nombre'] ?? 'Comensal',
                'recipient_nombre' => $recipient['nombre'] ?? 'Comensal',
                'sender_mesa' => $senderMesaLabel,
                'recipient_mesa' => $recipientMesaLabel,
                'gift_nombre' => $giftProduct['nombre'] ?? 'Regalo',
                'gift_descripcion' => $giftProduct['descripcion'] ?? null,
                'gift_precio' => $price,
                'gift_imagen' => $giftProduct['imagen'] ?? null,
                'status' => 'listo',
                'moneda' => 'MXN',
                'payment_request_key' => $requestKey,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if ($giftId <= 0) throw new \RuntimeException('No se pudo crear el regalo.');

            $reward = (new RewardsService())->charge(
                $pdo,
                (int)$user->id,
                $price,
                !empty($input['use_points']),
                'gift',
                'social_gift',
                $giftId,
                'Regalo social con Saldo Amare'
            );

            $account = $this->chargeGiftToTableAccount(
                $pdo,
                $restaurantId,
                (int)$senderMesa['id'],
                (int)$user->id,
                (string)($sender['nombre'] ?? 'Comensal'),
                $giftId,
                $giftProductId,
                $giftProduct,
                (float)$reward['wallet_total'],
                (string)($recipient['nombre'] ?? 'Comensal'),
                $recipientMesaLabel,
                false,
                null,
                'amare_wallet'
            );

            $folio = $this->buildGiftFolio($giftId);
            $giftUpdateSet = [
                'folio = :folio',
                'pedido_id = :order_id',
                'pedido_item_id = :item_id',
                'pagado_at = NOW()',
                'cargado_cuenta_at = NOW()',
                'updated_at = NOW()',
            ];
            $giftUpdateParams = [
                ':folio' => $folio,
                ':order_id' => $account['pedido_id'],
                ':item_id' => $account['pedido_item_id'],
                ':id' => $giftId,
            ];
            $rewardColumns = [
                'amare_wallet_used_mxn' => 'wallet_total',
                'amare_discount_mxn' => 'discount_amount',
                'amare_points_redeemed' => 'points_redeemed',
                'amare_points_earned' => 'points_earned',
            ];
            foreach ($rewardColumns as $column => $key) {
                if (in_array($column, $giftColumns, true)) {
                    $giftUpdateSet[] = "{$column} = :{$column}";
                    $giftUpdateParams[":{$column}"] = $reward[$key] ?? 0;
                }
            }
            if (in_array('consumo_id', $giftColumns, true) && !empty($account['consumo_id'])) {
                $giftUpdateSet[] = 'consumo_id = :consumo_id';
                $giftUpdateParams[':consumo_id'] = $account['consumo_id'];
            }
            $update = $pdo->prepare(
                'UPDATE social_gift_orders
                    SET ' . implode(', ', $giftUpdateSet) . '
                  WHERE id = :id'
            );
            $update->execute($giftUpdateParams);
            $read = $pdo->prepare('SELECT * FROM social_gift_orders WHERE id = :id');
            $read->execute([':id' => $giftId]);
            $gift = $read->fetch();
            if ($gift) {
                $this->markPaidGiftChargeOrder($pdo, $gift, 'amare_wallet');
            }
            $pdo->commit();

            Response::success([
                'gift' => $this->giftPaymentResponse($gift),
                'paid_with_wallet' => true,
                'reward' => $reward,
                'account' => $account,
            ], 'Regalo enviado con Saldo Amare.', 201);
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SocialController::createGiftPayment WALLET ERROR: ' . $exception->getMessage());
            error_log('SocialController::createGiftPayment WALLET TRACE: ' . $exception->getTraceAsString());
            Response::serverError('No se pudo pagar el regalo con Saldo Amare.');
        }
    }

    private function createGiftStripePayment(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $giftProductId = (int)($input['gift_product_id'] ?? 0);
        $recipientUserId = (int)($input['recipient_user_id'] ?? 0);
        $requestKey = trim((string)($input['request_key'] ?? ''));
        $giftType = $this->sanitizeGiftType($input['gift_type'] ?? null);

        $errors = [];
        if ($restaurantId <= 0) $errors['restaurant_id'] = ['Selecciona una sucursal válida'];
        if ($giftProductId <= 0) $errors['gift_product_id'] = ['Selecciona un regalo válido'];
        if ($recipientUserId <= 0) $errors['recipient_user_id'] = ['Selecciona un comensal válido'];
        if ($recipientUserId === (int)$user->id) $errors['recipient_user_id'] = ['No puedes enviarte un regalo a ti mismo'];
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestKey)) {
            $errors['request_key'] = ['La clave de solicitud no es válida'];
        }
        if (!empty($errors)) Response::validationError($errors);
        if (!$this->tableExists('social_gift_orders')) {
            Response::serverError('La tabla de regalos sociales no existe.');
        }
        $giftColumns = $this->getTableColumns('social_gift_orders');
        foreach (['stripe_payment_intent_id', 'payment_request_key', 'pagado_at', 'moneda'] as $required) {
            if (!in_array($required, $giftColumns, true)) {
                Response::serverError('Ejecuta la migración 030 antes de cobrar regalos.');
            }
        }

        $sender = $this->fetchSocialProfile((int)$user->id);
        if (!$sender || !$this->hasSocialProfile($sender)) {
            Response::error('Completa tu perfil social antes de enviar regalos.', 400);
        }
        $senderRestaurantId = (int)($sender['current_restaurante_id'] ?? 0);
        $senderMesa = $this->sanitizeNullableString($sender['mesa'] ?? null);
        if (!(bool)($sender['is_social_active'] ?? false) || $senderRestaurantId !== $restaurantId) {
            Response::error('Activa tu modo social en la sucursal actual antes de enviar regalos.', 400);
        }
        if ($senderMesa === null) Response::error('Necesitas seleccionar tu mesa antes de enviar un regalo.', 400);
        $senderMesaRow = $this->resolveMesaForRestaurant($restaurantId, $senderMesa);
        if ($senderMesaRow === null) Response::error('No encontramos tu mesa en la sucursal actual.', 409);

        $recipient = $this->fetchSocialProfile($recipientUserId);
        if (!$recipient || !(bool)($recipient['is_social_active'] ?? false) || (int)($recipient['current_restaurante_id'] ?? 0) !== $restaurantId) {
            Response::error('Este comensal ya no está disponible en la sucursal seleccionada.', 409);
        }
        $recipientMesa = $this->sanitizeNullableString($recipient['mesa'] ?? null);
        if ($recipientMesa === null) Response::error('No pudimos ubicar la mesa del comensal seleccionado.', 409);
        $mesa = $this->resolveMesaForRestaurant($restaurantId, $recipientMesa);
        if ($mesa === null) Response::error('No encontramos la mesa del comensal en la sucursal actual.', 409);

        $giftProduct = $this->findGiftProduct($giftProductId, $restaurantId, $giftType);
        if ($giftProduct === null) Response::notFound('Regalo no encontrado');
        $amountCents = (int)round((float)($giftProduct['precio'] ?? 0) * 100);
        if ($amountCents <= 0) Response::error('Este regalo no tiene un precio válido.', 409);

        $gift = Database::queryOne(
            'SELECT * FROM social_gift_orders WHERE sender_user_id = :sender_id AND payment_request_key = :request_key LIMIT 1',
            [':sender_id' => (int)$user->id, ':request_key' => $requestKey]
        );
        if ($gift && (
            (int)$gift['restaurante_id'] !== $restaurantId ||
            (int)$gift['recipient_user_id'] !== $recipientUserId ||
            (int)$gift['gift_product_id'] !== $giftProductId
        )) {
            Response::error('La clave de solicitud ya pertenece a otro regalo.', 409);
        }

        if (!$gift) {
            $pdo = Database::getInstance();
            try {
                $pdo->beginTransaction();
                $giftId = $this->insertDynamicRow($pdo, 'social_gift_orders', [
                    'restaurante_id' => $restaurantId,
                    'mesa_id' => (int)$mesa['id'],
                    'sender_mesa_id' => (int)$senderMesaRow['id'],
                    'gift_product_id' => $giftProductId,
                    'sender_user_id' => (int)$user->id,
                    'recipient_user_id' => $recipientUserId,
                    'sender_nombre' => $sender['nombre'] ?? 'Comensal',
                    'recipient_nombre' => $recipient['nombre'] ?? 'Comensal',
                    'sender_mesa' => $senderMesa,
                    'recipient_mesa' => $recipientMesa,
                    'gift_nombre' => $giftProduct['nombre'] ?? 'Regalo',
                    'gift_descripcion' => $giftProduct['descripcion'] ?? null,
                    'gift_precio' => $amountCents / 100,
                    'gift_imagen' => $giftProduct['imagen'] ?? null,
                    'status' => 'pendiente_pago',
                    'moneda' => 'MXN',
                    'payment_request_key' => $requestKey,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                if ($giftId <= 0) throw new \RuntimeException('No se pudo crear el regalo');
                Database::rowCount(
                    'UPDATE social_gift_orders SET folio = :folio WHERE id = :id',
                    [':folio' => $this->buildGiftFolio($giftId), ':id' => $giftId]
                );
                $pdo->commit();
                $gift = Database::queryOne('SELECT * FROM social_gift_orders WHERE id = :id', [':id' => $giftId]);
            } catch (\Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('SocialController::createGiftPayment DB ERROR: ' . $exception->getMessage());
                Response::serverError('No se pudo preparar el regalo.');
            }
        }

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intentId = (string)($gift['stripe_payment_intent_id'] ?? '');
            $intent = null;
            if ($intentId !== '') {
                try {
                    $intent = PaymentIntent::retrieve($intentId);
                } catch (\Stripe\Exception\InvalidRequestException $exception) {
                    $message = (string)$exception->getMessage();
                    if (stripos($message, 'No such payment_intent') === false) {
                        throw $exception;
                    }
                    Database::rowCount(
                        "UPDATE social_gift_orders
                            SET stripe_payment_intent_id = NULL,
                                status = IF(status = 'pago_fallido', 'pendiente_pago', status),
                                updated_at = NOW()
                          WHERE id = :id",
                        [':id' => (int)$gift['id']]
                    );
                    $intentId = '';
                }
            }
            if ($intent === null) {
                $intent = PaymentIntent::create([
                    'amount' => $amountCents,
                    'currency' => 'mxn',
                    'description' => 'Regalo social ' . ($gift['folio'] ?? ''),
                    'metadata' => [
                        'gift_order_id' => (string)$gift['id'],
                        'user_id' => (string)$user->id,
                    ],
                    'automatic_payment_methods' => ['enabled' => true],
                ], ['idempotency_key' => 'social_gift_' . $requestKey]);
                error_log('SocialController::createGiftPayment STRIPE CREATED gift_id=' . (int)$gift['id'] .
                    ' payment_intent_id=' . $intent->id .
                    ' request_key=' . $requestKey);
            }

            if ((int)$intent->amount !== $amountCents || strtolower((string)$intent->currency) !== 'mxn') {
                throw new \RuntimeException('El importe del intento no coincide');
            }
            Database::rowCount(
                "UPDATE social_gift_orders
                    SET stripe_payment_intent_id = :intent_id, status = IF(status = 'pago_fallido', 'pendiente_pago', status), updated_at = NOW()
                  WHERE id = :id",
                [':intent_id' => $intent->id, ':id' => (int)$gift['id']]
            );
            $gift['stripe_payment_intent_id'] = $intent->id;
            Response::success([
                'gift' => $this->giftPaymentResponse($gift),
                'client_secret' => $intent->client_secret,
                'payment_intent_id' => $intent->id,
            ], 'Pago de regalo preparado', 201);
        } catch (\Throwable $exception) {
            Database::rowCount(
                "UPDATE social_gift_orders SET status = 'pago_fallido', updated_at = NOW()
                  WHERE id = :id AND status = 'pendiente_pago'",
                [':id' => (int)$gift['id']]
            );
            error_log('SocialController::createGiftPayment STRIPE ERROR: ' . $exception->getMessage());
            error_log('SocialController::createGiftPayment STRIPE CONTEXT gift_id=' . (int)($gift['id'] ?? 0) .
                ' payment_intent_id=' . (string)($gift['stripe_payment_intent_id'] ?? '') .
                ' request_key=' . $requestKey .
                ' sender_user_id=' . (int)$user->id);
            Response::serverError('No se pudo iniciar el pago del regalo.');
        }
    }

    public function confirmGiftPayment(int $giftId): void
    {
        $user = AuthMiddleware::authenticate();
        $gift = Database::queryOne(
            'SELECT * FROM social_gift_orders WHERE id = :id AND sender_user_id = :user_id LIMIT 1',
            [':id' => $giftId, ':user_id' => (int)$user->id]
        );
        if (!$gift) Response::notFound('Regalo no encontrado');
        $intentId = (string)($gift['stripe_payment_intent_id'] ?? '');
        if ($intentId === '') Response::error('El regalo no tiene un intento de pago.', 409);
        $shouldNotifyRecipient = empty($gift['pagado_at']);

        try {
            Stripe::setApiKey($this->getStripeSecret());
            $intent = PaymentIntent::retrieve($intentId);
            $expectedCents = (int)round((float)$gift['gift_precio'] * 100);
            $metadataGiftId = (int)($intent->metadata['gift_order_id'] ?? 0);
            $metadataUserId = (int)($intent->metadata['user_id'] ?? 0);
            if ($metadataGiftId !== $giftId || $metadataUserId !== (int)$user->id || (int)$intent->amount !== $expectedCents || strtolower((string)$intent->currency) !== 'mxn') {
                Response::error('La información del pago no coincide con el regalo.', 409);
            }
            if ($intent->status !== 'succeeded') {
                Response::error('Stripe aún no confirma el pago del regalo.', 409);
            }
            if ((bool)$intent->livemode !== StripeConfig::isLiveMode()) {
                Response::error('El entorno del pago no coincide con el servidor.', 409);
            }
            if (empty($gift['pedido_id']) || empty($gift['pedido_item_id'])) {
                $pdo = Database::getInstance();
                try {
                    $pdo->beginTransaction();
                    $delivery = $this->chargeGiftToTableAccount(
                        $pdo,
                        (int)$gift['restaurante_id'],
                        (int)($gift['sender_mesa_id'] ?? $gift['mesa_id'] ?? 0),
                        (int)$user->id,
                        (string)($gift['sender_nombre'] ?? 'Comensal'),
                        $giftId,
                        (int)$gift['gift_product_id'],
                        [
                            'nombre' => $gift['gift_nombre'] ?? 'Regalo',
                            'descripcion' => $gift['gift_descripcion'] ?? null,
                            'imagen' => $gift['gift_imagen'] ?? null,
                        ],
                        (float)$gift['gift_precio'],
                        (string)($gift['recipient_nombre'] ?? 'Comensal'),
                        (string)($gift['recipient_mesa'] ?? ''),
                        false,
                        $intentId
                    );

                    $giftUpdateSet = [
                        'pedido_id = :order_id',
                        'pedido_item_id = :item_id',
                        'updated_at = NOW()',
                    ];
                    $giftUpdateParams = [
                        ':order_id' => $delivery['pedido_id'],
                        ':item_id' => $delivery['pedido_item_id'],
                        ':id' => $giftId,
                    ];
                    if (!empty($delivery['consumo_id']) && in_array('consumo_id', $this->getTableColumns('social_gift_orders'), true)) {
                        $giftUpdateSet[] = 'consumo_id = :consumo_id';
                        $giftUpdateParams[':consumo_id'] = $delivery['consumo_id'];
                    }
                    $updateGiftOrder = $pdo->prepare(
                        'UPDATE social_gift_orders
                            SET ' . implode(', ', $giftUpdateSet) . '
                          WHERE id = :id'
                    );
                    $updateGiftOrder->execute($giftUpdateParams);
                    $pdo->commit();
                } catch (\Throwable $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log('SocialController::confirmGiftPayment ORDER ERROR: ' . $exception->getMessage());
                    error_log('SocialController::confirmGiftPayment ORDER TRACE: ' . $exception->getTraceAsString());
                    Response::serverError('Stripe cobro el regalo, pero no pudimos registrarlo en pedidos.');
                }
            }

            Database::rowCount(
                "UPDATE social_gift_orders
                    SET status = IF(status IN ('pendiente_pago','pago_fallido'), 'listo', status),
                        pagado_at = COALESCE(pagado_at, NOW()), updated_at = NOW()
                  WHERE id = :id AND stripe_payment_intent_id = :intent_id",
                [':id' => $giftId, ':intent_id' => $intentId]
            );
            $paidGift = Database::queryOne('SELECT * FROM social_gift_orders WHERE id = :id', [':id' => $giftId]);
            if ($paidGift) {
                $this->markPaidGiftChargeOrder(Database::getInstance(), $paidGift, 'tarjeta', $intentId);
            }
            if ($paidGift && $shouldNotifyRecipient) {
                $this->recordSocialAccountNotification(
                    Database::getInstance(),
                    (int)$paidGift['recipient_user_id'],
                    (int)$user->id,
                    'social_gift_received',
                    'Regalo recibido',
                    sprintf(
                        '%s te envio %s.',
                        (string)($paidGift['sender_nombre'] ?? 'Alguien'),
                        (string)($paidGift['gift_nombre'] ?? 'un regalo')
                    ),
                    [
                        'gift_id' => $giftId,
                        'folio' => $paidGift['folio'] ?? null,
                        'gift_nombre' => $paidGift['gift_nombre'] ?? 'Regalo',
                        'sender_nombre' => $paidGift['sender_nombre'] ?? 'Comensal',
                        'recipient_mesa' => $paidGift['recipient_mesa'] ?? null,
                        'payment_mode' => 'stripe',
                    ]
                );
            }
            Response::success($this->giftPaymentResponse($paidGift), 'Regalo pagado y enviado');
        } catch (\Stripe\Exception\InvalidRequestException $exception) {
            if (stripos((string)$exception->getMessage(), 'No such payment_intent') !== false) {
                Response::error(
                    'Stripe no encuentra este intento de pago. Suele pasar cuando la app y el backend usan cuentas test distintas o cuando cambiaste llaves. Abre de nuevo el modal e intenta otra vez.',
                    409
                );
            }
            error_log('SocialController::confirmGiftPayment STRIPE ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo verificar el pago con Stripe.');
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('SocialController::confirmGiftPayment STRIPE ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo verificar el pago con Stripe.');
        }
    }

    public function reconcileGiftFromWebhook(int $giftId, int $senderUserId, string $paymentIntentId): void
    {
        $gift = Database::queryOne(
            'SELECT * FROM social_gift_orders WHERE id = :id AND sender_user_id = :user_id LIMIT 1',
            [':id' => $giftId, ':user_id' => $senderUserId]
        );
        if (!$gift || !hash_equals((string)($gift['stripe_payment_intent_id'] ?? ''), $paymentIntentId)) return;

        $pdo = Database::getInstance();
        $pdo->beginTransaction();
        try {
            $locked = Database::queryOne(
                'SELECT * FROM social_gift_orders WHERE id = :id AND sender_user_id = :user_id LIMIT 1 FOR UPDATE',
                [':id' => $giftId, ':user_id' => $senderUserId]
            );
            if (!$locked) {
                $pdo->commit();
                return;
            }

            $shouldNotify = empty($locked['pagado_at']);
            if (empty($locked['pedido_id']) || empty($locked['pedido_item_id'])) {
                $delivery = $this->chargeGiftToTableAccount(
                    $pdo,
                    (int)$locked['restaurante_id'],
                    (int)($locked['sender_mesa_id'] ?? $locked['mesa_id'] ?? 0),
                    $senderUserId,
                    (string)($locked['sender_nombre'] ?? 'Comensal'),
                    $giftId,
                    (int)$locked['gift_product_id'],
                    [
                        'nombre' => $locked['gift_nombre'] ?? 'Regalo',
                        'descripcion' => $locked['gift_descripcion'] ?? null,
                        'imagen' => $locked['gift_imagen'] ?? null,
                    ],
                    (float)$locked['gift_precio'],
                    (string)($locked['recipient_nombre'] ?? 'Comensal'),
                    (string)($locked['recipient_mesa'] ?? ''),
                    false,
                    $paymentIntentId
                );

                $updateSet = [
                    'pedido_id = :order_id',
                    'pedido_item_id = :item_id',
                ];
                $updateParams = [
                    ':order_id' => $delivery['pedido_id'],
                    ':item_id' => $delivery['pedido_item_id'],
                    ':id' => $giftId,
                ];
                if (!empty($delivery['consumo_id']) && in_array('consumo_id', $this->getTableColumns('social_gift_orders'), true)) {
                    $updateSet[] = 'consumo_id = :consumo_id';
                    $updateParams[':consumo_id'] = $delivery['consumo_id'];
                }
                $statement = $pdo->prepare(
                    'UPDATE social_gift_orders SET ' . implode(', ', $updateSet) . ', updated_at = NOW() WHERE id = :id'
                );
                $statement->execute($updateParams);
                $locked['pedido_id'] = $delivery['pedido_id'];
                $locked['pedido_item_id'] = $delivery['pedido_item_id'];
            }

            Database::rowCount(
                "UPDATE social_gift_orders
                    SET status = IF(status IN ('pendiente_pago','pago_fallido'), 'listo', status),
                        pagado_at = COALESCE(pagado_at, NOW()), updated_at = NOW()
                  WHERE id = :id AND stripe_payment_intent_id = :intent_id",
                [':id' => $giftId, ':intent_id' => $paymentIntentId]
            );
            $paidGift = Database::queryOne('SELECT * FROM social_gift_orders WHERE id = :id', [':id' => $giftId]);
            if ($paidGift) $this->markPaidGiftChargeOrder($pdo, $paidGift, 'tarjeta', $paymentIntentId);

            if ($paidGift && $shouldNotify) {
                $this->recordSocialAccountNotification(
                    $pdo,
                    (int)$paidGift['recipient_user_id'],
                    $senderUserId,
                    'social_gift_received',
                    'Regalo recibido',
                    sprintf(
                        '%s te envio %s.',
                        (string)($paidGift['sender_nombre'] ?? 'Alguien'),
                        (string)($paidGift['gift_nombre'] ?? 'un regalo')
                    ),
                    [
                        'gift_id' => $giftId,
                        'folio' => $paidGift['folio'] ?? null,
                        'gift_nombre' => $paidGift['gift_nombre'] ?? 'Regalo',
                        'sender_nombre' => $paidGift['sender_nombre'] ?? 'Comensal',
                        'recipient_mesa' => $paidGift['recipient_mesa'] ?? null,
                        'payment_mode' => 'stripe',
                    ]
                );
            }
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function respondGiftRequest(int $giftId): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $action = strtolower(trim((string)($input['action'] ?? '')));
        if (!in_array($action, ['accept', 'approve', 'reject', 'decline'], true)) {
            Response::validationError(['action' => ['Elige aceptar o rechazar el regalo']]);
        }
        if (!$this->tableExists('social_gift_orders')) {
            Response::serverError('La tabla de regalos sociales no existe.');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT *
                   FROM social_gift_orders
                  WHERE id = :id AND recipient_user_id = :user_id
                  LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $giftId, ':user_id' => (int)$user->id]);
            $gift = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$gift) {
                throw new \DomainException('Regalo no encontrado.');
            }
            if (in_array((string)$gift['status'], ['entregado', 'cancelado'], true)) {
                throw new \DomainException('Este regalo ya fue respondido.');
            }

            $accept = in_array($action, ['accept', 'approve'], true);
            if ($accept) {
                $this->markGiftNotificationsRead($pdo, $giftId, (int)$user->id);
                $this->recordSocialAccountNotification(
                    $pdo,
                    (int)$gift['sender_user_id'],
                    (int)$user->id,
                    'social_gift_accepted',
                    'Regalo aceptado',
                    (string)($gift['recipient_nombre'] ?? 'El comensal') . ' acepto tu regalo.',
                    [
                        'gift_id' => $giftId,
                        'gift_nombre' => $gift['gift_nombre'] ?? 'Regalo',
                        'recipient_user_id' => (int)$user->id,
                    ]
                );
                $pdo->commit();
                Response::success(['gift' => $this->giftPaymentResponse($gift)], 'Regalo aceptado.');
            }

            $update = $pdo->prepare(
                "UPDATE social_gift_orders
                    SET status = 'cancelado', updated_at = NOW()
                  WHERE id = :id"
            );
            $update->execute([':id' => $giftId]);
            $gift['status'] = 'cancelado';
            $refundEligible = $this->isRefundableSocialGiftObject($gift);
            $refund = !empty($gift['pagado_at']) && $refundEligible
                ? $this->refundRejectedGiftToWallet($pdo, $gift)
                : null;
            $refundMessage = !$refundEligible
                ? (string)($gift['recipient_nombre'] ?? 'El comensal') . ' rechazo tu regalo. Este tipo de regalo no genera reembolso.'
                : ($refund !== null
                    ? (string)($gift['recipient_nombre'] ?? 'El comensal') . ' rechazo tu regalo. Reembolsamos el 50% a tu Saldo Amare.'
                    : (string)($gift['recipient_nombre'] ?? 'El comensal') . ' rechazo tu regalo. Cuando pagues el cargo, reembolsaremos el 50% a tu Saldo Amare.');
            $this->markGiftNotificationsRead($pdo, $giftId, (int)$user->id);
            $this->recordSocialAccountNotification(
                $pdo,
                (int)$gift['sender_user_id'],
                (int)$user->id,
                'social_gift_rejected',
                'Regalo rechazado',
                $refundMessage,
                [
                    'gift_id' => $giftId,
                    'gift_nombre' => $gift['gift_nombre'] ?? 'Regalo',
                    'recipient_user_id' => (int)$user->id,
                    'refund_pending' => $refundEligible && $refund === null,
                    'refund_eligible' => $refundEligible,
                    'refund_amount_mxn' => $refund !== null
                        ? (float)($refund['refund_amount_mxn'] ?? 0)
                        : ($refundEligible ? round((float)($gift['gift_precio'] ?? 0) * 0.5, 2) : 0),
                    'wallet_balance_after_mxn' => $refund['balance_after_mxn'] ?? null,
                ]
            );
            $pdo->commit();

            Response::success([
                'gift' => $this->giftPaymentResponse($gift),
                'refund_pending' => $refundEligible && $refund === null,
                'refund_eligible' => $refundEligible,
                'refund' => $refund,
            ], 'Regalo rechazado.');
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('SocialController::respondGiftRequest ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo responder el regalo.');
        }
    }

    private function ensureSocialAccountCoverTables(): void
    {
        if (!$this->tableExists('social_account_covers')) {
            Response::serverError('Ejecuta la migracion 034 antes de cubrir cuentas sociales.');
        }
    }

    /**
     * @return array{0:array,1:string,2:array,3:array,4:string,5:array}
     */
    private function resolveSocialCoverContext(int $senderUserId, int $recipientUserId, int $restaurantId): array
    {
        if ($recipientUserId === $senderUserId) {
            Response::error('No puedes cubrir tu propia cuenta desde social.', 400);
        }

        $sender = $this->fetchSocialProfile($senderUserId);
        if (!$sender || !$this->hasSocialProfile($sender)) {
            Response::error('Completa tu perfil social antes de cubrir una cuenta.', 400);
        }
        if (!(bool)($sender['is_social_active'] ?? false) || (int)($sender['current_restaurante_id'] ?? 0) !== $restaurantId) {
            Response::error('Activa tu modo social en la sucursal actual antes de cubrir una cuenta.', 400);
        }
        $senderMesaLabel = $this->sanitizeNullableString($sender['mesa'] ?? null);
        if ($senderMesaLabel === null) Response::error('Necesitas seleccionar tu mesa antes de cubrir una cuenta.', 400);
        $senderMesa = $this->resolveMesaForRestaurant($restaurantId, $senderMesaLabel);
        if ($senderMesa === null) Response::error('No encontramos tu mesa en la sucursal actual.', 409);

        $recipient = $this->fetchSocialProfile($recipientUserId);
        if (!$recipient || !$this->hasSocialProfile($recipient)) {
            Response::notFound('Comensal no encontrado o sin perfil social completo.');
        }
        if (!(bool)($recipient['is_social_active'] ?? false) || (int)($recipient['current_restaurante_id'] ?? 0) !== $restaurantId) {
            Response::error('Este comensal ya no esta disponible en la sucursal seleccionada.', 409);
        }
        $recipientMesaLabel = $this->sanitizeNullableString($recipient['mesa'] ?? null);
        if ($recipientMesaLabel === null) Response::error('No pudimos ubicar la mesa del comensal seleccionado.', 409);
        $recipientMesa = $this->resolveMesaForRestaurant($restaurantId, $recipientMesaLabel);
        if ($recipientMesa === null) Response::error('No encontramos la mesa del comensal en la sucursal actual.', 409);

        return [$sender, $senderMesaLabel, $senderMesa, $recipient, $recipientMesaLabel, $recipientMesa];
    }

    private function findOpenDinerConsumption(int $restaurantId, int $userId, int $mesaId): ?array
    {
        $orderColumns = $this->getTableColumns('rest_pedidos');
        $where = [
            'restaurante_id = :restaurant_id',
            'mobile_usuario_id = :user_id',
            'mesa_id = :mesa_id',
            "tipo_pedido = 'eat_in'",
        ];
        if (in_array('cuenta_abierta', $orderColumns, true)) {
            $where[] = 'cuenta_abierta = 1';
        }
        if (in_array('salida_qr_generado_at', $orderColumns, true)) {
            $where[] = 'salida_qr_generado_at IS NULL';
        }
        if (in_array('salida_validado_at', $orderColumns, true)) {
            $where[] = 'salida_validado_at IS NULL';
        }

        $anchor = Database::queryOne(
            'SELECT * FROM rest_pedidos WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC, id DESC LIMIT 1',
            [
                ':restaurant_id' => $restaurantId,
                ':user_id' => $userId,
                ':mesa_id' => $mesaId,
            ]
        );
        if (!$anchor) return null;

        if (in_array('consumo_id', $orderColumns, true) && !empty($anchor['consumo_id'])) {
            return $this->findConsumptionById($restaurantId, $userId, (string)$anchor['consumo_id']);
        }

        $orders = Database::query(
            'SELECT * FROM rest_pedidos WHERE ' . implode(' AND ', $where) . ' ORDER BY id ASC',
            [
                ':restaurant_id' => $restaurantId,
                ':user_id' => $userId,
                ':mesa_id' => $mesaId,
            ]
        );

        return $orders ? $this->buildSocialConsumption($orders) : null;
    }

    private function findConsumptionById(int $restaurantId, int $userId, string $consumoId): ?array
    {
        if ($consumoId === '') return null;
        if (str_starts_with($consumoId, 'ORD-')) {
            $ids = array_values(array_filter(array_map('intval', explode('-', substr($consumoId, 4)))));
            if (!$ids) return null;
            $params = [
                ':restaurant_id' => $restaurantId,
                ':user_id' => $userId,
            ];
            $placeholders = [];
            foreach ($ids as $index => $id) {
                $key = ':id_' . $index;
                $placeholders[] = $key;
                $params[$key] = $id;
            }
            $orders = Database::query(
                'SELECT * FROM rest_pedidos
                  WHERE restaurante_id = :restaurant_id AND mobile_usuario_id = :user_id
                    AND id IN (' . implode(',', $placeholders) . ')',
                $params
            );
            return $orders ? $this->buildSocialConsumption($orders) : null;
        }

        $orders = Database::query(
            "SELECT * FROM rest_pedidos
              WHERE restaurante_id = :restaurant_id
                AND mobile_usuario_id = :user_id
                AND consumo_id = :consumo_id
                AND tipo_pedido = 'eat_in'
              ORDER BY id ASC",
            [
                ':restaurant_id' => $restaurantId,
                ':user_id' => $userId,
                ':consumo_id' => $consumoId,
            ]
        );
        return $orders ? $this->buildSocialConsumption($orders) : null;
    }

    private function buildSocialConsumption(array $orders): ?array
    {
        $allOrderIds = array_values(array_map(static fn(array $order): int => (int)$order['id'], $orders));
        $giftOrderIds = $this->socialGiftChargeOrderIds($allOrderIds);
        $billableOrders = array_values(array_filter(
            $orders,
            fn(array $order): bool => !$this->isSocialGiftChargeOrder($order, $giftOrderIds)
                && !$this->isSocialConsumptionOrderSettled($order)
                && round((float)($order['total'] ?? 0), 2) > 0
        ));
        $orderIds = array_values(array_map(static fn(array $order): int => (int)$order['id'], $billableOrders));
        if (!$orderIds) {
            return null;
        }

        $items = $this->fetchOrderItemsForCopy($orderIds);
        if (!$items) {
            return null;
        }

        $total = 0.0;
        $subtotal = 0.0;
        foreach ($items as $item) {
            $lineSubtotal = $this->socialOrderItemSubtotal($item);
            $subtotal += $lineSubtotal;
            $total += $lineSubtotal;
        }
        $consumoId = (string)($billableOrders[0]['consumo_id'] ?? '');
        if ($consumoId === '') {
            $consumoId = 'ORD-' . implode('-', $orderIds);
        }

        return [
            'consumo_id' => $consumoId,
            'orders' => $billableOrders,
            'order_ids' => $orderIds,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
            'excluded_social_gift_order_ids' => array_values(array_intersect($allOrderIds, array_keys($giftOrderIds))),
        ];
    }

    /**
     * @param array<int> $orderIds
     * @return array<int, bool>
     */
    private function socialGiftChargeOrderIds(array $orderIds): array
    {
        $orderIds = array_values(array_filter(array_unique(array_map('intval', $orderIds))));
        if (!$orderIds || !$this->tableExists('social_gift_orders')) {
            return [];
        }

        $giftColumns = $this->getTableColumns('social_gift_orders');
        if (!in_array('pedido_id', $giftColumns, true)) {
            return [];
        }

        $params = [];
        $placeholders = [];
        foreach ($orderIds as $index => $orderId) {
            $key = ':gift_order_' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        $rows = Database::query(
            'SELECT DISTINCT pedido_id
               FROM social_gift_orders
              WHERE pedido_id IN (' . implode(',', $placeholders) . ')' .
                (in_array('pedido_item_id', $giftColumns, true) ? '
                AND (pedido_item_id IS NULL OR pedido_item_id = 0)' : ''),
            $params
        );
        $result = [];
        foreach ($rows as $row) {
            $id = (int)($row['pedido_id'] ?? 0);
            if ($id > 0) {
                $result[$id] = true;
            }
        }

        return $result;
    }

    /**
     * @param array<int, bool> $giftOrderIds
     */
    private function isSocialGiftChargeOrder(array $order, array $giftOrderIds): bool
    {
        $orderId = (int)($order['id'] ?? 0);
        if ($orderId > 0 && isset($giftOrderIds[$orderId])) {
            return true;
        }

        return isset($order['notas']) && stripos((string)$order['notas'], 'Regalo social para ') === 0;
    }

    private function isSocialConsumptionOrderSettled(array $order): bool
    {
        if (!empty($order['pagado_at']) || !empty($order['cerrado_at']) || !empty($order['salida_validado_at'])) {
            return true;
        }

        return isset($order['estado']) && in_array((string)$order['estado'], ['entregado', 'cancelado'], true);
    }

    private function fetchOrderItemsForCopy(array $orderIds): array
    {
        if (!$orderIds) return [];
        $params = [];
        $placeholders = [];
        foreach (array_values(array_unique($orderIds)) as $index => $orderId) {
            $key = ':order_id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        $giftItemFilter = '';
        if ($this->tableExists('social_gift_orders')) {
            $giftColumns = $this->getTableColumns('social_gift_orders');
            $giftConditions = [];
            if (in_array('pedido_item_id', $giftColumns, true)) {
                $giftConditions[] = 'sg.pedido_item_id = pi.id';
            }
            if (in_array('pedido_id', $giftColumns, true)) {
                $giftConditions[] = 'sg.pedido_id = pi.pedido_id';
            }
            if ($giftConditions) {
                $giftItemFilter = ' AND NOT EXISTS (
                    SELECT 1
                      FROM social_gift_orders sg
                     WHERE (' . implode(' OR ', $giftConditions) . ')
                     LIMIT 1
                )';
            }
        }
        $legacyGiftFilters = [
            "COALESCE(pi.notas, '') NOT LIKE '%Regalo para %'",
            "COALESCE(pi.notas, '') NOT LIKE '%Regalo social%'",
            "COALESCE(p.nombre, '') NOT LIKE 'Regalo:%'",
        ];
        if ($this->tableExists('rest_platillos') && in_array('codigo', $this->getTableColumns('rest_platillos'), true)) {
            $legacyGiftFilters[] = "COALESCE(p.codigo, '') NOT LIKE 'SG-%'";
        }

        return Database::query(
            'SELECT pi.*, p.nombre AS platillo_nombre, p.precio AS platillo_precio
               FROM rest_pedido_items pi
          LEFT JOIN rest_platillos p ON p.id = pi.platillo_id
              WHERE pi.pedido_id IN (' . implode(',', $placeholders) . ')
                ' . $giftItemFilter . '
                AND ' . implode("\n                AND ", $legacyGiftFilters) . '
           ORDER BY pi.id ASC',
            $params
        );
    }

    private function socialConsumptionSummary(array $consumption): array
    {
        $items = array_map(function (array $item): array {
            $quantity = max(1, (int)($item['cantidad'] ?? 1));
            $subtotal = $this->socialOrderItemSubtotal($item);
            $unitPrice = (float)($item['precio_unit'] ?? 0);
            if ($unitPrice <= 0 && $quantity > 0) {
                $unitPrice = round($subtotal / $quantity, 2);
            }

            return [
                'id' => (int)($item['id'] ?? 0),
                'pedido_id' => (int)($item['pedido_id'] ?? 0),
                'platillo_id' => isset($item['platillo_id']) ? (int)$item['platillo_id'] : null,
                'nombre' => $item['platillo_nombre'] ?? 'Producto',
                'cantidad' => $quantity,
                'precio_unit' => $unitPrice,
                'subtotal' => $subtotal,
            ];
        }, $consumption['items'] ?? []);

        return [
            'consumo_id' => $consumption['consumo_id'],
            'order_ids' => $consumption['order_ids'],
            'subtotal_mxn' => (float)$consumption['subtotal'],
            'total_mxn' => (float)$consumption['total'],
            'items_count' => count($items),
            'items' => $items,
        ];
    }

    private function pendingSocialGiftChargesForConsumption(array $consumption, int $userId, int $restaurantId): array
    {
        if (
            $userId <= 0 ||
            $restaurantId <= 0 ||
            !$this->tableExists('social_gift_orders') ||
            !$this->tableExists('rest_pedidos')
        ) {
            return [];
        }

        $giftColumns = $this->getTableColumns('social_gift_orders');
        if (
            !in_array('pedido_id', $giftColumns, true) ||
            !in_array('cargado_cuenta_at', $giftColumns, true)
        ) {
            return [];
        }

        $orderColumns = $this->getTableColumns('rest_pedidos');
        $where = [
            'sg.sender_user_id = :user_id',
            'sg.restaurante_id = :restaurant_id',
            'sg.cargado_cuenta_at IS NOT NULL',
            'sg.pedido_id IS NOT NULL',
            'sg.pedido_id > 0',
        ];
        $params = [
            ':user_id' => $userId,
            ':restaurant_id' => $restaurantId,
        ];

        if (in_array('pagado_at', $giftColumns, true)) {
            $where[] = 'sg.pagado_at IS NULL';
        }
        if (in_array('status', $giftColumns, true)) {
            $where[] = "COALESCE(sg.status, '') IN ('listo','reclamado','cancelado')";
        }
        if (in_array('tipo_pedido', $orderColumns, true)) {
            $where[] = "rp.tipo_pedido = 'eat_in'";
        }

        $consumoId = trim((string)($consumption['consumo_id'] ?? ''));
        if ($consumoId !== '' && !str_starts_with($consumoId, 'ORD-') && in_array('consumo_id', $orderColumns, true)) {
            $where[] = 'rp.consumo_id = :consumo_id';
            $params[':consumo_id'] = $consumoId;
        } else {
            $mesaId = $this->firstConsumptionMesaId($consumption);
            if ($mesaId !== null && in_array('mesa_id', $orderColumns, true)) {
                $where[] = 'rp.mesa_id = :mesa_id';
                $params[':mesa_id'] = $mesaId;
            }
            if (in_array('mobile_usuario_id', $orderColumns, true)) {
                $where[] = 'rp.mobile_usuario_id = :user_id_for_order';
                $params[':user_id_for_order'] = $userId;
            }
        }

        $select = [
            'sg.id',
            'sg.folio',
            'sg.gift_nombre',
            'sg.gift_precio',
            'sg.recipient_nombre',
            'sg.recipient_mesa',
            'sg.pedido_id',
            'sg.status',
            'sg.cargado_cuenta_at',
        ];
        if (in_array('pedido_item_id', $giftColumns, true)) {
            $select[] = 'sg.pedido_item_id';
        }
        if (in_array('pagado_at', $giftColumns, true)) {
            $select[] = 'sg.pagado_at';
        }

        $rows = Database::query(
            'SELECT ' . implode(', ', $select) . '
               FROM social_gift_orders sg
               JOIN rest_pedidos rp ON rp.id = sg.pedido_id
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY sg.created_at ASC, sg.id ASC',
            $params
        );

        return array_map(static function (array $gift): array {
            return [
                'id' => (int)$gift['id'],
                'folio' => $gift['folio'] ?? null,
                'gift_nombre' => $gift['gift_nombre'] ?? 'Regalo',
                'gift_precio' => isset($gift['gift_precio']) ? (float)$gift['gift_precio'] : 0,
                'recipient_nombre' => $gift['recipient_nombre'] ?? null,
                'recipient_mesa' => $gift['recipient_mesa'] ?? null,
                'pedido_id' => isset($gift['pedido_id']) ? (int)$gift['pedido_id'] : null,
                'pedido_item_id' => isset($gift['pedido_item_id']) ? (int)$gift['pedido_item_id'] : null,
                'status' => $gift['status'] ?? null,
                'cargado_cuenta_at' => $gift['cargado_cuenta_at'] ?? null,
                'pagado_at' => $gift['pagado_at'] ?? null,
            ];
        }, $rows);
    }

    private function firstConsumptionMesaId(array $consumption): ?int
    {
        foreach (($consumption['orders'] ?? []) as $order) {
            $mesaId = isset($order['mesa_id']) ? (int)$order['mesa_id'] : 0;
            if ($mesaId > 0) {
                return $mesaId;
            }
        }

        return null;
    }

    private function socialOrderItemSubtotal(array $item): float
    {
        $quantity = max(1, (int)($item['cantidad'] ?? 1));
        $storedSubtotal = round((float)($item['subtotal'] ?? 0), 2);
        if ($storedSubtotal > 0) {
            return $storedSubtotal;
        }

        $unitPrice = (float)($item['precio_unit'] ?? 0);
        if ($unitPrice <= 0) {
            $unitPrice = (float)($item['platillo_precio'] ?? 0);
        }

        return round($unitPrice * $quantity, 2);
    }

    private function createSocialAccountCoverRecord(
        int $restaurantId,
        int $payerUserId,
        int $coveredUserId,
        int $payerMesaId,
        int $coveredMesaId,
        string $payerMesa,
        string $coveredMesa,
        string $coveredConsumoId,
        string $paymentMode,
        string $status,
        float $amount,
        int $itemsCount,
        string $requestKey,
        ?string $paymentIntentId,
        string $message,
        ?\PDO $pdo = null
    ): array {
        $pdo = $pdo ?? Database::getInstance();
        $now = date('Y-m-d H:i:s');
        $record = [
            'restaurante_id' => $restaurantId,
            'payer_user_id' => $payerUserId,
            'covered_user_id' => $coveredUserId,
            'payer_mesa_id' => $payerMesaId,
            'covered_mesa_id' => $coveredMesaId,
            'payer_mesa' => $payerMesa,
            'covered_mesa' => $coveredMesa,
            'covered_consumo_id' => $coveredConsumoId,
            'payment_mode' => $paymentMode,
            'status' => $status,
            'amount_mxn' => $amount,
            'items_count' => $itemsCount,
            'payment_request_key' => $requestKey,
            'stripe_payment_intent_id' => $paymentIntentId,
            'message' => $message,
            'payer_pedido_id' => null,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $id = $this->insertDynamicRow($pdo, 'social_account_covers', $record);
        if ($id <= 0) throw new \RuntimeException('No se pudo crear la cobertura social.');
        return ['id' => $id] + $record;
    }

    private function createCoveredConsumptionOrder(
        \PDO $pdo,
        int $restaurantId,
        int $payerUserId,
        string $payerName,
        int $payerMesaId,
        string $payerMesa,
        array $recipient,
        string $recipientMesa,
        array $consumption,
        bool $openAccount,
        ?string $paymentIntentId,
        ?string $paymentMethod
    ): int {
        $orderColumns = $this->getTableColumns('rest_pedidos');
        $total = round((float)($consumption['total'] ?? 0), 2);
        $subtotal = round((float)($consumption['subtotal'] ?? $total), 2);
        $notes = 'Cuenta social cubierta de ' . (string)($recipient['nombre'] ?? 'Comensal') . ' - ' . $this->formatMesaLabel($recipientMesa);
        $orderData = [
            'restaurante_id' => $restaurantId,
            'mobile_usuario_id' => $payerUserId,
            'folio' => 'AM-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10),
            'estado' => $openAccount ? 'pendiente' : 'en_preparacion',
            'subtotal' => $subtotal,
            'total' => $total,
            'tipo_pedido' => 'eat_in',
            'pedido_origen' => 'cliente',
            'cliente_nombre' => substr($payerName, 0, 120),
            'notas' => $notes,
            'cuenta_abierta' => $openAccount ? 1 : 0,
            'mesa_id' => $payerMesaId > 0 ? $payerMesaId : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($openAccount && in_array('consumo_id', $orderColumns, true)) {
            $orderData['consumo_id'] = $this->resolveOpenConsumptionIdForGift($pdo, $restaurantId, $payerUserId, $payerMesaId);
        }
        if (in_array('tipo_origen', $orderColumns, true)) {
            $orderData['tipo_origen'] = 'menu';
        }
        if ($paymentIntentId !== null) {
            if (in_array('stripe_payment_intent_id', $orderColumns, true)) {
                $orderData['stripe_payment_intent_id'] = $paymentIntentId;
            } elseif (in_array('payment_intent_id', $orderColumns, true)) {
                $orderData['payment_intent_id'] = $paymentIntentId;
            }
        }
        if ($paymentMethod !== null) {
            if (in_array('metodo_pago', $orderColumns, true)) {
                $orderData['metodo_pago'] = $paymentMethod;
            } elseif (in_array('payment_method', $orderColumns, true)) {
                $orderData['payment_method'] = $paymentMethod;
            }
        }
        if (!$openAccount && in_array('pagado_at', $orderColumns, true)) {
            $orderData['pagado_at'] = date('Y-m-d H:i:s');
        }

        $orderId = $this->insertDynamicRow($pdo, 'rest_pedidos', $orderData);
        if ($orderId <= 0) throw new \RuntimeException('No se pudo crear la cuenta cubierta.');

        foreach (($consumption['items'] ?? []) as $item) {
            $itemData = [
                'pedido_id' => $orderId,
                'platillo_id' => $item['platillo_id'] ?? null,
                'cantidad' => $item['cantidad'] ?? 1,
                'precio_unit' => $item['precio_unit'] ?? 0,
                'subtotal' => $item['subtotal'] ?? 0,
                'notas' => $item['notas'] ?? null,
                'estado' => $item['estado'] ?? 'pendiente',
                'origen' => $item['origen'] ?? 'menu',
                'extras_json' => $item['extras_json'] ?? null,
            ];
            $itemId = $this->insertDynamicRow($pdo, 'rest_pedido_items', $itemData);
            if ($itemId <= 0) throw new \RuntimeException('No se pudo copiar un producto de la cuenta cubierta.');
        }

        if ($payerMesaId > 0) {
            $this->markTableOccupiedForGift($pdo, $payerMesaId);
        }
        return $orderId;
    }

    private function markConsumptionCovered(\PDO $pdo, array $consumption, string $method): void
    {
        $orderIds = array_values(array_filter(array_map('intval', $consumption['order_ids'] ?? [])));
        if (!$orderIds) return;
        $columns = $this->getTableColumns('rest_pedidos');
        $hasPaidAt = in_array('pagado_at', $columns, true);
        $hasClosedAt = in_array('cerrado_at', $columns, true);
        $set = ['estado = :estado'];
        $params = [
            ':estado' => ($hasPaidAt || $hasClosedAt) ? 'en_preparacion' : 'entregado',
        ];
        if (in_array('cuenta_abierta', $columns, true)) {
            $set[] = 'cuenta_abierta = 0';
        }
        if (in_array('metodo_pago', $columns, true)) {
            $set[] = 'metodo_pago = :method';
            $params[':method'] = $method;
        } elseif (in_array('payment_method', $columns, true)) {
            $set[] = 'payment_method = :method';
            $params[':method'] = $method;
        }
        if ($hasPaidAt) {
            $set[] = 'pagado_at = COALESCE(pagado_at, NOW())';
        }
        if (!$hasPaidAt && $hasClosedAt) {
            $set[] = 'cerrado_at = COALESCE(cerrado_at, NOW())';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }
        $placeholders = [];
        foreach ($orderIds as $index => $orderId) {
            $key = ':order_' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }
        $statement = $pdo->prepare(
            'UPDATE rest_pedidos SET ' . implode(', ', $set) . ' WHERE id IN (' . implode(',', $placeholders) . ')'
        );
        $statement->execute($params);
    }

    private function processDirectSocialCoverPayment(\PDO $pdo, array $cover, array $consumption, string $method): array
    {
        $this->markConsumptionCovered($pdo, $consumption, $method);
        return $this->markSocialCoverTicketsPaid($pdo, $consumption, $method);
    }

    private function markSocialCoverTicketsPaid(\PDO $pdo, array $consumption, string $method): array
    {
        if (!$this->tableExists('rest_tickets') || !$this->tableExists('rest_visitas')) {
            return [
                'covered_order_id' => (int)($consumption['order_ids'][0] ?? 0),
                'covered_visit_id' => null,
                'visit_id' => null,
            ];
        }

        $visitIds = [];
        foreach (($consumption['orders'] ?? []) as $order) {
            $visitId = (int)($order['visita_id'] ?? 0);
            if ($visitId > 0) {
                $visitIds[$visitId] = true;
            }
        }

        $firstVisitId = null;
        foreach (array_keys($visitIds) as $visitId) {
            $firstVisitId = $firstVisitId ?? (int)$visitId;
            $summary = $this->visitOrderTotal($visitId);
            if ($summary['total'] <= 0) {
                continue;
            }

            $ticket = Database::queryOne(
                'SELECT * FROM rest_tickets WHERE visita_id = :visit_id ORDER BY id DESC LIMIT 1 FOR UPDATE',
                [':visit_id' => $visitId]
            );

            if ($ticket) {
                $pdo->prepare(
                    "UPDATE rest_tickets
                        SET subtotal = :subtotal,
                            total = :total,
                            estado = 'pagado',
                            metodo_pago = :method,
                            pagado_at = COALESCE(pagado_at, NOW())
                      WHERE id = :id"
                )->execute([
                    ':subtotal' => $summary['subtotal'],
                    ':total' => $summary['total'],
                    ':method' => $method,
                    ':id' => (int)$ticket['id'],
                ]);
            } else {
                $this->insertDynamicRow($pdo, 'rest_tickets', [
                    'restaurante_id' => $summary['restaurante_id'],
                    'visita_id' => $visitId,
                    'mesa_id' => $summary['mesa_id'],
                    'folio' => 'SC-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8)),
                    'subtotal' => $summary['subtotal'],
                    'propina' => 0,
                    'total' => $summary['total'],
                    'estado' => 'pagado',
                    'metodo_pago' => $method,
                    'created_at' => date('Y-m-d H:i:s'),
                    'pagado_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $pdo->prepare(
                "UPDATE rest_visitas
                    SET subtotal = :subtotal,
                        total = :total,
                        estado = 'pagada',
                        pagada_at = COALESCE(pagada_at, NOW())
                  WHERE id = :id"
            )->execute([
                ':subtotal' => $summary['subtotal'],
                ':total' => $summary['total'],
                ':id' => $visitId,
            ]);
        }

        return [
            'covered_order_id' => (int)($consumption['order_ids'][0] ?? 0),
            'covered_visit_id' => $firstVisitId,
            'visit_id' => $firstVisitId,
        ];
    }

    private function visitOrderTotal(int $visitId): array
    {
        $summary = Database::queryOne(
            "SELECT
                COALESCE(MAX(restaurante_id), 0) AS restaurante_id,
                COALESCE(MAX(mesa_id), 0) AS mesa_id,
                COALESCE(SUM(CASE WHEN estado <> 'cancelado' THEN subtotal ELSE 0 END), 0) AS subtotal,
                COALESCE(SUM(CASE WHEN estado <> 'cancelado' THEN total ELSE 0 END), 0) AS total
               FROM rest_pedidos
              WHERE visita_id = :visit_id",
            [':visit_id' => $visitId]
        ) ?? [];

        return [
            'restaurante_id' => (int)($summary['restaurante_id'] ?? 0),
            'mesa_id' => (int)($summary['mesa_id'] ?? 0) ?: null,
            'subtotal' => round((float)($summary['subtotal'] ?? 0), 2),
            'total' => round((float)($summary['total'] ?? 0), 2),
        ];
    }

    private function ensureCoveredConsumptionExitPass(array $consumption, int $coveredUserId): ?array
    {
        $orderIds = array_values(array_filter(array_map('intval', $consumption['order_ids'] ?? [])));
        if (!$orderIds) {
            return null;
        }

        try {
            return Order::ensureExitPass((int)$orderIds[0], $coveredUserId);
        } catch (\Throwable $exception) {
            error_log('SocialController::ensureCoveredConsumptionExitPass ERROR: ' . $exception->getMessage());
            return null;
        }
    }

    private function findActiveSocialAccountCover(
        int $restaurantId,
        int $coveredUserId,
        string $coveredConsumoId,
        bool $forUpdate = false
    ): ?array {
        if (!$this->tableExists('social_account_covers') || trim($coveredConsumoId) === '') {
            return null;
        }

        $sql = "SELECT *
                  FROM social_account_covers
                 WHERE restaurante_id = :restaurant_id
                   AND covered_user_id = :covered_user_id
                   AND covered_consumo_id = :covered_consumo_id
                   AND status IN ('pending','pending_approval','approved','pending_payment')
              ORDER BY id DESC
                 LIMIT 1";
        if ($forUpdate) {
            $sql .= ' FOR UPDATE';
        }

        return Database::queryOne($sql, [
            ':restaurant_id' => $restaurantId,
            ':covered_user_id' => $coveredUserId,
            ':covered_consumo_id' => $coveredConsumoId,
        ]);
    }

    private function updateSocialAccountCover(\PDO $pdo, int $coverId, array $data): void
    {
        $allowed = ['payer_pedido_id', 'status', 'paid_at'];
        $set = [];
        $params = [':id' => $coverId];
        foreach ($data as $key => $value) {
            if (!in_array($key, $allowed, true)) continue;
            $set[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }
        $set[] = 'updated_at = NOW()';
        $statement = $pdo->prepare('UPDATE social_account_covers SET ' . implode(', ', $set) . ' WHERE id = :id');
        $statement->execute($params);
    }

    private function socialAccountCoverResponse(array $cover): array
    {
        return [
            'id' => (int)$cover['id'],
            'restaurante_id' => (int)$cover['restaurante_id'],
            'payer_user_id' => (int)$cover['payer_user_id'],
            'covered_user_id' => (int)$cover['covered_user_id'],
            'payer_pedido_id' => isset($cover['payer_pedido_id']) ? (int)$cover['payer_pedido_id'] : null,
            'payment_mode' => $cover['payment_mode'],
            'status' => $cover['status'],
            'amount_mxn' => (float)$cover['amount_mxn'],
            'items_count' => (int)$cover['items_count'],
            'payer_mesa' => $cover['payer_mesa'] ?? null,
            'covered_mesa' => $cover['covered_mesa'] ?? null,
            'message' => $cover['message'] ?? null,
            'paid_at' => $cover['paid_at'] ?? null,
        ];
    }

    private function recordSocialAccountNotification(
        \PDO $pdo,
        int $userId,
        int $actorUserId,
        string $type,
        string $title,
        string $body,
        array $payload
    ): void {
        if (!$this->tableExists('social_account_notifications')) {
            return;
        }
        $payload = $this->withSocialNotificationDeepLink($type, $payload);
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $this->insertDynamicRow($pdo, 'social_account_notifications', [
            'user_id' => $userId,
            'actor_user_id' => $actorUserId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'payload_json' => $payloadJson === false ? null : $payloadJson,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $this->sendSocialPushNotification($userId, $type, $title, $body, $payload);
    }

    private function withSocialNotificationDeepLink(string $type, array $payload): array
    {
        if (!empty($payload['deep_link'])) {
            return $payload;
        }

        $params = ['notificationType' => $type];
        if (isset($payload['gift_id'])) {
            $params['giftId'] = (string)$payload['gift_id'];
        }
        if (isset($payload['cover_id'])) {
            $params['coverId'] = (string)$payload['cover_id'];
        }
        if (isset($payload['payment_mode'])) {
            $params['paymentMode'] = (string)$payload['payment_mode'];
        }

        $payload['deep_link'] = '/social?' . http_build_query($params);
        return $payload;
    }

    private function sendSocialPushNotification(int $userId, string $type, string $title, string $body, array $payload): void
    {
        if (strpos($type, 'social_') !== 0) {
            return;
        }

        try {
            (new FirebaseMessagingService())->sendToUser($userId, $title, $body, array_merge($payload, [
                'type' => $type,
                'deep_link' => $payload['deep_link'] ?? '/social',
            ]));
        } catch (\Throwable $exception) {
            error_log('SocialController::sendSocialPushNotification ERROR: ' . $exception->getMessage());
        }
    }

    private function markGiftNotificationsRead(\PDO $pdo, int $giftId, int $userId): void
    {
        if (!$this->tableExists('social_account_notifications')) {
            return;
        }

        $stmt = $pdo->prepare(
            "UPDATE social_account_notifications
                SET read_at = COALESCE(read_at, NOW())
              WHERE user_id = :user_id
                AND type = 'social_gift_received'
                AND payload_json LIKE :gift_match"
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':gift_match' => '%"gift_id":' . $giftId . '%',
        ]);
    }

    private function cancelGiftCharge(\PDO $pdo, array $gift): void
    {
        $orderId = (int)($gift['pedido_id'] ?? 0);
        $itemId = (int)($gift['pedido_item_id'] ?? 0);
        if ($orderId <= 0 && $itemId <= 0) {
            return;
        }

        if ($itemId > 0 && $this->tableExists('rest_pedido_items')) {
            $itemColumns = $this->getTableColumns('rest_pedido_items');
            if (in_array('estado', $itemColumns, true)) {
                $stmt = $pdo->prepare("UPDATE rest_pedido_items SET estado = 'cancelado' WHERE id = :id");
                $stmt->execute([':id' => $itemId]);
            }
        }

        if ($orderId > 0 && $this->tableExists('rest_pedidos')) {
            $orderColumns = $this->getTableColumns('rest_pedidos');
            $set = [];
            $params = [':id' => $orderId];
            if (in_array('estado', $orderColumns, true)) {
                $set[] = "estado = 'cancelado'";
            }
            if (in_array('cuenta_abierta', $orderColumns, true)) {
                $set[] = 'cuenta_abierta = 0';
            }
            if (in_array('subtotal', $orderColumns, true)) {
                $set[] = 'subtotal = 0';
            }
            if (in_array('total', $orderColumns, true)) {
                $set[] = 'total = 0';
            }
            if (in_array('updated_at', $orderColumns, true)) {
                $set[] = 'updated_at = NOW()';
            }
            if ($set) {
                $stmt = $pdo->prepare('UPDATE rest_pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
                $stmt->execute($params);
            }
        }
    }

    private function refundRejectedGiftToWallet(\PDO $pdo, array $gift): array
    {
        $giftId = (int)($gift['id'] ?? 0);
        $senderUserId = (int)($gift['sender_user_id'] ?? 0);
        $giftPrice = round((float)($gift['gift_precio'] ?? 0), 2);
        $refundAmount = round($giftPrice * 0.5, 2);

        if (
            (string)($gift['status'] ?? '') !== 'cancelado' ||
            !$this->isRefundableSocialGiftObject($gift) ||
            $giftId <= 0 ||
            $senderUserId <= 0 ||
            $refundAmount <= 0
        ) {
            return [
                'already_applied' => false,
                'refund_amount_mxn' => 0,
                'balance_after_mxn' => null,
                'points_after' => null,
            ];
        }

        return (new RewardsService())->refundToBalance(
            $pdo,
            $senderUserId,
            $refundAmount,
            'social_gift_rejected',
            $giftId,
            'Reembolso 50% por regalo social rechazado',
            [
                'gift_id' => $giftId,
                'gift_price_mxn' => $giftPrice,
                'refund_rate' => 0.5,
                'gift_nombre' => $gift['gift_nombre'] ?? null,
                'recipient_user_id' => isset($gift['recipient_user_id']) ? (int)$gift['recipient_user_id'] : null,
            ]
        );
    }

    private function isRefundableSocialGiftObject(array $gift): bool
    {
        $category = $this->sanitizeNullableString($gift['categoria'] ?? $gift['gift_categoria'] ?? null);
        if ($category !== null) {
            return $this->socialGiftCategoryAllowsRefund($category);
        }

        $productId = (int)($gift['gift_product_id'] ?? 0);
        if ($productId <= 0) {
            return false;
        }

        $tableName = $this->detectGiftProductsTable();
        if ($tableName === null) {
            return false;
        }

        $columns = $this->getTableColumns($tableName);
        if (!in_array('categoria', $columns, true)) {
            return false;
        }

        $row = Database::queryOne(
            "SELECT categoria FROM `{$tableName}` WHERE id = :id LIMIT 1",
            [':id' => $productId]
        );

        return $this->socialGiftCategoryAllowsRefund($row['categoria'] ?? null);
    }

    private function socialGiftCategoryAllowsRefund(mixed $category): bool
    {
        $normalized = strtolower(trim(strtr((string)$category, [
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
            'Á' => 'a', 'É' => 'e', 'Í' => 'i', 'Ó' => 'o', 'Ú' => 'u',
        ])));

        if ($normalized === '') {
            return false;
        }

        return !in_array($normalized, ['alimento', 'alimentos', 'bebida', 'bebidas', 'comida', 'menu', 'postre', 'postres'], true);
    }

    private function buildCoverRequestMessage(string $payerName, float $amount, string $mode): string
    {
        $method = $mode === 'stripe'
            ? 'con tarjeta'
            : ($mode === 'wallet' ? 'con Saldo Amare' : 'agregandola a su cuenta');
        return sprintf('%s quiere cubrir tu consumo por $%.2f MXN %s. Acepta o rechaza la solicitud.', $payerName, $amount, $method);
    }

    private function buildCoverMessage(string $payerName, float $amount, string $mode): string
    {
        $verb = $mode === 'account' ? 'agrego a su cuenta' : 'pago';
        return sprintf('%s %s tu consumo por $%.2f MXN.', $payerName, $verb, $amount);
    }

    private function getStripeSecret(): string
    {
        return StripeConfig::secretKey();
    }

    /**
     * @return array{pedido_id:int,pedido_item_id:int,mesa_id:int,total_agregado:float,consumo_id:?string}
     */
    private function chargeGiftToTableAccount(
        \PDO $pdo,
        int $restaurantId,
        int $tableId,
        int $senderUserId,
        string $senderName,
        int $giftId,
        int $giftProductId,
        array $giftProduct,
        float $price,
        string $recipientName,
        string $recipientTable,
        bool $openAccount = true,
        ?string $paymentIntentId = null,
        ?string $paymentMethod = null
    ): array {
        $orderColumns = $this->getTableColumns('rest_pedidos');
        $dishColumns = $this->getTableColumns('rest_platillos');
        $orderRow = null;
        $consumoId = null;

        if ($openAccount) {
            $selectFields = ['id'];
            if (in_array('consumo_id', $orderColumns, true)) {
                $selectFields[] = 'consumo_id';
            }
            $where = [
                'restaurante_id = :restaurant_id',
                'mesa_id = :table_id',
                "tipo_pedido IN ('eat_in','dine_in')",
            ];
            if (in_array('cuenta_abierta', $orderColumns, true)) {
                $where[] = 'cuenta_abierta = 1';
            } else {
                $where[] = "estado NOT IN ('entregado','cancelado')";
            }
            $orderSort = in_array('created_at', $orderColumns, true)
                ? 'created_at DESC, id DESC'
                : 'id DESC';
            $baseSql = 'SELECT ' . implode(', ', $selectFields) . ' FROM rest_pedidos WHERE ' . implode(' AND ', $where);
            $baseParams = [':restaurant_id' => $restaurantId, ':table_id' => $tableId];

            if (in_array('mobile_usuario_id', $orderColumns, true)) {
                $senderOrderStmt = $pdo->prepare(
                    $baseSql . ' AND mobile_usuario_id = :user_id ORDER BY ' . $orderSort . ' LIMIT 1 FOR UPDATE'
                );
                $senderOrderStmt->execute($baseParams + [':user_id' => $senderUserId]);
                $orderRow = $senderOrderStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }

            if ($orderRow === null) {
                $orderStmt = $pdo->prepare(
                    $baseSql . ' ORDER BY ' . $orderSort . ' LIMIT 1 FOR UPDATE'
                );
                $orderStmt->execute($baseParams);
                $orderRow = $orderStmt->fetch(\PDO::FETCH_ASSOC) ?: null;
            }

            $anchorOrderId = (int)($orderRow['id'] ?? 0);
            $consumoId = isset($orderRow['consumo_id']) && is_string($orderRow['consumo_id']) && trim($orderRow['consumo_id']) !== ''
                ? trim($orderRow['consumo_id'])
                : null;
            error_log('SocialController::chargeGiftToTableAccount ORDER LOOKUP restaurant_id=' . $restaurantId .
                ' table_id=' . $tableId .
                ' sender_user_id=' . $senderUserId .
                ' anchor_pedido_id=' . $anchorOrderId .
                ' consumo_id=' . (string)($consumoId ?? ''));

            if ($consumoId === null && in_array('consumo_id', $orderColumns, true)) {
                $consumoId = $this->resolveOpenConsumptionIdForGift($pdo, $restaurantId, $senderUserId, $tableId);
            }
        }

        $orderResult = $this->createGiftChargeOrder(
            $pdo,
            $restaurantId,
            $tableId,
            $senderUserId,
            $senderName,
            $consumoId,
            $price,
            $recipientName,
            $recipientTable,
            $openAccount,
            $paymentIntentId,
            $paymentMethod
        );
        $orderId = (int)$orderResult['pedido_id'];
        $consumoId = $orderResult['consumo_id'];
        error_log('SocialController::chargeGiftToTableAccount ORDER CREATED pedido_id=' . $orderId .
            ' consumo_id=' . (string)($consumoId ?? ''));

        $mappingStmt = $pdo->prepare(
            'SELECT platillo_id
               FROM social_gift_account_products
              WHERE restaurante_id = :restaurant_id AND gift_product_id = :gift_product_id
               LIMIT 1 FOR UPDATE'
        );
        $mappingStmt->execute([':restaurant_id' => $restaurantId, ':gift_product_id' => $giftProductId]);
        $dishId = (int)($mappingStmt->fetchColumn() ?: 0);
        if ($dishId <= 0) {
            $existingDishStmt = $pdo->prepare(
                'SELECT id
                   FROM rest_platillos
                  WHERE restaurante_id = :restaurant_id AND codigo = :code
                  LIMIT 1 FOR UPDATE'
            );
            $existingDishStmt->execute([
                ':restaurant_id' => $restaurantId,
                ':code' => 'SG-' . $giftProductId,
            ]);
            $dishId = (int)($existingDishStmt->fetchColumn() ?: 0);
        }
        $dishData = [
            'restaurante_id' => $restaurantId,
            'categoria_id' => null,
            'codigo' => 'SG-' . $giftProductId,
            'es_armado' => 0,
            'nombre' => 'Regalo: ' . (string)($giftProduct['nombre'] ?? 'Detalle social'),
            'descripcion' => $giftProduct['descripcion'] ?? 'Regalo enviado desde el modo social',
            'precio' => $price,
            'imagen' => $giftProduct['imagen'] ?? null,
            'tiempo_preparacion_min' => 0,
            'disponible' => 0,
            'activo' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $dishWasExisting = $dishId > 0;
        if ($dishId <= 0) {
            $dishId = $this->insertDynamicRow($pdo, 'rest_platillos', $dishData);
            if ($dishId <= 0) throw new \RuntimeException('No se pudo crear la partida contable del regalo.');
            error_log('SocialController::chargeGiftToTableAccount DISH CREATED dish_id=' . $dishId .
                ' gift_product_id=' . $giftProductId .
                ' code=' . $dishData['codigo']);
        }
        if ($dishWasExisting) {
            $set = ['nombre = :name', 'descripcion = :description', 'precio = :price'];
            $params = [
                ':name' => $dishData['nombre'],
                ':description' => $dishData['descripcion'],
                ':price' => $price,
                ':id' => $dishId,
            ];
            if (in_array('imagen', $dishColumns, true)) {
                $set[] = 'imagen = :image';
                $params[':image'] = $dishData['imagen'];
            }
            if (in_array('disponible', $dishColumns, true)) {
                $set[] = 'disponible = 0';
            }
            if (in_array('activo', $dishColumns, true)) {
                $set[] = 'activo = 0';
            }
            $whereDish = ['id = :id'];
            if (in_array('restaurante_id', $dishColumns, true)) {
                $whereDish[] = 'restaurante_id = :restaurant_id';
                $params[':restaurant_id'] = $restaurantId;
            }
            $updateDish = $pdo->prepare(
                'UPDATE rest_platillos SET ' . implode(', ', $set) .
                ' WHERE ' . implode(' AND ', $whereDish)
            );
            $updateDish->execute($params);
            error_log('SocialController::chargeGiftToTableAccount DISH UPDATED dish_id=' . $dishId .
                ' gift_product_id=' . $giftProductId);
        }

        if ($dishId > 0) {
            $mapping = $pdo->prepare(
                'INSERT INTO social_gift_account_products
                    (restaurante_id, gift_product_id, platillo_id, created_at)
                 VALUES (:restaurant_id, :gift_product_id, :dish_id, NOW())
                 ON DUPLICATE KEY UPDATE platillo_id = VALUES(platillo_id), updated_at = NOW()'
            );
            $mapping->execute([
                ':restaurant_id' => $restaurantId,
                ':gift_product_id' => $giftProductId,
                ':dish_id' => $dishId,
            ]);
            error_log('SocialController::chargeGiftToTableAccount MAPPING UPSERT restaurant_id=' . $restaurantId .
                ' gift_product_id=' . $giftProductId .
                ' dish_id=' . $dishId);
        }

        $notes = 'Regalo para ' . $recipientName . ' - ' . $this->formatMesaLabel($recipientTable);
        $notesPayload = json_encode([
            'notas' => $notes,
            'extras' => [],
        ], JSON_UNESCAPED_UNICODE);
        if ($notesPayload === false) {
            $notesPayload = $notes;
        }
        if (strlen($notesPayload) > 255) {
            $notesPayload = substr($notes, 0, 255);
        }
        $itemId = $this->insertDynamicRow($pdo, 'rest_pedido_items', [
            'pedido_id' => $orderId,
            'platillo_id' => $dishId,
            'cantidad' => 1,
            'precio_unit' => $price,
            'subtotal' => $price,
            'notas' => $notesPayload,
            'estado' => 'pendiente',
            'origen' => 'menu',
        ]);
        if ($itemId <= 0) throw new \RuntimeException('No se pudo agregar el regalo a la cuenta.');
        error_log('SocialController::chargeGiftToTableAccount ITEM CREATED pedido_item_id=' . $itemId .
            ' pedido_id=' . $orderId .
            ' dish_id=' . $dishId .
            ' price=' . $price);

        return [
            'pedido_id' => $orderId,
            'pedido_item_id' => $itemId,
            'mesa_id' => $tableId,
            'total_agregado' => $price,
            'consumo_id' => $consumoId,
        ];
    }

    private function markPaidGiftChargeOrder(\PDO $pdo, array $gift, string $paymentMethod, ?string $paymentIntentId = null): void
    {
        $orderId = (int)($gift['pedido_id'] ?? 0);
        if ($orderId <= 0 || !$this->tableExists('rest_pedidos')) {
            return;
        }

        $columns = $this->getTableColumns('rest_pedidos');
        $set = [];
        $params = [
            ':id' => $orderId,
            ':total' => round((float)($gift['gift_precio'] ?? 0), 2),
        ];

        if (in_array('cuenta_abierta', $columns, true)) {
            $set[] = 'cuenta_abierta = 1';
        }
        if (in_array('metodo_pago', $columns, true)) {
            $set[] = 'metodo_pago = COALESCE(metodo_pago, :payment_method)';
            $params[':payment_method'] = $paymentMethod;
        } elseif (in_array('payment_method', $columns, true)) {
            $set[] = 'payment_method = COALESCE(payment_method, :payment_method)';
            $params[':payment_method'] = $paymentMethod;
        }
        if ($paymentIntentId !== null) {
            if (in_array('stripe_payment_intent_id', $columns, true)) {
                $set[] = 'stripe_payment_intent_id = COALESCE(stripe_payment_intent_id, :payment_intent_id)';
                $params[':payment_intent_id'] = $paymentIntentId;
            } elseif (in_array('payment_intent_id', $columns, true)) {
                $set[] = 'payment_intent_id = COALESCE(payment_intent_id, :payment_intent_id)';
                $params[':payment_intent_id'] = $paymentIntentId;
            }
        }
        if (in_array('pagado_at', $columns, true)) {
            $set[] = 'pagado_at = COALESCE(pagado_at, NOW())';
        }
        if (in_array('salida_qr_generado_at', $columns, true)) {
            $set[] = 'salida_qr_generado_at = NULL';
        }
        if (in_array('salida_token', $columns, true)) {
            $set[] = 'salida_token = NULL';
        }
        if (in_array('total_pagado', $columns, true)) {
            $set[] = 'total_pagado = COALESCE(total_pagado, :total)';
        }
        if (in_array('total_con_propina', $columns, true)) {
            $set[] = 'total_con_propina = COALESCE(total_con_propina, :total)';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        if (!$set) {
            return;
        }

        $statement = $pdo->prepare('UPDATE rest_pedidos SET ' . implode(', ', $set) . ' WHERE id = :id');
        $statement->execute($params);
        $this->refundRejectedGiftToWallet($pdo, $gift);
    }

    /**
     * @return array{pedido_id:int,consumo_id:?string}
     */
    private function createGiftChargeOrder(
        \PDO $pdo,
        int $restaurantId,
        int $tableId,
        int $senderUserId,
        string $senderName,
        ?string $consumoId,
        float $price,
        string $recipientName,
        string $recipientTable,
        bool $openAccount = true,
        ?string $paymentIntentId = null,
        ?string $paymentMethod = null
    ): array {
        $orderColumns = $this->getTableColumns('rest_pedidos');
        $notes = 'Regalo social para ' . $recipientName . ' - ' . $this->formatMesaLabel($recipientTable);
        $finalConsumoId = null;
        if (in_array('consumo_id', $orderColumns, true)) {
            $finalConsumoId = $consumoId ?: $this->resolveOpenConsumptionIdForGift($pdo, $restaurantId, $senderUserId, $tableId);
        }
        $orderData = [
            'restaurante_id' => $restaurantId,
            'mobile_usuario_id' => $senderUserId,
            'folio' => 'AM-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10),
            'estado' => 'listo',
            'subtotal' => $price,
            'total' => $price,
            'tipo_pedido' => 'eat_in',
            'pedido_origen' => 'cliente',
            'cliente_nombre' => substr($senderName, 0, 120),
            'notas' => $notes,
            'cuenta_abierta' => 1,
            'mesa_id' => $tableId,
            'consumo_id' => $finalConsumoId,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('tipo_origen', $orderColumns, true)) {
            $orderData['tipo_origen'] = 'menu';
        }
        if ($paymentIntentId !== null) {
            if (in_array('stripe_payment_intent_id', $orderColumns, true)) {
                $orderData['stripe_payment_intent_id'] = $paymentIntentId;
            } elseif (in_array('payment_intent_id', $orderColumns, true)) {
                $orderData['payment_intent_id'] = $paymentIntentId;
            }
            $paymentMethod = $paymentMethod ?? 'tarjeta';
        }
        if ($paymentMethod !== null) {
            if (in_array('metodo_pago', $orderColumns, true)) {
                $orderData['metodo_pago'] = $paymentMethod;
            } elseif (in_array('payment_method', $orderColumns, true)) {
                $orderData['payment_method'] = $paymentMethod;
            }
        }
        if (!$openAccount) {
            if (in_array('pagado_at', $orderColumns, true)) {
                $orderData['pagado_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('total_pagado', $orderColumns, true)) {
                $orderData['total_pagado'] = $price;
            }
            if (in_array('total_con_propina', $orderColumns, true)) {
                $orderData['total_con_propina'] = $price;
            }
        }

        $orderId = $this->insertDynamicRow($pdo, 'rest_pedidos', $orderData);
        if ($orderId <= 0) {
            throw new \RuntimeException('No se pudo crear el pedido del regalo en la cuenta.');
        }

        $this->markTableOccupiedForGift($pdo, $tableId);

        return [
            'pedido_id' => $orderId,
            'consumo_id' => $finalConsumoId,
        ];
    }

    private function createOpenGiftAccount(
        \PDO $pdo,
        int $restaurantId,
        int $tableId,
        int $senderUserId,
        string $senderName
    ): int {
        $orderColumns = $this->getTableColumns('rest_pedidos');
        $accountData = [
            'restaurante_id' => $restaurantId,
            'mobile_usuario_id' => $senderUserId,
            'folio' => 'AM-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10),
            'estado' => 'pendiente',
            'subtotal' => 0,
            'total' => 0,
            'tipo_pedido' => 'eat_in',
            'pedido_origen' => 'cliente',
            'cliente_nombre' => substr($senderName, 0, 120),
            'notas' => 'Cuenta abierta automaticamente para regalo social',
            'cuenta_abierta' => 1,
            'mesa_id' => $tableId,
            'consumo_id' => in_array('consumo_id', $orderColumns, true)
                ? $this->resolveOpenConsumptionIdForGift($pdo, $restaurantId, $senderUserId, $tableId)
                : null,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if (in_array('tipo_origen', $orderColumns, true)) {
            $accountData['tipo_origen'] = 'menu';
        }

        $accountId = $this->insertDynamicRow($pdo, 'rest_pedidos', $accountData);

        if ($accountId <= 0) {
            throw new \RuntimeException('No se pudo abrir una cuenta para cargar el regalo.');
        }

        $this->markTableOccupiedForGift($pdo, $tableId);

        return $accountId;
    }

    private function resolveOpenConsumptionIdForGift(\PDO $pdo, int $restaurantId, int $senderUserId, int $tableId): string
    {
        $orderColumns = $this->getTableColumns('rest_pedidos');
        $where = [
            'p.restaurante_id = :restaurant_id',
            'p.mesa_id = :table_id',
            "p.tipo_pedido = 'eat_in'",
            'p.consumo_id IS NOT NULL',
            "p.consumo_id <> ''",
        ];
        if (in_array('mobile_usuario_id', $orderColumns, true)) {
            $where[] = 'p.mobile_usuario_id = :user_id';
        }
        if (in_array('salida_qr_generado_at', $orderColumns, true)) {
            $where[] = 'p.salida_qr_generado_at IS NULL';
        }
        if (in_array('salida_validado_at', $orderColumns, true)) {
            $where[] = 'p.salida_validado_at IS NULL';
        }
        $closedVisitConditions = [];
        if (in_array('salida_qr_generado_at', $orderColumns, true)) {
            $closedVisitConditions[] = 'closed.salida_qr_generado_at IS NOT NULL';
        }
        if (in_array('salida_validado_at', $orderColumns, true)) {
            $closedVisitConditions[] = 'closed.salida_validado_at IS NOT NULL';
        }
        if (!empty($closedVisitConditions)) {
            $where[] = 'NOT EXISTS (
                SELECT 1
                  FROM rest_pedidos closed
                 WHERE closed.consumo_id = p.consumo_id
                   AND closed.tipo_pedido = \'eat_in\'
                   AND (' . implode(' OR ', $closedVisitConditions) . ')
                 LIMIT 1
            )';
        }

        $existing = $pdo->prepare(
            'SELECT p.consumo_id FROM rest_pedidos p WHERE ' . implode(' AND ', $where) .
            ' ORDER BY ' . (in_array('cuenta_abierta', $orderColumns, true) ? 'p.cuenta_abierta DESC, ' : '') . 'p.id DESC LIMIT 1'
        );
        $params = [
            ':restaurant_id' => $restaurantId,
            ':table_id' => $tableId,
        ];
        if (in_array('mobile_usuario_id', $orderColumns, true)) {
            $params[':user_id'] = $senderUserId;
        }
        $existing->execute($params);
        $consumoId = $existing->fetchColumn();
        if (is_string($consumoId) && trim($consumoId) !== '') {
            return trim($consumoId);
        }

        return 'CON-' . date('Ymd') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
    }

    private function markTableOccupiedForGift(\PDO $pdo, int $tableId): void
    {
        if ($tableId <= 0 || !$this->tableExists('rest_mesas')) {
            return;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $set = [];
        $params = [':id' => $tableId];

        if (in_array('ocupada', $columns, true)) {
            $set[] = 'ocupada = :ocupada';
            $params[':ocupada'] = 1;
        }
        if (in_array('disponible', $columns, true)) {
            $set[] = 'disponible = :disponible';
            $params[':disponible'] = 0;
        }
        if (in_array('estado', $columns, true)) {
            $set[] = 'estado = :estado';
            $params[':estado'] = 'ocupada';
        }

        if (!$set) {
            return;
        }

        $statement = $pdo->prepare(
            'UPDATE rest_mesas SET ' . implode(', ', $set) . ' WHERE id = :id'
        );
        $statement->execute($params);
    }

    private function insertDynamicRow(\PDO $pdo, string $table, array $data): int
    {
        $available = $this->getTableColumns($table);
        $filtered = array_filter(
            $data,
            static fn(mixed $value, string $column): bool => in_array($column, $available, true),
            ARRAY_FILTER_USE_BOTH
        );
        if (!$filtered) throw new \RuntimeException("No hay columnas compatibles para {$table}.");
        $columns = array_keys($filtered);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $statement = $pdo->prepare(
            'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`)' .
            ' VALUES (' . implode(',', $placeholders) . ')'
        );
        $params = [];
        foreach ($filtered as $column => $value) $params[':' . $column] = $value;
        $statement->execute($params);
        return (int)$pdo->lastInsertId();
    }

    private function giftPaymentResponse(array $gift): array
    {
        return [
            'id' => (int)$gift['id'],
            'folio' => $gift['folio'] ?? null,
            'mesa_id' => (int)$gift['mesa_id'],
            'mesa_label' => $this->formatMesaLabel((string)($gift['recipient_mesa'] ?? $gift['mesa_id'])),
            'gift_nombre' => $gift['gift_nombre'],
            'gift_precio' => (float)$gift['gift_precio'],
            'categoria' => $gift['categoria'] ?? $gift['gift_categoria'] ?? null,
            'recipient_nombre' => $gift['recipient_nombre'],
            'sender_nombre' => $gift['sender_nombre'],
            'recipient_mesa' => $gift['recipient_mesa'] ?? null,
            'status' => $gift['status'],
            'sender_mesa_id' => isset($gift['sender_mesa_id']) ? (int)$gift['sender_mesa_id'] : null,
            'pedido_id' => isset($gift['pedido_id']) ? (int)$gift['pedido_id'] : null,
            'pedido_item_id' => isset($gift['pedido_item_id']) ? (int)$gift['pedido_item_id'] : null,
            'charged_to_account' => !empty($gift['cargado_cuenta_at']),
            'amare_wallet_used_mxn' => isset($gift['amare_wallet_used_mxn']) ? (float)$gift['amare_wallet_used_mxn'] : null,
            'amare_discount_mxn' => isset($gift['amare_discount_mxn']) ? (float)$gift['amare_discount_mxn'] : null,
            'amare_points_redeemed' => isset($gift['amare_points_redeemed']) ? (int)$gift['amare_points_redeemed'] : null,
            'amare_points_earned' => isset($gift['amare_points_earned']) ? (int)$gift['amare_points_earned'] : null,
        ];
    }

    public function restaurantTables(int $restaurantId): void
    {
        AuthMiddleware::authenticate();

        if (!$this->tableExists('rest_mesas')) {
            Response::success([]);
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $activeColumn = $this->firstExistingColumn($columns, ['activo']);
        $orderColumn = $this->firstExistingColumn($columns, ['orden', 'numero_mesa', 'numero', 'mesa', 'nombre', 'qr_codigo', 'id']);

        if ($idColumn === null || $labelColumn === null) {
            Response::success([]);
        }

        $sql = "SELECT `{$idColumn}` AS id, `{$labelColumn}` AS mesa_label
                  FROM `rest_mesas`
                 WHERE 1 = 1";
        $params = [];

        if ($restaurantColumn !== null) {
            $sql .= " AND `{$restaurantColumn}` = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }

        if ($activeColumn !== null) {
            $sql .= " AND `{$activeColumn}` = 1";
        }

        if ($orderColumn !== null) {
            $sql .= " ORDER BY `{$orderColumn}` ASC";
        }

        $mesas = Database::query($sql, $params);
        $result = [];

        foreach ($mesas as $mesa) {
            $value = trim((string)($mesa['mesa_label'] ?? ''));
            if ($value === '') {
                continue;
            }

            $result[] = [
                'id' => (int)($mesa['id'] ?? 0),
                'label' => $this->formatMesaLabel($value),
                'value' => $value,
            ];
        }

        Response::success($result);
    }

    public function scanTable(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $payload = trim((string)($input['payload'] ?? ''));
        $restaurantHint = isset($input['restaurante_id']) && $input['restaurante_id'] !== null
            ? (int)$input['restaurante_id']
            : null;

        if ($payload === '') {
            Response::validationError(['payload' => ['El QR de mesa es obligatorio']]);
        }

        if (!$this->tableExists('rest_mesas')) {
            Response::serverError('La base de datos aún no tiene rest_mesas configurado.');
        }

        if (!$this->hasMesaColumn()) {
            Response::serverError('La base de datos aún no tiene la columna mesa. Ejecuta primero la migración 018.');
        }

        $scanData = $this->parseTableQrPayload($payload);
        $qrRestaurantId = $scanData['restaurante_id'] ?? null;
        $restaurantId = $qrRestaurantId ?? $restaurantHint;
        $tableId = $scanData['mesa_id'] ?? null;
        $tableCode = $scanData['mesa'] ?? $scanData['codigo'] ?? null;
        $hasStrongLookup = ($tableId !== null && $tableId > 0) || !empty($scanData['codigo']);

        $mesa = $this->findScannedTable($restaurantId, $tableId, $tableCode);
        if ($mesa === null && $restaurantId !== null && $qrRestaurantId === null && $hasStrongLookup) {
            $mesa = $this->findScannedTable(null, $tableId, $tableCode);
        }
        if ($mesa === null) {
            Response::notFound('Mesa no encontrada o QR inválido');
        }

        $restaurantId = (int)$mesa['restaurante_id'];
        $branch = $this->findEatInBranch($restaurantId);
        if ($branch === null) {
            Response::error('Esta sucursal no tiene habilitado Comer aquí.', 409);
        }

        $this->assertCanUseTableSession(
            (int)$user->id,
            $restaurantId,
            (int)$mesa['id'],
            $mesa['label'] ?? $mesa['value']
        );

        Database::rowCount(
            "UPDATE mobile_usuarios
                SET current_restaurante_id = :restaurant_id,
                    mesa = :mesa,
                    updated_at = NOW()
              WHERE id = :user_id",
            [
                ':restaurant_id' => $restaurantId,
                ':mesa' => $mesa['value'],
                ':user_id' => $user->id,
            ]
        );

        Response::success([
            'restaurante_id' => $restaurantId,
            'mesa_id' => (int)$mesa['id'],
            'mesa_label' => $mesa['label'],
            'mesa_value' => $mesa['value'],
            'branch' => $branch,
        ], 'Mesa escaneada correctamente');
    }

    public function tableSessionDiagnostic(): void
    {
        $user = AuthMiddleware::authenticate();
        $restaurantId = isset($_GET['restaurant_id']) && $_GET['restaurant_id'] !== ''
            ? (int)$_GET['restaurant_id']
            : null;
        $tableId = isset($_GET['table_id']) && $_GET['table_id'] !== ''
            ? (int)$_GET['table_id']
            : null;
        $tableLabel = $this->sanitizeNullableString($_GET['mesa'] ?? $_GET['table_label'] ?? null) ?? '';

        Response::success($this->buildTableSessionDiagnostic(
            (int)$user->id,
            $restaurantId,
            $tableId,
            $tableLabel
        ));
    }

    public function resetTableSessionForTesting(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!$this->allowsTestingTableSessionReset()) {
            Response::error('El reset de cuentas de prueba no esta habilitado en esta API. Activa AMARE_ALLOW_TEST_SESSION_RESET=true solo en ambientes de prueba.', 403);
        }

        $result = $this->resetActiveTableVisitForUser((int)$user->id);

        Response::success([
            'reset' => true,
            'affected_orders' => $result['affected_orders'],
            'active_visit' => $result['active_visit'],
        ], $result['affected_orders'] > 0 ? 'Cuenta de prueba liberada.' : 'No habia una cuenta activa por liberar.');
    }

    private function assertCanUseTableSession(int $userId, int $restaurantId, int $tableId, string $tableLabel = ''): void
    {
        $diagnostic = $this->buildTableSessionDiagnostic($userId, $restaurantId, $tableId, $tableLabel);
        if (empty($diagnostic['blocked'])) {
            return;
        }

        $encodedDiagnostic = json_encode($diagnostic, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encodedDiagnostic)) {
            error_log('SocialController::TABLE_SESSION_ACTIVE ' . $encodedDiagnostic);
        }

        Response::error(
            (string)$diagnostic['message'],
            409,
            'TABLE_SESSION_ACTIVE'
        );
    }

    private function buildTableSessionDiagnostic(
        int $userId,
        ?int $nextRestaurantId = null,
        ?int $nextTableId = null,
        string $nextTableLabel = ''
    ): array {
        $activeVisit = $this->findActiveTableVisitForUser($userId);
        $nextTable = [
            'restaurante_id' => $nextRestaurantId,
            'mesa_id' => $nextTableId,
            'mesa_label' => $nextTableId !== null && $nextTableId > 0
                ? $this->formatMesaLabel(trim($nextTableLabel) !== '' ? $nextTableLabel : (string)$nextTableId)
                : (trim($nextTableLabel) !== '' ? $this->formatMesaLabel($nextTableLabel) : null),
        ];

        if ($activeVisit === null) {
            return [
                'blocked' => false,
                'reason_code' => null,
                'message' => 'No tienes una cuenta activa bloqueando el cambio de mesa.',
                'active_visit' => null,
                'next_table' => $nextTable,
            ];
        }

        $activeVisitDetails = $this->describeActiveTableVisit($activeVisit, $userId);
        $activeRestaurantId = (int)($activeVisitDetails['restaurante_id'] ?? $activeVisit['restaurante_id'] ?? 0);
        $activeTableId = isset($activeVisitDetails['mesa_id']) && $activeVisitDetails['mesa_id'] !== null
            ? (int)$activeVisitDetails['mesa_id']
            : (int)($activeVisit['mesa_id'] ?? 0);
        $sameTable = $nextRestaurantId !== null
            && $nextTableId !== null
            && $activeRestaurantId === $nextRestaurantId
            && $activeTableId === $nextTableId;

        if ($sameTable) {
            return [
                'blocked' => false,
                'reason_code' => null,
                'message' => 'Ya estás asociado a esta mesa.',
                'active_visit' => $activeVisitDetails,
                'next_table' => $nextTable,
            ];
        }

        $activeLabel = (string)($activeVisitDetails['mesa_label'] ?? 'tu mesa actual');
        $nextLabel = (string)($nextTable['mesa_label'] ?? 'la nueva mesa');

        return [
            'blocked' => true,
            'reason_code' => 'TABLE_SESSION_ACTIVE',
            'message' => 'Primero cierra y paga tu cuenta de ' . $activeLabel . ', valida tu QR de salida con hostess y después escanea ' . $nextLabel . '.',
            'active_visit' => $activeVisitDetails,
            'next_table' => $nextTable,
        ];
    }

    private function describeActiveTableVisit(array $activeVisit, int $userId): array
    {
        $order = $activeVisit;
        if ($this->tableExists('rest_pedidos') && isset($activeVisit['id'])) {
            $found = Database::queryOne(
                'SELECT * FROM rest_pedidos WHERE id = :id AND mobile_usuario_id = :user_id LIMIT 1',
                [':id' => (int)$activeVisit['id'], ':user_id' => $userId]
            );
            if ($found) {
                $order = $found;
            }
        }

        $mesaId = isset($order['mesa_id']) && $order['mesa_id'] !== null ? (int)$order['mesa_id'] : null;
        $reasons = [];
        if (isset($order['cuenta_abierta']) && (int)$order['cuenta_abierta'] === 1) {
            $reasons[] = 'cuenta_abierta';
        }
        if (!empty($order['salida_qr_generado_at']) && empty($order['salida_validado_at'])) {
            $reasons[] = 'salida_qr_pendiente_validacion';
        }
        if (empty($reasons) && isset($order['estado']) && !in_array((string)$order['estado'], ['entregado', 'cancelado'], true)) {
            $reasons[] = 'pedido_activo';
        }

        $mesaLabel = $mesaId !== null
            ? ($this->tableLabelForDiagnostic($mesaId) ?? $this->formatMesaLabel((string)$mesaId))
            : 'tu mesa actual';

        return [
            'pedido_id' => isset($order['id']) ? (int)$order['id'] : null,
            'folio' => $order['folio'] ?? null,
            'restaurante_id' => isset($order['restaurante_id']) ? (int)$order['restaurante_id'] : null,
            'mesa_id' => $mesaId,
            'mesa_label' => $mesaLabel,
            'consumo_id' => $order['consumo_id'] ?? null,
            'estado' => $order['estado'] ?? null,
            'tipo_pedido' => $order['tipo_pedido'] ?? null,
            'cuenta_abierta' => isset($order['cuenta_abierta']) ? (bool)$order['cuenta_abierta'] : null,
            'salida_qr_generado_at' => $order['salida_qr_generado_at'] ?? null,
            'salida_validado_at' => $order['salida_validado_at'] ?? null,
            'pagado_at' => $order['pagado_at'] ?? null,
            'cerrado_at' => $order['cerrado_at'] ?? null,
            'metodo_pago' => $order['metodo_pago'] ?? $order['payment_method'] ?? null,
            'subtotal' => isset($order['subtotal']) ? (float)$order['subtotal'] : null,
            'total' => isset($order['total']) ? (float)$order['total'] : null,
            'notas' => $order['notas'] ?? null,
            'created_at' => $order['created_at'] ?? null,
            'block_reasons' => $reasons,
            'social_gifts' => $this->socialGiftOrdersForDiagnostic((int)($order['id'] ?? 0)),
        ];
    }

    private function tableLabelForDiagnostic(int $tableId): ?string
    {
        if ($tableId <= 0 || !$this->tableExists('rest_mesas')) {
            return null;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);

        if ($idColumn === null || $labelColumn === null) {
            return null;
        }

        $row = Database::queryOne(
            "SELECT `{$labelColumn}` AS mesa_label FROM `rest_mesas` WHERE `{$idColumn}` = :id LIMIT 1",
            [':id' => $tableId]
        );
        $label = trim((string)($row['mesa_label'] ?? ''));

        return $label !== '' ? $this->formatMesaLabel($label) : null;
    }

    private function socialGiftOrdersForDiagnostic(int $orderId): array
    {
        if ($orderId <= 0 || !$this->tableExists('social_gift_orders')) {
            return [];
        }

        $giftColumns = $this->getTableColumns('social_gift_orders');
        if (!in_array('pedido_id', $giftColumns, true)) {
            return [];
        }

        $rows = Database::query(
            'SELECT * FROM social_gift_orders WHERE pedido_id = :order_id ORDER BY id DESC LIMIT 5',
            [':order_id' => $orderId]
        );

        return array_map(static function (array $gift): array {
            return [
                'id' => isset($gift['id']) ? (int)$gift['id'] : null,
                'folio' => $gift['folio'] ?? null,
                'status' => $gift['status'] ?? null,
                'gift_nombre' => $gift['gift_nombre'] ?? null,
                'gift_precio' => isset($gift['gift_precio']) ? (float)$gift['gift_precio'] : null,
                'recipient_nombre' => $gift['recipient_nombre'] ?? null,
                'cargado_cuenta_at' => $gift['cargado_cuenta_at'] ?? null,
                'pagado_at' => $gift['pagado_at'] ?? null,
                'pedido_item_id' => isset($gift['pedido_item_id']) ? (int)$gift['pedido_item_id'] : null,
                'amare_wallet_used_mxn' => isset($gift['amare_wallet_used_mxn']) ? (float)$gift['amare_wallet_used_mxn'] : null,
            ];
        }, $rows);
    }

    private function findActiveTableVisitForUser(int $userId): ?array
    {
        if ($userId <= 0 || !$this->tableExists('rest_pedidos')) {
            return null;
        }

        $columns = $this->getTableColumns('rest_pedidos');
        if (!in_array('mobile_usuario_id', $columns, true) || !in_array('mesa_id', $columns, true)) {
            return null;
        }

        $this->repairCompletedSocialGiftOrdersForUser($userId, $columns);

        $where = [
            'mobile_usuario_id = :user_id',
            "tipo_pedido = 'eat_in'",
            'mesa_id IS NOT NULL',
            'mesa_id > 0',
        ];

        if (in_array('salida_validado_at', $columns, true)) {
            $where[] = 'salida_validado_at IS NULL';
        }

        if (in_array('cuenta_abierta', $columns, true) && in_array('salida_qr_generado_at', $columns, true)) {
            $where[] = '(cuenta_abierta = 1 OR salida_qr_generado_at IS NOT NULL)';
        } elseif (in_array('cuenta_abierta', $columns, true)) {
            $where[] = 'cuenta_abierta = 1';
        } elseif (in_array('estado', $columns, true)) {
            $where[] = "estado NOT IN ('entregado','cancelado')";
        }
        if ($this->tableExists('social_gift_orders')) {
            $giftColumns = $this->getTableColumns('social_gift_orders');
            if (in_array('pedido_id', $giftColumns, true)) {
                $completedGiftCondition = $this->completedSocialGiftOrderCondition('sg', $giftColumns);
                $where[] = "NOT EXISTS (
                    SELECT 1
                      FROM social_gift_orders sg
                     WHERE sg.pedido_id = rp.id
                       AND {$completedGiftCondition}
                     LIMIT 1
                )";
            }
        }
        $giftOnlyMarkers = [];
        if ($this->tableExists('social_gift_orders') && in_array('pedido_id', $this->getTableColumns('social_gift_orders'), true)) {
            $giftOnlyMarkers[] = "EXISTS (
                SELECT 1
                  FROM social_gift_orders sg2
                 WHERE sg2.pedido_id = rp.id
                 LIMIT 1
            )";
        }
        if (in_array('notas', $columns, true)) {
            $giftOnlyMarkers[] = "COALESCE(rp.notas, '') LIKE 'Regalo social para %'";
        }
        if (!empty($giftOnlyMarkers) && in_array('cuenta_abierta', $columns, true)) {
            $where[] = "NOT (
                COALESCE(rp.cuenta_abierta, 0) = 0
                AND (" . implode(' OR ', $giftOnlyMarkers) . ")
            )";
        }

        return Database::queryOne(
            'SELECT id, restaurante_id, mesa_id, consumo_id, salida_qr_generado_at, salida_validado_at
               FROM rest_pedidos rp
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY created_at DESC, id DESC
              LIMIT 1',
            [':user_id' => $userId]
        );
    }

    private function repairCompletedSocialGiftOrdersForUser(int $userId, array $orderColumns): void
    {
        if (!$this->tableExists('social_gift_orders')) {
            return;
        }

        $giftColumns = $this->getTableColumns('social_gift_orders');
        if (!in_array('pedido_id', $giftColumns, true) || !in_array('mobile_usuario_id', $orderColumns, true)) {
            return;
        }

        $set = [];
        $params = [':user_id' => $userId];

        if (in_array('cuenta_abierta', $orderColumns, true)) {
            $set[] = 'rp.cuenta_abierta = 0';
        }
        if (in_array('metodo_pago', $orderColumns, true)) {
            $set[] = 'rp.metodo_pago = COALESCE(rp.metodo_pago, :payment_method)';
            $params[':payment_method'] = 'social_gift';
        } elseif (in_array('payment_method', $orderColumns, true)) {
            $set[] = 'rp.payment_method = COALESCE(rp.payment_method, :payment_method)';
            $params[':payment_method'] = 'social_gift';
        }
        if (in_array('pagado_at', $orderColumns, true)) {
            $set[] = in_array('pagado_at', $giftColumns, true)
                ? 'rp.pagado_at = COALESCE(rp.pagado_at, sg.pagado_at, NOW())'
                : 'rp.pagado_at = COALESCE(rp.pagado_at, NOW())';
        }
        if (in_array('cerrado_at', $orderColumns, true)) {
            $set[] = 'rp.cerrado_at = COALESCE(rp.cerrado_at, NOW())';
        }
        if (in_array('salida_qr_generado_at', $orderColumns, true)) {
            $set[] = 'rp.salida_qr_generado_at = NULL';
        }
        if (in_array('salida_token', $orderColumns, true)) {
            $set[] = 'rp.salida_token = NULL';
        }
        if (in_array('total_pagado', $orderColumns, true)) {
            $set[] = 'rp.total_pagado = COALESCE(rp.total_pagado, rp.total)';
        }
        if (in_array('total_con_propina', $orderColumns, true)) {
            $set[] = 'rp.total_con_propina = COALESCE(rp.total_con_propina, rp.total)';
        }
        if (in_array('updated_at', $orderColumns, true)) {
            $set[] = 'rp.updated_at = NOW()';
        }

        if (!$set) {
            return;
        }

        try {
            Database::rowCount(
                'UPDATE rest_pedidos rp
                  JOIN social_gift_orders sg ON sg.pedido_id = rp.id
                   SET ' . implode(', ', $set) . '
                 WHERE rp.mobile_usuario_id = :user_id
                   AND ' . $this->completedSocialGiftOrderCondition('sg', $giftColumns),
                $params
            );
        } catch (\Throwable $exception) {
            error_log('SocialController::repairCompletedSocialGiftOrdersForUser ERROR: ' . $exception->getMessage());
        }
    }

    private function completedSocialGiftOrderCondition(string $alias, array $giftColumns): string
    {
        $conditions = [];

        if (in_array('pagado_at', $giftColumns, true)) {
            $conditions[] = "{$alias}.pagado_at IS NOT NULL";
        }
        if (in_array('amare_wallet_used_mxn', $giftColumns, true)) {
            $conditions[] = "COALESCE({$alias}.amare_wallet_used_mxn, 0) > 0";
        }
        if (in_array('status', $giftColumns, true)) {
            $completedStatus = "{$alias}.status IN ('listo','reclamado','entregado')";
            if (in_array('cargado_cuenta_at', $giftColumns, true)) {
                $completedStatus = "({$completedStatus} AND {$alias}.cargado_cuenta_at IS NULL)";
            }
            $conditions[] = $completedStatus;
        }

        return empty($conditions) ? '1 = 0' : '(' . implode(' OR ', $conditions) . ')';
    }

    private function allowsTestingTableSessionReset(): bool
    {
        $env = strtolower(trim((string)($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production')));
        $debugValue = $_ENV['APP_DEBUG'] ?? $_SERVER['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false;
        $debug = filter_var($debugValue, FILTER_VALIDATE_BOOLEAN);
        $explicitAllowValue = $_ENV['AMARE_ALLOW_TEST_SESSION_RESET']
            ?? $_SERVER['AMARE_ALLOW_TEST_SESSION_RESET']
            ?? getenv('AMARE_ALLOW_TEST_SESSION_RESET')
            ?: false;
        $explicitAllow = filter_var($explicitAllowValue, FILTER_VALIDATE_BOOLEAN);

        return $env !== 'production' || $debug === true || $explicitAllow === true;
    }

    private function resetActiveTableVisitForUser(int $userId): array
    {
        $activeVisit = $this->findActiveTableVisitForUser($userId);
        if ($activeVisit === null) {
            $this->clearTestingTableSessionForUser($userId);
            return [
                'affected_orders' => 0,
                'active_visit' => null,
            ];
        }

        $activeVisitDetails = $this->describeActiveTableVisit($activeVisit, $userId);
        $columns = $this->getTableColumns('rest_pedidos');
        $targetOrderIds = $this->activeVisitOrderIdsForTestingReset($activeVisit, $userId, $columns);
        if (empty($targetOrderIds)) {
            $this->clearTestingTableSessionForUser($userId);
            return [
                'affected_orders' => 0,
                'active_visit' => $activeVisitDetails,
            ];
        }

        $set = [];
        $params = [
            ':user_id' => $userId,
            ':tester_id' => $userId,
        ];
        $placeholders = [];

        foreach (array_values(array_unique($targetOrderIds)) as $index => $orderId) {
            $placeholder = ':order_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $orderId;
        }

        if (in_array('cuenta_abierta', $columns, true)) {
            $set[] = 'cuenta_abierta = 0';
        }
        if (in_array('salida_validado_at', $columns, true)) {
            $set[] = 'salida_validado_at = COALESCE(salida_validado_at, NOW())';
        }
        if (in_array('salida_validado_por', $columns, true)) {
            $set[] = 'salida_validado_por = COALESCE(salida_validado_por, :tester_id)';
        }
        if (in_array('cerrado_at', $columns, true)) {
            $set[] = 'cerrado_at = COALESCE(cerrado_at, NOW())';
        }
        if (in_array('estado', $columns, true)) {
            $settledMarkers = [];
            foreach (['pagado_at', 'cerrado_at', 'salida_qr_generado_at'] as $column) {
                if (in_array($column, $columns, true)) {
                    $settledMarkers[] = "{$column} IS NOT NULL";
                }
            }
            $set[] = empty($settledMarkers)
                ? "estado = 'cancelado'"
                : "estado = CASE WHEN (" . implode(' OR ', $settledMarkers) . ") THEN 'entregado' ELSE 'cancelado' END";
        }
        if (in_array('actualizado_at', $columns, true)) {
            $set[] = 'actualizado_at = NOW()';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        $affected = 0;
        if (!empty($set)) {
            $affected = Database::rowCount(
                'UPDATE rest_pedidos
                    SET ' . implode(', ', $set) . '
                  WHERE id IN (' . implode(', ', $placeholders) . ')
                    AND mobile_usuario_id = :user_id',
                $params
            );
        }

        if (!empty($activeVisitDetails['mesa_id'])) {
            $this->setDiagnosticTableOccupied((int)$activeVisitDetails['mesa_id'], false);
        }
        $this->clearTestingTableSessionForUser($userId);

        return [
            'affected_orders' => $affected,
            'active_visit' => $activeVisitDetails,
        ];
    }

    private function activeVisitOrderIdsForTestingReset(array $activeVisit, int $userId, array $columns): array
    {
        $activeOrderId = isset($activeVisit['id']) ? (int)$activeVisit['id'] : 0;
        $consumptionId = trim((string)($activeVisit['consumo_id'] ?? ''));

        if ($consumptionId === '' || !in_array('consumo_id', $columns, true)) {
            return $activeOrderId > 0 ? [$activeOrderId] : [];
        }

        $where = [
            'mobile_usuario_id = :user_id',
            "tipo_pedido = 'eat_in'",
            'consumo_id = :consumo_id',
        ];
        $params = [
            ':user_id' => $userId,
            ':consumo_id' => $consumptionId,
        ];

        if (in_array('salida_validado_at', $columns, true)) {
            $where[] = 'salida_validado_at IS NULL';
        }

        $rows = Database::query(
            'SELECT id
               FROM rest_pedidos
              WHERE ' . implode(' AND ', $where),
            $params
        );

        $ids = array_map(static fn(array $row): int => (int)($row['id'] ?? 0), $rows);
        $ids = array_values(array_filter($ids, static fn(int $id): bool => $id > 0));

        return !empty($ids) ? $ids : ($activeOrderId > 0 ? [$activeOrderId] : []);
    }

    private function clearTestingTableSessionForUser(int $userId): void
    {
        if ($userId <= 0 || !$this->tableExists('mobile_usuarios')) {
            return;
        }

        $columns = $this->getTableColumns('mobile_usuarios');
        $set = [];

        if (in_array('current_restaurante_id', $columns, true)) {
            $set[] = 'current_restaurante_id = NULL';
        }
        if (in_array('mesa', $columns, true)) {
            $set[] = 'mesa = NULL';
        }
        if (in_array('is_social_active', $columns, true)) {
            $set[] = 'is_social_active = 0';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        if (empty($set)) {
            return;
        }

        Database::rowCount(
            'UPDATE mobile_usuarios SET ' . implode(', ', $set) . ' WHERE id = :user_id',
            [':user_id' => $userId]
        );
    }

    private function setDiagnosticTableOccupied(int $tableId, bool $occupied): void
    {
        if ($tableId <= 0 || !$this->tableExists('rest_mesas')) {
            return;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $set = [];
        $params = [':id' => $tableId];

        if (in_array('ocupada', $columns, true)) {
            $set[] = 'ocupada = :ocupada';
            $params[':ocupada'] = $occupied ? 1 : 0;
        }
        if (in_array('disponible', $columns, true)) {
            $set[] = 'disponible = :disponible';
            $params[':disponible'] = $occupied ? 0 : 1;
        }
        if (in_array('estado', $columns, true)) {
            $set[] = 'estado = :estado';
            $params[':estado'] = $occupied ? 'ocupada' : 'disponible';
        }

        if (empty($set)) {
            return;
        }

        Database::rowCount(
            'UPDATE rest_mesas SET ' . implode(', ', $set) . ' WHERE id = :id',
            $params
        );
    }

    private function fetchSocialProfile(int $userId): ?array
    {
        return Database::queryOne(
            "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero, descripcion, intereses, que_busca, redes_sociales" . ($this->hasSocialPhotosColumn() ? ", social_photos_json" : "") . ",
                    is_social_active, current_restaurante_id, social_updated_at" . ($this->hasSocialConsentColumns() ? ", social_consent_accepted_at, social_consent_version" : "") . ($this->hasMesaColumn() ? ", mesa" : "") . "
               FROM mobile_usuarios
              WHERE id = :id
              LIMIT 1",
            [':id' => $userId]
        );
    }

    private function normalizeProfileRow(array $row, bool $includeConsent = true): array
    {
        $socialPhotos = $this->normalizeSocialPhotos($row['social_photos_json'] ?? null, $row['foto_url'] ?? null);
        $primaryPhoto = $row['foto_url'] ?? ($socialPhotos[0] ?? null);

        $result = [
            'user_id' => (int)($row['user_id'] ?? $row['id'] ?? 0),
            'nombre' => $row['nombre'] ?? '',
            'foto_url' => $primaryPhoto,
            'social_photos' => $socialPhotos,
            'edad' => isset($row['edad']) && $row['edad'] !== null ? (int)$row['edad'] : null,
            'sexualidad' => $row['sexualidad'] ?? null,
            'genero' => $row['genero'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'intereses' => $row['intereses'] ?? null,
            'que_busca' => $row['que_busca'] ?? null,
            'redes_sociales' => $row['redes_sociales'] ?? null,
            'mesa' => $row['mesa'] ?? null,
            'is_social_active' => isset($row['is_social_active']) ? (bool)$row['is_social_active'] : null,
            'modo_social' => isset($row['is_social_active']) ? (bool)$row['is_social_active'] : null,
            'current_restaurante_id' => isset($row['current_restaurante_id']) && $row['current_restaurante_id'] !== null
                ? (int)$row['current_restaurante_id']
                : null,
            'social_updated_at' => $row['social_updated_at'] ?? null,
            'has_social_profile' => $this->hasSocialProfile($row),
        ];

        if ($includeConsent) {
            $result['social_consent_accepted_at'] = $row['social_consent_accepted_at'] ?? null;
            $result['social_consent_version'] = $row['social_consent_version'] ?? null;
            $result['requires_social_consent'] = !$this->hasCurrentSocialConsent($row);
        }

        return $result;
    }

    private function hasSocialProfile(array $profile): bool
    {
        return !empty($profile['foto_url'])
            && isset($profile['edad']) && $profile['edad'] !== null
            && !empty($profile['sexualidad'])
            && !empty($profile['genero'])
            && !empty(trim((string)($profile['descripcion'] ?? '')));
    }

    private function hasCurrentSocialConsent(array $profile): bool
    {
        return !empty($profile['social_consent_accepted_at'])
            && isset($profile['social_consent_version'])
            && (string)$profile['social_consent_version'] === self::SOCIAL_CONSENT_VERSION;
    }

    private function sanitizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string)$value);
        return $trimmed === '' ? null : $trimmed;
    }

    private function updateUserAllowingNulls(int $userId, array $data): bool
    {
        $allowedKeys = [
            'nombre',
            'foto_url',
            'social_photos_json',
            'edad',
            'sexualidad',
            'genero',
            'descripcion',
            'intereses',
            'que_busca',
            'redes_sociales',
            'is_social_active',
            'current_restaurante_id',
            'mesa',
            'social_consent_accepted_at',
            'social_consent_version',
        ];

        $setClauses = [];
        $params = [':id' => $userId];

        foreach ($data as $key => $value) {
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }

            $setClauses[] = "{$key} = :{$key}";
            $params[":{$key}"] = $value;
        }

        if (empty($setClauses)) {
            return false;
        }

        $setClauses[] = 'updated_at = NOW()';
        $sql = 'UPDATE mobile_usuarios SET ' . implode(', ', $setClauses) . ' WHERE id = :id';

        Database::rowCount($sql, $params);
        return true;
    }

    private function hasMesaColumn(): bool
    {
        static $hasMesaColumn = null;

        if ($hasMesaColumn !== null) {
            return $hasMesaColumn;
        }

        $result = Database::queryOne(
            "SELECT 1
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'mobile_usuarios'
                AND COLUMN_NAME = 'mesa'
              LIMIT 1"
        );

        $hasMesaColumn = $result !== null;
        return $hasMesaColumn;
    }

    private function hasSocialPhotosColumn(): bool
    {
        static $hasSocialPhotosColumn = null;

        if ($hasSocialPhotosColumn !== null) {
            return $hasSocialPhotosColumn;
        }

        $result = Database::queryOne(
            "SELECT 1
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'mobile_usuarios'
                AND COLUMN_NAME = 'social_photos_json'
              LIMIT 1"
        );

        $hasSocialPhotosColumn = $result !== null;
        return $hasSocialPhotosColumn;
    }

    private function hasSocialConsentColumns(): bool
    {
        static $hasSocialConsentColumns = null;

        if ($hasSocialConsentColumns !== null) {
            return $hasSocialConsentColumns;
        }

        $result = Database::queryOne(
            "SELECT COUNT(*) AS total
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'mobile_usuarios'
                AND COLUMN_NAME IN ('social_consent_accepted_at', 'social_consent_version')"
        );

        $hasSocialConsentColumns = (int)($result['total'] ?? 0) === 2;
        return $hasSocialConsentColumns;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeSocialPhotos(mixed $jsonValue, ?string $fallbackPhoto): array
    {
        $photos = [];

        if (is_string($jsonValue) && trim($jsonValue) !== '') {
            $decoded = json_decode($jsonValue, true);
            if (is_array($decoded)) {
                foreach ($decoded as $photo) {
                    if (!is_string($photo)) {
                        continue;
                    }
                    $trimmed = trim($photo);
                    if ($trimmed !== '') {
                        $photos[] = $trimmed;
                    }
                }
            }
        }

        if (empty($photos) && $fallbackPhoto !== null && trim($fallbackPhoto) !== '') {
            $photos[] = trim($fallbackPhoto);
        }

        return array_slice($this->uniquePhotoList($photos), 0, self::MAX_SOCIAL_PHOTOS);
    }

    /**
     * @param array<int, string> $photos
     * @return array<int, string>
     */
    private function uniquePhotoList(array $photos): array
    {
        $result = [];

        foreach ($photos as $photo) {
            $trimmed = trim($photo);
            if ($trimmed === '' || in_array($trimmed, $result, true)) {
                continue;
            }

            $result[] = $trimmed;
        }

        return array_values($result);
    }

    private function photoMatches(string $storedPhoto, string $requestedPhoto): bool
    {
        if ($storedPhoto === $requestedPhoto) {
            return true;
        }

        return $this->normalizePhotoComparisonValue($storedPhoto) === $this->normalizePhotoComparisonValue($requestedPhoto);
    }

    private function normalizePhotoComparisonValue(string $photo): string
    {
        $value = trim($photo);
        $path = parse_url($value, PHP_URL_PATH);
        $candidate = is_string($path) && $path !== '' ? $path : $value;
        $uploadsPosition = strpos($candidate, '/uploads/');

        if ($uploadsPosition !== false) {
            $candidate = substr($candidate, $uploadsPosition + 1);
        }

        return ltrim(rawurldecode($candidate), '/');
    }

    private function detectGiftProductsTable(): ?string
    {
        foreach (['social_gift_products', 'social_gifts_products', 'gift_products'] as $tableName) {
            $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
            if (!empty($exists)) {
                return $tableName;
            }
        }

        return null;
    }

    private function sanitizeGiftType(mixed $giftType): string
    {
        return (string)$giftType === 'menu' ? 'menu' : 'gift';
    }

    private function menuGiftProducts(int $restaurantId): array
    {
        if (!$this->tableExists('rest_platillos')) {
            return [];
        }

        $hasCategories = $this->tableExists('rest_categorias_menu');
        $categoryJoin = $hasCategories ? 'LEFT JOIN rest_categorias_menu c ON c.id = p.categoria_id' : '';
        $categoryField = $hasCategories ? 'c.nombre' : "''";
        $categoryFilter = $hasCategories
            ? "AND (
                LOWER(COALESCE(c.nombre, '')) LIKE '%bebida%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%trago%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%cerveza%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%vino%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%refresco%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%agua%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%cafe%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%café%'
                OR LOWER(COALESCE(c.nombre, '')) LIKE '%postre%'
            )"
            : '';

        $rows = Database::query(
            "SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen, {$categoryField} AS categoria_nombre
               FROM rest_platillos p
               {$categoryJoin}
              WHERE p.restaurante_id = :restaurant_id
                AND COALESCE(p.activo, 1) = 1
                AND COALESCE(p.disponible, 1) = 1
                {$categoryFilter}
           ORDER BY categoria_nombre ASC, p.nombre ASC",
            [':restaurant_id' => $restaurantId]
        );

        return array_map(
            static function (array $item): array {
                $category = (string)($item['categoria_nombre'] ?? '');
                return [
                    'id' => (int)$item['id'],
                    'tipo' => 'menu',
                    'nombre' => $item['nombre'],
                    'descripcion' => $item['descripcion'] ?: ($category !== '' ? $category : null),
                    'precio' => (float)($item['precio'] ?? 0),
                    'icono' => match (true) {
                        stripos($category, 'cerveza') !== false => 'beer-outline',
                        stripos($category, 'vino') !== false => 'wine-outline',
                        stripos($category, 'café') !== false || stripos($category, 'cafe') !== false => 'cafe-outline',
                        stripos($category, 'trago') !== false => 'flame-outline',
                        stripos($category, 'refresco') !== false || stripos($category, 'agua') !== false => 'water-outline',
                        stripos($category, 'postre') !== false => 'ice-cream-outline',
                        default => 'gift-outline',
                    },
                    'color' => '#B71C1C',
                    'es_regalo' => true,
                    'imagen' => $item['imagen'] ?? null,
                    'orden' => 1000,
                ];
            },
            $rows
        );
    }

    private function findGiftProduct(int $giftProductId, int $restaurantId = 0, string $giftType = 'gift'): ?array
    {
        if ($giftType === 'menu') {
            if ($restaurantId <= 0 || !$this->tableExists('rest_platillos')) {
                return null;
            }

            return Database::queryOne(
                "SELECT id, nombre, descripcion, precio, imagen
                   FROM rest_platillos
                  WHERE id = :id
                    AND restaurante_id = :restaurant_id
                    AND COALESCE(activo, 1) = 1
                    AND COALESCE(disponible, 1) = 1
                  LIMIT 1",
                [':id' => $giftProductId, ':restaurant_id' => $restaurantId]
            );
        }

        $tableName = $this->detectGiftProductsTable();
        if ($tableName === null) {
            return $restaurantId > 0 ? $this->findGiftProduct($giftProductId, $restaurantId, 'menu') : null;
        }

        $columns = $this->getTableColumns($tableName);
        $categorySelect = in_array('categoria', $columns, true) ? ', categoria' : '';

        return Database::queryOne(
            "SELECT id, nombre, descripcion, precio, imagen{$categorySelect}
               FROM {$tableName}
              WHERE id = :id
              LIMIT 1",
            [':id' => $giftProductId]
        );
    }

    private function tableExists(string $tableName): bool
    {
        $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
        return !empty($exists);
    }

    private function getRelationshipStatus(int $currentUserId, int $targetUserId): string
    {
        if ($currentUserId === $targetUserId || $this->isBlockedBetween($currentUserId, $targetUserId) || !$this->tableExists('social_likes')) {
            return 'none';
        }

        $like = Database::queryOne(
            "SELECT matched_at
               FROM social_likes
              WHERE liker_user_id = :current_user_id
                AND liked_user_id = :target_user_id
              LIMIT 1",
            [
                ':current_user_id' => $currentUserId,
                ':target_user_id' => $targetUserId,
            ]
        );

        if (!$like) {
            return 'none';
        }

        return !empty($like['matched_at']) ? 'matched' : 'liked';
    }

    private function blockedUsersSql(string $targetColumn, string $blockerPlaceholder, string $blockedPlaceholder): string
    {
        if (!$this->tableExists('social_blocks')) {
            return '';
        }

        return " AND NOT EXISTS (
                    SELECT 1
                      FROM social_blocks sb
                     WHERE (
                        sb.blocker_user_id = {$blockerPlaceholder}
                        AND sb.blocked_user_id = {$targetColumn}
                     ) OR (
                        sb.blocker_user_id = {$targetColumn}
                        AND sb.blocked_user_id = {$blockedPlaceholder}
                     )
                     LIMIT 1
                )";
    }

    private function isBlockedBetween(int $currentUserId, int $targetUserId): bool
    {
        if ($currentUserId <= 0 || $targetUserId <= 0 || !$this->tableExists('social_blocks')) {
            return false;
        }

        $row = Database::queryOne(
            'SELECT id
               FROM social_blocks
              WHERE (blocker_user_id = :current_a AND blocked_user_id = :target_a)
                 OR (blocker_user_id = :target_b AND blocked_user_id = :current_b)
              LIMIT 1',
            [
                ':current_a' => $currentUserId,
                ':target_a' => $targetUserId,
                ':target_b' => $targetUserId,
                ':current_b' => $currentUserId,
            ]
        );

        return $row !== null;
    }

    private function getMatchDate(int $currentUserId, int $targetUserId): ?string
    {
        if (!$this->tableExists('social_likes')) {
            return null;
        }

        $like = Database::queryOne(
            "SELECT matched_at
               FROM social_likes
              WHERE liker_user_id = :current_user_id
                AND liked_user_id = :target_user_id
                AND matched_at IS NOT NULL
              LIMIT 1",
            [
                ':current_user_id' => $currentUserId,
                ':target_user_id' => $targetUserId,
            ]
        );

        return $like['matched_at'] ?? null;
    }

    /**
     * @return array<string>
     */
    private function getTableColumns(string $tableName): array
    {
        $columns = Database::query("SHOW COLUMNS FROM `{$tableName}`");
        return array_values(array_map(
            static fn(array $column): string => (string)($column['Field'] ?? ''),
            $columns
        ));
    }

    /**
     * @param array<string> $columns
     * @param array<string> $candidates
     */
    private function firstExistingColumn(array $columns, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (in_array($candidate, $columns, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function formatMesaLabel(string $value): string
    {
        return stripos($value, 'mesa') === 0 ? $value : 'Mesa ' . $value;
    }

    /**
     * @return array{restaurante_id?: int|null, mesa_id?: int|null, mesa?: string|null, codigo?: string|null}
     */
    private function parseTableQrPayload(string $payload): array
    {
        $payload = trim($payload);
        $json = json_decode($payload, true);

        if (is_array($json)) {
            return [
                'restaurante_id' => isset($json['restaurante_id']) ? (int)$json['restaurante_id'] : (isset($json['branch_id']) ? (int)$json['branch_id'] : null),
                'mesa_id' => isset($json['mesa_id']) ? (int)$json['mesa_id'] : (isset($json['table_id']) ? (int)$json['table_id'] : null),
                'mesa' => isset($json['mesa']) ? trim((string)$json['mesa']) : (isset($json['value']) ? trim((string)$json['value']) : null),
                'codigo' => isset($json['code']) ? trim((string)$json['code']) : (isset($json['codigo']) ? trim((string)$json['codigo']) : null),
            ];
        }

        $parts = array_map('trim', explode('|', $payload));
        if (count($parts) >= 2 && strtoupper($parts[0]) === 'AMARE_TABLE') {
            return [
                'restaurante_id' => isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : null,
                'mesa_id' => isset($parts[2]) && $parts[2] !== '' ? (int)$parts[2] : null,
                'mesa' => $parts[3] ?? null,
                'codigo' => $parts[4] ?? null,
            ];
        }

        $urlData = $this->parseTableQrUrl($payload);
        if ($urlData !== null) {
            return $urlData;
        }

        return ['mesa' => $payload, 'codigo' => $payload];
    }

    /**
     * @return array{restaurante_id?: int|null, mesa_id?: int|null, mesa?: string|null, codigo?: string|null}|null
     */
    private function parseTableQrUrl(string $payload): ?array
    {
        if (!preg_match('/^https?:\/\//i', $payload)) {
            return null;
        }

        $parts = parse_url($payload);
        if (!is_array($parts)) {
            return null;
        }

        $queryParams = [];
        if (isset($parts['query'])) {
            parse_str((string)$parts['query'], $queryParams);
        }

        $fragmentParams = [];
        if (isset($parts['fragment'])) {
            $fragment = (string)$parts['fragment'];
            $fragmentQuery = parse_url($fragment, PHP_URL_QUERY);
            parse_str((string)($fragmentQuery ?: $fragment), $fragmentParams);
        }

        $params = array_merge($fragmentParams, $queryParams);
        $restaurantKeys = ['restaurante_id', 'restaurant_id', 'id_restaurante', 'branch_id', 'sucursal_id', 'id_sucursal', 'sucursal', 'branch', 'restaurant'];
        $tableIdKeys = ['mesa_id', 'table_id', 'id_mesa', 'id_table'];
        $mesaKeys = ['mesa', 'table', 'numero_mesa', 'numero', 'table_number'];
        $codeKeys = ['codigo', 'code', 'qr', 'token'];

        $restaurantId = $this->firstPositiveIntFromArray($params, $restaurantKeys);
        $tableId = $this->firstPositiveIntFromArray($params, $tableIdKeys);
        $mesa = $this->firstNonEmptyStringFromArray($params, $mesaKeys);
        $codigo = $this->firstNonEmptyStringFromArray($params, $codeKeys);

        $path = trim((string)($parts['path'] ?? ''), '/');
        $fragmentPath = isset($parts['fragment']) ? trim((string)parse_url((string)$parts['fragment'], PHP_URL_PATH), '/') : '';
        $pathParts = array_filter([$path, $fragmentPath], static fn(string $value): bool => $value !== '');
        $segments = empty($pathParts) ? [] : array_values(array_filter(explode('/', implode('/', $pathParts)), static fn(string $segment): bool => $segment !== ''));

        foreach ($segments as $index => $segment) {
            $key = strtolower(urldecode($segment));
            $next = isset($segments[$index + 1]) ? trim(urldecode($segments[$index + 1])) : null;

            if ($next === null || $next === '') {
                continue;
            }

            if ($restaurantId === null && in_array($key, ['restaurante', 'restaurante_id', 'restaurant', 'branch', 'branch_id', 'sucursal', 'sucursal_id'], true) && ctype_digit($next)) {
                $restaurantId = (int)$next;
                continue;
            }

            if ($tableId === null && in_array($key, ['mesa_id', 'table_id'], true) && ctype_digit($next)) {
                $tableId = (int)$next;
                continue;
            }

            if ($mesa === null && in_array($key, ['mesa', 'table'], true)) {
                $mesa = $next;
            }
        }

        $pathLooksLikeTable = $this->pathContainsAnySegment($segments, ['mesa', 'table']);
        if ($tableId === null && $pathLooksLikeTable) {
            $tableId = $this->firstPositiveIntFromArray($params, ['id']);
        }

        if ($restaurantId === null && $tableId === null && $mesa === null && $codigo === null) {
            return null;
        }

        return [
            'restaurante_id' => $restaurantId,
            'mesa_id' => $tableId,
            'mesa' => $mesa,
            'codigo' => $codigo,
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string> $keys
     */
    private function firstPositiveIntFromArray(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!isset($source[$key]) || is_array($source[$key])) {
                continue;
            }

            $value = trim((string)$source[$key]);
            if ($value !== '' && ctype_digit($value) && (int)$value > 0) {
                return (int)$value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<string> $keys
     */
    private function firstNonEmptyStringFromArray(array $source, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!isset($source[$key]) || is_array($source[$key])) {
                continue;
            }

            $value = trim((string)$source[$key]);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<string> $segments
     * @param array<string> $needles
     */
    private function pathContainsAnySegment(array $segments, array $needles): bool
    {
        foreach ($segments as $segment) {
            if (in_array(strtolower(urldecode($segment)), $needles, true)) {
                return true;
            }
        }

        return false;
    }

    private function findScannedTable(?int $restaurantId, ?int $tableId, ?string $tableCode): ?array
    {
        if (!$this->tableExists('rest_mesas')) {
            return null;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $activeColumn = $this->firstExistingColumn($columns, ['activo']);
        $lookupColumns = array_values(array_filter([
            $idColumn,
            $this->firstExistingColumn($columns, ['numero_mesa']),
            $this->firstExistingColumn($columns, ['numero']),
            $this->firstExistingColumn($columns, ['mesa']),
            $this->firstExistingColumn($columns, ['nombre']),
            $this->firstExistingColumn($columns, ['codigo']),
            $this->firstExistingColumn($columns, ['qr_codigo']),
        ]));

        if ($idColumn === null || $labelColumn === null) {
            return null;
        }

        $fields = [
            '*',
            "`{$idColumn}` AS id",
            "`{$labelColumn}` AS mesa_label",
        ];
        if ($restaurantColumn !== null) {
            $fields[] = "`{$restaurantColumn}` AS restaurante_id";
        }

        $sql = "SELECT " . implode(', ', $fields) . " FROM rest_mesas WHERE 1 = 1";
        $params = [];

        if ($activeColumn !== null) {
            $sql .= " AND `{$activeColumn}` = 1";
        }
        if ($restaurantId !== null && $restaurantColumn !== null) {
            $sql .= " AND `{$restaurantColumn}` = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }

        if ($tableId !== null && $tableId > 0) {
            $sql .= " AND `{$idColumn}` = :table_id";
            $params[':table_id'] = $tableId;
        }

        $rows = Database::query($sql, $params);
        $needle = $this->normalizeMesaMatchValue((string)($tableCode ?? ''));
        $needleDigits = preg_replace('/\D+/', '', $needle);

        foreach ($rows as $row) {
            $rawLabel = trim((string)($row['mesa_label'] ?? ''));
            if ($rawLabel === '') {
                continue;
            }

            if ($tableId !== null && $tableId > 0) {
                return [
                    'id' => (int)$row['id'],
                    'label' => $this->formatMesaLabel($rawLabel),
                    'value' => $rawLabel,
                    'restaurante_id' => (int)($row['restaurante_id'] ?? $restaurantId ?? 0),
                ];
            }

            if ($needle === '') {
                continue;
            }

            foreach ($lookupColumns as $lookupColumn) {
                $candidateRaw = trim((string)($row[$lookupColumn] ?? ''));
                if ($candidateRaw === '') {
                    continue;
                }
                $candidate = $this->normalizeMesaMatchValue($candidateRaw);
                $candidateDigits = preg_replace('/\D+/', '', $candidate);

                if ($candidate === $needle || ($needleDigits !== '' && $candidateDigits === $needleDigits)) {
                    return [
                        'id' => (int)$row['id'],
                        'label' => $this->formatMesaLabel($rawLabel),
                        'value' => $rawLabel,
                        'restaurante_id' => (int)($row['restaurante_id'] ?? $restaurantId ?? 0),
                    ];
                }
            }
        }

        return null;
    }

    private function findEatInBranch(int $restaurantId): ?array
    {
        $row = Database::queryOne(
            "SELECT r.id, r.nombre, r.slug, r.descripcion, r.direccion, r.lat, r.lng,
                    r.logo, r.imagen_banner, r.telefono, r.color_primario, r.color_secundario,
                    r.horario_apertura, r.horario_cierre, r.horarios_json,
                    r.mesas_habilitadas, r.reservas_habilitadas, r.activo,
                    COALESCE(rc.tipos_entrega, '[\"delivery\",\"pickup\"]') AS tipos_entrega
               FROM rest_restaurantes r
               LEFT JOIN rest_configuracion rc ON rc.restaurante_id = r.id AND rc.activo = 1
              WHERE r.id = :id
                AND r.activo = 1
              LIMIT 1",
            [':id' => $restaurantId]
        );

        if (!$row) {
            return null;
        }

        $types = json_decode((string)($row['tipos_entrega'] ?? '[]'), true) ?: [];
        if (!in_array('eat_in', $types, true)) {
            return null;
        }

        $row['id'] = (int)$row['id'];
        $row['lat'] = $row['lat'] !== null ? (float)$row['lat'] : null;
        $row['lng'] = $row['lng'] !== null ? (float)$row['lng'] : null;
        $row['mesas_habilitadas'] = (bool)$row['mesas_habilitadas'];
        $row['reservas_habilitadas'] = (bool)$row['reservas_habilitadas'];
        $row['activo'] = (bool)$row['activo'];
        $row['tipos_entrega'] = $types;
        $row['logo'] = !empty($row['logo']) ? $row['logo'] : self::DEFAULT_RESTAURANT_LOGO;

        return $row;
    }

    private function resolveMesaForRestaurant(int $restaurantId, string $mesaValue): ?array
    {
        if (!$this->tableExists('rest_mesas')) {
            return null;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $activeColumn = $this->firstExistingColumn($columns, ['activo']);

        if ($idColumn === null || $labelColumn === null) {
            return null;
        }

        $sql = "SELECT `{$idColumn}` AS id, `{$labelColumn}` AS mesa_label
                  FROM `rest_mesas`
                 WHERE 1 = 1";
        $params = [];

        if ($restaurantColumn !== null) {
            $sql .= " AND `{$restaurantColumn}` = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }

        if ($activeColumn !== null) {
            $sql .= " AND `{$activeColumn}` = 1";
        }

        $mesas = Database::query($sql, $params);
        $needle = $this->normalizeMesaMatchValue($mesaValue);
        $needleDigits = preg_replace('/\D+/', '', $needle);

        foreach ($mesas as $mesa) {
            $rawLabel = trim((string)($mesa['mesa_label'] ?? ''));
            if ($rawLabel === '') {
                continue;
            }

            $candidate = $this->normalizeMesaMatchValue($rawLabel);
            $candidateDigits = preg_replace('/\D+/', '', $candidate);

            if ($candidate === $needle || ($needleDigits !== '' && $candidateDigits === $needleDigits)) {
                return [
                    'id' => (int)($mesa['id'] ?? 0),
                    'label' => $this->formatMesaLabel($rawLabel),
                ];
            }
        }

        return null;
    }

    private function normalizeMesaMatchValue(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/^mesa\s*/i', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/', '', $normalized) ?? $normalized;
        return $normalized;
    }

    private function buildGiftFolio(int $giftOrderId): string
    {
        return 'SG-' . str_pad((string)$giftOrderId, 6, '0', STR_PAD_LEFT);
    }
}
