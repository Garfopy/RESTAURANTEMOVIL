<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestTicketController extends BaseController
{
    private RestTicketModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireMesero();
        $this->model = new RestTicketModel();
    }

    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $page      = (int)$this->get('page', 1);
        $resultado = $this->model->listar($restauranteId, $page);
        $flash     = $this->getFlash();
        $pageTitle  = 'Tickets';
        $activeMenu = 'rest_tickets';
        $this->render('restaurante/tickets/index', array_merge($resultado, compact('flash','pageTitle','activeMenu')));
    }

    public function generar(?string $visitaId = null): void
    {
        $propina  = (float)$this->post('propina', 0);
        $ticketId = $this->model->consolidar((int)$visitaId, $propina);
        $this->flash('success', 'Ticket generado.');
        $this->redirect('rest-ticket/detalle/' . $ticketId);
    }

    public function detalle(?string $id = null): void
    {
        $ticket = $this->model->find((int)$id);
        if (!$ticket) { $this->flash('error', 'Ticket no encontrado.'); $this->redirect('rest-ticket/index'); }

        // Cargar ítems de todos los pedidos de esta visita
        $pedidoModel = new RestPedidoModel();
        $pedidos     = $pedidoModel->getByVisita((int)$ticket['visita_id']);
        $todoItems   = [];
        foreach ($pedidos as $ped) {
            $detalle = $pedidoModel->getConItems((int)$ped['id']);
            if ($detalle && !empty($detalle['items'])) {
                foreach ($detalle['items'] as $item) {
                    if ($item['estado'] !== 'cancelado') {
                        $todoItems[] = $item;
                    }
                }
            }
        }

        $flash  = $this->getFlash();
        $pageTitle  = 'Ticket ' . $ticket['folio'];
        $activeMenu = 'rest_tickets';
        $this->render('restaurante/tickets/detalle', compact('ticket','todoItems','flash','pageTitle','activeMenu'));
    }

    public function confirmarPago(?string $id = null): void
    {
        $metodoPago = $this->post('metodo_pago', 'efectivo');
        $paypalOrderId = $this->post('paypal_order_id');
        $this->model->marcarPagado((int)$id, $metodoPago, $paypalOrderId);

        // Marcar visita como pagada
        $ticket = $this->model->find((int)$id);
        if ($ticket) {
            (new RestVisitaModel())->marcarPagada((int)$ticket['visita_id']);
        }

        $this->flash('success', 'Pago registrado.');
        $this->redirect('rest-ticket/detalle/' . $id);
    }
}
