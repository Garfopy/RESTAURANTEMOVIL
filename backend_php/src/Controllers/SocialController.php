<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;

class SocialController
{
    /**
     * PATCH /users/social-status — toggle social mode visibility
     * Body: { is_social_active: bool, current_restaurante_id?: int|null }
     */
    public function updateStatus(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['is_social_active'])) {
            Response::error('El campo is_social_active es obligatorio', 400);
        }

        $isActive = (bool)$input['is_social_active'];
        $restaurantId = isset($input['current_restaurante_id'])
            ? (int)$input['current_restaurante_id']
            : null;

        $sql = "UPDATE mobile_usuarios SET
                    is_social_active = :active,
                    current_restaurante_id = :restaurant_id,
                    social_updated_at = NOW()
                WHERE id = :user_id";

        \Amare\Api\Config\Database::execute($sql, [
            ':active' => $isActive ? 1 : 0,
            ':restaurant_id' => $restaurantId,
            ':user_id' => $user->id
        ]);

        $updated = \Amare\Api\Config\Database::queryOne(
            "SELECT id, nombre, is_social_active, current_restaurante_id, social_updated_at
             FROM mobile_usuarios WHERE id = :id",
            [':id' => $user->id]
        );

        Response::success([
            'user_id' => (int)$updated['id'],
            'nombre' => $updated['nombre'],
            'is_social_active' => (bool)$updated['is_social_active'],
            'current_restaurante_id' => $updated['current_restaurante_id'] !== null ? (int)$updated['current_restaurante_id'] : null,
            'social_updated_at' => $updated['social_updated_at']
        ]);
    }

    /**
     * GET /restaurants/{id}/active-diners — list active social users
     */
    public function activeDiners(int $restaurantId): void
    {
        $user = AuthMiddleware::authenticate();

        $sql = "SELECT id AS user_id, nombre, foto_url
                FROM mobile_usuarios
                WHERE is_social_active = 1
                  AND current_restaurante_id = :restaurant_id
                  AND id != :current_user_id
                ORDER BY social_updated_at DESC";

        $params = [
            ':restaurant_id' => $restaurantId,
            ':current_user_id' => $user->id
        ];

        // Apply filters from query params
        if (isset($_GET['edad_min'])) {
            $sql = "SELECT u.id AS user_id, u.nombre, u.foto_url
                    FROM mobile_usuarios u
                    WHERE u.is_social_active = 1
                      AND u.current_restaurante_id = :restaurant_id
                      AND u.id != :current_user_id";
            $filters = [];
            if (!empty($_GET['edad_min'])) {
                $sql .= " AND u.edad >= :edad_min";
                $filters[':edad_min'] = (int)$_GET['edad_min'];
            }
            if (!empty($_GET['edad_max'])) {
                $sql .= " AND u.edad <= :edad_max";
                $filters[':edad_max'] = (int)$_GET['edad_max'];
            }
            if (!empty($_GET['genero'])) {
                $sql .= " AND u.genero = :genero";
                $filters[':genero'] = $_GET['genero'];
            }
            if (!empty($_GET['sexualidad'])) {
                $sql .= " AND u.sexualidad = :sexualidad";
                $filters[':sexualidad'] = $_GET['sexualidad'];
            }
            $sql .= " ORDER BY u.social_updated_at DESC";
            $params = array_merge($params, $filters);
        }

        $diners = \Amare\Api\Config\Database::query($sql, $params);

        // Convert ids to int and format
        $result = array_map(function ($diner) {
            return [
                'user_id' => (int)$diner['user_id'],
                'nombre' => $diner['nombre'],
                'foto_url' => $diner['foto_url']
            ];
        }, $diners);

        Response::success($result);
    }

    /**
     * GET /users/social-profile — fetch own social profile
     */
    public function getProfile(): void
    {
        $user = AuthMiddleware::authenticate();

        $sql = "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero,
                       descripcion, intereses, que_busca, redes_sociales,
                       (CASE WHEN foto_url IS NOT NULL AND edad IS NOT NULL AND sexualidad IS NOT NULL
                        AND genero IS NOT NULL AND descripcion IS NOT NULL THEN 1 ELSE 0 END) AS has_social_profile
                FROM mobile_usuarios WHERE id = :id";

        $profile = \Amare\Api\Config\Database::queryOne($sql, [':id' => $user->id]);

        if (!$profile) {
            Response::notFound('Perfil no encontrado');
        }

        $profile['user_id'] = (int)$profile['user_id'];
        $profile['has_social_profile'] = (bool)$profile['has_social_profile'];
        if ($profile['edad']) $profile['edad'] = (int)$profile['edad'];

        Response::success($profile);
    }

    /**
     * PUT /users/social-profile — update own social profile
     */
    public function updateProfile(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input)) {
            Response::error('No se recibieron datos', 400);
        }

        $updateData = [];
        if (isset($input['edad'])) $updateData['edad'] = (int)$input['edad'];
        if (isset($input['sexualidad'])) $updateData['sexualidad'] = $input['sexualidad'];
        if (isset($input['genero'])) $updateData['genero'] = $input['genero'];
        if (isset($input['descripcion'])) $updateData['descripcion'] = $input['descripcion'];
        if (isset($input['intereses'])) $updateData['intereses'] = $input['intereses'];
        if (isset($input['que_busca'])) $updateData['que_busca'] = $input['que_busca'];
        if (isset($input['redes_sociales'])) $updateData['redes_sociales'] = $input['redes_sociales'];

        if (empty($updateData)) {
            Response::error('No se proporcionaron datos válidos', 400);
        }

        if (!\Amare\Api\Models\User::update($user->id, $updateData)) {
            Response::serverError('No se pudo actualizar el perfil social');
        }

        // Return updated profile
        $sql = "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero,
                       descripcion, intereses, que_busca, redes_sociales,
                       (CASE WHEN foto_url IS NOT NULL AND edad IS NOT NULL AND sexualidad IS NOT NULL
                        AND genero IS NOT NULL AND descripcion IS NOT NULL THEN 1 ELSE 0 END) AS has_social_profile
                FROM mobile_usuarios WHERE id = :id";

        $profile = \Amare\Api\Config\Database::queryOne($sql, [':id' => $user->id]);

        $profile['user_id'] = (int)$profile['user_id'];
        $profile['has_social_profile'] = (bool)$profile['has_social_profile'];
        if ($profile['edad']) $profile['edad'] = (int)$profile['edad'];

        Response::success($profile, 'Perfil social actualizado');
    }

    /**
     * POST /users/social-photo — upload social profile photo
     */
    public function uploadPhoto(): void
    {
        $user = AuthMiddleware::authenticate();

        if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            Response::error('No se recibió ninguna imagen', 400);
        }

        $file = $_FILES['photo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($ext, $allowed)) {
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

        $baseUrl = rtrim($_ENV['APP_URL'] ?? 'https://idactivos.digital/api_restaurante', '/');
        $fotoUrl = $baseUrl . '/uploads/social/' . $filename;

        \Amare\Api\Models\User::update($user->id, ['foto_url' => $fotoUrl]);

        Response::json([
            'success' => true,
            'data' => ['foto_url' => $fotoUrl]
        ]);
    }

    /**
     * GET /users/{id}/public-profile — view other user's public social profile
     */
    public function publicProfile(int $userId): void
    {
        $sql = "SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero,
                       descripcion, intereses, que_busca, redes_sociales
                FROM mobile_usuarios WHERE id = :id AND is_social_active = 1";

        $profile = \Amare\Api\Config\Database::queryOne($sql, [':id' => $userId]);

        if (!$profile) {
            Response::notFound('Usuario no encontrado o no tiene perfil público');
        }

        $profile['user_id'] = (int)$profile['user_id'];
        if ($profile['edad']) $profile['edad'] = (int)$profile['edad'];

        Response::success($profile);
    }

    /**
     * GET /gift-products — list available gifts
     */
    public function giftProducts(): void
    {
        // Check if table exists
        $check = \Amare\Api\Config\Database::query(
            "SHOW TABLES LIKE 'gift_products'"
        );

        if (empty($check)) {
            // Return empty array if table doesn't exist
            Response::success([]);
        }

        $products = \Amare\Api\Config\Database::query(
            "SELECT id, nombre, descripcion, precio, icono, color, es_regalo, imagen, orden
             FROM gift_products ORDER BY orden ASC, nombre ASC"
        );

        $result = array_map(function ($p) {
            return [
                'id' => (int)$p['id'],
                'nombre' => $p['nombre'],
                'descripcion' => $p['descripcion'],
                'precio' => (float)$p['precio'],
                'icono' => $p['icono'],
                'color' => $p['color'],
                'es_regalo' => (bool)($p['es_regalo'] ?? true),
                'imagen' => $p['imagen'] ?? null,
                'orden' => (int)($p['orden'] ?? 0)
            ];
        }, $products);

        Response::success($result);
    }
}