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
                'activo' => true,
            ];
        }

        // Decodificar campos JSON
        $config['metodos_pago'] = json_decode($config['metodos_pago'], true) ?: ['card', 'cash'];
        $config['tipos_entrega'] = json_decode($config['tipos_entrega'], true) ?: ['delivery', 'pickup'];
        $config['costo_envio'] = (float) $config['costo_envio'];
        $config['pedido_minimo'] = (float) $config['pedido_minimo'];
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
            // INSERT con defaults
            $defaultMetodos = '["card","cash"]';
            $defaultTipos = '["delivery","pickup"]';

            $sql = "INSERT INTO rest_configuracion (restaurante_id, metodos_pago, tipos_entrega"
                 . (!empty($fields) ? ', ' . implode(', ', array_map(fn($f) => explode(' = ', $f)[0], $fields)) : '')
                 . ") VALUES (:restaurante_id, '{$defaultMetodos}', '{$defaultTipos}'";

            // Agregar valores para campos extra
            foreach ($fields as $field) {
                $colName = explode(' = ', $field)[0];
                $placeholder = ':' . $colName;
                if (!isset($params[$placeholder])) {
                    $params[$placeholder] = null;
                }
                $sql .= ", {$placeholder}";
            }
            $sql .= ")";
        }

        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
}