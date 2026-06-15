<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestMeseroController extends BaseController
{
    private RestPedidoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireMesero();
        $this->model = new RestPedidoModel();
    }

    public function dashboard(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        if (!$restauranteId) {
            $this->redirect('acceso/login');
            return;
        }

        $restaurante = (new RestauranteModel())->find($restauranteId);
        $meseroId    = $this->usuarioId();
        $db          = Database::getInstance();

        // Zonas asignadas al mesero en el turno de hoy
        $stmtZ = $db->prepare(
            "SELECT zona_id FROM rest_mesero_turno
             WHERE restaurante_id = ? AND usuario_id = ? AND turno_fecha = CURDATE() AND activo = 1"
        );
        $stmtZ->execute([$restauranteId, $meseroId]);
        $misZonas = array_column($stmtZ->fetchAll(PDO::FETCH_ASSOC), 'zona_id');

        // Mesas con indicador de si pertenece a mi zona
        $stmt = $db->prepare(
            "SELECT m.id, m.nombre, m.capacidad, m.estado, m.zona_id
             FROM rest_mesas m
             WHERE m.restaurante_id = ? AND m.activo = 1
             ORDER BY m.nombre ASC"
        );
        $stmt->execute([$restauranteId]);
        $mesas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($mesas as &$m) {
            $m['es_mi_zona'] = in_array((int)($m['zona_id'] ?? 0), $misZonas);
        }
        unset($m);

        $flash     = $this->getFlash();
        $pageTitle = 'Mesero';
        $this->render('mesero/dashboard', compact(
            'restaurante', 'mesas', 'misZonas', 'flash', 'pageTitle'
        ));
    }

    // POST /rest-mesero/reclamar/{pedidoId}
    // Toma ownership del pedido: estado listo → reclamado, registra quién lo reclamó
    public function reclamar(?string $pedidoId = null): void
    {
        $db       = Database::getInstance();
        $meseroId = $this->usuarioId();
        $pid      = (int)$pedidoId;

        // Solo se puede reclamar si está en 'listo' (no reclamado por otro)
        $stmt = $db->prepare(
            "UPDATE rest_pedidos
             SET estado = 'reclamado', mesero_id = ?, reclamado_por = ?, reclamado_at = NOW()
             WHERE id = ? AND restaurante_id = ? AND estado = 'listo'"
        );
        $stmt->execute([$meseroId, $meseroId, $pid, $this->restauranteId()]);

        if ($stmt->rowCount() === 0) {
            // Verificar si ya lo reclamó este mismo mesero
            $check = $db->prepare(
                "SELECT estado, reclamado_por FROM rest_pedidos WHERE id = ? AND restaurante_id = ?"
            );
            $check->execute([$pid, $this->restauranteId()]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if ($row && $row['estado'] === 'reclamado' && (int)$row['reclamado_por'] === $meseroId) {
                $this->json(['ok' => true, 'ya_reclamado' => true]);
            } else {
                $this->json(['ok' => false, 'msg' => 'Pedido no disponible para reclamar']);
            }
            return;
        }

        $this->json(['ok' => true]);
    }

    public function marcarEntregado(?string $pedidoId = null): void
    {
        if ($this->isSocialGiftDeliveryId($pedidoId)) {
            $this->marcarRegaloEntregado((string)$pedidoId);
            return;
        }

        $db       = Database::getInstance();
        $meseroId = $this->usuarioId();
        $pid      = (int)$pedidoId;

        // Solo puede entregar el mesero que reclamó, o cualquiera si no fue reclamado
        $check = $db->prepare(
            "SELECT estado, reclamado_por FROM rest_pedidos WHERE id = ? AND restaurante_id = ?"
        );
        $check->execute([$pid, $this->restauranteId()]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row || !in_array($row['estado'], ['listo', 'reclamado'], true)) {
            $this->json(['ok' => false, 'msg' => 'Estado inválido']);
            return;
        }

        if ($row['estado'] === 'reclamado' && $row['reclamado_por'] !== null
            && (int)$row['reclamado_por'] !== $meseroId) {
            $this->json(['ok' => false, 'msg' => 'Este pedido fue reclamado por otro mesero']);
            return;
        }

        // Verificar que no haya ítems aún por preparar/pendientes (chef todavía trabajando)
        $pend = $db->prepare(
            "SELECT COUNT(*) FROM rest_pedido_items
             WHERE pedido_id = ? AND estado IN ('pendiente','en_preparacion')"
        );
        $pend->execute([$pid]);
        if ((int)$pend->fetchColumn() > 0) {
            $this->json(['ok' => false, 'msg' => 'Aún hay platillos sin marcar listos por el chef']);
            return;
        }

        $db->prepare(
            "UPDATE rest_pedidos SET estado='entregado', mesero_id = ? WHERE id = ? AND restaurante_id = ?"
        )->execute([$meseroId, $pid, $this->restauranteId()]);

        $db->prepare(
            "UPDATE rest_pedido_items SET estado='entregado'
             WHERE pedido_id = ? AND estado IN ('listo','reclamado')"
        )->execute([$pid]);

        // Propagar mesero_id al ticket si aún no tiene
        $db->prepare(
            "UPDATE rest_tickets t
             JOIN rest_visitas v ON v.id = t.visita_id
             JOIN rest_pedidos p ON p.visita_id = v.id AND p.id = ?
             SET t.mesero_id = ?
             WHERE t.mesero_id IS NULL"
        )->execute([$pid, $meseroId]);

        $this->json(['ok' => true]);
    }

    // POST /rest-mesero/atenderAlerta/{alertaId}
    public function atenderAlerta(?string $alertaId = null): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare("UPDATE rest_alertas SET atendida=1 WHERE id=? AND restaurante_id=?");
        $stmt->execute([(int)$alertaId, $this->restauranteId()]);
        $this->json(['ok' => true]);
    }

    // GET /rest-mesero/alertas  — polling JSON para el dashboard
    public function alertas(?string $p = null): void
    {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT a.id, a.tipo, a.created_at,
                    a.mesa_id,
                    m.nombre AS mesa_nombre
             FROM rest_alertas a
             LEFT JOIN rest_mesas m ON m.id = a.mesa_id
             WHERE a.restaurante_id = ? AND a.atendida = 0
             ORDER BY a.created_at DESC
             LIMIT 20"
        );
        $stmt->execute([$this->restauranteId()]);
        $this->json(['ok' => true, 'alertas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    // GET /rest-mesero/pedidosMesa/{mesaId}  — pedidos activos de una mesa (para modal)
    public function pedidosMesa(?string $mesaId = null): void
    {
        $db       = Database::getInstance();
        $meseroId = $this->usuarioId();
        $restauranteId = $this->restauranteId();
        $stmt = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.created_at, p.reclamado_por,
                    u.nombre AS reclamado_por_nombre
              FROM rest_pedidos p
              LEFT JOIN usuarios u ON u.id = p.reclamado_por
             WHERE p.mesa_id = ? AND p.restaurante_id = ?
               AND p.estado NOT IN ('entregado','cancelado')
             ORDER BY p.created_at DESC"
        );
        $stmt->execute([(int)$mesaId, $restauranteId]);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pedidos as &$ped) {
            $ped['es_mi_reclamo'] = $ped['estado'] === 'reclamado'
                && (int)$ped['reclamado_por'] === $meseroId;

            $stmt2 = $db->prepare(
                "SELECT pi.id, pl.nombre AS nombre, pi.cantidad, pi.estado
                 FROM rest_pedido_items pi
                 JOIN rest_platillos pl ON pl.id = pi.platillo_id
                 WHERE pi.pedido_id = ? AND pi.estado != 'cancelado'"
            );
            $stmt2->execute([(int)$ped['id']]);
            $ped['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($ped);

        $pedidos = array_merge($pedidos, $this->fetchSocialGiftPedidosMesa($db, $restauranteId, (int)$mesaId, $meseroId));
        usort($pedidos, static function (array $left, array $right): int {
            return strcmp((string)($right['created_at'] ?? ''), (string)($left['created_at'] ?? ''));
        });

        $this->json(['ok' => true, 'pedidos' => $pedidos]);
    }

    // GET /rest-mesero/listos  — pedidos en estado 'listo' o 'reclamado' para entregar
    public function listos(?string $p = null): void
    {
        $db          = Database::getInstance();
        $meseroId    = $this->usuarioId();
        $restauranteId = $this->restauranteId();

        // Zonas del mesero hoy
        $stmtZ = $db->prepare(
            "SELECT zona_id FROM rest_mesero_turno
             WHERE restaurante_id = ? AND usuario_id = ? AND turno_fecha = CURDATE() AND activo = 1"
        );
        $stmtZ->execute([$restauranteId, $meseroId]);
        $misZonas = array_column($stmtZ->fetchAll(PDO::FETCH_ASSOC), 'zona_id');

        $stmt = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.created_at, p.mesero_id,
                    p.reclamado_por, p.reclamado_at,
                    m.nombre AS mesa_nombre, m.zona_id,
                    u.nombre AS reclamado_por_nombre
             FROM rest_pedidos p
             LEFT JOIN rest_mesas m   ON m.id = p.mesa_id
             LEFT JOIN usuarios u     ON u.id = p.reclamado_por
             WHERE p.restaurante_id = ? AND p.estado IN ('listo','reclamado')
             ORDER BY
               CASE WHEN m.zona_id IN (" . (count($misZonas) ? implode(',', array_fill(0, count($misZonas), '?')) : '0') . ") THEN 0 ELSE 1 END ASC,
               p.created_at ASC
             LIMIT 50"
        );
        $params = array_merge([$restauranteId], $misZonas);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($pedidos as &$ped) {
            $ped['es_mi_zona']    = in_array((int)($ped['zona_id'] ?? 0), $misZonas);
            $ped['es_mi_reclamo'] = $ped['estado'] === 'reclamado' && (int)$ped['reclamado_por'] === $meseroId;
            $ped['reclamado_otro'] = $ped['estado'] === 'reclamado' && (int)$ped['reclamado_por'] !== $meseroId;

            $stmt2 = $db->prepare(
                "SELECT pi.id, pl.nombre AS nombre, pi.cantidad
                 FROM rest_pedido_items pi
                 JOIN rest_platillos pl ON pl.id = pi.platillo_id
                 WHERE pi.pedido_id = ? AND pi.estado != 'cancelado'"
            );
            $stmt2->execute([(int)$ped['id']]);
            $ped['items'] = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($ped);

        $pedidos = array_merge($pedidos, $this->fetchSocialGiftListos($db, $restauranteId, $meseroId, $misZonas));
        usort($pedidos, static function (array $left, array $right): int {
            $leftPriority = !empty($left['es_mi_zona']) ? 0 : 1;
            $rightPriority = !empty($right['es_mi_zona']) ? 0 : 1;

            if ($leftPriority !== $rightPriority) {
                return $leftPriority <=> $rightPriority;
            }

            return strcmp((string)($left['created_at'] ?? ''), (string)($right['created_at'] ?? ''));
        });

        $this->json(['ok' => true, 'listos' => $pedidos, 'mis_zonas' => $misZonas]);
    }

    // POST /rest-mesero/tomarZona  — reclama todos los pedidos 'listo' en las zonas del mesero
    public function tomarZona(?string $p = null): void
    {
        $db            = Database::getInstance();
        $meseroId      = $this->usuarioId();
        $restauranteId = $this->restauranteId();

        $stmtZ = $db->prepare(
            "SELECT zona_id FROM rest_mesero_turno
             WHERE restaurante_id = ? AND usuario_id = ? AND turno_fecha = CURDATE() AND activo = 1"
        );
        $stmtZ->execute([$restauranteId, $meseroId]);
        $misZonas = array_column($stmtZ->fetchAll(PDO::FETCH_ASSOC), 'zona_id');

        if (empty($misZonas)) {
            $this->json(['ok' => false, 'msg' => 'Sin zonas asignadas hoy', 'count' => 0]);
            return;
        }

        $placeholders = implode(',', array_fill(0, count($misZonas), '?'));

        // Recopilar IDs de los pedidos a entregar
        $stmtIds = $db->prepare(
            "SELECT p.id FROM rest_pedidos p
             LEFT JOIN rest_mesas m ON m.id = p.mesa_id
             WHERE p.restaurante_id = ? AND p.estado = 'listo'
               AND m.zona_id IN ($placeholders)"
        );
        $stmtIds->execute(array_merge([$restauranteId], $misZonas));
        $pedidoIds = array_column($stmtIds->fetchAll(PDO::FETCH_ASSOC), 'id');
        $giftIds = $this->fetchSocialGiftIdsByZones($db, $restauranteId, $misZonas, 'listo');

        if (empty($pedidoIds) && empty($giftIds)) {
            $this->json(['ok' => true, 'count' => 0]);
            return;
        }

        if (!empty($pedidoIds)) {
            $idPlaceholders = implode(',', array_fill(0, count($pedidoIds), '?'));

        // Marcar pedidos como entregados directamente (sin pasar por 'reclamado')
        $db->prepare(
            "UPDATE rest_pedidos
             SET estado = 'entregado', mesero_id = ?, reclamado_por = ?, reclamado_at = NOW()
             WHERE id IN ($idPlaceholders) AND restaurante_id = ?"
        )->execute(array_merge([$meseroId, $meseroId], $pedidoIds, [$restauranteId]));

        // Marcar items como entregados
        $db->prepare(
            "UPDATE rest_pedido_items
             SET estado = 'entregado'
             WHERE pedido_id IN ($idPlaceholders) AND estado IN ('listo','reclamado')"
        )->execute($pedidoIds);

        // Propagar mesero_id al ticket si aún no tiene
        $db->prepare(
            "UPDATE rest_tickets t
             JOIN rest_visitas v ON v.id = t.visita_id
             JOIN rest_pedidos p ON p.visita_id = v.id
             SET t.mesero_id = ?
             WHERE p.id IN ($idPlaceholders) AND t.mesero_id IS NULL"
        )->execute(array_merge([$meseroId], $pedidoIds));
        }

        if (!empty($giftIds)) {
            $giftPlaceholders = implode(',', array_fill(0, count($giftIds), '?'));
            $db->prepare(
                "UPDATE social_gift_orders
                    SET status = 'entregado',
                        reclamado_por = ?,
                        reclamado_at = COALESCE(reclamado_at, NOW()),
                        entregado_por = ?,
                        entregado_at = NOW(),
                        updated_at = NOW()
                  WHERE id IN ($giftPlaceholders) AND restaurante_id = ?"
            )->execute(array_merge([$meseroId, $meseroId], $giftIds, [$restauranteId]));
        }

        $this->json(['ok' => true, 'count' => count($pedidoIds) + count($giftIds)]);
    }

    // GET /rest-mesero/reservasHoy  — reservaciones de hoy en las zonas del mesero
    public function reservasHoy(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $meseroId      = $this->usuarioId();
        $db            = Database::getInstance();

        // Zonas del mesero hoy
        $stmtZ = $db->prepare(
            "SELECT zona_id FROM rest_mesero_turno
             WHERE restaurante_id = ? AND usuario_id = ? AND turno_fecha = CURDATE() AND activo = 1"
        );
        $stmtZ->execute([$restauranteId, $meseroId]);
        $misZonas = array_column($stmtZ->fetchAll(PDO::FETCH_ASSOC), 'zona_id');

        $reservas = (new RestReservaModel())->getHoyPorZonas($restauranteId, $misZonas);
        $this->json(['ok' => true, 'reservas' => $reservas]);
    }

    private function isSocialGiftDeliveryId(?string $value): bool
    {
        return is_string($value) && strncmp($value, 'gift-', 5) === 0;
    }

    private function parseSocialGiftDeliveryId(string $value): int
    {
        return (int)substr($value, 5);
    }

    private function socialGiftOrdersTableExists(PDO $db): bool
    {
        static $exists = null;

        if ($exists !== null) {
            return $exists;
        }

        $stmt = $db->query("SHOW TABLES LIKE 'social_gift_orders'");
        $exists = !empty($stmt->fetchAll(PDO::FETCH_ASSOC));
        return $exists;
    }

    private function fetchSocialGiftPedidosMesa(PDO $db, int $restauranteId, int $mesaId, int $meseroId): array
    {
        if (!$this->socialGiftOrdersTableExists($db)) {
            return [];
        }

        $stmt = $db->prepare(
            "SELECT g.id, g.folio, g.status, g.created_at, g.reclamado_por, g.reclamado_at,
                    g.sender_nombre, g.recipient_nombre, g.sender_mesa, g.recipient_mesa,
                    g.gift_nombre, g.gift_descripcion, g.gift_precio,
                    m.nombre AS mesa_nombre, m.zona_id,
                    u.nombre AS reclamado_por_nombre
               FROM social_gift_orders g
               LEFT JOIN rest_mesas m ON m.id = g.mesa_id
               LEFT JOIN usuarios u ON u.id = g.reclamado_por
              WHERE g.restaurante_id = ? AND g.mesa_id = ?
                AND g.status NOT IN ('entregado','cancelado')
              ORDER BY g.created_at DESC"
        );
        $stmt->execute([$restauranteId, $mesaId]);

        return $this->hydrateSocialGiftRows($stmt->fetchAll(PDO::FETCH_ASSOC), $meseroId, []);
    }

    private function fetchSocialGiftListos(PDO $db, int $restauranteId, int $meseroId, array $misZonas): array
    {
        if (!$this->socialGiftOrdersTableExists($db)) {
            return [];
        }

        $stmt = $db->prepare(
            "SELECT g.id, g.folio, g.status, g.created_at, g.reclamado_por, g.reclamado_at,
                    g.sender_nombre, g.recipient_nombre, g.sender_mesa, g.recipient_mesa,
                    g.gift_nombre, g.gift_descripcion, g.gift_precio,
                    m.nombre AS mesa_nombre, m.zona_id,
                    u.nombre AS reclamado_por_nombre
               FROM social_gift_orders g
               LEFT JOIN rest_mesas m ON m.id = g.mesa_id
               LEFT JOIN usuarios u ON u.id = g.reclamado_por
              WHERE g.restaurante_id = ? AND g.status IN ('listo','reclamado')
              ORDER BY g.created_at ASC"
        );
        $stmt->execute([$restauranteId]);

        return $this->hydrateSocialGiftRows($stmt->fetchAll(PDO::FETCH_ASSOC), $meseroId, $misZonas);
    }

    private function hydrateSocialGiftRows(array $rows, int $meseroId, array $misZonas): array
    {
        $result = [];

        foreach ($rows as $row) {
            $isMyZone = in_array((int)($row['zona_id'] ?? 0), $misZonas, true);
            $isMyClaim = ($row['status'] ?? '') === 'reclamado' && (int)($row['reclamado_por'] ?? 0) === $meseroId;
            $isClaimedByOther = ($row['status'] ?? '') === 'reclamado' && (int)($row['reclamado_por'] ?? 0) !== $meseroId;

            $giftName = trim((string)($row['gift_nombre'] ?? 'Regalo'));
            $recipientName = trim((string)($row['recipient_nombre'] ?? 'Comensal'));
            $senderName = trim((string)($row['sender_nombre'] ?? 'Comensal'));
            $recipientMesa = trim((string)($row['recipient_mesa'] ?? ''));

            $itemLabel = 'Regalo: ' . $giftName . ' para ' . $recipientName;
            if ($senderName !== '') {
                $itemLabel .= ' de ' . $senderName;
            }
            if ($recipientMesa !== '') {
                $itemLabel .= ' (' . $recipientMesa . ')';
            }

            $result[] = [
                'id' => 'gift-' . (int)$row['id'],
                'folio' => $row['folio'] ?: ('SG-' . str_pad((string)((int)$row['id']), 6, '0', STR_PAD_LEFT)),
                'estado' => $row['status'],
                'created_at' => $row['created_at'],
                'reclamado_por' => $row['reclamado_por'],
                'reclamado_por_nombre' => $row['reclamado_por_nombre'] ?? null,
                'mesa_nombre' => $row['mesa_nombre'] ?? ($recipientMesa !== '' ? $recipientMesa : 'Mesa'),
                'zona_id' => $row['zona_id'] ?? null,
                'es_mi_zona' => $isMyZone,
                'es_mi_reclamo' => $isMyClaim,
                'reclamado_otro' => $isClaimedByOther,
                'es_regalo_social' => true,
                'tipo_label' => 'Regalo',
                'items' => [[
                    'id' => 'gift-item-' . (int)$row['id'],
                    'nombre' => $itemLabel,
                    'cantidad' => 1,
                    'estado' => $row['status'],
                ]],
            ];
        }

        return $result;
    }

    private function fetchSocialGiftIdsByZones(PDO $db, int $restauranteId, array $misZonas, string $status): array
    {
        if (!$this->socialGiftOrdersTableExists($db) || empty($misZonas)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($misZonas), '?'));
        $stmt = $db->prepare(
            "SELECT g.id
               FROM social_gift_orders g
               JOIN rest_mesas m ON m.id = g.mesa_id
              WHERE g.restaurante_id = ? AND g.status = ?
                AND m.zona_id IN ($placeholders)"
        );
        $stmt->execute(array_merge([$restauranteId, $status], $misZonas));

        return array_map('intval', array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id'));
    }

    private function marcarRegaloEntregado(string $giftDeliveryId): void
    {
        $db = Database::getInstance();
        if (!$this->socialGiftOrdersTableExists($db)) {
            $this->json(['ok' => false, 'msg' => 'La tabla de regalos sociales no existe']);
        }

        $meseroId = $this->usuarioId();
        $giftId = $this->parseSocialGiftDeliveryId($giftDeliveryId);

        if ($giftId <= 0) {
            $this->json(['ok' => false, 'msg' => 'Identificador invalido']);
        }

        $check = $db->prepare(
            "SELECT status, reclamado_por
               FROM social_gift_orders
              WHERE id = ? AND restaurante_id = ?"
        );
        $check->execute([$giftId, $this->restauranteId()]);
        $row = $check->fetch(PDO::FETCH_ASSOC);

        if (!$row || !in_array($row['status'], ['listo', 'reclamado'], true)) {
            $this->json(['ok' => false, 'msg' => 'Estado invalido']);
        }

        if ($row['status'] === 'reclamado' && $row['reclamado_por'] !== null && (int)$row['reclamado_por'] !== $meseroId) {
            $this->json(['ok' => false, 'msg' => 'Este regalo fue reclamado por otro mesero']);
        }

        $db->prepare(
            "UPDATE social_gift_orders
                SET status = 'entregado',
                    reclamado_por = COALESCE(reclamado_por, ?),
                    reclamado_at = COALESCE(reclamado_at, NOW()),
                    entregado_por = ?,
                    entregado_at = NOW(),
                    updated_at = NOW()
              WHERE id = ? AND restaurante_id = ?"
        )->execute([$meseroId, $meseroId, $giftId, $this->restauranteId()]);

        $this->json(['ok' => true]);
    }
}
