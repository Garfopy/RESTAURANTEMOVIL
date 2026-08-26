<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Promotion;
use Amare\Api\Models\User;
use Amare\Api\Services\FirebaseMessagingService;

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
     * GET /promotions/history
     * Devuelve los codigos que el usuario ya consumio.
     */
    public function history(): void
    {
        $user = AuthMiddleware::authenticate();
        Response::success(Promotion::getUsageHistory((int)$user->id));
    }

    /**
     * GET /promotions/:id
     * Detalle de una promoción específica.
     */
    public function show(int $id): void
    {
        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promoción no encontrada');
        }

        Response::success(['promotion' => $promotion]);
    }

    /**
     * POST /promotions/validate
     * Valida un codigo de promocion contra los productos del carrito.
     */
    public function validateCode(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'code' => 'required|string|max:50',
            'items' => 'required|array',
        ];

        $errors = ValidationMiddleware::validate($rules, $input);
        if (!empty($errors)) {
            Response::validationError($errors);
        }

        try {
            $quote = Promotion::quoteCode((string)$input['code'], (int)$user->id, (array)$input['items']);
        } catch (\DomainException $exception) {
            Response::error($exception->getMessage(), 422);
        }

        if (!$quote) {
            Response::error('Codigo de promocion invalido, expirado o no asignado a este usuario', 404);
        }

        Response::success($quote);
    }

    /**
     * POST /admin/promotions/validate
     * Valida un código promocional (SOLO para admin desde panel web).
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
            Response::error('Código de promoción inválido, expirado o no asignado a este usuario', 404);
        }

        Response::success(['promotion' => $promotion]);
    }

    // =========================================================================
    // ENDPOINTS ADMIN (requieren rol = 'admin')
    // =========================================================================

    /**
     * GET /admin/promotions
     * Lista TODAS las promociones para el panel web admin con paginación.
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
     * Crea una nueva promoción para un usuario específico.
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
                    'Formato de fecha inválido. Usa YYYY-MM-DD o YYYY-MM-DD HH:MM:SS'
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
                'Este código ya está en uso. Por favor elige otro.'
            ]
        ]);
    }

    $discountInput = $this->normalizeDiscountInput($input) + [
        'discount_type' => null,
        'discount_value' => null,
    ];

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

    $productId = $input['producto_id'] ?? $input['product_id'] ?? $input['platillo_id'] ?? null;

    $promotionData = [
        'usuario_id'  => (int)$input['usuario_id'],
        'platillo_id' => !empty($input['platillo_id']) ? (int)$input['platillo_id'] : null,
        'producto_id' => !empty($productId) ? (int)$productId : null,
        'titulo'      => trim($input['titulo']),
        'descripcion' => $input['descripcion'] ?? null,
        'imagen' => $imagenUrl,
        'deep_link'   => $input['deep_link'] ?? null,
        'code'        => !empty($input['code'])
                            ? strtoupper(trim($input['code']))
                            : null,
        'discount_type' => $discountInput['discount_type'],
        'discount_value' => $discountInput['discount_value'],
        'activo'      => isset($input['activo'])
                            ? (int)$input['activo']
                            : 1,
        'expires_at'  => !empty($input['expires_at'])
                            ? $input['expires_at']
                            : null,
    ];

    foreach ([
        'tipo_descuento',
        'valor_descuento',
        'scope_tipo',
        'scope_ids',
        'buy_qty',
        'pay_qty',
        'min_subtotal',
        'max_uses',
        'combinable',
    ] as $ruleField) {
        if (array_key_exists($ruleField, $input)) {
            $promotionData[$ruleField] = $input[$ruleField];
        }
    }

    $newId = Promotion::create($promotionData, $adminId);

    $promotion = Promotion::findById($newId);
    $this->notifyPromotionActivated($promotion);

    Response::success(
        ['promotion' => $promotion],
        'Promoción creada exitosamente',
        201
    );
}
    /**
     * PUT /admin/promotions/:id
     * Actualiza una promoción existente.
     * SOLO accesible desde panel web (requiere rol=admin).
     * Body: cualquier combinacion de campos editables.
     */
    public function adminUpdate(int $id): void
    {
        $user = AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promoción no encontrada');
        }

        // Validar permisos: verificar si el admin puede editar esta promo
        if (!Promotion::canEdit($id, (int)$user->id)) {
            Response::error('No tienes permiso para editar esta promoción', 403);
        }

        $input = ValidationMiddleware::getAllInput();

        if (empty($input)) {
            Response::validationError(['body' => ['No se enviaron campos para actualizar']]);
        }

        // Validar código único si cambia
        if (!empty($input['code']) && Promotion::codeExists($input['code'], $id)) {
            Response::validationError(['code' => ['Este código ya está en uso por otra promoción.']]);
        }

        // Validar formato de expires_at si se proporciona
        if (!empty($input['expires_at'])) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $input['expires_at'])
               ?: \DateTime::createFromFormat('Y-m-d', $input['expires_at']);

            if (!$dt) {
                Response::validationError(['expires_at' => ['Formato de fecha inválido. Usa YYYY-MM-DD o YYYY-MM-DD HH:MM:SS']]);
            }

            // Validar que no sea fecha pasada
            if (!Promotion::isValidFutureDate($input['expires_at'])) {
                Response::validationError(['expires_at' => ['La fecha de expiración no puede ser en el pasado.']]);
            }
        }

        // Normalizar código a mayúsculas si se proporciona
        if (isset($input['code']) && $input['code'] !== null) {
            $input['code'] = strtoupper(trim($input['code']));
        }
        if (array_key_exists('platillo_id', $input)) {
            $input['platillo_id'] = !empty($input['platillo_id']) ? (int)$input['platillo_id'] : null;
        }
        if (array_key_exists('product_id', $input) && !array_key_exists('producto_id', $input)) {
            $input['producto_id'] = $input['product_id'];
            unset($input['product_id']);
        }
        if (array_key_exists('producto_id', $input)) {
            $input['producto_id'] = !empty($input['producto_id']) ? (int)$input['producto_id'] : null;
        }
        $discountInput = $this->normalizeDiscountInput($input, $promotion['discount_type'] ?? null);
        if (!empty($discountInput)) {
            $input = array_merge($input, $discountInput);
        }

        $wasActive = (int)($promotion['activo'] ?? 0) === 1;

        Promotion::update($id, $input, (int)$user->id);

        $updated = Promotion::findById($id);
        $isActive = (int)($updated['activo'] ?? 0) === 1;

        if (!$wasActive && $isActive) {
            $this->notifyPromotionActivated($updated);
        }

        Response::success(['promotion' => $updated], 'Promoción actualizada exitosamente');
    }

    /**
     * DELETE /admin/promotions/:id
     * Elimina permanentemente una promoción (hard delete).
     * SOLO accesible desde panel web (requiere rol=admin).
     */
    public function adminDestroy(int $id): void
    {
        $user = AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promoción no encontrada');
        }

        // Validar permisos: verificar si el admin puede editar esta promo
        if (!Promotion::canEdit($id, (int)$user->id)) {
            Response::error('No tienes permiso para eliminar esta promoción', 403);
        }

        $deleted = Promotion::delete($id);

        if (!$deleted) {
            Response::error('No se pudo eliminar la promoción', 500);
        }

        Response::success(null, 'Promoción eliminada exitosamente');
    }

    /**
     * PUT /admin/promotions/:id/deactivate
     * Desactiva (soft-delete) una promoción sin eliminarla.
     * SOLO accesible desde panel web (requiere rol=admin).
     */
    public function adminDeactivate(int $id): void
    {
        $user = AuthMiddleware::requireAdmin();

        $promotion = Promotion::findById($id);

        if (!$promotion) {
            Response::notFound('Promoción no encontrada');
        }

        if ((int)$promotion['activo'] === 0) {
            Response::error('La promoción ya está desactivada', 422);
        }

        // Validar permisos: verificar si el admin puede editar esta promo
        if (!Promotion::canEdit($id, (int)$user->id)) {
            Response::error('No tienes permiso para desactivar esta promoción', 403);
        }

        Promotion::deactivate($id, (int)$user->id);

        $updated = Promotion::findById($id);

        Response::success(['promotion' => $updated], 'Promoción desactivada exitosamente');
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
        '#/(api_restaurante|backend_php|api-php)/?$#',
        '',
        rtrim($_ENV['APP_URL'] ?? 'https://idactivos.digital/cafeuteq/api-php', '/')
    );

    Response::success([
        'path' => $path,
        'url' => $publicBaseUrl . '/' . $path
    ]);
}

    private function notifyPromotionActivated(?array $promotion): void
    {
        if (!$promotion || (int)($promotion['activo'] ?? 0) !== 1) {
            return;
        }

        if (!empty($promotion['expires_at']) && strtotime((string)$promotion['expires_at']) <= time()) {
            return;
        }

        $userId = (int)($promotion['usuario_id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $recipient = User::findById($userId);
        if (!$recipient || !(bool)($recipient['marketing_opt_in'] ?? false)) {
            return;
        }

        $title = (string)($promotion['titulo'] ?? 'Tienes una nueva promocion');
        $description = trim((string)($promotion['descripcion'] ?? ''));
        $body = $description !== '' ? $description : 'Abre Amare para ver tu promocion.';

        try {
            (new FirebaseMessagingService())->sendToUser($userId, $title, $body, [
                'type' => 'promotion_activated',
                'promotion_id' => (int)$promotion['id'],
                'deep_link' => '/promotions?promotionId=' . (int)$promotion['id'],
                'code' => $promotion['code'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            error_log('PromotionsController::notifyPromotionActivated ERROR: ' . $exception->getMessage());
        }
    }

    private function normalizeDiscountInput(array $input, ?string $existingType = null): array
    {
        $typeInput = $this->firstInputValue($input, ['discount_type', 'tipo_promocion', 'promotion_type', 'tipo_descuento', 'tipo']);
        $valueInput = $this->firstInputValue($input, [
            'discount_value',
            'porcentaje_descuento',
            'valor_descuento',
            'monto_descuento',
            'monto_fijo',
            'precio_final',
            'discount_percent',
            'discount_percentage',
            'discount_amount',
        ]);
        $hasType = $typeInput['exists'];
        $hasValue = $valueInput['exists'];

        if (!$hasType && !$hasValue) {
            return [];
        }

        $rawType = $hasType ? trim((string)$typeInput['value']) : trim((string)($existingType ?? ''));
        if ($rawType === '' || strtolower($rawType) === 'none' || strtolower($rawType) === 'null') {
            return [
                'discount_type' => null,
                'discount_value' => null,
            ];
        }

        $type = $this->normalizeDiscountType($rawType);
        $allowed = ['percent', 'amount', 'fixed_price', 'free_item', 'bogo'];
        if (!in_array($type, $allowed, true)) {
            Response::validationError([
                'discount_type' => ['Tipo de descuento invalido.']
            ]);
        }

        $value = null;
        if ($hasValue && $valueInput['value'] !== '' && $valueInput['value'] !== null) {
            $value = round((float)$valueInput['value'], 2);
        }

        if (in_array($type, ['percent', 'amount', 'fixed_price'], true)) {
            if ($value === null || $value <= 0) {
                Response::validationError([
                    'discount_value' => ['El valor del descuento debe ser mayor a 0.']
                ]);
            }
            if ($type === 'percent' && $value > 100) {
                Response::validationError([
                    'discount_value' => ['El porcentaje no puede ser mayor a 100.']
                ]);
            }
        }

        return [
            'discount_type' => $type,
            'discount_value' => $value,
        ];
    }

    /**
     * @param array<int, string> $keys
     * @return array{exists: bool, value: mixed}
     */
    private function firstInputValue(array $input, array $keys): array
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $input)) {
                return ['exists' => true, 'value' => $input[$key]];
            }
        }

        return ['exists' => false, 'value' => null];
    }

    private function normalizeDiscountType(string $type): string
    {
        $normalized = strtolower(trim($type));
        $normalized = str_replace([' ', '-'], '_', $normalized);
        $normalized = strtr($normalized, [
            'porcentaje' => 'percent',
            'percentage' => 'percent',
            'percentual' => 'percent',
            'monto' => 'amount',
            'monto_fijo' => 'amount',
            'fixed_amount' => 'amount',
            'importe' => 'amount',
            'precio_fijo' => 'fixed_price',
            'precio_final' => 'fixed_price',
            'fixed' => 'fixed_price',
            'gratis' => 'free_item',
            'producto_gratis' => 'free_item',
            'free' => 'free_item',
            'paquete' => 'bogo',
            'package' => 'bogo',
            '2x1' => 'bogo',
            '2_x_1' => 'bogo',
        ]);

        return $normalized;
    }
}
