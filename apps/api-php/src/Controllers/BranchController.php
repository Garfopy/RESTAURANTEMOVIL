<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Models\Branch;

class BranchController
{
    public function index(): void
    {
        $branches = Branch::getAll();
        Response::success(['branches' => $branches]);
    }

    public function nearest(): void
    {
        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;
        $tipoPedido = isset($_GET['tipo_pedido']) ? trim((string)$_GET['tipo_pedido']) : null;

        if ($lat === null || $lng === null) {
            Response::validationError(['location' => ['lat y lng son requeridos']]);
        }

        if ($tipoPedido !== null && !in_array($tipoPedido, ['delivery', 'pickup', 'eat_in'], true)) {
            Response::validationError(['tipo_pedido' => ['Tipo de pedido no válido']]);
        }

        $branches = Branch::nearest($lat, $lng, $tipoPedido);
        Response::success(['branches' => $branches]);
    }

    public function show(int $id): void
    {
        $branch = Branch::findById($id);

        if (!$branch) {
            Response::notFound('Sucursal no encontrada');
        }

        Response::success(['branch' => $branch]);
    }
}
