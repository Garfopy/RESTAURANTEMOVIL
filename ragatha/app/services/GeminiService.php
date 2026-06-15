<?php
class GeminiService
{
    private string $apiKey;
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent';

    public function __construct()
    {
        $db = Database::getInstance();
        $this->apiKey = $db->query(
            "SELECT valor FROM global_settings WHERE clave = 'gemini_api_key' LIMIT 1"
        )->fetchColumn() ?: '';
    }

    public function estaConfigurado(): bool
    {
        return !empty($this->apiKey);
    }

    public function chat(string $systemPrompt, array $mensajes): string
    {
        if (!$this->apiKey) return 'No hay API key de IA configurada. Configúrala en Panel > APIs.';

        // Gemini usa "model" en lugar de "assistant"
        $contents = array_map(fn($m) => [
            'role'  => $m['role'] === 'assistant' ? 'model' : $m['role'],
            'parts' => [['text' => $m['content']]],
        ], $mensajes);

        $payload = json_encode([
            'system_instruction' => ['parts' => [['text' => $systemPrompt]]],
            'contents'           => $contents,
            'generationConfig'   => ['maxOutputTokens' => 1024, 'temperature' => 0.7],
        ]);

        $url = self::API_URL . '?key=' . urlencode($this->apiKey);
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => 'Content-Type: application/json',
                'content'       => $payload,
                'timeout'       => 30,
                'ignore_errors' => true,
            ],
        ]);

        $resp = @file_get_contents($url, false, $ctx);
        if (!$resp) return 'Error al conectar con el servicio de IA.';

        $data = json_decode($resp, true);

        if (!empty($data['error'])) {
            return 'Error de IA: ' . ($data['error']['message'] ?? 'desconocido');
        }

        return $data['candidates'][0]['content']['parts'][0]['text'] ?? 'Sin respuesta.';
    }
}
