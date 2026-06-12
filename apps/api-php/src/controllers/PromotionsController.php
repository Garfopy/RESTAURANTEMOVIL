<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Promotion;

class PromotionsController
{
    private const PROMOTION_UPLOAD_DIR = '/public/uploads/promociones/';
    private const PROMOTION_DB_PATH = 'public/uploads/promociones/';

    // =========================================================================
    // ENDPOINTS PUBLICOS / APP MOVIL
    // =========================================================================

    /**
     * GET /promotions
     * Devuelve las promociones activas del usuario autenticado.
     * Si no hay token, retorna array vacio.
     */
    public function index(): void
    {
        $user = AuthMiddleware::optional();

        if ($user) {
            $promotions = Promotion::getByUser((int)$user->id);
        } else {
            $promotions = [];
        }

        Response::success($promotions);
    }

    /**
     * GET /promotions/:id
     * Detalle de una promocion especifica.
     */
    public function show(int $id): void
    {
        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
        }

        Response::success(['promotion' => $promotion]);
    }

    /**
     * POST /admin/promotions/validate
     * Valida un codigo promocional (SOLO para admin desde panel web).
     * Útil para verificar que un código sea válido antes de asignarlo.
     * Body: { "code": "PROMO123", "usuario_id": 123 }
     */
    public function adminValidateCode(): void
    {
        AuthMiddleware::requireAdmin();

        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'code' => 'required|string|max:50'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $userId = isset($input['usuario_id']) ? (int)$input['usuario_id'] : null;
        $promotion = Promotion::validateCode($input['code'], $userId);

        if (!$promotion) {
            Response::error('Codigo de promocion invalido, expirado o no asignado a este usuario', 404);
        }

        Response::success(['promotion' => $promotion]);
    }

    // =========================================================================
    // ENDPOINTS ADMIN (requieren rol = 'admin')
    // =========================================================================

    /**
     * GET /admin/promotions
     * Lista TODAS las promociones para el panel web admin con paginacion.
     *
     * Query params opcionales:
     *   page       (int, default 1)
     *   per_page   (int, default 20, max 100)
     *   usuario_id (int) filtra por usuario
     */
    public function adminIndex(): void
    {
        AuthMiddleware::requireAdmin();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 20)));
        $userId  = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : null;

        $offset = ($page - 1) * $perPage;

        $promotions = Promotion::getAllForAdmin($perPage, $offset, $userId);
        $total      = Promotion::countForAdmin($userId);

        Response::success([
            'promotions' => $promotions,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * POST /admin/promotions
     * Crea una nueva promocion para un usuario especifico.
     * SOLO accesible desde panel web (requiere rol=admin).
     *
     * Body requerido:  usuario_id, titulo
     * Body opcional:   descripcion, imagen, deep_link, code, activo, expires_at
     */
  public function adminStore(): void
{
    $user = AuthMiddleware::requireAdmin();

    $input = ValidationMiddleware::getAllInput();

    $rules = [
        'usuario_id' => 'required|integer',
        'titulo'     => 'required|max:255',
    ];

    $errors = ValidationMiddleware::validate($rules, $input);

    if (!empty($errors)) {
        Response::validationError($errors);
    }

    // Validar expires_at
    if (!empty($input['expires_at'])) {

        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $input['expires_at'])
           ?: \DateTime::createFromFormat('Y-m-d', $input['expires_at']);

        if (!$dt) {
            Response::validationError([
                'expires_at' => [
                    'Formato de fecha invalido. Usa YYYY-MM-DD o YYYY-MM-DD HH:MM:SS'
                ]
            ]);
        }

        if (!Promotion::isValidFutureDate($input['expires_at'])) {
            Response::validationError([
                'expires_at' => [
                    'La fecha de expiración no puede ser en el pasado.'
                ]
            ]);
        }
    }

    // Validar código duplicado
    if (!empty($input['code']) && Promotion::codeExists($input['code'])) {
        Response::validationError([
            'code' => [
                'Este codigo ya esta en uso. Por favor elige otro.'
            ]
        ]);
    }

    // Obtener ID del admin autenticado
    $adminId = (int)($user->id ?? $user->sub ?? 0);

    if ($adminId <= 0) {
        Response::error('No se pudo identificar al administrador autenticado.', 401);
    }

    error_log('===== FILES =====');
    error_log(print_r($_FILES, true));
    error_log('===== FILES =====');

    $imagenUrl = null;

if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {

    $file = $_FILES['imagen'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $permitidas, true)) {
        Response::validationError([
            'imagen' => ['Formato de imagen no permitido']
        ]);
    }

    error_log('========== PROMO DEBUG ==========');
    error_log('DIR ACTUAL: ' . __DIR__);
    error_log('BASE DIR: ' . dirname(__DIR__, 3));

    $folder = dirname(__DIR__, 3) . self::PROMOTION_UPLOAD_DIR;

    error_log('FOLDER: ' . $folder);
    error_log('EXISTS: ' . (is_dir($folder) ? 'SI' : 'NO'));
    error_log('WRITABLE: ' . (is_writable($folder) ? 'SI' : 'NO'));
    error_log('========== PROMO DEBUG ==========');


    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }

    $filename = 'promo_' . time() . '_' . uniqid() . '.' . $ext;

    $result = move_uploaded_file(
        $file['tmp_name'],
        $folder . $filename
    );

    error_log('MOVE RESULT: ' . ($result ? 'OK' : 'ERROR'));

    if (!$result) {
        error_log('LAST ERROR: ' . print_r(error_get_last(), true));
        Response::error('No se pudo guardar la imagen', 500);
    }

    error_log('ARCHIVO GUARDADO: ' . $folder . $filename);

    $imagenUrl = self::PROMOTION_DB_PATH . $filename;
}

    $newId = Promotion::create([
        'usuario_id'  => (int)$input['usuario_id'],
        'titulo'      => trim($input['titulo']),
        'descripcion' => $input['descripcion'] ?? null,
        'imagen' => $imagenUrl,
        'deep_link'   => $input['deep_link'] ?? null,
        'code'        => !empty($input['code'])
                            ? strtoupper(trim($input['code']))
                            : null,
        'activo'      => isset($input['activo'])
                            ? (int)$input['activo']
                            : 1,
        'expires_at'  => !empty($input['expires_at'])
                            ? $input['expires_at']
                            : null,
    ], $adminId);

    $promotion = Promotion::findById($newId);

    Response::success(
        ['promotion' => $promotion],
        'Promocion creada exitosamente',
        201
    );
}
    /**
     * PUT /admin/promotions/:id
     * Actualiza una promocion existente.
     * SOLO accesible desde panel web (requiere rol=admin).
     * Body: cualquier combinacion de campos editables.
     */
    public function adminUpdate(int $id): void
    {
        $user = AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
        }

        // Validar permisos: verificar si el admin puede editar esta promo
        if (!Promotion::canEdit($id, (int)$user->id)) {
            Response::error('No tienes permiso para editar esta promocion', 403);
        }

        $input = ValidationMiddleware::getAllInput();

        if (empty($input)) {
            Response::validationError(['body' => ['No se enviaron campos para actualizar']]);
        }

        // Validar codigo unico si cambia
        if (!empty($input['code']) && Promotion::codeExists($input['code'], $id)) {
            Response::validationError(['code' => ['Este codigo ya esta en uso por otra promocion.']]);
        }

        // Validar formato de expires_at si se proporciona
        if (!empty($input['expires_at'])) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $input['expires_at'])
               ?: \DateTime::createFromFormat('Y-m-d', $input['expires_at']);

            if (!$dt) {
                Response::validationError(['expires_at' => ['Formato de fecha invalido. Usa YYYY-MM-DD o YYYY-MM-DD HH:MM:SS']]);
            }

            // Validar que no sea fecha pasada
            if (!Promotion::isValidFutureDate($input['expires_at'])) {
                Response::validationError(['expires_at' => ['La fecha de expiración no puede ser en el pasado.']]);
            }
        }

        // Normalizar codigo a mayusculas si se proporciona
        if (isset($input['code']) && $input['code'] !== null) {
            $input['code'] = strtoupper(trim($input['code']));
        }

        Promotion::update($id, $input, (int)$user->id);

        $updated = Promotion::findById($id);

        Response::success(['promotion' => $updated], 'Promocion actualizada exitosamente');
    }

    /**
     * DELETE /admin/promotions/:id
     * Elimina permanentemente una promocion (hard delete).
     * SOLO accesible desde panel web (requiere rol=admin).
     */
    public function adminDestroy(int $id): void
    {
        $user = AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
        }

        // Validar permisos: verificar si el admin puede editar esta promo
        if (!Promotion::canEdit($id, (int)$user->id)) {
            Response::error('No tienes permiso para eliminar esta promocion', 403);
        }

        $deleted = Promotion::delete($id);

        if (!$deleted) {
            Response::error('No se pudo eliminar la promocion', 500);
        }

        Response::success(null, 'Promocion eliminada exitosamente');
    }

    /**
     * PUT /admin/promotions/:id/deactivate
     * Desactiva (soft-delete) una promocion sin eliminarla.
     * SOLO accesible desde panel web (requiere rol=admin).
     */
    public function adminDeactivate(int $id): void
    {
        $user = AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
        }

        if ((int)$promotion['activo'] === 0) {
            Response::error('La promocion ya esta desactivada', 422);
        }

        // Validar permisos: verificar si el admin puede editar esta promo
        if (!Promotion::canEdit($id, (int)$user->id)) {
            Response::error('No tienes permiso para desactivar esta promocion', 403);
        }

        Promotion::deactivate($id, (int)$user->id);

        $updated = Promotion::findById($id);

        Response::success(['promotion' => $updated], 'Promocion desactivada exitosamente');
    }

    public function uploadImage(): void
{
    AuthMiddleware::requireAdmin();

    if (!isset($_FILES['image'])) {
        Response::error('No se recibió ninguna imagen', 400);
    }

    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        Response::error('Error al subir archivo', 400);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

    if (!in_array($ext, $permitidas, true)) {
        Response::error('Formato no permitido', 422);
    }

    $folder = dirname(__DIR__, 3) . self::PROMOTION_UPLOAD_DIR;

    if (!is_dir($folder)) {
        mkdir($folder, 0775, true);
    }

    $filename = 'promo_' . time() . '_' . uniqid() . '.' . $ext;

    $result = move_uploaded_file(
        $file['tmp_name'],
        $folder . $filename
    );

    if (!$result) {
        Response::error('No se pudo guardar la imagen', 500);
    }

    $path = self::PROMOTION_DB_PATH . $filename;
    $publicBaseUrl = preg_replace(
        '#/api_restaurante/?$#',
        '',
        rtrim($_ENV['APP_URL'] ?? 'https://amarerestaurant.club/api_restaurante', '/')
    );

    Response::success([
        'path' => $path,
        'url' => $publicBaseUrl . '/' . $path
    ]);
}
}
