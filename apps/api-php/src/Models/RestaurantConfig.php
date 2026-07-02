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
                'platillos_modificadores' => self::getDishModifiers($restauranteId, true, true),
                'selector' => [
                    'exclusiones' => true,
                    'extras' => true,
                ],
                'facturacion' => [
                    'habilitada' => false,
                    'modo' => 'solicitud',
                    'emisor_configurado' => false,
                    'emisor' => null,
                    'email_notificacion' => null,
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
        $emisor = [];
        if (!empty($config['facturacion_emisor_json'])) {
            $decoded = json_decode((string)$config['facturacion_emisor_json'], true);
            $emisor = is_array($decoded) ? $decoded : [];
        }
        $config['facturacion'] = [
            'habilitada' => (bool)($config['facturacion_habilitada'] ?? false),
            'modo' => 'solicitud',
            'emisor_configurado' => !empty($emisor['rfc'])
                && !empty($emisor['nombre_fiscal'])
                && !empty($emisor['regimen_fiscal'])
                && !empty($emisor['codigo_postal']),
            'emisor' => !empty($emisor) ? $emisor : null,
            'email_notificacion' => $config['facturacion_email_notificacion'] ?? null,
        ];
        $config['platillos_modificadores'] = self::getDishModifiers(
            $restauranteId,
            $config['modificadores']['exclusiones_habilitadas'],
            $config['modificadores']['extras_habilitados']
        );
        unset(
            $config['config_version'],
            $config['exclusiones_habilitadas'],
            $config['extras_habilitados'],
            $config['facturacion_habilitada'],
            $config['facturacion_emisor_json'],
            $config['facturacion_email_notificacion']
        );
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

        if (array_key_exists('facturacion_habilitada', $data)) {
            $fields[] = 'facturacion_habilitada = :facturacion_habilitada';
            $params[':facturacion_habilitada'] = (int)$data['facturacion_habilitada'];
        }

        if (array_key_exists('facturacion_emisor', $data)) {
            $fields[] = 'facturacion_emisor_json = :facturacion_emisor_json';
            $params[':facturacion_emisor_json'] = $data['facturacion_emisor'] !== null
                ? json_encode($data['facturacion_emisor'], JSON_UNESCAPED_UNICODE)
                : null;
        }

        if (array_key_exists('facturacion_email_notificacion', $data)) {
            $fields[] = 'facturacion_email_notificacion = :facturacion_email_notificacion';
            $params[':facturacion_email_notificacion'] = $data['facturacion_email_notificacion'];
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
            $rows = DishModifier::getByRestaurant($restauranteId);
        } catch (\Throwable $exception) {
            return [];
        }

        $catalog = [];
        foreach ($rows as $dishId => $modifiers) {
            $catalog[$dishId] = [
                'modificadores' => $modifiers,
                'selector' => DishModifier::buildSelector($modifiers, $exclusionsEnabled, $extrasEnabled),
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
