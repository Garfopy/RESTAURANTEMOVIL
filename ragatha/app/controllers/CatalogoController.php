<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

/**
 * CatalogoController — Ver catálogo de productos con precios escalonados.
 * Accesible por: admin_empresa, supervisor, comprador.
 */
class CatalogoController extends BaseController
{
    private ProductoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->requireEmpresa();
        $this->model = new ProductoModel();
    }

    public function index(?string $p = null): void
    {
        $filtros = [
            'empresa_id'   => $this->empresaId(),
            'buscar'       => $this->get('buscar', ''),
            'categoria_id' => (int)$this->get('categoria_id', 0) ?: null,
        ];
        $page = max(1, (int)$this->get('page', 1));

        $resultado   = $this->model->listadoConPrecio($filtros, $page);
        $productos   = $resultado['data'];
        $paginacion  = $resultado;

        $db = Database::getInstance();
        $categorias = $db->query('SELECT * FROM categorias WHERE activo = 1 ORDER BY nombre')->fetchAll();

        // Cargar límites activos de esta empresa indexados por producto_id
        $stmtLim = $db->prepare(
            'SELECT producto_id, limite_kg, limite_monto, periodo FROM limites_compra WHERE empresa_id=? AND activo=1 AND producto_id IS NOT NULL'
        );
        $stmtLim->execute([$this->empresaId()]);
        $limitePorProducto = [];
        foreach ($stmtLim->fetchAll() as $lim) {
            $limitePorProducto[(int)$lim['producto_id']] = $lim;
        }

        // Favoritos del usuario actual (solo aplica para comprador)
        $favoritosIds = [];
        if ($this->rolActual() === 'comprador') {
            $favModel = new FavoritoModel();
            $favoritosIds = $favModel->idsFavoritos($this->usuarioId() ?? 0);
        }
        $favoritosSet = array_flip($favoritosIds);

        // Precios especiales del comprador actual (indexados por producto_id)
        $preciosEspeciales = [];
        if ($this->rolActual() === 'comprador') {
            $especiales = $this->model->getPreciosEspecialesComprador(
                $this->usuarioId() ?? 0,
                $this->empresaId()
            );
            foreach ($especiales as $pe) {
                $preciosEspeciales[(int)$pe['producto_id']] = (float)$pe['precio'];
            }
        }

        // Combos disponibles según rol
        $comboModel = new ComboModel();
        $combos = [];
        if ($this->rolActual() === 'comprador') {
            $combosRaw = $comboModel->getCombosParaComprador($this->usuarioId(), $this->empresaId());
            foreach ($combosRaw as $c) {
                $c['items'] = $comboModel->getItems((int)$c['id']);
                $combos[]   = $c;
            }
        } elseif (in_array($this->rolActual(), ['admin_empresa', 'supervisor'], true)) {
            $combosRaw = $comboModel->listadoEmpresa($this->empresaId());
            foreach ($combosRaw as $c) {
                $c['items'] = $comboModel->getItems((int)$c['id']);
                $combos[]   = $c;
            }
        }

        $flash     = $this->getFlash();
        $pageTitle = 'Catálogo de productos';
        $activeMenu = 'catalogo';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/catalogo/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function detalle(?string $id = null): void
    {
        $productoId = (int)$id;
        $producto = $this->model->conDetalle($productoId);

        if (!$producto || !$producto['activo']) {
            $this->redirect('catalogo/index');
        }

        $flash     = $this->getFlash();
        $pageTitle = htmlspecialchars($producto['nombre']);
        $activeMenu = 'catalogo';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/catalogo/detalle.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }
}
