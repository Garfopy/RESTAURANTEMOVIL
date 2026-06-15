<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Models\User;

class SocialController
{
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

        if ($isActive) {
            if (!$this->hasMesaColumn()) {
                Response::serverError('La base de datos aun no tiene la columna mesa. Ejecuta primero el SQL de la fase 1.');
            }

            if ($mesa === null) {
                Response::validationError([
                    'mesa' => ['Ingresa tu numero de mesa para activar el modo social'],
                ]);
            }

            $currentProfile = $this->fetchSocialProfile($user->id);
            if (!$currentProfile || !$this->hasSocialProfile($currentProfile)) {
                Response::error('Debes completar tu perfil social antes de activar el modo social.', 400);
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

        Database::rowCount(
            "UPDATE mobile_usuarios
                SET " . implode(",\n                    ", $setClauses) . "
              WHERE id = :user_id",
            $params
        );

        $updated = Database::queryOne(
            "SELECT id AS user_id, nombre, is_social_active, current_restaurante_id, social_updated_at" . ($this->hasMesaColumn() ? ", mesa" : "") . "
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
        ]);
    }

    public function activeDiners(int $restaurantId): void
    {
        $user = AuthMiddleware::authenticate();

        $sql = "SELECT id AS user_id, nombre, foto_url, edad, genero, sexualidad, descripcion, intereses, que_busca, redes_sociales" . ($this->hasMesaColumn() ? ", mesa" : "") . "
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

        Response::success(array_map([$this, 'normalizeProfileRow'], $diners));
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
            Response::error('No se recibio ninguna imagen', 400);
        }

        $ext = strtolower(pathinfo($file['name'] ?? 'photo.jpg', PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed, true)) {
            Response::error('Formato no permitido. Use: jpg, jpeg, png, webp', 400);
        }

        $uploadDir = __DIR__ . '/../../uploads/social/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $filename = 'social-' . $user->id . '-' . time() . '.' . $ext;
        $destPath = $uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            Response::serverError('No se pudo guardar la imagen');
        }

        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://amarerestaurant.club/api_restaurante', '/');
        $fotoUrl = $baseUrl . '/uploads/social/' . $filename;

        if (!$this->updateUserAllowingNulls($user->id, ['foto_url' => $fotoUrl])) {
            Response::serverError('No se pudo actualizar la foto del perfil social');
        }

        Response::success(['foto_url' => $fotoUrl]);
    }

    public function publicProfile(int $userId): void
    {
        AuthMiddleware::authenticate();

        $profile = Database::queryOne(
            "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero, descripcion, intereses, que_busca, redes_sociales" . ($this->hasMesaColumn() ? ", mesa" : "") . "
               FROM mobile_usuarios
              WHERE id = :id
                AND is_social_active = 1",
            [':id' => $userId]
        );

        if (!$profile) {
            Response::notFound('Usuario no encontrado o sin perfil social publico');
        }

        Response::success($this->normalizeProfileRow($profile));
    }

    public function giftProducts(): void
    {
        $tableName = $this->detectGiftProductsTable();

        if ($tableName === null) {
            Response::success([]);
        }

        $products = Database::query(
            "SELECT id, nombre, descripcion, precio, icono, color, es_regalo, imagen, orden
               FROM {$tableName}
           ORDER BY orden ASC, nombre ASC"
        );

        $result = array_map(
            static function (array $item): array {
                return [
                    'id' => (int)$item['id'],
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
            $errors['restaurant_id'] = ['Selecciona una sucursal valida'];
        }
        if ($giftProductId <= 0) {
            $errors['gift_product_id'] = ['Selecciona un regalo valido'];
        }
        if ($recipientUserId <= 0) {
            $errors['recipient_user_id'] = ['Selecciona un comensal valido'];
        }
        if ($recipientUserId === (int)$user->id) {
            $errors['recipient_user_id'] = ['No puedes enviarte un regalo a ti mismo'];
        }

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        if (!$this->tableExists('social_gift_orders')) {
            Response::serverError('La tabla social_gift_orders aun no existe. Ejecuta primero la migracion de regalos sociales.');
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
            Response::error('Este comensal ya no esta disponible en la sucursal seleccionada.', 409);
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

    public function restaurantTables(int $restaurantId): void
    {
        AuthMiddleware::authenticate();

        if (!$this->tableExists('rest_mesas')) {
            Response::success([]);
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $activeColumn = $this->firstExistingColumn($columns, ['activo']);
        $orderColumn = $this->firstExistingColumn($columns, ['orden', 'numero_mesa', 'numero', 'mesa', 'nombre', 'id']);

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

    private function fetchSocialProfile(int $userId): ?array
    {
        return Database::queryOne(
            "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero, descripcion, intereses, que_busca, redes_sociales,
                    is_social_active, current_restaurante_id, social_updated_at" . ($this->hasMesaColumn() ? ", mesa" : "") . "
               FROM mobile_usuarios
              WHERE id = :id
              LIMIT 1",
            [':id' => $userId]
        );
    }

    private function normalizeProfileRow(array $row): array
    {
        return [
            'user_id' => (int)($row['user_id'] ?? $row['id'] ?? 0),
            'nombre' => $row['nombre'] ?? '',
            'foto_url' => $row['foto_url'] ?? null,
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
    }

    private function hasSocialProfile(array $profile): bool
    {
        return !empty($profile['foto_url'])
            && isset($profile['edad']) && $profile['edad'] !== null
            && !empty($profile['sexualidad'])
            && !empty($profile['genero'])
            && !empty(trim((string)($profile['descripcion'] ?? '')));
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
            'foto_url',
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

    private function findGiftProduct(int $giftProductId): ?array
    {
        $tableName = $this->detectGiftProductsTable();
        if ($tableName === null) {
            return null;
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

    private function resolveMesaForRestaurant(int $restaurantId, string $mesaValue): ?array
    {
        if (!$this->tableExists('rest_mesas')) {
            return null;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo']);
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
