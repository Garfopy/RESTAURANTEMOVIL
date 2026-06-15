<?php
class WhatsAppService {
    private string $apiUrl;
    private string $token;
    private string $phoneId;

    public function __construct() {
        $db = Database::getInstance();
        $get = fn(string $k) => $db->query("SELECT valor FROM global_settings WHERE clave = '$k' LIMIT 1")->fetchColumn() ?: '';
        $this->apiUrl  = 'https://graph.facebook.com/v18.0';
        $this->token   = $get('whatsapp_api_token');
        $this->phoneId = $get('whatsapp_phone_id');
    }

    public function enviarMensaje(string $telefono, string $mensaje): bool {
        if (!$this->token || !$this->phoneId) return false;

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'type'              => 'text',
            'text'              => ['body' => $mensaje],
        ]);

        return $this->post("/v18.0/{$this->phoneId}/messages", $payload);
    }

    public function enviarPlantilla(string $telefono, string $template, array $params = []): bool {
        if (!$this->token || !$this->phoneId) return false;

        $components = [];
        if (!empty($params)) {
            $components[] = [
                'type'       => 'body',
                'parameters' => array_map(fn($v) => ['type' => 'text', 'text' => (string)$v], $params),
            ];
        }

        $payload = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $telefono,
            'type'              => 'template',
            'template'          => ['name' => $template, 'language' => ['code' => 'es_MX'], 'components' => $components],
        ]);

        return $this->post("/v18.0/{$this->phoneId}/messages", $payload);
    }

    public function notificarPedidoConfirmado(array $pedido): bool {
        $telefono = $pedido['telefono'] ?? '';
        if (!$telefono) return false;

        $msg = "✅ *CarniHub* — Tu pedido #{$pedido['folio']} ha sido confirmado.\n";
        $msg .= "Total: $" . number_format($pedido['total'], 2) . "\n";
        if (!empty($pedido['fecha_entrega'])) {
            $msg .= "Entrega estimada: {$pedido['fecha_entrega']}\n";
        }
        $msg .= "¡Gracias por tu compra! 🥩";
        return $this->enviarMensaje($telefono, $msg);
    }

    public function notificarRepartidor(array $chofer, array $ruta): bool {
        $telefono = $chofer['telefono'] ?? '';
        if (!$telefono) return false;

        $msg = "🚛 *CarniHub Logística* — Nueva ruta asignada: {$ruta['nombre']}\n";
        $msg .= "Fecha: {$ruta['fecha']}\n";
        $msg .= "Entregas: {$ruta['total_entregas']}\n";
        $msg .= "Ingresa a la app para ver el detalle.";
        return $this->enviarMensaje($telefono, $msg);
    }

    private function post(string $path, string $jsonPayload): bool {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nAuthorization: Bearer {$this->token}",
                'content' => $jsonPayload,
                'timeout' => 10,
            ]
        ]);
        $resp = @file_get_contents($this->apiUrl . $path, false, $ctx);
        $data = $resp ? json_decode($resp, true) : null;
        return isset($data['messages'][0]['id']);
    }
}
