<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class Promotion
{
    private static array $tableColumns = [];
    private static ?bool $usageTableAvailable = null;

    private static function columnExists(string $column): bool
    {
        if (!isset(self::$tableColumns['mobile_promociones'])) {
            $columns = Database::query('SHOW COLUMNS FROM mobile_promociones');
            self::$tableColumns['mobile_promociones'] = array_values(array_map(
                static fn(array $row): string => (string)($row['Field'] ?? ''),
                $columns
            ));
        }

        return in_array($column, self::$tableColumns['mobile_promociones'], true);
    }

    private static function selectColumns(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $productIdExpression = self::productIdExpression($alias);
        $fields = [
            "{$prefix}id",
            "{$prefix}usuario_id",
            "{$productIdExpression} AS producto_id",
            "{$productIdExpression} AS platillo_id",
            "(SELECT rp.restaurante_id FROM rest_platillos rp WHERE rp.id = {$productIdExpression} LIMIT 1) AS restaurante_id",
        ];

        return implode(', ', array_merge($fields, [
            "{$prefix}titulo",
            "{$prefix}descripcion",
            "{$prefix}imagen",
            "{$prefix}deep_link",
            "{$prefix}code",
            self::discountTypeExpression($alias) . " AS discount_type",
            self::discountValueExpression($alias) . " AS discount_value",
            self::selectNullableColumn('tipo_descuento', $alias),
            self::selectNullableColumn('valor_descuento', $alias),
            self::selectNullableColumn('scope_tipo', $alias),
            self::selectNullableColumn('scope_ids', $alias),
            self::selectNullableColumn('buy_qty', $alias),
            self::selectNullableColumn('pay_qty', $alias),
            self::selectNullableColumn('min_subtotal', $alias),
            self::selectNullableColumn('max_uses', $alias),
            self::selectNullableColumn('combinable', $alias),
            "{$prefix}activo",
            "{$prefix}expires_at",
            "{$prefix}created_at",
        ]));
    }

    private static function selectNullableColumn(string $column, string $alias = ''): string
    {
        if (self::columnExists($column)) {
            $prefix = $alias !== '' ? $alias . '.' : '';
            return "{$prefix}{$column}";
        }

        return "NULL AS {$column}";
    }

    private static function productIdExpression(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $productCandidates = [];
        if (self::columnExists('producto_id')) {
            $productCandidates[] = "{$prefix}producto_id";
        }
        if (self::columnExists('platillo_id')) {
            $productCandidates[] = "{$prefix}platillo_id";
        }
        $fallback = "CASE
            WHEN {$prefix}deep_link REGEXP '/(product|products|producto|productos|platillo|platillos)/[0-9]+'
                THEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(SUBSTRING_INDEX({$prefix}deep_link, '?', 1), '#', 1), '/', -1) AS UNSIGNED)
            ELSE NULL
        END";

        if (!empty($productCandidates)) {
            $productCandidates[] = $fallback;
            return "COALESCE(" . implode(', ', $productCandidates) . ")";
        }

        return $fallback;
    }

    private static function discountTypeExpression(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $candidates = [];
        if (self::columnExists('tipo_descuento')) {
            $candidates[] = "CASE LOWER({$prefix}tipo_descuento)
                WHEN 'porcentaje' THEN 'percent'
                WHEN 'monto_fijo' THEN 'amount'
                WHEN 'monto' THEN 'amount'
                WHEN 'precio_final' THEN 'fixed_price'
                WHEN 'precio_fijo' THEN 'fixed_price'
                WHEN 'producto_gratis' THEN 'free_item'
                WHEN 'gratis' THEN 'free_item'
                WHEN 'paquete' THEN 'bogo'
                WHEN '2x1' THEN 'bogo'
                ELSE NULLIF({$prefix}tipo_descuento, '')
            END";
        }
        if (self::columnExists('discount_type')) {
            $candidates[] = "NULLIF({$prefix}discount_type, '')";
        }

        return !empty($candidates) ? 'COALESCE(' . implode(', ', $candidates) . ')' : 'NULL';
    }

    private static function discountValueExpression(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        $candidates = [];
        if (self::columnExists('valor_descuento')) {
            $candidates[] = "{$prefix}valor_descuento";
        }
        if (self::columnExists('discount_value')) {
            $candidates[] = "{$prefix}discount_value";
        }

        return !empty($candidates) ? 'COALESCE(' . implode(', ', $candidates) . ')' : 'NULL';
    }

    /**
     * Obtener promociones activas de un usuario específico (app móvil).
     * Filtra por activo = 1 y que no hayan expirado.
     */
    public static function getByUser(int $userId): array
    {
        $sql = "SELECT " . self::selectColumns() . "
                FROM mobile_promociones
                WHERE activo = 1
                  AND usuario_id = :usuario_id
                  AND (expires_at IS NULL OR expires_at > NOW())";

        if (self::usageTableExists()) {
            $sql .= "
                  AND NOT EXISTS (
                      SELECT 1
                        FROM mobile_promocion_usos pu
                       WHERE pu.promocion_id = mobile_promociones.id
                         AND pu.usuario_id = mobile_promociones.usuario_id
                         AND pu.estado = 'usado'
                  )";
        }

        $sql .= " ORDER BY created_at DESC";

        return Database::query($sql, [':usuario_id' => $userId]);
    }

    /**
     * Historial de promociones consumidas por el usuario autenticado.
     */
    public static function getUsageHistory(int $userId): array
    {
        if (!self::usageTableExists()) {
            return [];
        }

        $sql = "SELECT " . self::selectColumns('p') . ",
                       pu.pedido_id AS uso_pedido_id,
                       pu.codigo AS uso_codigo,
                       pu.descuento_mxn AS uso_descuento_mxn,
                       pu.estado AS uso_estado,
                       pu.usado_at
                  FROM mobile_promocion_usos pu
                  INNER JOIN mobile_promociones p ON p.id = pu.promocion_id
                 WHERE pu.usuario_id = :usuario_id
                   AND pu.estado = 'usado'
                 ORDER BY pu.usado_at DESC";

        return Database::query($sql, [':usuario_id' => $userId]);
    }

    /**
     * Obtener todas las promociones activas (sin filtro de usuario).
     * Útil para promos globales o vista admin pública.
     */
    public static function getAll(?int $userId = null): array
    {
        $sql = "SELECT " . self::selectColumns() . "
                FROM mobile_promociones
                WHERE activo = 1
                  AND (expires_at IS NULL OR expires_at > NOW())";

        $params = [];

        if ($userId !== null) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
        }

        $sql .= " ORDER BY created_at DESC";

        return Database::query($sql, $params);
    }

    /**
     * Obtener TODAS las promociones para el panel admin (incluyendo inactivas / expiradas).
     * Incluye JOIN con el usuario para mostrar nombre y email.
     */
    public static function getAllForAdmin(int $limit = 50, int $offset = 0, ?int $usuarioId = null): array
    {
        $where = '1=1';
        $params = [];

        if ($usuarioId !== null) {
            $where .= ' AND p.usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $sql = "SELECT
                    " . self::selectColumns('p') . ",
                    u.nombre  AS usuario_nombre,
                    u.email   AS usuario_email
                FROM mobile_promociones p
                LEFT JOIN mobile_usuarios u ON u.id = p.usuario_id
                WHERE {$where}
                ORDER BY p.created_at DESC
                LIMIT :limit OFFSET :offset";

        $params[':limit']  = $limit;
        $params[':offset'] = $offset;

        return Database::query($sql, $params);
    }

    /**
     * Contar total de promociones para paginación en admin.
     */
    public static function countForAdmin(?int $usuarioId = null): int
    {
        $where = '1=1';
        $params = [];

        if ($usuarioId !== null) {
            $where .= ' AND usuario_id = :usuario_id';
            $params[':usuario_id'] = $usuarioId;
        }

        $sql = "SELECT COUNT(*) as total FROM mobile_promociones WHERE {$where}";
        $result = Database::queryOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Buscar una promoción por ID (incluye datos del usuario asignado).
     */
    public static function findById(int $id): ?array
    {
        $sql = "SELECT
                    " . self::selectColumns('p') . ",
                    u.nombre  AS usuario_nombre,
                    u.email   AS usuario_email
                FROM mobile_promociones p
                LEFT JOIN mobile_usuarios u ON u.id = p.usuario_id
                WHERE p.id = :id
                LIMIT 1";

        return Database::queryOne($sql, [':id' => $id]);
    }

    /**
     * Crear una nueva promoción (SOLO desde admin web).
     * Requiere created_by (ID del admin que la crea).
     * Retorna el ID insertado.
     */
    public static function create(array $data, int $createdBy): int
    {
        $columns = ['usuario_id', 'titulo', 'descripcion', 'imagen', 'deep_link', 'code', 'activo', 'expires_at', 'created_at', 'created_by'];
        $values = [':usuario_id', ':titulo', ':descripcion', ':imagen', ':deep_link', ':code', ':activo', ':expires_at', 'NOW()', ':created_by'];
        $params = [
            ':usuario_id'  => $data['usuario_id'],
            ':titulo'      => $data['titulo'],
            ':descripcion' => $data['descripcion'] ?? null,
            ':imagen'      => $data['imagen'] ?? null,
            ':deep_link'   => $data['deep_link'] ?? null,
            ':code'        => $data['code'] ?? null,
            ':activo'      => $data['activo'] ?? 1,
            ':expires_at'  => $data['expires_at'] ?? null,
            ':created_by'  => $createdBy,
        ];

        if (self::columnExists('platillo_id')) {
            array_splice($columns, 1, 0, 'platillo_id');
            array_splice($values, 1, 0, ':platillo_id');
            $params[':platillo_id'] = !empty($data['platillo_id']) ? (int)$data['platillo_id'] : null;
        }
        if (self::columnExists('producto_id')) {
            array_splice($columns, 1, 0, 'producto_id');
            array_splice($values, 1, 0, ':producto_id');
            $params[':producto_id'] = !empty($data['producto_id'] ?? $data['product_id'] ?? null)
                ? (int)($data['producto_id'] ?? $data['product_id'])
                : (!empty($data['platillo_id']) ? (int)$data['platillo_id'] : null);
        }

        $canonicalType = self::canonicalDiscountType($data['discount_type'] ?? $data['tipo_descuento'] ?? null);
        $discountValue = self::discountValue($data);

        if (self::columnExists('discount_type')) {
            $columns[] = 'discount_type';
            $values[] = ':discount_type';
            $params[':discount_type'] = $canonicalType;
        }

        if (self::columnExists('discount_value')) {
            $columns[] = 'discount_value';
            $values[] = ':discount_value';
            $params[':discount_value'] = $discountValue;
        }
        if (self::columnExists('tipo_descuento')) {
            $columns[] = 'tipo_descuento';
            $values[] = ':tipo_descuento';
            $params[':tipo_descuento'] = self::spanishDiscountType($canonicalType);
        }
        if (self::columnExists('valor_descuento')) {
            $columns[] = 'valor_descuento';
            $values[] = ':valor_descuento';
            $params[':valor_descuento'] = $discountValue ?? 0.0;
        }
        foreach (['scope_tipo', 'scope_ids', 'buy_qty', 'pay_qty', 'min_subtotal', 'max_uses', 'combinable'] as $optionalColumn) {
            if (self::columnExists($optionalColumn)) {
                $columns[] = $optionalColumn;
                $values[] = ':' . $optionalColumn;
                $params[':' . $optionalColumn] = self::preparePromotionColumnValue(
                    $optionalColumn,
                    $data[$optionalColumn] ?? self::defaultPromotionColumnValue($optionalColumn)
                );
            }
        }

        $sql = 'INSERT INTO mobile_promociones (' . implode(', ', $columns) . ')
                VALUES (' . implode(', ', $values) . ')';

        return Database::execute($sql, $params);
    }

    /**
     * Actualizar una promoción existente (SOLO admin).
     * Registra quién hizo la actualización (updated_by) y cuándo (updated_at).
     */
    public static function update(int $id, array $data, int $updatedBy): bool
    {
        $setClause = [];
        $params = [':id' => $id, ':updated_by' => $updatedBy];

        $allowed = ['titulo', 'descripcion', 'imagen', 'deep_link', 'code', 'activo', 'expires_at', 'usuario_id'];
        if (self::columnExists('platillo_id')) {
            $allowed[] = 'platillo_id';
        }
        if (self::columnExists('producto_id')) {
            $allowed[] = 'producto_id';
        }
        if (self::columnExists('discount_type')) {
            $allowed[] = 'discount_type';
        }
        if (self::columnExists('discount_value')) {
            $allowed[] = 'discount_value';
        }
        if (self::columnExists('tipo_descuento')) {
            $allowed[] = 'tipo_descuento';
        }
        if (self::columnExists('valor_descuento')) {
            $allowed[] = 'valor_descuento';
        }
        foreach (['scope_tipo', 'scope_ids', 'buy_qty', 'pay_qty', 'min_subtotal', 'max_uses', 'combinable'] as $optionalColumn) {
            if (self::columnExists($optionalColumn)) {
                $allowed[] = $optionalColumn;
            }
        }

        if (
            (array_key_exists('discount_type', $data) || array_key_exists('tipo_descuento', $data))
            && (self::columnExists('discount_type') || self::columnExists('tipo_descuento'))
        ) {
            $canonicalType = self::canonicalDiscountType($data['discount_type'] ?? $data['tipo_descuento'] ?? null);
            if (self::columnExists('discount_type')) {
                $data['discount_type'] = $canonicalType;
            }
            if (self::columnExists('tipo_descuento')) {
                $data['tipo_descuento'] = self::spanishDiscountType($canonicalType);
            }
        }
        if (
            (array_key_exists('discount_value', $data) || array_key_exists('valor_descuento', $data))
            && (self::columnExists('discount_value') || self::columnExists('valor_descuento'))
        ) {
            $discountValue = self::discountValue($data);
            if (self::columnExists('discount_value')) {
                $data['discount_value'] = $discountValue;
            }
            if (self::columnExists('valor_descuento')) {
                $data['valor_descuento'] = $discountValue ?? 0.0;
            }
        }
        if (array_key_exists('platillo_id', $data) && !array_key_exists('producto_id', $data) && self::columnExists('producto_id')) {
            $data['producto_id'] = $data['platillo_id'];
        }

        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $setClause[] = "{$key} = :{$key}";
                $params[":{$key}"] = self::preparePromotionColumnValue($key, $data[$key]);
            }
        }

        if (empty($setClause)) {
            return false;
        }

        // Siempre agregar updated_at y updated_by
        $setClause[] = "updated_at = NOW()";
        $setClause[] = "updated_by = :updated_by";

        $sql = "UPDATE mobile_promociones SET " . implode(', ', $setClause) . " WHERE id = :id";
        return Database::rowCount($sql, $params) > 0;
    }

    /**
     * Eliminar una promoción (hard delete – admin).
     */
    public static function delete(int $id): bool
    {
        $sql = "DELETE FROM mobile_promociones WHERE id = :id";
        return Database::rowCount($sql, [':id' => $id]) > 0;
    }

    /**
     * Desactivar (soft-delete) una promoción.
     * Registra quién la desactivó.
     */
    public static function deactivate(int $id, int $deactivatedBy): bool
    {
        $sql = "UPDATE mobile_promociones 
                SET activo = 0, updated_at = NOW(), updated_by = :updated_by 
                WHERE id = :id";
        return Database::rowCount($sql, [':id' => $id, ':updated_by' => $deactivatedBy]) > 0;
    }

    /**
     * Validar un código promocional para un usuario específico (app móvil).
     */
    public static function validateCode(string $code, ?int $userId = null, bool $onlyUnused = false): ?array
    {
        $sql = "SELECT " . self::selectColumns() . "
                FROM mobile_promociones
                WHERE UPPER(code) = UPPER(:code)
                  AND activo = 1
                  AND (expires_at IS NULL OR expires_at > NOW())";

        $params = [':code' => $code];

        if ($userId !== null) {
            $sql .= " AND usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;

            if ($onlyUnused && self::usageTableExists()) {
                $sql .= " AND NOT EXISTS (
                              SELECT 1
                                FROM mobile_promocion_usos pu
                               WHERE pu.promocion_id = mobile_promociones.id
                                 AND pu.usuario_id = mobile_promociones.usuario_id
                                 AND pu.estado = 'usado'
                          )";
            }
        }

        $sql .= " LIMIT 1";

        $result = Database::queryOne($sql, $params);
        return $result ?: null;
    }

    /**
     * Verificar si un código ya existe (para evitar duplicados al crear admin).
     * Cotiza un codigo promocional contra los productos del carrito.
     */
    public static function quoteCode(string $code, int $userId, array $items): ?array
    {
        $promotion = self::validateCode(trim($code), $userId, true);
        if (!$promotion) {
            return null;
        }

        $maxUses = isset($promotion['max_uses']) ? (int)$promotion['max_uses'] : null;
        if ($maxUses !== null && $maxUses > 0 && self::countCodeUsage((string)$promotion['code']) >= $maxUses) {
            throw new \DomainException('Este codigo alcanzo su limite maximo de usos.');
        }

        $normalizedItems = self::normalizeCartItems($items);
        if (empty($normalizedItems)) {
            throw new \DomainException('Agrega productos al carrito para usar este codigo.');
        }

        $subtotal = self::sumItems($normalizedItems);
        $eligibleItems = self::filterEligibleItems($promotion, $normalizedItems);
        $applicableProductIds = self::extractProductIds($promotion, $eligibleItems);

        if (empty($eligibleItems)) {
            throw new \DomainException('Este codigo no es valido para los productos de tu carrito.');
        }

        $eligibleSubtotal = self::sumItems($eligibleItems);
        $discount = self::calculateDiscount($promotion, $eligibleItems, $eligibleSubtotal, $subtotal);

        if ($discount <= 0) {
            throw new \DomainException('Este codigo no cumple las condiciones para tu carrito.');
        }

        $discount = min($discount, $eligibleSubtotal, $subtotal);

        return [
            'promotion' => $promotion,
            'code' => $promotion['code'],
            'discount' => round($discount, 2),
            'subtotal' => round($subtotal, 2),
            'eligible_subtotal' => round($eligibleSubtotal, 2),
            'total' => round(max(0, $subtotal - $discount), 2),
            'applicable_product_ids' => $applicableProductIds,
        ];
    }

    /**
     * Marca una promocion como usada de forma idempotente.
     * Debe llamarse dentro de la misma transaccion que confirma el pedido.
     */
    public static function recordUsageForOrder(\PDO $pdo, int $userId, array $order): bool
    {
        $code = strtoupper(trim((string)($order['promo_code'] ?? $order['coupon_code'] ?? '')));
        if ($userId <= 0 || $code === '') {
            return false;
        }

        if (!self::usageTableExists()) {
            throw new \RuntimeException('Falta aplicar la migracion de uso unico de promociones.');
        }

        $promotion = Database::queryOne(
            'SELECT id, code FROM mobile_promociones
              WHERE usuario_id = :usuario_id AND UPPER(code) = :code
              ORDER BY id ASC LIMIT 1',
            [':usuario_id' => $userId, ':code' => $code]
        );
        if (!$promotion) {
            throw new \RuntimeException('No se encontro la promocion aplicada al pedido.');
        }

        $statement = $pdo->prepare(
            "INSERT IGNORE INTO mobile_promocion_usos
                (promocion_id, usuario_id, pedido_id, codigo, descuento_mxn, estado, usado_at, created_at)
             VALUES (:promocion_id, :usuario_id, :pedido_id, :codigo, :descuento_mxn, 'usado', NOW(), NOW())"
        );
        $statement->execute([
            ':promocion_id' => (int)$promotion['id'],
            ':usuario_id' => $userId,
            ':pedido_id' => (int)($order['id'] ?? 0) ?: null,
            ':codigo' => (string)($promotion['code'] ?? $code),
            ':descuento_mxn' => round((float)($order['descuento'] ?? $order['promo_discount'] ?? 0), 2),
        ]);

        return $statement->rowCount() > 0;
    }

    private static function usageTableExists(): bool
    {
        if (self::$usageTableAvailable !== null) {
            return self::$usageTableAvailable;
        }

        $row = Database::queryOne(
            "SELECT COUNT(*) AS total
               FROM information_schema.TABLES
              WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'mobile_promocion_usos'"
        );
        self::$usageTableAvailable = (int)($row['total'] ?? 0) > 0;
        return self::$usageTableAvailable;
    }

    /**
     * Cuenta cuantas veces se ha redimido un codigo en total (todas las
     * asignaciones de usuario que comparten ese texto de codigo), para
     * poder aplicar el limite global 'max_uses' de la campana.
     */
    private static function countCodeUsage(string $code): int
    {
        if (!self::usageTableExists() || $code === '') {
            return 0;
        }

        $row = Database::queryOne(
            "SELECT COUNT(*) AS total
               FROM mobile_promocion_usos
              WHERE UPPER(codigo) = UPPER(:code)
                AND estado = 'usado'",
            [':code' => $code]
        );

        return (int)($row['total'] ?? 0);
    }

    private static function filterEligibleItems(array $promotion, array $normalizedItems): array
    {
        $scopeType = self::resolveScopeType($promotion);
        $scopeIds = self::productScopeIds($promotion);

        if ($scopeType === 'all') {
            return $normalizedItems;
        }

        $eligibleItems = [];

        if ($scopeType === 'products') {
            if (empty($scopeIds)) {
                return [];
            }

            foreach ($normalizedItems as $item) {
                if (in_array((int)$item['product_id'], $scopeIds, true)) {
                    $eligibleItems[] = $item;
                }
            }

            return $eligibleItems;
        }

        if ($scopeType === 'categories') {
            $scopeIds = self::parseIdList($promotion['scope_ids'] ?? null);
            if (empty($scopeIds)) {
                return [];
            }

            $categoriesByProduct = self::getProductCategoryMap(
                array_values(array_unique(array_map(static fn(array $item): int => (int)$item['product_id'], $normalizedItems)))
            );

            foreach ($normalizedItems as $item) {
                $categoryId = (int)($categoriesByProduct[(int)$item['product_id']] ?? 0);
                if ($categoryId > 0 && in_array($categoryId, $scopeIds, true)) {
                    $eligibleItems[] = $item;
                }
            }

            return $eligibleItems;
        }

        return $normalizedItems;
    }

    private static function getProductCategoryMap(array $productIds): array
    {
        $productIds = array_values(array_unique(array_filter(array_map('intval', $productIds), static fn(int $id): bool => $id > 0)));
        if (empty($productIds)) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($productIds as $index => $productId) {
            $placeholder = ':product_id_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $productId;
        }

        $rows = Database::query(
            'SELECT id, categoria_id FROM rest_platillos WHERE id IN (' . implode(', ', $placeholders) . ')',
            $params
        );

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['id']] = (int)($row['categoria_id'] ?? 0);
        }

        return $map;
    }

    private static function normalizeCartItems(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $productId = (int)($item['product_id'] ?? $item['platillo_id'] ?? $item['id'] ?? 0);
            $quantity = max(0, (int)($item['quantity'] ?? $item['cantidad'] ?? $item['qty'] ?? 0));
            $unitPrice = round((float)($item['unit_price'] ?? $item['precio_unit'] ?? $item['precio_unitario'] ?? $item['price'] ?? 0), 2);
            $origin = strtolower(trim((string)($item['origen'] ?? 'menu')));

            if ($productId <= 0 || $quantity <= 0 || $unitPrice <= 0 || $origin !== 'menu') {
                continue;
            }

            $normalized[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        return $normalized;
    }

    private static function extractProductIds(array $promotion, array $eligibleItems = []): array
    {
        $scopeType = self::resolveScopeType($promotion);
        if ($scopeType === 'all') {
            return [];
        }

        if ($scopeType === 'products') {
            return self::productScopeIds($promotion);
        }

        if ($scopeType === 'categories') {
            return array_values(array_unique(array_map(
                static fn(array $item): int => (int)$item['product_id'],
                $eligibleItems
            )));
        }

        return self::legacyProductIds($promotion);
    }

    private static function productScopeIds(array $promotion): array
    {
        $scopeIds = self::parseIdList($promotion['scope_ids'] ?? null);
        return !empty($scopeIds) ? $scopeIds : self::legacyProductIds($promotion);
    }

    private static function legacyProductIds(array $promotion): array
    {
        $ids = [];

        foreach (['producto_id', 'platillo_id', 'product_id'] as $key) {
            if (!empty($promotion[$key])) {
                $ids[] = (int)$promotion[$key];
            }
        }

        $deepLink = (string)($promotion['deep_link'] ?? '');
        if (preg_match_all('#/(?:product|products|producto|productos|platillo|platillos)/(\d+)#i', $deepLink, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = (int)$id;
            }
        }
        if (preg_match_all('/(?:product_id|platillo_id|producto_id)=([0-9]+)/i', $deepLink, $matches)) {
            foreach ($matches[1] as $id) {
                $ids[] = (int)$id;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
    }

    private static function sumItems(array $items): float
    {
        return array_reduce(
            $items,
            static fn(float $sum, array $item): float => $sum + ((float)$item['unit_price'] * (int)$item['quantity']),
            0.0
        );
    }

    private static function calculateDiscount(array $promotion, array $items, float $eligibleSubtotal, float $subtotal): float
    {
        $structuredDiscount = self::calculateStructuredDiscount($promotion, $items, $eligibleSubtotal, $subtotal);
        if ($structuredDiscount !== null) {
            return $structuredDiscount;
        }

        $text = strtoupper(trim(implode(' ', [
            (string)($promotion['titulo'] ?? ''),
            (string)($promotion['descripcion'] ?? ''),
            (string)($promotion['code'] ?? ''),
        ])));

        if (preg_match('/(\d+)\s*X\s*(\d+)/', $text, $matches) === 1) {
            return self::calculateBogoDiscount($items, (int)$matches[1], (int)$matches[2]);
        }

        if (preg_match('/(\d{1,2})\s*%/', $text, $matches) === 1) {
            $percent = min(100, max(1, (int)$matches[1]));
            return round($eligibleSubtotal * ($percent / 100), 2);
        }

        if (preg_match('/(?:A\s+SOLO|SOLO)\s*\$?\s*(\d+(?:[.,]\d+)?)/', $text, $matches) === 1) {
            $promoPrice = (float)str_replace(',', '.', $matches[1]);
            $quantity = self::sumQuantities($items);
            return round(max(0, $eligibleSubtotal - ($promoPrice * max(1, $quantity))), 2);
        }

        if (str_contains($text, 'GRATIS') || str_contains($text, 'REGALO')) {
            $unitPrices = array_map(static fn(array $item): float => (float)$item['unit_price'], $items);
            return round(min($unitPrices ?: [0]), 2);
        }

        if (
            (str_contains($text, 'DESCUENTO') || str_contains($text, ' OFF') || str_contains($text, 'OFF '))
            && preg_match('/\$?\s*(\d+(?:[.,]\d+)?)\s*(?:MXN|PESOS)?/', $text, $matches) === 1
        ) {
            return round((float)str_replace(',', '.', $matches[1]), 2);
        }

        return 0.0;
    }

    private static function calculateStructuredDiscount(array $promotion, array $items, float $eligibleSubtotal, float $subtotal): ?float
    {
        $type = strtolower(trim((string)($promotion['discount_type'] ?? '')));
        if ($type === '') {
            return null;
        }

        $minimumSubtotal = round((float)($promotion['min_subtotal'] ?? 0), 2);
        if ($minimumSubtotal > 0 && $subtotal < $minimumSubtotal) {
            return 0.0;
        }

        $value = isset($promotion['discount_value']) ? round((float)$promotion['discount_value'], 2) : 0.0;

        switch ($type) {
            case 'percent':
                if ($value <= 0) {
                    return 0.0;
                }
                $percent = min(100.0, max(0.0, $value));
                return round($eligibleSubtotal * ($percent / 100), 2);

            case 'amount':
                return round(min(max(0.0, $value), $eligibleSubtotal), 2);

            case 'fixed_price':
                if ($value < 0) {
                    return 0.0;
                }
                $quantity = self::sumQuantities($items);
                return round(max(0, $eligibleSubtotal - ($value * max(1, $quantity))), 2);

            case 'free_item':
                return round(self::minimumUnitPrice($items), 2);

            case 'bogo':
                return self::calculateBogoDiscount(
                    $items,
                    (int)($promotion['buy_qty'] ?? 2),
                    (int)($promotion['pay_qty'] ?? 1)
                );

            default:
                return null;
        }
    }

    private static function calculateBogoDiscount(array $items, int $buyQty = 2, int $payQty = 1): float
    {
        if ($buyQty <= 1 || $payQty < 0 || $payQty >= $buyQty) {
            return 0.0;
        }

        $unitPrices = [];
        foreach ($items as $item) {
            $quantity = max(0, (int)($item['quantity'] ?? 0));
            for ($i = 0; $i < $quantity; $i++) {
                $unitPrices[] = (float)$item['unit_price'];
            }
        }

        if (count($unitPrices) < $buyQty) {
            return 0.0;
        }

        $freeCount = intdiv(count($unitPrices), $buyQty) * ($buyQty - $payQty);
        if ($freeCount <= 0) {
            return 0.0;
        }

        sort($unitPrices, SORT_NUMERIC);
        return round(array_sum(array_slice($unitPrices, 0, $freeCount)), 2);
    }

    private static function minimumUnitPrice(array $items): float
    {
        $unitPrices = array_map(static fn(array $item): float => (float)$item['unit_price'], $items);
        return min($unitPrices ?: [0.0]);
    }

    private static function sumQuantities(array $items): int
    {
        return array_reduce(
            $items,
            static fn(int $sum, array $item): int => $sum + max(0, (int)($item['quantity'] ?? 0)),
            0
        );
    }

    private static function canonicalDiscountType(mixed $type): ?string
    {
        $normalized = strtolower(trim((string)$type));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'percent', 'percentage', 'porcentaje', 'percentual' => 'percent',
            'amount', 'monto', 'monto_fijo', 'fixed_amount', 'importe' => 'amount',
            'fixed_price', 'precio_final', 'precio_fijo', 'fixed' => 'fixed_price',
            'free_item', 'gratis', 'producto_gratis', 'free' => 'free_item',
            'bogo', 'paquete', 'package', '2x1', '2_x_1' => 'bogo',
            '', 'none', 'null' => null,
            default => $normalized,
        };
    }

    private static function spanishDiscountType(mixed $type): string
    {
        return match (self::canonicalDiscountType($type)) {
            'percent', 'percentage', 'porcentaje' => 'porcentaje',
            'amount', 'monto', 'monto_fijo' => 'monto_fijo',
            'fixed_price', 'precio_final', 'precio_fijo' => 'precio_final',
            'free_item', 'gratis', 'producto_gratis' => 'producto_gratis',
            'bogo', 'paquete', '2x1' => 'paquete',
            default => 'porcentaje',
        };
    }

    private static function discountValue(array $data): ?float
    {
        $value = $data['discount_value'] ?? $data['valor_descuento'] ?? null;
        if ($value === '' || $value === null) {
            return null;
        }

        return round((float)$value, 2);
    }

    private static function normalizeScopeType(mixed $scopeType): string
    {
        $normalized = strtolower(trim((string)$scopeType));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'product', 'products', 'producto', 'productos', 'platillo', 'platillos' => 'products',
            'category', 'categories', 'categoria', 'categorias' => 'categories',
            default => 'all',
        };
    }

    private static function resolveScopeType(array $promotion): string
    {
        $rawScope = $promotion['scope_tipo'] ?? null;
        if ($rawScope !== null && trim((string)$rawScope) !== '') {
            return self::normalizeScopeType($rawScope);
        }

        return !empty(self::legacyProductIds($promotion)) ? 'products' : 'all';
    }

    private static function parseIdList(mixed $value): array
    {
        if (is_array($value)) {
            $rawIds = $value;
        } else {
            $text = trim((string)$value);
            if ($text === '') {
                return [];
            }

            $decoded = json_decode($text, true);
            $rawIds = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $text);
        }

        return array_values(array_unique(array_filter(
            array_map('intval', $rawIds),
            static fn(int $id): bool => $id > 0
        )));
    }

    private static function preparePromotionColumnValue(string $column, mixed $value): mixed
    {
        return match ($column) {
            'scope_tipo' => self::normalizeScopeType($value),
            'scope_ids' => is_array($value)
                ? json_encode(array_values(self::parseIdList($value)), JSON_UNESCAPED_UNICODE)
                : ($value === '' ? null : $value),
            'buy_qty', 'pay_qty', 'max_uses' => ($value === '' || $value === null) ? null : max(0, (int)$value),
            'min_subtotal' => ($value === '' || $value === null) ? 0.0 : round((float)$value, 2),
            'combinable' => !empty($value) ? 1 : 0,
            default => $value,
        };
    }

    private static function defaultPromotionColumnValue(string $column): mixed
    {
        return match ($column) {
            'scope_tipo' => 'all',
            'min_subtotal' => 0.0,
            'combinable' => 0,
            default => null,
        };
    }

    public static function codeExists(string $code, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as cnt FROM mobile_promociones WHERE code = :code";
        $params = [':code' => $code];

        if ($excludeId !== null) {
            $sql .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }

        $result = Database::queryOne($sql, $params);
        return (int)($result['cnt'] ?? 0) > 0;
    }

    /**
     * Verificar si un admin puede editar una promoción.
     * Solo el admin que la creó (o un super-admin) puede editarla.
     * 
     * Actualmente: cualquier admin puede editar cualquier promo
     * Si necesitas restringir por creador, descomentar la lógica.
     */
    public static function canEdit(int $promotionId, int $adminId): bool
    {
        // OPCIÓN 1: Cualquier admin puede editar (comentada actualmente)
        // return true;
        
        // OPCIÓN 2: Solo el admin creador puede editar (descomenta si lo necesitas)
        $sql = "SELECT created_by FROM mobile_promociones WHERE id = :id LIMIT 1";
        $result = Database::queryOne($sql, [':id' => $promotionId]);
        
        if (!$result) {
            return false; // Promoción no existe
        }

        // Por ahora: cualquier admin puede editar (retorna true)
        // Si quieres que solo el creador edite, usa: return (int)$result['created_by'] === $adminId;
        return true;
    }

    /**
     * Validar que una fecha no sea pasada.
     * Retorna true si la fecha es válida (presente o futura), false si es pasada.
     */
    public static function isValidFutureDate(?string $dateString): bool
    {
        if (empty($dateString)) {
            return true; // Si no hay fecha, es válido (puede ser NULL)
        }

        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $now->setTime(0, 0, 0); // Resetear a medianoche para comparar solo fechas

            return $date >= $now;
        } catch (\Exception $e) {
            return false; // Formato inválido
        }
    }
}
