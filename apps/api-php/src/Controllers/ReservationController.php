<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;

class ReservationController
{
    private const BLOCK_BEFORE_SECONDS = 7200;
    private const BLOCK_AFTER_SECONDS = 9000;

    public function availability(): void
    {
        AuthMiddleware::authenticate();

        $restaurantId = (int)($_GET['restaurant_id'] ?? 0);
        $date = trim((string)($_GET['fecha'] ?? ''));
        $time = trim((string)($_GET['hora'] ?? ''));
        $people = (int)($_GET['personas'] ?? 0);

        $this->validateAvailabilityInput($restaurantId, $date, $time, $people);
        $this->assertReservationsEnabled($restaurantId);

        Response::success([
            'tables' => $this->availableTables($restaurantId, $date, $time, $people),
            'block_window' => [
                'before_seconds' => self::BLOCK_BEFORE_SECONDS,
                'after_seconds' => self::BLOCK_AFTER_SECONDS,
            ],
        ]);
    }

    public function store(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = json_decode((string)file_get_contents('php://input'), true) ?: [];

        $restaurantId = (int)($input['restaurant_id'] ?? 0);
        $tableId = (int)($input['mesa_id'] ?? 0);
        $date = trim((string)($input['fecha'] ?? ''));
        $time = trim((string)($input['hora'] ?? ''));
        $people = (int)($input['personas'] ?? 0);
        $name = trim((string)($input['nombre'] ?? ''));
        $phone = trim((string)($input['telefono'] ?? ''));
        $email = trim((string)($input['email'] ?? ''));
        $notes = trim((string)($input['notas'] ?? ''));

        $phone = $this->normalizePhone($phone);
        $this->validateReservationInput($restaurantId, $tableId, $date, $time, $people, $name, $phone, $email);

        $this->assertReservationsEnabled($restaurantId);
        if (!$this->tableExists('rest_reservaciones') || !$this->tableExists('rest_mesas')) {
            Response::serverError('El modulo de reservaciones no esta configurado.');
        }
        $this->assertTableCanBeReserved($restaurantId, $tableId, $people);

        $pdo = Database::getInstance();
        try {
            $pdo->beginTransaction();

            if (!$this->isTableAvailable($restaurantId, $tableId, $date, $time, $people, true)) {
                throw new \DomainException('Esta mesa ya no esta disponible para ese horario. Elige otra mesa.');
            }

            $columns = $this->getTableColumns('rest_reservaciones');
            $data = [
                'restaurante_id' => $restaurantId,
                'mesa_id' => $tableId,
                'nombre' => $name,
                'telefono' => $phone,
                'email' => $email !== '' ? $email : null,
                'fecha' => $date,
                'hora' => $this->normalizeTime($time),
                'personas' => $people,
                'estado' => 'confirmada',
                'origen' => 'comensal',
                'canal_reserva' => 'movil',
                'notas' => $notes !== '' ? $notes : null,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            if (in_array('comensal_id', $columns, true)) {
                $data['comensal_id'] = (int)$user->id;
            }
            if (in_array('mesero_id', $columns, true)) {
                $data['mesero_id'] = $this->findAssignedWaiterForTable($restaurantId, $tableId, $date);
            }

            $insertData = array_intersect_key($data, array_flip($columns));
            $fields = array_keys($insertData);
            $placeholders = array_map(static fn(string $field): string => ':' . $field, $fields);
            $stmt = $pdo->prepare(
                'INSERT INTO rest_reservaciones (`' . implode('`,`', $fields) . '`) VALUES (' . implode(',', $placeholders) . ')'
            );
            foreach ($insertData as $field => $value) {
                $stmt->bindValue(':' . $field, $value);
            }
            $stmt->execute();
            $reservationId = (int)$pdo->lastInsertId();
            $pdo->commit();

            Response::success([
                'reservation' => $this->reservationById($reservationId),
            ], 'Reservacion confirmada', 201);
        } catch (\DomainException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            Response::error($exception->getMessage(), 409);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log('ReservationController::store ERROR: ' . $exception->getMessage());
            Response::serverError('No se pudo crear la reservacion.');
        }
    }

    private function validateAvailabilityInput(int $restaurantId, string $date, string $time, int $people): void
    {
        $errors = [];
        if ($restaurantId <= 0) $errors['restaurant_id'][] = 'Selecciona una sucursal';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !$this->isValidDate($date)) $errors['fecha'][] = 'Fecha invalida';
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $time)) $errors['hora'][] = 'Hora invalida';
        if ($people <= 0) $errors['personas'][] = 'Indica el numero de personas';
        if ($errors) Response::validationError($errors);
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if (strlen($digits) === 12 && strpos($digits, '52') === 0) {
            return substr($digits, 2);
        }
        if (strlen($digits) === 13 && strpos($digits, '521') === 0) {
            return substr($digits, 3);
        }
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function validateReservationInput(
        int $restaurantId,
        int $tableId,
        string $date,
        string $time,
        int $people,
        string $name,
        string $phone,
        string $email
    ): void {
        $this->validateAvailabilityInput($restaurantId, $date, $time, $people);

        $errors = [];
        if ($tableId <= 0) $errors['mesa_id'][] = 'Selecciona una mesa disponible';
        if ($name === '') $errors['nombre'][] = 'Ingresa tu nombre';
        if (!preg_match('/^\d{10}$/', $phone)) $errors['telefono'][] = 'Ingresa un telefono de 10 digitos';
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'][] = 'Ingresa un correo valido';
        if ($errors) Response::validationError($errors);
    }

    private function assertReservationsEnabled(int $restaurantId): void
    {
        $branch = Database::queryOne(
            'SELECT id, reservas_habilitadas, activo FROM rest_restaurantes WHERE id = :id LIMIT 1',
            [':id' => $restaurantId]
        );
        if (!$branch || !(bool)($branch['activo'] ?? false)) {
            Response::notFound('Sucursal no encontrada');
        }
        if (!(bool)($branch['reservas_habilitadas'] ?? false)) {
            Response::error('Las reservaciones no estan habilitadas para esta sucursal.', 409);
        }
    }

    private function availableTables(int $restaurantId, string $date, string $time, int $people): array
    {
        if (!$this->tableExists('rest_mesas')) {
            return [];
        }

        $tableColumns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($tableColumns, ['id']);
        $restaurantColumn = $this->firstExistingColumn($tableColumns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $labelColumn = $this->firstExistingColumn($tableColumns, ['numero_mesa', 'numero', 'mesa', 'nombre', 'codigo', 'qr_codigo']);
        $activeColumn = $this->firstExistingColumn($tableColumns, ['activo']);
        $capacityColumn = $this->firstExistingColumn($tableColumns, ['capacidad', 'personas', 'capacity', 'asientos']);
        $zoneColumn = $this->firstExistingColumn($tableColumns, ['zona_id', 'zone_id']);
        $zoneColumns = $this->tableExists('rest_zonas') ? $this->getTableColumns('rest_zonas') : [];
        $zoneIdColumn = $this->firstExistingColumn($zoneColumns, ['id']);
        $zoneLabelColumn = $this->firstExistingColumn($zoneColumns, ['nombre', 'nombre_zona', 'zona', 'name']);
        $zoneRestaurantColumn = $this->firstExistingColumn($zoneColumns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $canJoinZone = $zoneColumn !== null && $zoneIdColumn !== null && $zoneLabelColumn !== null;

        if ($idColumn === null || $restaurantColumn === null || $labelColumn === null) {
            return [];
        }

        $fields = [
            "m.`{$idColumn}` AS id",
            "m.`{$labelColumn}` AS label",
        ];
        $fields[] = $capacityColumn !== null ? "m.`{$capacityColumn}` AS capacity" : '0 AS capacity';
        if ($zoneColumn !== null) {
            $fields[] = "m.`{$zoneColumn}` AS zona_id";
        }
        if ($canJoinZone) {
            $fields[] = "z.`{$zoneLabelColumn}` AS zona_nombre";
        }

        $sql = 'SELECT ' . implode(', ', $fields) . ' FROM rest_mesas m';
        if ($canJoinZone) {
            $sql .= " LEFT JOIN rest_zonas z ON z.`{$zoneIdColumn}` = m.`{$zoneColumn}`";
            if ($zoneRestaurantColumn !== null) {
                $sql .= " AND z.`{$zoneRestaurantColumn}` = m.`{$restaurantColumn}`";
            }
        }
        $sql .= ' WHERE m.`' . $restaurantColumn . '` = :restaurant_id';
        $params = [':restaurant_id' => $restaurantId];
        if ($activeColumn !== null) {
            $sql .= " AND m.`{$activeColumn}` = 1";
        }
        if ($capacityColumn !== null) {
            $sql .= " AND (m.`{$capacityColumn}` IS NULL OR m.`{$capacityColumn}` = 0 OR m.`{$capacityColumn}` >= :people)";
            $params[':people'] = $people;
        }
        $sql .= " ORDER BY COALESCE(m.`{$labelColumn}`, m.`{$idColumn}`) ASC";

        $rows = Database::query($sql, $params);
        $available = [];
        foreach ($rows as $row) {
            $tableId = (int)$row['id'];
            if (!$this->isTableAvailable($restaurantId, $tableId, $date, $time, $people, false)) {
                continue;
            }
            $available[] = [
                'id' => $tableId,
                'label' => $this->formatMesaLabel((string)$row['label']),
                'nombre' => $this->formatMesaLabel((string)$row['label']),
                'capacity' => (int)($row['capacity'] ?? 0),
                'capacidad' => (int)($row['capacity'] ?? 0),
                'zona_id' => isset($row['zona_id']) && $row['zona_id'] !== null ? (int)$row['zona_id'] : null,
                'zona_nombre' => $row['zona_nombre'] ?? null,
            ];
        }

        return $available;
    }

    private function isTableAvailable(int $restaurantId, int $tableId, string $date, string $time, int $people, bool $lock): bool
    {
        if (!$this->tableExists('rest_reservaciones')) {
            return false;
        }

        $requestedTime = $this->normalizeTime($time);
        $sql = "SELECT id
                  FROM rest_reservaciones
                 WHERE restaurante_id = :restaurant_id
                   AND mesa_id = :table_id
                   AND fecha = :date
                   AND COALESCE(estado, 'pendiente') IN ('pendiente', 'confirmada')
                   AND TIME_TO_SEC(TIMEDIFF(:requested_time, hora)) >= :block_before
                   AND TIME_TO_SEC(TIMEDIFF(:requested_time_2, hora)) < :block_after";
        if ($lock) {
            $sql .= ' FOR UPDATE';
        }

        $conflicts = Database::query($sql, [
            ':restaurant_id' => $restaurantId,
            ':table_id' => $tableId,
            ':date' => $date,
            ':requested_time' => $requestedTime,
            ':requested_time_2' => $requestedTime,
            ':block_before' => -self::BLOCK_BEFORE_SECONDS,
            ':block_after' => self::BLOCK_AFTER_SECONDS,
        ]);

        return empty($conflicts);
    }

    private function assertTableCanBeReserved(int $restaurantId, int $tableId, int $people): void
    {
        if (!$this->tableExists('rest_mesas')) {
            Response::serverError('El modulo de mesas no esta configurado.');
        }

        $columns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($columns, ['id']);
        $restaurantColumn = $this->firstExistingColumn($columns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $activeColumn = $this->firstExistingColumn($columns, ['activo']);
        $capacityColumn = $this->firstExistingColumn($columns, ['capacidad', 'personas', 'capacity', 'asientos']);

        if ($idColumn === null || $restaurantColumn === null) {
            Response::serverError('La tabla de mesas no tiene la estructura requerida.');
        }

        $fields = ["`{$idColumn}` AS id"];
        $fields[] = $capacityColumn !== null ? "`{$capacityColumn}` AS capacity" : '0 AS capacity';
        if ($activeColumn !== null) {
            $fields[] = "`{$activeColumn}` AS active";
        }

        $table = Database::queryOne(
            'SELECT ' . implode(', ', $fields) . " FROM rest_mesas WHERE `{$idColumn}` = :table_id AND `{$restaurantColumn}` = :restaurant_id LIMIT 1",
            [':table_id' => $tableId, ':restaurant_id' => $restaurantId]
        );

        if (!$table) {
            Response::validationError(['mesa_id' => ['La mesa seleccionada no existe para esta sucursal']]);
        }
        if ($activeColumn !== null && !(bool)($table['active'] ?? false)) {
            Response::validationError(['mesa_id' => ['La mesa seleccionada no esta activa']]);
        }
        $capacity = (int)($table['capacity'] ?? 0);
        if ($capacity > 0 && $capacity < $people) {
            Response::validationError(['personas' => ['La mesa seleccionada no tiene capacidad suficiente']]);
        }
    }

    private function reservationById(int $reservationId): ?array
    {
        if ($reservationId <= 0) return null;
        return Database::queryOne('SELECT * FROM rest_reservaciones WHERE id = :id LIMIT 1', [':id' => $reservationId]);
    }

    private function findAssignedWaiterForTable(int $restaurantId, int $tableId, string $date): ?int
    {
        if (!$this->tableExists('rest_mesas') || !$this->tableExists('rest_mesero_turno')) {
            return null;
        }

        $tableColumns = $this->getTableColumns('rest_mesas');
        $idColumn = $this->firstExistingColumn($tableColumns, ['id']);
        $restaurantColumn = $this->firstExistingColumn($tableColumns, ['restaurante_id', 'sucursal_id', 'branch_id']);
        $zoneColumn = $this->firstExistingColumn($tableColumns, ['zona_id', 'zone_id']);
        if ($idColumn === null || $restaurantColumn === null || $zoneColumn === null) {
            return null;
        }

        $shiftColumns = $this->getTableColumns('rest_mesero_turno');
        foreach (['restaurante_id', 'usuario_id', 'zona_id', 'turno_fecha', 'activo'] as $requiredColumn) {
            if (!in_array($requiredColumn, $shiftColumns, true)) {
                return null;
            }
        }

        $table = Database::queryOne(
            "SELECT `{$zoneColumn}` AS zona_id FROM rest_mesas WHERE `{$idColumn}` = :table_id AND `{$restaurantColumn}` = :restaurant_id LIMIT 1",
            [':table_id' => $tableId, ':restaurant_id' => $restaurantId]
        );
        $zoneId = (int)($table['zona_id'] ?? 0);
        if ($zoneId <= 0) {
            return null;
        }

        $waiter = Database::queryOne(
            "SELECT usuario_id
               FROM rest_mesero_turno
              WHERE restaurante_id = :restaurant_id
                AND zona_id = :zone_id
                AND turno_fecha = :date
                AND activo = 1
              ORDER BY id ASC
              LIMIT 1",
            [':restaurant_id' => $restaurantId, ':zone_id' => $zoneId, ':date' => $date]
        );

        $waiterId = (int)($waiter['usuario_id'] ?? 0);
        return $waiterId > 0 ? $waiterId : null;
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time . ':00' : $time;
    }

    private function isValidDate(string $date): bool
    {
        $parts = explode('-', $date);
        return count($parts) === 3 && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    }

    private function tableExists(string $tableName): bool
    {
        return !empty(Database::query("SHOW TABLES LIKE '{$tableName}'"));
    }

    private function getTableColumns(string $tableName): array
    {
        return array_values(array_map(
            static fn(array $column): string => (string)($column['Field'] ?? ''),
            Database::query("SHOW COLUMNS FROM `{$tableName}`")
        ));
    }

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
