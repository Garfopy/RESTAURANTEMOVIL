<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RestChefController extends BaseController
{
    private RestPedidoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireChef();
        $this->model = new RestPedidoModel();
    }

    public function dashboard(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $restaurante   = (new RestauranteModel())->find($restauranteId);
        $pageTitle     = 'Cocina — KDS';
        $this->render('chef/dashboard', compact('restaurante','pageTitle'));
    }

    public function queue(?string $p = null): void
    {
        $restauranteId = $this->restauranteId();
        $items = $this->model->getKitchenQueue($restauranteId);
        $this->json($items);
    }

    public function marcarPreparacion(?string $itemId = null): void
    {
        $itemId = (int)$itemId;

        // ── Leer estado actual ANTES de cambiar (idempotencia) ────────────────
        try {
            $db       = Database::getInstance();
            $stmtItem = $db->prepare(
                "SELECT pi.platillo_id, pi.cantidad, pi.pedido_id, pi.estado,
                        p.restaurante_id, p.tipo_origen
                 FROM rest_pedido_items pi
                 JOIN rest_pedidos p ON p.id = pi.pedido_id
                 WHERE pi.id = ? LIMIT 1"
            );
            $stmtItem->execute([$itemId]);
            $item = $stmtItem->fetch(\PDO::FETCH_ASSOC);

            if ($item && strtolower((string)($item['tipo_origen'] ?? '')) === 'store') {
                $this->json(['ok' => true, 'store_ignored' => true]);
                return;
            }

            // Si ya estaba en preparación, no descontar stock de nuevo
            if ($item && $item['estado'] === 'en_preparacion') {
                $this->json(['ok' => true]);
                return;
            }
        } catch (\Throwable $e) {
            // Si falla la lectura previa, dejar que continúe el flujo normal
        }

        $this->model->cambiarEstadoItem($itemId, 'en_preparacion');

        // ── Descontar ingredientes de inventario al iniciar preparación ───────
        try {
            $db       = Database::getInstance();
            $stmtItem = $db->prepare(
                "SELECT pi.platillo_id, pi.cantidad, pi.pedido_id, p.restaurante_id
                 FROM rest_pedido_items pi
                 JOIN rest_pedidos p ON p.id = pi.pedido_id
                 WHERE pi.id = ? LIMIT 1"
            );
            $stmtItem->execute([$itemId]);
            $item = $stmtItem->fetch(\PDO::FETCH_ASSOC);

            if ($item && $item['restaurante_id']) {
                $restauranteId  = (int)$item['restaurante_id'];
                $platilloId     = (int)$item['platillo_id'];
                $cantidadPlatos = max(1, (int)$item['cantidad']);
                $pedidoId       = (int)$item['pedido_id'];
                $ref            = 'rest_item:' . $itemId;
                $invModel       = new RestInventarioModel();

                // Ingredientes de la receta que sí descuentan stock (es_informativo = 0)
                $stmtRec = $db->prepare(
                    "SELECT ri.ingrediente_id, ri.cantidad
                     FROM rest_receta_ingredientes ri
                     JOIN rest_recetas rec ON rec.id = ri.receta_id
                     WHERE rec.platillo_id = ?
                       AND ri.es_informativo = 0"
                );
                $stmtRec->execute([$platilloId]);
                $recIngredientes = $stmtRec->fetchAll(\PDO::FETCH_ASSOC);

                if (!empty($recIngredientes)) {
                    // Platillo con receta → descontar ingredientes
                    foreach ($recIngredientes as $ri) {
                        $delta = (float)$ri['cantidad'] * $cantidadPlatos;
                        $invModel->ajustarStock(
                            (int)$ri['ingrediente_id'],
                            -$delta,
                            'salida',
                            'Preparación (pedido #' . $pedidoId . ')',
                            $ref,
                            $restauranteId,
                            null
                        );
                    }
                } else {
                    // Sin receta → deducir por código (bebidas, postres, etc.)
                    // Busca el ingrediente cuyo codigo coincide con el del platillo.
                    $stmtCod = $db->prepare(
                        "SELECT i.id
                         FROM rest_ingredientes i
                         JOIN rest_platillos pl ON TRIM(pl.codigo) = TRIM(i.codigo)
                         WHERE pl.id = ?
                           AND i.restaurante_id = ?
                           AND i.activo = 1
                           AND TRIM(COALESCE(i.codigo,'')) != ''
                         LIMIT 1"
                    );
                    $stmtCod->execute([$platilloId, $restauranteId]);
                    $ingId = (int)$stmtCod->fetchColumn();
                    if ($ingId) {
                        $invModel->ajustarStock(
                            $ingId,
                            -(float)$cantidadPlatos,
                            'salida',
                            'Preparación (pedido #' . $pedidoId . ')',
                            $ref,
                            $restauranteId,
                            null
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            // No bloquea el flujo — el ítem ya quedó marcado como en preparación
        }

        $this->json(['ok' => true]);
    }

    public function marcarListo(?string $itemId = null): void
    {
        $itemId = (int)$itemId;
        $db   = Database::getInstance();
        try {
            $stmtItem = $db->prepare(
                "SELECT p.tipo_origen
                   FROM rest_pedido_items pi
                   JOIN rest_pedidos p ON p.id = pi.pedido_id
                  WHERE pi.id = ? LIMIT 1"
            );
            $stmtItem->execute([$itemId]);
            $item = $stmtItem->fetch(\PDO::FETCH_ASSOC);
            if ($item && strtolower((string)($item['tipo_origen'] ?? '')) === 'store') {
                $this->json(['ok' => true, 'store_ignored' => true]);
                return;
            }
        } catch (\Throwable $e) {
            // Si la columna tipo_origen no existe, continuar con el flujo normal.
        }

        $this->model->cambiarEstadoItem($itemId, 'listo');

        $stmt = $db->prepare(
            "SELECT pedido_id FROM rest_pedido_items WHERE id = ?"
        );
        $stmt->execute([$itemId]);
        $pedidoId = (int)$stmt->fetchColumn();

        if ($pedidoId) {
            // Si todos los ítems están listos/entregados → marcar pedido como listo
            $stmt2 = $db->prepare(
                "SELECT COUNT(*) FROM rest_pedido_items
                 WHERE pedido_id = ? AND estado NOT IN ('listo','entregado','cancelado')"
            );
            $stmt2->execute([$pedidoId]);
            if ((int)$stmt2->fetchColumn() === 0) {
                $this->model->cambiarEstadoPedido($pedidoId, 'listo');
            }
        }

        $this->json(['ok' => true]);
    }

    // GET /rest-chef/armado/{platillo_id}
    // Devuelve ingredientes (con codigo_display) y pasos de preparación para el KDS
    public function armado(?string $platilloId = null): void
    {
        $platilloId    = (int)$platilloId;
        $restauranteId = $this->restauranteId();
        $db            = Database::getInstance();

        $stmtIng = $db->prepare(
            "SELECT ri.codigo_display, ri.tipo_componente, ri.cantidad, ri.unidad,
                    i.nombre
             FROM rest_receta_ingredientes ri
             JOIN rest_recetas            re ON re.id = ri.receta_id
             JOIN rest_ingredientes        i  ON i.id  = ri.ingrediente_id
             WHERE re.platillo_id   = ?
               AND i.restaurante_id = ?
             ORDER BY ri.tipo_componente, ri.codigo_display, i.nombre"
        );
        $stmtIng->execute([$platilloId, $restauranteId]);
        $ingredientes = $stmtIng->fetchAll(PDO::FETCH_ASSOC);

        $stmtPasos = $db->prepare(
            "SELECT orden_paso, descripcion
             FROM rest_pasos_preparacion
             WHERE platillo_id    = ?
               AND restaurante_id = ?
               AND activo         = 1
             ORDER BY orden_paso ASC"
        );
        $stmtPasos->execute([$platilloId, $restauranteId]);
        $pasos = $stmtPasos->fetchAll(PDO::FETCH_ASSOC);

        $this->json(['ingredientes' => $ingredientes, 'pasos' => $pasos]);
    }
}
