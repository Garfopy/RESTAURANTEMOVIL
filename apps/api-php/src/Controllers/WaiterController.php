<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\Order;
use Amare\Api\Models\Product;
use Amare\Api\Models\User;

class WaiterController
{
    public function branches(): void
    {
        $user = $this->requireWaiter();
        $assignmentSource = $this->staffAssignmentSource();

        if ($assignmentSource === null) {
            Response::success(['branches' => []]);
        }

        $roleColumn = $assignmentSource === 'rest_staff' ? 'rol_slug' : 'rol_operativo';
        $branches = Database::query(
            "SELECT r.id, r.nombre, r.slug, r.descripcion, r.direccion, r.lat, r.lng,
                    r.logo, r.imagen_banner, r.telefono, r.color_primario, r.color_secundario,
                    r.horario_apertura, r.horario_cierre, r.horarios_json,
                    r.mesas_habilitadas, r.reservas_habilitadas, r.activo,
                    COALESCE(rc.tipos_entrega, '[\"delivery\",\"pickup\"]') AS tipos_entrega
               FROM {$assignmentSource} sr
               JOIN rest_restaurantes r ON r.id = sr.restaurante_id
          LEFT JOIN rest_configuracion rc ON rc.restaurante_id = r.id AND rc.activo = 1
              WHERE sr.usuario_id = :user_id
                AND sr.{$roleColumn} = 'mesero'
                AND sr.activo = 1
                AND r.activo = 1
           ORDER BY r.nombre ASC",
            [':user_id' => (int)$user['id']]
        );

        Response::success(['branches' => array_map([$this, 'normalizeBranch'], $branches)]);
    }

    public function tables(): void
    {
        $user = $this->requireWaiter();
        $restaurantId = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);

        if (!$this->tableExists('rest_mesas')) {
            Response::success(['tables' => []]);
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $activeColumn = $this->firstExistingColumn($columns, ['activo']);
        $stateColumn = $this->firstExistingColumn($columns, ['estado']);
        $orderColumn = $this->firstExistingColumn($columns, ['orden', 'numero_mesa', 'numero', 'mesa', 'nombre', 'id']);
        $zoneColumn = $this->firstExistingColumn($columns, ['zona_id', 'zone_id']);
        $zoneColumns = $this->tableExists('rest_zonas') ? $this->getTableColumns('rest_zonas') : [];
        $zoneIdColumn = $this->firstExistingColumn($zoneColumns, ['id']);
        $zoneLabelColumn = $this->firstExistingColumn($zoneColumns, ['nombre', 'nombre_zona', 'zona', 'name']);
        $zoneRestaurantColumn = $this->firstExistingColumn($zoneColumns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $canJoinZone = $zoneColumn !== null && $zoneIdColumn !== null && $zoneLabelColumn !== null;

        if ($idColumn === null || $labelColumn === null) {
            Response::success(['tables' => []]);
        }

        $fields = [
            "m.`{$idColumn}` AS id",
            "m.`{$labelColumn}` AS mesa_label",
        ];
        foreach (['mesero_usuario_id', 'mesero_nombre', 'cliente_nombre', 'reclamada_at'] as $column) {
            if (in_array($column, $columns, true)) {
                $fields[] = "m.`{$column}`";
            }
        }
        if ($stateColumn !== null) {
            $fields[] = "m.`{$stateColumn}` AS mesa_estado";
        }
        if ($zoneColumn !== null) {
            $fields[] = "m.`{$zoneColumn}` AS zona_id";
        }
        if ($canJoinZone) {
            $fields[] = "z.`{$zoneLabelColumn}` AS zona_nombre";
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM rest_mesas m';
        if ($canJoinZone) {
            $sql .= " LEFT JOIN rest_zonas z ON z.`{$zoneIdColumn}` = m.`{$zoneColumn}`";
            if ($zoneRestaurantColumn !== null && $restaurantColumn !== null) {
                $sql .= " AND z.`{$zoneRestaurantColumn}` = m.`{$restaurantColumn}`";
            }
        }
        $sql .= ' WHERE 1 = 1';
        $params = [];

        if ($restaurantColumn !== null) {
            $sql .= " AND m.`{$restaurantColumn}` = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }
        if ($activeColumn !== null) {
            $sql .= " AND m.`{$activeColumn}` = 1";
        }
        if ($orderColumn !== null) {
            $sql .= " ORDER BY m.`{$orderColumn}` ASC";
        }

        $rows = Database::query($sql, $params);
        $tables = [];

        foreach ($rows as $row) {
            $tableId = (int)$row['id'];
            $openAccount = $this->findOpenAccountForTable($restaurantId, $tableId);
            $claimedBy = isset($row['mesero_usuario_id']) && $row['mesero_usuario_id'] !== null
                ? (int)$row['mesero_usuario_id']
                : null;

            $status = 'libre';
            if ($openAccount !== null) {
                $status = $claimedBy === (int)$user['id'] || $claimedBy === null ? 'cuenta_abierta' : 'ocupada_por_otro';
            } elseif ($claimedBy === (int)$user['id']) {
                $status = 'mia';
            } elseif ($claimedBy !== null) {
                $status = 'ocupada_por_otro';
            }

            $tables[] = [
                'id' => $tableId,
                'label' => $this->formatMesaLabel((string)$row['mesa_label']),
                'value' => (string)$row['mesa_label'],
                'status' => $status,
                'estado' => $row['mesa_estado'] ?? null,
                'zona_id' => isset($row['zona_id']) && $row['zona_id'] !== null ? (int)$row['zona_id'] : null,
                'zona_nombre' => $row['zona_nombre'] ?? null,
                'mesero_usuario_id' => $claimedBy,
                'mesero_nombre' => $row['mesero_nombre'] ?? null,
                'cliente_nombre' => $row['cliente_nombre'] ?? ($openAccount['cliente_nombre'] ?? null),
                'reclamada_at' => $row['reclamada_at'] ?? null,
                'cuenta_abierta' => $openAccount !== null,
                'consumo_id' => $openAccount['consumo_id'] ?? null,
                'total' => $openAccount !== null ? (float)($openAccount['total'] ?? 0) : 0,
            ];
        }

        Response::success(['tables' => $tables]);
    }

    public function claimTable(int $tableId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;
        $customerName = trim((string)($input['cliente_nombre'] ?? $input['customer_name'] ?? ''));

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        if ($customerName === '') {
            Response::validationError(['cliente_nombre' => ['Ingresa el nombre del comensal']]);
        }

        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        $table = $this->findTable($restaurantId, $tableId);
        if ($table === null) {
            Response::notFound('Mesa no encontrada');
        }
        if ($this->getActiveSplit($restaurantId, $tableId) !== null) {
            Response::error('Esta mesa ya tiene cuentas separadas en proceso de cobro.', 409);
        }

        $claimedBy = isset($table['mesero_usuario_id']) && $table['mesero_usuario_id'] !== null
            ? (int)$table['mesero_usuario_id']
            : null;
        if ($claimedBy !== null && $claimedBy !== (int)$user['id']) {
            Response::error('Esta mesa ya fue reclamada por otro mesero.', 409);
        }

        $this->updateTableClaim($tableId, (int)$user['id'], (string)$user['nombre'], substr($customerName, 0, 120), true);
        Response::success(['table' => $this->buildTableResponse($restaurantId, $tableId, (int)$user['id'])], 'Mesa reclamada');
    }

    public function releaseTable(int $tableId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        $table = $this->findTable($restaurantId, $tableId);
        if ($table === null) {
            Response::notFound('Mesa no encontrada');
        }
        if ($this->getActiveSplit($restaurantId, $tableId) !== null) {
            Response::error('No puedes soltar una mesa mientras se cobran cuentas separadas.', 409);
        }

        $claimedBy = isset($table['mesero_usuario_id']) && $table['mesero_usuario_id'] !== null
            ? (int)$table['mesero_usuario_id']
            : null;
        if ($claimedBy !== (int)$user['id']) {
            Response::error('Solo el mesero asignado puede soltar esta mesa.', 403);
        }
        if ($this->findOpenAccountForTable($restaurantId, $tableId) !== null) {
            Response::error('No puedes soltar una mesa con cuenta abierta.', 409);
        }

        $this->updateTableClaim($tableId, null, null, null, false);
        Response::success(['ok' => true], 'Mesa liberada');
    }

    public function account(int $tableId): void
    {
        $user = $this->requireWaiter();
        $restaurantId = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }

        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        $table = $this->findTable($restaurantId, $tableId);
        if ($table === null) {
            Response::notFound('Mesa no encontrada');
        }

        Response::success(['account' => $this->buildAccount($restaurantId, $tableId, (int)$user['id'])]);
    }

    public function gifts(): void
    {
        $user = $this->requireWaiter();
        $restaurantId = (int)($_GET['restaurant_id'] ?? 0);
        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        if (!$this->tableExists('social_gift_orders')) {
            Response::success(['active' => [], 'history' => [], 'pending_count' => 0]);
        }

        $rows = Database::query(
            "SELECT g.id, g.folio, g.restaurante_id, g.mesa_id, g.gift_nombre, g.gift_descripcion,
                    g.gift_precio, g.gift_imagen, g.sender_nombre, g.recipient_nombre,
                    g.sender_mesa, g.recipient_mesa, g.status, g.reclamado_por, g.reclamado_at,
                    g.entregado_por, g.entregado_at, g.created_at, g.pagado_at,
                    claimant.nombre AS reclamado_por_nombre, deliverer.nombre AS entregado_por_nombre
               FROM social_gift_orders g
          LEFT JOIN mobile_usuarios claimant ON claimant.id = g.reclamado_por
          LEFT JOIN mobile_usuarios deliverer ON deliverer.id = g.entregado_por
              WHERE g.restaurante_id = :restaurant_id
                AND (g.status IN ('listo','reclamado')
                     OR (g.status = 'entregado' AND DATE(g.entregado_at) = CURDATE()))
           ORDER BY CASE g.status WHEN 'listo' THEN 0 WHEN 'reclamado' THEN 1 ELSE 2 END,
                    g.created_at ASC",
            [':restaurant_id' => $restaurantId]
        );
        $active = [];
        $history = [];
        foreach ($rows as $row) {
            $gift = $this->normalizeWaiterGift($row, (int)$user['id']);
            if ($row['status'] === 'entregado') {
                $history[] = $gift;
            } else {
                $active[] = $gift;
            }
        }
        Response::success([
            'active' => $active,
            'history' => $history,
            'pending_count' => count(array_filter($active, static fn(array $gift): bool => $gift['status'] === 'listo')),
        ]);
    }

    public function claimGift(int $giftId): void
    {
        $this->changeGiftStatus($giftId, 'claim');
    }

    public function releaseGift(int $giftId): void
    {
        $this->changeGiftStatus($giftId, 'release');
    }

    public function deliverGift(int $giftId): void
    {
        $this->changeGiftStatus($giftId, 'deliver');
    }

    private function changeGiftStatus(int $giftId, string $action): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        if (!$this->tableExists('social_gift_orders')) {
            Response::notFound('Regalo no encontrado');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                'SELECT * FROM social_gift_orders
                  WHERE id = :id AND restaurante_id = :restaurant_id LIMIT 1 FOR UPDATE'
            );
            $stmt->execute([':id' => $giftId, ':restaurant_id' => $restaurantId]);
            $gift = $stmt->fetch();
            if (!$gift) throw new \DomainException('Regalo no encontrado.');

            $userId = (int)$user['id'];
            $status = (string)$gift['status'];
            if ($action === 'claim') {
                if ($status === 'reclamado' && (int)$gift['reclamado_por'] === $userId) {
                    // Reintento idempotente.
                } elseif ($status === 'listo') {
                    $update = $pdo->prepare(
                        "UPDATE social_gift_orders
                            SET status = 'reclamado', reclamado_por = :user_id,
                                reclamado_at = NOW(), updated_at = NOW() WHERE id = :id"
                    );
                    $update->execute([':user_id' => $userId, ':id' => $giftId]);
                } elseif ($status === 'reclamado') {
                    throw new \DomainException('Otro mesero ya reclamo este regalo.');
                } else {
                    throw new \DomainException('Este regalo ya no se puede reclamar.');
                }
            } elseif ($action === 'release') {
                if ($status === 'listo') {
                    // Reintento idempotente.
                } elseif ($status === 'reclamado' && (int)$gift['reclamado_por'] === $userId) {
                    $update = $pdo->prepare(
                        "UPDATE social_gift_orders
                            SET status = 'listo', reclamado_por = NULL, reclamado_at = NULL,
                                updated_at = NOW() WHERE id = :id"
                    );
                    $update->execute([':id' => $giftId]);
                } elseif ($status === 'reclamado') {
                    throw new \DomainException('Solo el mesero responsable puede liberar este regalo.');
                } else {
                    throw new \DomainException('Este regalo ya no se puede liberar.');
                }
            } elseif ($action === 'deliver') {
                if ($status === 'entregado' && (int)$gift['entregado_por'] === $userId) {
                    // Reintento idempotente.
                } elseif ($status === 'reclamado' && (int)$gift['reclamado_por'] === $userId) {
                    $update = $pdo->prepare(
                        "UPDATE social_gift_orders
                            SET status = 'entregado', entregado_por = :user_id,
                                entregado_at = NOW(), updated_at = NOW() WHERE id = :id"
                    );
                    $update->execute([':user_id' => $userId, ':id' => $giftId]);
                } elseif ($status === 'reclamado') {
                    throw new \DomainException('Solo el mesero responsable puede entregar este regalo.');
                } else {
                    throw new \DomainException('Reclama el regalo antes de marcarlo como entregado.');
                }
            }

            $read = $pdo->prepare('SELECT * FROM social_gift_orders WHERE id = :id');
            $read->execute([':id' => $giftId]);
            $updated = $read->fetch();
            $pdo->commit();
            Response::success(['gift' => $this->normalizeWaiterGift($updated, $userId)], 'Regalo actualizado');
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('WaiterController::changeGiftStatus ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo actualizar el regalo.');
        }
    }

    private function normalizeWaiterGift(array $gift, int $currentUserId): array
    {
        $claimedBy = isset($gift['reclamado_por']) && $gift['reclamado_por'] !== null ? (int)$gift['reclamado_por'] : null;
        return [
            'id' => (int)$gift['id'],
            'folio' => $gift['folio'] ?? null,
            'restaurant_id' => (int)$gift['restaurante_id'],
            'table_id' => (int)$gift['mesa_id'],
            'gift_name' => $gift['gift_nombre'] ?? 'Regalo',
            'gift_description' => $gift['gift_descripcion'] ?? null,
            'gift_price' => (float)($gift['gift_precio'] ?? 0),
            'gift_image' => $gift['gift_imagen'] ?? null,
            'sender_name' => $gift['sender_nombre'] ?? 'Comensal',
            'recipient_name' => $gift['recipient_nombre'] ?? 'Comensal',
            'sender_table' => $gift['sender_mesa'] ?? null,
            'recipient_table' => $gift['recipient_mesa'] ?? null,
            'status' => $gift['status'],
            'claimed_by' => $claimedBy,
            'claimed_by_name' => $gift['reclamado_por_nombre'] ?? null,
            'claimed_by_me' => $claimedBy === $currentUserId,
            'claimed_at' => $gift['reclamado_at'] ?? null,
            'delivered_by' => isset($gift['entregado_por']) && $gift['entregado_por'] !== null ? (int)$gift['entregado_por'] : null,
            'delivered_by_name' => $gift['entregado_por_nombre'] ?? null,
            'delivered_at' => $gift['entregado_at'] ?? null,
            'created_at' => $gift['created_at'] ?? null,
            'paid_at' => $gift['pagado_at'] ?? null,
        ];
    }

    public function createOrder(int $tableId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;
        $customerName = trim((string)($input['cliente_nombre'] ?? $input['customer_name'] ?? ''));

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        if (empty($input['items']) || !is_array($input['items'])) {
            Response::validationError(['items' => ['Agrega al menos un producto']]);
        }

        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        $table = $this->findTable($restaurantId, $tableId);
        if ($table === null) {
            Response::notFound('Mesa no encontrada');
        }
        if ($this->getActiveSplit($restaurantId, $tableId) !== null) {
            Response::error('No puedes agregar productos mientras se cobran cuentas separadas.', 409);
        }

        $claimedBy = isset($table['mesero_usuario_id']) && $table['mesero_usuario_id'] !== null
            ? (int)$table['mesero_usuario_id']
            : null;
        $openAccount = $this->findOpenAccountForTable($restaurantId, $tableId);
        if ($claimedBy === null && $openAccount === null) {
            Response::error('Reclama esta mesa antes de agregar productos.', 403);
        }

        if ($customerName === '') {
            $customerName = (string)($table['cliente_nombre'] ?? 'Comensal');
        }

        $items = [];
        $subtotal = 0.0;
        foreach ($input['items'] as $item) {
            $platilloId = (int)($item['platillo_id'] ?? $item['product_id'] ?? 0);
            $cantidad = max(1, (int)($item['cantidad'] ?? $item['quantity'] ?? 1));
            $precio = (float)($item['precio_unit'] ?? $item['unit_price'] ?? 0);

            if ($platilloId <= 0 || $precio <= 0) {
                Response::validationError(['items' => ['Producto o precio invalido']]);
            }
            if (!Product::belongsToRestaurant($platilloId, $restaurantId)) {
                Response::validationError(['items' => ["El platillo {$platilloId} no pertenece a la sucursal seleccionada"]]);
            }

            $subtotal += $precio * $cantidad;
            $items[] = [
                'platillo_id' => $platilloId,
                'cantidad' => $cantidad,
                'precio_unit' => $precio,
                'notas' => $item['notas'] ?? null,
                'modificadores' => $item['modificadores'] ?? [],
                'origen' => 'menu',
            ];
        }

        try {
            $orderId = Order::create([
                'restaurante_id' => $restaurantId,
                'user_id' => (int)$user['id'],
                'order_type' => 'eat_in',
                'subtotal' => $subtotal,
                'total' => $subtotal,
                'notes' => 'Pedido de mesero - ' . $this->formatMesaLabel((string)($table['mesa_label'] ?? $tableId)),
                'mesa_id' => $tableId,
                'pedido_origen' => 'mesero',
                'mesero_usuario_id' => (int)$user['id'],
                'mesero_nombre' => (string)$user['nombre'],
                'cliente_nombre' => substr($customerName, 0, 120),
                'consumo_por_mesa' => true,
                'items' => $items,
            ]);
        } catch (\InvalidArgumentException $exception) {
            Response::validationError(['items' => [$exception->getMessage()]]);
        } catch (\RuntimeException $exception) {
            Response::error($exception->getMessage(), 409);
        }

        if ($orderId <= 0) {
            Response::serverError('No se pudo crear la comanda');
        }

        if ($claimedBy === null) {
            $this->updateTableClaim($tableId, (int)$user['id'], (string)$user['nombre'], substr($customerName, 0, 120), true);
        }

        Response::success(['account' => $this->buildAccount($restaurantId, $tableId, (int)$user['id'])], 'Comanda enviada', 201);
    }

    public function closeAccount(int $tableId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;
        $paymentMethod = $this->normalizeWaiterPaymentMethod((string)($input['metodo_pago'] ?? $input['payment_method'] ?? ''));

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        if ($paymentMethod === null) {
            Response::validationError(['metodo_pago' => ['Selecciona efectivo, tarjeta o transferencia']]);
        }

        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        $table = $this->findTable($restaurantId, $tableId);
        if ($table === null) {
            Response::notFound('Mesa no encontrada');
        }
        if ($this->getActiveSplit($restaurantId, $tableId) !== null) {
            Response::error('Esta mesa tiene cuentas separadas pendientes.', 409);
        }

        $orders = $this->getOpenOrdersForTable($restaurantId, $tableId);
        if (empty($orders)) {
            Response::error('Esta mesa no tiene cuenta abierta.', 409);
        }

        $total = array_reduce(
            $orders,
            static fn(float $sum, array $order): float => $sum + (float)($order['total'] ?? 0),
            0.0
        );
        $columns = $this->getTableColumns('rest_pedidos');
        $set = ["estado = 'entregado'"];
        $params = [
            ':restaurant_id' => $restaurantId,
            ':table_id' => $tableId,
        ];

        if (in_array('cuenta_abierta', $columns, true)) {
            $set[] = 'cuenta_abierta = 0';
        }
        if (in_array('metodo_pago', $columns, true)) {
            $set[] = 'metodo_pago = :payment_method';
            $params[':payment_method'] = $paymentMethod;
        }
        if (in_array('pagado_at', $columns, true)) {
            $set[] = 'pagado_at = NOW()';
        }
        if (in_array('cerrado_por_mesero_usuario_id', $columns, true)) {
            $set[] = 'cerrado_por_mesero_usuario_id = :waiter_id';
            $params[':waiter_id'] = (int)$user['id'];
        }
        if (in_array('cerrado_por_mesero_nombre', $columns, true)) {
            $set[] = 'cerrado_por_mesero_nombre = :waiter_name';
            $params[':waiter_name'] = (string)$user['nombre'];
        }
        if (in_array('cerrado_at', $columns, true)) {
            $set[] = 'cerrado_at = NOW()';
        }
        if (in_array('actualizado_at', $columns, true)) {
            $set[] = 'actualizado_at = NOW()';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE rest_pedidos
                   SET ' . implode(', ', $set) . '
                 WHERE restaurante_id = :restaurant_id
                   AND mesa_id = :table_id
                   AND tipo_pedido IN ("eat_in", "dine_in")';
        if (in_array('cuenta_abierta', $columns, true)) {
            $sql .= ' AND cuenta_abierta = 1';
        } else {
            $sql .= " AND estado NOT IN ('entregado', 'cancelado')";
        }

        Database::rowCount($sql, $params);
        $this->updateTableClaim($tableId, null, null, null, false);

        Response::success([
            'table_id' => $tableId,
            'restaurant_id' => $restaurantId,
            'metodo_pago' => $paymentMethod,
            'total' => $total,
            'orders_count' => count($orders),
            'closed' => true,
        ], 'Cuenta cerrada');
    }

    public function createSplit(int $tableId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $accountsInput = $input['accounts'] ?? null;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        if (!is_array($accountsInput) || count($accountsInput) < 2) {
            Response::validationError(['accounts' => ['Agrega al menos dos cuentas']]);
        }
        if (!$this->splitTablesExist()) {
            Response::error('Ejecuta la migracion 029 para usar cuentas separadas.', 500);
        }

        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);
        if ($this->findTable($restaurantId, $tableId) === null) {
            Response::notFound('Mesa no encontrada');
        }

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            $activeStmt = $pdo->prepare(
                "SELECT id FROM rest_cuenta_divisiones
                  WHERE restaurante_id = :restaurant_id AND mesa_id = :table_id AND estado = 'activa'
                  LIMIT 1 FOR UPDATE"
            );
            $activeStmt->execute([':restaurant_id' => $restaurantId, ':table_id' => $tableId]);
            if ($activeStmt->fetch()) {
                throw new \DomainException('Esta mesa ya tiene una division activa.');
            }

            $orders = $this->getOpenOrdersForTable($restaurantId, $tableId);
            if (empty($orders)) {
                throw new \DomainException('Esta mesa no tiene cuenta abierta.');
            }
            $orderIds = array_map(static fn(array $order): int => (int)$order['id'], $orders);
            $orderPlaceholders = implode(',', array_fill(0, count($orderIds), '?'));
            $lockStmt = $pdo->prepare("SELECT id FROM rest_pedidos WHERE id IN ({$orderPlaceholders}) FOR UPDATE");
            $lockStmt->execute($orderIds);

            $itemsStmt = $pdo->prepare(
                "SELECT id, cantidad, precio_unit FROM rest_pedido_items
                  WHERE pedido_id IN ({$orderPlaceholders}) ORDER BY id ASC"
            );
            $itemsStmt->execute($orderIds);
            $sourceItems = [];
            foreach ($itemsStmt->fetchAll() as $item) {
                $sourceItems[(int)$item['id']] = [
                    'cantidad' => (int)$item['cantidad'],
                    'precio_unit' => round((float)$item['precio_unit'], 2),
                ];
            }
            if (empty($sourceItems)) {
                throw new \DomainException('La cuenta no tiene productos para dividir.');
            }

            $normalizedAccounts = [];
            $assigned = [];
            foreach (array_values($accountsInput) as $index => $accountInput) {
                if (!is_array($accountInput) || empty($accountInput['items']) || !is_array($accountInput['items'])) {
                    throw new \InvalidArgumentException('Cada cuenta debe contener al menos un producto.');
                }
                $name = trim((string)($accountInput['name'] ?? $accountInput['nombre'] ?? ''));
                $name = $name !== '' ? substr($name, 0, 60) : 'Cuenta ' . ($index + 1);
                $accountItems = [];
                $accountTotalCents = 0;

                foreach ($accountInput['items'] as $itemInput) {
                    $itemId = (int)($itemInput['pedido_item_id'] ?? 0);
                    $quantity = (int)($itemInput['cantidad'] ?? 0);
                    if ($itemId <= 0 || $quantity <= 0 || !isset($sourceItems[$itemId])) {
                        throw new \InvalidArgumentException('El reparto contiene un producto o cantidad invalida.');
                    }
                    if (isset($accountItems[$itemId])) {
                        throw new \InvalidArgumentException('Un producto esta repetido dentro de la misma cuenta.');
                    }
                    $assigned[$itemId] = ($assigned[$itemId] ?? 0) + $quantity;
                    $unitCents = (int)round($sourceItems[$itemId]['precio_unit'] * 100);
                    $subtotalCents = $unitCents * $quantity;
                    $accountTotalCents += $subtotalCents;
                    $accountItems[$itemId] = [
                        'pedido_item_id' => $itemId,
                        'cantidad' => $quantity,
                        'precio_unit' => $unitCents / 100,
                        'subtotal' => $subtotalCents / 100,
                    ];
                }
                $normalizedAccounts[] = [
                    'numero' => $index + 1,
                    'nombre' => $name,
                    'total' => $accountTotalCents / 100,
                    'items' => array_values($accountItems),
                ];
            }

            foreach ($sourceItems as $itemId => $sourceItem) {
                if (($assigned[$itemId] ?? 0) !== $sourceItem['cantidad']) {
                    throw new \InvalidArgumentException('Todos los productos deben asignarse exactamente una vez.');
                }
            }
            if (count($assigned) !== count($sourceItems)) {
                throw new \InvalidArgumentException('El reparto contiene productos que no pertenecen a la cuenta.');
            }

            $splitStmt = $pdo->prepare(
                'INSERT INTO rest_cuenta_divisiones
                    (restaurante_id, mesa_id, estado, creado_por_usuario_id, creado_por_nombre)
                 VALUES (:restaurant_id, :table_id, \'activa\', :user_id, :user_name)'
            );
            $splitStmt->execute([
                ':restaurant_id' => $restaurantId,
                ':table_id' => $tableId,
                ':user_id' => (int)$user['id'],
                ':user_name' => (string)$user['nombre'],
            ]);
            $splitId = (int)$pdo->lastInsertId();

            $accountStmt = $pdo->prepare(
                'INSERT INTO rest_cuenta_division_cuentas (division_id, numero, nombre, total)
                 VALUES (:division_id, :numero, :nombre, :total)'
            );
            $itemStmt = $pdo->prepare(
                'INSERT INTO rest_cuenta_division_items
                    (cuenta_id, pedido_item_id, cantidad, precio_unit, subtotal)
                 VALUES (:cuenta_id, :item_id, :cantidad, :precio_unit, :subtotal)'
            );
            foreach ($normalizedAccounts as $account) {
                $accountStmt->execute([
                    ':division_id' => $splitId,
                    ':numero' => $account['numero'],
                    ':nombre' => $account['nombre'],
                    ':total' => $account['total'],
                ]);
                $accountId = (int)$pdo->lastInsertId();
                foreach ($account['items'] as $item) {
                    $itemStmt->execute([
                        ':cuenta_id' => $accountId,
                        ':item_id' => $item['pedido_item_id'],
                        ':cantidad' => $item['cantidad'],
                        ':precio_unit' => $item['precio_unit'],
                        ':subtotal' => $item['subtotal'],
                    ]);
                }
            }

            $pdo->commit();
            Response::success(['split' => $this->getSplitById($splitId)], 'Cuentas separadas creadas', 201);
        } catch (\InvalidArgumentException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::validationError(['accounts' => [$e->getMessage()]]);
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('WaiterController::createSplit ERROR: ' . $e->getMessage());
            Response::serverError('No se pudieron crear las cuentas separadas.');
        }
    }

    public function paySplitAccount(int $tableId, int $splitId, int $accountId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $paymentMethod = $this->normalizeWaiterPaymentMethod((string)($input['metodo_pago'] ?? ''));

        if ($restaurantId <= 0 || $paymentMethod === null) {
            Response::validationError(['metodo_pago' => ['Selecciona efectivo, tarjeta o transferencia']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT c.id, c.estado AS cuenta_estado, d.estado AS division_estado
                   FROM rest_cuenta_division_cuentas c
                   JOIN rest_cuenta_divisiones d ON d.id = c.division_id
                  WHERE c.id = :account_id AND d.id = :split_id
                    AND d.restaurante_id = :restaurant_id AND d.mesa_id = :table_id
                  FOR UPDATE"
            );
            $stmt->execute([
                ':account_id' => $accountId,
                ':split_id' => $splitId,
                ':restaurant_id' => $restaurantId,
                ':table_id' => $tableId,
            ]);
            $row = $stmt->fetch();
            if (!$row) {
                throw new \DomainException('La cuenta separada no existe.');
            }
            if ($row['cuenta_estado'] === 'pagada') {
                $pdo->commit();
                $existingSplit = $this->getSplitById($splitId);
                Response::success([
                    'split' => $existingSplit,
                    'closed' => ($existingSplit['estado'] ?? null) === 'pagada',
                ], 'La cuenta ya estaba pagada');
            }
            if ($row['division_estado'] !== 'activa') {
                throw new \DomainException('La division ya no esta activa.');
            }

            $payStmt = $pdo->prepare(
                "UPDATE rest_cuenta_division_cuentas
                    SET estado = 'pagada', metodo_pago = :payment_method,
                        pagado_por_usuario_id = :user_id, pagado_por_nombre = :user_name,
                        pagado_at = NOW(), updated_at = NOW()
                  WHERE id = :account_id AND estado = 'pendiente'"
            );
            $payStmt->execute([
                ':payment_method' => $paymentMethod,
                ':user_id' => (int)$user['id'],
                ':user_name' => (string)$user['nombre'],
                ':account_id' => $accountId,
            ]);

            $pendingStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM rest_cuenta_division_cuentas
                  WHERE division_id = :split_id AND estado = 'pendiente'"
            );
            $pendingStmt->execute([':split_id' => $splitId]);
            $pending = (int)$pendingStmt->fetchColumn();

            if ($pending === 0) {
                $methodsStmt = $pdo->prepare(
                    'SELECT COUNT(DISTINCT metodo_pago) AS method_count, MIN(metodo_pago) AS method
                       FROM rest_cuenta_division_cuentas WHERE division_id = :split_id'
                );
                $methodsStmt->execute([':split_id' => $splitId]);
                $methods = $methodsStmt->fetch();
                $finalMethod = (int)$methods['method_count'] === 1 ? (string)$methods['method'] : 'mixto';
                $this->finalizeSplitOrders($splitId, $finalMethod, $user);
                $completeStmt = $pdo->prepare(
                    "UPDATE rest_cuenta_divisiones
                        SET estado = 'pagada', completed_at = NOW(), updated_at = NOW()
                      WHERE id = :split_id AND estado = 'activa'"
                );
                $completeStmt->execute([':split_id' => $splitId]);
                $this->updateTableClaim($tableId, null, null, null, false);
            }

            $pdo->commit();
            Response::success([
                'split' => $this->getSplitById($splitId),
                'closed' => $pending === 0,
            ], $pending === 0 ? 'Mesa liquidada' : 'Cuenta pagada');
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('WaiterController::paySplitAccount ERROR: ' . $e->getMessage());
            Response::serverError('No se pudo registrar el pago.');
        }
    }

    public function cancelSplit(int $tableId, int $splitId): void
    {
        $user = $this->requireWaiter();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = (int)($input['restaurant_id'] ?? $_GET['restaurant_id'] ?? 0);
        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId);

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare(
                "SELECT d.id,
                        (SELECT COUNT(*) FROM rest_cuenta_division_cuentas c
                          WHERE c.division_id = d.id AND c.estado = 'pagada') AS paid_count
                   FROM rest_cuenta_divisiones d
                  WHERE d.id = :split_id AND d.restaurante_id = :restaurant_id
                    AND d.mesa_id = :table_id AND d.estado = 'activa'
                  FOR UPDATE"
            );
            $stmt->execute([
                ':split_id' => $splitId,
                ':restaurant_id' => $restaurantId,
                ':table_id' => $tableId,
            ]);
            $split = $stmt->fetch();
            if (!$split) {
                throw new \DomainException('La division activa no existe.');
            }
            if ((int)$split['paid_count'] > 0) {
                throw new \DomainException('No puedes cancelar una division con cuentas pagadas.');
            }
            $cancelStmt = $pdo->prepare(
                "UPDATE rest_cuenta_divisiones
                    SET estado = 'cancelada', cancelled_at = NOW(), updated_at = NOW()
                  WHERE id = :split_id"
            );
            $cancelStmt->execute([':split_id' => $splitId]);
            $pdo->commit();
            Response::success(['cancelled' => true], 'Division cancelada');
        } catch (\DomainException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Response::error($e->getMessage(), 409);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('WaiterController::cancelSplit ERROR: ' . $e->getMessage());
            Response::serverError('No se pudo cancelar la division.');
        }
    }

    private function splitTablesExist(): bool
    {
        return $this->tableExists('rest_cuenta_divisiones')
            && $this->tableExists('rest_cuenta_division_cuentas')
            && $this->tableExists('rest_cuenta_division_items');
    }

    private function getActiveSplit(int $restaurantId, int $tableId): ?array
    {
        if (!$this->splitTablesExist()) {
            return null;
        }
        $split = Database::queryOne(
            "SELECT id FROM rest_cuenta_divisiones
              WHERE restaurante_id = :restaurant_id AND mesa_id = :table_id AND estado = 'activa'
              ORDER BY id DESC LIMIT 1",
            [':restaurant_id' => $restaurantId, ':table_id' => $tableId]
        );

        return $split ? $this->getSplitById((int)$split['id']) : null;
    }

    private function getSplitById(int $splitId): ?array
    {
        if (!$this->splitTablesExist()) {
            return null;
        }
        $split = Database::queryOne(
            'SELECT id, restaurante_id, mesa_id, estado, creado_por_usuario_id,
                    creado_por_nombre, created_at, completed_at
               FROM rest_cuenta_divisiones WHERE id = :id',
            [':id' => $splitId]
        );
        if (!$split) {
            return null;
        }

        $accounts = Database::query(
            'SELECT id, numero, nombre, total, estado, metodo_pago,
                    pagado_por_usuario_id, pagado_por_nombre, pagado_at
               FROM rest_cuenta_division_cuentas
              WHERE division_id = :split_id ORDER BY numero ASC, id ASC',
            [':split_id' => $splitId]
        );
        $items = Database::query(
            'SELECT di.cuenta_id, di.pedido_item_id, di.cantidad, di.precio_unit, di.subtotal
               FROM rest_cuenta_division_items di
               JOIN rest_cuenta_division_cuentas c ON c.id = di.cuenta_id
              WHERE c.division_id = :split_id ORDER BY di.id ASC',
            [':split_id' => $splitId]
        );
        $itemsByAccount = [];
        foreach ($items as $item) {
            $itemsByAccount[(int)$item['cuenta_id']][] = [
                'pedido_item_id' => (int)$item['pedido_item_id'],
                'cantidad' => (int)$item['cantidad'],
                'precio_unit' => (float)$item['precio_unit'],
                'subtotal' => (float)$item['subtotal'],
            ];
        }

        $total = 0.0;
        $paidCount = 0;
        $normalizedAccounts = [];
        foreach ($accounts as $account) {
            $accountTotal = (float)$account['total'];
            $total += $accountTotal;
            if ($account['estado'] === 'pagada') {
                $paidCount++;
            }
            $normalizedAccounts[] = [
                'id' => (int)$account['id'],
                'numero' => (int)$account['numero'],
                'nombre' => $account['nombre'],
                'total' => $accountTotal,
                'estado' => $account['estado'],
                'metodo_pago' => $account['metodo_pago'],
                'pagado_por_usuario_id' => $account['pagado_por_usuario_id'] !== null ? (int)$account['pagado_por_usuario_id'] : null,
                'pagado_por_nombre' => $account['pagado_por_nombre'],
                'pagado_at' => $account['pagado_at'],
                'items' => $itemsByAccount[(int)$account['id']] ?? [],
            ];
        }

        return [
            'id' => (int)$split['id'],
            'restaurant_id' => (int)$split['restaurante_id'],
            'table_id' => (int)$split['mesa_id'],
            'estado' => $split['estado'],
            'total' => $total,
            'paid_count' => $paidCount,
            'accounts_count' => count($normalizedAccounts),
            'created_at' => $split['created_at'],
            'completed_at' => $split['completed_at'],
            'accounts' => $normalizedAccounts,
        ];
    }

    private function finalizeSplitOrders(int $splitId, string $paymentMethod, array $user): void
    {
        $orderRows = Database::query(
            'SELECT DISTINCT pi.pedido_id
               FROM rest_cuenta_division_items di
               JOIN rest_cuenta_division_cuentas c ON c.id = di.cuenta_id
               JOIN rest_pedido_items pi ON pi.id = di.pedido_item_id
              WHERE c.division_id = :split_id',
            [':split_id' => $splitId]
        );
        $orderIds = array_map(static fn(array $row): int => (int)$row['pedido_id'], $orderRows);
        if (empty($orderIds)) {
            throw new \DomainException('La division no contiene pedidos para cerrar.');
        }

        $columns = $this->getTableColumns('rest_pedidos');
        $set = ["estado = 'entregado'"];
        $params = [];
        if (in_array('cuenta_abierta', $columns, true)) {
            $set[] = 'cuenta_abierta = 0';
        }
        if (in_array('metodo_pago', $columns, true)) {
            $set[] = 'metodo_pago = :payment_method';
            $params[':payment_method'] = $paymentMethod;
        }
        if (in_array('pagado_at', $columns, true)) {
            $set[] = 'pagado_at = NOW()';
        }
        if (in_array('cerrado_por_mesero_usuario_id', $columns, true)) {
            $set[] = 'cerrado_por_mesero_usuario_id = :waiter_id';
            $params[':waiter_id'] = (int)$user['id'];
        }
        if (in_array('cerrado_por_mesero_nombre', $columns, true)) {
            $set[] = 'cerrado_por_mesero_nombre = :waiter_name';
            $params[':waiter_name'] = (string)$user['nombre'];
        }
        if (in_array('cerrado_at', $columns, true)) {
            $set[] = 'cerrado_at = NOW()';
        }
        if (in_array('actualizado_at', $columns, true)) {
            $set[] = 'actualizado_at = NOW()';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }
        $idParams = [];
        foreach ($orderIds as $index => $orderId) {
            $key = ':order_' . $index;
            $idParams[] = $key;
            $params[$key] = $orderId;
        }
        Database::rowCount(
            'UPDATE rest_pedidos SET ' . implode(', ', $set) .
            ' WHERE id IN (' . implode(',', $idParams) . ')',
            $params
        );
    }

    private function requireWaiter(): array
    {
        $tokenUser = AuthMiddleware::authenticate();
        $user = User::findById((int)$tokenUser->id);

        if (!$user) {
            Response::unauthorized('Usuario no encontrado');
        }

        $role = (string)($user['rol'] ?? 'user');
        if (!in_array($role, ['mesero', 'admin'], true)) {
            Response::error('No tienes permisos de mesero.', 403);
        }

        return $user;
    }

    private function normalizeWaiterPaymentMethod(string $value): ?string
    {
        $normalized = strtolower(trim($value));
        $map = [
            'cash' => 'efectivo',
            'efectivo' => 'efectivo',
            'card' => 'tarjeta',
            'tarjeta' => 'tarjeta',
            'transfer' => 'transferencia',
            'transferencia' => 'transferencia',
        ];

        return $map[$normalized] ?? null;
    }

    private function assertAssignedRestaurant(int $userId, int $restaurantId): void
    {
        $assignmentSource = $this->staffAssignmentSource();

        if ($assignmentSource === null) {
            Response::error('No existe tabla de asignacion de meseros. Ejecuta la migracion 025.', 500);
        }

        $roleColumn = $assignmentSource === 'rest_staff' ? 'rol_slug' : 'rol_operativo';
        $assigned = Database::queryOne(
            "SELECT 1
               FROM {$assignmentSource}
              WHERE usuario_id = :user_id
                AND restaurante_id = :restaurant_id
                AND {$roleColumn} = 'mesero'
                AND activo = 1
              LIMIT 1",
            [
                ':user_id' => $userId,
                ':restaurant_id' => $restaurantId,
            ]
        );

        if (!$assigned) {
            Response::error('No tienes asignada esta sucursal.', 403);
        }
    }

    private function findTable(int $restaurantId, int $tableId): ?array
    {
        if (!$this->tableExists('rest_mesas')) {
            return null;
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $labelColumn = $this->firstExistingColumn($columns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $zoneColumn = $this->firstExistingColumn($columns, ['zona_id', 'zone_id']);
        $zoneColumns = $this->tableExists('rest_zonas') ? $this->getTableColumns('rest_zonas') : [];
        $zoneIdColumn = $this->firstExistingColumn($zoneColumns, ['id']);
        $zoneLabelColumn = $this->firstExistingColumn($zoneColumns, ['nombre', 'nombre_zona', 'zona', 'name']);
        $zoneRestaurantColumn = $this->firstExistingColumn($zoneColumns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $canJoinZone = $zoneColumn !== null && $zoneIdColumn !== null && $zoneLabelColumn !== null;

        if ($idColumn === null || $labelColumn === null) {
            return null;
        }

        $fields = [
            "m.`{$idColumn}` AS id",
            "m.`{$labelColumn}` AS mesa_label",
        ];
        foreach (['mesero_usuario_id', 'mesero_nombre', 'cliente_nombre', 'reclamada_at', 'estado'] as $column) {
            if (in_array($column, $columns, true)) {
                $fields[] = "m.`{$column}`";
            }
        }
        if ($zoneColumn !== null) {
            $fields[] = "m.`{$zoneColumn}` AS zona_id";
        }
        if ($canJoinZone) {
            $fields[] = "z.`{$zoneLabelColumn}` AS zona_nombre";
        }

        $sql = 'SELECT ' . implode(', ', $fields) . " FROM rest_mesas m";
        if ($canJoinZone) {
            $sql .= " LEFT JOIN rest_zonas z ON z.`{$zoneIdColumn}` = m.`{$zoneColumn}`";
            if ($zoneRestaurantColumn !== null && $restaurantColumn !== null) {
                $sql .= " AND z.`{$zoneRestaurantColumn}` = m.`{$restaurantColumn}`";
            }
        }
        $sql .= " WHERE m.`{$idColumn}` = :id";
        $params = [':id' => $tableId];

        if ($restaurantColumn !== null) {
            $sql .= " AND m.`{$restaurantColumn}` = :restaurant_id";
            $params[':restaurant_id'] = $restaurantId;
        }

        return Database::queryOne($sql, $params);
    }

    private function buildTableResponse(int $restaurantId, int $tableId, int $userId): array
    {
        $table = $this->findTable($restaurantId, $tableId) ?? [];
        $openAccount = $this->findOpenAccountForTable($restaurantId, $tableId);

        return [
            'id' => $tableId,
            'label' => $this->formatMesaLabel((string)($table['mesa_label'] ?? $tableId)),
            'value' => (string)($table['mesa_label'] ?? $tableId),
            'status' => $openAccount ? 'cuenta_abierta' : 'mia',
            'estado' => $table['estado'] ?? null,
            'zona_id' => isset($table['zona_id']) && $table['zona_id'] !== null ? (int)$table['zona_id'] : null,
            'zona_nombre' => $table['zona_nombre'] ?? null,
            'mesero_usuario_id' => (int)($table['mesero_usuario_id'] ?? $userId),
            'mesero_nombre' => $table['mesero_nombre'] ?? null,
            'cliente_nombre' => $table['cliente_nombre'] ?? ($openAccount['cliente_nombre'] ?? null),
            'cuenta_abierta' => $openAccount !== null,
            'consumo_id' => $openAccount['consumo_id'] ?? null,
            'total' => $openAccount !== null ? (float)($openAccount['total'] ?? 0) : 0,
        ];
    }

    private function buildAccount(int $restaurantId, int $tableId, int $userId): array
    {
        $table = $this->buildTableResponse($restaurantId, $tableId, $userId);
        $orders = $this->getOpenOrdersForTable($restaurantId, $tableId);
        $items = [];
        $total = 0.0;

        foreach ($orders as &$order) {
            $orderItems = $this->getOrderItems((int)$order['id']);
            $order['items'] = $orderItems;
            $total += (float)($order['total'] ?? 0);
            foreach ($orderItems as $item) {
                $items[] = $item + [
                    'pedido_id' => (int)$order['id'],
                    'pedido_folio' => $order['folio'] ?? null,
                    'pedido_created_at' => $order['created_at'] ?? null,
                ];
            }
        }

        return [
            'table' => $table,
            'orders' => $orders,
            'items' => $items,
            'total' => $total,
            'orders_count' => count($orders),
            'cliente_nombre' => $table['cliente_nombre'] ?? null,
            'mesero_nombre' => $table['mesero_nombre'] ?? null,
            'active_split' => $this->getActiveSplit($restaurantId, $tableId),
        ];
    }

    private function findOpenAccountForTable(int $restaurantId, int $tableId): ?array
    {
        $orders = $this->getOpenOrdersForTable($restaurantId, $tableId);
        if (empty($orders)) {
            return null;
        }

        $total = 0.0;
        $latest = $orders[count($orders) - 1];
        foreach ($orders as $order) {
            $total += (float)($order['total'] ?? 0);
        }

        return [
            'consumo_id' => $latest['consumo_id'] ?? null,
            'cliente_nombre' => $latest['cliente_nombre'] ?? null,
            'mesero_nombre' => $latest['mesero_nombre'] ?? null,
            'total' => $total,
        ];
    }

    private function getOpenOrdersForTable(int $restaurantId, int $tableId): array
    {
        if (!$this->tableExists('rest_pedidos')) {
            return [];
        }

        $columns = $this->getTableColumns('rest_pedidos');
        $fields = ['id', 'folio', 'estado', 'subtotal', 'total', 'tipo_pedido', 'created_at'];
        foreach (['consumo_id', 'cuenta_abierta', 'cliente_nombre', 'mesero_nombre', 'mesero_usuario_id', 'pedido_origen'] as $column) {
            if (in_array($column, $columns, true)) {
                $fields[] = $column;
            }
        }

        $sql = 'SELECT ' . implode(', ', array_map(static fn(string $field): string => "`{$field}`", $fields)) . '
                  FROM rest_pedidos
                 WHERE restaurante_id = :restaurant_id
                   AND mesa_id = :table_id
                   AND tipo_pedido IN ("eat_in", "dine_in")';
        $params = [
            ':restaurant_id' => $restaurantId,
            ':table_id' => $tableId,
        ];

        if (in_array('cuenta_abierta', $columns, true)) {
            $sql .= ' AND cuenta_abierta = 1';
        } else {
            $sql .= " AND estado NOT IN ('entregado', 'cancelado')";
        }
        if (in_array('salida_validado_at', $columns, true)) {
            $sql .= ' AND salida_validado_at IS NULL';
        }

        $sql .= ' ORDER BY created_at ASC, id ASC';
        return Database::query($sql, $params);
    }

    private function getOrderItems(int $orderId): array
    {
        if (!$this->tableExists('rest_pedido_items')) {
            return [];
        }

        $columns = $this->getTableColumns('rest_pedido_items');
        $hasExtras = in_array('extras_json', $columns, true);

        $sql = "SELECT pi.id, pi.pedido_id, pi.platillo_id, pi.cantidad, pi.precio_unit, pi.notas, pi.estado,
                       p.nombre AS nombre, p.imagen AS imagen" . ($hasExtras ? ', pi.extras_json' : '') . "
                  FROM rest_pedido_items pi
             LEFT JOIN rest_platillos p ON p.id = pi.platillo_id
                 WHERE pi.pedido_id = :order_id
              ORDER BY pi.id ASC";
        $rows = Database::query($sql, [':order_id' => $orderId]);

        return array_map(static function (array $item) use ($hasExtras): array {
            $extras = [];
            if ($hasExtras && !empty($item['extras_json'])) {
                $decoded = json_decode((string)$item['extras_json'], true);
                $extras = is_array($decoded) ? $decoded : [];
            }

            return [
                'id' => (int)$item['id'],
                'pedido_id' => (int)$item['pedido_id'],
                'platillo_id' => (int)$item['platillo_id'],
                'nombre' => $item['nombre'] ?? 'Producto',
                'imagen' => $item['imagen'] ?? null,
                'cantidad' => (int)$item['cantidad'],
                'precio_unit' => (float)$item['precio_unit'],
                'subtotal' => (float)$item['precio_unit'] * (int)$item['cantidad'],
                'notas' => $item['notas'] ?? null,
                'estado' => $item['estado'] ?? null,
                'modificadores' => $extras,
            ];
        }, $rows);
    }

    private function updateTableClaim(int $tableId, ?int $waiterId, ?string $waiterName, ?string $customerName, bool $claim): void
    {
        $columns = $this->getTableColumns('rest_mesas');
        $set = [];
        $params = [':id' => $tableId];

        if (in_array('mesero_usuario_id', $columns, true)) {
            $set[] = 'mesero_usuario_id = :waiter_id';
            $params[':waiter_id'] = $waiterId;
        }
        if (in_array('mesero_nombre', $columns, true)) {
            $set[] = 'mesero_nombre = :waiter_name';
            $params[':waiter_name'] = $waiterName;
        }
        if (in_array('cliente_nombre', $columns, true)) {
            $set[] = 'cliente_nombre = :customer_name';
            $params[':customer_name'] = $customerName;
        }
        if (in_array('reclamada_at', $columns, true)) {
            $set[] = 'reclamada_at = ' . ($claim ? 'NOW()' : 'NULL');
        }
        if (in_array('estado', $columns, true)) {
            $set[] = "estado = :estado";
            $params[':estado'] = $claim ? 'ocupada' : 'disponible';
        }

        if (empty($set)) {
            return;
        }

        Database::rowCount(
            'UPDATE rest_mesas SET ' . implode(', ', $set) . ' WHERE id = :id',
            $params
        );
    }

    private function normalizeBranch(array $row): array
    {
        $types = json_decode((string)($row['tipos_entrega'] ?? '[]'), true) ?: [];

        return [
            'id' => (int)$row['id'],
            'nombre' => $row['nombre'],
            'slug' => $row['slug'] ?? null,
            'descripcion' => $row['descripcion'] ?? null,
            'direccion' => $row['direccion'] ?? null,
            'lat' => $row['lat'] !== null ? (float)$row['lat'] : null,
            'lng' => $row['lng'] !== null ? (float)$row['lng'] : null,
            'logo' => $row['logo'] ?? null,
            'imagen_banner' => $row['imagen_banner'] ?? null,
            'telefono' => $row['telefono'] ?? null,
            'color_primario' => $row['color_primario'] ?? null,
            'color_secundario' => $row['color_secundario'] ?? null,
            'horario_apertura' => $row['horario_apertura'] ?? null,
            'horario_cierre' => $row['horario_cierre'] ?? null,
            'horarios_json' => $row['horarios_json'] ?? null,
            'mesas_habilitadas' => (bool)($row['mesas_habilitadas'] ?? false),
            'reservas_habilitadas' => (bool)($row['reservas_habilitadas'] ?? false),
            'activo' => (bool)($row['activo'] ?? true),
            'tipos_entrega' => $types,
        ];
    }

    private function tableExists(string $tableName): bool
    {
        $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
        return !empty($exists);
    }

    private function staffAssignmentSource(): ?string
    {
        if ($this->tableExists('rest_staff')) {
            return 'rest_staff';
        }

        if ($this->tableExists('rest_staff_restaurantes')) {
            return 'rest_staff_restaurantes';
        }

        return null;
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
        $trimmed = trim($value);
        return stripos($trimmed, 'mesa') === 0 ? $trimmed : 'Mesa ' . $trimmed;
    }
}
