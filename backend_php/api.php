<?php
/**
 * Amare App — API Backend (CORREGIDO)
 * IMPORTANTE: Este archivo REEMPLAZA el api.php que tienes actualmente.
 * Las rutas /health, /auth/login y /auth/register NO requieren token.
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('X-Requested-With: XMLHttpRequest');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$envPath = __DIR__ . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v, " \t\"'");
    }
}

require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/util.php';

$method = $_SERVER['REQUEST_METHOD'];

$pathInfo = $_SERVER['PATH_INFO'] ?? '';

if ($pathInfo !== '' && $pathInfo !== '/') {
    $path = $pathInfo;
} else {
    $uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $baseDir = str_replace('\\', '/', dirname($scriptName));

    if ($baseDir !== '/' && $baseDir !== '.' && str_starts_with($uriPath, $baseDir)) {
        $path = substr($uriPath, strlen($baseDir));
    } else {
        $path = $uriPath;
    }
}

$path = str_replace('/api.php', '', $path);
$path = '/' . trim($path, '/');

$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $raw = file_get_contents('php://input');
    $body = $raw ? json_decode($raw, true) : null;
}

set_exception_handler(function ($ex) {
    $code = $ex->getCode() ?: 500;
    if (!in_array($code, [400, 401, 403, 404, 409, 422], true)) $code = 500;
    json_response(['detail' => $ex->getMessage()], $code);
});

// ── RUTAS PÚBLICAS ──────────────────────────────────────────────────
if ($path === '/' || $path === '/health') {
    json_response(['status' => 'ok']);
}

if ($path === '/auth/login' && $method === 'POST') {
    require __DIR__ . '/routes/auth/login.php'; exit;
}
if ($path === '/auth/register' && $method === 'POST') {
    require __DIR__ . '/routes/auth/register.php'; exit;
}

if ($path === '/db-test') {
    try {
        $row = db_one("SELECT 1 AS ok, COUNT(*) AS total_usuarios FROM mobile_usuarios");
        json_response(['db_ok' => true, 'total_usuarios' => (int)($row['total_usuarios'] ?? 0)]);
    } catch (Throwable $e) {
        json_response(['db_ok' => false, 'error' => $e->getMessage()], 500);
    }
}

// ── RUTAS PROTEGIDAS ────────────────────────────────────────────────
if (!get_bearer_token()) {
    json_response(['detail' => 'Token inválido o expirado.'], 401);
}
$userId = require_auth();

if ($path === '/auth/logout' && $method === 'POST') {
    require __DIR__ . '/routes/auth/logout.php'; exit;
}

if ($path === '/auth/me' && $method === 'GET') {
    require __DIR__ . '/routes/auth/me.php'; exit;
}

// ── Menú ────────────────────────────────────────────────────────────
if ($path === '/restaurants/1/menu' && $method === 'GET') {
    require __DIR__ . '/routes/menu/get.php'; exit;
}
// Menú dinámico para cualquier restaurante
if (preg_match('#^/restaurants/(\d+)/menu$#', $path, $m) && $method === 'GET') {
    require __DIR__ . '/routes/menu/get.php'; exit;
}

// ── Órdenes ─────────────────────────────────────────────────────────
if ($path === '/orders' && $method === 'POST') {
    $db = db();
    $restauranteId = (int)($body['restaurante_id'] ?? 1);
    $tipoPedido = $body['tipo_pedido'] ?? 'pickup';
    $subtotal = (float)($body['subtotal'] ?? 0);
    $total = (float)($body['total'] ?? $subtotal);
    $notas = $body['notas'] ?? null;
    $items = $body['items'] ?? [];
    $folio = 'AM-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

    $stmt = $db->prepare('INSERT INTO rest_pedidos (restaurante_id, mobile_usuario_id, folio, tipo_pedido, estado, subtotal, total, notas, created_at) VALUES (?, ?, ?, ?, "pendiente", ?, ?, ?, NOW())');
    $stmt->execute([$restauranteId, $userId, $folio, $tipoPedido, $subtotal, $total, $notas]);
    $orderId = $db->lastInsertId();

    foreach ($items as $item) {
        $pid = (int)($item['platillo_id'] ?? $item['product_id'] ?? 0);
        $qty = (int)($item['cantidad'] ?? $item['quantity'] ?? 1);
        $price = (float)($item['precio_unit'] ?? $item['unit_price'] ?? 0);
        $itemNotas = $item['notas'] ?? $item['options'] ?? null;
        if ($pid > 0) {
            $ins = $db->prepare('INSERT INTO rest_pedido_items (pedido_id, platillo_id, cantidad, precio_unit, notas, estado) VALUES (?, ?, ?, ?, ?, "pendiente")');
            $ins->execute([$orderId, $pid, $qty, $price, $itemNotas]);
        }
    }

    json_response(['success' => true, 'data' => ['order' => ['id' => (int)$orderId, 'folio' => $folio, 'estado' => 'pendiente', 'total' => $total]]], 201);
    exit;
}

if ($path === '/orders' && $method === 'GET') {
    $db = db();
    $stmt = $db->prepare('SELECT p.*, r.nombre AS restaurante_nombre FROM rest_pedidos p LEFT JOIN rest_restaurantes r ON r.id = p.restaurante_id WHERE p.mobile_usuario_id = ? ORDER BY p.id DESC');
    $stmt->execute([$userId]);
    json_response(['success' => true, 'data' => ['orders' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    exit;
}

if (preg_match('#^/orders/(\d+)$#', $path, $m) && $method === 'GET') {
    $db = db();
    $oid = (int)$m[1];
    $stmt = $db->prepare('SELECT p.*, r.nombre AS restaurante_nombre FROM rest_pedidos p LEFT JOIN rest_restaurantes r ON r.id = p.restaurante_id WHERE p.id = ? AND p.mobile_usuario_id = ?');
    $stmt->execute([$oid, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$order) json_response(['detail' => 'Orden no encontrada'], 404);
    try {
        $istmt = $db->prepare('SELECT pi.*, rp.nombre as platillo_nombre, rp.imagen as platillo_imagen, (pi.cantidad * pi.precio_unit) AS subtotal FROM rest_pedido_items pi LEFT JOIN rest_platillos rp ON pi.platillo_id = rp.id WHERE pi.pedido_id = ?');
        $istmt->execute([$oid]);
    } catch (Exception $e) {
        $istmt = $db->prepare('SELECT * FROM rest_pedido_items WHERE pedido_id = ?');
        $istmt->execute([$oid]);
    }
    $order['items'] = $istmt->fetchAll(PDO::FETCH_ASSOC);
    json_response(['success' => true, 'data' => ['order' => $order]]);
    exit;
}

// ── Social ──────────────────────────────────────────────────────────
if ($path === '/users/social-status' && in_array($method, ['POST', 'PATCH'], true)) {
    require __DIR__ . '/routes/social/update_status.php'; exit;
}
if (preg_match('#^/restaurants/(\d+)/(active-users|active-diners)$#', $path, $m) && $method === 'GET') {
    $db = db();
    $restaurantId = (int)$m[1];
    $sql = "SELECT id AS user_id, nombre, foto_url, edad, genero, sexualidad, descripcion, intereses, que_busca
            FROM mobile_usuarios
            WHERE is_social_active = 1
              AND current_restaurante_id = ?
              AND id != ?";
    $params = [$restaurantId, $userId];
    // Aplicar filtros opcionales
    if (!empty($_GET['edad_min'])) {
        $sql .= " AND edad >= ?";
        $params[] = (int)$_GET['edad_min'];
    }
    if (!empty($_GET['edad_max'])) {
        $sql .= " AND edad <= ?";
        $params[] = (int)$_GET['edad_max'];
    }
    if (!empty($_GET['genero'])) {
        $sql .= " AND genero = ?";
        $params[] = $_GET['genero'];
    }
    if (!empty($_GET['sexualidad'])) {
        $sql .= " AND sexualidad = ?";
        $params[] = $_GET['sexualidad'];
    }
    $sql .= " ORDER BY social_updated_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $diners = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Formatear IDs como enteros
    $result = array_map(function ($d) {
        return [
            'user_id' => (int)$d['user_id'],
            'nombre' => $d['nombre'],
            'foto_url' => $d['foto_url'] ?? null,
            'edad' => $d['edad'] ?? null,
            'genero' => $d['genero'] ?? null,
            'sexualidad' => $d['sexualidad'] ?? null,
            'descripcion' => $d['descripcion'] ?? null,
            'intereses' => $d['intereses'] ?? null,
            'que_busca' => $d['que_busca'] ?? null,
        ];
    }, $diners);
    json_response(['success' => true, 'data' => $result]);
    exit;
}
if (preg_match('#^/users/(\d+)/public-profile$#', $path, $m) && $method === 'GET') {
    $db = db();
    $profileUserId = (int)$m[1];
    $stmt = $db->prepare('SELECT id AS user_id, nombre, foto_url, edad, sexualidad, genero, descripcion, intereses, que_busca, redes_sociales FROM mobile_usuarios WHERE id = ?');
    $stmt->execute([$profileUserId]);
    $profile = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) json_response(['detail' => 'Usuario no encontrado'], 404);
    $profile['user_id'] = (int)$profile['user_id'];
    if (!empty($profile['edad'])) $profile['edad'] = (int)$profile['edad'];
    json_response(['success' => true, 'data' => $profile]);
    exit;
}
if ($path === '/users/social-profile' && $method === 'GET') {
    require __DIR__ . '/routes/social/get_profile.php'; exit;
}
if ($path === '/users/social-profile' && $method === 'PUT') {
    require __DIR__ . '/routes/social/update_profile.php'; exit;
}
if ($path === '/users/social-profile/photo' && $method === 'POST') {
    $db = db();
    if (!isset($_FILES['photo']) && !isset($_FILES['file'])) {
        json_response(['detail' => 'No se recibió ninguna imagen'], 400);
    }
    $file = isset($_FILES['photo']) ? $_FILES['photo'] : $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_response(['detail' => 'Error al subir archivo: ' . $file['error']], 400);
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($ext, $allowed)) {
        json_response(['detail' => 'Formato no permitido. Use: jpg, jpeg, png, webp'], 400);
    }
    // Guardar en el directorio 'photos' que ya existe en el servidor
    $uploadDir = __DIR__ . '/photos/';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $filename = md5($userId . time() . bin2hex(random_bytes(4))) . '.' . $ext;
    $destPath = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_response(['detail' => 'No se pudo guardar la imagen. Verifique permisos de la carpeta photos/'], 500);
    }
    $fotoUrl = '/api_asocial/backend_php/photos/' . $filename;
    try {
        $stmt = $db->prepare('UPDATE mobile_usuarios SET foto_url = ? WHERE id = ?');
        $stmt->execute([$fotoUrl, $userId]);
    } catch (Exception $e) {
        // Continuar de todas formas
    }
    json_response(['success' => true, 'data' => ['foto_url' => $fotoUrl]]);
    exit;
}

// ── Productos y Gifts ───────────────────────────────────────────────
if ($path === '/products' && $method === 'GET') {
    require __DIR__ . '/routes/products/list.php'; exit;
}
if ($path === '/gift-products' && $method === 'GET') {
    $db = db();
    try {
        try {
            $stmt = $db->query('SELECT id, nombre, descripcion, precio, icono, color, es_regalo, imagen, orden FROM social_gift_products ORDER BY orden ASC, nombre ASC');
        } catch (Exception $e) {
            $stmt = $db->query('SELECT id, nombre, descripcion, precio, icono, color, es_regalo, imagen, orden FROM social_gifts_products ORDER BY orden ASC, nombre ASC');
        }
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Si la tabla no existe, retornar array vacío
        json_response(['success' => true, 'data' => []]);
        exit;
    }
    $result = array_map(function ($p) {
        return [
            'id' => (int)$p['id'],
            'nombre' => $p['nombre'],
            'descripcion' => $p['descripcion'] ?? null,
            'precio' => (float)$p['precio'],
            'icono' => $p['icono'] ?? null,
            'color' => $p['color'] ?? '#B71C1C',
            'es_regalo' => (bool)($p['es_regalo'] ?? true),
            'imagen' => $p['imagen'] ?? null,
            'orden' => (int)($p['orden'] ?? 0),
        ];
    }, $products);
    json_response(['success' => true, 'data' => $result]);
    exit;
}

// ── Promotions ──────────────────────────────────────────────────────
if ($path === '/promotions' && $method === 'GET') {
    require __DIR__ . '/routes/promotions/list.php'; exit;
}

// ── Branches/Sucursales ─────────────────────────────────────────────
if ($path === '/branches' && $method === 'GET') {
    $db = db();
    $stmt = $db->query(
        'SELECT id, nombre, slug, descripcion, lat, lng, telefono, imagen_banner,
                horarios_json, mesas_habilitadas, reservas_habilitadas, activo
           FROM rest_restaurantes
          WHERE activo = 1
          ORDER BY nombre'
    );
    json_response(['success' => true, 'data' => ['branches' => $stmt->fetchAll(PDO::FETCH_ASSOC)]]);
    exit;
}

if (preg_match('#^/branches/(\d+)$#', $path, $m) && $method === 'GET') {
    $db = db();
    $branchId = (int)$m[1];
    $stmt = $db->prepare(
        'SELECT id, nombre, slug, descripcion, lat, lng, telefono, imagen_banner,
                horarios_json, mesas_habilitadas, reservas_habilitadas, activo
           FROM rest_restaurantes
          WHERE id = ? AND activo = 1
          LIMIT 1'
    );
    $stmt->execute([$branchId]);
    $branch = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$branch) {
        json_response(['detail' => 'Sucursal no encontrada.'], 404);
    }
    json_response(['success' => true, 'data' => ['branch' => $branch]]);
    exit;
}

// ── Favorites (inline) ──────────────────────────────────────────────
if ($path === '/favorites' && $method === 'GET') {
    $db = db();
    // Primero intenta con JOIN a platillos, si falla retorna solo los IDs
    try {
        $stmt = $db->prepare('SELECT f.*, f.platillo_id as id FROM favoritos f WHERE f.usuario_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Enriquecer con datos del platillo desde rest_platillos
        foreach ($rows as &$row) {
            try {
                $pstmt = $db->prepare('SELECT * FROM rest_platillos WHERE id = ? LIMIT 1');
                $pstmt->execute([$row['platillo_id']]);
                $platillo = $pstmt->fetch(PDO::FETCH_ASSOC);
                if ($platillo) $row = array_merge($row, $platillo);
            } catch (Exception $e) {
                // Tabla no encontrada, usar solo el ID
            }
        }
        json_response(['success' => true, 'data' => $rows]);
    } catch (Exception $e) {
        json_response(['success' => true, 'data' => []]);
    }
    exit;
}
if ($path === '/favorites/toggle' && $method === 'POST') {
    $db = db();
    $productId = (int)($body['product_id'] ?? 0);
    if ($productId <= 0) json_response(['success' => false, 'detail' => 'ID inválido'], 400);
    $stmt = $db->prepare('SELECT id FROM favoritos WHERE usuario_id = ? AND platillo_id = ?');
    $stmt->execute([$userId, $productId]);
    if ($stmt->fetch()) {
        $del = $db->prepare('DELETE FROM favoritos WHERE usuario_id = ? AND platillo_id = ?');
        $del->execute([$userId, $productId]);
        json_response(['success' => true, 'data' => ['favorito' => false]]);
    } else {
        $ins = $db->prepare('INSERT INTO favoritos (usuario_id, platillo_id, created_at) VALUES (?, ?, NOW())');
        $ins->execute([$userId, $productId]);
        json_response(['success' => true, 'data' => ['favorito' => true]], 201);
    }
    exit;
}

// ── Payments (inline) ───────────────────────────────────────────────
if ($path === '/payments/create-intent' && $method === 'POST') {
    $stripeKey = $_ENV['STRIPE_SECRET_KEY'] ?? '';
    if ($stripeKey && class_exists('Stripe\\Stripe')) {
        \Stripe\Stripe::setApiKey($stripeKey);
        $pi = \Stripe\PaymentIntent::create([
            'amount'   => (int)(($body['amount'] ?? 0) * 100),
            'currency' => $body['currency'] ?? 'mxn',
            'metadata' => ['user_id' => $userId, 'order_id' => $body['order_id'] ?? 0],
            'automatic_payment_methods' => ['enabled' => true],
        ]);
        json_response(['success' => true, 'data' => ['client_secret' => $pi->client_secret, 'payment_intent_id' => $pi->id]]);
    } else {
        json_response(['success' => true, 'data' => ['client_secret' => 'mock_secret_' . bin2hex(random_bytes(8)), 'payment_intent_id' => 'pi_mock_' . time()]]);
    }
    exit;
}

// ── Profile/Addresses (inline) ──────────────────────────────────────
if ($path === '/profile/addresses' && $method === 'GET') {
    $db = db();
    try {
        $stmt = $db->prepare('SELECT * FROM direcciones WHERE usuario_id = ?');
        $stmt->execute([$userId]);
        json_response(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } catch (Exception $e) {
        json_response(['success' => true, 'data' => []]);
    }
    exit;
}
if ($path === '/profile/addresses' && $method === 'POST') {
    $db = db();
    try {
        $stmt = $db->prepare('INSERT INTO direcciones (usuario_id, alias, calle, numero, colonia, ciudad, lat, lng, cp, es_principal, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        $stmt->execute([$userId, $body['alias'] ?? 'Direccion', $body['calle'] ?? '', $body['numero'] ?? '', $body['colonia'] ?? '', $body['ciudad'] ?? '', $body['lat'] ?? 0, $body['lng'] ?? 0, $body['cp'] ?? '', $body['es_principal'] ?? 0]);
        $newId = $db->lastInsertId();
        json_response(['success' => true, 'data' => ['id' => $newId]], 201);
    } catch (Exception $e) {
        json_response(['success' => true, 'data' => ['id' => null]]);
    }
    exit;
}

json_response(['detail' => 'Ruta no encontrada.'], 404);
