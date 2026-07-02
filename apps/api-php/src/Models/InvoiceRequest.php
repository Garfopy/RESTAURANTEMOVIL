<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;

class InvoiceRequest
{
    public static function createFromPayment(array $context, ?array $invoiceRequest): ?array
    {
        if (!self::isRequired($invoiceRequest)) {
            return null;
        }

        $restaurantId = (int)($context['restaurante_id'] ?? 0);
        if ($restaurantId <= 0) {
            throw new \InvalidArgumentException('No se detecto la sucursal para facturar.');
        }

        $config = RestaurantConfig::getByRestaurant($restaurantId);
        if (empty($config['facturacion']['habilitada'])) {
            throw new \InvalidArgumentException('Esta sucursal no tiene facturacion activa.');
        }

        $fiscalData = self::extractFiscalData($invoiceRequest);
        $userId = isset($context['mobile_usuario_id']) ? (int)$context['mobile_usuario_id'] : 0;
        if (!empty($invoiceRequest['save_to_profile']) && $userId > 0) {
            FiscalData::upsert($userId, $fiscalData);
        }

        $id = Database::execute(
            'INSERT INTO facturacion_solicitudes
                (restaurante_id, pedido_id, consumo_id, mesa_id, division_id, division_cuenta_id,
                 mobile_usuario_id, solicitado_por_usuario_id, origen, scope, monto, metodo_pago,
                 receptor_rfc, receptor_nombre, receptor_regimen_fiscal, receptor_codigo_postal,
                 uso_cfdi, receptor_email)
             VALUES
                (:restaurant_id, :order_id, :consumo_id, :table_id, :split_id, :split_account_id,
                 :mobile_user_id, :requested_by_user_id, :origin, :scope, :amount, :payment_method,
                 :rfc, :name, :regime, :postal_code, :cfdi_use, :email)',
            [
                ':restaurant_id' => $restaurantId,
                ':order_id' => self::nullableInt($context['pedido_id'] ?? null),
                ':consumo_id' => self::nullableString($context['consumo_id'] ?? null),
                ':table_id' => self::nullableInt($context['mesa_id'] ?? null),
                ':split_id' => self::nullableInt($context['division_id'] ?? null),
                ':split_account_id' => self::nullableInt($context['division_cuenta_id'] ?? null),
                ':mobile_user_id' => self::nullableInt($context['mobile_usuario_id'] ?? null),
                ':requested_by_user_id' => self::nullableInt($context['solicitado_por_usuario_id'] ?? null),
                ':origin' => (string)($context['origen'] ?? 'cliente'),
                ':scope' => (string)($context['scope'] ?? 'pedido'),
                ':amount' => round((float)($context['monto'] ?? 0), 2),
                ':payment_method' => self::nullableString($context['metodo_pago'] ?? null),
                ':rfc' => $fiscalData['rfc'],
                ':name' => $fiscalData['nombre_fiscal'],
                ':regime' => $fiscalData['regimen_fiscal'],
                ':postal_code' => $fiscalData['codigo_postal'],
                ':cfdi_use' => $fiscalData['uso_cfdi'],
                ':email' => $fiscalData['email'],
            ]
        );

        return $id > 0 ? self::findById($id) : null;
    }

    public static function isRequired(?array $invoiceRequest): bool
    {
        return is_array($invoiceRequest) && !empty($invoiceRequest['required']);
    }

    public static function extractFiscalData(array $invoiceRequest): array
    {
        $source = $invoiceRequest['receptor'] ?? $invoiceRequest['fiscal_data'] ?? $invoiceRequest;
        if (!is_array($source)) {
            throw new \InvalidArgumentException('Los datos fiscales no son validos.');
        }

        return FiscalData::validate($source);
    }

    public static function validateForPayment(int $restaurantId, ?array $invoiceRequest): void
    {
        if (!self::isRequired($invoiceRequest)) {
            return;
        }

        $config = RestaurantConfig::getByRestaurant($restaurantId);
        if (empty($config['facturacion']['habilitada'])) {
            throw new \InvalidArgumentException('Esta sucursal no tiene facturacion activa.');
        }

        self::extractFiscalData($invoiceRequest);
    }

    public static function findById(int $id): ?array
    {
        $row = Database::queryOne(
            'SELECT fs.*, r.nombre AS restaurante_nombre
               FROM facturacion_solicitudes fs
          LEFT JOIN rest_restaurantes r ON r.id = fs.restaurante_id
              WHERE fs.id = :id',
            [':id' => $id]
        );

        return $row ? self::normalize($row) : null;
    }

    public static function listForAdmin(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $sql = 'SELECT fs.*, r.nombre AS restaurante_nombre
                  FROM facturacion_solicitudes fs
             LEFT JOIN rest_restaurantes r ON r.id = fs.restaurante_id
                 WHERE 1 = 1';
        $params = [];

        if (!empty($filters['restaurante_id'])) {
            $sql .= ' AND fs.restaurante_id = :restaurant_id';
            $params[':restaurant_id'] = (int)$filters['restaurante_id'];
        }
        if (!empty($filters['estado'])) {
            $sql .= ' AND fs.estado = :status';
            $params[':status'] = (string)$filters['estado'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(fs.created_at) >= :from';
            $params[':from'] = (string)$filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(fs.created_at) <= :to';
            $params[':to'] = (string)$filters['to'];
        }

        $sql .= ' ORDER BY fs.created_at DESC LIMIT :limit OFFSET :offset';
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return array_map([self::class, 'normalize'], $stmt->fetchAll());
    }

    public static function updateAdmin(int $id, array $data): ?array
    {
        $allowedStatuses = ['pendiente', 'en_proceso', 'facturada', 'cancelada'];
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['estado'])) {
            $status = (string)$data['estado'];
            if (!in_array($status, $allowedStatuses, true)) {
                throw new \InvalidArgumentException('Estado de solicitud no valido.');
            }
            $fields[] = 'estado = :status';
            $params[':status'] = $status;
            $fields[] = $status === 'facturada' ? 'facturada_at = COALESCE(facturada_at, NOW())' : 'facturada_at = NULL';
        }

        foreach (['cfdi_uuid', 'pdf_url', 'xml_url', 'notas'] as $column) {
            if (array_key_exists($column, $data)) {
                $fields[] = "{$column} = :{$column}";
                $params[":{$column}"] = trim((string)($data[$column] ?? '')) ?: null;
            }
        }

        if (empty($fields)) {
            return self::findById($id);
        }

        $fields[] = 'updated_at = NOW()';
        Database::rowCount(
            'UPDATE facturacion_solicitudes SET ' . implode(', ', $fields) . ' WHERE id = :id',
            $params
        );

        return self::findById($id);
    }

    public static function normalize(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'restaurante_id' => (int)$row['restaurante_id'],
            'restaurante_nombre' => $row['restaurante_nombre'] ?? null,
            'pedido_id' => self::nullableInt($row['pedido_id'] ?? null),
            'consumo_id' => $row['consumo_id'] ?? null,
            'mesa_id' => self::nullableInt($row['mesa_id'] ?? null),
            'division_id' => self::nullableInt($row['division_id'] ?? null),
            'division_cuenta_id' => self::nullableInt($row['division_cuenta_id'] ?? null),
            'mobile_usuario_id' => self::nullableInt($row['mobile_usuario_id'] ?? null),
            'solicitado_por_usuario_id' => self::nullableInt($row['solicitado_por_usuario_id'] ?? null),
            'origen' => $row['origen'] ?? 'cliente',
            'scope' => $row['scope'] ?? 'pedido',
            'monto' => (float)($row['monto'] ?? 0),
            'metodo_pago' => $row['metodo_pago'] ?? null,
            'estado' => $row['estado'] ?? 'pendiente',
            'receptor' => [
                'rfc' => $row['receptor_rfc'] ?? '',
                'nombre_fiscal' => $row['receptor_nombre'] ?? '',
                'regimen_fiscal' => $row['receptor_regimen_fiscal'] ?? '',
                'codigo_postal' => $row['receptor_codigo_postal'] ?? '',
                'uso_cfdi' => $row['uso_cfdi'] ?? '',
                'email' => $row['receptor_email'] ?? '',
            ],
            'cfdi_uuid' => $row['cfdi_uuid'] ?? null,
            'pdf_url' => $row['pdf_url'] ?? null,
            'xml_url' => $row['xml_url'] ?? null,
            'notas' => $row['notas'] ?? null,
            'facturada_at' => $row['facturada_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $int = (int)$value;
        return $int > 0 ? $int : null;
    }

    private static function nullableString(mixed $value): ?string
    {
        $text = trim((string)($value ?? ''));
        return $text !== '' ? $text : null;
    }
}
