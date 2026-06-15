<?php
/**
 * MobileApiController — API REST para la app móvil de repartidores
 * Autenticación: Bearer token (no usa sesión PHP)
 * Prefijo de ruta: /mobile-api/{action}/{param}
 */

require_once ROOT_PATH . '/app/controllers/BaseController.php';

class MobileApiController extends BaseController
{
    // Días de vida del token
    private const TOKEN_DAYS = 30;

    public function __construct()
    {
        // No llamamos parent::__construct() para evitar que lea $_SESSION
        // La autenticación se hace vía Bearer token en cada método protegido
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers internos
    // ─────────────────────────────────────────────────────────────────────────

    private function jsonOk(mixed $data): void
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['ok' => true, 'data' => $data]);
        exit;
    }

    private function jsonError(string $message, int $code = 400): void
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['ok' => false, 'error' => $message]);
        exit;
    }

    /** Extrae y valida el Bearer token; devuelve el usuario o llama jsonError */
    private function requireMobileAuth(): array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!str_starts_with($header, 'Bearer ')) {
            $this->jsonError('Token requerido', 401);
        }

        $plainToken = substr($header, 7);
        $hash       = hash('sha256', $plainToken);
        $db         = Database::getInstance();

        $stmt = $db->prepare(
            "SELECT mt.usuario_id, mt.expires_at, mt.id AS token_id,
                    u.nombre, u.apellido_paterno, u.email, u.activo,
                    r.slug AS rol_slug
               FROM mobile_tokens mt
               JOIN usuarios u ON u.id = mt.usuario_id
               JOIN roles r ON r.id = u.rol_id
              WHERE mt.token_hash = ? AND mt.expires_at > NOW()"
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->jsonError('Token inválido o expirado', 401);
        }

        if (!(int)$row['activo']) {
            $this->jsonError('Cuenta desactivada', 403);
        }

        if ($row['rol_slug'] !== 'repartidor') {
            $this->jsonError('Acceso solo para repartidores', 403);
        }

        // Actualizar last_used_at (sin bloquear si falla)
        try {
            $db->prepare("UPDATE mobile_tokens SET last_used_at = NOW() WHERE id = ?")
               ->execute([$row['token_id']]);
        } catch (\Throwable $e) {
            // No crítico
        }

        return $row;
    }

    private function bodyJson(): array
    {
        $raw = file_get_contents('php://input');
        return json_decode($raw, true) ?? [];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CORS preflight
    // ─────────────────────────────────────────────────────────────────────────

    public function options(?string $p = null): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type');
        http_response_code(204);
        exit;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/login
    // ─────────────────────────────────────────────────────────────────────────

    public function login(?string $p = null): void
    {
        header('Access-Control-Allow-Origin: *');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            $this->options();
        }

        $body  = $this->bodyJson();
        $email = trim($body['email'] ?? '');
        $pass  = $body['password'] ?? '';
        $device = substr(trim($body['device_name'] ?? 'App'), 0, 100);

        if (!$email || !$pass) {
            $this->jsonError('Email y contraseña requeridos');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->jsonError('Email inválido');
        }

        $db           = Database::getInstance();
        $usuarioModel = new UsuarioModel();
        $usuario      = $usuarioModel->getByEmail($email);

        if (!$usuario || !password_verify($pass, $usuario['password'])) {
            $this->jsonError('Credenciales incorrectas', 401);
        }

        if (!(int)$usuario['activo']) {
            $this->jsonError('Cuenta desactivada. Contacta al administrador.', 403);
        }

        // Verificar que sea repartidor
        $stmt = $db->prepare("SELECT slug FROM roles WHERE id = ?");
        $stmt->execute([$usuario['rol_id']]);
        $rol = $stmt->fetchColumn();

        if ($rol !== 'repartidor') {
            $this->jsonError('Solo los repartidores pueden usar la app móvil.', 403);
        }

        // Generar token seguro
        $plainToken = bin2hex(random_bytes(32));   // 64 chars hex
        $tokenHash  = hash('sha256', $plainToken);
        $expiresAt  = date('Y-m-d H:i:s', strtotime('+' . self::TOKEN_DAYS . ' days'));

        // Limpiar tokens viejos del usuario (máximo 5 dispositivos)
        $db->prepare(
            "DELETE FROM mobile_tokens WHERE usuario_id = ?
             AND id NOT IN (
               SELECT id FROM (
                 SELECT id FROM mobile_tokens WHERE usuario_id = ?
                 ORDER BY created_at DESC LIMIT 4
               ) t
             )"
        )->execute([$usuario['id'], $usuario['id']]);

        $db->prepare(
            "INSERT INTO mobile_tokens (usuario_id, token_hash, device_name, expires_at)
             VALUES (?, ?, ?, ?)"
        )->execute([$usuario['id'], $tokenHash, $device, $expiresAt]);

        $this->jsonOk([
            'token'      => $plainToken,
            'expires_at' => $expiresAt,
            'usuario'    => [
                'id'     => (int)$usuario['id'],
                'nombre' => trim($usuario['nombre'] . ' ' . ($usuario['apellido_paterno'] ?? '')),
                'email'  => $usuario['email'],
                'rol'    => $rol,
                'avatar' => $usuario['avatar'] ?? null,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/logout
    // ─────────────────────────────────────────────────────────────────────────

    public function logout(?string $p = null): void
    {
        header('Access-Control-Allow-Origin: *');
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (str_starts_with($header, 'Bearer ')) {
            $hash = hash('sha256', substr($header, 7));
            Database::getInstance()
                ->prepare("DELETE FROM mobile_tokens WHERE token_hash = ?")
                ->execute([$hash]);
        }
        $this->jsonOk(['message' => 'Sesión cerrada']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /mobile-api/inicio
    // ─────────────────────────────────────────────────────────────────────────

    public function inicio(?string $p = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];
        $db           = Database::getInstance();

        // Ruta del día
        $stmt = $db->prepare(
            "SELECT r.id, r.estado, r.fecha,
                    COUNT(rd.id) AS total_paradas,
                    SUM(CASE WHEN rd.estado = 'entregado' THEN 1 ELSE 0 END) AS entregadas
               FROM rutas r
               JOIN ruta_detalle rd ON rd.ruta_id = r.id
              WHERE r.repartidor_id = ? AND r.fecha = CURDATE()
                AND r.estado IN ('planificada','en_curso')
           GROUP BY r.id
           ORDER BY r.estado DESC LIMIT 1"
        );
        $stmt->execute([$repartidorId]);
        $rutaHoy = $stmt->fetch() ?: null;

        $paradas = [];
        if ($rutaHoy) {
            $stmt = $db->prepare(
                "SELECT rd.id, rd.estado, rd.orden, rd.hora_entrega,
                        s.nombre AS sucursal_nombre, s.direccion, s.lat, s.lng,
                        p.folio AS pedido_folio, p.id AS pedido_id,
                        p.notas,
                        e.razon_social AS empresa_nombre
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

        // Pedidos directos activos
        $stmtDirectos = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.fecha_entrega,
                    p.direccion_entrega, p.nota_empresa,
                    e.razon_social AS empresa_nombre,
                    e.lat AS empresa_lat, e.lng AS empresa_lng
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'
                AND p.estado IN ('en_preparacion', 'en_ruta')
              ORDER BY p.fecha_entrega ASC, p.id ASC"
        );
        $stmtDirectos->execute([$repartidorId]);
        $pedidosDirectos = $stmtDirectos->fetchAll();

        // Sucursales por pedido directo
        if (!empty($pedidosDirectos)) {
            $ids = implode(',', array_map('intval', array_column($pedidosDirectos, 'id')));
            $stmtSucs = $db->query(
                "SELECT ps.id, ps.pedido_id, ps.estado,
                        ps.foto_entrega_path, ps.firma_path,
                        s.nombre AS sucursal_nombre, s.direccion, s.lat, s.lng
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

        // KPIs a nivel de PEDIDO: entregados, en camino, pendientes, vencidos
        $stmtKpi = $db->prepare(
            "SELECT
                SUM(CASE WHEN p.estado = 'entregado' THEN 1 ELSE 0 END) AS entregados,
                SUM(CASE WHEN p.estado = 'en_ruta' THEN 1 ELSE 0 END) AS en_camino,
                SUM(CASE WHEN p.estado IN ('confirmado','en_preparacion')
                          AND (p.fecha_entrega IS NULL OR p.fecha_entrega >= CURDATE())
                          THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN p.estado IN ('pendiente','confirmado','en_preparacion')
                          AND p.fecha_entrega IS NOT NULL
                          AND p.fecha_entrega < CURDATE()
                          THEN 1 ELSE 0 END) AS vencidos
               FROM pedidos p
              WHERE p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'"
        );
        $stmtKpi->execute([$repartidorId]);
        $kpiD = $stmtKpi->fetch() ?: [];

        $this->jsonOk([
            'ruta_hoy'        => $rutaHoy,
            'paradas'         => array_values($paradas),
            'pedidos_directos'=> array_values($pedidosDirectos),
            'kpis'            => [
                'entregados' => (int)($kpiD['entregados'] ?? 0),
                'en_camino'  => (int)($kpiD['en_camino']  ?? 0),
                'pendientes' => (int)($kpiD['pendientes'] ?? 0),
                'vencidos'   => (int)($kpiD['vencidos']   ?? 0),
            ],
            'usuario_nombre' => trim($user['nombre'] . ' ' . $user['apellido_paterno']),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /mobile-api/kpiDetalle/{tipo}
    // tipo: entregados | en_camino | pendientes | vencidos
    // ─────────────────────────────────────────────────────────────────────────

    public function kpiDetalle(?string $tipo = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];

        $allowed = ['entregados', 'en_camino', 'pendientes', 'vencidos'];
        if (!in_array($tipo, $allowed, true)) $this->jsonError('Tipo de KPI inválido');

        $db = Database::getInstance();

        $where = match ($tipo) {
            'entregados' => "p.estado = 'entregado'",
            'en_camino'  => "p.estado = 'en_ruta'",
            'pendientes' => "p.estado IN ('confirmado','en_preparacion') AND (p.fecha_entrega IS NULL OR p.fecha_entrega >= CURDATE())",
            'vencidos'   => "p.estado IN ('pendiente','confirmado','en_preparacion') AND p.fecha_entrega IS NOT NULL AND p.fecha_entrega < CURDATE()",
        };

        $stmt = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.fecha_entrega, p.ruta_finalizada_at,
                    e.razon_social AS empresa_nombre,
                    CASE WHEN p.fecha_entrega < CURDATE() AND p.estado NOT IN ('entregado','cancelado')
                         THEN DATEDIFF(CURDATE(), p.fecha_entrega) ELSE NULL END AS dias_retraso
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'
                AND {$where}
           ORDER BY p.fecha_entrega DESC, p.id DESC
              LIMIT 50"
        );
        $stmt->execute([$repartidorId]);
        $pedidos = $stmt->fetchAll();

        if (!empty($pedidos)) {
            $ids          = implode(',', array_map('intval', array_column($pedidos, 'id')));
            $sucsPorPedido = [];
            try {
                $stmtSucs = $db->query(
                    "SELECT ps.pedido_id, ps.foto_entrega_path, ps.firma_path,
                            ps.fecha_llegada,
                            s.nombre AS sucursal_nombre
                       FROM pedido_sucursal ps
                       JOIN sucursales s ON s.id = ps.sucursal_id
                      WHERE ps.pedido_id IN ($ids)
                      ORDER BY ps.id ASC"
                );
                foreach ($stmtSucs->fetchAll() as $row) {
                    $sucsPorPedido[$row['pedido_id']][] = $row;
                }
            } catch (\PDOException $e) {
                // Columna aún no existe en DB — aplicar migración 029
            }
            foreach ($pedidos as &$pd) {
                $pd['sucursales']   = $sucsPorPedido[$pd['id']] ?? [];
                $pd['dias_retraso'] = $pd['dias_retraso'] !== null ? (int)$pd['dias_retraso'] : null;
            }
            unset($pd);
        }

        $this->jsonOk(['tipo' => $tipo, 'pedidos' => array_values($pedidos)]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /mobile-api/pedidoDirecto/{pedidoId}
    // ─────────────────────────────────────────────────────────────────────────

    public function pedidoDirecto(?string $pedidoId = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];
        $pedidoId     = (int)$pedidoId;

        if (!$pedidoId) $this->jsonError('Pedido inválido');

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT p.*, e.razon_social AS empresa_nombre,
                    e.lat AS empresa_lat, e.lng AS empresa_lng,
                    e.direccion_fiscal AS empresa_direccion
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.id = ? AND p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'
                AND p.estado IN ('en_preparacion','en_ruta')"
        );
        $stmt->execute([$pedidoId, $repartidorId]);
        $pedido = $stmt->fetch();

        if (!$pedido) $this->jsonError('Pedido no encontrado o no autorizado', 404);

        $pedidoModel = new PedidoModel();
        $sucursales  = $pedidoModel->getSucursalesPedido($pedidoId);

        $this->jsonOk([
            'pedido'     => $pedido,
            'sucursales' => $sucursales,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/iniciarViaje/{pedidoId}
    // ─────────────────────────────────────────────────────────────────────────

    public function iniciarViaje(?string $pedidoId = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];
        $pedidoId     = (int)$pedidoId;

        if (!$pedidoId) $this->jsonError('Pedido inválido');

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT id FROM pedidos
              WHERE id = ? AND repartidor_asignado_id = ?
                AND tipo_entrega = 'repartidor' AND estado = 'en_preparacion'"
        );
        $stmt->execute([$pedidoId, $repartidorId]);
        if (!$stmt->fetch()) $this->jsonError('Pedido no encontrado', 404);

        $pedidoModel = new PedidoModel();
        $pedidoModel->cambiarEstado($pedidoId, 'en_ruta');
        $db->prepare("UPDATE pedidos SET ruta_iniciada_at = NOW() WHERE id = ?")
           ->execute([$pedidoId]);

        $this->jsonOk(['message' => 'Viaje iniciado']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/tracking
    // Body JSON: { pedido_id, ruta_detalle_id (opcional), lat, lng }
    // ─────────────────────────────────────────────────────────────────────────

    public function tracking(?string $p = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];

        $body     = $this->bodyJson();
        $lat      = (float)($body['lat']  ?? 0);
        $lng      = (float)($body['lng']  ?? 0);
        $pedidoId = (int)($body['pedido_id'] ?? 0);
        $paradaId = (int)($body['ruta_detalle_id'] ?? 0);

        if (!$lat || !$lng) $this->jsonError('Coordenadas inválidas');
        if (!$pedidoId)     $this->jsonError('pedido_id requerido');

        $db = Database::getInstance();

        // Insertar posición en historial
        $db->prepare(
            "INSERT INTO tracking_posiciones (pedido_id, lat, lng, ts) VALUES (?, ?, ?, NOW())"
        )->execute([$pedidoId, $lat, $lng]);

        // Si hay parada de ruta formal, actualizar su posición + ETA
        if ($paradaId) {
            $stmtDest = $db->prepare(
                "SELECT s.lat, s.lng FROM ruta_detalle rd
                   JOIN sucursales s ON s.id = rd.sucursal_id
                  WHERE rd.id = ?"
            );
            $stmtDest->execute([$paradaId]);
            $dest = $stmtDest->fetch();

            $etaMinutos = null;
            if ($dest && $dest['lat'] && $dest['lng']) {
                $distKm     = $this->haversineKm($lat, $lng, (float)$dest['lat'], (float)$dest['lng']);
                $etaMinutos = (int)round(($distKm / 30) * 60); // 30 km/h urbano
            }

            $db->prepare(
                "UPDATE ruta_detalle SET lat_actual = ?, lng_actual = ?, eta_minutos = ?,
                          tracking_activo = 1 WHERE id = ?"
            )->execute([$lat, $lng, $etaMinutos, $paradaId]);
        }

        $this->jsonOk(['message' => 'Posición actualizada']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/confirmarParadaDirecta/{pedidoSucursalId}
    // multipart/form-data: foto (file), firma_data (base64), nombre_receptor
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmarParadaDirecta(?string $psId = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];
        $psId         = (int)$psId;

        if (!$psId) $this->jsonError('ID de parada inválido');

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT ps.pedido_id FROM pedido_sucursal ps
               JOIN pedidos p ON p.id = ps.pedido_id
              WHERE ps.id = ? AND p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor' AND p.estado = 'en_ruta'"
        );
        $stmt->execute([$psId, $repartidorId]);
        $row = $stmt->fetch();
        if (!$row) $this->jsonError('Parada no encontrada', 404);

        $pedidoId = (int)$row['pedido_id'];

        // Foto: multipart upload o base64
        $fotoPath = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $fotoPath = $this->guardarFotoMovil($_FILES['foto'], 'ps_' . $psId);
        } elseif (!empty($_POST['foto_base64'])) {
            $fotoPath = $this->guardarBase64Movil($_POST['foto_base64'], 'ps_' . $psId);
        }

        if (!$fotoPath) $this->jsonError('Foto requerida como evidencia');

        // Firma digital
        $firmaPath = null;
        if (!empty($_POST['firma_data'])) {
            $firmaPath = $this->guardarFirmaMovil($_POST['firma_data'], 'firma_ps_' . $psId);
        }

        $nombreReceptor = substr(trim($_POST['nombre_receptor'] ?? ''), 0, 100) ?: null;
        $pedidoModel = new PedidoModel();
        $pedidoModel->confirmarSucursalEntrega($psId, $fotoPath, $firmaPath, $nombreReceptor);

        // ¿Se entregaron todas las sucursales?
        if ($pedidoModel->allSucursalesEntregadas($pedidoId)) {
            // Comprimir trail GPS
            $rows = $db->prepare(
                "SELECT lat, lng FROM tracking_posiciones WHERE pedido_id = ? ORDER BY ts ASC LIMIT 300"
            );
            $rows->execute([$pedidoId]);
            $pts     = $rows->fetchAll(\PDO::FETCH_NUM);
            $sampled = $this->sampleGPS($pts, 100);
            $pedidoModel->saveRutaPolyline($pedidoId, json_encode($sampled));

            $this->jsonOk(['message' => 'Viaje finalizado', 'viaje_completo' => true]);
        }

        $this->jsonOk(['message' => 'Sucursal entregada', 'viaje_completo' => false]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/confirmarEntrega/{paradaId}  (ruta formal)
    // multipart/form-data: foto, firma_data, nombre_receptor
    // ─────────────────────────────────────────────────────────────────────────

    public function confirmarEntrega(?string $paradaId = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];
        $paradaId     = (int)$paradaId;

        if (!$paradaId) $this->jsonError('ID de parada inválido');

        $db   = Database::getInstance();
        $stmt = $db->prepare(
            "SELECT rd.id, rd.pedido_id, rd.sucursal_id FROM ruta_detalle rd
               JOIN rutas r ON r.id = rd.ruta_id
              WHERE rd.id = ? AND r.repartidor_id = ?"
        );
        $stmt->execute([$paradaId, $repartidorId]);
        $paradaRow = $stmt->fetch();
        if (!$paradaRow) $this->jsonError('Parada no encontrada', 404);

        $pedidoIdParada   = (int)$paradaRow['pedido_id'];
        $sucursalIdParada = (int)$paradaRow['sucursal_id'];

        // Foto
        $fotoPath = null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $fotoPath = $this->guardarFotoMovil($_FILES['foto'], 'ruta_' . $paradaId);
        } elseif (!empty($_POST['foto_base64'])) {
            $fotoPath = $this->guardarBase64Movil($_POST['foto_base64'], 'ruta_' . $paradaId);
        }
        if (!$fotoPath) $this->jsonError('Foto requerida como evidencia');

        // Firma
        $firmaPath = null;
        if (!empty($_POST['firma_data'])) {
            $firmaPath = $this->guardarFirmaMovil($_POST['firma_data'], 'firma_ruta_' . $paradaId);
        }

        $nombreReceptor = substr(trim($_POST['nombre_receptor'] ?? ''), 0, 120);

        // Guardar evidencia en tabla dedicada
        $db->prepare(
            "INSERT INTO evidencias_entrega (ruta_detalle_id, nombre_receptor, firma_path, foto_path)
             VALUES (?, ?, ?, ?)"
        )->execute([$paradaId, $nombreReceptor, $firmaPath, $fotoPath]);

        // Sincronizar en pedido_sucursal
        $db->prepare(
            "UPDATE pedido_sucursal
                SET estado = 'entregado', firma_path = ?, foto_entrega_path = ?, fecha_llegada = NOW()
              WHERE pedido_id = ? AND sucursal_id = ?"
        )->execute([$firmaPath, $fotoPath, $pedidoIdParada, $sucursalIdParada]);

        // Marcar parada como entregada
        $db->prepare(
            "UPDATE ruta_detalle SET estado = 'entregado', hora_entrega = NOW(), tracking_activo = 0
              WHERE id = ?"
        )->execute([$paradaId]);

        // Marcar pedido como entregado si todas las paradas están listas
        $stmt2 = $db->prepare(
            "SELECT COUNT(*) FROM ruta_detalle WHERE pedido_id = ? AND estado != 'entregado'"
        );
        $stmt2->execute([$pedidoIdParada]);
        if ((int)$stmt2->fetchColumn() === 0) {
            $db->prepare("UPDATE pedidos SET estado = 'entregado' WHERE id = ?")
               ->execute([$pedidoIdParada]);
        }

        $this->jsonOk(['message' => 'Entrega registrada']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // POST /mobile-api/subirAvatar
    // Sube o actualiza la foto de perfil del repartidor
    // ─────────────────────────────────────────────────────────────────────────

    public function subirAvatar(?string $p = null): void
    {
        $user   = $this->requireMobileAuth();
        $userId = (int)$user['usuario_id'];
        $db     = Database::getInstance();

        $avatarUrl = null;

        if (!empty($_FILES['foto']['name'])) {
            $avatarUrl = $this->guardarFotoMovil($_FILES['foto'], 'avatar_' . $userId);
            if (!$avatarUrl) {
                $this->jsonError('Formato no válido. Usa JPG, PNG o WEBP.');
            }
        } elseif (!empty($_POST['foto_base64'])) {
            $avatarUrl = $this->guardarBase64Movil($_POST['foto_base64'], 'avatar_' . $userId);
            if (!$avatarUrl) {
                $this->jsonError('No se pudo procesar la imagen.');
            }
        } else {
            $this->jsonError('No se recibió ninguna imagen.');
        }

        $db->prepare("UPDATE usuarios SET avatar = ? WHERE id = ?")
           ->execute([$avatarUrl, $userId]);

        $this->jsonOk(['avatar' => $avatarUrl]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // GET /mobile-api/historial
    // ─────────────────────────────────────────────────────────────────────────

    public function historial(?string $p = null): void
    {
        $user         = $this->requireMobileAuth();
        $repartidorId = (int)$user['usuario_id'];
        $db           = Database::getInstance();

        // Paradas de ruta formal entregadas
        $stmt = $db->prepare(
            "SELECT rd.id, rd.hora_entrega, rd.estado,
                    s.nombre AS sucursal_nombre, s.direccion,
                    p.folio, p.id AS pedido_id,
                    r.fecha,
                    e.razon_social AS empresa_nombre
               FROM ruta_detalle rd
               JOIN rutas r ON r.id = rd.ruta_id
               JOIN sucursales s ON s.id = rd.sucursal_id
               JOIN pedidos p ON p.id = rd.pedido_id
               JOIN empresas e ON e.id = p.empresa_id
              WHERE r.repartidor_id = ? AND rd.estado = 'entregado'
           ORDER BY rd.hora_entrega DESC LIMIT 50"
        );
        $stmt->execute([$repartidorId]);
        $paradas = $stmt->fetchAll();

        // Pedidos directos completados
        $stmtD = $db->prepare(
            "SELECT p.id, p.folio, p.estado, p.ruta_iniciada_at, p.ruta_finalizada_at,
                    p.fecha_entrega,
                    e.razon_social AS empresa_nombre
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
              WHERE p.repartidor_asignado_id = ?
                AND p.tipo_entrega = 'repartidor'
                AND p.estado = 'entregado'
           ORDER BY p.ruta_finalizada_at DESC, p.id DESC
              LIMIT 30"
        );
        $stmtD->execute([$repartidorId]);
        $directos = $stmtD->fetchAll();

        if (!empty($directos)) {
            $ids           = implode(',', array_map('intval', array_column($directos, 'id')));
            $sucsPorPedido = [];
            try {
                $stmtSucs = $db->query(
                    "SELECT ps.pedido_id, ps.foto_entrega_path, ps.firma_path,
                            ps.fecha_llegada,
                            s.nombre AS sucursal_nombre
                       FROM pedido_sucursal ps
                       JOIN sucursales s ON s.id = ps.sucursal_id
                      WHERE ps.pedido_id IN ($ids)
                      ORDER BY ps.id ASC"
                );
                foreach ($stmtSucs->fetchAll() as $row) {
                    $sucsPorPedido[$row['pedido_id']][] = $row;
                }
            } catch (\PDOException $e) {
                // Columna aún no existe en DB — aplicar migración 029
            }
            foreach ($directos as &$pd) {
                $pd['sucursales'] = $sucsPorPedido[$pd['id']] ?? [];
            }
            unset($pd);
        }

        $this->jsonOk([
            'paradas_ruta' => array_values($paradas),
            'directos'     => array_values($directos),
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilidades privadas
    // ─────────────────────────────────────────────────────────────────────────

    private function guardarFotoMovil(array $file, string $prefix): ?string
    {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime    = mime_content_type($file['tmp_name']);
        if (!in_array($mime, $allowed, true)) return null;

        $ext  = match ($mime) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'jpg',
        };
        $dir  = UPLOAD_PATH . 'entregas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $nombre = $prefix . '_' . time() . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], $dir . $nombre)) return null;
        return UPLOAD_URL . 'entregas/' . $nombre;
    }

    private function guardarBase64Movil(string $b64, string $prefix): ?string
    {
        if (!str_starts_with($b64, 'data:image/')) return null;
        $parts = explode(',', $b64, 2);
        $data  = base64_decode($parts[1] ?? '');
        if (!$data) return null;

        $dir = UPLOAD_PATH . 'entregas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $nombre = $prefix . '_' . time() . '.jpg';
        file_put_contents($dir . $nombre, $data);
        return UPLOAD_URL . 'entregas/' . $nombre;
    }

    private function guardarFirmaMovil(string $b64, string $prefix): ?string
    {
        if (!str_starts_with($b64, 'data:image/')) return null;
        $parts = explode(',', $b64, 2);
        $data  = base64_decode($parts[1] ?? '');
        if (!$data) return null;

        $dir = UPLOAD_PATH . 'firmas/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $nombre = $prefix . '_' . time() . '.png';
        file_put_contents($dir . $nombre, $data);
        return UPLOAD_URL . 'firmas/' . $nombre;
    }

    private function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /** Douglas-Peucker simplificado: muestreo uniforme de puntos GPS */
    private function sampleGPS(array $pts, int $max): array
    {
        $n = count($pts);
        if ($n <= $max) return $pts;
        $step   = $n / $max;
        $result = [];
        for ($i = 0; $i < $max; $i++) {
            $result[] = $pts[(int)round($i * $step)];
        }
        return $result;
    }
}
