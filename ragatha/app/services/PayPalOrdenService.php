<?php
/**
 * PayPalOrdenService — Crea y captura órdenes de pago único (no suscripciones).
 * Usado para cobrar pedidos a proveedores (CarniHub B2B) y pagos de comensales.
 */
class PayPalOrdenService
{
    private string $baseUrl;
    private string $clientId;
    private string $secret;
    private string $mode;

    public function __construct()
    {
        require_once ROOT_PATH . '/app/models/ConfigModel.php';
        $config         = new ConfigModel();
        $this->mode     = $config->get('paypal_mode', 'sandbox');
        $this->clientId = $this->mode === 'live'
            ? $config->get('paypal_client_id_live', $config->get('paypal_client_id', ''))
            : $config->get('paypal_client_id_sandbox', $config->get('paypal_client_id', ''));
        $this->secret   = $this->mode === 'live'
            ? $config->get('paypal_secret_live', $config->get('paypal_secret', ''))
            : $config->get('paypal_secret_sandbox', $config->get('paypal_secret', ''));
        $this->baseUrl  = $this->mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    // ── OAuth token ───────────────────────────────────────────────────────────
    private function getAccessToken(): string
    {
        $cacheKey = 'paypal_order_access_token';
        if (!empty($_SESSION[$cacheKey]) && !empty($_SESSION[$cacheKey . '_exp'])
            && time() < $_SESSION[$cacheKey . '_exp']) {
            return $_SESSION[$cacheKey];
        }

        $ch = curl_init($this->baseUrl . '/v1/oauth2/token');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_USERPWD        => $this->clientId . ':' . $this->secret,
            CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 200) {
            throw new \RuntimeException('PayPal auth error: ' . $body);
        }
        $data = json_decode($body, true);
        $_SESSION[$cacheKey]          = $data['access_token'];
        $_SESSION[$cacheKey . '_exp'] = time() + (int)($data['expires_in'] ?? 28800) - 60;

        return $data['access_token'];
    }

    // ── Helper cURL ───────────────────────────────────────────────────────────
    private function request(string $method, string $endpoint, array $body = []): array
    {
        $token = $this->getAccessToken();
        $ch    = curl_init($this->baseUrl . $endpoint);
        $opts  = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
                'PayPal-Request-Id: carnihub-' . uniqid(),
            ],
        ];
        if ($method === 'POST') {
            $opts[CURLOPT_POST]       = true;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body);
        } elseif ($method === 'GET') {
            $opts[CURLOPT_HTTPGET] = true;
        } elseif ($method === 'PATCH') {
            $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
            $opts[CURLOPT_POSTFIELDS]    = json_encode($body);
        }
        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $decoded = json_decode($response, true) ?? [];
        if ($code >= 400) {
            $msg = $decoded['message'] ?? $decoded['error_description'] ?? $response;
            throw new \RuntimeException("PayPal API error ($code): $msg");
        }
        return $decoded;
    }

    /**
     * Crea una orden de pago único en PayPal.
     *
     * @param  float  $monto      Monto total a cobrar
     * @param  string $moneda     Código de moneda (ej. 'MXN', 'USD')
     * @param  string $returnUrl  URL a la que PayPal redirige tras aprobar
     * @param  string $cancelUrl  URL a la que PayPal redirige si cancela
     * @param  string $descripcion Descripción del pago
     * @return array  ['orderId' => string, 'approvalUrl' => string]
     */
    public function crearOrden(
        float  $monto,
        string $moneda,
        string $returnUrl,
        string $cancelUrl,
        string $descripcion = 'Pago'
    ): array {
        $data = $this->request('POST', '/v2/checkout/orders', [
            'intent'         => 'CAPTURE',
            'purchase_units' => [[
                'description' => $descripcion,
                'amount'      => [
                    'currency_code' => strtoupper($moneda),
                    'value'         => number_format($monto, 2, '.', ''),
                ],
            ]],
            'application_context' => [
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
                'brand_name'          => 'CarniHub',
                'landing_page'        => 'NO_PREFERENCE',
                'user_action'         => 'PAY_NOW',
            ],
        ]);

        $approvalUrl = '';
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }

        return [
            'orderId'     => $data['id'] ?? '',
            'approvalUrl' => $approvalUrl,
        ];
    }

    /**
     * Captura (ejecuta el cobro de) una orden ya aprobada por el comprador.
     *
     * @param  string $orderId  El ID de la orden de PayPal
     * @return array  Respuesta de la API (incluye status, capture id, etc.)
     */
    public function capturarOrden(string $orderId): array
    {
        $data = $this->request('POST', "/v2/checkout/orders/{$orderId}/capture");

        $status = $data['status'] ?? '';
        $captureId = '';
        foreach ($data['purchase_units'][0]['payments']['captures'] ?? [] as $capture) {
            $captureId = $capture['id'] ?? '';
            break;
        }

        return [
            'ok'        => $status === 'COMPLETED',
            'status'    => $status,
            'captureId' => $captureId,
            'raw'       => $data,
        ];
    }
}
