<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestPropinaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireMesero(); // admin del restaurante también tiene rol mesero o superior
    }

    // GET /rest-propinas/index
    public function index(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $desde = $this->get('desde', date('Y-m-d'));
        $hasta = $this->get('hasta', date('Y-m-d'));

        $db = Database::getInstance();

        // Propinas agrupadas por mesero con totales y conteos
        $stmt = $db->prepare(
            "SELECT
                u.id         AS mesero_id,
                u.nombre     AS mesero_nombre,
                COUNT(t.id)  AS total_tickets,
                COALESCE(SUM(t.propina), 0)                                     AS total_propinas,
                COALESCE(SUM(CASE WHEN t.propina_entregada = 1 THEN t.propina ELSE 0 END), 0) AS propinas_entregadas,
                COALESCE(SUM(CASE WHEN t.propina_entregada = 0 THEN t.propina ELSE 0 END), 0) AS propinas_pendientes,
                COUNT(CASE WHEN t.propina_entregada = 0 AND t.propina > 0 THEN 1 END) AS tickets_pendientes
             FROM rest_tickets t
             JOIN usuarios u ON u.id = t.mesero_id
             WHERE t.restaurante_id = ?
               AND t.estado = 'pagado'
               AND t.propina > 0
               AND DATE(t.pagado_at) BETWEEN ? AND ?
             GROUP BY u.id, u.nombre
             ORDER BY propinas_pendientes DESC, u.nombre ASC"
        );
        $stmt->execute([$restauranteId, $desde, $hasta]);
        $meseros = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Tickets sin mesero asignado pero con propina
        $stmt2 = $db->prepare(
            "SELECT
                COUNT(id)       AS total_tickets,
                COALESCE(SUM(propina), 0)                                          AS total_propinas,
                COALESCE(SUM(CASE WHEN propina_entregada = 1 THEN propina ELSE 0 END), 0) AS propinas_entregadas,
                COALESCE(SUM(CASE WHEN propina_entregada = 0 THEN propina ELSE 0 END), 0) AS propinas_pendientes
             FROM rest_tickets
             WHERE restaurante_id = ?
               AND mesero_id IS NULL
               AND estado = 'pagado'
               AND propina > 0
               AND DATE(pagado_at) BETWEEN ? AND ?"
        );
        $stmt2->execute([$restauranteId, $desde, $hasta]);
        $sinMesero = $stmt2->fetch(PDO::FETCH_ASSOC);

        $pageTitle  = 'Propinas por Mesero';
        $activeMenu = 'rest_propinas';
        $this->render('restaurante/propinas/index', compact(
            'meseros', 'sinMesero', 'desde', 'hasta', 'pageTitle', 'activeMenu'
        ));
    }

    // POST /rest-propinas/marcarEntregada/{ticketId}
    public function marcarEntregada(?string $ticketId = null): void
    {
        $restauranteId = $this->restauranteId();
        $db = Database::getInstance();

        // Verificar que el ticket pertenece a este restaurante
        $stmt = $db->prepare(
            "SELECT id FROM rest_tickets WHERE id = ? AND restaurante_id = ? AND estado = 'pagado'"
        );
        $stmt->execute([(int)$ticketId, $restauranteId]);
        if (!$stmt->fetch()) {
            $this->json(['ok' => false, 'msg' => 'Ticket no encontrado']);
            return;
        }

        $db->prepare(
            "UPDATE rest_tickets SET propina_entregada = 1 WHERE id = ? AND restaurante_id = ?"
        )->execute([(int)$ticketId, $restauranteId]);

        $this->json(['ok' => true]);
    }

    // POST /rest-propinas/marcarTodasEntregadas/{meseroId}
    public function marcarTodasEntregadas(?string $meseroId = null): void
    {
        $restauranteId = $this->restauranteId();
        $desde = $this->post('desde', date('Y-m-d'));
        $hasta = $this->post('hasta', date('Y-m-d'));

        $db = Database::getInstance();
        $db->prepare(
            "UPDATE rest_tickets
             SET propina_entregada = 1
             WHERE restaurante_id = ?
               AND mesero_id = ?
               AND estado = 'pagado'
               AND propina > 0
               AND propina_entregada = 0
               AND DATE(pagado_at) BETWEEN ? AND ?"
        )->execute([$restauranteId, (int)$meseroId, $desde, $hasta]);

        $this->json(['ok' => true]);
    }
}
