<?php
/**
 * CarnihubController — Receptor de webhooks entrantes desde CarniHub.
 *
 * CarniHub llama POST /carnihub/webhook cuando cambia el estado de un pedido B2B.
 * Este endpoint actualiza rest_pedidos_sugeridos.estado_carnihub y, si el pedido
 * fue cancelado, notifica al admin del restaurante por correo.
 *
 * Agregar en index.php:
 *   $routes['carnihub'] = 'CarnihubController';
 *   $publicPaths[]      = 'carnihub/webhook';
 *
 * Autenticación: Bearer {webhook_secret}
 *   El secret recibido se compara con carnihub_api_config.webhook_secret
 *   del restaurante asociado a este pedido.
 */
class CarnihubController extends BaseController
{
    /**
     * POST /carnihub/webhook
     *
     * Payload JSON enviado por CarniHub:
     * {
     *   "pedido_id":          789,        // ID en CarniHub
     *   "capirest_pedido_id": 45,         // ID en rest_pedidos_sugeridos
     *   "folio":              "CHB-2026-0789",
     *   "estado":             "confirmado",
     *   "total":              1740.00,
     *   "updated_at":         "2026-05-27T10:30:00+00:00"
     * }
     */
    public function webhook(?string $p = null): void
    {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
            exit;
        }

        // ── Validar Bearer ────────────────────────────────────────
        $authHeader = $_SERVER['HTTP_AUTHORIZATION']
                   ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                   ?? '';

        if (!preg_match('/^Bearer\s+(\S+)$/i', trim($authHeader), $m)) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'error' => 'Token requerido']);
            exit;
        }
        $bearerRecibido = $m[1];

        // ── Buscar restaurante con ese webhook_secret ─────────────
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            'SELECT restaurante_id FROM carnihub_api_config
              WHERE webhook_secret = ? AND activo = 1
              LIMIT 1'
        );
        $stmt->execute([$bearerRecibido]);
        $cfg = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$cfg) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
            exit;
        }

        // ── Parsear payload ───────────────────────────────────────
        $rawBody = file_get_contents('php://input');
        $data    = json_decode($rawBody ?: '', true);

        if (!is_array($data) || empty($data['estado'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Payload inválido o estado ausente']);
            exit;
        }

        $estadoCarnihub     = $data['estado'];
        $capirestPedidoId   = (int)($data['capirest_pedido_id'] ?? 0);
        $carnihubPedidoId   = (int)($data['pedido_id'] ?? 0);
        $folio              = htmlspecialchars($data['folio'] ?? '', ENT_QUOTES, 'UTF-8');
        $restauranteId      = (int)$cfg['restaurante_id'];

        // ── Actualizar estado en rest_pedidos_sugeridos ───────────
        if ($capirestPedidoId > 0) {
            require_once ROOT_PATH . '/app/models/RestPedidoSugeridoModel.php';
            $model = new RestPedidoSugeridoModel();

            // Verificar que el pedido pertenece al restaurante autenticado
            $pedido = $model->find($capirestPedidoId);
            if ($pedido && (int)$pedido['restaurante_id'] === $restauranteId) {
                $model->syncEstadoCarnihub($capirestPedidoId, $estadoCarnihub);

                // Notificar al admin si fue cancelado o rechazado
                if (in_array($estadoCarnihub, ['cancelado', 'rechazado'], true)) {
                    try {
                        $this->_notificarCancelacion(
                            $restauranteId,
                            $carnihubPedidoId,
                            $folio,
                            $estadoCarnihub
                        );
                    } catch (\Throwable $e) {
                        error_log('[CarnihubController::webhook] Error al enviar email: ' . $e->getMessage());
                    }
                }
            } else {
                // Pedido no encontrado o no pertenece al restaurante — aun así responder 200
                error_log("[CarnihubController::webhook] capirest_pedido_id=$capirestPedidoId no pertenece a restaurante=$restauranteId");
            }
        }

        http_response_code(200);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ── Helpers ───────────────────────────────────────────────────

    private function _notificarCancelacion(
        int    $restauranteId,
        int    $carnihubPedidoId,
        string $folio,
        string $estado
    ): void {
        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT u.email, u.nombre
               FROM usuarios u
               JOIN rest_staff s ON s.usuario_id = u.id
              WHERE s.restaurante_id = ? AND s.rol_slug = 'admin_local' AND s.activo = 1
              LIMIT 1"
        );
        $stmt->execute([$restauranteId]);
        $admin = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$admin || empty($admin['email'])) return;

        require_once ROOT_PATH . '/app/services/EmailService.php';

        $asunto     = "Pedido CarniHub $folio fue $estado";
        $nombreSafe = htmlspecialchars($admin['nombre'], ENT_QUOTES, 'UTF-8');
        $cuerpo     = "
            <p>Hola {$nombreSafe},</p>
            <p>Tu pedido <strong>{$folio}</strong> (ID CarniHub: {$carnihubPedidoId})
               fue <strong>{$estado}</strong> por el proveedor.</p>
            <p>Por favor revisa tu inventario y genera un nuevo pedido si es necesario.</p>
        ";

        // EmailService utiliza instancia (no método estático)
        $mailer = new EmailService();
        $mailer->enviarCancelacionPedido($admin['email'], $admin['nombre'], $folio, $carnihubPedidoId, $estado);
    }
}
