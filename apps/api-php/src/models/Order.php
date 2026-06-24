<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;
use PDO;

class Order
{
    /**
     * Caché de columnas existentes por tabla para evitar consultas repetidas.
     * @var array<string, array<string>>
     */
    private static array $tableColumns = [];

    /**
     * Obtiene las columnas reales de una tabla en la BD.
     * @return array<string> nombres de columnas
     */
    private static function getTableColumns(string $tableName): array
    {
        if (isset(self::$tableColumns[$tableName])) {
            return self::$tableColumns[$tableName];
        }

        $pdo = Database::getInstance();
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$tableName}`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

        self::$tableColumns[$tableName] = $columns;
        return $columns;
    }

    /**
     * Construye un INSERT dinámico, omitiendo columnas que no existen en la BD.
     * @return array{sql: string, params: array<string, mixed>}
     */
    private static function buildInsert(string $table, array $data): array
    {
        $columns = self::getTableColumns($table);

        $fields = [];
        $placeholders = [];
        $params = [];

        foreach ($data as $col => $val) {
            if (in_array($col, $columns, true)) {
                $fields[] = "`{$col}`";
                $placeholders[] = ":{$col}";
                $params[":{$col}"] = $val;
            }
        }

        $sql = "INSERT INTO `{$table}` (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";

        return ['sql' => $sql, 'params' => $params];
    }

    /**
     * Verifica si una columna existe en una tabla.
     */
    private static function columnExists(string $table, string $column): bool
    {
        $cols = self::getTableColumns($table);
        return in_array($column, $cols, true);
    }

    private static function tableExists(string $tableName): bool
    {
        $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
        return !empty($exists);
    }

    /**
     * Convierte la seleccion de la app (grupos/opciones) o de CarniHub
     * (modificador_id/cantidad) a cantidades por id.
     * @return array<int, int>
     */
    private static function flattenModifierSelection(array $selection): array
    {
        $quantities = [];
        foreach ($selection as $entry) {
            if (!is_array($entry)) continue;

            if (isset($entry['modificador_id']) && !isset($entry['opciones'])) {
                $id = (int)$entry['modificador_id'];
                $quantity = max(1, (int)($entry['cantidad'] ?? 1));
                if ($id > 0) $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
                continue;
            }

            foreach (($entry['opciones'] ?? []) as $option) {
                if (!is_array($option)) continue;
                $id = (int)($option['opcion_id'] ?? $option['modificador_id'] ?? 0);
                $quantity = max(1, (int)($option['cantidad'] ?? 1));
                if ($id > 0) $quantities[$id] = ($quantities[$id] ?? 0) + $quantity;
            }
        }
        return $quantities;
    }

    /**
     * Fija precios desde la BD y valida el catálogo sincronizado antes de cobrar.
     */
    private static function normalizeAndPriceOrder(array $data): array
    {
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('El pedido no contiene productos.');
        }

        $restaurantId = (int)($data['restaurante_id'] ?? 0);
        $config = Database::queryOne(
            'SELECT exclusiones_habilitadas, extras_habilitados FROM rest_configuracion
              WHERE restaurante_id = :restaurant_id LIMIT 1',
            [':restaurant_id' => $restaurantId]
        );
        $subtotal = 0.0;
        $normalizedItems = [];

        foreach ($data['items'] as $item) {
            $quantity = (int)($item['cantidad'] ?? $item['quantity'] ?? 0);
            $dishId = (int)($item['platillo_id'] ?? $item['product_id'] ?? 0);
            $origin = (string)($item['origen'] ?? 'menu');
            if ($dishId <= 0 || $quantity <= 0) {
                throw new \InvalidArgumentException('Producto o cantidad inválida.');
            }

            if ($origin === 'store') {
                $storeProduct = Database::queryOne(
                    'SELECT precio FROM store_productos WHERE id = :id AND activo = 1 LIMIT 1',
                    [':id' => $dishId]
                );
                if (!$storeProduct) throw new \RuntimeException('Producto de tienda no disponible.');
                $item['precio_unit'] = round((float)$storeProduct['precio'], 2);
                $item['modificadores'] = [];
                $subtotal += $item['precio_unit'] * $quantity;
                $normalizedItems[] = $item;
                continue;
            }

            $dish = Database::queryOne(
                'SELECT id, nombre, precio, disponible
                   FROM rest_platillos
                  WHERE id = :id AND restaurante_id = :restaurant_id AND activo = 1 LIMIT 1',
                [':id' => $dishId, ':restaurant_id' => $restaurantId]
            );
            if (!$dish || !(bool)$dish['disponible']) {
                throw new \RuntimeException('El platillo solicitado no está disponible.');
            }

            $selection = self::flattenModifierSelection((array)($item['modificadores'] ?? []));
            $snapshots = [];
            $extrasTotal = 0.0;

            $catalogRows = DishModifier::getByDish($restaurantId, $dishId);

            $catalog = [];
            foreach ($catalogRows as $row) $catalog[(int)$row['id']] = $row;

            foreach ($selection as $modifierId => $modifierQuantity) {
                if (!isset($catalog[$modifierId])) {
                    throw new \InvalidArgumentException('Un modificador no pertenece al platillo.');
                }
                $modifier = $catalog[$modifierId];
                $type = (string)$modifier['tipo'];
                if ($type === 'exclusion' && !($config['exclusiones_habilitadas'] ?? true)) {
                    throw new \InvalidArgumentException('Las exclusiones están deshabilitadas en esta sucursal.');
                }
                if ($type === 'extra' && !($config['extras_habilitados'] ?? true)) {
                    throw new \InvalidArgumentException('Los extras están deshabilitados en esta sucursal.');
                }
                $max = $type === 'exclusion' ? 1 : max(1, (int)$modifier['max_cantidad']);
                if ($modifierQuantity > $max) {
                    throw new \InvalidArgumentException('La cantidad de un modificador excede el máximo permitido.');
                }
                $price = $type === 'exclusion' ? 0.0 : round((float)$modifier['precio_unitario'], 2);
                $modifierSubtotal = round($price * $modifierQuantity, 2);
                $extrasTotal += $modifierSubtotal;
                $snapshots[] = [
                    'modificador_id' => $modifierId,
                    'tipo' => $type,
                    'nombre' => (string)$modifier['nombre'],
                    'ingrediente_id' => $modifier['ingrediente_id'] !== null ? (int)$modifier['ingrediente_id'] : null,
                    'cantidad' => $modifierQuantity,
                    'cantidad_unidad' => (float)$modifier['cantidad_unidad'],
                    'unidad' => $modifier['unidad'],
                    'precio_unitario' => $price,
                    'subtotal' => $modifierSubtotal,
                ];
            }
            $item['modificadores'] = $snapshots;

            $item['platillo_id'] = $dishId;
            $item['cantidad'] = $quantity;
            $item['precio_unit'] = round((float)$dish['precio'] + $extrasTotal, 2);
            $subtotal += $item['precio_unit'] * $quantity;
            $normalizedItems[] = $item;
        }

        $data['items'] = $normalizedItems;
        $data['subtotal'] = round($subtotal, 2);
        $data['total'] = round($subtotal, 2);
        return $data;
    }

    public static function quote(array $data): array
    {
        return self::normalizeAndPriceOrder($data);
    }

    /**
     * Normaliza el método de pago al valor esperado por esquemas legacy.
     */
    private static function normalizePaymentMethod(string $metodo): string
    {
        return match ($metodo) {
            'card', 'apple_pay', 'google_pay' => 'tarjeta',
            'cash' => 'efectivo',
            'amare_wallet' => 'amare_wallet',
            default => $metodo,
        };
    }

    /**
     * Verifica si existen TRIGGERs en MySQL que descuenten stock automáticamente.
     * Si existen, no debemos descontar manualmente en PHP para evitar doble descuento.
     * @return bool true si hay triggers de stock en la BD
     */
    private static function hasStockTriggers(): bool
    {
        try {
            $pdo = Database::getInstance();
            // Buscar triggers relacionados con stock en rest_pedido_items (INSERT)
            $triggers = $pdo->query(
                "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
                 WHERE EVENT_OBJECT_TABLE IN ('rest_pedido_items', 'store_pedido_items')
                 AND ACTION_TIMING = 'AFTER'
                 AND EVENT_MANIPULATION = 'INSERT'"
            )->fetchAll(PDO::FETCH_COLUMN);

            return !empty($triggers);
        } catch (\PDOException $e) {
            error_log('Order::hasStockTriggers ERROR: ' . $e->getMessage());
            return false;
        }
    }

    private static function isConsumableEatInOrder(array $order): bool
    {
        return ($order['tipo_pedido'] ?? null) === 'eat_in' && self::columnExists('rest_pedidos', 'consumo_id');
    }

    private static function resolveOpenConsumptionId(int $restaurantId, int $userId, ?int $mesaId, bool $byTableOnly = false): string
    {
        if ($mesaId !== null && self::columnExists('rest_pedidos', 'cuenta_abierta')) {
            $sql = "SELECT consumo_id
                      FROM rest_pedidos
                     WHERE restaurante_id = :restaurant_id
                       AND mesa_id = :mesa_id
                       AND tipo_pedido = 'eat_in'
                       AND consumo_id IS NOT NULL
                       AND consumo_id <> ''
                       AND cuenta_abierta = 1";
            $params = [
                ':restaurant_id' => $restaurantId,
                ':mesa_id' => $mesaId,
            ];

            if (!$byTableOnly) {
                $sql .= ' AND mobile_usuario_id = :user_id';
                $params[':user_id'] = $userId;
            }

            if (self::columnExists('rest_pedidos', 'salida_qr_generado_at')) {
                $sql .= ' AND salida_qr_generado_at IS NULL';
            }
            if (self::columnExists('rest_pedidos', 'salida_validado_at')) {
                $sql .= ' AND salida_validado_at IS NULL';
            }

            $sql .= ' ORDER BY created_at DESC, id DESC LIMIT 1';
            $existing = Database::queryOne($sql, $params);
            if (!empty($existing['consumo_id'])) {
                return (string)$existing['consumo_id'];
            }
        }

        return 'CON-' . date('Ymd') . '-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<int, array<string, mixed>>
     */
    private static function groupConsumptionOrders(array $orders): array
    {
        $grouped = [];
        $regular = [];

        foreach ($orders as $order) {
            if (self::isConsumableEatInOrder($order) && !empty($order['consumo_id'])) {
                $grouped[(string)$order['consumo_id']][] = $order;
                continue;
            }

            $order['items'] = self::getOrderItems((int)$order['id']);
            $regular[] = $order;
        }

        foreach ($grouped as $consumptionOrders) {
            $regular[] = self::buildConsumptionOrder($consumptionOrders);
        }

        usort($regular, static function (array $a, array $b): int {
            return strcmp((string)($b['created_at'] ?? ''), (string)($a['created_at'] ?? ''));
        });

        return $regular;
    }

    /**
     * @param array<int, array<string, mixed>> $orders
     * @return array<string, mixed>
     */
    private static function buildConsumptionOrder(array $orders): array
    {
        usort($orders, static fn(array $a, array $b): int => (int)$a['id'] <=> (int)$b['id']);

        $anchor = $orders[0];
        $latest = $orders[count($orders) - 1];
        $items = [];
        $subtotal = 0.0;
        $total = 0.0;
        $isOpen = false;
        $validatedAt = null;
        $generatedAt = null;

        foreach ($orders as $order) {
            $subtotal += (float)($order['subtotal'] ?? 0);
            $total += (float)($order['total'] ?? 0);
            $isOpen = $isOpen || (int)($order['cuenta_abierta'] ?? 0) === 1;
            $generatedAt = $generatedAt ?? ($order['salida_qr_generado_at'] ?? null);
            $validatedAt = $validatedAt ?? ($order['salida_validado_at'] ?? null);

            foreach (self::getOrderItems((int)$order['id']) as $item) {
                $item['pedido_id'] = (int)$order['id'];
                $item['pedido_folio'] = $order['folio'] ?? null;
                $items[] = $item;
            }
        }

        $anchor['id'] = (int)$anchor['id'];
        $anchor['folio'] = $anchor['folio'] ?? ('Cuenta #' . $anchor['id']);
        $anchor['subtotal'] = $subtotal;
        $anchor['total'] = $total;
        $anchor['items'] = $items;
        $anchor['estado'] = $validatedAt ? 'entregado' : ($generatedAt ? 'listo' : 'pendiente');
        $anchor['created_at'] = $latest['created_at'] ?? $anchor['created_at'];
        $anchor['cuenta_abierta'] = $isOpen ? 1 : 0;
        $anchor['salida_qr_generado_at'] = $generatedAt;
        $anchor['salida_validado_at'] = $validatedAt;
        $anchor['pedidos_count'] = count($orders);
        $anchor['es_consumo'] = true;
        $anchor['consumo_id'] = $anchor['consumo_id'] ?? null;
        $anchor['mesa_id'] = $anchor['mesa_id'] ?? null;

        return $anchor;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function getConsumptionOrdersForOrder(array $order, ?int $userId = null, bool $fallbackToAnchor = true): array
    {
        if (empty($order['consumo_id'])) {
            return [$order];
        }

        $sql = "SELECT p.*, r.nombre AS restaurante_nombre
                  FROM rest_pedidos p
                  JOIN rest_restaurantes r ON r.id = p.restaurante_id
                 WHERE p.consumo_id = :consumo_id
                   AND p.tipo_pedido = 'eat_in'";
        $params = [':consumo_id' => $order['consumo_id']];

        if ($userId !== null) {
            $sql .= ' AND p.mobile_usuario_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $sql .= ' ORDER BY p.id ASC';
        $orders = Database::query($sql, $params);

        return empty($orders) && $fallbackToAnchor ? [$order] : $orders;
    }

    /**
     * @return array<int>
     */
    private static function getPaymentTargetOrderIds(int $orderId): array
    {
        if (!self::columnExists('rest_pedidos', 'consumo_id')) {
            return [$orderId];
        }

        $order = Database::queryOne('SELECT id, tipo_pedido, consumo_id FROM rest_pedidos WHERE id = :id', [':id' => $orderId]);
        if (!$order || ($order['tipo_pedido'] ?? null) !== 'eat_in' || empty($order['consumo_id'])) {
            return [$orderId];
        }

        $rows = Database::query(
            "SELECT id FROM rest_pedidos WHERE consumo_id = :consumo_id AND tipo_pedido = 'eat_in'",
            [':consumo_id' => $order['consumo_id']]
        );
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);

        return empty($ids) ? [$orderId] : $ids;
    }

    private static function rawOrderById(int $orderId, ?int $userId = null): ?array
    {
        $sql = 'SELECT * FROM rest_pedidos WHERE id = :id';
        $params = [':id' => $orderId];

        if ($userId !== null) {
            $sql .= ' AND mobile_usuario_id = :user_id';
            $params[':user_id'] = $userId;
        }

        return Database::queryOne($sql, $params);
    }

    private static function resolveUserOrderForConsumption(int $orderId, ?int $userId = null): ?array
    {
        $order = self::rawOrderById($orderId, $userId);
        if ($order) {
            return $order;
        }

        if ($userId === null || !self::columnExists('rest_pedidos', 'consumo_id')) {
            return null;
        }

        $anchor = self::rawOrderById($orderId);
        if (!$anchor || ($anchor['tipo_pedido'] ?? null) !== 'eat_in' || empty($anchor['consumo_id'])) {
            return null;
        }

        return Database::queryOne(
            "SELECT *
               FROM rest_pedidos
              WHERE consumo_id = :consumo_id
                AND tipo_pedido = 'eat_in'
                AND mobile_usuario_id = :user_id
              ORDER BY id ASC
              LIMIT 1",
            [
                ':consumo_id' => $anchor['consumo_id'],
                ':user_id' => $userId,
            ]
        );
    }

    /**
     * @param array<int> $orderIds
     */
    private static function getExitTokenOrderForIds(array $orderIds): ?array
    {
        if (empty($orderIds) || !self::columnExists('rest_pedidos', 'salida_token')) {
            return null;
        }

        $params = [];
        $placeholders = [];
        foreach (array_values(array_unique($orderIds)) as $index => $orderId) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $orderId;
        }

        return Database::queryOne(
            'SELECT *
               FROM rest_pedidos
              WHERE id IN (' . implode(', ', $placeholders) . ')
                AND salida_token IS NOT NULL
                AND salida_token <> \'\'
              ORDER BY id ASC
              LIMIT 1',
            $params
        );
    }

    public static function getByUser(int $userId, ?string $tipo = null): array
    {
        $hasTipoOrigen = self::columnExists('rest_pedidos', 'tipo_origen');
        $hasConsumoId = self::columnExists('rest_pedidos', 'consumo_id');

        $extraFields = [];
        foreach (['cuenta_abierta', 'mesa_id', 'salida_qr_generado_at', 'salida_validado_at', 'consumo_id'] as $column) {
            if (self::columnExists('rest_pedidos', $column)) {
                $extraFields[] = "p.{$column}";
            }
        }

        $selectFields = $hasTipoOrigen
            ? "p.id, p.restaurante_id, p.folio, p.estado, p.subtotal, p.total, p.tipo_pedido, p.tipo_origen, p.created_at, r.nombre AS restaurante_nombre"
            : "p.id, p.restaurante_id, p.folio, p.estado, p.subtotal, p.total, p.tipo_pedido, p.created_at, r.nombre AS restaurante_nombre";

        if (!empty($extraFields)) {
            $selectFields .= ', ' . implode(', ', $extraFields);
        }

        $sql = "SELECT {$selectFields}
                FROM rest_pedidos p
                JOIN rest_restaurantes r ON r.id = p.restaurante_id
                WHERE p.mobile_usuario_id = :usuario_id";
        
        $params = [':usuario_id' => $userId];
        
        if ($tipo !== null && $hasTipoOrigen && in_array($tipo, ['menu', 'store'])) {
            $sql .= " AND p.tipo_origen = :tipo_origen";
            $params[':tipo_origen'] = $tipo;
        }
        
        $sql .= " ORDER BY p.created_at DESC";
        
        $orders = Database::query($sql, $params);
        
        if ($hasConsumoId) {
            return self::groupConsumptionOrders($orders);
        }

        foreach ($orders as &$order) {
            $order['items'] = self::getOrderItems((int)$order['id']);
        }

        return $orders;
    }

    /**
     * Actualiza el método de pago de un pedido.
     */
    public static function updatePaymentMethod(int $orderId, string $metodo, ?string $paymentIntentId = null): bool
    {
        $pdo = Database::getInstance();
        $targetOrderIds = self::getPaymentTargetOrderIds($orderId);

        $fields = ["estado = :estado"];
        $params = [
            ':estado' => $metodo === 'cash' ? 'pendiente' : 'en_preparacion',
        ];

        if (self::columnExists('rest_pedidos', 'metodo_pago')) {
            $fields[] = "metodo_pago = :metodo";
            $params[':metodo'] = self::normalizePaymentMethod($metodo);
        } elseif (self::columnExists('rest_pedidos', 'payment_method')) {
            $fields[] = "payment_method = :metodo";
            $params[':metodo'] = self::normalizePaymentMethod($metodo);
        }

        if ($paymentIntentId !== null) {
            if (self::columnExists('rest_pedidos', 'payment_intent_id')) {
                $fields[] = "payment_intent_id = :payment_intent_id";
                $params[':payment_intent_id'] = $paymentIntentId;
            } elseif (self::columnExists('rest_pedidos', 'stripe_payment_intent_id')) {
                $fields[] = "stripe_payment_intent_id = :payment_intent_id";
                $params[':payment_intent_id'] = $paymentIntentId;
            }
        }

        $placeholders = [];
        foreach ($targetOrderIds as $index => $targetId) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $targetId;
        }

        $sql = "UPDATE rest_pedidos SET " . implode(', ', $fields) . " WHERE id IN (" . implode(', ', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public static function applyRewardsPayment(int $orderId, array $reward): bool
    {
        $pdo = Database::getInstance();
        $targetOrderIds = self::getPaymentTargetOrderIds($orderId);

        $fields = ['estado = :estado'];
        $params = [':estado' => 'en_preparacion'];

        if (self::columnExists('rest_pedidos', 'metodo_pago')) {
            $fields[] = 'metodo_pago = :metodo';
            $params[':metodo'] = 'amare_wallet';
        } elseif (self::columnExists('rest_pedidos', 'payment_method')) {
            $fields[] = 'payment_method = :metodo';
            $params[':metodo'] = 'amare_wallet';
        }

        $rewardColumns = [
            'amare_wallet_used_mxn' => 'wallet_total',
            'amare_discount_mxn' => 'discount_amount',
            'amare_points_redeemed' => 'points_redeemed',
            'amare_points_earned' => 'points_earned',
        ];
        foreach ($rewardColumns as $column => $key) {
            if (self::columnExists('rest_pedidos', $column)) {
                $fields[] = "{$column} = :{$column}";
                $params[":{$column}"] = $reward[$key] ?? 0;
            }
        }

        if (count($targetOrderIds) === 1) {
            if (self::columnExists('rest_pedidos', 'descuento')) {
                $fields[] = 'descuento = :descuento';
                $params[':descuento'] = (float)($reward['discount_amount'] ?? 0) + (float)($reward['points_discount'] ?? 0);
            }
            if (self::columnExists('rest_pedidos', 'total')) {
                $fields[] = 'total = :total';
                $params[':total'] = (float)($reward['wallet_total'] ?? 0);
            }
        }

        if (self::columnExists('rest_pedidos', 'pagado_at')) {
            $fields[] = 'pagado_at = NOW()';
        }
        if (self::columnExists('rest_pedidos', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        $placeholders = [];
        foreach ($targetOrderIds as $index => $targetId) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $targetId;
        }

        $sql = 'UPDATE rest_pedidos SET ' . implode(', ', $fields) . ' WHERE id IN (' . implode(', ', $placeholders) . ')';
        return $pdo->prepare($sql)->execute($params);
    }

    public static function applyExternalRewardsSummary(int $orderId, array $reward, bool $markPaid = true): bool
    {
        $pdo = Database::getInstance();
        $targetOrderIds = self::getPaymentTargetOrderIds($orderId);
        $originalTotal = round((float)($reward['original_total'] ?? 0), 2);
        $pointsDiscount = round((float)($reward['points_discount'] ?? 0), 2);
        $externalTotal = round(max(0, $originalTotal - $pointsDiscount), 2);
        $reward['discount_amount'] = 0;
        $reward['wallet_total'] = $externalTotal;

        $fields = [];
        $params = [];

        $rewardValues = [
            'amare_wallet_used_mxn' => 0,
            'amare_discount_mxn' => 0,
            'amare_points_redeemed' => $reward['points_redeemed'] ?? 0,
            'amare_points_earned' => $reward['points_earned'] ?? 0,
        ];

        foreach ($rewardValues as $column => $value) {
            if (self::columnExists('rest_pedidos', $column)) {
                $fields[] = "{$column} = :{$column}";
                $params[":{$column}"] = $value;
            }
        }

        if (count($targetOrderIds) === 1) {
            if (self::columnExists('rest_pedidos', 'descuento')) {
                $fields[] = 'descuento = :descuento';
                $params[':descuento'] = (float)($reward['discount_amount'] ?? 0) + (float)($reward['points_discount'] ?? 0);
            }
            if (self::columnExists('rest_pedidos', 'total')) {
                $fields[] = 'total = :total';
                $params[':total'] = (float)($reward['wallet_total'] ?? 0);
            }
        }

        if ($markPaid && self::columnExists('rest_pedidos', 'pagado_at')) {
            $fields[] = 'pagado_at = NOW()';
        }
        if (self::columnExists('rest_pedidos', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (empty($fields)) {
            return true;
        }

        $placeholders = [];
        foreach ($targetOrderIds as $index => $targetId) {
            $key = ':id_' . $index;
            $placeholders[] = $key;
            $params[$key] = $targetId;
        }

        $sql = 'UPDATE rest_pedidos SET ' . implode(', ', $fields) . ' WHERE id IN (' . implode(', ', $placeholders) . ')';
        return $pdo->prepare($sql)->execute($params);
    }

    public static function findById(int $id, ?int $userId = null): ?array
    {
        $sql = "SELECT p.*, r.nombre AS restaurante_nombre
                FROM rest_pedidos p
                JOIN rest_restaurantes r ON r.id = p.restaurante_id
                WHERE p.id = :id";
        
        $params = [':id' => $id];
        
        if ($userId !== null) {
            $sql .= " AND p.mobile_usuario_id = :usuario_id";
            $params[':usuario_id'] = $userId;
        }
        
        $order = Database::queryOne($sql, $params);

        if (!$order && $userId !== null && self::columnExists('rest_pedidos', 'consumo_id')) {
            $anchor = Database::queryOne(
                "SELECT p.*, r.nombre AS restaurante_nombre
                   FROM rest_pedidos p
                   JOIN rest_restaurantes r ON r.id = p.restaurante_id
                  WHERE p.id = :id",
                [':id' => $id]
            );

            if ($anchor && self::isConsumableEatInOrder($anchor)) {
                $consumptionOrders = self::getConsumptionOrdersForOrder($anchor, $userId, false);
                if (!empty($consumptionOrders)) {
                    return self::buildConsumptionOrder($consumptionOrders);
                }
            }
        }

        if ($order && self::isConsumableEatInOrder($order)) {
            $consumptionOrders = self::getConsumptionOrdersForOrder($order, $userId);
            if (count($consumptionOrders) > 1 || !empty($order['consumo_id'])) {
                return self::buildConsumptionOrder($consumptionOrders);
            }
        }

        if ($order) {
            $order['items'] = self::getOrderItems((int)$order['id']);
        }
        
        return $order;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

            $data = self::normalizeAndPriceOrder($data);

            $isEatIn = in_array(($data['order_type'] ?? null), ['eat_in', 'dine_in'], true);
            $tableId = !empty($data['mesa_id']) ? (int)$data['mesa_id'] : 0;
            if ($isEatIn && $tableId > 0 && self::tableExists('rest_cuenta_divisiones')) {
                $splitStmt = $pdo->prepare(
                    "SELECT id FROM rest_cuenta_divisiones
                      WHERE restaurante_id = :restaurant_id AND mesa_id = :table_id AND estado = 'activa'
                      LIMIT 1 FOR UPDATE"
                );
                $splitStmt->execute([
                    ':restaurant_id' => (int)$data['restaurante_id'],
                    ':table_id' => $tableId,
                ]);
                if ($splitStmt->fetch()) {
                    throw new \RuntimeException('No se pueden agregar productos mientras se cobran cuentas separadas.');
                }
            }

            $folio = 'AM-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 10);

            // Detectar tipo_origen del pedido según los items
            $tipoOrigen = 'menu';
            if (!empty($data['items'])) {
                $allStore = true;
                $allMenu = true;
                foreach ($data['items'] as $it) {
                    $orig = $it['origen'] ?? 'menu';
                    if ($orig !== 'store') $allStore = false;
                    if ($orig !== 'menu') $allMenu = false;
                }
                if ($allStore && !$allMenu) $tipoOrigen = 'store';
                elseif ($allMenu && !$allStore) $tipoOrigen = 'menu';
                else $tipoOrigen = 'mixto';
            }

            // INSERT dinámico en rest_pedidos (tolera columnas faltantes)
            $pedidoData = [
                'restaurante_id' => $data['restaurante_id'],
                'mobile_usuario_id' => $data['user_id'],
                'folio' => $folio,
                'estado' => $data['estado'] ?? 'pendiente',
                'subtotal' => $data['subtotal'],
                'total' => $data['total'],
                'tipo_pedido' => $data['order_type'],
                'pedido_origen' => $data['pedido_origen'] ?? 'cliente',
                'mesero_usuario_id' => $data['mesero_usuario_id'] ?? null,
                'mesero_nombre' => $data['mesero_nombre'] ?? null,
                'cliente_nombre' => $data['cliente_nombre'] ?? null,
                'notas' => $data['notes'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            // tipo_origen solo si la columna existe (migración 012)
            if (self::columnExists('rest_pedidos', 'tipo_origen')) {
                $pedidoData['tipo_origen'] = $tipoOrigen;
            }

            // Campos opcionales
            if (!empty($data['direccion_id'])) {
                $pedidoData['direccion_id'] = $data['direccion_id'];
            }
            if (!empty($data['direccion_entrega'])) {
                $pedidoData['direccion_entrega'] = $data['direccion_entrega'];
            }
            if (!empty($data['payment_intent_id'])) {
                $pedidoData['payment_intent_id'] = $data['payment_intent_id'];
            }
            if (($data['order_type'] ?? null) === 'eat_in') {
                $pedidoData['cuenta_abierta'] = 1;
            }
            if (!empty($data['mesa_id'])) {
                $pedidoData['mesa_id'] = (int)$data['mesa_id'];
            }
            if (($data['order_type'] ?? null) === 'eat_in' && self::columnExists('rest_pedidos', 'consumo_id')) {
                $pedidoData['consumo_id'] = self::resolveOpenConsumptionId(
                    (int)$data['restaurante_id'],
                    (int)$data['user_id'],
                    !empty($data['mesa_id']) ? (int)$data['mesa_id'] : null,
                    (bool)($data['consumo_por_mesa'] ?? false)
                );
            }

            $insertResult = self::buildInsert('rest_pedidos', $pedidoData);
            $orderId = Database::execute($insertResult['sql'], $insertResult['params']);

            if (!$orderId) {
                $pdo->rollBack();
                return 0;
            }

            if (($data['order_type'] ?? null) === 'eat_in' && !empty($data['mesa_id'])) {
                self::setTableOccupied((int)$data['mesa_id'], true);
            }

            if (empty($data['items']) || !is_array($data['items'])) {
                $pdo->rollBack();
                return 0;
            }

            // Arrays para acumular items por tipo (para descuento de stock)
            $storeItems = [];
            $menuItems = [];

            // Detectar columnas disponibles en rest_pedido_items
            $itemColumns = self::getTableColumns('rest_pedido_items');
            $hasExtrasJson = in_array('extras_json', $itemColumns, true);
            $hasOrigen = in_array('origen', $itemColumns, true);

            foreach ($data['items'] as $item) {
                $platilloId = $item['platillo_id'] ?? $item['product_id'] ?? null;
                $cantidad   = $item['cantidad'] ?? $item['quantity'] ?? null;
                $precio     = $item['precio_unit'] ?? $item['unit_price'] ?? null;

                if (!$platilloId || !$cantidad || !$precio) {
                    continue;
                }

                $origen = $item['origen'] ?? 'menu';

                $itemNotas = $item['notas'] ?? $item['options'] ?? '';
                $modificadores = $item['modificadores'] ?? [];

                $notasLegacy = json_encode([
                    'notas' => $itemNotas,
                    'extras' => $modificadores
                ], JSON_UNESCAPED_UNICODE);

                // INSERT dinámico para items (tolera extras_json / origen faltantes)
                $itemData = [
                    'pedido_id' => $orderId,
                    'platillo_id' => $platilloId,
                    'cantidad' => $cantidad,
                    'precio_unit' => $precio,
                    'notas' => $notasLegacy,
                    'estado' => 'pendiente',
                ];

                if ($hasOrigen) {
                    $itemData['origen'] = $origen;
                }

                if ($hasExtrasJson) {
                    $extrasJson = !empty($modificadores) ? json_encode($modificadores, JSON_UNESCAPED_UNICODE) : null;
                    $itemData['extras_json'] = $extrasJson;
                }

                $itemInsert = self::buildInsert('rest_pedido_items', $itemData);
                $pedidoItemId = Database::execute($itemInsert['sql'], $itemInsert['params']);

                if ($pedidoItemId && self::tableExists('rest_pedido_item_modificadores')) {
                    foreach ($modificadores as $modifier) {
                        if (!is_array($modifier) || !isset($modifier['tipo'], $modifier['nombre'])) continue;
                        $snapshotInsert = self::buildInsert('rest_pedido_item_modificadores', [
                            'pedido_item_id' => $pedidoItemId,
                            'modificador_id' => $modifier['modificador_id'] ?? null,
                            'tipo' => $modifier['tipo'],
                            'nombre' => $modifier['nombre'],
                            'ingrediente_id' => $modifier['ingrediente_id'] ?? null,
                            'cantidad' => $modifier['cantidad'] ?? 1,
                            'cantidad_unidad' => $modifier['cantidad_unidad'] ?? 0,
                            'unidad' => $modifier['unidad'] ?? null,
                            'precio_unitario' => $modifier['precio_unitario'] ?? 0,
                            'precio_extra' => $modifier['precio_unitario'] ?? 0,
                            'subtotal' => $modifier['subtotal'] ?? 0,
                            'created_at' => date('Y-m-d H:i:s'),
                        ]);
                        Database::execute($snapshotInsert['sql'], $snapshotInsert['params']);
                    }
                }

                // Acumular para descuento de stock
                if ($origen === 'store') {
                    $storeItems[] = ['id' => (int)$platilloId, 'cantidad' => (int)$cantidad];
                } elseif ($origen === 'menu') {
                    $menuItems[] = [
                        'id' => (int)$platilloId,
                        'cantidad' => (int)$cantidad,
                        'modificadores' => $modificadores,
                    ];
                }
            }

            // ──── DESCUENTO DE STOCK ────────────────────────────────────────
            // Verificar si existen triggers de MySQL que ya descuentan stock
            // automáticamente al insertar en rest_pedido_items. Si existen,
            // saltamos el descuento manual para evitar el DOBLE descuento.
            $skipManualStock = self::hasStockTriggers();

            if (!$skipManualStock) {
                // Descontar stock de productos de tienda
                foreach ($storeItems as $si) {
                    $decremented = Database::rowCount(
                        "UPDATE store_productos
                        SET stock = stock - :cantidad_update
                        WHERE id = :id
                        AND stock >= :cantidad_check",
                        [
                            ':id' => $si['id'],
                            ':cantidad_update' => $si['cantidad'],
                            ':cantidad_check' => $si['cantidad']
                        ]
                    );

                    if ($decremented === 0) {
                        $producto = Database::queryOne(
                            "SELECT nombre, stock FROM store_productos WHERE id = :id",
                            [':id' => $si['id']]
                        );

                        $nombre = $producto['nombre'] ?? 'Producto #' . $si['id'];
                        $stockDisp = $producto['stock'] ?? 0;

                        throw new \RuntimeException(
                            "Stock insuficiente: \"{$nombre}\" " .
                            "(disponible: {$stockDisp}, solicitado: {$si['cantidad']})"
                        );
                    }
                }

                // Descontar stock de ingredientes de menú (recetas)
                foreach ($menuItems as $mi) {
                    $receta = Database::queryOne(
                        "SELECT id FROM rest_recetas WHERE platillo_id = :platillo_id LIMIT 1",
                        [':platillo_id' => $mi['id']]
                    );

                    if (!$receta) {
                        continue;
                    }

                    // Verificar si existe columna 'cantidad' en rest_receta_ingredientes (migración 014)
                    $hasCantidad = self::columnExists('rest_receta_ingredientes', 'cantidad');
                    $hasStock = self::columnExists('rest_ingredientes', 'stock');

                    if (!$hasCantidad || !$hasStock) {
                        // Sin migración 014, no hay stock de ingredientes que descontar
                        continue;
                    }

                    $ingredientes = Database::query(
                        "SELECT ri.ingrediente_id, i.nombre AS ingrediente_nombre,
                                ri.cantidad AS cantidad_receta, ri.unidad
                         FROM rest_receta_ingredientes ri
                         JOIN rest_ingredientes i ON i.id = ri.ingrediente_id
                         WHERE ri.receta_id = :receta_id AND ri.cantidad > 0",
                        [':receta_id' => $receta['id']]
                    );

                    if (empty($ingredientes)) {
                        continue;
                    }

                    foreach ($ingredientes as $ing) {
                        $excludedIngredientIds = [];
                        foreach (($mi['modificadores'] ?? []) as $modifier) {
                            if (($modifier['tipo'] ?? null) === 'exclusion' && !empty($modifier['ingrediente_id'])) {
                                $excludedIngredientIds[] = (int)$modifier['ingrediente_id'];
                            }
                        }
                        if (in_array((int)$ing['ingrediente_id'], $excludedIngredientIds, true)) continue;

                        $cantidadADescontar = (float)$ing['cantidad_receta'] * (int)$mi['cantidad'];

                        $decremented = Database::rowCount(
                            "UPDATE rest_ingredientes
                            SET stock = stock - :cantidad_update
                            WHERE id = :id
                            AND stock >= :cantidad_check",
                            [
                                ':id' => $ing['ingrediente_id'],
                                ':cantidad_update' => $cantidadADescontar,
                                ':cantidad_check' => $cantidadADescontar
                            ]
                        );

                        if ($decremented === 0) {
                            $stockDisp = Database::queryOne(
                                "SELECT stock FROM rest_ingredientes WHERE id = :id",
                                [':id' => $ing['ingrediente_id']]
                            );

                            $stockActual = $stockDisp['stock'] ?? 0;

                            throw new \RuntimeException(
                                "Stock insuficiente de ingrediente \"{$ing['ingrediente_nombre']}\" " .
                                "(disponible: {$stockActual}, necesario: {$cantidadADescontar})"
                            );
                        }
                    }

                }
            } else {
                error_log('Order::create: Saltando descuento manual de stock (triggers MySQL detectados)');
                foreach ($menuItems as $mi) {
                    foreach (($mi['modificadores'] ?? []) as $modifier) {
                        if (($modifier['tipo'] ?? null) !== 'exclusion' || empty($modifier['ingrediente_id'])) continue;
                        $recipeIngredient = Database::queryOne(
                            'SELECT ri.cantidad
                               FROM rest_receta_ingredientes ri
                               JOIN rest_recetas r ON r.id = ri.receta_id
                              WHERE r.platillo_id = :dish_id AND ri.ingrediente_id = :ingredient_id LIMIT 1',
                            [
                                ':dish_id' => (int)$mi['id'],
                                ':ingredient_id' => (int)$modifier['ingrediente_id'],
                            ]
                        );
                        $quantityToRestore = (float)($recipeIngredient['cantidad'] ?? 0) * (int)$mi['cantidad'];
                        if ($quantityToRestore > 0) {
                            Database::rowCount(
                                'UPDATE rest_ingredientes SET stock = stock + :quantity WHERE id = :id',
                                [':quantity' => $quantityToRestore, ':id' => (int)$modifier['ingrediente_id']]
                            );
                        }
                    }
                }
            }

            // Los triggers existentes solo conocen la receta base; los extras se descuentan aquí siempre.
            if (self::columnExists('rest_ingredientes', 'stock')) {
                foreach ($menuItems as $mi) {
                    foreach (($mi['modificadores'] ?? []) as $modifier) {
                        if (($modifier['tipo'] ?? null) !== 'extra' || empty($modifier['ingrediente_id'])) continue;
                        $extraQuantity = (float)($modifier['cantidad_unidad'] ?? 0)
                            * (int)($modifier['cantidad'] ?? 1)
                            * (int)$mi['cantidad'];
                        if ($extraQuantity <= 0) continue;
                        $decremented = Database::rowCount(
                            'UPDATE rest_ingredientes SET stock = stock - :quantity_update
                              WHERE id = :id AND stock >= :quantity_check',
                            [
                                ':id' => (int)$modifier['ingrediente_id'],
                                ':quantity_update' => $extraQuantity,
                                ':quantity_check' => $extraQuantity,
                            ]
                        );
                        if ($decremented === 0) {
                            throw new \RuntimeException('Stock insuficiente para el extra "' . $modifier['nombre'] . '".');
                        }
                    }
                }
            }
            // ──── FIN DESCUENTO DE STOCK ────────────────────────────────────

            $pdo->commit();
            return $orderId;

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Order::create ERROR: ' . $e->getMessage());

            throw $e;
        }
    }

    public static function ensureExitPass(int $orderId, ?int $userId = null): ?array
    {
        $order = self::resolveUserOrderForConsumption($orderId, $userId);
        if (!$order) {
            return null;
        }

        if (($order['tipo_pedido'] ?? null) !== 'eat_in') {
            return null;
        }

        $tokenColumn = self::columnExists('rest_pedidos', 'salida_token');
        if (!$tokenColumn) {
            return null;
        }

        $targetOrderIds = self::getPaymentTargetOrderIds((int)$order['id']);
        $tokenOrder = self::getExitTokenOrderForIds($targetOrderIds);
        $token = $tokenOrder['salida_token'] ?? ($order['salida_token'] ?? null);
        $tokenOrderId = (int)($tokenOrder['id'] ?? $order['id']);

        if (!$token) {
            $token = bin2hex(random_bytes(24));
        }

        $tokenFields = ['salida_token = :token'];
        $tokenParams = [
            ':token' => $token,
            ':token_order_id' => $tokenOrderId,
        ];

        if (self::columnExists('rest_pedidos', 'salida_qr_generado_at')) {
            $tokenFields[] = 'salida_qr_generado_at = COALESCE(salida_qr_generado_at, NOW())';
        }
        if (self::columnExists('rest_pedidos', 'updated_at')) {
            $tokenFields[] = 'updated_at = NOW()';
        }

        Database::rowCount(
            'UPDATE rest_pedidos SET ' . implode(', ', $tokenFields) . ' WHERE id = :token_order_id',
            $tokenParams
        );

        $closeFields = [];
        if (self::columnExists('rest_pedidos', 'cuenta_abierta')) {
            $closeFields[] = 'cuenta_abierta = 0';
        }
        if (self::columnExists('rest_pedidos', 'updated_at')) {
            $closeFields[] = 'updated_at = NOW()';
        }
        if (!empty($closeFields)) {
            $closeParams = [];
            $placeholders = [];
            foreach ($targetOrderIds as $index => $targetId) {
                $key = ':id_' . $index;
                $placeholders[] = $key;
                $closeParams[$key] = $targetId;
            }
            Database::rowCount(
                'UPDATE rest_pedidos SET ' . implode(', ', $closeFields) . ' WHERE id IN (' . implode(', ', $placeholders) . ')',
                $closeParams
            );
        }

        $updated = self::rawOrderById($tokenOrderId, $userId) ?? self::rawOrderById($tokenOrderId) ?? $order;
        if ((int)($updated['id'] ?? 0) !== $tokenOrderId) {
            $updated['id'] = $tokenOrderId;
        }
        $updated['salida_token'] = $token;
        return self::formatExitPass($updated, $token);
    }

    public static function getExitPass(int $orderId, int $userId): ?array
    {
        $order = self::resolveUserOrderForConsumption($orderId, $userId);
        if (!$order || empty($order['salida_token'])) {
            $targetOrderIds = self::getPaymentTargetOrderIds((int)($order['id'] ?? $orderId));
            $tokenOrder = self::getExitTokenOrderForIds($targetOrderIds);
            $resolvedOrder = $order;

            if (!$resolvedOrder && !empty($targetOrderIds)) {
                $resolvedOrder = self::resolveUserOrderForConsumption(min($targetOrderIds), $userId);
            }

            if (!$resolvedOrder || empty($tokenOrder['salida_token'])) {
                return null;
            }

            $order = $tokenOrder;
        }

        return self::formatExitPass($order, (string)$order['salida_token']);
    }

    public static function validateExitPass(string $payload, int $validatorUserId): ?array
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }

        $orderId = null;
        $token = $payload;
        $parts = explode('|', $payload);

        if (count($parts) >= 3 && $parts[0] === 'AMARE_EXIT') {
            $orderId = (int)$parts[1];
            $token = $parts[2];
        }

        $sql = "SELECT * FROM rest_pedidos WHERE salida_token = :token";
        $params = [':token' => $token];

        if ($orderId !== null && $orderId > 0) {
            $sql .= ' AND id = :id';
            $params[':id'] = $orderId;
        }

        $order = Database::queryOne($sql, $params);
        if (!$order || ($order['tipo_pedido'] ?? null) !== 'eat_in') {
            return null;
        }

        if (!empty($order['salida_validado_at'])) {
            return self::formatExitPass($order, (string)$order['salida_token']);
        }

        $targetOrderIds = self::getPaymentTargetOrderIds((int)$order['id']);
        $fields = [];
        $updateParams = [':validator_id' => $validatorUserId];

        if (self::columnExists('rest_pedidos', 'salida_validado_at')) {
            $fields[] = 'salida_validado_at = NOW()';
        }
        if (self::columnExists('rest_pedidos', 'salida_validado_por')) {
            $fields[] = 'salida_validado_por = :validator_id';
        }
        if (self::columnExists('rest_pedidos', 'cuenta_abierta')) {
            $fields[] = 'cuenta_abierta = 0';
        }
        if (self::columnExists('rest_pedidos', 'estado')) {
            $fields[] = "estado = 'entregado'";
        }
        if (self::columnExists('rest_pedidos', 'updated_at')) {
            $fields[] = 'updated_at = NOW()';
        }

        if (!empty($fields)) {
            $placeholders = [];
            foreach ($targetOrderIds as $index => $targetId) {
                $key = ':id_' . $index;
                $placeholders[] = $key;
                $updateParams[$key] = $targetId;
            }

            Database::rowCount(
                'UPDATE rest_pedidos SET ' . implode(', ', $fields) . ' WHERE id IN (' . implode(', ', $placeholders) . ')',
                $updateParams
            );
        }

        if (!empty($order['mesa_id'])) {
            self::setTableOccupied((int)$order['mesa_id'], false);
        }

        if (!empty($order['mobile_usuario_id'])) {
            self::clearDinerTableSession((int)$order['mobile_usuario_id']);
        }

        $updated = self::rawOrderById((int)$order['id']);
        return self::formatExitPass($updated ?? $order, (string)$order['salida_token']);
    }

    private static function formatExitPass(?array $order, string $token): ?array
    {
        if (!$order) {
            return null;
        }

        $payload = 'AMARE_EXIT|' . (int)$order['id'] . '|' . $token;

        return [
            'pedido_id' => (int)$order['id'],
            'folio' => $order['folio'] ?? null,
            'restaurante_id' => isset($order['restaurante_id']) ? (int)$order['restaurante_id'] : null,
            'mesa_id' => isset($order['mesa_id']) && $order['mesa_id'] !== null ? (int)$order['mesa_id'] : null,
            'payload' => $payload,
            'token' => $token,
            'generated_at' => $order['salida_qr_generado_at'] ?? null,
            'validated_at' => $order['salida_validado_at'] ?? null,
            'is_validated' => !empty($order['salida_validado_at']),
        ];
    }

    private static function setTableOccupied(int $mesaId, bool $occupied): void
    {
        if ($mesaId <= 0 || !self::tableExists('rest_mesas')) {
            return;
        }

        $columns = self::getTableColumns('rest_mesas');
        $fields = [];
        $params = [':id' => $mesaId];

        if (in_array('ocupada', $columns, true)) {
            $fields[] = 'ocupada = :ocupada';
            $params[':ocupada'] = $occupied ? 1 : 0;
        }
        if (in_array('disponible', $columns, true)) {
            $fields[] = 'disponible = :disponible';
            $params[':disponible'] = $occupied ? 0 : 1;
        }
        if (in_array('estado', $columns, true)) {
            $fields[] = 'estado = :estado';
            $params[':estado'] = $occupied ? 'ocupada' : 'libre';
        }
        if (in_array('updated_at', $columns, true)) {
            $fields[] = 'updated_at = NOW()';
        }

        if (empty($fields)) {
            return;
        }

        Database::rowCount(
            'UPDATE rest_mesas SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );
    }

    private static function clearDinerTableSession(int $userId): void
    {
        if ($userId <= 0 || !self::tableExists('mobile_usuarios')) {
            return;
        }

        $columns = self::getTableColumns('mobile_usuarios');
        $fields = [];
        $params = [':id' => $userId];

        if (in_array('is_social_active', $columns, true)) {
            $fields[] = 'is_social_active = 0';
        }
        if (in_array('current_restaurante_id', $columns, true)) {
            $fields[] = 'current_restaurante_id = NULL';
        }
        if (in_array('mesa', $columns, true)) {
            $fields[] = 'mesa = NULL';
        }
        if (in_array('social_updated_at', $columns, true)) {
            $fields[] = 'social_updated_at = NOW()';
        }
        if (in_array('updated_at', $columns, true)) {
            $fields[] = 'updated_at = NOW()';
        }

        if (empty($fields)) {
            return;
        }

        Database::rowCount(
            'UPDATE mobile_usuarios SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );
    }

    /**
     * Obtiene los items de un pedido con construcción dinámica del SELECT
     * para evitar comas huérfanas cuando columnas opcionales no existen.
     */
    private static function getOrderItems(int $orderId): array
    {
        $hasExtrasJson = self::columnExists('rest_pedido_items', 'extras_json');
        $hasOrigen = self::columnExists('rest_pedido_items', 'origen');

        // Construir lista de columnas dinámicamente para evitar comas huérfanas
        $fields = [
            'pi.id',
            'pi.platillo_id',
        ];

        if ($hasOrigen) {
            $fields[] = 'pi.origen';
        }

        // Usar CASE WHEN basado en origen para seleccionar la tabla correcta.
        // Esto evita que los IDs de store_productos colisionen con rest_platillos
        // y muestren el platillo equivocado (ej: comprar silla → muestra "tamales").
        if ($hasOrigen) {
            $fields = array_merge($fields, [
                "CASE WHEN pi.origen = 'store' THEN sp.nombre ELSE pl.nombre END AS platillo_nombre",
                "CASE WHEN pi.origen = 'store' THEN sp.imagen ELSE pl.imagen END AS platillo_imagen",
            ]);
        } else {
            // Fallback: si la columna origen no existe, COALESCE con prioridad a menú
            // (riesgo de colisión de IDs, pero es el comportamiento legacy)
            $fields = array_merge($fields, [
                'COALESCE(pl.nombre, sp.nombre) AS platillo_nombre',
                'COALESCE(pl.imagen, sp.imagen) AS platillo_imagen',
            ]);
        }

        $fields = array_merge($fields, [
            'pi.cantidad',
            'pi.precio_unit',
            'pi.notas',
        ]);

        if ($hasExtrasJson) {
            $fields[] = 'pi.extras_json';
        }

        $fields = array_merge($fields, [
            'pi.estado',
            '(pi.cantidad * pi.precio_unit) AS subtotal',
        ]);

        $sql = "SELECT " . implode(', ', $fields) . "
                FROM rest_pedido_items pi
                LEFT JOIN rest_platillos pl ON pl.id = pi.platillo_id
                LEFT JOIN store_productos sp ON sp.id = pi.platillo_id
                WHERE pi.pedido_id = :pedido_id";

        return Database::query($sql, [':pedido_id' => $orderId]);
    }
}
