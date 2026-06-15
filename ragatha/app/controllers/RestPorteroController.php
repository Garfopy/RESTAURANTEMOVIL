<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestPorteroController extends BaseController
{
    private RestVisitaModel $visitas;

    public function __construct()
    {
        parent::__construct();
        $this->requirePortero();
        $this->visitas = new RestVisitaModel();
    }

    public function dashboard(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $restaurante   = (new RestauranteModel())->find($restauranteId);
        $activas       = $this->visitas->getActivas($restauranteId);
        $flash         = $this->getFlash();
        $pageTitle     = 'Portero';
        $this->render('portero/dashboard', compact('restaurante','activas','flash','pageTitle'));
    }

    public function verificar(?string $p = null): void
    {
        $qr     = trim($this->post('qr_code') ?? $this->get('qr_code', ''));
        $visita = $this->visitas->getByQr($qr);

        if (!$visita) {
            $this->json(['ok' => false, 'mensaje' => 'QR no reconocido.'], 404);
        }

        $pagado  = $visita['estado'] === 'pagada';
        $yaSalio = !empty($visita['salida_at']);
        $this->json([
            'ok'       => true,
            'pagado'   => $pagado,
            'ya_salio' => $yaSalio,
            'estado'   => $visita['estado'],
            'comensal' => $visita['comensal_nombre'] ?? 'Visitante',
            'mesa'     => $visita['mesa_nombre'] ?? '—',
            'total'    => (float)$visita['total'],
            'propina'  => (float)($visita['propina'] ?? 0),
            'mensaje'  => $pagado ? '✅ PUEDE SALIR' : '❌ PAGO PENDIENTE $' . number_format((float)$visita['total'], 2),
        ]);
    }

    public function registrarEntrada(?string $p = null): void
    {
        if (!$this->isPost()) $this->redirect('rest-portero/dashboard');

        $restauranteId = $this->restauranteId();
        $mesaId        = $this->post('mesa_id') ?: null;
        $nombre        = trim($this->post('nombre', ''));
        $telefono      = trim($this->post('telefono', '')) ?: null;

        $comensalId = null;
        if ($nombre || $telefono) {
            $clientes   = new RestClienteModel();
            $comensalId = $clientes->buscarOCrear($restauranteId, $nombre ?: null, $telefono, null);
        }

        $visitaId = $this->visitas->crear($restauranteId, $mesaId ? (int)$mesaId : null, $comensalId);
        $visita   = $this->visitas->find($visitaId);

        $this->json([
            'ok'       => true,
            'visita_id'=> $visitaId,
            'qr_code'  => $visita['qr_code'],
        ]);
    }

    public function registrarSalida(?string $p = null): void
    {
        $qr     = trim($this->post('qr_code', ''));
        $visita = $this->visitas->getByQr($qr);

        if (!$visita || $visita['estado'] !== 'pagada') {
            $this->json(['ok' => false, 'mensaje' => 'La visita no está pagada o no existe.'], 400);
        }

        $this->visitas->marcarSalida((int)$visita['id']);

        // Liberar la mesa
        if (!empty($visita['mesa_id'])) {
            (new RestMesaModel())->cambiarEstado((int)$visita['mesa_id'], 'disponible');
        }

        // Auto-completar reservación del día si aplica
        if (!empty($visita['mesa_id'])) {
            Database::getInstance()->prepare(
                "UPDATE rest_reservaciones SET estado='completada'
                 WHERE mesa_id = ? AND fecha = CURDATE() AND estado IN ('pendiente','confirmada')"
            )->execute([(int)$visita['mesa_id']]);
        }

        $this->json(['ok' => true, 'mensaje' => 'Salida registrada.']);
    }
}
