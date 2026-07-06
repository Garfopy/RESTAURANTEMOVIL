<?php

declare(strict_types=1);

namespace Amare\Api\Services;

class FacturapiService
{
    private const BASE_URL = 'https://www.facturapi.io/v2';

    private string $secretKey;

    public function __construct(?string $secretKey = null)
    {
        $this->secretKey = trim((string)($secretKey
            ?? $_ENV['FACTURAPI_SECRET_KEY']
            ?? $_SERVER['FACTURAPI_SECRET_KEY']
            ?? getenv('FACTURAPI_SECRET_KEY')
            ?: ''));

        if ($this->secretKey === '') {
            throw new \RuntimeException('Configura FACTURAPI_SECRET_KEY en el .env del API.');
        }
    }

    public function createInvoiceFromRequest(array $invoiceRequest, array $options = []): array
    {
        $payload = $this->buildInvoicePayload($invoiceRequest, $options);
        return $this->requestJson('POST', '/invoices', $payload);
    }

    public function downloadInvoice(string $invoiceId, string $format): string
    {
        if (!in_array($format, ['pdf', 'xml'], true)) {
            throw new \InvalidArgumentException('Formato de factura no soportado.');
        }

        return $this->requestBinary('/invoices/' . rawurlencode($invoiceId) . '/' . $format);
    }

    private function buildInvoicePayload(array $invoiceRequest, array $options): array
    {
        $receptor = $invoiceRequest['receptor'] ?? [];
        $amount = round((float)($invoiceRequest['monto'] ?? 0), 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('La solicitud no tiene un monto valido para facturar.');
        }

        return [
            'customer' => [
                'legal_name' => (string)($receptor['nombre_fiscal'] ?? ''),
                'tax_id' => strtoupper((string)($receptor['rfc'] ?? '')),
                'tax_system' => (string)($receptor['regimen_fiscal'] ?? ''),
                'email' => (string)($receptor['email'] ?? ''),
                'address' => [
                    'zip' => (string)($receptor['codigo_postal'] ?? ''),
                    'country' => 'MEX',
                ],
            ],
            'items' => [[
                'quantity' => 1,
                'product' => [
                    'description' => (string)($options['description'] ?? $this->defaultDescription($invoiceRequest)),
                    'product_key' => (string)($options['product_key'] ?? $this->env('FACTURAPI_PRODUCT_KEY', '90101501')),
                    'unit_key' => (string)($options['unit_key'] ?? $this->env('FACTURAPI_UNIT_KEY', 'E48')),
                    'price' => $amount,
                    'tax_included' => $this->boolOption($options['tax_included'] ?? $this->env('FACTURAPI_TAX_INCLUDED', 'true')),
                    'taxability' => '02',
                    'taxes' => [[
                        'type' => 'IVA',
                        'rate' => (float)($options['tax_rate'] ?? $this->env('FACTURAPI_TAX_RATE', '0.16')),
                    ]],
                ],
            ]],
            'use' => (string)($options['use'] ?? $receptor['uso_cfdi'] ?? 'G03'),
            'payment_form' => (string)($options['payment_form'] ?? $this->paymentForm((string)($invoiceRequest['metodo_pago'] ?? ''))),
            'external_id' => 'amare-invoice-request-' . (int)$invoiceRequest['id'],
            'idempotency_key' => 'amare-invoice-request-' . (int)$invoiceRequest['id'],
        ];
    }

    private function requestJson(string $method, string $path, array $payload): array
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new \RuntimeException('No se pudo preparar la solicitud para FacturAPI.');
        }

        $response = $this->curl($method, $path, $json, [
            'Content-Type: application/json',
        ]);

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('FacturAPI respondio con un formato inesperado.');
        }

        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = (string)($decoded['message'] ?? 'FacturAPI no pudo crear la factura.');
            throw new \RuntimeException($message);
        }

        return $decoded;
    }

    private function requestBinary(string $path): string
    {
        $response = $this->curl('GET', $path, null, []);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $decoded = json_decode($response['body'], true);
            $message = is_array($decoded) ? (string)($decoded['message'] ?? '') : '';
            throw new \RuntimeException($message !== '' ? $message : 'FacturAPI no pudo descargar el archivo.');
        }

        return $response['body'];
    }

    /**
     * @return array{status:int, body:string}
     */
    private function curl(string $method, string $path, ?string $body, array $headers): array
    {
        if (!function_exists('curl_init')) {
            throw new \RuntimeException('La extension cURL de PHP es requerida para FacturAPI.');
        }

        $curl = curl_init(self::BASE_URL . $path);
        $allHeaders = array_merge([
            'Authorization: Bearer ' . $this->secretKey,
            'Accept: application/json',
        ], $headers);

        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_TIMEOUT => 45,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $result = curl_exec($curl);
        if ($result === false) {
            $error = curl_error($curl);
            curl_close($curl);
            throw new \RuntimeException('No se pudo conectar con FacturAPI: ' . $error);
        }

        $status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        return ['status' => $status, 'body' => (string)$result];
    }

    private function defaultDescription(array $invoiceRequest): string
    {
        $scope = (string)($invoiceRequest['scope'] ?? 'pedido');
        $id = (int)($invoiceRequest['id'] ?? 0);
        return 'Consumo en restaurante - solicitud de factura #' . $id . ' (' . $scope . ')';
    }

    private function paymentForm(string $method): string
    {
        return match (strtolower(trim($method))) {
            'cash', 'efectivo' => '01',
            'transfer', 'transferencia', 'spei' => '03',
            'card', 'tarjeta', 'credit_card', 'credito' => '04',
            'debit_card', 'debito' => '28',
            'wallet', 'saldo' => '05',
            default => '99',
        };
    }

    private function env(string $key, string $default): string
    {
        return (string)($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default);
    }

    private function boolOption(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string)$value), ['1', 'true', 'yes', 'on'], true);
    }
}
