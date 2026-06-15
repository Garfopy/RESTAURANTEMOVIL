<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class PanelReporteController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(?string $p = null): void
    {
        $db  = Database::getInstance();
        $hoy = date('Y-m-d');

        // ── Período de análisis ───────────────────────────────────────────
        $periodo = (string)$this->get('periodo', '30d');
        $periodosValidos = ['hoy','7d','30d','90d','año','custom'];
        if (!in_array($periodo, $periodosValidos, true)) $periodo = '30d';

        switch ($periodo) {
            case 'hoy':
                $desde = $hoy; $hasta = $hoy;
                $labelPeriodo = 'Hoy'; break;
            case '7d':
                $desde = date('Y-m-d', strtotime('-6 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 7 días'; break;
            case '90d':
                $desde = date('Y-m-d', strtotime('-89 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 90 días'; break;
            case 'año':
                $desde = date('Y-01-01'); $hasta = $hoy;
                $labelPeriodo = 'Este año'; break;
            case 'custom':
                $desde = (string)$this->get('fecha_desde', date('Y-m-d', strtotime('-29 days')));
                $hasta = (string)$this->get('fecha_hasta', $hoy);
                $labelPeriodo = 'Período personalizado'; break;
            default:
                $desde = date('Y-m-d', strtotime('-29 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 30 días';
        }

        $fechaDesdeIn = $this->get('fecha_desde');
        $fechaHastaIn = $this->get('fecha_hasta');
        if ($fechaDesdeIn !== null || $fechaHastaIn !== null) {
            if ($fechaDesdeIn !== null) $desde = (string)$fechaDesdeIn;
            if ($fechaHastaIn !== null) $hasta = (string)$fechaHastaIn;
            $periodo = 'custom'; $labelPeriodo = 'Período personalizado';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-29 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = $hoy;
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

        $mostrarInput = $this->get('mostrar', ['logo', 'kpis', 'graficas', 'tabla', 'notas']);
        $mostrar = is_array($mostrarInput) ? $mostrarInput : [$mostrarInput];
        $mostrar = array_values(array_intersect($mostrar, ['logo', 'kpis', 'graficas', 'tabla', 'notas']));
        if (empty($mostrar)) $mostrar = ['logo', 'kpis', 'graficas', 'tabla', 'notas'];

        // ── KPIs SaaS del período ─────────────────────────────────────────
        $totalEmpresas = (int)$db->query('SELECT COUNT(*) FROM empresas WHERE activo=1')->fetchColumn();
        $suscActivas   = (int)$db->query("SELECT COUNT(*) FROM suscripciones WHERE estado='activo'")->fetchColumn();
        $ingresosMes   = (float)$db->query(
            "SELECT COALESCE(SUM(ps.precio_mensual),0) FROM suscripciones s
               JOIN planes_saas ps ON ps.id=s.plan_id WHERE s.estado='activo'"
        )->fetchColumn();
        $totalUsuarios = (int)$db->query(
            "SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id
              WHERE u.activo=1 AND r.slug NOT IN ('superadmin','admin')"
        )->fetchColumn();
        $totalSucursales = (int)$db->query("SELECT COUNT(*) FROM sucursales WHERE activo=1")->fetchColumn();

        $stmtEnt = $db->prepare(
            "SELECT COUNT(*) FROM pedidos WHERE estado='entregado' AND DATE(created_at) BETWEEN ? AND ?"
        );
        $stmtEnt->execute([$desde, $hasta]);
        $entregasPeriodo = (int)$stmtEnt->fetchColumn();

        $stmtNuevas = $db->prepare(
            "SELECT COUNT(*) FROM empresas WHERE DATE(created_at) BETWEEN ? AND ?"
        );
        $stmtNuevas->execute([$desde, $hasta]);
        $empresasNuevasPeriodo = (int)$stmtNuevas->fetchColumn();
        $planMasPopular = (string)($db->query(
            "SELECT ps.nombre FROM planes_saas ps
               JOIN suscripciones s ON s.plan_id=ps.id AND s.estado='activo'
           GROUP BY ps.id ORDER BY COUNT(*) DESC LIMIT 1"
        )->fetchColumn() ?: '—');

        // ── Gráfica 1: Ingresos SaaS por mes ─────────────────────────────
        $ingresosPorMes = $db->query(
            "SELECT DATE_FORMAT(s.created_at,'%Y-%m') AS mes,
                    COALESCE(SUM(ps.precio_mensual),0) AS ingresos
               FROM suscripciones s
               JOIN planes_saas ps ON ps.id=s.plan_id
              WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
           GROUP BY mes ORDER BY mes ASC"
        )->fetchAll();

        // ── Gráfica 2: Distribución de planes ────────────────────────────
        $distPlanes = $db->query(
            "SELECT ps.nombre AS plan,
                    SUM(CASE WHEN s.estado='activo' THEN 1 ELSE 0 END) AS activas
               FROM planes_saas ps
          LEFT JOIN suscripciones s ON s.plan_id=ps.id
              WHERE ps.activo=1
           GROUP BY ps.id ORDER BY ps.precio_mensual ASC"
        )->fetchAll();

        // ── Gráfica 3: Empresas nuevas por mes ───────────────────────────
        $empresasNuevas = $db->query(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS mes, COUNT(*) AS total
               FROM empresas
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
           GROUP BY mes ORDER BY mes ASC"
        )->fetchAll();

        // ── Gráfica 4: Estado de suscripciones ───────────────────────────
        $estadoSus = $db->query(
            "SELECT estado, COUNT(*) AS total FROM suscripciones GROUP BY estado"
        )->fetchAll();

        // ── Tabla: Top empresas del período ──────────────────────────────
        $stmtTop = $db->prepare(
            "SELECT e.razon_social,
                    COALESCE(ps.nombre,'Sin plan') AS plan,
                    COUNT(p.id) AS total_pedidos,
                    COALESCE(SUM(p.total),0) AS monto,
                    COALESCE(s.estado,'—') AS estado_suscripcion
               FROM empresas e
          LEFT JOIN pedidos p ON p.empresa_id=e.id AND p.estado != 'cancelado'
                             AND DATE(p.created_at) BETWEEN ? AND ?
          LEFT JOIN suscripciones s ON s.empresa_id=e.id AND s.estado='activo'
          LEFT JOIN planes_saas ps  ON ps.id=s.plan_id
              WHERE e.activo=1
           GROUP BY e.id ORDER BY total_pedidos DESC LIMIT 20"
        );
        $stmtTop->execute([$desde, $hasta]);
        $topEmpresas = $stmtTop->fetchAll();

        // ── Ensamblar en formato tecnico.php ─────────────────────────────
        $kpis = [
            ['label' => 'Empresas activas',       'valor' => number_format($totalEmpresas),              'hint' => 'Registradas en la plataforma'],
            ['label' => 'Suscripciones activas',  'valor' => number_format($suscActivas),                'hint' => 'Con plan activo'],
            ['label' => 'Ingresos SaaS/mes',      'valor' => '$' . number_format($ingresosMes, 0, '.', ','), 'hint' => 'Recurrente mensual'],
            ['label' => 'Usuarios activos',       'valor' => number_format($totalUsuarios),              'hint' => 'Compradores y operadores'],
            ['label' => 'Sucursales activas',     'valor' => number_format($totalSucursales),            'hint' => 'En todas las empresas'],
            ['label' => 'Entregas del período',   'valor' => number_format($entregasPeriodo),            'hint' => 'Pedidos entregados'],
            ['label' => 'Empresas nuevas',        'valor' => number_format($empresasNuevasPeriodo),      'hint' => 'Registradas en el período'],
            ['label' => 'Plan más popular',       'valor' => mb_strimwidth($planMasPopular, 0, 18, '…'), 'hint' => 'Por suscripciones activas'],
        ];

        $graficas = [
            [
                'tipo'   => 'bar',
                'titulo' => 'Ingresos SaaS por mes',
                'labels' => array_column($ingresosPorMes, 'mes'),
                'data'   => array_map('floatval', array_column($ingresosPorMes, 'ingresos')),
                'label'  => 'Ingresos ($)',
            ],
            [
                'tipo'   => 'doughnut',
                'titulo' => 'Distribución de planes',
                'labels' => array_column($distPlanes, 'plan'),
                'data'   => array_map('intval', array_column($distPlanes, 'activas')),
                'label'  => 'Suscripciones activas',
            ],
            [
                'tipo'   => 'bar',
                'titulo' => 'Empresas nuevas por mes',
                'labels' => array_column($empresasNuevas, 'mes'),
                'data'   => array_map('intval', array_column($empresasNuevas, 'total')),
                'label'  => 'Empresas',
            ],
            [
                'tipo'   => 'doughnut',
                'titulo' => 'Estado de suscripciones',
                'labels' => array_map(fn($x) => strtoupper((string)$x['estado']), $estadoSus),
                'data'   => array_map('intval', array_column($estadoSus, 'total')),
                'label'  => 'Suscripciones',
            ],
        ];

        $columnas = ['Empresa', 'Plan', 'Pedidos', 'Monto generado', 'Estado suscripción'];
        $filas = array_map(fn($r) => [
            $r['razon_social'],
            $r['plan'],
            (int)$r['total_pedidos'],
            '$' . number_format((float)$r['monto'], 2),
            strtoupper((string)$r['estado_suscripcion']),
        ], $topEmpresas);

        $notas = [
            'Ingresos SaaS recurrentes mensuales: $' . number_format($ingresosMes, 2) . ' (basado en suscripciones activas).',
            'Total de sucursales gestionadas en la plataforma: ' . number_format($totalSucursales) . '.',
            'Empresas sin suscripción activa: ' . number_format($totalEmpresas - $suscActivas) . ' — revisar para conversión o renovación.',
            'Plan más popular del período: ' . $planMasPopular . '.',
            'Entregas confirmadas en el período analizado: ' . number_format($entregasPeriodo) . '.',
        ];

        $configModel = new ConfigModel();
        $logoUrl = $configModel->get('app_logo', BASE_URL . 'public/img/logo.svg') ?: BASE_URL . 'public/img/logo.svg';

        $fechaReporte = (function(string $f): string {
            $m = ['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo',
                  '06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre',
                  '10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
            [$y,$mo,$d] = explode('-', $f);
            return (int)$d . ' de ' . ($m[$mo] ?? $mo) . ', ' . $y;
        })(date('Y-m-d'));
        $reportId = '#CH-' . date('Ymd-His') . '-SA-' . strtoupper(bin2hex(random_bytes(2)));

        $filtros = [
            'fecha_desde'   => $desde,
            'fecha_hasta'   => $hasta,
            'periodo'       => $periodo,
            'label_periodo' => $labelPeriodo,
            'mostrar'       => $mostrar,
        ];

        $tituloReporte = 'Reporte de Plataforma SaaS';

        $flash      = $this->getFlash();
        $pageTitle  = 'Reportes de plataforma';
        $activeMenu = 'reportes';

        ob_start();
        require ROOT_PATH . '/app/views/reportes/tecnico.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }
}
