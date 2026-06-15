<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class AdminStorageController extends BaseController
{
    const MANAGED_DIRS = [
        'entregas'     => 'Fotos de entrega',
        'firmas'       => 'Firmas de repartidor',
        'comprobantes' => 'Fotos de comprobante',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    private function getDias(): array
    {
        $cfg = new ConfigModel();
        return [
            'evidencias' => max(1,  (int)$cfg->get('retencion_fotos_evidencias_dias', '90')),
            'pedidos'    => max(1,  (int)$cfg->get('retencion_fotos_pedidos_dias',    '90')),
            'logs'       => max(30, (int)$cfg->get('retencion_logs_dias',             '365')),
        ];
    }

    public function index(?string $p = null): void
    {
        $dias = $this->getDias();

        $diasPorDir = [
            'entregas'     => $dias['evidencias'],
            'firmas'       => $dias['evidencias'],
            'comprobantes' => $dias['pedidos'],
        ];

        $dirs         = [];
        $totalSize    = 0;
        $totalAPurgar = 0;

        foreach (self::MANAGED_DIRS as $slug => $label) {
            $d             = $diasPorDir[$slug];
            $info          = $this->scanDir(UPLOAD_PATH . $slug, $d);
            $info['slug']  = $slug;
            $info['label'] = $label;
            $info['dias']  = $d;
            $dirs[$slug]   = $info;
            $totalSize    += $info['total_size'];
            $totalAPurgar += $info['old_count'];
        }

        $db = Database::getInstance();
        $historial = $db->query(
            "SELECT al.accion, al.descripcion, al.created_at, u.nombre
               FROM action_logs al
          LEFT JOIN usuarios u ON u.id = al.usuario_id
              WHERE al.modulo = 'retencion'
           ORDER BY al.created_at DESC LIMIT 15"
        )->fetchAll();

        $empresas = $db->query(
            "SELECT id, razon_social AS nombre FROM empresas WHERE activo = 1 ORDER BY razon_social"
        )->fetchAll();

        $flash      = $this->getFlash();
        $pageTitle  = 'Políticas de retención';
        $activeMenu = 'almacenamiento';

        ob_start();
        require ROOT_PATH . '/app/views/panel/storage/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/panel/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) { $this->redirect('admin-storage/index'); }

        $diasEv   = max(1,  (int)$this->post('retencion_fotos_evidencias_dias', '90'));
        $diasPed  = max(1,  (int)$this->post('retencion_fotos_pedidos_dias',    '90'));
        $diasLogs = max(30, (int)$this->post('retencion_logs_dias',             '365'));

        $cfg = new ConfigModel();
        $cfg->set('retencion_fotos_evidencias_dias', (string)$diasEv);
        $cfg->set('retencion_fotos_pedidos_dias',    (string)$diasPed);
        $cfg->set('retencion_logs_dias',             (string)$diasLogs);

        $this->log('guardar_retencion', 'retencion',
            "evidencias={$diasEv}d, pedidos={$diasPed}d, logs={$diasLogs}d");

        $this->flash('success', 'Políticas de retención guardadas.');
        $this->redirect('admin-storage/index');
    }

    public function purgar(?string $p = null): void
    {
        if (!$this->isPost()) { $this->redirect('admin-storage/index'); }

        $conf = (string)$this->post('confirmacion', '');
        if ($conf !== 'PURGAR') {
            $this->flash('error', 'Escribe PURGAR para confirmar la purga.');
            $this->redirect('admin-storage/index');
        }

        $dias    = $this->getDias();
        $db      = Database::getInstance();
        $nFiles  = 0;
        $freed   = 0;
        $migrada = false;

        // -- Evidencias (firma_path + foto_path) --
        $cutoffEv = date('Y-m-d H:i:s', time() - $dias['evidencias'] * 86400);
        $hasColEv = $db->query("SHOW COLUMNS FROM evidencias_entrega LIKE 'imagenes_purgadas_at'")->fetch();

        if ($hasColEv) {
            $migrada = true;
            $stmt = $db->prepare(
                "SELECT id, firma_path, foto_path FROM evidencias_entrega
                  WHERE entregado_at < ? AND imagenes_purgadas_at IS NULL
                    AND (firma_path IS NOT NULL OR foto_path IS NOT NULL)"
            );
            $stmt->execute([$cutoffEv]);
            foreach ($stmt->fetchAll() as $ev) {
                foreach (['firma_path', 'foto_path'] as $col) {
                    if (!empty($ev[$col])) {
                        $abs = $this->urlToPath($ev[$col]);
                        if ($abs && is_file($abs)) {
                            $freed += filesize($abs);
                            @unlink($abs);
                            $nFiles++;
                        }
                    }
                }
                $db->prepare(
                    "UPDATE evidencias_entrega SET firma_path=NULL, foto_path=NULL, imagenes_purgadas_at=NOW() WHERE id=?"
                )->execute([$ev['id']]);
            }
        }

        // -- Pedidos (foto_comprobante_path + foto_entrega_path) --
        $cutoffPed = date('Y-m-d H:i:s', time() - $dias['pedidos'] * 86400);
        $hasColPed = $db->query("SHOW COLUMNS FROM pedidos LIKE 'imagenes_purgadas_at'")->fetch();

        if ($hasColPed) {
            $migrada = true;
            $stmt = $db->prepare(
                "SELECT id, foto_comprobante_path, foto_entrega_path FROM pedidos
                  WHERE created_at < ? AND imagenes_purgadas_at IS NULL
                    AND (foto_comprobante_path IS NOT NULL OR foto_entrega_path IS NOT NULL)"
            );
            $stmt->execute([$cutoffPed]);
            foreach ($stmt->fetchAll() as $ped) {
                foreach (['foto_comprobante_path', 'foto_entrega_path'] as $col) {
                    if (!empty($ped[$col])) {
                        $abs = $this->urlToPath($ped[$col]);
                        if ($abs && is_file($abs)) {
                            $freed += filesize($abs);
                            @unlink($abs);
                            $nFiles++;
                        }
                    }
                }
                $db->prepare(
                    "UPDATE pedidos SET foto_comprobante_path=NULL, foto_entrega_path=NULL, imagenes_purgadas_at=NOW() WHERE id=?"
                )->execute([$ped['id']]);
            }
        }

        if (!$migrada) {
            $this->flash('error', 'Ejecuta la migración 019_retencion_politicas.sql en la BD antes de usar la purga inteligente.');
            $this->redirect('admin-storage/index');
        }

        $this->log('purgar_imagenes', 'retencion',
            "Purgados: {$nFiles} archivos, liberado: " . $this->formatSize($freed));

        $this->flash('success',
            "{$nFiles} imagen(es) eliminada(s) del disco — " . $this->formatSize($freed) . " liberados. Historial de pedidos y evidencias conservado.");
        $this->redirect('admin-storage/index');
    }

    public function exportarCsv(?string $p = null): void
    {
        if (!$this->isPost()) { $this->redirect('admin-storage/index'); }

        $empresa_id = (int)$this->post('empresa_id', 0);
        $desde      = (string)$this->post('fecha_desde', date('Y-01-01'));
        $hasta      = (string)$this->post('fecha_hasta', date('Y-m-d'));

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) {
            $this->flash('error', 'Fechas inválidas.');
            $this->redirect('admin-storage/index');
        }

        $db     = Database::getInstance();
        $params = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];
        $extra  = '';
        if ($empresa_id > 0) { $extra = ' AND p.empresa_id = ?'; $params[] = $empresa_id; }

        $stmt = $db->prepare(
            "SELECT p.folio, e.razon_social AS empresa, u.nombre AS comprador,
                    p.estado, p.direccion_entrega,
                    (SELECT SUM(pd.subtotal) FROM pedido_detalle pd WHERE pd.pedido_id = p.id) AS total,
                    p.created_at, p.ruta_iniciada_at, p.ruta_finalizada_at
               FROM pedidos p
               JOIN empresas e ON e.id = p.empresa_id
               JOIN usuarios u ON u.id = p.comprador_id
              WHERE p.created_at BETWEEN ? AND ?{$extra}
           ORDER BY p.created_at DESC"
        );
        $stmt->execute($params);
        $pedidos = $stmt->fetchAll();

        $filename = 'carnihub_pedidos_' . $desde . '_' . $hasta . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');

        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
        fputcsv($out, ['Folio', 'Empresa', 'Comprador', 'Estado', 'Total MXN', 'Dirección entrega', 'Creado', 'Ruta iniciada', 'Ruta finalizada']);
        foreach ($pedidos as $row) {
            fputcsv($out, [
                $row['folio'],
                $row['empresa'],
                $row['comprador'],
                $row['estado'],
                number_format((float)($row['total'] ?? 0), 2, '.', ''),
                $row['direccion_entrega'] ?? '',
                $row['created_at'],
                $row['ruta_iniciada_at'] ?? '',
                $row['ruta_finalizada_at'] ?? '',
            ]);
        }
        fclose($out);
        exit;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function urlToPath(string $url): ?string
    {
        $rel = ltrim(str_replace(UPLOAD_URL, '', $url), '/');
        return UPLOAD_PATH . $rel;
    }

    private function scanDir(string $path, int $retentionDays): array
    {
        $cutoff    = time() - $retentionDays * 86400;
        $totalSize = 0;
        $count     = 0;
        $oldCount  = 0;
        $oldestTs  = PHP_INT_MAX;
        $newestTs  = 0;
        $oldest10  = [];

        if (!is_dir($path)) {
            return ['total_size' => 0, 'count' => 0, 'old_count' => 0,
                    'oldest' => null, 'newest' => null, 'oldest10' => [], 'label_size' => '0 B'];
        }

        foreach (glob($path . '/*') ?: [] as $file) {
            if (!is_file($file)) continue;
            $mtime = filemtime($file);
            $size  = filesize($file);
            $count++;
            $totalSize += $size;
            if ($mtime < $cutoff) $oldCount++;
            if ($mtime < $oldestTs) $oldestTs = $mtime;
            if ($mtime > $newestTs) $newestTs = $mtime;
            $oldest10[] = ['name' => basename($file), 'size' => $size, 'mtime' => $mtime];
        }

        usort($oldest10, fn($a, $b) => $a['mtime'] - $b['mtime']);
        $oldest10 = array_slice($oldest10, 0, 10);

        return [
            'total_size' => $totalSize,
            'label_size' => $this->formatSize($totalSize),
            'count'      => $count,
            'old_count'  => $oldCount,
            'oldest'     => $oldestTs !== PHP_INT_MAX ? date('Y-m-d', $oldestTs) : null,
            'newest'     => $newestTs > 0             ? date('Y-m-d', $newestTs) : null,
            'oldest10'   => $oldest10,
        ];
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
        if ($bytes >= 1048576)    return round($bytes / 1048576, 1)    . ' MB';
        if ($bytes >= 1024)       return round($bytes / 1024, 1)       . ' KB';
        return $bytes . ' B';
    }
}
