<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

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
            'telefono' => 'max:30'
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
            $updateData['telefono'] = $input['telefono'];
        }

        if (empty($updateData)) {
            Response::error('No se proporcionaron datos para actualizar', 400);
        }

        if (!User::update($user->id, $updateData)) {
            Response::serverError('No se pudo actualizar el perfil');
        }

        $updatedUser = User::findById($user->id);
        
        Response::success(['profile' => $updatedUser], 'Perfil actualizado exitosamente');
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
}
