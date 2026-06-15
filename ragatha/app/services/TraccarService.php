<?php
class TraccarService {
    private string $baseUrl;
    private string $user;
    private string $pass;

    public function __construct() {
        $db = Database::getInstance();
        $get = fn(string $k) => $db->query("SELECT valor FROM global_settings WHERE clave = '$k' LIMIT 1")->fetchColumn() ?: '';
        $this->baseUrl = rtrim($get('traccar_url') ?: 'http://traccar.carnihub.mx', '/');
        $this->user    = $get('traccar_user');
        $this->pass    = $get('traccar_pass');
    }

    public function getDispositivos(): array {
        return $this->get('/api/devices') ?: [];
    }

    public function getPosicion(int $deviceId): array {
        $positions = $this->get('/api/positions?deviceId=' . $deviceId);
        if (empty($positions)) return [];
        $p = $positions[0];
        return [
            'device_id' => $deviceId,
            'lat'       => $p['latitude']  ?? 0,
            'lng'       => $p['longitude'] ?? 0,
            'velocidad' => $p['speed']     ?? 0,
            'tiempo'    => $p['fixTime']   ?? null,
        ];
    }

    public function getRuta(int $deviceId, string $desde, string $hasta): array {
        $url = "/api/reports/route?deviceId={$deviceId}&from=" . urlencode($desde) . '&to=' . urlencode($hasta);
        return $this->get($url) ?: [];
    }

    private function get(string $path): ?array {
        if (!$this->baseUrl) return null;
        $ctx = stream_context_create([
            'http' => [
                'header'  => 'Authorization: Basic ' . base64_encode("{$this->user}:{$this->pass}"),
                'timeout' => 8,
            ]
        ]);
        $resp = @file_get_contents($this->baseUrl . $path, false, $ctx);
        return $resp ? json_decode($resp, true) : null;
    }
}
