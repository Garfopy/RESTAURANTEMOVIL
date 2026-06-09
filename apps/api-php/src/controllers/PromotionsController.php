<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Promotion;

class PromotionsController
{
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
     * POST /promotions/validate
     * Valida un codigo promocional para el usuario autenticado.
     * Body: { "code": "PROMO123" }
     */
    public function validateCode(): void
    {
        $user = AuthMiddleware::authenticate();

        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'code' => 'required|string|max:50'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $promotion = Promotion::validateCode($input['code'], (int)$user->id);

        if (!$promotion) {
            Response::error('Codigo de promocion invalido o expirado', 404);
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
     *
     * Body requerido:  usuario_id, titulo
     * Body opcional:   descripcion, imagen, deep_link, code, activo, expires_at
     */
    public function adminStore(): void
    {
        AuthMiddleware::requireAdmin();

        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'usuario_id' => 'required|integer',
            'titulo'     => 'required|max:255',
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        // Verificar que el codigo no este duplicado si se proporciona
        if (!empty($input['code']) && Promotion::codeExists($input['code'])) {
            Response::validationError(['code' => ['Este codigo ya esta en uso. Por favor elige otro.']]);
        }

        // Validar formato de expires_at si se proporciona
        if (!empty($input['expires_at'])) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $input['expires_at'])
               ?: \DateTime::createFromFormat('Y-m-d', $input['expires_at']);

            if (!$dt) {
                Response::validationError(['expires_at' => ['Formato de fecha invalido. Usa YYYY-MM-DD o YYYY-MM-DD HH:MM:SS']]);
            }
        }

        $newId = Promotion::create([
            'usuario_id'  => (int)$input['usuario_id'],
            'titulo'      => trim($input['titulo']),
            'descripcion' => $input['descripcion'] ?? null,
            'imagen'      => $input['imagen'] ?? null,
            'deep_link'   => $input['deep_link'] ?? null,
            'code'        => !empty($input['code']) ? strtoupper(trim($input['code'])) : null,
            'activo'      => isset($input['activo']) ? (int)$input['activo'] : 1,
            'expires_at'  => !empty($input['expires_at']) ? $input['expires_at'] : null,
        ]);

        $promotion = Promotion::findById($newId);

        Response::success(['promotion' => $promotion], 'Promocion creada exitosamente', 201);
    }

    /**
     * PUT /admin/promotions/:id
     * Actualiza una promocion existente.
     * Body: cualquier combinacion de campos editables.
     */
    public function adminUpdate(int $id): void
    {
        AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
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
        }

        // Normalizar codigo a mayusculas si se proporciona
        if (isset($input['code']) && $input['code'] !== null) {
            $input['code'] = strtoupper(trim($input['code']));
        }

        Promotion::update($id, $input);

        $updated = Promotion::findById($id);

        Response::success(['promotion' => $updated], 'Promocion actualizada exitosamente');
    }

    /**
     * DELETE /admin/promotions/:id
     * Elimina permanentemente una promocion (hard delete).
     */
    public function adminDestroy(int $id): void
    {
        AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
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
     */
    public function adminDeactivate(int $id): void
    {
        AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promocion no encontrada');
        }

        if ((int)$promotion['activo'] === 0) {
            Response::error('La promocion ya esta desactivada', 422);
        }

        Promotion::deactivate($id);

        $updated = Promotion::findById($id);

        Response::success(['promotion' => $updated], 'Promocion desactivada exitosamente');
    }
}
