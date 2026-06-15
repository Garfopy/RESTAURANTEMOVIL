<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class SupervisorController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireRole(['supervisor']);
    }

    public function dashboard(?string $p = null): void
    {
        $empresaId = $_SESSION['usuario']['empresa_id'] ?? 0;
        $usuarioId = $this->usuarioId();

        // ── Período seleccionado ───────────────────────────────────────────
        $periodo = $this->get('periodo', '30d');
        $hoy     = date('Y-m-d');

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
                $desde = $this->get('desde', date('Y-m-d', strtotime('-29 days')));
                $hasta = $this->get('hasta', $hoy);
                $labelPeriodo = 'Período personalizado';
                break;
            default:
                $periodo = '30d';
                $desde = date('Y-m-d', strtotime('-29 days')); $hasta = $hoy;
                $labelPeriodo = 'Últimos 30 días';
        }

        $pedidoModel     = new PedidoModel();
        $movimientoModel = new MovimientoInventarioModel();
        $logModel        = new LogModel();

        // ── KPIs rápidos (tiempo real) ─────────────────────────────────────
        $pendientes     = $pedidoModel->pendientesAprobacion($empresaId);
        $enRuta         = $pedidoModel->getPedidosEnRutaEmpresa($empresaId);
        $entregadosHoy  = $pedidoModel->countEntregadosHoy($empresaId);
        $pedidosHoy     = $pedidoModel->countPedidosHoy($empresaId);

        // ── KPIs operativos críticos del Supervisor ────────────────────────
        $sla_demorados      = $pedidoModel->pedidosDemoradosAprobacion($empresaId, 15);
        $excepciones_limite = $pedidoModel->excepcionesLimite($empresaId, $desde, $hasta);
        $incidencias_reparto = $pedidoModel->incidenciasReparto($empresaId, $desde, $hasta);

        // ── Analytics del período ──────────────────────────────────────────
        $kpis             = $pedidoModel->kpisResumen($empresaId, $desde, $hasta);
        $pedidosPorDia    = $pedidoModel->pedidosPorDia($empresaId, $desde, $hasta);
        $pedidosPorEstado = $pedidoModel->pedidosPorEstado($empresaId, $desde, $hasta);
        $topProductos     = $pedidoModel->topProductos($empresaId, $desde, $hasta, 8);
        $pedidosRecientes = $pedidoModel->pedidosRecientes($empresaId, 8);

        // ── Llenar días sin pedidos para la gráfica ────────────────────────
        $dataMap      = array_column($pedidosPorDia, null, 'dia');
        $chartDias    = [];
        $chartPedidos = [];
        $chartMontos  = [];
        $cur = new DateTime($desde);
        $end = new DateTime($hasta);
        while ($cur <= $end) {
            $d = $cur->format('Y-m-d');
            $chartDias[]    = $cur->format('d/m');
            $chartPedidos[] = (int)($dataMap[$d]['total_pedidos'] ?? 0);
            $chartMontos[]  = (float)($dataMap[$d]['monto_total'] ?? 0);
            $cur->modify('+1 day');
        }

        // ── Stock ──────────────────────────────────────────────────────────
        $stockResumen = $movimientoModel->resumenStock($empresaId);
        $alertasStock = array_values(array_filter(
            $stockResumen,
            fn($p) => in_array($p['estado_stock'], ['agotado', 'critico'], true)
        ));
        $stockStats = [
            'agotado' => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'agotado')),
            'critico' => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'critico')),
            'bajo'    => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'bajo')),
            'ok'      => count(array_filter($stockResumen, fn($p) => $p['estado_stock'] === 'ok')),
        ];
        $movsSemanal        = $movimientoModel->entradasVsSalidasSemanal($empresaId, 6);
        $ultimosMovimientos = $movimientoModel->ultimosMovimientos($empresaId, 6);

        // ── Historial de accesos ───────────────────────────────────────────
        $historialAccesos = $logModel->getAccesosUsuario($usuarioId, 10);

        $countPendientesSidebar = count($pendientes);

        $flash      = $this->getFlash();
        $pageTitle  = 'Dashboard de Supervisión';
        $activeMenu = 'supervisor_dashboard';

        ob_start();
        require ROOT_PATH . '/app/views/supervisor/dashboard.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }
}
