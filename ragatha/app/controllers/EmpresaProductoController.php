<?php
require_once ROOT_PATH . '/app/controllers/BaseController.php';

class EmpresaProductoController extends BaseController
{
    private ProductoModel $productoModel;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdminEmpresa();
        $this->productoModel = new ProductoModel();
    }

    public function index(?string $p = null): void
    {
        $filtros = [
            'empresa_id'   => $this->empresaId(),
            'buscar'       => $this->get('buscar', ''),
            'categoria_id' => $this->get('categoria_id', ''),
            'stock_bajo'   => $this->get('stock_bajo', ''),
        ];
        $page       = max(1, (int)$this->get('page', 1));
        $resultado  = $this->productoModel->listadoAdmin($filtros, $page);
        $productos  = $resultado['data'];
        $paginacion = $resultado;
        $categorias = $this->productoModel->getCategorias();
        $flash      = $this->getFlash();
        $pageTitle  = 'Catálogo de Productos';
        $activeMenu = 'productos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/productos/index.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function nuevo(?string $p = null): void
    {
        $categorias = $this->productoModel->getCategorias();
        $producto   = null;
        $flash      = $this->getFlash();
        $pageTitle  = 'Nuevo Producto';
        $activeMenu = 'productos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/productos/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function guardar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-producto/nuevo');
        }

        $imagen = '';
        if (!empty($_FILES['imagen']['name'])) {
            $imagen = $this->subirImagen($_FILES['imagen']);
            if (!$imagen) {
                $this->flash('error', 'La imagen debe ser JPG, PNG o WebP y no superar 2 MB.');
                $this->redirect('empresa-producto/nuevo');
            }
        }

        $id = $this->productoModel->insert([
            'empresa_id'   => $this->empresaId(),
            'nombre'       => trim($this->post('nombre')),
            'descripcion'  => trim($this->post('descripcion', '')),
            'categoria_id' => (int)$this->post('categoria_id'),
            'precio_base'  => (float)$this->post('precio_base'),
            'presentacion' => trim($this->post('presentacion', 'kg')),
            'imagen'       => $imagen,
            'activo'       => 1,
        ]);

        $this->productoModel->actualizarEscalonados(
            $id,
            $_POST['esc_cant_min'] ?? [],
            $_POST['esc_cant_max'] ?? [],
            $_POST['esc_precio']   ?? []
        );

        $this->productoModel->inicializarInventario(
            $id,
            (int)$this->post('stock_inicial', 0),
            (int)$this->post('umbral_minimo', 10)
        );

        $this->log('Crear producto', 'productos', "ID: $id");
        $this->flash('success', 'Producto creado correctamente.');
        $this->redirect('empresa-producto/index');
    }

    public function editar(?string $p = null): void
    {
        $producto = $this->productoModel->conDetalle((int)$p);
        if (!$producto) {
            $this->flash('error', 'Producto no encontrado.');
            $this->redirect('empresa-producto/index');
        }

        $categorias = $this->productoModel->getCategorias();
        $flash      = $this->getFlash();
        $pageTitle  = 'Editar: ' . $producto['nombre'];
        $activeMenu = 'productos';

        ob_start();
        require ROOT_PATH . '/app/views/empresa/productos/form.php';
        $content = ob_get_clean();
        require ROOT_PATH . '/app/views/empresa/layouts/main.php';
    }

    public function actualizar(?string $p = null): void
    {
        if (!$this->isPost()) {
            $this->redirect('empresa-producto/index');
        }

        $id   = (int)$p;
        $data = [
            'nombre'       => trim($this->post('nombre')),
            'descripcion'  => trim($this->post('descripcion', '')),
            'categoria_id' => (int)$this->post('categoria_id'),
            'precio_base'  => (float)$this->post('precio_base'),
            'presentacion' => trim($this->post('presentacion', 'kg')),
        ];

        if (!empty($_FILES['imagen']['name'])) {
            $imagen = $this->subirImagen($_FILES['imagen']);
            if ($imagen) {
                $data['imagen'] = $imagen;
            }
        }

        $this->productoModel->update($id, $data);

        $this->productoModel->actualizarEscalonados(
            $id,
            $_POST['esc_cant_min'] ?? [],
            $_POST['esc_cant_max'] ?? [],
            $_POST['esc_precio']   ?? []
        );

        $this->productoModel->actualizarInventario($id, (int)$this->post('umbral_minimo', 10));

        $this->log('Editar producto', 'productos', "ID: $id");
        $this->flash('success', 'Producto actualizado.');
        $this->redirect('empresa-producto/index');
    }

    public function eliminar(?string $p = null): void
    {
        $id = (int)$p;
        $this->productoModel->update($id, ['activo' => 0]);
        $this->log('Desactivar producto', 'productos', "ID: $id");
        $this->flash('success', 'Producto ocultado — ya no es visible para los compradores.');
        $this->redirect('empresa-producto/index');
    }

    public function activar(?string $p = null): void
    {
        $id = (int)$p;
        // Verificar que el producto pertenece a esta empresa
        $prod = $this->productoModel->find($id);
        if (!$prod || $prod['empresa_id'] != $this->empresaId()) {
            $this->redirect('empresa-producto/index');
        }
        $this->productoModel->update($id, ['activo' => 1]);
        $this->log('Activar producto', 'productos', "ID: $id");
        $this->flash('success', 'Producto activado — ya es visible para los compradores.');
        $this->redirect('empresa-producto/index');
    }

    private function subirImagen(array $file): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return '';
        if ($file['size'] > 2 * 1024 * 1024) return '';

        $nombre  = 'prod_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = UPLOAD_PATH . 'productos/' . $nombre;
        if (!move_uploaded_file($file['tmp_name'], $destino)) return '';

        return UPLOAD_URL . 'productos/' . $nombre;
    }
}
