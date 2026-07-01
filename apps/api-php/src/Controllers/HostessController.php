<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\User;

class HostessController
{
    public function branches(): void
    {
        $user = $this->requireHostess();
        $source = $this->staffAssignmentSource();

        if ($this->isAdmin($user)) {
            $branches = Database::query(
                "SELECT r.id, r.nombre, r.slug, r.descripcion, r.direccion, r.lat, r.lng,
                        r.logo, r.imagen_banner, r.telefono, r.color_primario, r.color_secundario,
                        r.horario_apertura, r.horario_cierre, r.horarios_json,
                        r.mesas_habilitadas, r.reservas_habilitadas, r.activo,
                        COALESCE(rc.tipos_entrega, '[\"delivery\",\"pickup\"]') AS tipos_entrega
                   FROM rest_restaurantes r
              LEFT JOIN rest_configuracion rc ON rc.restaurante_id = r.id AND rc.activo = 1
                  WHERE r.activo = 1
               ORDER BY r.nombre ASC"
            );
            Response::success(['branches' => array_map([$this, 'normalizeBranch'], $branches)]);
        }

        if ($source === null) {
            Response::success(['branches' => []]);
        }

        $roleColumn = $source === 'rest_staff' ? 'rol_slug' : 'rol_operativo';
        $branches = Database::query(
            "SELECT r.id, r.nombre, r.slug, r.descripcion, r.direccion, r.lat, r.lng,
                    r.logo, r.imagen_banner, r.telefono, r.color_primario, r.color_secundario,
                    r.horario_apertura, r.horario_cierre, r.horarios_json,
                    r.mesas_habilitadas, r.reservas_habilitadas, r.activo,
                    COALESCE(rc.tipos_entrega, '[\"delivery\",\"pickup\"]') AS tipos_entrega
               FROM {$source} sr
               JOIN rest_restaurantes r ON r.id = sr.restaurante_id
          LEFT JOIN rest_configuracion rc ON rc.restaurante_id = r.id AND rc.activo = 1
              WHERE sr.usuario_id = :user_id
                AND sr.{$roleColumn} IN ('hostess', 'hostes', 'host', 'anfitrion', 'anfitriona', 'portero', 'recepcion', 'admin')
                AND sr.activo = 1
                AND r.activo = 1
           ORDER BY r.nombre ASC",
            [':user_id' => (int)$user['id']]
        );

        Response::success(['branches' => array_map([$this, 'normalizeBranch'], $branches)]);
    }

    public function reservations(): void
    {
        $user = $this->requireHostess();
        $restaurantId = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId, $user);

        if (!$this->tableExists('rest_reservaciones')) {
            Response::success(['reservations' => []]);
        }

        $reservationColumns = $this->getTableColumns('rest_reservaciones');
        $tableColumns = $this->tableExists('rest_mesas') ? $this->getTableColumns('rest_mesas') : [];
        $tableIdColumn = $this->firstExistingColumn($tableColumns, ['id']);
        $tableLabelColumn = $this->firstExistingColumn($tableColumns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);

        $fields = [
            'rv.id',
            'rv.restaurante_id',
            'rv.mesa_id',
            'rv.nombre',
            'rv.telefono',
            'rv.email',
            'rv.fecha',
            'rv.hora',
            'rv.personas',
            'rv.estado',
            'rv.origen',
            'rv.notas',
            'rv.created_at',
            'rv.updated_at',
        ];

        foreach (['comensal_id', 'mesero_id', 'confirmacion_enviada', 'recordatorio_enviado'] as $column) {
            if (in_array($column, $reservationColumns, true)) {
                $fields[] = "rv.{$column}";
            }
        }

        if ($tableIdColumn !== null && $tableLabelColumn !== null) {
            $fields[] = "m.`{$tableLabelColumn}` AS mesa_label";
        } else {
            $fields[] = 'NULL AS mesa_label';
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM rest_reservaciones rv';
        if ($tableIdColumn !== null && $tableLabelColumn !== null) {
            $sql .= " LEFT JOIN rest_mesas m ON m.`{$tableIdColumn}` = rv.mesa_id";
        }
        $sql .= ' WHERE rv.restaurante_id = :restaurant_id';
        $params = [':restaurant_id' => $restaurantId];

        $date = trim((string)($_GET['fecha'] ?? ''));
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            $sql .= ' AND rv.fecha = :fecha';
            $params[':fecha'] = $date;
        } else {
            $sql .= ' AND rv.fecha = CURDATE()';
        }

        $status = trim((string)($_GET['estado'] ?? ''));
        if ($status !== '' && $status !== 'todas') {
            $sql .= ' AND rv.estado = :status';
            $params[':status'] = $status;
        }

        $sql .= " ORDER BY FIELD(rv.estado, 'confirmada', 'pendiente', 'completada', 'cancelada'), rv.fecha ASC, rv.hora ASC, rv.id ASC";

        $rows = Database::query($sql, $params);
        Response::success(['reservations' => array_map([$this, 'normalizeReservation'], $rows)]);
    }

    public function orders(): void
    {
        $user = $this->requireHostess();
        $restaurantId = isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId, $user);

        if (!$this->tableExists('rest_pedidos')) {
            Response::success(['orders' => []]);
        }

        $columns = $this->getTableColumns('rest_pedidos');
        $fields = [
            'p.id',
            'p.restaurante_id',
            'p.folio',
            'p.estado',
            'p.subtotal',
            'p.total',
            'p.tipo_pedido',
            'p.created_at',
        ];
        foreach (['cliente_nombre', 'notas', 'direccion_entrega', 'metodo_pago', 'updated_at'] as $column) {
            if (in_array($column, $columns, true)) {
                $fields[] = "p.{$column}";
            }
        }

        $itemsCountField = $this->tableExists('rest_pedido_items')
            ? "(SELECT COALESCE(SUM(i.cantidad), 0) FROM rest_pedido_items i WHERE i.pedido_id = p.id) AS items_count"
            : '0 AS items_count';
        $fields[] = $itemsCountField;

        $sql = 'SELECT ' . implode(', ', $fields) . '
                  FROM rest_pedidos p
                 WHERE p.restaurante_id = :restaurant_id
                   AND p.tipo_pedido IN (\'pickup\', \'delivery\')
                   AND p.estado NOT IN (\'entregado\', \'cancelado\')
              ORDER BY FIELD(p.estado, \'listo\', \'en_camino\', \'en_preparacion\', \'pendiente\'),
                       p.created_at ASC,
                       p.id ASC';

        $rows = Database::query($sql, [':restaurant_id' => $restaurantId]);
        Response::success(['orders' => array_map([$this, 'normalizeReleaseOrder'], $rows)]);
    }

    public function completeOrder(int $id): void
    {
        $user = $this->requireHostess();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId, $user);

        if (!$this->tableExists('rest_pedidos')) {
            Response::notFound('No existe el modulo de pedidos');
        }

        $order = Database::queryOne(
            "SELECT * FROM rest_pedidos
              WHERE id = :id
                AND restaurante_id = :restaurant_id
                AND tipo_pedido IN ('pickup', 'delivery')
              LIMIT 1",
            [':id' => $id, ':restaurant_id' => $restaurantId]
        );

        if (!$order) {
            Response::notFound('Pedido no encontrado');
        }

        if (($order['estado'] ?? null) === 'cancelado') {
            Response::error('No se puede completar un pedido cancelado.', 409);
        }

        if ($this->tableExists('rest_pedido_items')) {
            $itemColumns = $this->getTableColumns('rest_pedido_items');
            if (in_array('estado', $itemColumns, true)) {
                Database::rowCount(
                    "UPDATE rest_pedido_items
                        SET estado = 'entregado'
                      WHERE pedido_id = :order_id
                        AND COALESCE(estado, 'pendiente') <> 'cancelado'",
                    [':order_id' => $id]
                );
            }
        }

        $columns = $this->getTableColumns('rest_pedidos');
        $set = ["estado = 'entregado'"];
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        Database::rowCount(
            'UPDATE rest_pedidos SET ' . implode(', ', $set) . ' WHERE id = :id AND restaurante_id = :restaurant_id',
            [':id' => $id, ':restaurant_id' => $restaurantId]
        );

        Response::success(['ok' => true], 'Pedido completado');
    }

    public function completeReservation(int $id): void
    {
        $user = $this->requireHostess();
        $input = ValidationMiddleware::getAllInput();
        $restaurantId = isset($input['restaurant_id']) ? (int)$input['restaurant_id'] : 0;

        if ($restaurantId <= 0) {
            Response::validationError(['restaurant_id' => ['Selecciona una sucursal valida']]);
        }
        $this->assertAssignedRestaurant((int)$user['id'], $restaurantId, $user);

        if (!$this->tableExists('rest_reservaciones')) {
            Response::notFound('No existe el modulo de reservaciones');
        }

        $reservation = Database::queryOne(
            'SELECT * FROM rest_reservaciones WHERE id = :id AND restaurante_id = :restaurant_id LIMIT 1',
            [':id' => $id, ':restaurant_id' => $restaurantId]
        );

        if (!$reservation) {
            Response::notFound('Reservacion no encontrada');
        }

        if (($reservation['estado'] ?? null) === 'cancelada') {
            Response::error('No se puede completar una reservacion cancelada.', 409);
        }

        $columns = $this->getTableColumns('rest_reservaciones');
        $set = ["estado = 'completada'"];
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        Database::rowCount(
            'UPDATE rest_reservaciones SET ' . implode(', ', $set) . ' WHERE id = :id AND restaurante_id = :restaurant_id',
            [':id' => $id, ':restaurant_id' => $restaurantId]
        );

        $updated = Database::queryOne(
            'SELECT * FROM rest_reservaciones WHERE id = :id AND restaurante_id = :restaurant_id LIMIT 1',
            [':id' => $id, ':restaurant_id' => $restaurantId]
        );

        Response::success(['reservation' => $this->normalizeReservation($updated ?? $reservation)], 'Reservacion completada');
    }

    private function requireHostess(): array
    {
        $tokenUser = AuthMiddleware::authenticate();
        $user = User::findAuthenticated(
            (int)$tokenUser->id,
            isset($tokenUser->auth_source) ? (string)$tokenUser->auth_source : null
        );

        if (!$user) {
            Response::unauthorized('Usuario no encontrado');
        }

        $role = (string)($user['rol'] ?? 'user');
        if (!in_array($role, ['hostess', 'admin', 'admin_restaurante'], true)) {
            Response::error('No tienes permisos de hostess.', 403);
        }

        return $user;
    }

    private function assertAssignedRestaurant(int $userId, int $restaurantId, array $user): void
    {
        if ($this->isAdmin($user)) {
            return;
        }

        $source = $this->staffAssignmentSource();
        if ($source === null) {
            Response::error('No existe tabla de asignacion de personal.', 500);
        }

        $roleColumn = $source === 'rest_staff' ? 'rol_slug' : 'rol_operativo';
        $assigned = Database::queryOne(
            "SELECT 1
               FROM {$source}
              WHERE usuario_id = :user_id
                AND restaurante_id = :restaurant_id
                AND {$roleColumn} IN ('hostess', 'hostes', 'host', 'anfitrion', 'anfitriona', 'portero', 'recepcion', 'admin')
                AND activo = 1
              LIMIT 1",
            [':user_id' => $userId, ':restaurant_id' => $restaurantId]
        );

        if (!$assigned) {
            Response::error('No tienes asignada esta sucursal.', 403);
        }
    }

    private function isAdmin(array $user): bool
    {
        return in_array((string)($user['rol'] ?? ''), ['admin', 'admin_restaurante'], true);
    }

    private function normalizeReservation(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'restaurante_id' => (int)$row['restaurante_id'],
            'mesa_id' => isset($row['mesa_id']) && $row['mesa_id'] !== null ? (int)$row['mesa_id'] : null,
            'mesa_label' => !empty($row['mesa_label'])
                ? $this->formatMesaLabel((string)$row['mesa_label'])
                : (isset($row['mesa_id']) && $row['mesa_id'] !== null ? $this->formatMesaLabel((string)$row['mesa_id']) : null),
            'nombre' => $row['nombre'] ?? 'Cliente',
            'telefono' => $row['telefono'] ?? null,
            'email' => $row['email'] ?? null,
            'fecha' => $row['fecha'] ?? null,
            'hora' => $row['hora'] ?? null,
            'personas' => (int)($row['personas'] ?? 0),
            'estado' => $row['estado'] ?? 'pendiente',
            'origen' => $row['origen'] ?? null,
            'notas' => $row['notas'] ?? null,
            'confirmacion_enviada' => (bool)($row['confirmacion_enviada'] ?? false),
            'recordatorio_enviado' => (bool)($row['recordatorio_enviado'] ?? false),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private function normalizeReleaseOrder(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'restaurante_id' => (int)$row['restaurante_id'],
            'folio' => $row['folio'] ?? null,
            'estado' => $row['estado'] ?? 'pendiente',
            'tipo_pedido' => $row['tipo_pedido'] ?? null,
            'cliente_nombre' => $row['cliente_nombre'] ?? 'Cliente app',
            'notas' => $row['notas'] ?? null,
            'direccion_entrega' => $row['direccion_entrega'] ?? null,
            'metodo_pago' => $row['metodo_pago'] ?? null,
            'subtotal' => (float)($row['subtotal'] ?? 0),
            'total' => (float)($row['total'] ?? 0),
            'items_count' => (int)($row['items_count'] ?? 0),
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
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

    private function tableExists(string $tableName): bool
    {
        $exists = Database::query("SHOW TABLES LIKE '{$tableName}'");
        return !empty($exists);
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
