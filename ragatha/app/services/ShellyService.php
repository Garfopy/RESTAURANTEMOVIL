<?php
class ShellyService {
    private array $device;
    private const API_BASE = 'https://shelly-103-eu.shelly.cloud/device';

    public function __construct(int $dispositivoId) {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM dispositivos_shelly WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$dispositivoId]);
        $this->device = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStatus(): array {
        return $this->apiCall('status');
    }

    public function turnOn(): bool {
        $result = $this->apiCall('relay/control', ['turn' => 'on', 'channel' => 0]);
        $this->updateLocalState('on');
        return !empty($result['isok']);
    }

    public function turnOff(): bool {
        $result = $this->apiCall('relay/control', ['turn' => 'off', 'channel' => 0]);
        $this->updateLocalState('off');
        return !empty($result['isok']);
    }

    public function toggle(): bool {
        $status = $this->getStatus();
        $current = $status['data']['device_status']['relays'][0]['ison'] ?? false;
        return $current ? $this->turnOff() : $this->turnOn();
    }

    private function apiCall(string $endpoint, array $params = []): array {
        if (empty($this->device)) return ['isok' => false, 'error' => 'Dispositivo no encontrado'];

        $params['id']       = $this->device['device_id'];
        $params['auth_key'] = $this->device['auth_key'];

        $url  = self::API_BASE . '/' . $endpoint;
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => 'Content-Type: application/x-www-form-urlencoded',
                'content' => http_build_query($params),
                'timeout' => 8,
            ]
        ]);
        $resp = @file_get_contents($url, false, $ctx);
        return $resp ? json_decode($resp, true) : ['isok' => false];
    }

    private function updateLocalState(string $state): void {
        $db   = Database::getInstance();
        $stmt = $db->prepare('UPDATE dispositivos_shelly SET estado_actual = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$state, $this->device['id']]);
    }

    public static function listar(): array {
        $db   = Database::getInstance();
        $stmt = $db->query('SELECT * FROM dispositivos_shelly ORDER BY nombre');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
