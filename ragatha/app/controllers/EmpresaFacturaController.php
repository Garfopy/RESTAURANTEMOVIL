<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';
require_once ROOT_PATH . '/app/services/FacturaloService.php';

class EmpresaFacturaController extends BaseController
{
    private PDO $db;

    public function __construct()
    {
        parent::__construct();
        $this->requireSupervisor();
        $this->db = Database::getInstance();
    }

    public function index(?string $p = null): void
    {
        $empresaId = $this->empresaId();
        $usuario   = $_SESSION['usuario'] ?? [];
        $page      = max(1, (int)$this->get('page', 1));
        $perPage   = 20;
        $offset    = ($page - 1) * $perPage;

        $stTotal = $this->db->prepare('SELECT COUNT(*) FROM facturas WHERE empresa_id = ?');
        $stTotal->execute([$empresaId]);
        $total = (int)$stTotal->fetchColumn();

        $stmt = $this->db->prepare(
            'SELECT f.*, p.folio AS pedido_folio
               FROM facturas f
               JOIN pedidos p ON p.id = f.pedido_id
              WHERE f.empresa_id = ?
              ORDER BY f.created_at DESC
              LIMIT ? OFFSET ?'
        );
        $stmt->execute([$empresaId, $perPage, $offset]);
        $facturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stPedidos = $this->db->prepare(
            'SELECT p.id, p.folio, p.total, p.created_at
               FROM pedidos p
              WHERE p.empresa_id = ?
                AND p.estado = "entregado"
                AND NOT EXISTS (SELECT 1 FROM facturas f WHERE f.pedido_id = p.id)
              ORDER BY p.created_at DESC
              LIMIT 50'
        );
        $stPedidos->execute([$empresaId]);
        $pedidosSinFactura = $stPedidos->fetchAll(PDO::FETCH_ASSOC);

        // Check empresa's own facturacion credentials
        $stCfg = $this->db->prepare(
            'SELECT facturalo_apikey FROM empresas WHERE id = ? LIMIT 1'
        );
        $stCfg->execute([$empresaId]);
        $hayCredenciales = !empty($stCfg->fetchColumn());

        $totalPages = $total > 0 ? (int)ceil($total / $perPage) : 1;
        $flash      = $this->getFlash();
        $pageTitle  = 'Facturas CFDI';
        $activeMenu = 'facturas';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/facturas/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function generar(?string $pedidoId = null): void
    {
        $this->requireAdminEmpresa();
        $pedidoId  = (int)$pedidoId;
        $empresaId = $this->empresaId();

        if (!$pedidoId) {
            $this->flash('error', 'Pedido no especificado.');
            $this->redirect('empresa-factura/index');
        }

        $stPedido = $this->db->prepare(
            'SELECT id FROM pedidos WHERE id = ? AND empresa_id = ? AND estado = "entregado"'
        );
        $stPedido->execute([$pedidoId, $empresaId]);
        if (!$stPedido->fetch()) {
            $this->flash('error', 'El pedido no existe, no pertenece a tu empresa o no está entregado.');
            $this->redirect('empresa-factura/index');
        }

        $stExiste = $this->db->prepare('SELECT id FROM facturas WHERE pedido_id = ?');
        $stExiste->execute([$pedidoId]);
        if ($stExiste->fetch()) {
            $this->flash('error', 'Este pedido ya tiene una factura generada.');
            $this->redirect('empresa-factura/index');
        }

        $service   = new FacturaloService($empresaId);
        $resultado = $service->generarCFDI($pedidoId);

        if (!$resultado['ok']) {
            $this->flash('error', 'Error al timbrar: ' . ($resultado['error'] ?? 'desconocido'));
        } else {
            $this->log('Factura generada', 'facturas', 'Pedido #' . $pedidoId . ' UUID: ' . $resultado['uuid']);
            $this->flash('success', 'Factura timbrada correctamente. UUID: ' . $resultado['uuid']);
        }

        $this->redirect('empresa-factura/index');
    }

    public function cancelar(?string $uuid = null): void
    {
        $this->requireAdminEmpresa();
        $uuid      = preg_replace('/[^a-f0-9\-]/i', '', $uuid ?? '');
        $empresaId = $this->empresaId();

        if (!$uuid) {
            $this->flash('error', 'UUID no válido.');
            $this->redirect('empresa-factura/index');
        }

        $stFact = $this->db->prepare(
            'SELECT f.monto FROM facturas f
              WHERE f.uuid_cfdi = ? AND f.empresa_id = ?'
        );
        $stFact->execute([$uuid, $empresaId]);
        $factura = $stFact->fetch(PDO::FETCH_ASSOC);
        if (!$factura) {
            $this->flash('error', 'Factura no encontrada.');
            $this->redirect('empresa-factura/index');
        }

        $service = new FacturaloService($empresaId);
        $ok      = $service->cancelarCFDI($uuid, '', (float)$factura['monto']);

        if ($ok) {
            $this->db->prepare(
                'UPDATE facturas SET estado = "cancelada" WHERE uuid_cfdi = ?'
            )->execute([$uuid]);
            $this->log('Factura cancelada', 'facturas', 'UUID: ' . $uuid);
            $this->flash('success', 'Solicitud de cancelación enviada al SAT.');
        } else {
            $this->flash('error', 'No se pudo cancelar. Verifica las credenciales o intenta más tarde.');
        }

        $this->redirect('empresa-factura/index');
    }
}
