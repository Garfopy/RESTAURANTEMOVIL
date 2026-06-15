<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class PanelController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    public function index(?string $p = null): void
    {
        $this->redirect('panel/dashboard');
    }

    public function dashboard(?string $p = null): void
    {
        $db = Database::getInstance();

        // ── KPIs principales ──────────────────────────────────────────────
        $totalEmpresas       = (int)$db->query('SELECT COUNT(*) FROM empresas WHERE activo = 1')->fetchColumn();
        $totalUsuarios       = (int)$db->query("SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.activo = 1 AND r.slug NOT IN ('superadmin','admin')")->fetchColumn();
        $empresasActivas     = (int)$db->query("SELECT COUNT(*) FROM empresas WHERE activo=1 AND suscripcion_estado='activo'")->fetchColumn();
        $empresasSuspendidas = (int)$db->query("SELECT COUNT(*) FROM empresas WHERE activo=1 AND suscripcion_estado IN ('suspendido','sin_plan')")->fetchColumn();

        // ── Ingresos SaaS (suma de suscripciones activas × precio plan) ───
        $ingresosSaas = (float)$db->query(
            "SELECT COALESCE(SUM(ps.precio_mensual),0)
               FROM suscripciones s
               JOIN planes_saas ps ON ps.id = s.plan_id
              WHERE s.estado = 'activo'"
        )->fetchColumn();

        // ── Totales SaaS adicionales ──────────────────────────────────────
        $totalSucursales = (int)$db->query(
            "SELECT COUNT(*) FROM sucursales WHERE activo = 1"
        )->fetchColumn();

        $pedidosEntregados = (int)$db->query(
            "SELECT COUNT(*) FROM pedidos WHERE estado = 'entregado'
              AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())"
        )->fetchColumn();

        // ── Ingresos SaaS por mes (últimos 6 meses) ───────────────────────
        $ingresosPorMes = $db->query(
            "SELECT DATE_FORMAT(s.created_at,'%Y-%m') AS mes,
                    COALESCE(SUM(ps.precio_mensual),0) AS ingresos
               FROM suscripciones s
               JOIN planes_saas ps ON ps.id = s.plan_id
              WHERE s.created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
           GROUP BY mes ORDER BY mes ASC"
        )->fetchAll();

        // ── Distribución de planes ────────────────────────────────────────
        $distPlanes = $db->query(
            "SELECT ps.nombre, COUNT(s.id) AS total
               FROM planes_saas ps
          LEFT JOIN suscripciones s ON s.plan_id = ps.id AND s.estado = 'activo'
              WHERE ps.activo = 1
           GROUP BY ps.id, ps.nombre
           ORDER BY ps.precio_mensual ASC"
        )->fetchAll();

        // ── Estado de suscripciones ───────────────────────────────────────
        $estadoSus = $db->query(
            "SELECT estado, COUNT(*) AS total FROM suscripciones GROUP BY estado"
        )->fetchAll();

        // ── Empresas nuevas por mes (últimos 6 meses) ─────────────────────
        $empresasNuevas = $db->query(
            "SELECT DATE_FORMAT(created_at,'%Y-%m') AS mes, COUNT(*) AS total
               FROM empresas
              WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
           GROUP BY mes ORDER BY mes ASC"
        )->fetchAll();

        // ── Últimos pedidos ───────────────────────────────────────────────
        $ultimosPedidos = $db->query(
            "SELECT p.folio, p.estado, p.total, p.created_at,
                    e.razon_social AS empresa, u.nombre AS comprador
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
               JOIN usuarios u ON u.id = p.comprador_id
           ORDER BY p.created_at DESC LIMIT 8"
        )->fetchAll();

        // ── Stock bajo ────────────────────────────────────────────────────
        $stockBajo = $db->query(
            "SELECT p.nombre, inv.stock, inv.umbral_minimo, e.razon_social AS empresa
               FROM inventario inv
               JOIN productos p  ON p.id  = inv.producto_id
               JOIN empresas e   ON e.id  = p.empresa_id
              WHERE inv.stock <= inv.umbral_minimo AND p.activo = 1 LIMIT 5"
        )->fetchAll();

        // ── Actividad reciente ─────────────────────────────────────────────
        $actividadReciente = $db->query(
            "SELECT al.accion, al.modulo, al.created_at,
                    COALESCE(u.nombre,'Sistema') AS nombre, r.slug AS rol_slug
               FROM action_logs al
          LEFT JOIN usuarios u ON u.id = al.usuario_id
          LEFT JOIN roles r    ON r.id = u.rol_id
           ORDER BY al.created_at DESC LIMIT 6"
        )->fetchAll();

        $flash      = $this->getFlash();
        $pageTitle  = 'Dashboard';
        $activeMenu = 'dashboard';

        $alertaStorage = 0;
        $cfg           = new ConfigModel();
        $diasRet       = max(1, (int)$cfg->get('retencion_fotos_evidencias_dias', '90'));
        $cutoffStorage = time() - $diasRet * 86400;
        foreach (['entregas', 'firmas'] as $_sd) {
            foreach (glob(UPLOAD_PATH . $_sd . '/*') ?: [] as $_sf) {
                if (is_file($_sf) && filemtime($_sf) < $cutoffStorage) $alertaStorage++;
            }
        }

        ob_start();
        require ROOT_PATH . '/app/views/panel/dashboard.php';
        $content = ob_get_clean();

        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }
}
