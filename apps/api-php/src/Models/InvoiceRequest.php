<?php

declare(strict_types=1);

namespace Amare\Api\Models;

use Amare\Api\Config\Database;
use Amare\Api\Services\FacturapiService;

class InvoiceRequest
{
    public static function createFromPayment(array $context, ?array $invoiceRequest): ?array
    {
        if (!self::isRequired($invoiceRequest)) {
            return null;
        }

        $orderId = self::nullableInt($context['pedido_id'] ?? null);
        if ($orderId !== null) {
            $existing = self::findByOrder($orderId);
            if ($existing) {
                return $existing;
            }
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
                (restaurante_id, pedido_id,
                 mobile_usuario_id, solicitado_por_usuario_id, origen, scope, monto, metodo_pago,
                 receptor_rfc, receptor_nombre, receptor_regimen_fiscal, receptor_codigo_postal,
                 uso_cfdi, receptor_email)
             VALUES
                (:restaurant_id, :order_id,
                 :mobile_user_id, :requested_by_user_id, :origin, :scope, :amount, :payment_method,
                 :rfc, :name, :regime, :postal_code, :cfdi_use, :email)',
            [
                ':restaurant_id' => $restaurantId,
                ':order_id' => self::nullableInt($context['pedido_id'] ?? null),
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

        if ($id <= 0) {
            return null;
        }

        $created = self::findById($id);
        if ($created && self::autoStampEnabled()) {
            try {
                return self::stampWithFacturapi($id);
            } catch (\Throwable $exception) {
                error_log('InvoiceRequest::autoStampWithFacturapi ERROR: ' . $exception->getMessage());
                self::updateAdmin($id, [
                    'estado' => 'en_proceso',
                    'notas' => self::appendNote(
                        $created['notas'] ?? null,
                        'No se pudo timbrar automaticamente en FacturAPI: ' . $exception->getMessage()
                    ),
                ]);
                return self::findById($id);
            }
        }

        return $created;
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

    public static function findByOrder(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $row = Database::queryOne(
            'SELECT fs.*, r.nombre AS restaurante_nombre
               FROM facturacion_solicitudes fs
          LEFT JOIN rest_restaurantes r ON r.id = fs.restaurante_id
              WHERE fs.pedido_id = :order_id
           ORDER BY fs.id ASC
              LIMIT 1',
            [':order_id' => $orderId]
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

    public static function stampWithFacturapi(int $id, array $options = []): ?array
    {
        $request = self::findById($id);
        if (!$request) {
            return null;
        }

        if (($request['estado'] ?? '') === 'facturada' && !empty($request['cfdi_uuid'])) {
            return $request;
        }

        self::updateAdmin($id, [
            'estado' => 'en_proceso',
            'notas' => self::appendNote($request['notas'] ?? null, 'Timbrando en FacturAPI.'),
        ]);

        $service = new FacturapiService();
        $invoice = $service->createInvoiceFromRequest($request, $options);
        $facturapiInvoiceId = (string)($invoice['id'] ?? '');
        if ($facturapiInvoiceId === '') {
            throw new \RuntimeException('FacturAPI no regreso el ID de la factura.');
        }

        $uuid = (string)($invoice['uuid'] ?? '');
        if ($uuid === '') {
            throw new \RuntimeException('FacturAPI no regreso UUID CFDI.');
        }

        $pdfPath = self::storeFacturapiFile($id, $uuid, 'pdf', $service->downloadInvoice($facturapiInvoiceId, 'pdf'));
        $xmlPath = self::storeFacturapiFile($id, $uuid, 'xml', $service->downloadInvoice($facturapiInvoiceId, 'xml'));
        $baseUrl = rtrim((string)($_ENV['APP_URL'] ?? $_SERVER['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');

        $data = [
            'estado' => 'facturada',
            'cfdi_uuid' => $uuid,
            'pdf_url' => $baseUrl . '/uploads/facturas/' . basename($pdfPath),
            'xml_url' => $baseUrl . '/uploads/facturas/' . basename($xmlPath),
            'notas' => self::appendNote($request['notas'] ?? null, 'Factura generada en FacturAPI (test=' . (empty($invoice['livemode']) ? 'si' : 'no') . ').'),
            'facturapi_invoice_id' => $facturapiInvoiceId,
            'facturapi_status' => (string)($invoice['status'] ?? ''),
            'facturapi_livemode' => !empty($invoice['livemode']) ? 1 : 0,
        ];

        self::updateFacturapiData($id, $data);
        return self::findById($id);
    }

    public static function updateFacturapiData(int $id, array $data): void
    {
        Database::rowCount(
            'UPDATE facturacion_solicitudes
                SET estado = :status,
                    cfdi_uuid = :uuid,
                    pdf_url = :pdf_url,
                    xml_url = :xml_url,
                    notas = :notes,
                    facturapi_invoice_id = :facturapi_invoice_id,
                    facturapi_status = :facturapi_status,
                    facturapi_livemode = :facturapi_livemode,
                    facturada_at = COALESCE(facturada_at, NOW()),
                    updated_at = NOW()
              WHERE id = :id',
            [
                ':id' => $id,
                ':status' => (string)$data['estado'],
                ':uuid' => (string)$data['cfdi_uuid'],
                ':pdf_url' => (string)$data['pdf_url'],
                ':xml_url' => (string)$data['xml_url'],
                ':notes' => $data['notas'] ?? null,
                ':facturapi_invoice_id' => (string)$data['facturapi_invoice_id'],
                ':facturapi_status' => (string)$data['facturapi_status'],
                ':facturapi_livemode' => (int)$data['facturapi_livemode'],
            ]
        );
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
            'facturapi_invoice_id' => $row['facturapi_invoice_id'] ?? null,
            'facturapi_status' => $row['facturapi_status'] ?? null,
            'facturapi_livemode' => isset($row['facturapi_livemode']) ? (bool)$row['facturapi_livemode'] : null,
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

    private static function storeFacturapiFile(int $requestId, string $uuid, string $extension, string $contents): string
    {
        $directory = dirname(__DIR__, 2) . '/uploads/facturas';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de facturas.');
        }

        $safeUuid = preg_replace('/[^a-zA-Z0-9-]/', '', $uuid) ?: ('request-' . $requestId);
        $path = $directory . '/' . $requestId . '-' . $safeUuid . '.' . $extension;
        if (file_put_contents($path, $contents) === false) {
            throw new \RuntimeException('No se pudo guardar el archivo ' . strtoupper($extension) . ' de la factura.');
        }

        return $path;
    }

    private static function appendNote(?string $current, string $note): string
    {
        $current = trim((string)$current);
        $timestamp = date('Y-m-d H:i:s');
        $entry = '[' . $timestamp . '] ' . $note;
        return $current !== '' ? $current . "\n" . $entry : $entry;
    }

    private static function autoStampEnabled(): bool
    {
        $value = $_ENV['FACTURAPI_AUTO_STAMP'] ?? $_SERVER['FACTURAPI_AUTO_STAMP'] ?? getenv('FACTURAPI_AUTO_STAMP') ?: 'false';
        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
