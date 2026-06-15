<?php
class ClaudeService
{
    private string $apiKey;
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const MODEL   = 'claude-haiku-4-5-20251001';

    public function __construct()
    {
        $db = Database::getInstance();
        $this->apiKey = $db->query(
            "SELECT valor FROM global_settings WHERE clave = 'anthropic_api_key' LIMIT 1"
        )->fetchColumn() ?: '';
    }

    public function estaConfigurado(): bool
    {
        return !empty($this->apiKey);
    }

    public function chat(string $systemPrompt, array $mensajes): string
    {
        if (!$this->apiKey) return 'No hay API key de IA configurada. Configúrala en Panel > APIs.';

        $payload = json_encode([
            'model'      => self::MODEL,
            'max_tokens' => 1024,
            'system'     => $systemPrompt,
            'messages'   => $mensajes,
        ]);

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => implode("\r\n", [
                    'Content-Type: application/json',
                    'x-api-key: ' . $this->apiKey,
                    'anthropic-version: 2023-06-01',
                ]),
                'content' => $payload,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents(self::API_URL, false, $ctx);
        if (!$resp) return 'Error al conectar con el servicio de IA.';

        $data = json_decode($resp, true);

        if (!empty($data['error'])) {
            return 'Error de IA: ' . ($data['error']['message'] ?? 'desconocido');
        }

        return $data['content'][0]['text'] ?? 'Sin respuesta.';
    }
}
