<?php
/**
 * CapiRest — CarniHubApiService v1.0
 *
 * Cliente HTTP que encapsula todas las llamadas a la API REST
 * del sistema CarniHub externo.
 *
 * Configuración por restaurante: tabla `carnihub_api_config`
 *   · carnihub_url   → URL base del servidor CarniHub (ej: https://carnihub.digital)
 *                     SIN sufijo /api/v1 — los paths del servicio ya incluyen /api/
 *   · api_key        → Bearer token emitido por CarniHub (raw, nunca hash)
 *
 * Uso:
 *   $service = new CarniHubApiService();
 *   $result  = $service->crearPedido($restauranteId, $items);
 */
class CarniHubApiService
{
    private const TIMEOUT_SECONDS   = 15;
    private const CONNECT_TIMEOUT   = 5;
    private const MAX_URL_LENGTH    = 2048;

    // ── Config cache ───────────────────────────────────────────────
    /** @var array<int, array|null> */
    private array $configCache = [];

    // ================================================================
    // Métodos públicos
    // ================================================================

    /**
     * Crear un pedido B2B en CarniHub.
     *
     * @param  int    $restauranteId  ID del restaurante en CapiRest
     * @param  array  $items          [['producto_id'=>int, 'cantidad'=>float, 'precio_unit'=>float], ...]
     * @param  string $notas          Notas opcionales para el pedido
     * @param  array  $compradorInfo  Datos del comprador/restaurante para la dirección de entrega:
     *                                ['comprador_nombre', 'comprador_direccion', 'comprador_telefono',
     *                                 'comprador_lat', 'comprador_lng']
     * @return array  ['success'=>bool, 'pedido_id'=>int, 'folio'=>str, ...] | ['success'=>false, 'error'=>str]
     */
    public function crearPedido(int $restauranteId, array $items, string $notas = '', array $compradorInfo = [], int $capirestPedidoId = 0): array
    {
        $config = $this->getConfig($restauranteId);
        if ($config === null) {
            return $this->errorResponse('No hay configuración de CarniHub para este restaurante');
        }

        if (empty($items)) {
            return $this->errorResponse('La lista de items está vacía');
        }

        // Sanitizar items
        $payload = ['items' => []];
        if ($config['carnihub_empresa_id']) {
            $payload['empresa_id'] = (int)$config['carnihub_empresa_id'];
        }
        if ($notas !== '') {
            $payload['notas'] = substr($notas, 0, 500);
        }
        // Referencia de vuelta: CarniHub la incluirá en el webhook
        if ($capirestPedidoId > 0) {
            $payload['capirest_pedido_id'] = $capirestPedidoId;
        }

        // Dirección de entrega del restaurante (comprador)
        if (!empty($compradorInfo['comprador_nombre'])) {
            $payload['comprador_nombre'] = substr((string)$compradorInfo['comprador_nombre'], 0, 200);
        }
        if (!empty($compradorInfo['comprador_direccion'])) {
            $payload['comprador_direccion'] = substr((string)$compradorInfo['comprador_direccion'], 0, 500);
        }
        if (!empty($compradorInfo['comprador_telefono'])) {
            $payload['comprador_telefono'] = substr((string)$compradorInfo['comprador_telefono'], 0, 30);
        }
        if (isset($compradorInfo['comprador_lat']) && $compradorInfo['comprador_lat'] !== null) {
            $payload['comprador_lat'] = (float)$compradorInfo['comprador_lat'];
        }
        if (isset($compradorInfo['comprador_lng']) && $compradorInfo['comprador_lng'] !== null) {
            $payload['comprador_lng'] = (float)$compradorInfo['comprador_lng'];
        }

        foreach ($items as $item) {
            $productoId = (int)($item['producto_id'] ?? 0);
            $cantidad   = (float)($item['cantidad']   ?? 0);
            $precioUnit = (float)($item['precio_unit'] ?? 0);

            if ($productoId <= 0 || $cantidad <= 0 || $precioUnit <= 0) {
                return $this->errorResponse('Item inválido: producto_id, cantidad y precio_unit deben ser positivos');
            }

            $payload['items'][] = [
                'producto_id' => $productoId,
                'cantidad'    => $cantidad,
                'precio_unit' => $precioUnit,
            ];
        }

        return $this->request('POST', '/api/pedidos', $config, $payload);
    }

    /**
     * Cancelar un pedido en CarniHub (si aún no fue aprobado/procesado).
     *
     * @param  int $restauranteId       ID del restaurante en CapiRest
     * @param  int $pedidoCarnihubId    ID del pedido en CarniHub
     * @return array  ['success'=>bool, ...] | ['success'=>false, 'error'=>str]
     */
    public function cancelarPedido(int $restauranteId, int $pedidoCarnihubId): array
    {
        $config = $this->getConfig($restauranteId);
        if ($config === null) {
            return $this->errorResponse('No hay configuración de CarniHub para este restaurante');
        }

        if ($pedidoCarnihubId <= 0) {
            return $this->errorResponse('ID de pedido inválido');
        }

        // Intentar primero con DELETE, fallback a POST /cancelar
        $result = $this->request('DELETE', '/api/pedidos/' . $pedidoCarnihubId, $config);
        if (!($result['success'] ?? false) && ($result['http_code'] ?? 0) === 405) {
            // Algunos servidores usan POST /cancelar en lugar de DELETE
            $result = $this->request('POST', '/api/pedidos/' . $pedidoCarnihubId . '/cancelar', $config, []);
        }

        return $result;
    }

    /**
     * Consultar el estado de un pedido en CarniHub.
     *
     * @param  int $restauranteId        ID del restaurante en CapiRest
     * @param  int $pedidoCarnihubId     ID del pedido en CarniHub
     * @return array  ['success'=>bool, 'pedido'=>array] | ['success'=>false, 'error'=>str]
     */
    public function consultarPedido(int $restauranteId, int $pedidoCarnihubId): array
    {
        $config = $this->getConfig($restauranteId);
        if ($config === null) {
            return $this->errorResponse('No hay configuración de CarniHub para este restaurante');
        }

        if ($pedidoCarnihubId <= 0) {
            return $this->errorResponse('ID de pedido inválido');
        }

        return $this->request('GET', '/api/pedidos/' . $pedidoCarnihubId, $config);
    }

    /**
     * Buscar productos en el catálogo de CarniHub.
     *
     * @param  int    $restauranteId  ID del restaurante en CapiRest
     * @param  string $query          Término de búsqueda
     * @param  string $categoria      Slug de categoría (opcional)
     * @param  int    $page           Página (default 1)
     * @return array  ['success'=>bool, 'productos'=>array, 'total'=>int] | ['success'=>false, 'error'=>str]
     */
    public function buscarProducto(int $restauranteId, string $query, string $categoria = '', int $page = 1, int $limit = 20): array
    {
        $config = $this->getConfig($restauranteId);
        if ($config === null) {
            return $this->errorResponse('No hay configuración de CarniHub para este restaurante');
        }

        // Enviamos tanto `limit` como `per_page` porque distintas
        // versiones del API remoto aceptan uno u otro.
        $effLimit = min(max($limit, 1), 5000);
        $params = [
            'q'        => trim($query),
            'page'     => max(1, $page),
            'limit'    => $effLimit,
            'per_page' => $effLimit,
        ];
        if ($categoria !== '') {
            $params['categoria'] = $categoria;
        }

        return $this->request('GET', '/api/productos?' . http_build_query($params), $config);
    }

    /**
     * Obtener detalle de un producto por ID.
     *
     * @param  int $restauranteId    ID del restaurante en CapiRest
     * @param  int $carnihubProductoId  ID del producto en CarniHub
     * @return array  ['success'=>bool, 'producto'=>array] | ['success'=>false, 'error'=>str]
     */
    public function detalleProducto(int $restauranteId, int $carnihubProductoId): array
    {
        $config = $this->getConfig($restauranteId);
        if ($config === null) {
            return $this->errorResponse('No hay configuración de CarniHub para este restaurante');
        }

        if ($carnihubProductoId <= 0) {
            return $this->errorResponse('ID de producto inválido');
        }

        return $this->request('GET', '/api/productos/' . $carnihubProductoId, $config);
    }

    /**
     * Verificar que la conexión con CarniHub funciona.
     * Hace una búsqueda vacía como ping.
     *
     * @return array ['success'=>bool, 'latency_ms'=>int] | ['success'=>false, 'error'=>str]
     */
    public function testConexion(int $restauranteId): array
    {
        $config = $this->getConfig($restauranteId);
        if ($config === null) {
            return $this->errorResponse('No hay configuración de CarniHub para este restaurante');
        }

        $start  = microtime(true);
        $result = $this->request('GET', '/api/productos?per_page=1', $config);
        $ms     = (int)round((microtime(true) - $start) * 1000);

        if ($result['success']) {
            $result['latency_ms'] = $ms;
        }

        // Actualizar última sincronización si fue exitoso
        if ($result['success']) {
            try {
                $upd = Database::getInstance()->prepare(
                    "UPDATE carnihub_api_config SET ultima_sincronizacion = NOW() WHERE restaurante_id = ?"
                );
                $upd->execute([$restauranteId]);
            } catch (\Throwable) { /* silencioso */ }
        }

        return $result;
    }

    // ================================================================
    // Métodos privados
    // ================================================================

    /**
     * Carga la configuración API del restaurante desde BD.
     * Cachea en memoria para la request actual.
     */
    private function getConfig(int $restauranteId): ?array
    {
        if (array_key_exists($restauranteId, $this->configCache)) {
            return $this->configCache[$restauranteId];
        }

        try {
            $stmt = Database::getInstance()->prepare(
                "SELECT carnihub_url, api_key, carnihub_empresa_id, nombre_distribuidor
                   FROM carnihub_api_config
                  WHERE restaurante_id = ? AND activo = 1
                  LIMIT 1"
            );
            $stmt->execute([$restauranteId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            error_log('[CarniHubApiService::getConfig] ' . $e->getMessage());
            $row = false;
        }

        $this->configCache[$restauranteId] = $row ?: null;
        return $this->configCache[$restauranteId];
    }

    /**
     * Ejecuta una llamada HTTP a la API de CarniHub usando cURL.
     *
     * @param  string $method   'GET' | 'POST'
     * @param  string $path     Path relativo (empieza con /)
     * @param  array  $config   Fila de carnihub_api_config
     * @param  array  $data     Body para POST (se serializa a JSON)
     */
    private function request(string $method, string $path, array $config, array $data = []): array
    {
        // Construir URL con validación básica
        $baseUrl = rtrim($config['carnihub_url'], '/');
        $url     = $baseUrl . $path;

        if (strlen($url) > self::MAX_URL_LENGTH || !filter_var($baseUrl, FILTER_VALIDATE_URL)) {
            return $this->errorResponse('URL de CarniHub inválida o demasiado larga');
        }

        // Solo HTTPS en producción
        if (str_starts_with($url, 'http://') && !defined('CARNIHUB_ALLOW_HTTP')) {
            return $this->errorResponse('La URL de CarniHub debe usar HTTPS');
        }

        $ch = curl_init();

        $headers = [
            'Authorization: Bearer ' . $config['api_key'],
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Source: CapiRest',
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_FOLLOWLOCATION => true,    // Seguir redirects legítimos (http→https, trailing slash, etc.)
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        if ($method === 'POST') {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $rawBody   = curl_exec($ch);
        $httpCode  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($rawBody === false || $curlError !== '') {
            error_log("[CarniHubApiService] cURL error: {$curlError} | URL: {$url}");
            return $this->errorResponse('No se pudo conectar con CarniHub: ' . $curlError);
        }

        // Detectar respuesta HTML: ocurre cuando el token es inválido/expirado
        // y el servidor redirige (302→login) en lugar de devolver 401 JSON.
        $trimmed = ltrim((string)$rawBody);
        if ($trimmed !== '' && ($trimmed[0] === '<' || stripos($trimmed, '<html') !== false || stripos($trimmed, '<!doctype') !== false)) {
            error_log("[CarniHubApiService] Respuesta HTML (HTTP {$httpCode}) — token inválido o URL incorrecta | URL: {$url}");
            return $this->errorResponse('Token API de CarniHub inválido o expirado. Verifica la clave API en la configuración del restaurante.', $httpCode);
        }

        $decoded = json_decode($rawBody, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("[CarniHubApiService] Respuesta no JSON (HTTP {$httpCode}): " . substr($rawBody, 0, 200));
            return $this->errorResponse("CarniHub devolvió una respuesta inesperada (HTTP {$httpCode})");
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $errMsg = $decoded['error'] ?? "HTTP {$httpCode}";
            return $this->errorResponse("CarniHub respondió con error: {$errMsg}", $httpCode);
        }

        return array_merge(['success' => true], $decoded);
    }

    /** Construye un array de error estándar */
    private function errorResponse(string $message, int $httpCode = 0): array
    {
        return [
            'success'   => false,
            'error'     => $message,
            'http_code' => $httpCode,
        ];
    }
}
