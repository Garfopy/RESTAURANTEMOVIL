<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\InvoiceRequest;

class InvoiceRequestController
{
    public function adminIndex(): void
    {
        AuthMiddleware::requireAdmin();

        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int)($_GET['per_page'] ?? 50)));
        $filters = [
            'restaurante_id' => isset($_GET['restaurant_id']) ? (int)$_GET['restaurant_id'] : null,
            'estado' => $_GET['estado'] ?? null,
            'from' => $_GET['from'] ?? null,
            'to' => $_GET['to'] ?? null,
        ];

        Response::success([
            'invoice_requests' => InvoiceRequest::listForAdmin($filters, $perPage, ($page - 1) * $perPage),
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function adminShow(int $id): void
    {
        AuthMiddleware::requireAdmin();
        $request = InvoiceRequest::findById($id);
        if (!$request) {
            Response::notFound('Solicitud de factura no encontrada');
        }

        Response::success(['invoice_request' => $request]);
    }

    public function adminUpdate(int $id): void
    {
        AuthMiddleware::requireAdmin();
        $input = ValidationMiddleware::getAllInput();

        try {
            $request = InvoiceRequest::updateAdmin($id, $input);
        } catch (\InvalidArgumentException $exception) {
            Response::validationError(['invoice_request' => [$exception->getMessage()]]);
        }

        if (!$request) {
            Response::notFound('Solicitud de factura no encontrada');
        }

        Response::success(['invoice_request' => $request], 'Solicitud de factura actualizada');
    }
}
