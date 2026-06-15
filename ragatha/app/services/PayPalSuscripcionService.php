<?php
class PayPalSuscripcionService
{
    private string $baseUrl;
    private string $clientId;
    private string $secret;

    private string $mode;

    public function __construct()
    {
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
        $cacheKey = 'paypal_access_token';
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

    // ── Crear producto en el catálogo PayPal ──────────────────────────────────
    public function crearProducto(string $nombre, string $descripcion): string
    {
        $data = $this->request('POST', '/v1/catalogs/products', [
            'name'        => $nombre,
            'description' => $descripcion,
            'type'        => 'SERVICE',
            'category'    => 'SOFTWARE',
        ]);
        return $data['id'] ?? '';
    }

    // ── Crear plan de facturación PayPal ──────────────────────────────────────
    public function crearPlanBilling(
        string $productId,
        string $nombre,
        string $ciclo,
        float  $precio,
        string $moneda = 'MXN'
    ): string {
        $intervalUnit = $ciclo === 'anual' ? 'YEAR' : 'MONTH';
        $data = $this->request('POST', '/v1/billing/plans', [
            'product_id'     => $productId,
            'name'           => $nombre,
            'status'         => 'ACTIVE',
            'billing_cycles' => [[
                'frequency'      => ['interval_unit' => $intervalUnit, 'interval_count' => 1],
                'tenure_type'    => 'REGULAR',
                'sequence'       => 1,
                'total_cycles'   => 0,
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value'         => number_format($precio, 2, '.', ''),
                        'currency_code' => $moneda,
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding'     => true,
                'setup_fee_failure_action'  => 'CONTINUE',
                'payment_failure_threshold' => 3,
            ],
        ]);
        return $data['id'] ?? '';
    }

    // ── Sincronizar todos los planes locales con PayPal ───────────────────────
    // Crea nuevos planes en PayPal según el modo activo (sandbox o live).
    // Si el plan ya tiene ID para el modo activo, lo omite (no recrea).
    // Para forzar recreación (al cambiar precios), pasar forceRecreate=true.
    public function sincronizarPlanes(array $planes, string $moneda = 'MXN'): array
    {
        $config         = new ConfigModel();
        $productKey     = 'paypal_product_id_' . $this->mode;
        $productId      = $config->get($productKey, '');

        if (!$productId) {
            $productId = $this->crearProducto(
                'CarniHub SaaS',
                'Plataforma de gestión para carnicerías'
            );
            $config->set($productKey, $productId);
            // Compatibilidad con clave legacy
            $config->set('paypal_product_id', $productId);
        }

        // Campos de BD según modo
        $colMensual = $this->mode === 'live' ? 'paypal_plan_id_live'       : 'paypal_plan_id';
        $colAnual   = $this->mode === 'live' ? 'paypal_plan_id_anual_live' : 'paypal_plan_id_anual';

        $resultado = [];
        foreach ($planes as $plan) {
            $ids = [];
            if (empty($plan[$colMensual])) {
                $ids['mensual'] = $this->crearPlanBilling(
                    $productId,
                    $plan['nombre'] . ' — Mensual',
                    'mensual',
                    (float)$plan['precio_mensual'],
                    $moneda
                );
            }
            if (empty($plan[$colAnual])) {
                $ids['anual'] = $this->crearPlanBilling(
                    $productId,
                    $plan['nombre'] . ' — Anual',
                    'anual',
                    (float)$plan['precio_anual'],
                    $moneda
                );
            }
            $resultado[$plan['id']] = $ids + ['_modo' => $this->mode];
        }
        return $resultado;
    }

    // ── Crear suscripción ─────────────────────────────────────────────────────
    public function crearSuscripcion(
        string $paypalPlanId,
        string $returnUrl,
        string $cancelUrl
    ): array {
        $data = $this->request('POST', '/v1/billing/subscriptions', [
            'plan_id'             => $paypalPlanId,
            'application_context' => [
                'brand_name'          => 'CarniHub',
                'locale'              => 'es-MX',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action'         => 'SUBSCRIBE_NOW',
                'return_url'          => $returnUrl,
                'cancel_url'          => $cancelUrl,
            ],
        ]);

        $approveLink = '';
        foreach ($data['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approveLink = $link['href'];
                break;
            }
        }

        return [
            'id'           => $data['id']     ?? '',
            'status'       => $data['status'] ?? '',
            'approve_link' => $approveLink,
        ];
    }

    // ── Obtener estado de suscripción ─────────────────────────────────────────
    public function obtenerSuscripcion(string $subscriptionId): array
    {
        return $this->request('GET', '/v1/billing/subscriptions/' . $subscriptionId);
    }

    // ── Cancelar suscripción ──────────────────────────────────────────────────
    public function cancelarSuscripcion(
        string $subscriptionId,
        string $reason = 'Cancelado por usuario'
    ): void {
        $ch = curl_init($this->baseUrl . '/v1/billing/subscriptions/' . $subscriptionId . '/cancel');
        $token = $this->getAccessToken();
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode(['reason' => $reason]),
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 204 && $code !== 200) {
            throw new \RuntimeException("Error al cancelar suscripción PayPal ($code)");
        }
    }

    // ── Verificar webhook ─────────────────────────────────────────────────────
    public function verificarWebhook(array $headers, string $rawBody, string $webhookId): bool
    {
        try {
            $data = $this->request('POST', '/v1/notifications/verify-webhook-signature', [
                'transmission_id'   => $headers['PAYPAL-TRANSMISSION-ID']   ?? '',
                'transmission_time' => $headers['PAYPAL-TRANSMISSION-TIME'] ?? '',
                'cert_url'          => $headers['PAYPAL-CERT-URL']          ?? '',
                'auth_algo'         => $headers['PAYPAL-AUTH-ALGO']         ?? 'SHA256withRSA',
                'transmission_sig'  => $headers['PAYPAL-TRANSMISSION-SIG']  ?? '',
                'webhook_id'        => $webhookId,
                'webhook_event'     => json_decode($rawBody, true),
            ]);
            return ($data['verification_status'] ?? '') === 'SUCCESS';
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ── Normalizar headers HTTP ───────────────────────────────────────────────
    public static function getRequestHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $val) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $val;
            }
        }
        return $headers;
    }
}
