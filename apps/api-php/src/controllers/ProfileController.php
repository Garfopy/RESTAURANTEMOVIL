<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\ImageUploadHelper;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\User;

class ProfileController
{
    public function show(): void
    {
        $user = AuthMiddleware::authenticate();
        $userData = User::findById($user->id);

        if (!$userData) {
            Response::notFound('Usuario no encontrado');
        }

        Response::success(['profile' => $userData]);
    }

    public function update(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'nombre' => 'min:3|max:200',
            'telefono' => 'max:20',
            'fecha_nacimiento' => 'max:10'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $updateData = [];
        
        if (isset($input['nombre'])) {
            $updateData['nombre'] = $input['nombre'];
        }

        if (isset($input['telefono'])) {
            $phone = preg_replace('/\D+/', '', (string)$input['telefono']);
            if (strlen($phone) === 10) {
                $phone = '52' . $phone;
            }
            if (strlen($phone) < 10 || strlen($phone) > 15) {
                Response::validationError(['telefono' => ['El telefono debe tener entre 10 y 15 digitos']]);
            }
            if (User::existsByAnyPhoneCandidate($phone, (int)$user->id)) {
                Response::error('El telefono ya esta registrado', 409);
            }
            $updateData['telefono'] = $phone;
        }

        if (isset($input['fecha_nacimiento'])) {
            $birthday = trim((string)$input['fecha_nacimiento']);
            if (!$this->isValidBirthDate($birthday)) {
                Response::validationError(['fecha_nacimiento' => ['Debes ser mayor de edad para crear una cuenta']]);
            }
            $updateData['fecha_nacimiento'] = $birthday;
        }

        if (array_key_exists('marketing_opt_in', $input)) {
            $updateData['marketing_opt_in'] = filter_var($input['marketing_opt_in'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
        }

        if (filter_var($input['terms_accepted'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $updateData['terms_accepted_at'] = date('Y-m-d H:i:s');
        }

        if (
            isset($updateData['telefono']) &&
            isset($updateData['fecha_nacimiento']) &&
            isset($updateData['terms_accepted_at'])
        ) {
            $updateData['onboarding_completed_at'] = date('Y-m-d H:i:s');
        }

        if (empty($updateData)) {
            $debug = ValidationMiddleware::getInputDebugInfo();
            $inputKeys = array_keys($input);
            $summary = sprintf(
                'No se proporcionaron datos para actualizar. input_keys=[%s], body_bytes=%s, content_type=%s',
                implode(',', $inputKeys),
                (string)($debug['raw_body_length'] ?? 'n/a'),
                (string)($debug['content_type'] ?? '')
            );

            Response::json([
                'success' => false,
                'message' => $summary,
                'code' => 'PROFILE_UPDATE_EMPTY_INPUT',
                'debug' => [
                    'allowed_fields' => [
                        'nombre',
                        'telefono',
                        'fecha_nacimiento',
                        'marketing_opt_in',
                        'terms_accepted',
                    ],
                    'received_input_keys' => $inputKeys,
                    'input_debug' => $debug,
                ],
            ], 400);
        }

        if (!User::update($user->id, $updateData)) {
            Response::serverError('No se pudo actualizar el perfil');
        }

        $updatedUser = User::findById($user->id);
        
        Response::success(['profile' => $updatedUser], 'Perfil actualizado exitosamente');
    }

    public function cancelOnboarding(): void
    {
        $user = AuthMiddleware::authenticate();
        $deleted = User::deleteIncompleteGoogleOnboarding((int)$user->id);

        Response::success([
            'deleted' => $deleted,
        ], $deleted ? 'Registro cancelado' : 'Flujo de registro cancelado');
    }

    private function isValidBirthDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));
        if (!checkdate($month, $day, $year)) {
            return false;
        }

        $timestamp = strtotime($value . ' 00:00:00');
        if ($timestamp === false || $timestamp > time()) {
            return false;
        }

        return $timestamp <= strtotime('-18 years') && $timestamp >= strtotime('-120 years');
    }

    public function orders(): void
    {
        $user = AuthMiddleware::authenticate();
        
        $sql = "SELECT p.id, p.folio, p.estado, p.subtotal, p.total,
                       p.tipo_pedido, p.created_at,
                       r.nombre AS restaurante_nombre
                FROM rest_pedidos p
                JOIN rest_restaurantes r ON r.id = p.restaurante_id
                WHERE p.mobile_usuario_id = :usuario_id
                ORDER BY p.created_at DESC
                LIMIT 50";
        
        $orders = \Amare\Api\Config\Database::query($sql, [':usuario_id' => $user->id]);
        
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }
        
        Response::success(['orders' => $orders]);
    }

    public function updateAvatar(): void
    {
        $user = AuthMiddleware::authenticate();
        $currentUser = User::findById((int)$user->id);

        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No se recibió ninguna imagen o hubo un error al subirla', 400);
        }

        $file = $_FILES['foto'];
        try {
            ImageUploadHelper::inspectUploadedImage(
                $file,
                ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
                10 * 1024 * 1024,
                120,
                120
            );
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        }

        $uploadDir = __DIR__ . '/../../uploads/';
        try {
            $filename = ImageUploadHelper::saveCompressedUpload(
                $file,
                $uploadDir,
                'avatar-' . $user->id . '-' . time(),
                512,
                512,
                78
            );
        } catch (\InvalidArgumentException $exception) {
            Response::error($exception->getMessage(), 400);
        } catch (\RuntimeException $exception) {
            Response::serverError($exception->getMessage());
        }

        // Construir URL pública — se usa APP_URL como base
        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://idactivos.digital/api_restaurante', '/');
        $fotoUrl = $baseUrl . '/uploads/' . $filename;

        if (!User::update($user->id, ['foto_url' => $fotoUrl])) {
            ImageUploadHelper::deleteLocalUploadFromUrl($fotoUrl, $uploadDir, 'avatar-' . $user->id . '-');
            Response::serverError('No se pudo actualizar la foto de perfil');
        }

        if (!$this->isPhotoReferencedInSocialGallery((int)$user->id, $currentUser['foto_url'] ?? null)) {
            ImageUploadHelper::deleteLocalUploadFromUrl(
                $currentUser['foto_url'] ?? null,
                $uploadDir,
                'avatar-' . $user->id . '-'
            );
        }

        // 🔥 IMPORTANTE: El frontend espera response.data.foto_url directamente,
        // no response.data.data.foto_url. Por eso usamos json() directamente
        // con el mismo formato que la vieja API Node.js.
        Response::json([
            'success' => true,
            'foto_url' => $fotoUrl
        ]);
    }

    public function deleteAccount(): void
    {
        $user = AuthMiddleware::authenticate();

        if (($user->auth_source ?? 'mobile') === 'staff') {
            Response::error('Las cuentas de personal se administran desde el panel web.', 400);
        }

        $userId = (int)$user->id;
        $currentUser = User::findByIdWithPassword($userId);
        if (!$currentUser) {
            Response::notFound('Usuario no encontrado');
        }

        $photos = $this->collectUserPhotoUrls($currentUser);
        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            $this->deleteRowsIfTableExists('mobile_direcciones', 'usuario_id', $userId);
            $this->deleteRowsIfTableExists('mobile_favoritos', 'usuario_id', $userId);
            $this->deleteRowsIfTableExists('mobile_push_tokens', 'usuario_id', $userId);
            $this->deleteRowsIfTableExists('mobile_datos_fiscales', 'usuario_id', $userId);

            if ($this->tableExists('social_likes')) {
                Database::rowCount(
                    'DELETE FROM social_likes WHERE liker_user_id = :liker_id OR liked_user_id = :liked_id',
                    [
                        ':liker_id' => $userId,
                        ':liked_id' => $userId,
                    ]
                );
            }
            if ($this->tableExists('social_blocks')) {
                Database::rowCount(
                    'DELETE FROM social_blocks WHERE blocker_user_id = :blocker_id OR blocked_user_id = :blocked_id',
                    [
                        ':blocker_id' => $userId,
                        ':blocked_id' => $userId,
                    ]
                );
            }
            if ($this->tableExists('social_reports')) {
                Database::rowCount(
                    'DELETE FROM social_reports WHERE reporter_user_id = :id',
                    [':id' => $userId]
                );
            }

            $deletedEmail = sprintf('deleted-user-%d-%d@deleted.amare.local', $userId, time());
            $set = [
                'nombre = :nombre',
                'email = :email',
                'password_hash = NULL',
                'telefono = NULL',
                'foto_url = NULL',
                'google_id = NULL',
                'activo = 0',
                'updated_at = NOW()',
            ];
            $params = [
                ':id' => $userId,
                ':nombre' => 'Cuenta eliminada',
                ':email' => $deletedEmail,
            ];

            foreach ([
                'fecha_nacimiento',
                'onboarding_completed_at',
                'terms_accepted_at',
                'marketing_opt_in',
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
                'social_updated_at',
                'social_consent_accepted_at',
                'social_consent_version',
                'password_reset_code_hash',
                'password_reset_expires_at',
                'password_reset_requested_at',
            ] as $column) {
                if (!$this->columnExists('mobile_usuarios', $column)) {
                    continue;
                }

                if ($column === 'marketing_opt_in' || $column === 'is_social_active') {
                    $set[] = "{$column} = 0";
                } else {
                    $set[] = "{$column} = NULL";
                }
            }

            Database::rowCount(
                'UPDATE mobile_usuarios SET ' . implode(', ', $set) . ' WHERE id = :id',
                $params
            );

            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('ProfileController::deleteAccount ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo eliminar la cuenta en este momento.');
        }

        foreach ($photos as $photoUrl) {
            ImageUploadHelper::deleteLocalUploadFromUrl($photoUrl, __DIR__ . '/../../uploads/', 'avatar-' . $userId . '-');
            ImageUploadHelper::deleteLocalUploadFromUrl($photoUrl, __DIR__ . '/../../uploads/', 'social-' . $userId . '-');
        }

        Response::success([
            'deleted' => true,
            'retained_data' => [
                'orders',
                'invoice_requests',
                'payment_records',
                'moderation_records',
            ],
        ], 'Cuenta eliminada');
    }

    private function isPhotoReferencedInSocialGallery(int $userId, ?string $photoUrl): bool
    {
        if ($photoUrl === null || trim($photoUrl) === '') {
            return false;
        }

        try {
            $row = \Amare\Api\Config\Database::queryOne(
                "SELECT social_photos_json FROM mobile_usuarios WHERE id = :id LIMIT 1",
                [':id' => $userId]
            );
        } catch (\Throwable) {
            return true;
        }

        $decoded = json_decode((string)($row['social_photos_json'] ?? ''), true);
        if (!is_array($decoded)) {
            return false;
        }

        $target = $this->normalizePhotoComparisonValue($photoUrl);
        foreach ($decoded as $photo) {
            if (is_string($photo) && $this->normalizePhotoComparisonValue($photo) === $target) {
                return true;
            }
        }

        return false;
    }

    private function normalizePhotoComparisonValue(string $photo): string
    {
        $value = (string)(parse_url(trim($photo), PHP_URL_PATH) ?: $photo);
        $uploadsPosition = strpos($value, '/uploads/');
        if ($uploadsPosition !== false) {
            $value = substr($value, $uploadsPosition);
        }

        return strtolower(trim($value));
    }

    private function getOrderItems(int $orderId): array
    {
        $sql = "SELECT pi.id, pi.platillo_id, pl.nombre AS platillo_nombre,
                       pl.imagen AS platillo_imagen,
                       pi.cantidad, pi.precio_unit, pi.notas,
                       pi.estado,
                       (pi.cantidad * pi.precio_unit) AS subtotal
                FROM rest_pedido_items pi
                JOIN rest_platillos pl ON pl.id = pi.platillo_id
                WHERE pi.pedido_id = :pedido_id";
        
        return \Amare\Api\Config\Database::query($sql, [':pedido_id' => $orderId]);
    }

    /**
     * @return array<int, string>
     */
    private function collectUserPhotoUrls(array $user): array
    {
        $photos = [];
        foreach (['foto_url', 'avatar'] as $field) {
            if (!empty($user[$field]) && is_string($user[$field])) {
                $photos[] = trim($user[$field]);
            }
        }

        if (!empty($user['social_photos_json']) && is_string($user['social_photos_json'])) {
            $decoded = json_decode($user['social_photos_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $photo) {
                    if (is_string($photo) && trim($photo) !== '') {
                        $photos[] = trim($photo);
                    }
                }
            }
        }

        return array_values(array_unique(array_filter($photos)));
    }

    private function deleteRowsIfTableExists(string $table, string $column, int $userId): void
    {
        if (!$this->tableExists($table) || !$this->columnExists($table, $column)) {
            return;
        }

        Database::rowCount(
            "DELETE FROM `{$table}` WHERE `{$column}` = :id",
            [':id' => $userId]
        );
    }

    private function tableExists(string $tableName): bool
    {
        $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
        return !empty($exists);
    }

    private function columnExists(string $tableName, string $columnName): bool
    {
        $row = Database::queryOne(
            'SELECT COUNT(*) AS total
               FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :table_name
                AND COLUMN_NAME = :column_name',
            [
                ':table_name' => $tableName,
                ':column_name' => $columnName,
            ]
        );

        return (int)($row['total'] ?? 0) > 0;
    }
}
