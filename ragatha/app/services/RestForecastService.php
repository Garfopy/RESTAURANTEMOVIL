<?php
/**
 * RestForecastService
 *
 * Servicio de proyección inteligente de inventario para restaurantes.
 * Analiza el historial de consumo (rest_movimientos_inventario) para:
 *   - Calcular consumo promedio diario y promedio móvil
 *   - Proyectar días restantes de stock
 *   - Identificar ingredientes críticos basándose en lead time
 *   - Calcular cantidad óptima a pedir
 *
 * Compatible con futura IA predictiva (sólo ampliar calcularPromedioMovil).
 */
class RestForecastService
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    // ──────────────────────────────────────────────────────────────
    // CÁLCULOS CORE
    // ──────────────────────────────────────────────────────────────

    /**
     * Consumo total de un ingrediente en los últimos $dias días.
     * Solo considera movimientos tipo='salida' (consumo por pedidos + manual).
     *
     * @return float  Consumo total en unidad principal del ingrediente
     */
    public function consumoTotal(int $ingredienteId, int $restauranteId, int $dias = 7): float
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(cantidad), 0) AS total
             FROM rest_movimientos_inventario
             WHERE ingrediente_id   = ?
               AND restaurante_id   = ?
               AND tipo             = 'salida'
               AND created_at      >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$ingredienteId, $restauranteId, $dias]);
        return (float)($stmt->fetchColumn() ?? 0);
    }

    /**
     * Días distintos con al menos una salida en el período.
     * Evita dividir entre días sin actividad (ej: restaurante cerrado).
     */
    public function diasConActividad(int $ingredienteId, int $restauranteId, int $dias = 7): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT DATE(created_at)) AS dias
             FROM rest_movimientos_inventario
             WHERE ingrediente_id   = ?
               AND restaurante_id   = ?
               AND tipo             = 'salida'
               AND created_at      >= DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$ingredienteId, $restauranteId, $dias]);
        $d = (int)($stmt->fetchColumn() ?? 0);
        return max(1, $d); // al menos 1 para no dividir por 0
    }

    /**
     * Consumo promedio diario = consumo_total / ventana
     *
     * Dividimos entre el período completo (no solo días con actividad)
     * para evitar CPD inflado cuando solo hay 1-2 días con registros.
     * Ejemplo: 50 L en 1 día de los últimos 7 → CPD = 50/7 ≈ 7.14  (no 50).
     */
    public function calcularConsumoPromedioDiario(
        int $ingredienteId,
        int $restauranteId,
        int $ventana = 7
    ): float {
        $total = $this->consumoTotal($ingredienteId, $restauranteId, $ventana);
        if ($total <= 0) return 0.0;
        return $total / max(1, $ventana);
    }

    /**
     * Promedio móvil de los últimos $ventana días individualmente.
     * Fórmula: (día1 + día2 + ... + díaN) / N
     *
     * Retorna array con claves 'promedio' y 'dias' (consumo diario).
     */
    public function calcularPromedioMovil(
        int $ingredienteId,
        int $restauranteId,
        int $ventana = 3
    ): array {
        $stmt = $this->db->prepare(
            "SELECT DATE(created_at) AS dia, SUM(cantidad) AS total
             FROM rest_movimientos_inventario
             WHERE ingrediente_id   = ?
               AND restaurante_id   = ?
               AND tipo             = 'salida'
               AND created_at      >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY dia DESC
             LIMIT ?"
        );
        $stmt->execute([$ingredienteId, $restauranteId, $ventana, $ventana]);
        $filas = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($filas)) {
            return ['promedio' => 0.0, 'dias' => []];
        }

        $diasConsumo = array_column($filas, 'total', 'dia');
        $promedio = array_sum(array_column($filas, 'total')) / count($filas);

        return ['promedio' => round($promedio, 4), 'dias' => $diasConsumo];
    }

    /**
     * Días restantes de stock al ritmo actual.
     * stock_actual / consumo_promedio_diario
     * Retorna INF si el consumo es 0 (no se usa, no se agotará).
     */
    public function calcularDiasRestantes(float $stockActual, float $consumoPromedioDiario): float
    {
        if ($consumoPromedioDiario <= 0) return INF;
        if ($stockActual <= 0) return 0.0;
        return $stockActual / $consumoPromedioDiario;
    }

    /**
     * Cantidad óptima a pedir para cubrir el lead time + buffer de cobertura.
     * cantidad = (cpd * (leadTime + diasCobertura)) - stockActual
     * Retorna 0 si el stock ya es suficiente.
     *
     * $diasCobertura reducido a 3 (buffer razonable: leadTime + 3 días extra).
     */
    public function calcularCantidadPedir(
        float $consumoPromedioDiario,
        int   $leadTimeDias,
        float $stockActual,
        int   $diasCobertura = 3
    ): float {
        if ($consumoPromedioDiario <= 0) return 0.0;
        $necesario = $consumoPromedioDiario * ($leadTimeDias + $diasCobertura);
        return max(0.0, round($necesario - $stockActual, 3));
    }

    // ──────────────────────────────────────────────────────────────
    // PROYECCIÓN SEMANAL
    // ──────────────────────────────────────────────────────────────

    /**
     * Proyecta el stock para los próximos 7 días.
     * Retorna array de [fecha, consumo_estimado, stock_proyectado].
     */
    public function proyeccionSemanal(
        int   $ingredienteId,
        int   $restauranteId,
        float $stockActual,
        int   $ventanaHistorica = 7
    ): array {
        $cpd = $this->calcularConsumoPromedioDiario($ingredienteId, $restauranteId, $ventanaHistorica);

        $proyeccion  = [];
        $stockActual = max(0.0, $stockActual);

        for ($i = 1; $i <= 7; $i++) {
            $fecha          = date('Y-m-d', strtotime("+{$i} days"));
            $consumoEstimado = round($cpd, 4);
            $stockActual    = max(0.0, round($stockActual - $cpd, 4));
            $proyeccion[]   = [
                'fecha'            => $fecha,
                'consumo_estimado' => $consumoEstimado,
                'stock_proyectado' => $stockActual,
            ];
        }

        return $proyeccion;
    }

    // ──────────────────────────────────────────────────────────────
    // ANÁLISIS MASIVO POR RESTAURANTE
    // ──────────────────────────────────────────────────────────────

    /**
     * Calcula el forecast completo para todos los ingredientes activos
     * de un restaurante, enriqueciendo cada ingrediente con:
     *   - cpd           : consumo promedio diario
     *   - promedio_movil: promedio móvil 3 días
     *   - dias_restantes: días de stock disponibles
     *   - requiere_pedido: true si dias_restantes <= lead_time + 1
     *   - cantidad_sugerida: cuánto pedir
     *   - nivel_alerta  : 'critico' | 'advertencia' | 'ok' | 'sin_datos'
     *   - proyeccion_7d : array proyección semanal
     *   - empresa       : info de empresa proveedora (si tiene carnihub_producto_id)
     *
     * @param array $ingredientes Resultado de RestInventarioModel::getByRestaurante()
     */
    public function analizarIngredientes(array $ingredientes, int $restauranteId): array
    {
        if (empty($ingredientes)) return [];

        // Pre-cargar info de empresa para ingredientes con carnihub_producto_id
        $productoIds = array_filter(array_column($ingredientes, 'carnihub_producto_id'));
        $empresaMap  = $this->_cargarEmpresasPorProducto(array_values(array_unique($productoIds)), $restauranteId);

        $resultado = [];
        foreach ($ingredientes as $ing) {
            $ingId      = (int)$ing['id'];
            $stock      = (float)$ing['stock'];
            $leadTime   = (int)($ing['dias_entrega'] ?? 1);
            $prodId     = (int)($ing['carnihub_producto_id'] ?? 0);

            $cpd           = $this->calcularConsumoPromedioDiario($ingId, $restauranteId, 7);
            $movil         = $this->calcularPromedioMovil($ingId, $restauranteId, 3);
            $diasRestantes = $this->calcularDiasRestantes($stock, $cpd);
            $cantPedir     = $this->calcularCantidadPedir($cpd, $leadTime, $stock, 7);
            $proyeccion    = $this->proyeccionSemanal($ingId, $restauranteId, $stock, 7);

            // Determinar nivel de alerta
            if ($cpd <= 0) {
                $nivelAlerta = 'sin_datos';
                $requierePedido = false;
            } elseif ($diasRestantes <= $leadTime) {
                $nivelAlerta = 'critico';     // agotamiento antes de que llegue el pedido
                $requierePedido = true;
            } elseif ($diasRestantes <= $leadTime + 2) {
                $nivelAlerta = 'advertencia'; // margen muy ajustado
                $requierePedido = true;
            } else {
                $nivelAlerta = 'ok';
                $requierePedido = false;
            }

            $resultado[] = array_merge($ing, [
                'cpd'               => round($cpd, 4),
                'promedio_movil'    => $movil['promedio'],
                'dias_restantes'    => $diasRestantes === INF ? null : round($diasRestantes, 1),
                'requiere_pedido'   => $requierePedido,
                'cantidad_sugerida' => $cantPedir,
                'nivel_alerta'      => $nivelAlerta,
                'proyeccion_7d'     => $proyeccion,
                'empresa'           => $prodId ? ($empresaMap[$prodId] ?? null) : null,
            ]);
        }

        // Ordenar: crítico primero, luego advertencia, luego ok, sin_datos al final
        $orden = ['critico' => 0, 'advertencia' => 1, 'ok' => 2, 'sin_datos' => 3];
        usort($resultado, fn($a, $b) =>
            ($orden[$a['nivel_alerta']] ?? 4) <=> ($orden[$b['nivel_alerta']] ?? 4)
        );

        return $resultado;
    }

    /**
     * Filtra ingredientes que requieren pedido y tienen proveedor CarniHub.
     * Agrupa por empresa_id para generar un pedido sugerido por empresa.
     *
     * @return array  ['empresa_id' => ['empresa' => [...], 'items' => [...]]]
     */
    public function agruparPorEmpresa(array $ingredientesAnalizados): array
    {
        $grupos = [];
        foreach ($ingredientesAnalizados as $ing) {
            if (!$ing['requiere_pedido']) continue;
            if (empty($ing['empresa']) || empty($ing['carnihub_producto_id'])) continue;
            if ((float)$ing['cantidad_sugerida'] <= 0) continue;

            $eid = (int)$ing['empresa']['id'];
            if (!isset($grupos[$eid])) {
                $grupos[$eid] = ['empresa' => $ing['empresa'], 'items' => []];
            }
            $grupos[$eid]['items'][] = $ing;
        }
        return $grupos;
    }

    // ──────────────────────────────────────────────────────────────
    // HELPERS PRIVADOS
    // ──────────────────────────────────────────────────────────────

    /**
     * Carga empresa y precio_base de una lista de productos CarniHub.
     * En modo standalone usa carnihub_api_config + rest_ingredientes
     * (las tablas 'productos' y 'empresas' no existen en standalone).
     * Retorna [producto_id => empresa_array]
     */
    private function _cargarEmpresasPorProducto(array $productoIds, int $restauranteId = 0): array
    {
        if (empty($productoIds)) return [];

        // ── Modo standalone ───────────────────────────────────────────────────
        if (defined('RESTAURANTE_STANDALONE') && RESTAURANTE_STANDALONE) {
            $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
            $params       = array_values($productoIds);
            $whereRest    = '';
            if ($restauranteId > 0) {
                $whereRest = ' AND i.restaurante_id = ?';
                $params[]  = $restauranteId;
            }
            try {
                $stmt = $this->db->prepare(
                    "SELECT i.carnihub_producto_id AS producto_id,
                            COALESCE(c.carnihub_empresa_id, 0) AS id,
                            COALESCE(c.nombre_distribuidor, 'CarniHub') AS razon_social,
                            NULL AS email,
                            NULL AS telefono,
                            i.costo_unitario AS precio_base,
                            i.unidad_principal AS unidad
                     FROM rest_ingredientes i
                     LEFT JOIN carnihub_api_config c
                            ON c.restaurante_id = i.restaurante_id AND c.activo = 1
                     WHERE i.carnihub_producto_id IN ($placeholders){$whereRest}"
                );
                $stmt->execute($params);
            } catch (\Throwable $e) {
                error_log('[RestForecastService] _cargarEmpresasPorProducto (standalone): ' . $e->getMessage());
                return [];
            }
            $mapa = [];
            foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $pid = (int)$row['producto_id'];
                $mapa[$pid] = [
                    'id'           => (int)$row['id'],
                    'razon_social' => $row['razon_social'],
                    'email'        => $row['email'],
                    'telefono'     => $row['telefono'],
                    'precio_base'  => (float)$row['precio_base'],
                    'unidad'       => $row['unidad'],
                ];
            }
            return $mapa;
        }

        // ── Modo B2B (plataforma completa) ────────────────────────────────────
        $placeholders = implode(',', array_fill(0, count($productoIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT p.id AS producto_id, p.precio_base, p.presentacion AS unidad,
                    e.id, e.razon_social, e.email, e.telefono
             FROM productos p
             JOIN empresas e ON e.id = p.empresa_id
             WHERE p.id IN ($placeholders) AND p.activo = 1 AND e.activo = 1"
        );
        $stmt->execute($productoIds);

        $mapa = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $pid = (int)$row['producto_id'];
            $mapa[$pid] = [
                'id'           => (int)$row['id'],
                'razon_social' => $row['razon_social'],
                'email'        => $row['email'],
                'telefono'     => $row['telefono'],
                'precio_base'  => (float)$row['precio_base'],
                'unidad'       => $row['unidad'],
            ];
        }
        return $mapa;
    }

    // ──────────────────────────────────────────────────────────────
    // INTEGRACIÓN CARNIHUB — Convertir pedido sugerido en pedido B2B
    // ──────────────────────────────────────────────────────────────

    /**
     * Convierte un pedido sugerido aprobado en un pedido B2B real en CarniHub.
     *
     * Flujo:
     *   1. Carga el pedido sugerido y verifica que esté en estado 'aprobado'
     *   2. Filtra los items que tienen carnihub_producto_id asignado
     *   3. Llama a CarniHubApiService::crearPedido() con esos items
     *   4. Si tiene éxito, guarda el pedido_carnihub_id y cambia estado a 'convertido'
     *   5. Devuelve el resultado con detalles para mostrar al usuario
     *
     * @param  int $sugeridoId  ID de rest_pedidos_sugeridos
     * @return array ['success'=>bool, 'pedido_carnihub_id'=>int|null, 'error'=>str, 'items_enviados'=>int]
     */
    public function convertirAOrdenCarnihub(int $sugeridoId): array
    {
        // Cargar el pedido sugerido
        $sugerido = $this->db->query(
            "SELECT ps.*, r.id AS rest_id
               FROM rest_pedidos_sugeridos ps
               JOIN rest_restaurantes r ON r.id = ps.restaurante_id
              WHERE ps.id = ?
              LIMIT 1",
            [$sugeridoId]
        )->fetch(\PDO::FETCH_ASSOC);

        if (!$sugerido) {
            return ['success' => false, 'error' => 'Pedido sugerido no encontrado', 'items_enviados' => 0];
        }

        if ($sugerido['estado'] !== 'aprobado') {
            return [
                'success'      => false,
                'error'        => "El pedido debe estar en estado 'aprobado' (actual: {$sugerido['estado']})",
                'items_enviados' => 0,
            ];
        }

        if ($sugerido['pedido_carnihub_id']) {
            return [
                'success'             => false,
                'error'               => 'Este pedido ya fue convertido anteriormente',
                'pedido_carnihub_id'  => (int)$sugerido['pedido_carnihub_id'],
                'items_enviados'      => 0,
            ];
        }

        // Obtener items con producto en CarniHub
        $items = $this->db->query(
            "SELECT psi.id, psi.ingrediente_id, psi.carnihub_producto_id,
                    COALESCE(psi.cantidad_aprobada, psi.cantidad_sugerida) AS cantidad,
                    psi.precio_unit_estimado,
                    ri.nombre AS ingrediente_nombre
               FROM rest_pedido_sugerido_items psi
               JOIN rest_ingredientes ri ON ri.id = psi.ingrediente_id
              WHERE psi.pedido_sugerido_id = ?
                AND psi.carnihub_producto_id IS NOT NULL",
            [$sugeridoId]
        )->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($items)) {
            return [
                'success'        => false,
                'error'          => 'Ningún item tiene un producto de CarniHub asignado. Vincula ingredientes primero.',
                'items_enviados' => 0,
            ];
        }

        // Preparar payload para la API
        $apiItems = array_map(fn($item) => [
            'producto_id' => (int)$item['carnihub_producto_id'],
            'cantidad'    => (float)$item['cantidad'],
            'precio_unit' => (float)$item['precio_unit_estimado'],
        ], $items);

        $notas = "CapiRest pedido sugerido #{$sugeridoId} — " . date('d/m/Y H:i');

        // Llamar a la API de CarniHub
        $apiService = new CarniHubApiService();
        $resultado  = $apiService->crearPedido((int)$sugerido['restaurante_id'], $apiItems, $notas);

        if (!$resultado['success']) {
            return array_merge($resultado, ['items_enviados' => 0]);
        }

        $pedidoCarnihubId = (int)($resultado['pedido_id'] ?? 0);

        // Persistir el ID del pedido en CarniHub y cambiar estado
        try {
            $this->db->query(
                "UPDATE rest_pedidos_sugeridos
                    SET estado = 'convertido',
                        pedido_carnihub_id = ?,
                        aprobado_at = COALESCE(aprobado_at, NOW())
                  WHERE id = ?",
                [$pedidoCarnihubId, $sugeridoId]
            );
        } catch (\Throwable $e) {
            error_log('[RestForecastService::convertirAOrdenCarnihub] Error al guardar pedido_carnihub_id: ' . $e->getMessage());
            // El pedido se creó en CarniHub — devolver éxito con advertencia
            return [
                'success'            => true,
                'pedido_carnihub_id' => $pedidoCarnihubId,
                'folio'              => $resultado['folio'] ?? null,
                'items_enviados'     => count($apiItems),
                'advertencia'        => 'El pedido se creó en CarniHub pero no se pudo guardar el ID localmente. Anotarlo manualmente.',
            ];
        }

        return [
            'success'            => true,
            'pedido_carnihub_id' => $pedidoCarnihubId,
            'folio'              => $resultado['folio'] ?? null,
            'estado'             => 'convertido',
            'items_enviados'     => count($apiItems),
        ];
    }
}
