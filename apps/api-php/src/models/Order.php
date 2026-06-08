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

    public static function getByUser(int $userId, ?string $tipo = null): array
    {
        $hasTipoOrigen = self::columnExists('rest_pedidos', 'tipo_origen');

        $selectFields = $hasTipoOrigen
            ? "p.id, p.folio, p.estado, p.subtotal, p.total, p.tipo_pedido, p.tipo_origen, p.created_at, r.nombre AS restaurante_nombre"
            : "p.id, p.folio, p.estado, p.subtotal, p.total, p.tipo_pedido, p.created_at, r.nombre AS restaurante_nombre";

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
        
        foreach ($orders as &$order) {
            $order['items'] = self::getOrderItems($order['id']);
        }
        
        return $orders;
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
        
        if ($order) {
            $order['items'] = self::getOrderItems($order['id']);
        }
        
        return $order;
    }

    public static function create(array $data): int
    {
        $pdo = Database::getInstance();

        try {
            $pdo->beginTransaction();

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

            $insertResult = self::buildInsert('rest_pedidos', $pedidoData);
            $orderId = Database::execute($insertResult['sql'], $insertResult['params']);

            if (!$orderId) {
                $pdo->rollBack();
                return 0;
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
                Database::execute($itemInsert['sql'], $itemInsert['params']);

                // Acumular para descuento de stock
                if ($origen === 'store') {
                    $storeItems[] = ['id' => (int)$platilloId, 'cantidad' => (int)$cantidad];
                } elseif ($origen === 'menu') {
                    $menuItems[] = ['id' => (int)$platilloId, 'cantidad' => (int)$cantidad];
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