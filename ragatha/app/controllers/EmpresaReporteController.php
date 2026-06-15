<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaReporteController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAuth();
    }

    public function index(?string $p = null): void
    {
        $rol      = $this->rolActual();
        $empresaId = $this->empresaId();
        $usuarioId = $this->usuarioId() ?? 0;

        $hoy = date('Y-m-d');

        // ── Presets de período (alineado con el dashboard del Supervisor) ──
        $periodo = (string)$this->get('periodo', '30d');
        $periodosValidos = ['hoy','7d','30d','90d','año','custom'];
        if (!in_array($periodo, $periodosValidos, true)) {
            $periodo = '30d';
        }

        switch ($periodo) {
            case 'hoy':
                $desde = $hoy; $hasta = $hoy;
                $labelPeriodo = 'Hoy';
                break;
            case '7d':
                $desde = date('Y-m-d', strtotime('-6 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 7 días';
                break;
            case '90d':
                $desde = date('Y-m-d', strtotime('-89 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 90 días';
                break;
            case 'año':
                $desde = date('Y-01-01'); $hasta = $hoy;
                $labelPeriodo = 'Este año';
                break;
            case 'custom':
                $desde = (string)$this->get('fecha_desde', date('Y-m-d', strtotime('-29 days')));
                $hasta = (string)$this->get('fecha_hasta', $hoy);
                $labelPeriodo = 'Período personalizado';
                break;
            default:
                $desde = date('Y-m-d', strtotime('-29 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 30 días';
        }

        // Si vienen fechas explícitas (compatibilidad), respetarlas y forzar custom
        $fechaDesdeIn = $this->get('fecha_desde');
        $fechaHastaIn = $this->get('fecha_hasta');
        if ($periodo === 'custom' || $fechaDesdeIn !== null || $fechaHastaIn !== null) {
            if ($fechaDesdeIn !== null) {
                $desde = (string)$fechaDesdeIn;
            }
            if ($fechaHastaIn !== null) {
                $hasta = (string)$fechaHastaIn;
            }
            if ($fechaDesdeIn !== null || $fechaHastaIn !== null) {
                $periodo = 'custom';
                $labelPeriodo = 'Período personalizado';
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) {
            $desde = date('Y-m-d', strtotime('-29 days'));
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $hasta = $hoy;
        }
        if ($desde > $hasta) {
            [$desde, $hasta] = [$hasta, $desde];
        }

        $mostrarInput = $this->get('mostrar', ['logo', 'kpis', 'graficas', 'tabla', 'notas']);
        $mostrar = is_array($mostrarInput) ? $mostrarInput : [$mostrarInput];
        $mostrar = array_values(array_intersect($mostrar, ['logo', 'kpis', 'graficas', 'tabla', 'notas']));
        if (empty($mostrar)) {
            $mostrar = ['logo', 'kpis', 'graficas', 'tabla', 'notas'];
        }

        $reporte = $this->armarReporte($rol, (int)$empresaId, $usuarioId, $desde, $hasta);

        $configModel = new ConfigModel();
        $logoUrl = $configModel->get('app_logo', BASE_URL . 'public/img/logo.svg');
        if (!$logoUrl) {
            $logoUrl = BASE_URL . 'public/img/logo.svg';
        }

        $fechaReporte = $this->fechaEspanol(date('Y-m-d'));
        $reportId = '#CH-' . date('Ymd-His') . '-' . str_pad((string)$usuarioId, 3, '0', STR_PAD_LEFT) . '-' . strtoupper(bin2hex(random_bytes(2)));

        $filtros = [
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'mostrar' => $mostrar,
            'periodo' => $periodo,
            'label_periodo' => $labelPeriodo,
        ];

        $tituloReporte = $reporte['titulo'];
        $kpis = $reporte['kpis'];
        $columnas = $reporte['columnas'];
        $filas = $reporte['filas'];
        $notas = $reporte['notas'];
        $graficas = $reporte['graficas'] ?? [];

        if (in_array($rol, ['superadmin', 'admin'], true)) {
            $flash = $this->getFlash();
            $pageTitle = 'Reportes';
            $activeMenu = 'reportes';
            ob_start();
            require ROOT_PATH . '/app/views/reportes/tecnico.php';
            $content = ob_get_clean();
            require ROOT_PATH . '/app/views/panel/layouts/main.php';
            return;
        }

        if (in_array($rol, ['admin_empresa', 'supervisor', 'comprador'], true)) {
            $flash = $this->getFlash();
            $pageTitle = 'Reportes';
            $activeMenu = 'reportes';
            if ($rol === 'supervisor') {
                $pendientes = (new PedidoModel())->pendientesAprobacion((int)$empresaId);
                $countPendientesSidebar = count($pendientes);
            }
            ob_start();
            require ROOT_PATH . '/app/views/reportes/tecnico.php';
            $content = ob_get_clean();
            require ROOT_PATH . '/app/views/empresa/layouts/main.php';
            return;
        }

        if ($rol === 'repartidor') {
            $flash = $this->getFlash();
            $pageTitle = 'Reporte de rendimiento';
            // Render standalone (sin sidebar de empresa) porque el repartidor usa UI móvil
            require ROOT_PATH . '/app/views/repartidor/reporte_layout.php';
            return;
        }

        $this->redirectSegunRol($rol);
    }

    public function descargarPdf(?string $p = null): void
    {
        $this->redirect('empresa-reporte/index');
    }

    private function armarReporte(string $rol, int $empresaId, int $usuarioId, string $desde, string $hasta): array
    {
        $db = Database::getInstance();

        if (in_array($rol, ['superadmin', 'admin'], true)) {
            $kpiRow = $db->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM empresas WHERE activo = 1) AS empresas_activas,
                    (SELECT COUNT(*) FROM usuarios WHERE activo = 1) AS usuarios_activos,
                    COUNT(*) AS pedidos,
                    COALESCE(SUM(CASE WHEN estado != 'cancelado' THEN total ELSE 0 END),0) AS ingresos
                 FROM pedidos
                 WHERE DATE(created_at) BETWEEN ? AND ?"
            );
            $kpiRow->execute([$desde, $hasta]);
            $r = $kpiRow->fetch() ?: [];

            $stmt = $db->prepare(
                "SELECT DATE(p.created_at) AS fecha, e.razon_social, p.folio, p.estado, p.total,
                        CONCAT(u.nombre, ' ', u.apellido_paterno) AS responsable
                   FROM pedidos p
                   JOIN empresas e ON e.id = p.empresa_id
                   JOIN usuarios u ON u.id = p.comprador_id
                  WHERE DATE(p.created_at) BETWEEN ? AND ?
               ORDER BY p.created_at DESC
                  LIMIT 120"
            );
            $stmt->execute([$desde, $hasta]);
            $rows = $stmt->fetchAll();

            $trendStmt = $db->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS pedidos, COALESCE(SUM(CASE WHEN estado != 'cancelado' THEN total ELSE 0 END),0) AS ingresos
                   FROM pedidos
                  WHERE DATE(created_at) BETWEEN ? AND ?
               GROUP BY DATE(created_at)
               ORDER BY DATE(created_at)"
            );
            $trendStmt->execute([$desde, $hasta]);
            $trend = $trendStmt->fetchAll();

            $estStmt = $db->prepare(
                "SELECT estado, COUNT(*) AS c FROM pedidos
                  WHERE DATE(created_at) BETWEEN ? AND ?
               GROUP BY estado"
            );
            $estStmt->execute([$desde, $hasta]);
            $est = $estStmt->fetchAll();

            return [
                'titulo' => 'Reporte Ejecutivo SaaS',
                'kpis' => [
                    ['label' => 'Empresas activas', 'valor' => number_format((int)($r['empresas_activas'] ?? 0)), 'hint' => 'Clientes vigentes'],
                    ['label' => 'Usuarios activos', 'valor' => number_format((int)($r['usuarios_activos'] ?? 0)), 'hint' => 'Plataforma completa'],
                    ['label' => 'Pedidos del período', 'valor' => number_format((int)($r['pedidos'] ?? 0)), 'hint' => 'Transacciones'],
                    ['label' => 'Ingresos del período', 'valor' => '$' . number_format((float)($r['ingresos'] ?? 0), 2), 'hint' => 'Sin cancelados'],
                ],
                'columnas' => ['Fecha', 'Empresa', 'Folio', 'Estado', 'Total', 'Responsable'],
                'filas' => array_map(fn($x) => [
                    $x['fecha'],
                    $x['razon_social'],
                    $x['folio'],
                    strtoupper((string)$x['estado']),
                    '$' . number_format((float)$x['total'], 2),
                    trim((string)$x['responsable']),
                ], $rows),
                'notas' => [
                    'Monitorear variaciones de demanda por empresa para ajustar capacidad de distribución.',
                    'Validar stock crítico en centros de mayor rotación antes del siguiente corte operativo.',
                    'Rango técnico recomendado de conservación para cadena fría: 0°C a 4°C.',
                ],
                'graficas' => [
                    [
                        'tipo' => 'line',
                        'titulo' => 'Tendencia diaria de pedidos',
                        'labels' => array_map(fn($x) => $x['d'], $trend),
                        'data' => array_map(fn($x) => (int)$x['pedidos'], $trend),
                        'label' => 'Pedidos',
                    ],
                    [
                        'tipo' => 'doughnut',
                        'titulo' => 'Distribución por estado',
                        'labels' => array_map(fn($x) => strtoupper((string)$x['estado']), $est),
                        'data' => array_map(fn($x) => (int)$x['c'], $est),
                        'label' => 'Pedidos',
                    ],
                ],
            ];
        }

        if ($rol === 'comprador') {
            $pedidoModel = new PedidoModel();

            $kpiStmt = $db->prepare(
                "SELECT COUNT(*) AS pedidos,
                        COALESCE(SUM(total),0) AS gasto,
                        COALESCE(AVG(total),0) AS ticket,
                        SUM(estado = 'en_ruta') AS en_ruta
                   FROM pedidos
                  WHERE empresa_id = ? AND comprador_id = ?
                    AND DATE(created_at) BETWEEN ? AND ?"
            );
            $kpiStmt->execute([$empresaId, $usuarioId, $desde, $hasta]);
            $r = $kpiStmt->fetch() ?: [];

            $stmt = $db->prepare(
                "SELECT DATE(created_at) AS fecha, folio, estado, total,
                        COALESCE(tipo_entrega, 'n/d') AS tipo_entrega,
                        COALESCE(DATE(fecha_entrega), '-') AS fecha_entrega
                   FROM pedidos
                  WHERE empresa_id = ? AND comprador_id = ?
                    AND DATE(created_at) BETWEEN ? AND ?
               ORDER BY created_at DESC
                  LIMIT 120"
            );
            $stmt->execute([$empresaId, $usuarioId, $desde, $hasta]);
            $rows = $stmt->fetchAll();

            $trendStmt = $db->prepare(
                "SELECT DATE(created_at) AS d, COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS gasto
                   FROM pedidos
                  WHERE empresa_id = ? AND comprador_id = ?
                    AND DATE(created_at) BETWEEN ? AND ?
               GROUP BY DATE(created_at)
               ORDER BY DATE(created_at)"
            );
            $trendStmt->execute([$empresaId, $usuarioId, $desde, $hasta]);
            $trend = $trendStmt->fetchAll();

            $estStmt = $db->prepare(
                "SELECT estado, COUNT(*) AS c FROM pedidos
                  WHERE empresa_id = ? AND comprador_id = ?
                    AND DATE(created_at) BETWEEN ? AND ?
               GROUP BY estado"
            );
            $estStmt->execute([$empresaId, $usuarioId, $desde, $hasta]);
            $est = $estStmt->fetchAll();

            // Nuevos KPIs estratégicos del Comprador
            $kgMes              = $pedidoModel->kgTotalesMes($usuarioId, (int)$empresaId);
            $gastoMes           = $pedidoModel->gastoMesComprador($usuarioId, (int)$empresaId);
            $enTransitoGps      = $pedidoModel->pedidosEnTransitoConGps($usuarioId, (int)$empresaId);
            $ahorroVolumen      = $pedidoModel->ahorroPorVolumen($usuarioId, (int)$empresaId, $desde, $hasta);
            $recurrentesActivos = $pedidoModel->recurrentesActivos((int)$empresaId);
            $proxima            = $pedidoModel->proximaEntregaComprador($usuarioId, (int)$empresaId);
            $topProd            = $pedidoModel->topProductoComprador($usuarioId, (int)$empresaId, $desde, $hasta);
            $consumoCat         = $pedidoModel->consumoPorCategoriaComprador($usuarioId, (int)$empresaId, $desde, $hasta);
            $gastoSemanal       = $pedidoModel->gastoSemanalComprador($usuarioId, (int)$empresaId, 8);

            // Presupuesto = suma de límites mensuales activos de la empresa (referencia)
            $presupuestoStmt = $db->prepare(
                "SELECT COALESCE(SUM(limite_monto),0) AS p
                   FROM limites_compra
                  WHERE empresa_id = ? AND activo = 1 AND periodo = 'mensual'"
            );
            $presupuestoStmt->execute([$empresaId]);
            $presupuesto = (float)$presupuestoStmt->fetchColumn();
            $pctPresupuesto = $presupuesto > 0 ? min(100, ($gastoMes / $presupuesto) * 100) : 0.0;

            $proximaTxt = '—';
            if ($proxima) {
                if (!empty($proxima['fecha_entrega'])) {
                    $proximaTxt = date('d/m H:i', strtotime((string)$proxima['fecha_entrega']));
                } elseif (!empty($proxima['eta_minutos'])) {
                    $proximaTxt = 'ETA ' . (int)$proxima['eta_minutos'] . ' min';
                } else {
                    $proximaTxt = strtoupper((string)$proxima['estado']);
                }
            }

            $topProdNombre = $topProd ? (string)$topProd['nombre'] : '—';

            return [
                'titulo' => 'Reporte de Compras y Abasto',
                'kpis' => [
                    ['label' => 'Kilos totales (mes)', 'valor' => number_format($kgMes, 2) . ' kg', 'hint' => 'Volumen para escalonado'],
                    ['label' => 'Gasto del mes', 'valor' => '$' . number_format($gastoMes, 2), 'hint' => $presupuesto > 0
                        ? 'de $' . number_format($presupuesto, 0) . ' (' . number_format($pctPresupuesto, 1) . '%)'
                        : 'Sin presupuesto configurado'],
                    ['label' => 'Pedidos en tránsito', 'valor' => number_format($enTransitoGps), 'hint' => 'con rastreo GPS activo'],
                    ['label' => 'Ahorro por volumen', 'valor' => '$' . number_format($ahorroVolumen, 2), 'hint' => 'Precios escalonados'],
                    ['label' => 'Recurrentes activos', 'valor' => number_format($recurrentesActivos), 'hint' => 'Plantillas automáticas'],
                    ['label' => 'Próxima entrega', 'valor' => $proximaTxt, 'hint' => $proxima ? '#' . ($proxima['folio'] ?? '') : 'Sin programada'],
                    ['label' => 'Top producto', 'valor' => mb_strimwidth($topProdNombre, 0, 20, '…'), 'hint' => 'Más comprado'],
                    ['label' => 'Ticket promedio', 'valor' => '$' . number_format((float)($r['ticket'] ?? 0), 2), 'hint' => 'Costo por pedido'],
                ],
                'columnas' => ['Fecha', 'Folio', 'Estado', 'Total', 'Tipo entrega', 'Entrega programada'],
                'filas' => array_map(fn($x) => [
                    $x['fecha'],
                    $x['folio'],
                    strtoupper((string)$x['estado']),
                    '$' . number_format((float)$x['total'], 2),
                    strtoupper((string)$x['tipo_entrega']),
                    $x['fecha_entrega'],
                ], $rows),
                'notas' => [
                    'Volumen del mes: ' . number_format($kgMes, 2) . ' kg. Acumular más kilos puede activar mejores precios escalonados.',
                    'Ahorro acumulado por escalonado en el período: $' . number_format($ahorroVolumen, 2) . '.',
                    $presupuesto > 0
                        ? ('Has consumido ' . number_format($pctPresupuesto, 1) . '% del presupuesto mensual configurado ($' . number_format($presupuesto, 2) . ').')
                        : 'No hay presupuesto mensual configurado en límites de compra.',
                    'Programar compras de alto volumen en ventanas de menor saturación logística.',
                    'Revisar pedidos recurrentes para automatizar reposición.',
                ],
                'graficas' => [
                    [
                        'tipo' => 'line',
                        'titulo' => 'Gasto diario del período',
                        'labels' => array_map(fn($x) => $x['d'], $trend),
                        'data' => array_map(fn($x) => (float)$x['gasto'], $trend),
                        'label' => 'Gasto ($)',
                    ],
                    [
                        'tipo' => 'doughnut',
                        'titulo' => 'Consumo por categoría',
                        'labels' => array_map(fn($x) => (string)$x['categoria'], $consumoCat),
                        'data' => array_map(fn($x) => (float)$x['monto'], $consumoCat),
                        'label' => 'Monto ($)',
                    ],
                    [
                        'tipo' => 'bar',
                        'titulo' => 'Historial de gasto semanal',
                        'labels' => array_map(fn($x) => 'Sem ' . substr((string)$x['yw'], 4) . ' (' . $x['desde'] . ')', $gastoSemanal),
                        'data' => array_map(fn($x) => (float)$x['gasto'], $gastoSemanal),
                        'label' => 'Gasto semanal ($)',
                    ],
                    [
                        'tipo' => 'doughnut',
                        'titulo' => 'Pedidos por estado',
                        'labels' => array_map(fn($x) => strtoupper((string)$x['estado']), $est),
                        'data' => array_map(fn($x) => (int)$x['c'], $est),
                        'label' => 'Pedidos',
                    ],
                ],
            ];
        }

        if ($rol === 'repartidor') {
            $pedidoModel = new PedidoModel();

            $kpiStmt = $db->prepare(
                "SELECT COUNT(*) AS paradas,
                        SUM(rd.estado = 'entregado') AS entregadas,
                        SUM(rd.estado != 'entregado') AS pendientes
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
                  WHERE r.repartidor_id = ?
                    AND DATE(r.fecha) BETWEEN ? AND ?"
            );
            $kpiStmt->execute([$usuarioId, $desde, $hasta]);
            $r = $kpiStmt->fetch() ?: [];
            $paradas = (int)($r['paradas'] ?? 0);
            $entregadas = (int)($r['entregadas'] ?? 0);
            $pendientes = (int)($r['pendientes'] ?? 0);
            $cumplimiento = $paradas > 0 ? ($entregadas / $paradas) * 100 : 0.0;

            $stmt = $db->prepare(
                "SELECT DATE(r.fecha) AS fecha, CONCAT('RUTA-', r.id) AS ruta,
                        p.folio, s.nombre AS sucursal,
                        rd.estado,
                        COALESCE(DATE_FORMAT(rd.hora_entrega, '%H:%i'), '-') AS hora_entrega
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
                   JOIN pedidos p ON p.id = rd.pedido_id
                   JOIN sucursales s ON s.id = rd.sucursal_id
                  WHERE r.repartidor_id = ?
                    AND DATE(r.fecha) BETWEEN ? AND ?
               ORDER BY r.fecha DESC, rd.orden ASC
                  LIMIT 80"
            );
            $stmt->execute([$usuarioId, $desde, $hasta]);
            $rows = $stmt->fetchAll();

            // KPIs adicionales
            $evidencia    = $pedidoModel->cumplimientoEvidencia($usuarioId, $desde, $hasta);
            $incidencias  = $pedidoModel->incidenciasRutaRepartidor($usuarioId, $desde, $hasta);
            $tiempoProm   = $pedidoModel->tiempoPromedioPorParada($usuarioId, $desde, $hasta);
            $prodSemanal  = $pedidoModel->productividadSemanalRepartidor($usuarioId, 6);

            return [
                'titulo' => 'Reporte Semanal de Rendimiento — Repartidor',
                'kpis' => [
                    ['label' => 'Paradas del período', 'valor' => number_format($paradas), 'hint' => 'Total programado'],
                    ['label' => 'Entregas exitosas', 'valor' => number_format($entregadas), 'hint' => number_format($cumplimiento, 1) . '% del total'],
                    ['label' => 'Cumplimiento de evidencia', 'valor' => number_format($evidencia['pct'], 1) . '%', 'hint' => $evidencia['completas'] . ' de ' . $evidencia['entregadas'] . ' con foto+firma'],
                    ['label' => 'Incidencias', 'valor' => number_format($incidencias), 'hint' => 'Fallidas o parciales'],
                    ['label' => 'Productividad de ruta', 'valor' => number_format($cumplimiento, 1) . '%', 'hint' => 'Entregadas / programadas'],
                    ['label' => 'Tiempo promedio/parada', 'valor' => $tiempoProm > 0 ? number_format($tiempoProm, 0) . ' min' : '—', 'hint' => 'Entre entregas consecutivas'],
                ],
                'columnas' => ['Fecha', 'Ruta', 'Pedido', 'Sucursal', 'Estado', 'Hora entrega'],
                'filas' => array_map(fn($x) => [
                    $x['fecha'],
                    $x['ruta'],
                    $x['folio'],
                    $x['sucursal'],
                    strtoupper((string)$x['estado']),
                    $x['hora_entrega'],
                ], $rows),
                'notas' => [
                    'Cumplimiento general del período: ' . number_format($cumplimiento, 1) . '% (' . $entregadas . ' de ' . $paradas . ' paradas).',
                    'Cumplimiento de evidencia (foto + firma): ' . number_format($evidencia['pct'], 1) . '%. Asegura el respaldo de cada entrega para validar tu pago.',
                    'Incidencias en el período: ' . number_format($incidencias) . ' (paradas fallidas o parciales).',
                    'Mantén la cadena de frío: 0°C a 4°C durante toda la ruta.',
                    'Prioriza perecederos en la primera mitad de la ruta para reducir riesgos.',
                ],
                'graficas' => [
                    [
                        'tipo' => 'bar',
                        'titulo' => 'Productividad semanal (entregadas vs intentos)',
                        'labels' => array_map(fn($x) => 'Sem ' . substr((string)$x['yw'], 4) . ' (' . $x['desde'] . ')', $prodSemanal),
                        'datasets' => [
                            ['label' => 'Entregadas', 'data' => array_map(fn($x) => (int)$x['entregadas'], $prodSemanal), 'color' => '#10B981'],
                            ['label' => 'Intentos',   'data' => array_map(fn($x) => (int)$x['intentos'],   $prodSemanal), 'color' => '#1D4ED8'],
                        ],
                    ],
                ],
            ];
        }

        if ($rol === 'admin_empresa') {
            $kpiStmt = $db->prepare(
                "SELECT COUNT(*) AS pedidos,
                        COALESCE(SUM(total),0) AS monto,
                        COALESCE(SUM(CASE WHEN estado = 'entregado' THEN total ELSE 0 END),0) AS facturado,
                        COALESCE(SUM(CASE WHEN estado IN ('confirmado','en_preparacion','en_ruta') THEN total ELSE 0 END),0) AS pendiente_cobro,
                        SUM(estado = 'entregado') AS entregados,
                        COALESCE(AVG(total),0) AS ticket
                   FROM pedidos
                  WHERE empresa_id = ?
                    AND DATE(created_at) BETWEEN ? AND ?"
            );
            $kpiStmt->execute([$empresaId, $desde, $hasta]);
            $r = $kpiStmt->fetch() ?: [];

            $slaStmt = $db->prepare(
                "SELECT
                    SUM(CASE WHEN rd.estado='entregado' AND p.fecha_entrega IS NOT NULL AND DATE(rd.hora_entrega) <= p.fecha_entrega THEN 1 ELSE 0 END) AS a_tiempo,
                    SUM(CASE WHEN rd.estado='entregado' THEN 1 ELSE 0 END) AS entregadas
                   FROM ruta_detalle rd
                   JOIN rutas r ON r.id = rd.ruta_id
                   JOIN pedidos p ON p.id = rd.pedido_id
                  WHERE p.empresa_id = ?
                    AND DATE(r.fecha) BETWEEN ? AND ?"
            );
            $slaStmt->execute([$empresaId, $desde, $hasta]);
            $sla = $slaStmt->fetch() ?: ['a_tiempo'=>0,'entregadas'=>0];
            $entregadasTot = (int)($sla['entregadas'] ?? 0);
            $slaPct = $entregadasTot > 0 ? ((int)($sla['a_tiempo'] ?? 0) / $entregadasTot) * 100 : 0.0;

            $stockStmt = $db->prepare(
                "SELECT COUNT(*) FROM inventario inv
                   JOIN productos p ON p.id = inv.producto_id
                  WHERE p.empresa_id = ? AND p.activo = 1
                    AND inv.stock <= inv.umbral_minimo"
            );
            $stockStmt->execute([$empresaId]);
            $stockCritico = (int)$stockStmt->fetchColumn();

            $semanaStmt = $db->prepare(
                "SELECT YEARWEEK(created_at, 3) AS yw, MIN(DATE(created_at)) AS desde,
                        COALESCE(SUM(CASE WHEN estado != 'cancelado' THEN total ELSE 0 END),0) AS ingresos
                   FROM pedidos
                  WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?
               GROUP BY YEARWEEK(created_at, 3)
               ORDER BY yw"
            );
            $semanaStmt->execute([$empresaId, $desde, $hasta]);
            $semanas = $semanaStmt->fetchAll();

            $estStmt = $db->prepare(
                "SELECT estado, COUNT(*) AS c FROM pedidos
                  WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?
               GROUP BY estado"
            );
            $estStmt->execute([$empresaId, $desde, $hasta]);
            $est = $estStmt->fetchAll();

            $topProdStmt = $db->prepare(
                "SELECT pr.nombre, COALESCE(SUM(pd.cantidad),0) AS volumen, COALESCE(SUM(pd.subtotal),0) AS monto
                   FROM pedido_detalle pd
                   JOIN pedidos p ON p.id = pd.pedido_id
                   JOIN productos pr ON pr.id = pd.producto_id
                  WHERE p.empresa_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
               GROUP BY pr.id, pr.nombre
               ORDER BY volumen DESC LIMIT 5"
            );
            $topProdStmt->execute([$empresaId, $desde, $hasta]);
            $topProd = $topProdStmt->fetchAll();

            $clientesStmt = $db->prepare(
                "SELECT CONCAT(u.nombre, ' ', u.apellido_paterno) AS cliente,
                        COUNT(*) AS pedidos,
                        COALESCE(SUM(p.total),0) AS gasto
                   FROM pedidos p
                   JOIN usuarios u ON u.id = p.comprador_id
                  WHERE p.empresa_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
                    AND p.estado != 'cancelado'
               GROUP BY u.id, u.nombre, u.apellido_paterno
               ORDER BY gasto DESC LIMIT 12"
            );
            $clientesStmt->execute([$empresaId, $desde, $hasta]);
            $clientes = $clientesStmt->fetchAll();

            $stmt = $db->prepare(
                "SELECT DATE(p.created_at) AS fecha, p.folio,
                        CONCAT(u.nombre, ' ', u.apellido_paterno) AS comprador,
                        p.estado, p.total,
                        COUNT(DISTINCT ps.id) AS sucursales
                   FROM pedidos p
                   JOIN usuarios u ON u.id = p.comprador_id
              LEFT JOIN pedido_sucursal ps ON ps.pedido_id = p.id
                  WHERE p.empresa_id = ? AND DATE(p.created_at) BETWEEN ? AND ?
               GROUP BY p.id
               ORDER BY p.created_at DESC LIMIT 120"
            );
            $stmt->execute([$empresaId, $desde, $hasta]);
            $rows = $stmt->fetchAll();

            return [
                'titulo' => 'Reporte Ejecutivo de Empresa',
                'kpis' => [
                    ['label' => 'Pedidos del período', 'valor' => number_format((int)($r['pedidos'] ?? 0)), 'hint' => 'Operación total'],
                    ['label' => 'Monto facturado', 'valor' => '$' . number_format((float)($r['facturado'] ?? 0), 2), 'hint' => 'Pedidos entregados'],
                    ['label' => 'Saldo pendiente cobro', 'valor' => '$' . number_format((float)($r['pendiente_cobro'] ?? 0), 2), 'hint' => 'Confirmados sin entregar'],
                    ['label' => 'Cumplimiento SLA', 'valor' => number_format($slaPct, 1) . '%', 'hint' => 'Meta ideal 98.5%'],
                    ['label' => 'Inventario crítico', 'valor' => number_format($stockCritico), 'hint' => 'Productos en alerta'],
                    ['label' => 'Entregados', 'valor' => number_format((int)($r['entregados'] ?? 0)), 'hint' => 'Pedidos cerrados'],
                    ['label' => 'Ticket promedio', 'valor' => '$' . number_format((float)($r['ticket'] ?? 0), 2), 'hint' => 'Monto por pedido'],
                    ['label' => 'Monto total período', 'valor' => '$' . number_format((float)($r['monto'] ?? 0), 2), 'hint' => 'Venta acumulada'],
                ],
                'columnas' => ['Fecha', 'Folio', 'Comprador', 'Estado', 'Total', 'Sucursales'],
                'filas' => array_map(fn($x) => [
                    $x['fecha'],
                    $x['folio'],
                    trim((string)$x['comprador']),
                    strtoupper((string)$x['estado']),
                    '$' . number_format((float)$x['total'], 2),
                    (string)$x['sucursales'],
                ], $rows),
                'notas' => [
                    'Saldo pendiente de cobro acumulado: $' . number_format((float)($r['pendiente_cobro'] ?? 0), 2) . '. Priorizar gestión de cobranza.',
                    'Cumplimiento SLA actual: ' . number_format($slaPct, 1) . '%. Meta operativa recomendada: 98.5%.',
                    'Productos en stock crítico: ' . number_format($stockCritico) . '. Coordinar reabastecimiento con producción.',
                    'Concentrar atención comercial en los clientes "VIP" identificados en la gráfica de burbujas.',
                    'Rango técnico de conservación para cadena fría: 0°C a 4°C.',
                ],
                'graficas' => [
                    [
                        'tipo' => 'line',
                        'titulo' => 'Tendencia de ventas semanal',
                        'labels' => array_map(fn($x) => 'Sem ' . substr((string)$x['yw'], 4) . ' (' . $x['desde'] . ')', $semanas),
                        'data' => array_map(fn($x) => (float)$x['ingresos'], $semanas),
                        'label' => 'Ingresos ($)',
                    ],
                    [
                        'tipo' => 'doughnut',
                        'titulo' => 'Distribución de pedidos por estado',
                        'labels' => array_map(fn($x) => strtoupper((string)$x['estado']), $est),
                        'data' => array_map(fn($x) => (int)$x['c'], $est),
                        'label' => 'Pedidos',
                    ],
                    [
                        'tipo' => 'barH',
                        'titulo' => 'Top 5 productos más vendidos (volumen)',
                        'labels' => array_map(fn($x) => (string)$x['nombre'], $topProd),
                        'data' => array_map(fn($x) => (float)$x['volumen'], $topProd),
                        'label' => 'Volumen (kg)',
                    ],
                    [
                        'tipo' => 'gauge',
                        'titulo' => 'Efectividad de logística (SLA)',
                        'labels' => ['Cumplimiento'],
                        'data' => [round($slaPct, 1)],
                        'label' => '%',
                    ],
                    [
                        'tipo' => 'bubble',
                        'titulo' => 'Gasto vs volumen por cliente',
                        'labels' => array_map(fn($x) => trim((string)$x['cliente']), $clientes),
                        'data' => array_map(function($x) {
                            $pedidos = (int)$x['pedidos'];
                            $gasto = (float)$x['gasto'];
                            $ticket = $pedidos > 0 ? $gasto / $pedidos : 0;
                            return ['x' => $pedidos, 'y' => $gasto, 'r' => max(6, min(28, sqrt($ticket) / 2))];
                        }, $clientes),
                        'label' => 'Clientes',
                    ],
                ],
            ];
        }

        $kpiStmt = $db->prepare(
            "SELECT COUNT(*) AS pedidos,
                    COALESCE(SUM(total),0) AS monto,
                    SUM(estado = 'entregado') AS entregados,
                    COALESCE(AVG(total),0) AS ticket
               FROM pedidos
              WHERE empresa_id = ?
                AND DATE(created_at) BETWEEN ? AND ?"
        );
        $kpiStmt->execute([$empresaId, $desde, $hasta]);
        $r = $kpiStmt->fetch() ?: [];

        $stockCriticoStmt = $db->prepare(
            "SELECT COUNT(*)
               FROM inventario inv
               JOIN productos p ON p.id = inv.producto_id
              WHERE p.empresa_id = ? AND p.activo = 1
                AND inv.stock <= inv.umbral_minimo"
        );
        $stockCriticoStmt->execute([$empresaId]);
        $stockCritico = (int)$stockCriticoStmt->fetchColumn();

        $pendientesStmt = $db->prepare(
            "SELECT COUNT(*) FROM pedidos
              WHERE empresa_id = ? AND estado = 'pendiente'
                AND DATE(created_at) BETWEEN ? AND ?"
        );
        $pendientesStmt->execute([$empresaId, $desde, $hasta]);
        $pendientes = (int)$pendientesStmt->fetchColumn();

        $stmt = $db->prepare(
            "SELECT DATE(p.created_at) AS fecha, p.folio,
                    CONCAT(u.nombre, ' ', u.apellido_paterno) AS comprador,
                    p.estado, p.total,
                    COUNT(ps.id) AS sucursales
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
          LEFT JOIN pedido_sucursal ps ON ps.pedido_id = p.id
              WHERE p.empresa_id = ?
                AND DATE(p.created_at) BETWEEN ? AND ?
           GROUP BY p.id
           ORDER BY p.created_at DESC
              LIMIT 120"
        );
        $stmt->execute([$empresaId, $desde, $hasta]);
        $rows = $stmt->fetchAll();

        $trendStmt = $db->prepare(
            "SELECT DATE(created_at) AS d, COUNT(*) AS pedidos, COALESCE(SUM(total),0) AS monto
               FROM pedidos
              WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?
           GROUP BY DATE(created_at) ORDER BY DATE(created_at)"
        );
        $trendStmt->execute([$empresaId, $desde, $hasta]);
        $trend = $trendStmt->fetchAll();

        $estStmt = $db->prepare(
            "SELECT estado, COUNT(*) AS c FROM pedidos
              WHERE empresa_id = ? AND DATE(created_at) BETWEEN ? AND ?
           GROUP BY estado"
        );
        $estStmt->execute([$empresaId, $desde, $hasta]);
        $est = $estStmt->fetchAll();

        // ── Reporte específico para Supervisor (foco operativo, no comercial) ─
        if ($rol === 'supervisor') {
            $pedidoModel = new PedidoModel();
            $movModel    = new MovimientoInventarioModel();

            $slaDemorados        = $pedidoModel->pedidosDemoradosAprobacion($empresaId, 15);
            $excepcionesLimite   = $pedidoModel->excepcionesLimite($empresaId, $desde, $hasta);
            $incidenciasReparto  = $pedidoModel->incidenciasReparto($empresaId, $desde, $hasta);

            // Datos para gráficas adicionales (espejo del dashboard)
            $topProds = $pedidoModel->topProductos($empresaId, $desde, $hasta, 8);
            $stockResumen = $movModel->resumenStock($empresaId);
            $stockStats = [
                'agotado' => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'agotado')),
                'critico' => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'critico')),
                'bajo'    => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'bajo')),
                'ok'      => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'ok')),
            ];
            $movsSemanal = $movModel->entradasVsSalidasSemanal($empresaId, 6);

            return [
                'titulo' => 'Reporte Operativo de Supervisión',
                'kpis' => [
                    ['label' => 'SLA crítico (>15 min)', 'valor' => number_format($slaDemorados), 'hint' => 'Pedidos demorados sin aprobar'],
                    ['label' => 'Excepciones de límite', 'valor' => number_format($excepcionesLimite), 'hint' => 'Intentos por encima del límite'],
                    ['label' => 'Incidencias de reparto', 'valor' => number_format($incidenciasReparto), 'hint' => 'Paradas fallidas / vencidas'],
                    ['label' => 'Pendientes del período', 'valor' => number_format($pendientes), 'hint' => 'Por aprobar'],
                    ['label' => 'Entregados', 'valor' => number_format((int)($r['entregados'] ?? 0)), 'hint' => 'Pedidos cerrados'],
                    ['label' => 'Stock crítico', 'valor' => number_format($stockCritico), 'hint' => 'Productos en alerta'],
                ],
                'columnas' => ['Fecha', 'Folio', 'Comprador', 'Estado', 'Total', 'Sucursales'],
                'filas' => array_map(fn($x) => [
                    $x['fecha'],
                    $x['folio'],
                    trim((string)$x['comprador']),
                    strtoupper((string)$x['estado']),
                    '$' . number_format((float)$x['total'], 2),
                    (string)$x['sucursales'],
                ], $rows),
                'notas' => [
                    'Pedidos demorados (>15 min sin aprobar): ' . number_format($slaDemorados) . '. Atender estos primero para sostener el SLA.',
                    'Excepciones de límite registradas: ' . number_format($excepcionesLimite) . '. Revisar compradores que intentan exceder su tope.',
                    'Incidencias de reparto: ' . number_format($incidenciasReparto) . ' (paradas fallidas o pendientes vencidas).',
                    'Productos en stock crítico: ' . number_format($stockCritico) . '. Coordinar reabastecimiento con producción.',
                    'Parámetro técnico recomendado de conservación: 0°C a 4°C en operación.',
                ],
                'graficas' => [
                    [
                        'tipo' => 'line',
                        'titulo' => 'Pedidos diarios',
                        'labels' => array_map(fn($x) => $x['d'], $trend),
                        'data' => array_map(fn($x) => (int)$x['pedidos'], $trend),
                        'label' => 'Pedidos',
                    ],
                    [
                        'tipo' => 'doughnut',
                        'titulo' => 'Pedidos por estado',
                        'labels' => array_map(fn($x) => strtoupper((string)$x['estado']), $est),
                        'data' => array_map(fn($x) => (int)$x['c'], $est),
                        'label' => 'Pedidos',
                    ],
                    [
                        'tipo' => 'barH',
                        'titulo' => 'Top productos más pedidos',
                        'labels' => array_map(fn($x) => (string)$x['nombre'], $topProds),
                        'data' => array_map(fn($x) => (float)$x['total_cantidad'], $topProds),
                        'label' => 'Cantidad',
                    ],
                    [
                        'tipo' => 'doughnut',
                        'titulo' => 'Estado del inventario',
                        'labels' => ['Agotado', 'Crítico', 'Bajo', 'Normal'],
                        'data' => [$stockStats['agotado'], $stockStats['critico'], $stockStats['bajo'], $stockStats['ok']],
                        'label' => 'Productos',
                    ],
                    [
                        'tipo' => 'bar',
                        'titulo' => 'Entradas vs Salidas de stock (últimas 6 semanas)',
                        'labels' => array_map(fn($x) => 'Sem ' . date('d/m', strtotime((string)$x['inicio_semana'])), $movsSemanal),
                        'datasets' => [
                            ['label' => 'Entradas', 'data' => array_map(fn($x) => (float)$x['entradas'], $movsSemanal), 'color' => '#10B981'],
                            ['label' => 'Salidas',  'data' => array_map(fn($x) => (float)$x['salidas'],  $movsSemanal), 'color' => '#EF4444'],
                        ],
                    ],
                ],
            ];
        }

        $titulo = 'Reporte Ejecutivo de Empresa';

        $kpi4Label = 'Ticket promedio';
        $kpi4Valor = '$' . number_format((float)($r['ticket'] ?? 0), 2);
        $kpi4Hint = 'Monto por pedido';

        return [
            'titulo' => $titulo,
            'kpis' => [
                ['label' => 'Pedidos del período', 'valor' => number_format((int)($r['pedidos'] ?? 0)), 'hint' => 'Operación total'],
                ['label' => 'Monto del período', 'valor' => '$' . number_format((float)($r['monto'] ?? 0), 2), 'hint' => 'Venta acumulada'],
                ['label' => 'Entregados', 'valor' => number_format((int)($r['entregados'] ?? 0)), 'hint' => 'Pedidos cerrados'],
                ['label' => $kpi4Label, 'valor' => $kpi4Valor, 'hint' => $kpi4Hint],
            ],
            'columnas' => ['Fecha', 'Folio', 'Comprador', 'Estado', 'Total', 'Sucursales'],
            'filas' => array_map(fn($x) => [
                $x['fecha'],
                $x['folio'],
                trim((string)$x['comprador']),
                strtoupper((string)$x['estado']),
                '$' . number_format((float)$x['total'], 2),
                (string)$x['sucursales'],
            ], $rows),
            'notas' => [
                'Pedidos pendientes en el período: ' . number_format($pendientes) . '.',
                'Mantener abastecimiento preventivo para productos de mayor rotación y alertas de stock.',
                'Parámetro técnico de conservación recomendado en operación: 0°C a 4°C.',
            ],
            'graficas' => [
                [
                    'tipo' => 'line',
                    'titulo' => 'Pedidos diarios',
                    'labels' => array_map(fn($x) => $x['d'], $trend),
                    'data' => array_map(fn($x) => (int)$x['pedidos'], $trend),
                    'label' => 'Pedidos',
                ],
                [
                    'tipo' => 'doughnut',
                    'titulo' => 'Pedidos por estado',
                    'labels' => array_map(fn($x) => strtoupper((string)$x['estado']), $est),
                    'data' => array_map(fn($x) => (int)$x['c'], $est),
                    'label' => 'Pedidos',
                ],
            ],
        ];
    }

    private function fechaEspanol(string $fecha): string
    {
        static $meses = [
            '01' => 'Enero', '02' => 'Febrero', '03' => 'Marzo', '04' => 'Abril',
            '05' => 'Mayo', '06' => 'Junio', '07' => 'Julio', '08' => 'Agosto',
            '09' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre',
        ];
        $ts = strtotime($fecha);
        if (!$ts) {
            return $fecha;
        }
        $d = date('d', $ts);
        $m = $meses[date('m', $ts)] ?? date('m', $ts);
        $y = date('Y', $ts);
        return $d . ' de ' . $m . ', ' . $y;
    }
}
