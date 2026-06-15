<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class RepartidorController extends BaseController
{
    private string $colorPrimary = '#111827';

    public function __construct()
    {
        parent::__construct();
        $this->requireRepartidor();
    }

    public function index(?string $p = null): void
    {
        $this->redirect('repartidor/inicio');
    }

    public function inicio(?string $p = null): void
    {
        $repartidorId = $this->usuarioId();
        $db = Database::getInstance();

        // Ruta del día asignada a este repartidor
        $stmt = $db->prepare(
            "SELECT r.*, COUNT(rd.id) AS total_paradas,
                    SUM(CASE WHEN rd.estado = 'entregado' THEN 1 ELSE 0 END) AS entregadas
               FROM rutas r
               JOIN ruta_detalle rd ON rd.ruta_id = r.id
              WHERE r.repartidor_id = ? AND r.fecha = CURDATE() AND r.estado IN ('planificada','en_curso')
           GROUP BY r.id
           ORDER BY r.estado DESC LIMIT 1"
        );
        $stmt->execute([$repartidorId]);
        $rutaHoy = $stmt->fetch() ?: null;

        $paradas = [];
        if ($rutaHoy) {
            $stmt = $db->prepare(
                "SELECT rd.*, s.nombre AS sucursal_nombre, s.direccion, s.lat, s.lng,
                        p.folio AS pedido_folio, e.razon_social AS empresa_nombre
                   FROM ruta_detalle rd
                   JOIN sucursales s ON s.id = rd.sucursal_id
                   JOIN pedidos p ON p.id = rd.pedido_id
                   JOIN empresas e ON e.id = p.empresa_id
                  WHERE rd.ruta_id = ?
               ORDER BY rd.orden"
            );
            $stmt->execute([$rutaHoy['id']]);
            $paradas = $stmt->fetchAll();
        }

        // Pedidos directos asignados (sin ruta formal)
        $stmtDirectos = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.fecha_entrega,
                    p.direccion_entrega, p.nota_empresa,
                    e.razon_social AS empresa_nombre
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'
                AND p.estado IN ('en_preparacion', 'en_ruta')
              ORDER BY p.fecha_entrega ASC, p.id ASC"
        );
        $stmtDirectos->execute([$repartidorId]);
        $pedidosDirectos = $stmtDirectos->fetchAll();

        // Agregar sucursales a cada pedido directo para mostrar progreso en inicio
        if (!empty($pedidosDirectos)) {
            $ids = implode(',', array_map('intval', array_column($pedidosDirectos, 'id')));
            $stmtSucs = $db->query(
                "SELECT ps.pedido_id, ps.foto_entrega_path, s.nombre AS sucursal_nombre
                   FROM pedido_sucursal ps
                   JOIN sucursales s ON s.id = ps.sucursal_id
                  WHERE ps.pedido_id IN ($ids)
                  ORDER BY ps.id ASC"
            );
            $sucsPorPedido = [];
            foreach ($stmtSucs->fetchAll() as $row) {
                $sucsPorPedido[$row['pedido_id']][] = $row;
            }
            foreach ($pedidosDirectos as &$pd) {
                $pd['sucursales'] = $sucsPorPedido[$pd['id']] ?? [];
            }
            unset($pd);
        }

        // ── KPIs operativos del Repartidor ─────────────────────────────────
        $pedidoModel = new PedidoModel();
        $hoy   = date('Y-m-d');
        $desde = date('Y-m-d', strtotime('-29 days'));

        $resumenHoy        = $pedidoModel->paradasHoyRepartidor($repartidorId);
        $kilosPendientes   = $pedidoModel->kilosPendientesHoy($repartidorId);
        $proximaParada     = $pedidoModel->proximaParadaRepartidor($repartidorId);
        $evidencia         = $pedidoModel->cumplimientoEvidencia($repartidorId, $desde, $hoy);
        $incidencias       = $pedidoModel->incidenciasRutaRepartidor($repartidorId, $desde, $hoy);
        $tiempoProm        = $pedidoModel->tiempoPromedioPorParada($repartidorId, $desde, $hoy);
        $prodSemanal       = $pedidoModel->productividadSemanalRepartidor($repartidorId, 6);

        // SLA estimado: 30 min por parada (umbral configurable a futuro)
        $slaMinutosParada  = 30;

        $flash     = $this->getFlash();
        $pageTitle = 'Mis entregas de hoy';

        require ROOT_PATH . '/app/views/repartidor/inicio.php';
    }

    // ── Inicio de viaje directo (pedido sin ruta formal) ──────────────────────
    public function iniciarViaje(?string $pedidoId = null): void
    {
        if (!$this->isPost() || !$pedidoId) {
            $this->redirect('repartidor/inicio');
        }

        $pedidoId     = (int)$pedidoId;
        $repartidorId = $this->usuarioId();
        $db           = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT id FROM pedidos
              WHERE id = ? AND repartidor_asignado_id = ?
                AND tipo_entrega = 'repartidor' AND estado = 'en_preparacion'"
        );
        $stmt->execute([$pedidoId, $repartidorId]);
        if (!$stmt->fetch()) {
            $this->redirect('repartidor/inicio');
        }

        $pedidoModel = new PedidoModel();
        $pedidoModel->cambiarEstado($pedidoId, 'en_ruta');

        $db->prepare('UPDATE pedidos SET ruta_iniciada_at = NOW() WHERE id = ?')
           ->execute([$pedidoId]);

        $this->flash('success', '¡Viaje iniciado! Comparte tu ubicación para el seguimiento.');
        $this->redirect('repartidor/pedidoDirecto/' . $pedidoId);
    }

    // ── Vista de entrega directa de un pedido (GPS + foto) ────────────────────
    public function pedidoDirecto(?string $pedidoId = null): void
    {
        if (!$pedidoId) {
            $this->redirect('repartidor/inicio');
        }

        $pedidoId     = (int)$pedidoId;
        $repartidorId = $this->usuarioId();
        $db           = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT p.*, e.razon_social AS empresa_nombre,
                    e.lat AS empresa_lat, e.lng AS empresa_lng,
                    e.direccion_fiscal AS empresa_direccion
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.id = ? AND p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor' AND p.estado IN ('en_preparacion','en_ruta')"
        );
        $stmt->execute([$pedidoId, $repartidorId]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            $this->redirect('repartidor/inicio');
        }

        // Firebase config para el tracking
        $cfgModel    = new ConfigModel();
        $firebaseConfig = [
            'apiKey'      => $cfgModel->get('firebase_api_key', ''),
            'authDomain'  => $cfgModel->get('firebase_auth_domain', ''),
            'databaseURL' => $cfgModel->get('firebase_database_url', ''),
            'projectId'   => $cfgModel->get('firebase_project_id', ''),
            'appId'       => $cfgModel->get('firebase_app_id', ''),
        ];
        $firebaseActivo = !empty($firebaseConfig['apiKey']) && !empty($firebaseConfig['databaseURL']);

        $pedidoModel = new PedidoModel();
        $sucursales  = $pedidoModel->getSucursalesPedido($pedidoId);

        $flash     = $this->getFlash();
        $pageTitle = 'Entrega — ' . $pedido['folio'];

        require ROOT_PATH . '/app/views/repartidor/pedido_directo.php';
    }

    // ── Confirmar entrega directa (foto + marcar entregado) ──────────────────
    public function confirmarEntregaDirecta(?string $pedidoId = null): void
    {
        if (!$this->isPost() || !$pedidoId) {
            $this->redirect('repartidor/inicio');
        }

        $pedidoId     = (int)$pedidoId;
        $repartidorId = $this->usuarioId();
        $db           = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT id FROM pedidos
              WHERE id = ? AND repartidor_asignado_id = ?
                AND tipo_entrega = 'repartidor' AND estado = 'en_ruta'"
        );
        $stmt->execute([$pedidoId, $repartidorId]);
        if (!$stmt->fetch()) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('repartidor/inicio');
        }

        if (empty($_FILES['foto']['tmp_name'])) {
            $this->flash('error', 'Debes tomar una foto como evidencia de entrega.');
            $this->redirect('repartidor/pedidoDirecto/' . $pedidoId);
        }

        $fotoPath = $this->guardarFoto($_FILES['foto'], 'dir_' . $pedidoId);
        if (!$fotoPath) {
            $this->flash('error', 'Formato de imagen no válido. Usa JPG, PNG o WEBP.');
            $this->redirect('repartidor/pedidoDirecto/' . $pedidoId);
        }

        $pedidoModel = new PedidoModel();
        $pedidoModel->subirFotoEntrega($pedidoId, $fotoPath);

        $this->log('Entrega directa confirmada', 'repartidor', "Pedido ID: $pedidoId");
        $this->flash('success', '¡Entrega completada! El pedido fue marcado como entregado.');
        $this->redirect('repartidor/inicio');
    }

    // ── Confirmar entrega en una sucursal (multi-parada directo) ─────────────
    public function confirmarParadaDirecta(?string $pedidoSucursalId = null): void
    {
        if (!$this->isPost() || !$pedidoSucursalId) {
            $this->redirect('repartidor/inicio');
        }

        $psId         = (int)$pedidoSucursalId;
        $repartidorId = $this->usuarioId();
        $db           = Database::getInstance();

        // Verificar que esta parada pertenece a un pedido asignado a este repartidor
        $stmt = $db->prepare(
            'SELECT ps.pedido_id FROM pedido_sucursal ps
               JOIN pedidos p ON p.id = ps.pedido_id
              WHERE ps.id = ? AND p.repartidor_asignado_id = ?
                AND p.tipo_entrega = \'repartidor\' AND p.estado = \'en_ruta\''
        );
        $stmt->execute([$psId, $repartidorId]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->flash('error', 'Parada no encontrada.');
            $this->redirect('repartidor/inicio');
        }

        $pedidoId = (int)$row['pedido_id'];

        if (empty($_FILES['foto']['tmp_name']) && empty($_POST['foto_base64'])) {
            $this->flash('error', 'Debes tomar una foto como evidencia de entrega.');
            $this->redirect('repartidor/pedidoDirecto/' . $pedidoId);
        }

        // Foto: upload de archivo o base64 desde cámara
        $fotoPath = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $fotoPath = $this->guardarFoto($_FILES['foto'], 'ps_' . $psId);
        } elseif (!empty($_POST['foto_base64'])) {
            $b64 = $_POST['foto_base64'];
            if (str_starts_with($b64, 'data:image/')) {
                $data   = explode(',', $b64)[1] ?? '';
                $bytes  = base64_decode($data);
                $nombre = 'foto_ps_' . $psId . '_' . time() . '.jpg';
                $dir    = UPLOAD_PATH . 'entregas/';
                if (!is_dir($dir)) mkdir($dir, 0755, true);
                file_put_contents($dir . $nombre, $bytes);
                $fotoPath = UPLOAD_URL . 'entregas/' . $nombre;
            }
        }

        if (!$fotoPath) {
            $this->flash('error', 'Formato de imagen no válido. Usa JPG, PNG o WEBP.');
            $this->redirect('repartidor/pedidoDirecto/' . $pedidoId);
        }

        // Firma digital (opcional pero requerida en el front)
        $firmaPath = null;
        if (!empty($_POST['firma_data'])) {
            $firmaPath = $this->guardarFirma($_POST['firma_data'], 'ps_' . $psId);
        }

        $pedidoModel = new PedidoModel();
        $pedidoModel->confirmarSucursalEntrega($psId, $fotoPath, $firmaPath);

        // Si ya se entregaron todas las sucursales, finalizar el viaje
        if ($pedidoModel->allSucursalesEntregadas($pedidoId)) {
            // Comprimir trail GPS y guardar en pedido
            $rows = $db->prepare(
                'SELECT lat, lng FROM tracking_posiciones WHERE pedido_id = ? ORDER BY ts ASC LIMIT 300'
            );
            $rows->execute([$pedidoId]);
            $pts = $rows->fetchAll(\PDO::FETCH_NUM);

            $sampled = $this->samplePoints($pts, 100);
            $pedidoModel->saveRutaPolyline($pedidoId, json_encode($sampled));

            $this->flash('success', '¡Viaje finalizado! Todas las sucursales entregadas.');
            $this->redirect('repartidor/historial');
        }

        $this->flash('success', 'Sucursal entregada. Continúa al siguiente destino.');
        $this->redirect('repartidor/pedidoDirecto/' . $pedidoId);
    }

    public function entrega(?string $paradaId = null): void
    {
        if (!$paradaId) {
            $this->redirect('repartidor/inicio');
        }

        $repartidorId = $this->usuarioId();
        $db = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT rd.*, s.nombre AS sucursal_nombre, s.direccion, s.lat, s.lng,
                    p.folio, p.notas, e.razon_social AS empresa_nombre
               FROM ruta_detalle rd
               JOIN rutas r ON r.id = rd.ruta_id
               JOIN sucursales s ON s.id = rd.sucursal_id
               JOIN pedidos p ON p.id = rd.pedido_id
               JOIN empresas e ON e.id = p.empresa_id
              WHERE rd.id = ? AND r.repartidor_id = ?"
        );
        $stmt->execute([$paradaId, $repartidorId]);
        $parada = $stmt->fetch();

        if (!$parada) {
            $this->redirect('repartidor/inicio');
        }

        $flash     = $this->getFlash();
        $pageTitle = 'Detalle de entrega';

        require ROOT_PATH . '/app/views/repartidor/entrega.php';
    }

    public function confirmarEntrega(?string $paradaId = null): void
    {
        if (!$this->isPost() || !$paradaId) {
            $this->redirect('repartidor/inicio');
        }

        $repartidorId = $this->usuarioId();
        $db = Database::getInstance();

        // Verificar que la parada pertenece a este repartidor y obtener pedido+sucursal
        $stmt = $db->prepare(
            'SELECT rd.id, rd.pedido_id, rd.sucursal_id FROM ruta_detalle rd
               JOIN rutas r ON r.id = rd.ruta_id
              WHERE rd.id = ? AND r.repartidor_id = ?'
        );
        $stmt->execute([$paradaId, $repartidorId]);
        $paradaRow = $stmt->fetch();
        if (!$paradaRow) {
            $this->redirect('repartidor/inicio');
        }
        $pedidoIdParada   = (int)$paradaRow['pedido_id'];
        $sucursalIdParada = (int)$paradaRow['sucursal_id'];

        // Procesar firma (base64 → archivo)
        $firmaPath = null;
        if (!empty($_POST['firma_data'])) {
            $firmaPath = $this->guardarFirma($_POST['firma_data'], $paradaId);
        }

        // Procesar foto (upload)
        $fotoPath = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $fotoPath = $this->guardarFoto($_FILES['foto'], $paradaId);
        }

        // Guardar evidencia en tabla dedicada (rutas formales)
        $db->prepare(
            'INSERT INTO evidencias_entrega (ruta_detalle_id, nombre_receptor, firma_path, foto_path)
             VALUES (?, ?, ?, ?)'
        )->execute([$paradaId, $this->post('nombre_receptor'), $firmaPath, $fotoPath]);

        // Sincronizar en pedido_sucursal para que el detalle del pedido muestre firma y foto
        $db->prepare(
            "UPDATE pedido_sucursal
                SET estado = 'entregado', firma_path = ?, foto_entrega_path = ?, fecha_llegada = NOW()
              WHERE pedido_id = ? AND sucursal_id = ?"
        )->execute([$firmaPath, $fotoPath, $pedidoIdParada, $sucursalIdParada]);

        // Actualizar estado de parada
        $db->prepare(
            "UPDATE ruta_detalle SET estado = 'entregado', hora_entrega = NOW(), tracking_activo = 0 WHERE id = ?"
        )->execute([$paradaId]);

        // Actualizar estado del pedido si todas las paradas están entregadas
        $pedidoId = $pedidoIdParada;

        if ($pedidoId) {
            $stmt2 = $db->prepare(
                "SELECT COUNT(*) FROM ruta_detalle WHERE pedido_id = ? AND estado != 'entregado'"
            );
            $stmt2->execute([$pedidoId]);
            if ((int)$stmt2->fetchColumn() === 0) {
                $db->prepare("UPDATE pedidos SET estado = 'entregado' WHERE id = ?")
                   ->execute([$pedidoId]);
            }
        }

        $this->log('Entrega confirmada', 'repartidor', "Parada ID: $paradaId");
        $this->flash('success', 'Entrega registrada correctamente.');
        $this->redirect('repartidor/inicio');
    }

    public function historial(?string $p = null): void
    {
        $repartidorId = $this->usuarioId();
        $db = Database::getInstance();

        // Paradas de ruta formal entregadas
        $stmt = $db->prepare(
            "SELECT rd.*, s.nombre AS sucursal_nombre, p.folio,
                    r.fecha, e.razon_social AS empresa_nombre
               FROM ruta_detalle rd
               JOIN rutas r ON r.id = rd.ruta_id
               JOIN sucursales s ON s.id = rd.sucursal_id
               JOIN pedidos p ON p.id = rd.pedido_id
               JOIN empresas e ON e.id = p.empresa_id
              WHERE r.repartidor_id = ? AND rd.estado = 'entregado'
           ORDER BY rd.hora_entrega DESC LIMIT 50"
        );
        $stmt->execute([$repartidorId]);
        $historial = $stmt->fetchAll();

        // Pedidos directos completados (multi-parada)
        $stmtDirectos = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.ruta_polyline, p.ruta_iniciada_at,
                    p.ruta_finalizada_at, p.fecha_entrega,
                    e.razon_social AS empresa_nombre
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'
                AND p.estado = 'entregado'
           ORDER BY p.ruta_finalizada_at DESC, p.id DESC
              LIMIT 30"
        );
        $stmtDirectos->execute([$repartidorId]);
        $pedidosEntregados = $stmtDirectos->fetchAll();

        // Sucursales por pedido directo
        if (!empty($pedidosEntregados)) {
            $ids = implode(',', array_map('intval', array_column($pedidosEntregados, 'id')));
            $stmtSucs = $db->query(
                "SELECT ps.pedido_id, ps.foto_entrega_path, ps.fecha_llegada,
                        s.nombre AS sucursal_nombre
                   FROM pedido_sucursal ps
                   JOIN sucursales s ON s.id = ps.sucursal_id
                  WHERE ps.pedido_id IN ($ids)
                  ORDER BY ps.id ASC"
            );
            $sucsPorPedido = [];
            foreach ($stmtSucs->fetchAll() as $row) {
                $sucsPorPedido[$row['pedido_id']][] = $row;
            }
            foreach ($pedidosEntregados as &$pd) {
                $pd['sucursales'] = $sucsPorPedido[$pd['id']] ?? [];
            }
            unset($pd);
        }

        $flash     = $this->getFlash();
        $pageTitle = 'Historial de entregas';

        require ROOT_PATH . '/app/views/repartidor/historial.php';
    }

    public function verViaje(?string $pedidoId = null): void
    {
        $repartidorId = $this->usuarioId();
        $db = Database::getInstance();
        $pedidoId = (int)$pedidoId;

        if (!$pedidoId) {
            $this->redirect('repartidor/historial');
        }

        $stmt = $db->prepare(
            "SELECT p.id, p.folio, p.ruta_polyline, p.ruta_iniciada_at, p.ruta_finalizada_at,
                    p.estado, e.razon_social AS empresa_nombre
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.id = ? AND p.repartidor_asignado_id = ?"
        );
        $stmt->execute([$pedidoId, $repartidorId]);
        $pedido = $stmt->fetch();

        if (!$pedido) {
            $this->redirect('repartidor/historial');
        }

        $pedidoModel = new PedidoModel();
        $sucursales  = $pedidoModel->getSucursalesPedido($pedidoId);

        $flash     = $this->getFlash();
        $pageTitle = 'Recorrido — ' . $pedido['folio'];

        require ROOT_PATH . '/app/views/repartidor/ver_viaje.php';
    }

    private function samplePoints(array $pts, int $max): array
    {
        if (count($pts) <= $max) return $pts;
        $step    = count($pts) / ($max - 1);
        $sampled = [];
        for ($i = 0; $i < $max - 1; $i++) {
            $sampled[] = $pts[(int)round($i * $step)];
        }
        $sampled[] = $pts[count($pts) - 1];
        return $sampled;
    }

    private function guardarFirma(string $base64, string $paradaId): ?string
    {
        if (!str_starts_with($base64, 'data:image/')) return null;
        $data   = explode(',', $base64)[1] ?? '';
        $bytes  = base64_decode($data);
        $nombre = 'firma_' . $paradaId . '_' . time() . '.png';
        $ruta   = UPLOAD_PATH . 'firmas/';
        if (!is_dir($ruta)) mkdir($ruta, 0755, true);
        file_put_contents($ruta . $nombre, $bytes);
        return UPLOAD_URL . 'firmas/' . $nombre;
    }

    private function guardarFoto(array $file, string $paradaId): ?string
    {
        $ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allow  = ['jpg','jpeg','png','webp'];
        if (!in_array($ext, $allow, true)) return null;
        $nombre = 'foto_' . $paradaId . '_' . time() . '.' . $ext;
        $ruta   = UPLOAD_PATH . 'entregas/';
        if (!is_dir($ruta)) mkdir($ruta, 0755, true);
        move_uploaded_file($file['tmp_name'], $ruta . $nombre);
        return UPLOAD_URL . 'entregas/' . $nombre;
    }
}
