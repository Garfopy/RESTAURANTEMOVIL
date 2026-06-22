<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;
use PDO;

final class BranchMenuModifier
{
    private const TABLE = 'amare_branch_menu_modifiers';
    private static ?array $columns = null;

    public static function tableExists(): bool
    {
        return !empty(Database::query("SHOW TABLES LIKE '" . self::TABLE . "'"));
    }

    public static function forBranch(int $branchId): array
    {
        if (!self::tableExists()) return [];
        $branchColumn = self::column(['branch_id', 'restaurante_id', 'restaurant_id', 'sucursal_id']);
        if (!$branchColumn) return [];
        $rows = Database::query(
            'SELECT * FROM `' . self::TABLE . '` WHERE `' . $branchColumn . '` = :branch_id',
            [':branch_id' => $branchId]
        );
        return self::normalizeRows($rows);
    }

    public static function forDish(int $branchId, int $dishId): array
    {
        return array_values(array_filter(
            self::forBranch($branchId),
            static fn(array $modifier): bool => (int)$modifier['platillo_id'] === $dishId
        ));
    }

    public static function replaceDish(PDO $pdo, int $branchId, int $dishId, array $modifiers, ?array $selector = null): void
    {
        if (!self::tableExists()) {
            throw new \RuntimeException('Falta la tabla amare_branch_menu_modifiers. Ejecuta la migracion 071.');
        }
        $branchColumn = self::column(['branch_id', 'restaurante_id', 'restaurant_id', 'sucursal_id']);
        $dishColumn = self::column(['menu_item_id', 'platillo_id', 'dish_id']);
        if (!$branchColumn || !$dishColumn) {
            throw new \RuntimeException('La tabla amare_branch_menu_modifiers no tiene columnas de sucursal y platillo compatibles.');
        }

        $delete = $pdo->prepare(
            'DELETE FROM `' . self::TABLE . '` WHERE `' . $branchColumn . '` = :branch_id AND `' . $dishColumn . '` = :dish_id'
        );
        $delete->execute([':branch_id' => $branchId, ':dish_id' => $dishId]);

        $jsonModifiersColumn = self::column(['modificadores_json', 'modificadores']);
        if ($jsonModifiersColumn) {
            $data = [
                $branchColumn => $branchId,
                $dishColumn => $dishId,
                $jsonModifiersColumn => json_encode($modifiers, JSON_UNESCAPED_UNICODE),
            ];
            $selectorColumn = self::column(['selector_json', 'selector']);
            if ($selectorColumn) $data[$selectorColumn] = json_encode($selector ?? [], JSON_UNESCAPED_UNICODE);
            self::insert($pdo, $data);
            return;
        }

        foreach ($modifiers as $modifier) {
            $data = [$branchColumn => $branchId, $dishColumn => $dishId];
            self::put($data, ['modificador_id', 'modifier_id', 'id'], $modifier['id']);
            self::put($data, ['tipo', 'type'], $modifier['tipo']);
            self::put($data, ['nombre', 'name'], $modifier['nombre']);
            self::put($data, ['ingrediente_id', 'ingredient_id'], $modifier['ingrediente_id']);
            self::put($data, ['cantidad_unidad', 'unit_quantity'], $modifier['cantidad_unidad']);
            self::put($data, ['unidad', 'unit'], $modifier['unidad']);
            self::put($data, ['precio_unitario', 'unit_price'], $modifier['precio_unitario']);
            self::put($data, ['max_cantidad', 'max_quantity'], $modifier['max_cantidad']);
            self::put($data, ['activo', 'active'], 1);

            $selectorItem = self::findSelectorItem($selector, (int)$modifier['id']);
            if ($selectorItem) {
                foreach ([
                    'incluida' => ['incluida', 'included'],
                    'visible' => ['visible'],
                    'puede_omitirse' => ['puede_omitirse', 'can_omit'],
                    'omitida_por_defecto' => ['omitida_por_defecto', 'omitted_by_default'],
                    'seleccionada_por_defecto' => ['seleccionada_por_defecto', 'selected_by_default'],
                    'accion_al_desmarcar' => ['accion_al_desmarcar', 'uncheck_action'],
                    'cantidad_inicial' => ['cantidad_inicial', 'initial_quantity'],
                ] as $source => $aliases) {
                    if (array_key_exists($source, $selectorItem)) self::put($data, $aliases, $selectorItem[$source]);
                }
            }
            self::insert($pdo, $data);
        }
    }

    public static function selector(array $modifiers, bool $exclusionsEnabled = true, bool $extrasEnabled = true): array
    {
        $included = [];
        $extras = [];
        foreach ($modifiers as $modifier) {
            if ($modifier['tipo'] === 'exclusion' && $exclusionsEnabled && ($modifier['visible'] ?? true)) {
                $included[] = [
                    'id' => (int)$modifier['id'],
                    'tipo' => 'exclusion',
                    'nombre' => (string)$modifier['nombre'],
                    'incluida' => (bool)($modifier['incluida'] ?? true),
                    'visible' => (bool)($modifier['visible'] ?? true),
                    'puede_omitirse' => (bool)($modifier['puede_omitirse'] ?? true),
                    'omitida_por_defecto' => (bool)($modifier['omitida_por_defecto'] ?? false),
                    'seleccionada_por_defecto' => (bool)($modifier['seleccionada_por_defecto'] ?? true),
                    'accion_al_desmarcar' => (string)($modifier['accion_al_desmarcar'] ?? 'enviar_exclusion'),
                ];
            } elseif ($modifier['tipo'] === 'extra' && $extrasEnabled && ($modifier['visible'] ?? true)) {
                $extras[] = [
                    'id' => (int)$modifier['id'],
                    'tipo' => 'extra',
                    'nombre' => (string)$modifier['nombre'],
                    'precio_unitario' => (float)$modifier['precio_unitario'],
                    'cantidad_inicial' => max(0, (int)($modifier['cantidad_inicial'] ?? 0)),
                    'max_cantidad' => max(1, (int)$modifier['max_cantidad']),
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

    public static function payloadForDish(int $branchId, int $dishId, bool $exclusionsEnabled = true, bool $extrasEnabled = true): array
    {
        $modifiers = self::forDish($branchId, $dishId);
        return [
            'platillo_id' => $dishId,
            'modificadores' => $modifiers,
            'selector' => self::selector($modifiers, $exclusionsEnabled, $extrasEnabled),
        ];
    }

    private static function normalizeRows(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $json = self::value($row, ['modificadores_json', 'modificadores']);
            if (is_string($json) && $json !== '') {
                $decoded = json_decode($json, true);
                foreach (is_array($decoded) ? $decoded : [] as $modifier) {
                    if (is_array($modifier)) $result[] = self::normalize($modifier + $row);
                }
                continue;
            }
            $result[] = self::normalize($row);
        }
        return array_values(array_filter($result, static fn(array $row): bool => $row['id'] > 0));
    }

    private static function normalize(array $row): array
    {
        $type = strtolower((string)self::value($row, ['tipo', 'type'], 'extra'));
        return [
            'id' => (int)self::value($row, ['modificador_id', 'modifier_id', 'id'], 0),
            'platillo_id' => (int)self::value($row, ['menu_item_id', 'platillo_id', 'dish_id'], 0),
            'tipo' => $type === 'exclusion' ? 'exclusion' : 'extra',
            'nombre' => (string)self::value($row, ['nombre', 'name'], ''),
            'ingrediente_id' => self::nullableInt(self::value($row, ['ingrediente_id', 'ingredient_id'])),
            'cantidad_unidad' => (float)self::value($row, ['cantidad_unidad', 'unit_quantity'], 0),
            'unidad' => self::value($row, ['unidad', 'unit']),
            'precio_unitario' => $type === 'exclusion' ? 0.0 : (float)self::value($row, ['precio_unitario', 'unit_price'], 0),
            'max_cantidad' => $type === 'exclusion' ? 1 : max(1, (int)self::value($row, ['max_cantidad', 'max_quantity'], 1)),
            'incluida' => (bool)self::value($row, ['incluida', 'included'], $type === 'exclusion'),
            'visible' => (bool)self::value($row, ['visible'], true),
            'puede_omitirse' => (bool)self::value($row, ['puede_omitirse', 'can_omit'], true),
            'omitida_por_defecto' => (bool)self::value($row, ['omitida_por_defecto', 'omitted_by_default'], false),
            'seleccionada_por_defecto' => (bool)self::value($row, ['seleccionada_por_defecto', 'selected_by_default'], true),
            'accion_al_desmarcar' => (string)self::value($row, ['accion_al_desmarcar', 'uncheck_action'], 'enviar_exclusion'),
            'cantidad_inicial' => max(0, (int)self::value($row, ['cantidad_inicial', 'initial_quantity'], 0)),
        ];
    }

    private static function columns(): array
    {
        if (self::$columns === null) {
            self::$columns = Database::query('SHOW COLUMNS FROM `' . self::TABLE . '`');
            self::$columns = array_map(static fn(array $row): string => (string)$row['Field'], self::$columns);
        }
        return self::$columns;
    }

    private static function column(array $aliases): ?string
    {
        foreach ($aliases as $alias) if (in_array($alias, self::columns(), true)) return $alias;
        return null;
    }

    private static function put(array &$data, array $aliases, mixed $value): void
    {
        $column = self::column($aliases);
        if ($column) $data[$column] = $value;
    }

    private static function insert(PDO $pdo, array $data): void
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);
        $statement = $pdo->prepare(
            'INSERT INTO `' . self::TABLE . '` (`' . implode('`,`', $columns) . '`) VALUES (' . implode(',', $placeholders) . ')'
        );
        $params = [];
        foreach ($data as $column => $value) $params[':' . $column] = $value;
        $statement->execute($params);
    }

    private static function findSelectorItem(?array $selector, int $id): ?array
    {
        foreach (array_merge($selector['incluidas'] ?? [], $selector['extras'] ?? []) as $item) {
            if ((int)($item['id'] ?? 0) === $id) return $item;
        }
        return null;
    }

    private static function value(array $row, array $aliases, mixed $default = null): mixed
    {
        foreach ($aliases as $alias) if (array_key_exists($alias, $row)) return $row[$alias];
        return $default;
    }

    private static function nullableInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int)$value;
    }
}
