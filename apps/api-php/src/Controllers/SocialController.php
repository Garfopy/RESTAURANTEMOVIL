<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Models\User;
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

        $ext = strtolower(pathinfo($file['name'] ?? 'photo.jpg', PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            Response::error('Formato no permitido. Use: jpg, jpeg, png, webp', 400);
        }
        if ((int)($file['size'] ?? 0) > 5 * 1024 * 1024) {
            Response::error('La imagen no debe pesar más de 5 MB.', 400);
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
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'social-' . $user->id . '-' . time() . '-' . count($currentPhotos) . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::serverError('No se pudo guardar la imagen');
        }

        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://amarerestaurant.club/api_restaurante', '/');
        $fotoUrl = $baseUrl . '/uploads/social/' . $filename;
        $photos = $this->uniquePhotoList(array_merge($currentPhotos, [$fotoUrl]));
        $primaryPhoto = $photos[0] ?? $fotoUrl;

        if (!$this->updateUserAllowingNulls($user->id, [
            'foto_url' => $primaryPhoto,
            'social_photos_json' => json_encode($photos, JSON_UNESCAPED_SLASHES),
        ])) {
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

        $errors = [];
        if ($restaurantId <= 0) $errors['restaurant_id'] = ['Selecciona una sucursal válida'];
        if ($giftProductId <= 0) $errors['gift_product_id'] = ['Selecciona un regalo válido'];
        if ($recipientUserId <= 0) $errors['recipient_user_id'] = ['Selecciona un comensal válido'];
        if ($recipientUserId === (int)$user->id) $errors['recipient_user_id'] = ['No puedes enviarte un regalo a ti mismo'];
        if (!preg_match('/^[A-Za-z0-9_-]{16,64}$/', $requestKey)) {
            $errors['request_key'] = ['La clave de solicitud no es válida'];
        }
        if ($errors) Response::validationError($errors);

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
                $giftId,
                $giftProductId,
                $giftProduct,
                $price,
                (string)($recipient['nombre'] ?? 'Comensal'),
                $recipientMesaLabel
            );
            $folio = $this->buildGiftFolio($giftId);
            $update = $pdo->prepare(
                'UPDATE social_gift_orders
                    SET folio = :folio, pedido_id = :order_id, pedido_item_id = :item_id,
                        cargado_cuenta_at = NOW(), updated_at = NOW()
                  WHERE id = :id'
            );
            $update->execute([
                ':folio' => $folio,
                ':order_id' => $account['pedido_id'],
                ':item_id' => $account['pedido_item_id'],
                ':id' => $giftId,
            ]);
            $read = $pdo->prepare('SELECT * FROM social_gift_orders WHERE id = :id');
            $read->execute([':id' => $giftId]);
            $gift = $read->fetch();
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
            error_log('SocialController::createGiftPayment TRACE: ' . $exception->getTraceAsString());
            Response::serverError('No se pudo cargar el regalo a la cuenta.');
        }
    }

    private function createGiftStripePaymentLegacy(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $giftProductId = (int)($input['gift_product_id'] ?? 0);
        $recipientUserId = (int)($input['recipient_user_id'] ?? 0);
        $requestKey = trim((string)($input['request_key'] ?? ''));

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

        $recipient = $this->fetchSocialProfile($recipientUserId);
        if (!$recipient || !(bool)($recipient['is_social_active'] ?? false) || (int)($recipient['current_restaurante_id'] ?? 0) !== $restaurantId) {
            Response::error('Este comensal ya no está disponible en la sucursal seleccionada.', 409);
        }
        $recipientMesa = $this->sanitizeNullableString($recipient['mesa'] ?? null);
        if ($recipientMesa === null) Response::error('No pudimos ubicar la mesa del comensal seleccionado.', 409);
        $mesa = $this->resolveMesaForRestaurant($restaurantId, $recipientMesa);
        if ($mesa === null) Response::error('No encontramos la mesa del comensal en la sucursal actual.', 409);

        $giftProduct = $this->findGiftProduct($giftProductId);
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
                        restaurante_id, mesa_id, gift_product_id, sender_user_id, recipient_user_id,
                        sender_nombre, recipient_nombre, sender_mesa, recipient_mesa,
                        gift_nombre, gift_descripcion, gift_precio, gift_imagen,
                        status, moneda, payment_request_key, created_at, updated_at
                    ) VALUES (
                        :restaurant_id, :table_id, :product_id, :sender_id, :recipient_id,
                        :sender_name, :recipient_name, :sender_table, :recipient_table,
                        :gift_name, :gift_description, :gift_price, :gift_image,
                        'pendiente_pago', 'MXN', :request_key, NOW(), NOW()
                    )",
                    [
                        ':restaurant_id' => $restaurantId,
                        ':table_id' => (int)$mesa['id'],
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
            $intent = $intentId !== '' ? PaymentIntent::retrieve($intentId) : PaymentIntent::create([
                'amount' => $amountCents,
                'currency' => 'mxn',
                'description' => 'Regalo social ' . ($gift['folio'] ?? ''),
                'metadata' => [
                    'gift_order_id' => (string)$gift['id'],
                    'user_id' => (string)$user->id,
                ],
                'automatic_payment_methods' => ['enabled' => true],
            ], ['idempotency_key' => 'social_gift_' . $requestKey]);

            if ((int)$intent->amount !== $amountCents || strtolower((string)$intent->currency) !== 'mxn') {
                throw new \RuntimeException('El importe del intento no coincide');
            }
            Database::rowCount(
                "UPDATE social_gift_orders
                    SET stripe_payment_intent_id = :intent_id, status = IF(status = 'pago_fallido', 'pendiente_pago', status), updated_at = NOW()
                  WHERE id = :id",
                [':intent_id' => $intent->id, ':id' => (int)$gift['id']]
            );
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
            Database::rowCount(
                "UPDATE social_gift_orders
                    SET status = IF(status IN ('pendiente_pago','pago_fallido'), 'listo', status),
                        pagado_at = COALESCE(pagado_at, NOW()), updated_at = NOW()
                  WHERE id = :id AND stripe_payment_intent_id = :intent_id",
                [':id' => $giftId, ':intent_id' => $intentId]
            );
            $paidGift = Database::queryOne('SELECT * FROM social_gift_orders WHERE id = :id', [':id' => $giftId]);
            Response::success($this->giftPaymentResponse($paidGift), 'Regalo pagado y enviado');
        } catch (\Stripe\Exception\ApiErrorException $exception) {
            error_log('SocialController::confirmGiftPayment STRIPE ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo verificar el pago con Stripe.');
        }
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
     * @return array{pedido_id:int,pedido_item_id:int,mesa_id:int,total_agregado:float}
     */
    private function chargeGiftToTableAccount(
        \PDO $pdo,
        int $restaurantId,
        int $tableId,
        int $giftId,
        int $giftProductId,
        array $giftProduct,
        float $price,
        string $recipientName,
        string $recipientTable
    ): array {
        $orderColumns = $this->getTableColumns('rest_pedidos');
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
        $orderStmt = $pdo->prepare(
            'SELECT id FROM rest_pedidos WHERE ' . implode(' AND ', $where) .
            ' ORDER BY created_at DESC, id DESC LIMIT 1 FOR UPDATE'
        );
        $orderStmt->execute([':restaurant_id' => $restaurantId, ':table_id' => $tableId]);
        $orderId = (int)($orderStmt->fetchColumn() ?: 0);

        if ($orderId <= 0) {
            throw new \DomainException('Tu mesa aún no tiene una cuenta abierta. Solicita al mesero abrirla antes de enviar el regalo.');
        }

        $mappingStmt = $pdo->prepare(
            'SELECT map.platillo_id
               FROM social_gift_account_products map
               JOIN rest_platillos p ON p.id = map.platillo_id AND p.restaurante_id = map.restaurante_id
              WHERE map.restaurante_id = :restaurant_id AND map.gift_product_id = :gift_product_id
              LIMIT 1 FOR UPDATE'
        );
        $mappingStmt->execute([':restaurant_id' => $restaurantId, ':gift_product_id' => $giftProductId]);
        $dishId = (int)($mappingStmt->fetchColumn() ?: 0);
        $dishData = [
            'restaurante_id' => $restaurantId,
            'categoria_id' => null,
            'codigo' => 'SG-' . $giftProductId,
            'nombre' => 'Regalo: ' . (string)($giftProduct['nombre'] ?? 'Detalle social'),
            'descripcion' => $giftProduct['descripcion'] ?? 'Regalo enviado desde el modo social',
            'precio' => $price,
            'imagen' => $giftProduct['imagen'] ?? null,
            'tiempo_preparacion_min' => 0,
            'disponible' => 0,
            'activo' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($dishId <= 0) {
            $dishId = $this->insertDynamicRow($pdo, 'rest_platillos', $dishData);
            if ($dishId <= 0) throw new \RuntimeException('No se pudo crear la partida contable del regalo.');
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
        } else {
            $updateDish = $pdo->prepare(
                'UPDATE rest_platillos
                    SET nombre = :name, descripcion = :description, precio = :price,
                        imagen = :image, disponible = 0, activo = 0
                  WHERE id = :id AND restaurante_id = :restaurant_id'
            );
            $updateDish->execute([
                ':name' => $dishData['nombre'],
                ':description' => $dishData['descripcion'],
                ':price' => $price,
                ':image' => $dishData['imagen'],
                ':id' => $dishId,
                ':restaurant_id' => $restaurantId,
            ]);
        }

        $notes = 'Regalo para ' . $recipientName . ' - ' . $this->formatMesaLabel($recipientTable);
        $itemId = $this->insertDynamicRow($pdo, 'rest_pedido_items', [
            'pedido_id' => $orderId,
            'platillo_id' => $dishId,
            'cantidad' => 1,
            'precio_unit' => $price,
            'subtotal' => $price,
            'notas' => $notes,
            'estado' => 'entregado',
            'origen' => 'menu',
        ]);
        if ($itemId <= 0) throw new \RuntimeException('No se pudo agregar el regalo a la cuenta.');

        $totalUpdate = $pdo->prepare(
            'UPDATE rest_pedidos
                SET subtotal = subtotal + :subtotal, total = total + :total
              WHERE id = :id'
        );
        $totalUpdate->execute([':subtotal' => $price, ':total' => $price, ':id' => $orderId]);
        return [
            'pedido_id' => $orderId,
            'pedido_item_id' => $itemId,
            'mesa_id' => $tableId,
            'total_agregado' => $price,
        ];
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
