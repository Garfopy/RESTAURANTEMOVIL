<?php
class FacturaloService {
    private const BASE_URL = 'https://www.facturapi.io/v2';

    private string $apiKey;
    private string $rfcEmisor;
    private string $nombreEmisor;
    private string $regimenEmisor;
    private string $cpEmisor;

    public function __construct(int $empresaId) {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT facturalo_apikey, facturalo_rfc, facturalo_nombre,
                    facturalo_regimen, facturalo_cp
               FROM empresas WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$empresaId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $this->apiKey        = (string)($row['facturalo_apikey'] ?? '');
        $this->rfcEmisor     = (string)($row['facturalo_rfc'] ?? '');
        $this->nombreEmisor  = (string)($row['facturalo_nombre'] ?? '');
        $this->regimenEmisor = (string)($row['facturalo_regimen'] ?? '601');
        $this->cpEmisor      = (string)($row['facturalo_cp'] ?? '76000');
    }

    public function credencialesCompletas(): bool {
        return !empty($this->apiKey) && !empty($this->rfcEmisor);
    }

    public function generarCFDI(int $pedidoId): array {
        if (!$this->credencialesCompletas()) {
            return ['ok' => false, 'error' => 'Configura tu API Key y RFC en Facturación → Configuración.'];
        }

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT p.*, u.nombre AS comprador_nombre
               FROM pedidos p
               LEFT JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.id = ?'
        );
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$pedido) return ['ok' => false, 'error' => 'Pedido no encontrado'];

        $itemsStmt = $db->prepare(
            'SELECT pd.cantidad, pd.precio_unit, pd.subtotal, pr.nombre
               FROM pedido_detalle pd
               JOIN productos pr ON pr.id = pd.producto_id
              WHERE pd.pedido_id = ?'
        );
        $itemsStmt->execute([$pedidoId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($items)) return ['ok' => false, 'error' => 'El pedido no tiene productos'];

        $lineItems = [];
        foreach ($items as $i) {
            $lineItems[] = [
                'quantity' => (float)$i['cantidad'],
                'product'  => [
                    'description' => $i['nombre'],
                    'product_key' => '50304300',
                    'price'       => (float)$i['precio_unit'],
                    'unit_key'    => 'KGM',
                    'unit_name'   => 'Kilogramo',
                    'tax_included'=> false,
                    'taxes'       => [[
                        'type'       => 'IVA',
                        'rate'       => 0.16,
                        'factor'     => 'Tasa',
                        'withholding'=> false,
                    ]],
                ],
            ];
        }

        $payload = [
            'customer'      => [
                'legal_name' => $pedido['comprador_nombre'] ?: 'PUBLICO EN GENERAL',
                'tax_id'     => 'XAXX010101000',
                'tax_system' => '616',
                'address'    => ['zip' => '06600', 'country' => 'MEX'],
            ],
            'items'         => $lineItems,
            'payment_form'  => '03',
            'payment_method'=> 'PUE',
            'use'           => 'G01',
            'series'        => 'CHB',
            'folio_number'  => max(1, (int)($pedido['folio'] ?? 1)),
        ];

        $invoice = $this->post('/invoices', $payload);

        if (!$invoice || empty($invoice['id'])) {
            $msg = $invoice['message'] ?? ($invoice['error'] ?? 'Error al generar la factura con FacturAPI');
            return ['ok' => false, 'error' => $msg];
        }

        $invoiceId = $invoice['id'];
        $uuid      = $invoice['uuid'] ?? '';

        $dir = ROOT_PATH . '/public/uploads/facturas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $xmlContent = $this->download("/invoices/{$invoiceId}/xml");
        $pdfContent = $this->download("/invoices/{$invoiceId}/pdf");

        $xmlPath = '';
        $pdfPath = '';
        if ($xmlContent) {
            $xmlPath = 'public/uploads/facturas/' . $uuid . '.xml';
            file_put_contents(ROOT_PATH . '/' . $xmlPath, $xmlContent);
        }
        if ($pdfContent) {
            $pdfPath = 'public/uploads/facturas/' . $uuid . '.pdf';
            file_put_contents(ROOT_PATH . '/' . $pdfPath, $pdfContent);
        }

        $db->prepare(
            'INSERT INTO facturas (pedido_id, empresa_id, uuid_cfdi, xml_path, pdf_path, serie, folio_fac, monto)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
               xml_path=VALUES(xml_path), pdf_path=VALUES(pdf_path), uuid_cfdi=VALUES(uuid_cfdi)'
        )->execute([
            $pedidoId,
            $pedido['empresa_id'],
            $uuid,
            $xmlPath ?: null,
            $pdfPath ?: null,
            'CHB',
            $pedido['folio'],
            round((float)($invoice['total'] ?? 0), 2),
        ]);

        return ['ok' => true, 'uuid' => $uuid, 'xml_path' => $xmlPath, 'pdf_path' => $pdfPath];
    }

    public function cancelarCFDI(string $uuid, string $rfcReceptor, float $total): bool {
        if (!$this->apiKey) return false;

        // Look up the FacturAPI invoice ID by UUID
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT facturapi_id FROM facturas WHERE uuid_cfdi = ? LIMIT 1');
        $stmt->execute([$uuid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // FacturAPI cancel uses DELETE on the invoice resource
        if (!empty($row['facturapi_id'])) {
            $resp = $this->delete('/invoices/' . $row['facturapi_id']);
            return isset($resp['status']) && $resp['status'] === 'canceled';
        }

        return false;
    }

    public function consultarCreditos(): int {
        return -1;
    }

    private function post(string $path, array $body): ?array {
        return $this->request('POST', $path, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    private function delete(string $path): ?array {
        return $this->request('DELETE', $path, null);
    }

    private function download(string $path): ?string {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'header'  => 'Authorization: Bearer ' . $this->apiKey . "\r\n",
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);
        $resp = @file_get_contents(self::BASE_URL . $path, false, $ctx);
        return $resp ?: null;
    }

    private function request(string $method, string $path, ?string $body): ?array {
        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        $opts = [
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'timeout'       => 30,
                'ignore_errors' => true,
            ],
        ];
        if ($body !== null) $opts['http']['content'] = $body;
        $ctx  = stream_context_create($opts);
        $resp = @file_get_contents(self::BASE_URL . $path, false, $ctx);
        if (!$resp) return null;
        return json_decode($resp, true);
    }
}
