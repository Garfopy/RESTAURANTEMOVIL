<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

/**
 * FavoritoController — Productos favoritos del comprador.
 *
 * Acciones:
 *   GET  /favorito/index   → vista con lista de favoritos
 *   POST /favorito/toggle  → AJAX: agrega o quita producto (JSON)
 */
class FavoritoController extends BaseController
{
    private FavoritoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireRole(['comprador']);
        $this->model = new FavoritoModel();
    }

    public function index(?string $p = null): void
    {
        $usuario   = $_SESSION['usuario'] ?? [];
        $usuarioId = (int)($usuario['id'] ?? 0);
        $empresaId = (int)($usuario['empresa_id'] ?? 0);

        $productos = $this->model->listarPorComprador($usuarioId, $empresaId);

        $flash      = $this->getFlash();
        $pageTitle  = 'Mis favoritos';
        $activeMenu = 'favoritos';

        ob_start();
        require ROOT_PATH . '/app/views/comprador/favoritos/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    /**
     * Toggle vía AJAX. Requiere POST con producto_id.
     * Devuelve JSON: { ok, favorito (bool), total }
     */
    public function toggle(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->json(['ok' => false, 'error' => 'método no permitido'], 405);
        }

        $usuarioId  = (int)($_SESSION['usuario']['id'] ?? 0);
        $productoId = (int)$this->post('producto_id', 0);

        if ($usuarioId <= 0 || $productoId <= 0) {
            $this->json(['ok' => false, 'error' => 'parámetros inválidos'], 400);
        }

        if ($this->model->esFavorito($usuarioId, $productoId)) {
            $this->model->quitar($usuarioId, $productoId);
            $favorito = false;
        } else {
            $this->model->agregar($usuarioId, $productoId);
            $favorito = true;
        }

        $this->json([
            'ok'       => true,
            'favorito' => $favorito,
            'total'    => $this->model->contarPorUsuario($usuarioId),
        ]);
    }
}
