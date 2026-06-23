<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;
use PDO;

final class DishModifier
{
    private static array $columns = [];

    public static function resolveRestaurantId(int $branchId): ?int
    {
        $restaurantColumns = self::columns('rest_restaurantes');
        $conditions = ['id = :internal_id'];
        $params = [':internal_id' => $branchId, ':priority_id' => $branchId];
        foreach (['sucursal_carnihub_id', 'sucursal_id'] as $column) {
            if (in_array($column, $restaurantColumns, true)) {
                $conditions[] = "`{$column}` = :{$column}";
                $params[":{$column}"] = $branchId;
            }
        }
        $statement = Database::getInstance()->prepare(
            'SELECT id FROM rest_restaurantes WHERE (' . implode(' OR ', $conditions) . ')
             ORDER BY (id = :priority_id) DESC LIMIT 1'
        );
        $statement->execute($params);
        $row = $statement->fetch();
        return $row ? (int)$row['id'] : null;
    }

    public static function dishBelongsToRestaurant(int $dishId, int $restaurantId): bool
    {
        $statement = Database::getInstance()->prepare(
            'SELECT id FROM rest_platillos
              WHERE id = :dish_id AND restaurante_id = :restaurant_id LIMIT 1'
        );
        $statement->execute([':dish_id' => $dishId, ':restaurant_id' => $restaurantId]);
        return (bool)$statement->fetch();
    }

    public static function getByDish(int $restaurantId, int $dishId): array
    {
        $modifierColumns = self::columns('rest_modificadores');
        $ingredientJoin = in_array('ingrediente_id', $modifierColumns, true)
            ? 'LEFT JOIN rest_ingredientes i ON i.id = m.ingrediente_id'
            : '';
        $ingredientName = $ingredientJoin ? ', i.nombre AS ingrediente_nombre' : ', NULL AS ingrediente_nombre';
        $ingredientColumns = $ingredientJoin ? self::columns('rest_ingredientes') : [];
        $unitColumn = in_array('unidad_principal', $ingredientColumns, true)
            ? 'i.unidad_principal'
            : (in_array('unidad', $ingredientColumns, true) ? 'i.unidad' : 'NULL');

        $statement = Database::getInstance()->prepare(
            "SELECT m.*, pm.max_seleccion, pm.obligatorio{$ingredientName},
                    {$unitColumn} AS ingrediente_unidad
               FROM rest_platillo_modificador pm
               JOIN rest_modificadores m ON m.id = pm.modificador_id
               {$ingredientJoin}
              WHERE pm.platillo_id = :dish_id
                AND m.restaurante_id = :restaurant_id
                AND m.activo = 1
           ORDER BY FIELD(m.tipo, 'sin', 'extra', 'opcion'), m.nombre"
        );
        $statement->execute([':dish_id' => $dishId, ':restaurant_id' => $restaurantId]);
        return array_map([self::class, 'normalize'], $statement->fetchAll());
    }

    public static function getByRestaurant(int $restaurantId): array
    {
        $statement = Database::getInstance()->prepare(
            'SELECT DISTINCT pm.platillo_id
               FROM rest_platillo_modificador pm
               JOIN rest_platillos p ON p.id = pm.platillo_id
              WHERE p.restaurante_id = :restaurant_id AND p.activo = 1
           ORDER BY pm.platillo_id'
        );
        $statement->execute([':restaurant_id' => $restaurantId]);
        $catalog = [];
        foreach ($statement->fetchAll(PDO::FETCH_COLUMN) as $dishId) {
            $catalog[(string)(int)$dishId] = self::getByDish($restaurantId, (int)$dishId);
        }
        return $catalog;
    }

    public static function buildSelector(array $modifiers, bool $exclusionsEnabled = true, bool $extrasEnabled = true): array
    {
        $included = [];
        $extras = [];
        foreach ($modifiers as $modifier) {
            if ($modifier['tipo'] === 'exclusion' && $exclusionsEnabled && $modifier['visible']) {
                $included[] = [
                    'id' => $modifier['id'],
                    'tipo' => 'exclusion',
                    'nombre' => $modifier['nombre'],
                    'incluida' => $modifier['incluida'],
                    'visible' => $modifier['visible'],
                    'puede_omitirse' => $modifier['puede_omitirse'],
                    'omitida_por_defecto' => $modifier['omitida_por_defecto'],
                    'seleccionada_por_defecto' => $modifier['seleccionada_por_defecto'],
                    'accion_al_desmarcar' => $modifier['accion_al_desmarcar'],
                ];
            } elseif ($modifier['tipo'] === 'extra' && $extrasEnabled && $modifier['visible']) {
                $extras[] = [
                    'id' => $modifier['id'],
                    'tipo' => 'extra',
                    'nombre' => $modifier['nombre'],
                    'precio_unitario' => $modifier['precio_unitario'],
                    'cantidad_inicial' => $modifier['cantidad_inicial'],
                    'max_cantidad' => $modifier['max_cantidad'],
                ];
            }
        }
        return [
            'tipo' => 'personalizacion_platillo',
            'titulo' => 'Personaliza tu platillo',
            'visible' => !empty($included) || !empty($extras),
            'incluidas' => $included,
            'extras' => $extras,
        ];
    }

    public static function payload(int $restaurantId, int $dishId, bool $exclusionsEnabled = true, bool $extrasEnabled = true): array
    {
        $modifiers = self::getByDish($restaurantId, $dishId);
        return [
            'platillo_id' => $dishId,
            'modificadores' => $modifiers,
            'selector' => self::buildSelector($modifiers, $exclusionsEnabled, $extrasEnabled),
        ];
    }

    public static function sync(PDO $pdo, int $restaurantId, int $dishId, array $modifiers): int
    {
        if (empty($modifiers)) return 0;
        $count = 0;
        foreach ($modifiers as $input) {
            if (!is_array($input)) throw new \InvalidArgumentException('Cada modificador debe ser un objeto válido.');
            $modifierId = self::upsertModifier($pdo, $restaurantId, $input);
            $maxSelection = self::normalizeType((string)($input['tipo'] ?? '')) === 'sin'
                ? 1
                : max(1, (int)($input['max_seleccion'] ?? $input['max_cantidad'] ?? 1));
            self::attachToDish($pdo, $dishId, $modifierId, $maxSelection);
            if (self::value($input, 'alcance', 'platillo') === 'restaurante') {
                self::attachToAllDishes($pdo, $restaurantId, $modifierId, $maxSelection);
            }
            $count++;
        }
        return $count;
    }

    private static function upsertModifier(PDO $pdo, int $restaurantId, array $input): int
    {
        $type = self::normalizeType((string)($input['tipo'] ?? ''));
        $name = trim((string)($input['nombre'] ?? ''));
        if (!in_array($type, ['sin', 'extra', 'opcion'], true) || $name === '') {
            throw new \InvalidArgumentException('Cada modificador requiere tipo y nombre válidos.');
        }
        $ingredientId = !empty($input['ingrediente_id']) ? (int)$input['ingrediente_id'] : null;
        $scope = (string)self::value($input, 'alcance', 'platillo');
        if (!in_array($scope, ['platillo', 'restaurante'], true)) {
            throw new \InvalidArgumentException('El alcance debe ser platillo o restaurante.');
        }
        if ($ingredientId !== null && !self::ingredientBelongs($pdo, $ingredientId, $restaurantId)) {
            throw new \InvalidArgumentException('El ingrediente no pertenece al restaurante.');
        }
        $requestedId = (int)($input['id'] ?? 0);
        if ($requestedId > 0) {
            $statement = $pdo->prepare('SELECT id FROM rest_modificadores WHERE id = :id AND restaurante_id = :restaurant_id LIMIT 1');
            $statement->execute([':id' => $requestedId, ':restaurant_id' => $restaurantId]);
            if (!$statement->fetch()) throw new \InvalidArgumentException('El modificador no pertenece al restaurante.');
            self::updateModifier($pdo, $requestedId, $input, $type, $name, $ingredientId);
            return $requestedId;
        }

        $columns = self::columns('rest_modificadores');
        $conditions = ['restaurante_id = :restaurant_id', 'tipo = :type', 'nombre = :name'];
        $params = [':restaurant_id' => $restaurantId, ':type' => $type, ':name' => $name];
        if (in_array('ingrediente_id', $columns, true)) {
            if ($ingredientId === null) $conditions[] = 'ingrediente_id IS NULL';
            else { $conditions[] = 'ingrediente_id = :ingredient_id'; $params[':ingredient_id'] = $ingredientId; }
        }
        if (in_array('alcance', $columns, true)) {
            $conditions[] = 'alcance = :scope';
            $params[':scope'] = $scope;
        }
        $find = $pdo->prepare('SELECT id FROM rest_modificadores WHERE ' . implode(' AND ', $conditions) . ' LIMIT 1');
        $find->execute($params);
        $existing = $find->fetch();
        if ($existing) {
            self::updateModifier($pdo, (int)$existing['id'], $input, $type, $name, $ingredientId);
            return (int)$existing['id'];
        }

        $data = ['restaurante_id' => $restaurantId, 'nombre' => substr($name, 0, 120), 'tipo' => $type, 'activo' => 1];
        self::setIfColumn($data, $columns, 'ingrediente_id', $ingredientId);
        self::setIfColumn($data, $columns, 'alcance', $scope);
        self::setIfColumn($data, $columns, 'precio_extra', $type === 'sin' ? 0 : max(0, (float)($input['precio_extra'] ?? $input['precio_unitario'] ?? 0)));
        self::setIfColumn($data, $columns, 'cantidad_unidad', max(0, (float)($input['cantidad_unidad'] ?? 0)));
        self::setIfColumn($data, $columns, 'unidad', isset($input['unidad']) ? substr(trim((string)$input['unidad']), 0, 20) : null);
        self::setSelectorColumns($data, $columns, $input, $type, true);
        return self::insert($pdo, 'rest_modificadores', $data);
    }

    private static function updateModifier(PDO $pdo, int $id, array $input, string $type, string $name, ?int $ingredientId): void
    {
        $columns = self::columns('rest_modificadores');
        $data = ['nombre' => substr($name, 0, 120), 'tipo' => $type, 'activo' => 1];
        self::setIfColumn($data, $columns, 'ingrediente_id', $ingredientId);
        self::setIfColumn($data, $columns, 'alcance', self::value($input, 'alcance', 'platillo'));
        self::setIfColumn($data, $columns, 'precio_extra', $type === 'sin' ? 0 : max(0, (float)($input['precio_extra'] ?? $input['precio_unitario'] ?? 0)));
        self::setIfColumn($data, $columns, 'cantidad_unidad', max(0, (float)($input['cantidad_unidad'] ?? 0)));
        self::setIfColumn($data, $columns, 'unidad', isset($input['unidad']) ? substr(trim((string)$input['unidad']), 0, 20) : null);
        self::setSelectorColumns($data, $columns, $input, $type, false);
        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $column => $value) { $sets[] = "`{$column}` = :{$column}"; $params[":{$column}"] = $value; }
        $statement = $pdo->prepare('UPDATE rest_modificadores SET ' . implode(', ', $sets) . ' WHERE id = :id');
        $statement->execute($params);
    }

    private static function attachToDish(PDO $pdo, int $dishId, int $modifierId, int $maxSelection): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO rest_platillo_modificador (platillo_id, modificador_id, obligatorio, max_seleccion)
             VALUES (:dish_id, :modifier_id, 0, :max_selection)
             ON DUPLICATE KEY UPDATE max_seleccion = VALUES(max_seleccion), obligatorio = 0'
        );
        $statement->execute([':dish_id' => $dishId, ':modifier_id' => $modifierId, ':max_selection' => $maxSelection]);
    }

    private static function attachToAllDishes(PDO $pdo, int $restaurantId, int $modifierId, int $maxSelection): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO rest_platillo_modificador (platillo_id, modificador_id, obligatorio, max_seleccion)
             SELECT id, :modifier_id, 0, :max_selection FROM rest_platillos
              WHERE restaurante_id = :restaurant_id AND activo = 1
             ON DUPLICATE KEY UPDATE max_seleccion = VALUES(max_seleccion), obligatorio = 0'
        );
        $statement->execute([':modifier_id' => $modifierId, ':max_selection' => $maxSelection, ':restaurant_id' => $restaurantId]);
    }

    private static function ingredientBelongs(PDO $pdo, int $ingredientId, int $restaurantId): bool
    {
        $columns = self::columns('rest_ingredientes');
        if (!in_array('restaurante_id', $columns, true)) return false;
        $statement = $pdo->prepare('SELECT id FROM rest_ingredientes WHERE id = :id AND restaurante_id = :restaurant_id LIMIT 1');
        $statement->execute([':id' => $ingredientId, ':restaurant_id' => $restaurantId]);
        return (bool)$statement->fetch();
    }

    private static function normalize(array $row): array
    {
        $type = (string)($row['tipo'] ?? 'opcion');
        return [
            'id' => (int)$row['id'],
            'tipo' => $type === 'sin' ? 'exclusion' : $type,
            'nombre' => (string)$row['nombre'],
            'ingrediente_id' => isset($row['ingrediente_id']) ? (int)$row['ingrediente_id'] : null,
            'ingrediente_nombre' => $row['ingrediente_nombre'] ?? null,
            'cantidad_unidad' => (float)($row['cantidad_unidad'] ?? 0),
            'unidad' => $row['unidad'] ?? $row['ingrediente_unidad'] ?? null,
            'precio_unitario' => $type === 'sin' ? 0.0 : (float)($row['precio_extra'] ?? 0),
            'max_cantidad' => $type === 'sin' ? 1 : max(1, (int)($row['max_seleccion'] ?? 1)),
            'alcance' => $row['alcance'] ?? 'platillo',
            'visible' => (bool)($row['visible'] ?? true),
            'incluida' => (bool)($row['incluida'] ?? ($type === 'sin')),
            'puede_omitirse' => (bool)($row['puede_omitirse'] ?? true),
            'omitida_por_defecto' => (bool)($row['omitida_por_defecto'] ?? false),
            'seleccionada_por_defecto' => (bool)($row['seleccionada_por_defecto'] ?? !($row['omitida_por_defecto'] ?? false)),
            'accion_al_desmarcar' => (string)($row['accion_al_desmarcar'] ?? 'enviar_exclusion'),
            'cantidad_inicial' => max(0, (int)($row['cantidad_inicial'] ?? 0)),
        ];
    }

    private static function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));
        return $type === 'exclusion' ? 'sin' : $type;
    }

    private static function columns(string $table): array
    {
        if (!isset(self::$columns[$table])) {
            $statement = Database::getInstance()->query("SHOW COLUMNS FROM `{$table}`");
            self::$columns[$table] = $statement->fetchAll(PDO::FETCH_COLUMN, 0);
        }
        return self::$columns[$table];
    }

    private static function setIfColumn(array &$data, array $columns, string $column, mixed $value): void
    {
        if (in_array($column, $columns, true)) $data[$column] = $value;
    }

    private static function setSelectorColumns(array &$data, array $columns, array $input, string $type, bool $withDefaults): void
    {
        $defaults = [
            'visible' => 1,
            'incluida' => $type === 'sin' ? 1 : 0,
            'puede_omitirse' => 1,
            'omitida_por_defecto' => 0,
            'seleccionada_por_defecto' => $type === 'sin' ? 1 : 0,
            'accion_al_desmarcar' => 'enviar_exclusion',
            'cantidad_inicial' => 0,
        ];
        foreach ($defaults as $column => $default) {
            if ($withDefaults || array_key_exists($column, $input)) {
                self::setIfColumn($data, $columns, $column, $input[$column] ?? $default);
            }
        }
    }

    private static function insert(PDO $pdo, string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $statement = $pdo->prepare(
            'INSERT INTO `' . $table . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', $placeholders) . ')'
        );
        $params = [];
        foreach ($data as $column => $value) $params[':' . $column] = $value;
        $statement->execute($params);
        return (int)$pdo->lastInsertId();
    }

    private static function value(array $input, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $input) ? $input[$key] : $default;
    }
}
