<?php
class HikvisionService {
    private array $device;

    public function __construct(int $dispositivoId) {
        $db   = Database::getInstance();
        $stmt = $db->prepare('SELECT * FROM dispositivos_hikvision WHERE id = ? AND activo = 1 LIMIT 1');
        $stmt->execute([$dispositivoId]);
        $this->device = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function getStreamUrl(): string {
        if (empty($this->device)) return '';
        $d = $this->device;
        $pass = base64_decode($d['password_enc'] ?? '');
        return "rtsp://{$d['usuario']}:{$pass}@{$d['ip']}:{$d['puerto']}/Streaming/Channels/{$d['canal']}01";
    }

    public function getSnapshotUrl(): string {
        if (empty($this->device)) return '';
        $d    = $this->device;
        $pass = base64_decode($d['password_enc'] ?? '');
        return "http://{$d['ip']}:{$d['puerto']}/ISAPI/Streaming/channels/{$d['canal']}01/picture?auth={$d['usuario']}:{$pass}";
    }

    public function getStatus(): array {
        if (empty($this->device)) return ['online' => false, 'error' => 'Dispositivo no encontrado'];
        try {
            $d   = $this->device;
            $pass = base64_decode($d['password_enc'] ?? '');
            $url  = "http://{$d['ip']}:{$d['puerto']}/ISAPI/System/status";
            $ctx  = stream_context_create([
                'http' => [
                    'header'  => 'Authorization: Basic ' . base64_encode("{$d['usuario']}:{$pass}"),
                    'timeout' => 5,
                ]
            ]);
            $resp = @file_get_contents($url, false, $ctx);
            return ['online' => $resp !== false, 'ip' => $d['ip']];
        } catch (Exception $e) {
            return ['online' => false, 'error' => $e->getMessage()];
        }
    }

    public static function listar(): array {
        $db   = Database::getInstance();
        $stmt = $db->query('SELECT * FROM dispositivos_hikvision ORDER BY nombre');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
