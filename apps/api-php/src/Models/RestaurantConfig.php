<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;
use PDO;

class RestaurantConfig
{
    /**
     * Obtiene la configuración de un restaurante por su ID.
     * Si no existe registro, devuelve valores por defecto.
     */
    public static function getByRestaurant(int $restauranteId): array
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            "SELECT * FROM rest_configuracion WHERE restaurante_id = :restaurante_id"
        );
        $stmt->execute([':restaurante_id' => $restauranteId]);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$config) {
            // Valores por defecto si no hay configuración guardada
            return [
                'restaurante_id' => $restauranteId,
                'metodos_pago' => ['card', 'cash'],
                'tipos_entrega' => ['delivery', 'pickup'],
                'costo_envio' => 0,
                'pedido_minimo' => 0,
                'version' => 0,
                'updated_at' => null,
                'modificadores' => [
                    'exclusiones_habilitadas' => true,
                    'extras_habilitados' => true,
                ],
                'platillos_modificadores' => [],
                'selector' => [
                    'exclusiones' => true,
                    'extras' => true,
                ],
                'activo' => true,
            ];
        }

        // Decodificar campos JSON
        $config['metodos_pago'] = json_decode($config['metodos_pago'], true) ?: ['card', 'cash'];
        $config['tipos_entrega'] = json_decode($config['tipos_entrega'], true) ?: ['delivery', 'pickup'];
        $config['costo_envio'] = (float) $config['costo_envio'];
        $config['pedido_minimo'] = (float) $config['pedido_minimo'];
        $config['version'] = (int)($config['config_version'] ?? 0);
        $config['updated_at'] = !empty($config['updated_at'])
            ? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$config['updated_at']))
            : null;
        $config['modificadores'] = [
            'exclusiones_habilitadas' => (bool)($config['exclusiones_habilitadas'] ?? true),
            'extras_habilitados' => (bool)($config['extras_habilitados'] ?? true),
        ];
        $config['selector'] = [
            'exclusiones' => $config['modificadores']['exclusiones_habilitadas'],
            'extras' => $config['modificadores']['extras_habilitados'],
        ];
        $config['platillos_modificadores'] = self::getDishModifiers(
            $restauranteId,
            $config['modificadores']['exclusiones_habilitadas'],
            $config['modificadores']['extras_habilitados']
        );
        unset($config['config_version'], $config['exclusiones_habilitadas'], $config['extras_habilitados']);
        $config['activo'] = (bool) $config['activo'];

        return $config;
    }

    /**
     * Crea o actualiza la configuración de un restaurante.
     * Solo los campos enviados se actualizan (partial update).
     */
    public static function upsert(int $restauranteId, array $data): bool
    {
        $pdo = Database::getInstance();

        // Verificar si ya existe
        $stmt = $pdo->prepare(
            "SELECT id FROM rest_configuracion WHERE restaurante_id = :restaurante_id"
        );
        $stmt->execute([':restaurante_id' => $restauranteId]);
        $exists = $stmt->fetch();

        // Preparar campos a actualizar
        $fields = [];
        $params = [':restaurante_id' => $restauranteId];

        if (isset($data['metodos_pago']) && is_array($data['metodos_pago'])) {
            $fields[] = "metodos_pago = :metodos_pago";
            $params[':metodos_pago'] = json_encode($data['metodos_pago'], JSON_UNESCAPED_UNICODE);
        }

        if (isset($data['tipos_entrega']) && is_array($data['tipos_entrega'])) {
            $fields[] = "tipos_entrega = :tipos_entrega";
            $params[':tipos_entrega'] = json_encode($data['tipos_entrega'], JSON_UNESCAPED_UNICODE);
        }

        if (isset($data['costo_envio'])) {
            $fields[] = "costo_envio = :costo_envio";
            $params[':costo_envio'] = $data['costo_envio'];
        }

        if (isset($data['pedido_minimo'])) {
            $fields[] = "pedido_minimo = :pedido_minimo";
            $params[':pedido_minimo'] = $data['pedido_minimo'];
        }

        if (array_key_exists('exclusiones_habilitadas', $data)) {
            $fields[] = "exclusiones_habilitadas = :exclusiones_habilitadas";
            $params[':exclusiones_habilitadas'] = (int)$data['exclusiones_habilitadas'];
        }

        if (array_key_exists('extras_habilitados', $data)) {
            $fields[] = "extras_habilitados = :extras_habilitados";
            $params[':extras_habilitados'] = (int)$data['extras_habilitados'];
        }

        if (isset($data['activo'])) {
            $fields[] = "activo = :activo";
            $params[':activo'] = (int) $data['activo'];
        }

        if (empty($fields)) {
            return false; // Nada que actualizar
        }

        if ($exists) {
            // UPDATE
            $sql = "UPDATE rest_configuracion SET " . implode(', ', $fields)
                 . " WHERE restaurante_id = :restaurante_id";
        } else {
            // INSERT dinamico sin repetir metodos_pago/tipos_entrega cuando vienen en el PUT.
            $insertColumns = ['restaurante_id'];
            $insertPlaceholders = [':restaurante_id'];
            foreach ($fields as $field) {
                $colName = explode(' = ', $field)[0];
                $placeholder = ':' . $colName;
                $insertColumns[] = $colName;
                $insertPlaceholders[] = $placeholder;
            }
            if (!in_array('metodos_pago', $insertColumns, true)) {
                $insertColumns[] = 'metodos_pago';
                $insertPlaceholders[] = ':default_metodos_pago';
                $params[':default_metodos_pago'] = '["card","cash"]';
            }
            if (!in_array('tipos_entrega', $insertColumns, true)) {
                $insertColumns[] = 'tipos_entrega';
                $insertPlaceholders[] = ':default_tipos_entrega';
                $params[':default_tipos_entrega'] = '["delivery","pickup"]';
            }
            $sql = 'INSERT INTO rest_configuracion (' . implode(', ', $insertColumns)
                 . ') VALUES (' . implode(', ', $insertPlaceholders) . ')';
        }

        $stmt = $pdo->prepare($sql);
        $success = $stmt->execute($params);
        if ($success && $exists) {
            $versionStmt = $pdo->prepare(
                'UPDATE rest_configuracion
                    SET config_version = config_version + 1, updated_at = NOW()
                  WHERE restaurante_id = :restaurante_id'
            );
            $versionStmt->execute([':restaurante_id' => $restauranteId]);
        }
        return $success;
    }

    private static function getDishModifiers(int $restauranteId, bool $exclusionsEnabled, bool $extrasEnabled): array
    {
        try {
            $rows = BranchMenuModifier::forBranch($restauranteId);
        } catch (\Throwable $exception) {
            return [];
        }

        $byDish = [];
        foreach ($rows as $row) {
            $dishId = (string)(int)$row['platillo_id'];
            $byDish[$dishId][] = $row;
        }
        $catalog = [];
        foreach ($byDish as $dishId => $modifiers) {
            $catalog[$dishId] = [
                'modificadores' => $modifiers,
                'selector' => BranchMenuModifier::selector($modifiers, $exclusionsEnabled, $extrasEnabled),
            ];
        }
        return $catalog;
    }

    public static function incrementVersion(int $restauranteId): void
    {
        Database::rowCount(
            'UPDATE rest_configuracion
                SET config_version = config_version + 1, updated_at = NOW()
              WHERE restaurante_id = :restaurante_id',
            [':restaurante_id' => $restauranteId]
        );
    }
}
