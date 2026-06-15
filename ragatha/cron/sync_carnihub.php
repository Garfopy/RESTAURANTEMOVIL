<?php
/**
 * sync_carnihub.php — Sincronización de estado de pedidos B2B con CarniHub.
 *
 * Propósito: Fallback por si el webhook entrante no llegó (timeout, error red).
 *            Consulta el estado actual de pedidos "en tránsito" y actualiza BD.
 *
 * Frecuencia sugerida: cada 15 minutos.
 * Crontab: * /15 * * * * php /path/to/cron/sync_carnihub.php >> /var/log/sync_carnihub.log 2>&1
 *
 * Ejecutar manualmente: php cron/sync_carnihub.php
 */

define('ROOT_PATH', dirname(__DIR__));
define('CLI_MODE', true);

require_once ROOT_PATH . '/config/config.php';
require_once ROOT_PATH . '/config/database.php';

// Autoload manual de modelos y servicios
foreach (['models', 'services'] as $dir) {
    foreach (glob(ROOT_PATH . "/app/$dir/*.php") as $f) {
        require_once $f;
    }
}

$db    = Database::getInstance();
$model = new RestPedidoSugeridoModel();
$api   = new CarniHubApiService();

// Pedidos que ya se enviaron a CarniHub y aún no terminaron
$stmt = $db->prepare(
    "SELECT ps.id, ps.restaurante_id, ps.pedido_carnihub_id,
            COALESCE(ps.estado_carnihub, 'pendiente') AS estado_carnihub
       FROM rest_pedidos_sugeridos ps
      WHERE ps.pedido_carnihub_id IS NOT NULL
        AND ps.estado NOT IN ('cancelado', 'rechazado')
        AND COALESCE(ps.estado_carnihub, '') NOT IN ('entregado', 'cancelado', 'rechazado')
      ORDER BY ps.ultima_sync_carnihub ASC
      LIMIT 100"
);
$stmt->execute();
$pedidos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

if (empty($pedidos)) {
    echo date('[Y-m-d H:i:s]') . " Sin pedidos pendientes de sincronizar.\n";
    exit(0);
}

echo date('[Y-m-d H:i:s]') . ' Sincronizando ' . count($pedidos) . " pedido(s)...\n";

$actualizados = 0;
$errores      = 0;

foreach ($pedidos as $pedido) {
    $pedidoSugeridoId = (int)$pedido['id'];
    $restauranteId    = (int)$pedido['restaurante_id'];
    $carnihubId       = (int)$pedido['pedido_carnihub_id'];
    $estadoActual     = $pedido['estado_carnihub'];

    try {
        $res = $api->consultarPedido($restauranteId, $carnihubId);

        if (!($res['success'] ?? false)) {
            echo date('[Y-m-d H:i:s]') . " [WARN] PedidoSugerido #$pedidoSugeridoId: " . ($res['error'] ?? 'sin respuesta') . "\n";
            $errores++;
            // Actualizar timestamp para no reintentar inmediatamente
            $db->prepare('UPDATE rest_pedidos_sugeridos SET ultima_sync_carnihub = NOW() WHERE id = ?')
               ->execute([$pedidoSugeridoId]);
            continue;
        }

        // La respuesta puede venir en ['pedido'] o directamente
        $pedidoData   = $res['pedido'] ?? $res;
        $estadoRemoto = $pedidoData['estado'] ?? ($pedidoData['status'] ?? 'desconocido');

        if ($estadoRemoto !== $estadoActual) {
            $model->syncEstadoCarnihub($pedidoSugeridoId, $estadoRemoto);
            echo date('[Y-m-d H:i:s]')
               . " [OK] PedidoSugerido #$pedidoSugeridoId (CH:#$carnihubId): $estadoActual → $estadoRemoto\n";
            $actualizados++;
        } else {
            // Solo actualizar timestamp de sync
            $db->prepare('UPDATE rest_pedidos_sugeridos SET ultima_sync_carnihub = NOW() WHERE id = ?')
               ->execute([$pedidoSugeridoId]);
        }

    } catch (\Throwable $e) {
        echo date('[Y-m-d H:i:s]')
           . " [ERROR] PedidoSugerido #$pedidoSugeridoId: " . $e->getMessage() . "\n";
        error_log('[sync_carnihub] Error pedido ' . $pedidoSugeridoId . ': ' . $e->getMessage());
        $errores++;
    }

    usleep(200_000); // 200ms entre requests para no saturar la API
}

echo date('[Y-m-d H:i:s]') . " Fin: $actualizados actualizado(s), $errores error(es).\n";
exit($errores > 0 ? 1 : 0);
