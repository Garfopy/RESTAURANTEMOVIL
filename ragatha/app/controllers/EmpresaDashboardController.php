<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaDashboardController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireEmpresa();
    }

    public function index(?string $p = null): void
    {
        $this->redirect('empresa/dashboard');
    }

    public function dashboard(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $rol       = $this->rolActual();
        $db        = Database::getInstance();

        // Datos comunes
        $stmt = $db->prepare('SELECT COUNT(*) FROM pedidos WHERE empresa_id = ?');
        $stmt->execute([$empresaId]);
        $totalPedidos = (int)$stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM pedidos WHERE empresa_id = ? AND estado != 'cancelado' AND MONTH(created_at) = MONTH(NOW())");
        $stmt->execute([$empresaId]);
        $gastomMes = (float)$stmt->fetchColumn();

        // Pedidos recientes
        $stmt = $db->prepare(
            "SELECT p.folio, p.estado, p.total, p.created_at, u.nombre AS comprador
               FROM pedidos p
               JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.empresa_id = ?
           ORDER BY p.created_at DESC LIMIT 8"
        );
        $stmt->execute([$empresaId]);
        $pedidosRecientes = $stmt->fetchAll();

        // Pendientes de aprobación (supervisor y admin_empresa los ven)
        $pendientesAprobacion = 0;
        if (in_array($rol, ['admin_empresa', 'supervisor'], true)) {
            $stmt = $db->prepare("SELECT COUNT(*) FROM pedidos WHERE empresa_id = ? AND requiere_aprobacion = 1 AND estado = 'pendiente'");
            $stmt->execute([$empresaId]);
            $pendientesAprobacion = (int)$stmt->fetchColumn();
        }

        // Datos para gráficas (solo admin_empresa y supervisor)
        $datosGraficas = [];
        if (in_array($rol, ['admin_empresa', 'supervisor'], true)) {
            $datosGraficas = $this->obtenerDatosGraficas($empresaId);
        }

        // Resumen de pedidos recurrentes (solo admin_empresa y supervisor)
        $resumenRecurrentes  = ['total_pedidos' => 0, 'compradores_unicos' => 0, 'productos_distintos' => 0, 'monto_total' => 0];
        $proximasRecurrentes = [];
        if (in_array($rol, ['admin_empresa', 'supervisor'], true)) {
            $recModel           = new RecurrenteModel();
            $resumenRecurrentes = $recModel->getResumen($empresaId);
        }

        // Cargar empresa a sesión si no está
        if (empty($_SESSION['empresa']) && $empresaId) {
            $empresaModel = new EmpresaModel();
            $_SESSION['empresa'] = $empresaModel->find($empresaId);
        }

        $flash     = $this->getFlash();
        $pageTitle = 'Mi Empresa';
        $activeMenu = 'dashboard';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/dashboard.php';
        $content = ob_get_clean();

        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Estadísticas de pedidos recurrentes
    public function recurrentes(?string $p = null): void
    {
        $this->requireAdminEmpresa();
        $empresaId = $this->empresaId();
        $rol       = $this->rolActual();

        $recModel            = new RecurrenteModel();
        $resumen             = $recModel->getResumen($empresaId);
        $topProductos        = $recModel->getTopProductos($empresaId, 10);
        $diasSemana          = $recModel->getPedidosPorDiaSemana($empresaId);
        $topCompradores      = $recModel->getTopCompradores($empresaId, 8);
        $productosRecurrentes = $recModel->getProductosRecurrentes($empresaId, 2, 15);

        $flash      = $this->getFlash();
        $pageTitle  = 'Pedidos Recurrentes';
        $activeMenu = 'recurrentes';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/recurrentes/estadisticas.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    // Guardar dirección/ubicación de la empresa (para Maps)
    public function guardarDireccion(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa/dashboard');
        }
        $this->requireAdminEmpresa();
        $empresaId       = $this->empresaId();
        $direccion       = trim($this->post('direccion_fiscal', ''));
        $lat             = $this->post('lat', '');
        $lng             = $this->post('lng', '');

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'UPDATE empresas SET direccion_fiscal = ?, lat = ?, lng = ? WHERE id = ?'
        );
        $stmt->execute([
            $direccion ?: null,
            is_numeric($lat) ? (float)$lat : null,
            is_numeric($lng) ? (float)$lng : null,
            $empresaId,
        ]);

        // Refrescar sesión
        $empresaModel = new EmpresaModel();
        $_SESSION['empresa'] = $empresaModel->find($empresaId);

        $this->log('Actualizar dirección empresa', 'empresa', "Dirección: $direccion");
        $this->flash('success', 'Dirección de la empresa actualizada.');
        $this->redirect('empresa/dashboard');
    }

    private function obtenerDatosGraficas(int $empresaId): array
    {
        $db = Database::getInstance();

        // 1. Ventas por mes (últimos 6 meses)
        $stmt = $db->prepare("
            SELECT
                DATE_FORMAT(created_at, '%Y-%m') as mes,
                DATE_FORMAT(created_at, '%b') as mes_corto,
                COUNT(*) as total_pedidos,
                COALESCE(SUM(CASE WHEN estado != 'cancelado' THEN total ELSE 0 END), 0) as ventas
            FROM pedidos
            WHERE empresa_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY mes ASC
        ");
        $stmt->execute([$empresaId]);
        $ventasMes = $stmt->fetchAll();

        // 2. Ventas por día (últimos 30 días)
        $stmt = $db->prepare("
            SELECT
                DATE(created_at) as fecha,
                COUNT(*) as pedidos,
                COALESCE(SUM(CASE WHEN estado != 'cancelado' THEN total ELSE 0 END), 0) as ventas
            FROM pedidos
            WHERE empresa_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created_at)
            ORDER BY fecha ASC
        ");
        $stmt->execute([$empresaId]);
        $ventasDia = $stmt->fetchAll();

        // 3. Estados de pedidos (últimos 30 días)
        $stmt = $db->prepare("
            SELECT estado, COUNT(*) as total
            FROM pedidos
            WHERE empresa_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY estado
            ORDER BY total DESC
        ");
        $stmt->execute([$empresaId]);
        $estadosPedidos = $stmt->fetchAll();

        // 4. Usuarios activos por rol
        $stmt = $db->prepare("
            SELECT
                r.nombre as rol,
                r.slug as rol_slug,
                COUNT(u.id) as total
            FROM usuarios u
            JOIN roles r ON r.id = u.rol_id
            WHERE u.empresa_id = ?
                AND u.activo = 1
                AND r.slug IN ('supervisor', 'comprador', 'repartidor')
            GROUP BY r.id, r.nombre, r.slug
            ORDER BY total DESC
        ");
        $stmt->execute([$empresaId]);
        $usuariosPorRol = $stmt->fetchAll();

        // 5. Top 5 productos más vendidos (últimos 30 días)
        $stmt = $db->prepare("
            SELECT
                pr.nombre,
                pr.presentacion,
                SUM(pd.cantidad) as total_vendido
            FROM pedido_detalle pd
            JOIN productos pr ON pr.id = pd.producto_id
            JOIN pedidos p ON p.id = pd.pedido_id
            WHERE p.empresa_id = ?
                AND p.estado != 'cancelado'
                AND p.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY pr.id, pr.nombre, pr.presentacion
            ORDER BY total_vendido DESC
            LIMIT 5
        ");
        $stmt->execute([$empresaId]);
        $topProductos = $stmt->fetchAll();

        // 6. Métodos de pago (últimos 90 días)
        $stmt = $db->prepare("
            SELECT
                metodo_pago,
                COUNT(*) as total,
                SUM(total) as monto_total
            FROM pedidos
            WHERE empresa_id = ?
                AND metodo_pago IS NOT NULL
                AND estado != 'cancelado'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY metodo_pago
            ORDER BY total DESC
        ");
        $stmt->execute([$empresaId]);
        $metodosPago = $stmt->fetchAll();

        return [
            'ventasMes' => $ventasMes,
            'ventasDia' => $ventasDia,
            'estadosPedidos' => $estadosPedidos,
            'usuariosPorRol' => $usuariosPorRol,
            'topProductos' => $topProductos,
            'metodosPago' => $metodosPago,
        ];
    }
}
