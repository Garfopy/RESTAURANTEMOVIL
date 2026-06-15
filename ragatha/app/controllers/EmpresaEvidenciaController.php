<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaEvidenciaController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->requireEmpresa();
    }

    public function index(?string $p = null): void
    {
        $db        = Database::getInstance();
        $empresaId = (int)$this->empresaId();
        $esComp    = $this->rolActual() === 'comprador';
        $userId    = (int)$this->usuarioId();

        $hoy   = date('Y-m-d');
        $desde = (string)$this->get('fecha_desde', date('Y-m-d', strtotime('-90 days')));
        $hasta = (string)$this->get('fecha_hasta', $hoy);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-d', strtotime('-90 days'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = $hoy;
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];

        $sql = "SELECT p.id, p.folio, p.estado, p.created_at,
                       (p.ruta_polyline IS NOT NULL AND p.ruta_polyline != '') AS has_ruta,
                       (p.foto_entrega_path IS NOT NULL) AS has_foto_directa,
                       COUNT(DISTINCT ee.id) AS evidencias_ruta,
                       COUNT(DISTINCT IF(ps.foto_entrega_path IS NOT NULL, ps.id, NULL)) AS fotos_sucursales
                  FROM pedidos p
             LEFT JOIN ruta_detalle rd ON rd.pedido_id = p.id
             LEFT JOIN evidencias_entrega ee ON ee.ruta_detalle_id = rd.id
             LEFT JOIN pedido_sucursal ps ON ps.pedido_id = p.id
                 WHERE p.empresa_id = ?
                   AND p.estado = 'entregado'
                   AND DATE(p.created_at) BETWEEN ? AND ?
                   AND (p.foto_entrega_path IS NOT NULL OR ee.id IS NOT NULL
                        OR ps.foto_entrega_path IS NOT NULL OR p.ruta_polyline IS NOT NULL)";
        $params = [$empresaId, $desde, $hasta];

        if ($esComp) {
            $sql .= " AND p.comprador_id = ?";
            $params[] = $userId;
        }
        $sql .= " GROUP BY p.id ORDER BY p.created_at DESC LIMIT 60";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll();

        // Contar pedidos con archivos próximos a expirar
        $cutoff45 = time() - 45 * 86400;
        $proximos = 0;
        foreach ($pedidos as $ped) {
            if (strtotime($ped['created_at']) < $cutoff45) $proximos++;
        }

        $flash       = $this->getFlash();
        $esComprador = $esComp;
        $pageTitle  = $esComp ? 'Mis evidencias de entrega' : 'Evidencias de entrega';
        $activeMenu = 'evidencias';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/evidencia/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function exportar(?string $p = null): void
    {
        if (!$this->isPost()) { $this->redirect('empresa-evidencia/index'); }

        $pedidoId  = (int)$this->post('pedido_id');
        $empresaId = (int)$this->empresaId();
        $esComp    = $this->rolActual() === 'comprador';
        $userId    = (int)$this->usuarioId();

        if (!$pedidoId) {
            $this->flash('error', 'Pedido inválido.');
            $this->redirect('empresa-evidencia/index');
        }

        $db = Database::getInstance();

        // Verificar propiedad
        $check = "SELECT id FROM pedidos WHERE id = ? AND empresa_id = ?";
        $checkParams = [$pedidoId, $empresaId];
        if ($esComp) { $check .= " AND comprador_id = ?"; $checkParams[] = $userId; }
        $row = $db->prepare($check);
        $row->execute($checkParams);
        if (!$row->fetchColumn()) {
            $this->flash('error', 'No tienes acceso a este pedido.');
            $this->redirect('empresa-evidencia/index');
        }

        // Cargar datos del pedido
        $pedidoModel = new PedidoModel();
        $pedido      = $pedidoModel->conDetalle($pedidoId);
        if (!$pedido) {
            $this->flash('error', 'Pedido no encontrado.');
            $this->redirect('empresa-evidencia/index');
        }
        $empresa     = (new EmpresaModel())->find($empresaId);
        $configModel = new ConfigModel();
        $appLogo     = $configModel->get('app_logo', '');
        $colorPrimary = $configModel->get('color_primary', '#C8102E');

        // Evidencias de ruta (formal)
        $stmtEv = $db->prepare(
            "SELECT ee.id, ee.nombre_receptor, ee.firma_path, ee.foto_path, ee.entregado_at,
                    s.nombre AS sucursal_nombre, rd.hora_entrega
               FROM evidencias_entrega ee
               JOIN ruta_detalle rd ON rd.id = ee.ruta_detalle_id
          LEFT JOIN sucursales s ON s.id = rd.sucursal_id
              WHERE rd.pedido_id = ?"
        );
        $stmtEv->execute([$pedidoId]);
        $evidenciasRuta = $stmtEv->fetchAll();

        // Fotos y firmas de entrega directa (pedido_sucursal)
        $stmtPs = $db->prepare(
            "SELECT ps.foto_entrega_path, ps.firma_path, ps.fecha_llegada, s.nombre AS sucursal_nombre
               FROM pedido_sucursal ps
               JOIN sucursales s ON s.id = ps.sucursal_id
              WHERE ps.pedido_id = ? AND ps.foto_entrega_path IS NOT NULL"
        );
        $stmtPs->execute([$pedidoId]);
        $fotosDirectas = $stmtPs->fetchAll();

        // Construir ZIP
        $zipPath = sys_get_temp_dir() . '/carnihub_ev_' . $pedido['folio'] . '_' . time() . '.zip';
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->flash('error', 'Error al generar el archivo ZIP.');
            $this->redirect('empresa-evidencia/index');
        }

        $fotosAgregadas  = [];
        $firmasAgregadas = [];

        // Agregar fotos/firmas de ruta
        foreach ($evidenciasRuta as $ev) {
            foreach (['foto_path' => 'fotos', 'firma_path' => 'firmas'] as $field => $dir) {
                if (empty($ev[$field])) continue;
                $fsPath = $this->urlToFsPath($ev[$field]);
                if ($fsPath && file_exists($fsPath)) {
                    $nombre = basename($fsPath);
                    $zip->addFile($fsPath, $dir . '/' . $nombre);
                    if ($dir === 'fotos') $fotosAgregadas[$ev['id']] = 'fotos/' . $nombre;
                    else                  $firmasAgregadas[$ev['id']] = 'firmas/' . $nombre;
                }
            }
        }

        // Foto directa del pedido principal
        if (!empty($pedido['foto_entrega_path'])) {
            $fsPath = $this->urlToFsPath($pedido['foto_entrega_path']);
            if ($fsPath && file_exists($fsPath)) {
                $nombre = basename($fsPath);
                $zip->addFile($fsPath, 'fotos/' . $nombre);
                $fotosAgregadas['directo'] = 'fotos/' . $nombre;
            }
        }

        // Fotos y firmas directas por sucursal
        foreach ($fotosDirectas as $fd) {
            $fsPath = $this->urlToFsPath($fd['foto_entrega_path']);
            if ($fsPath && file_exists($fsPath)) {
                $nombre = basename($fsPath);
                $zip->addFile($fsPath, 'fotos/' . $nombre);
            }
            if (!empty($fd['firma_path'])) {
                $fsPath = $this->urlToFsPath($fd['firma_path']);
                if ($fsPath && file_exists($fsPath)) {
                    $nombre = basename($fsPath);
                    $zip->addFile($fsPath, 'firmas/' . $nombre);
                }
            }
        }

        // Generar el reporte HTML
        $html = $this->buildReporteHtml(
            $pedido, $empresa, $evidenciasRuta, $fotosDirectas,
            $appLogo, $colorPrimary
        );
        $zip->addFromString('reporte_' . $pedido['folio'] . '.html', $html);
        $zip->close();

        $this->log('exportar_evidencia', 'evidencias', "Pedido #{$pedidoId} folio={$pedido['folio']}");

        $filename = 'carnihub_evidencia_' . $pedido['folio'] . '.zip';
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($zipPath));
        header('Pragma: no-cache');
        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    // ── Helpers privados ─────────────────────────────────────────────

    private function urlToFsPath(string $url): ?string
    {
        if (empty($url)) return null;

        // 1. Conversión directa UPLOAD_URL → UPLOAD_PATH
        $converted = str_replace(UPLOAD_URL, UPLOAD_PATH, $url);
        if ($converted !== $url && file_exists($converted)) return $converted;

        // 2. Extraer la parte del path y buscar /uploads/
        $urlPath = parse_url($url, PHP_URL_PATH) ?? '';
        if ($urlPath) {
            $uploadsPos = strpos($urlPath, '/uploads/');
            if ($uploadsPos !== false) {
                $relative = substr($urlPath, $uploadsPos + 1); // "uploads/firmas/archivo.png"
                $fsPath   = ROOT_PATH . '/public/' . $relative;
                if (file_exists($fsPath)) return $fsPath;
            }
            // 2b. Fallback con DOCUMENT_ROOT
            if (!empty($_SERVER['DOCUMENT_ROOT'])) {
                $docPath = rtrim($_SERVER['DOCUMENT_ROOT'], '/') . $urlPath;
                if (file_exists($docPath)) return $docPath;
            }
        }

        // 3. Fallback por nombre de directorio conocido
        foreach (['entregas', 'firmas', 'evidencias', 'comprobantes'] as $dir) {
            if (str_contains($url, "/$dir/")) {
                $filename = basename(parse_url($url, PHP_URL_PATH));
                $path     = UPLOAD_PATH . $dir . '/' . $filename;
                if (file_exists($path)) return $path;
            }
        }

        return null;
    }

    private function fileToDataUri(?string $fsPath): string
    {
        if (empty($fsPath) || !file_exists($fsPath)) return '';
        $ext  = strtolower(pathinfo($fsPath, PATHINFO_EXTENSION));
        $mime = match($ext) { 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg' };
        return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($fsPath));
    }

    private function buildReporteHtml(
        array $pedido,
        ?array $empresa,
        array $evidenciasRuta,
        array $fotosDirectas,
        string $appLogo,
        string $colorPrimary
    ): string {
        $folio   = htmlspecialchars($pedido['folio']);
        $color   = htmlspecialchars($colorPrimary ?: '#C8102E');
        $empresa = $empresa ?? [];

        $estadoLabels = [
            'pendiente'=>'Pendiente','confirmado'=>'Confirmado',
            'en_preparacion'=>'En preparación','en_ruta'=>'En ruta',
            'entregado'=>'Entregado','cancelado'=>'Cancelado',
        ];
        $estadoLabel = $estadoLabels[$pedido['estado']] ?? ucfirst($pedido['estado']);

        $metodoPago = ['transferencia'=>'Transferencia bancaria','tarjeta'=>'Tarjeta','credito'=>'Crédito'][$pedido['metodo_pago'] ?? ''] ?? ucfirst($pedido['metodo_pago'] ?? '—');
        $tipoEntrega = match ($pedido['tipo_entrega'] ?? '') {
            'pickup'     => 'Recoger en bodega',
            'repartidor' => 'Envío a domicilio',
            default      => '—',
        };

        $logoHtml = $appLogo
            ? '<img src="' . htmlspecialchars($appLogo) . '" alt="Logo" style="height:52px;max-width:180px;object-fit:contain">'
            : '<span style="font-size:1.4rem;font-weight:900;color:' . $color . '">CarniHub</span>';

        // Items table rows
        $itemRows = '';
        foreach ($pedido['items'] ?? [] as $item) {
            $itemRows .= '<tr>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #F3F4F6">'
                . htmlspecialchars($item['producto_nombre'])
                . '<div style="font-size:11px;color:#9CA3AF">' . htmlspecialchars($item['presentacion'] ?? '') . '</div></td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #F3F4F6;text-align:center">' . number_format((float)$item['cantidad'], 2) . '</td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #F3F4F6;text-align:right">$' . number_format((float)$item['precio_unit'], 2) . '</td>'
                . '<td style="padding:8px 10px;border-bottom:1px solid #F3F4F6;text-align:right;font-weight:700">$' . number_format((float)$item['subtotal'], 2) . '</td>'
                . '</tr>';
        }

        $subtotalProd = array_sum(array_column($pedido['items'] ?? [], 'subtotal'));
        $costoEnvio   = (float)($pedido['costo_envio'] ?? 0);
        $total        = (float)($pedido['total'] ?? ($subtotalProd + $costoEnvio));
        $labelSubtotal = '$' . number_format($subtotalProd, 2);
        $labelEnvio    = '$' . number_format($costoEnvio, 2);
        $labelTotal    = '$' . number_format($total, 2);

        // Evidencias section
        $evSection = '';
        if (!empty($evidenciasRuta) || !empty($fotosDirectas) || !empty($pedido['foto_entrega_path'])) {
            $evSection .= '<div style="margin-top:28px;padding-top:20px;border-top:2px solid ' . $color . '">';
            $evSection .= '<h2 style="font-size:14px;font-weight:800;color:' . $color . ';text-transform:uppercase;letter-spacing:.06em;margin-bottom:16px">Evidencias de entrega</h2>';

            foreach ($evidenciasRuta as $ev) {
                $evSection .= '<div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px;margin-bottom:14px">';
                $evSection .= '<div style="font-weight:700;font-size:12px;margin-bottom:6px;color:#374151">';
                $evSection .= htmlspecialchars($ev['sucursal_nombre'] ?? 'Sucursal');
                if (!empty($ev['hora_entrega'])) $evSection .= ' — ' . date('d/m/Y H:i', strtotime($ev['hora_entrega']));
                $evSection .= '</div>';
                if (!empty($ev['nombre_receptor'])) {
                    $evSection .= '<div style="font-size:11px;color:#6B7280;margin-bottom:10px">Receptor: ' . htmlspecialchars($ev['nombre_receptor']) . '</div>';
                }
                if (!empty($ev['foto_path'])) {
                    $fotoUri = $this->fileToDataUri($this->urlToFsPath($ev['foto_path']));
                    if ($fotoUri) {
                        $evSection .= '<img src="' . $fotoUri . '" style="max-width:100%;border-radius:8px;margin-bottom:10px;display:block">';
                    }
                }
                if (!empty($ev['firma_path'])) {
                    $firmaUri = $this->fileToDataUri($this->urlToFsPath($ev['firma_path']));
                    if ($firmaUri) {
                        $evSection .= '<div style="font-size:11px;color:#6B7280;margin-bottom:4px">Firma del receptor:</div>';
                        $evSection .= '<img src="' . $firmaUri . '" style="max-height:100px;border:1px solid #E5E7EB;border-radius:6px;background:#F9FAFB;padding:6px">';
                    }
                }
                $evSection .= '</div>';
            }

            foreach ($fotosDirectas as $fd) {
                $fdUri = $this->fileToDataUri($this->urlToFsPath($fd['foto_entrega_path']));
                if (!$fdUri) continue;
                $evSection .= '<div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px;margin-bottom:14px">';
                $evSection .= '<div style="font-weight:700;font-size:12px;margin-bottom:8px;color:#374151">';
                $evSection .= htmlspecialchars($fd['sucursal_nombre'] ?? 'Entrega');
                if (!empty($fd['fecha_llegada'])) $evSection .= ' — ' . date('d/m/Y H:i', strtotime($fd['fecha_llegada']));
                $evSection .= '</div>';
                $evSection .= '<img src="' . $fdUri . '" style="max-width:100%;border-radius:8px;display:block">';
                $evSection .= '</div>';
            }

            // Foto directa del pedido principal
            if (!empty($pedido['foto_entrega_path'])) {
                $pedFotoUri = $this->fileToDataUri($this->urlToFsPath($pedido['foto_entrega_path']));
                if ($pedFotoUri) {
                    $evSection .= '<div style="border:1px solid #E5E7EB;border-radius:10px;padding:14px;margin-bottom:14px">';
                    $evSection .= '<div style="font-weight:700;font-size:12px;margin-bottom:8px;color:#374151">Foto de entrega</div>';
                    $evSection .= '<img src="' . $pedFotoUri . '" style="max-width:100%;border-radius:8px;display:block">';
                    $evSection .= '</div>';
                }
            }

            $evSection .= '</div>';
        }

        // Mapa de ruta (Leaflet)
        $mapaSection = '';
        if (!empty($pedido['ruta_polyline'])) {
            $coords = $pedido['ruta_polyline'];
            $mapaSection = <<<HTML
<div style="margin-top:28px;padding-top:20px;border-top:2px solid {$color}">
  <h2 style="font-size:14px;font-weight:800;color:{$color};text-transform:uppercase;letter-spacing:.06em;margin-bottom:12px">Mapa de la ruta de entrega</h2>
  <p style="font-size:11px;color:#6B7280;margin-bottom:10px">El mapa requiere conexión a internet para cargar (OpenStreetMap). Si se abre sin conexión, solo se mostrará el contenido vacío.</p>
  <div id="mapa-ruta" style="height:420px;border-radius:12px;border:1px solid #E5E7EB;overflow:hidden"></div>
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script>
  (function() {
    var coords = {$coords};
    if (!coords || !coords.length) return;
    var map = L.map('mapa-ruta').setView(coords[0], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://openstreetmap.org">OpenStreetMap</a>'
    }).addTo(map);
    L.polyline(coords, {color:'{$color}',weight:4,opacity:.85}).addTo(map);
    L.marker(coords[0]).addTo(map).bindPopup('<b>Inicio de ruta</b>').openPopup();
    L.marker(coords[coords.length-1]).addTo(map).bindPopup('<b>Fin de ruta</b>');
    var group = L.featureGroup([L.polyline(coords)]);
    map.fitBounds(group.getBounds().pad(0.1));
  })();
  </script>
</div>
HTML;
        }

        $now = date('d/m/Y H:i');
        $razonSocial = htmlspecialchars($empresa['razon_social'] ?? 'CarniHub');
        $rfc = htmlspecialchars($empresa['rfc'] ?? '');
        $dirFiscal = htmlspecialchars($empresa['direccion_fiscal'] ?? '');
        $tel = htmlspecialchars($empresa['telefono'] ?? '');
        $correo = htmlspecialchars($empresa['email'] ?? '');
        $compradorNombre = htmlspecialchars(($pedido['comprador_nombre'] ?? '') . ' ' . ($pedido['comprador_apellido'] ?? ''));
        $aprobadorNombre = htmlspecialchars($pedido['aprobador_nombre'] ?? '—');
        $fechaCreacion = date('d/m/Y', strtotime($pedido['created_at']));
        $fechaEntrega = !empty($pedido['fecha_entrega']) ? date('d/m/Y', strtotime($pedido['fecha_entrega'])) : '—';
        $notas       = htmlspecialchars($pedido['notas'] ?? '');
        $dirEntrega  = htmlspecialchars($pedido['direccion_entrega'] ?? '');

        // Pre-compute condicionales (heredoc no permite ternaries)
        $rfcBr       = $rfc       ? $rfc . '<br>'       : '';
        $dirFiscalBr = $dirFiscal ? $dirFiscal . '<br>' : '';
        $telBr       = $tel       ? $tel . '<br>'       : '';
        $notasHtml   = $notas ? '<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px"><span style="font-weight:700;color:#92400E">Notas del pedido:</span><div style="color:#374151;margin-top:3px">' . $notas . '</div></div>' : '';
        $dirHtml     = $dirEntrega ? '<div style="border:1px solid #E5E7EB;border-radius:8px;padding:10px 14px;margin-bottom:12px;font-size:12px"><div style="font-size:10px;font-weight:700;color:#6B7280;text-transform:uppercase;margin-bottom:3px">Dirección de entrega</div>' . $dirEntrega . '</div>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Evidencia — {$folio}</title>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',Arial,sans-serif;font-size:13px;color:#1f2937;background:#f3f4f6;line-height:1.5}
.page{background:#fff;width:210mm;min-height:297mm;margin:20px auto;padding:16mm;box-shadow:0 4px 24px rgba(0,0,0,.12)}
.doc-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:18px;padding-bottom:14px;border-bottom:2px solid {$color}}
.empresa-info{text-align:right;font-size:11px;color:#374151}
.doc-title{font-size:18px;font-weight:800;color:{$color};text-transform:uppercase;letter-spacing:.04em}
.folio{font-size:18px;font-weight:800;color:#111827}
.estado-badge{display:inline-block;padding:3px 12px;border-radius:999px;font-size:11px;font-weight:700;background:#D1FAE5;color:#065F46}
.meta-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px 16px;background:#F9FAFB;border:1px solid #E5E7EB;border-radius:8px;padding:12px 14px;margin:14px 0}
.meta-item{font-size:11px;color:#6B7280;text-transform:uppercase;letter-spacing:.04em;margin-bottom:2px}
.meta-val{font-size:13px;font-weight:700;color:#111827}
table.items{width:100%;border-collapse:collapse;margin-top:14px}
table.items thead tr{background:{$color};color:#fff}
table.items th{padding:8px 10px;font-size:11px;font-weight:700;text-align:left}
table.items th:nth-child(n+2){text-align:center}
table.items th:last-child{text-align:right}
.total-box{margin-top:10px;display:flex;flex-direction:column;align-items:flex-end;gap:4px}
.total-row{display:flex;gap:60px;font-size:12px;color:#374151}
.total-final{display:flex;gap:60px;background:{$color};color:#fff;font-weight:800;padding:8px 14px;border-radius:6px;font-size:14px;margin-top:4px}
.footer{margin-top:24px;padding-top:12px;border-top:1px solid #E5E7EB;display:flex;justify-content:space-between;font-size:10px;color:#9CA3AF}
@media print{body{background:#fff}.page{box-shadow:none;margin:0;padding:14mm}.no-print{display:none}}
</style>
</head>
<body>
<div class="page">
  <div class="doc-header">
    <div class="logo-area">{$logoHtml}</div>
    <div class="empresa-info">
      <strong>{$razonSocial}</strong><br>
      {$rfcBr}
      {$dirFiscalBr}
      {$telBr}
      {$correo}
    </div>
  </div>

  <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
    <div><div class="doc-title">Detalle de pedido</div><div style="margin-top:4px;font-size:11px;color:#6B7280">Incluye evidencias de entrega</div></div>
    <div style="text-align:right"><div class="folio">{$folio}</div><div class="estado-badge" style="margin-top:4px">{$estadoLabel}</div></div>
  </div>

  <div class="meta-grid">
    <div><div class="meta-item">Fecha del pedido</div><div class="meta-val">{$fechaCreacion}</div></div>
    <div><div class="meta-item">Comprador</div><div class="meta-val">{$compradorNombre}</div></div>
    <div><div class="meta-item">Fecha de entrega</div><div class="meta-val">{$fechaEntrega}</div></div>
    <div><div class="meta-item">Método de pago</div><div class="meta-val">{$metodoPago}</div></div>
    <div><div class="meta-item">Tipo de entrega</div><div class="meta-val">{$tipoEntrega}</div></div>
    <div><div class="meta-item">Aprobado por</div><div class="meta-val">{$aprobadorNombre}</div></div>
  </div>

  {$notasHtml}
  {$dirHtml}

  <table class="items">
    <thead><tr>
      <th>Producto</th>
      <th style="text-align:center">Cantidad</th>
      <th style="text-align:right">Precio Unit.</th>
      <th style="text-align:right">Subtotal</th>
    </tr></thead>
    <tbody>{$itemRows}</tbody>
  </table>

  <div class="total-box">
    <div class="total-row"><span>Subtotal productos</span><span>{$labelSubtotal}</span></div>
    <div class="total-row"><span>Costo de envío</span><span>{$labelEnvio}</span></div>
    <div class="total-final"><span>TOTAL</span><span>{$labelTotal}</span></div>
  </div>

  {$evSection}
  {$mapaSection}

  <div class="footer">
    <span>Generado el {$now} hrs — CarniHub</span>
    <span>Documento de evidencia de entrega — Folio {$folio}</span>
  </div>
</div>

<div class="no-print" style="text-align:center;padding:18px 0 28px">
  <button id="btn-pdf" onclick="generarPDF()"
          style="padding:11px 30px;background:{$color};color:#fff;border:none;border-radius:8px;font-weight:700;font-size:.9rem;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.18)">
    ↓ Guardar como PDF
  </button>
  <div id="pdf-status" style="margin-top:8px;font-size:.78rem;color:#6B7280;display:none"></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
async function generarPDF() {
  var btn = document.getElementById('btn-pdf');
  var status = document.getElementById('pdf-status');
  btn.textContent = 'Generando PDF...';
  btn.disabled = true;
  status.style.display = 'block';
  status.textContent = 'Capturando contenido...';
  try {
    var pdf  = new window.jspdf.jsPDF({orientation:'portrait',unit:'mm',format:'a4'});
    var page = document.querySelector('.page');
    status.textContent = 'Procesando imágenes...';
    var canvas = await html2canvas(page, {
      scale: 2, useCORS: true, allowTaint: false,
      logging: false, backgroundColor: '#ffffff'
    });
    var imgData = canvas.toDataURL('image/jpeg', 0.92);
    var pageW = pdf.internal.pageSize.getWidth();
    var pageH = pdf.internal.pageSize.getHeight();
    var imgH  = (canvas.height * pageW) / canvas.width;
    pdf.addImage(imgData, 'JPEG', 0, 0, pageW, imgH);
    var remaining = imgH - pageH;
    var page2 = 1;
    while (remaining > 1) {
      pdf.addPage();
      pdf.addImage(imgData, 'JPEG', 0, -(page2 * pageH), pageW, imgH);
      remaining -= pageH;
      page2++;
    }
    pdf.save('evidencia_{$folio}.pdf');
    status.textContent = 'PDF guardado correctamente.';
  } catch(err) {
    status.textContent = 'Error: ' + err.message;
  }
  btn.textContent = '↓ Guardar como PDF';
  btn.disabled = false;
}
</script>
</body>
</html>
HTML;
    }
}
