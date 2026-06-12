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

    public function show(int $id): void
    {
        $branch = Branch::findById($id);

        if (!$branch) {
            Response::notFound('Sucursal no encontrada');
        }

        Response::success(['branch' => $branch]);
    }
}