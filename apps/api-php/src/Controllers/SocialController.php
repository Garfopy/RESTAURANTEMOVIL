<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\ImageUploadHelper;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Models\Order;
use Amare\Api\Models\User;
use Amare\Api\Services\RewardsService;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class SocialController
{
    private const DEFAULT_RESTAURANT_LOGO = 'public/uploads/restaurantes/rest_logo_1_1781280185.png';
    private const MAX_SOCIAL_PHOTOS = 6;
    private const SOCIAL_CONSENT_VERSION = 'social-v1-2026-06-16';

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

    public function matches(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!$this->tableExists('social_likes')) {
            Response::success(['matches' => []]);
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
           ORDER BY sl.matched_at DESC",
            [':user_id' => (int)$user->id]
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
              ORDER BY created_at DESC
              LIMIT 10",
            [':user_id' => (int)$user->id]
        );

        $notifications = array_map(static function (array $row): array {
            $payload = [];
            if (!empty($row['payload_json'])) {
                $decoded = json_decode((string)$row['payload_json'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            return [
                'id' => (int)$row['id'],
                'actor_user_id' => isset($row['actor_user_id']) ? (int)$row['actor_user_id'] : null,
                'type' => (string)$row['type'],
                'title' => (string)$row['title'],
                'body' => (string)$row['body'],
                'payload' => $payload,
                'read_at' => $row['read_at'] ?? null,
                'created_at' => $row['created_at'] ?? null,
            ];
        }, $rows);

        Response::success(['notifications' => $notifications]);
    }

    public function receivedLikes(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!$this->tableExists('social_likes')) {
            Response::success(['likes' => []]);
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
                AND NOT EXISTS (
                    SELECT 1
                      FROM social_likes mine
                     WHERE mine.liker_user_id = :user_id_for_mine
                       AND mine.liked_user_id = sl.liker_user_id
                     LIMIT 1
                )
           ORDER BY sl.created_at DESC",
            [
                ':user_id' => (int)$user->id,
                ':user_id_for_mine' => (int)$user->id,
            ]
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
           ORDER BY sl.created_at DESC",
            [':user_id' => (int)$user->id]
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

        $errors = [];
        if ($restaurantId <= 0) $errors['restaurant_id'] = ['Selecciona una sucursal valida'];
        if ($targetUserId <= 0) $errors['recipient_user_id'] = ['Selecciona un comensal valido'];
        if ($targetUserId === (int)$user->id) $errors['recipient_user_id'] = ['No puedes cubrir tu propia cuenta desde social'];
        if (!in_array($paymentMode, ['account', 'stripe'], true)) $errors['payment_mode'] = ['Selecciona una forma de pago valida'];
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
                ]
            );
            $pdo->commit();

            Response::success([
                'cover' => $this->socialAccountCoverResponse($cover),
                'account' => $summary,
                'approval_required' => true,
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

            if ((string)$cover['payment_mode'] === 'stripe') {
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
            $coveredExitPass = $this->ensureCoveredConsumptionExitPass($consumption, (int)$cover['covered_user_id']);
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

            $payerOrderId = $this->createCoveredConsumptionOrder(
                $pdo,
                (int)$cover['restaurante_id'],
                (int)$user->id,
                (string)($user->nombre ?? 'Comensal'),
                (int)($cover['payer_mesa_id'] ?? 0),
                (string)($cover['payer_mesa'] ?? ''),
                $recipient,
                (string)($cover['covered_mesa'] ?? ''),
                $consumption,
                false,
                $intentId,
                'tarjeta'
            );
            $this->markConsumptionCovered($pdo, $consumption, 'tarjeta');
            $coveredExitPass = $this->ensureCoveredConsumptionExitPass($consumption, (int)$cover['covered_user_id']);
            $this->updateSocialAccountCover($pdo, $coverId, [
                'payer_pedido_id' => $payerOrderId,
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
                ]
            );
            $pdo->commit();

            $cover['payer_pedido_id'] = $payerOrderId;
            $cover['status'] = 'paid';
            $cover['paid_at'] = date('Y-m-d H:i:s');
            Response::success([
                'cover' => $this->socialAccountCoverResponse($cover),
                'covered_exit_pass' => $coveredExitPass,
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

    public function giftProducts(): void
    {
        $restaurantId = (int)($_GET['restaurant_id'] ?? 0);
        $tableName = $this->detectGiftProductsTable();

        $result = [];
        if ($tableName !== null) {
            $products = Database::query(
                "SELECT id, nombre, descripcion, precio, icono, color, es_regalo, imagen, orden
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
                $giftId = Database::execute(
                    "INSERT INTO social_gift_orders (
                        restaurante_id, mesa_id, sender_mesa_id, gift_product_id, sender_user_id, recipient_user_id,
                        sender_nombre, recipient_nombre, sender_mesa, recipient_mesa,
                        gift_nombre, gift_descripcion, gift_precio, gift_imagen,
                        status, moneda, payment_request_key, created_at, updated_at
                    ) VALUES (
                        :restaurant_id, :table_id, :sender_table_id, :product_id, :sender_id, :recipient_id,
                        :sender_name, :recipient_name, :sender_table, :recipient_table,
                        :gift_name, :gift_description, :gift_price, :gift_image,
                        'pendiente_pago', 'MXN', :request_key, NOW(), NOW()
                    )",
                    [
                        ':restaurant_id' => $restaurantId,
                        ':table_id' => (int)$mesa['id'],
                        ':sender_table_id' => (int)$senderMesaRow['id'],
                        ':product_id' => $giftProductId,
                        ':sender_id' => (int)$user->id,
                        ':recipient_id' => $recipientUserId,
                        ':sender_name' => $sender['nombre'] ?? 'Comensal',
                        ':recipient_name' => $recipient['nombre'] ?? 'Comensal',
                        ':sender_table' => $senderMesa,
                        ':recipient_table' => $recipientMesa,
                        ':gift_name' => $giftProduct['nombre'] ?? 'Regalo',
                        ':gift_description' => $giftProduct['descripcion'] ?? null,
                        ':gift_price' => $amountCents / 100,
                        ':gift_image' => $giftProduct['imagen'] ?? null,
                        ':request_key' => $requestKey,
                    ]
                );
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

        return $this->buildSocialConsumption([$anchor]);
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

    private function buildSocialConsumption(array $orders): array
    {
        $orderIds = array_values(array_map(static fn(array $order): int => (int)$order['id'], $orders));
        $items = $this->fetchOrderItemsForCopy($orderIds);
        $total = 0.0;
        $subtotal = 0.0;
        foreach ($orders as $order) {
            $total += (float)($order['total'] ?? 0);
            $subtotal += (float)($order['subtotal'] ?? 0);
        }
        $consumoId = (string)($orders[0]['consumo_id'] ?? '');
        if ($consumoId === '') {
            $consumoId = 'ORD-' . implode('-', $orderIds);
        }

        return [
            'consumo_id' => $consumoId,
            'orders' => $orders,
            'order_ids' => $orderIds,
            'items' => $items,
            'subtotal' => round($subtotal, 2),
            'total' => round($total, 2),
        ];
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

        return Database::query(
            'SELECT pi.*, p.nombre AS platillo_nombre
               FROM rest_pedido_items pi
          LEFT JOIN rest_platillos p ON p.id = pi.platillo_id
              WHERE pi.pedido_id IN (' . implode(',', $placeholders) . ')
           ORDER BY pi.id ASC',
            $params
        );
    }

    private function socialConsumptionSummary(array $consumption): array
    {
        $items = array_map(static function (array $item): array {
            return [
                'id' => (int)($item['id'] ?? 0),
                'pedido_id' => (int)($item['pedido_id'] ?? 0),
                'platillo_id' => isset($item['platillo_id']) ? (int)$item['platillo_id'] : null,
                'nombre' => $item['platillo_nombre'] ?? 'Producto',
                'cantidad' => (int)($item['cantidad'] ?? 1),
                'precio_unit' => (float)($item['precio_unit'] ?? 0),
                'subtotal' => (float)($item['subtotal'] ?? 0),
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
        $set = ['estado = :estado'];
        $params = [':estado' => 'en_preparacion'];
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
        if (in_array('pagado_at', $columns, true)) {
            $set[] = 'pagado_at = COALESCE(pagado_at, NOW())';
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
                   AND status IN ('pending','pending_approval','approved','pending_payment','charged_to_account','paid')
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
    }

    private function buildCoverRequestMessage(string $payerName, float $amount, string $mode): string
    {
        $method = $mode === 'stripe' ? 'con tarjeta' : 'agregandola a su cuenta';
        return sprintf('%s quiere cubrir tu consumo por $%.2f MXN %s. Acepta o rechaza la solicitud.', $payerName, $amount, $method);
    }

    private function buildCoverMessage(string $payerName, float $amount, string $mode): string
    {
        $verb = $mode === 'stripe' ? 'pago' : 'agrego a su cuenta';
        return sprintf('%s %s tu consumo por $%.2f MXN.', $payerName, $verb, $amount);
    }

    private function getStripeSecret(): string
    {
        $key = $_ENV['STRIPE_SECRET_KEY'] ?? $_SERVER['STRIPE_SECRET_KEY'] ?? getenv('STRIPE_SECRET_KEY');
        if (!is_string($key) || trim($key) === '') {
            throw new \RuntimeException('STRIPE_SECRET_KEY no configurada');
        }
        return trim($key);
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
        if ($openAccount && in_array('consumo_id', $orderColumns, true)) {
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
            'cuenta_abierta' => $openAccount ? 1 : 0,
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
            'restaurante_id = :restaurant_id',
            'mesa_id = :table_id',
            "tipo_pedido = 'eat_in'",
            'consumo_id IS NOT NULL',
            "consumo_id <> ''",
        ];
        if (in_array('cuenta_abierta', $orderColumns, true)) {
            $where[] = 'cuenta_abierta = 1';
        }
        if (in_array('mobile_usuario_id', $orderColumns, true)) {
            $where[] = 'mobile_usuario_id = :user_id';
        }
        if (in_array('salida_qr_generado_at', $orderColumns, true)) {
            $where[] = 'salida_qr_generado_at IS NULL';
        }
        if (in_array('salida_validado_at', $orderColumns, true)) {
            $where[] = 'salida_validado_at IS NULL';
        }

        $existing = $pdo->prepare(
            'SELECT consumo_id FROM rest_pedidos WHERE ' . implode(' AND ', $where) .
            ' ORDER BY id DESC LIMIT 1'
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

    private function assertCanUseTableSession(int $userId, int $restaurantId, int $tableId, string $tableLabel = ''): void
    {
        $activeVisit = $this->findActiveTableVisitForUser($userId);
        if ($activeVisit === null) {
            return;
        }

        $activeRestaurantId = isset($activeVisit['restaurante_id']) ? (int)$activeVisit['restaurante_id'] : 0;
        $activeTableId = isset($activeVisit['mesa_id']) && $activeVisit['mesa_id'] !== null ? (int)$activeVisit['mesa_id'] : 0;

        if ($activeRestaurantId === $restaurantId && $activeTableId === $tableId) {
            return;
        }

        $activeLabel = $activeTableId > 0 ? $this->formatMesaLabel((string)$activeTableId) : 'tu mesa actual';
        $nextLabel = trim($tableLabel) !== '' ? $this->formatMesaLabel($tableLabel) : $this->formatMesaLabel((string)$tableId);

        Response::error(
            'Primero cierra y paga tu cuenta de ' . $activeLabel . ', valida tu QR de salida con hostess y despues escanea ' . $nextLabel . '.',
            409,
            'TABLE_SESSION_ACTIVE'
        );
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

        return Database::queryOne(
            'SELECT id, restaurante_id, mesa_id, consumo_id, salida_qr_generado_at, salida_validado_at
               FROM rest_pedidos
              WHERE ' . implode(' AND ', $where) . '
           ORDER BY created_at DESC, id DESC
              LIMIT 1',
            [':user_id' => $userId]
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

        return Database::queryOne(
            "SELECT id, nombre, descripcion, precio, imagen
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
        if ($currentUserId === $targetUserId || !$this->tableExists('social_likes')) {
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
