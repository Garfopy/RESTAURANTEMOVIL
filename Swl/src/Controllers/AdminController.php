<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Config\Database;
use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;

class AdminController
{
    // GET /admin/users
    // Devuelve la lista de usuarios registrados en la app movil.
    // Util para el selector de usuario al crear/editar promociones desde la web admin.
    // Query params opcionales:
    //   search   (string) busca por nombre o email
    //   page     (int, default 1)
    //   per_page (int, default 50, max 200)
    public function users(): void
    {
        AuthMiddleware::requireAdmin();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(200, max(1, (int)($_GET['per_page'] ?? 50)));
        $search  = trim($_GET['search'] ?? '');

        $offset = ($page - 1) * $perPage;

        $where  = '1=1';
        $params = [];

        if ($search !== '') {
            $where .= ' AND (nombre LIKE :search OR email LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = "SELECT id, nombre, email, created_at
                FROM mobile_usuarios
                WHERE {$where}
                ORDER BY nombre ASC
                LIMIT :limit OFFSET :offset";

        $params[':limit']  = $perPage;
        $params[':offset'] = $offset;

        $users = Database::query($sql, $params);

        $countSql    = "SELECT COUNT(*) as total FROM mobile_usuarios WHERE {$where}";
        $countParams = array_diff_key($params, [':limit' => '', ':offset' => '']);
        $countResult = Database::queryOne($countSql, $countParams);
        $total       = (int)($countResult['total'] ?? 0);

        Response::success([
            'users' => $users,
            'pagination' => [
                'total'    => $total,
                'page'     => $page,
                'per_page' => $perPage,
                'pages'    => (int)ceil($total / $perPage),
            ],
        ]);
    }
}
