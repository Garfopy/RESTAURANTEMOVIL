<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\User;

class ProfileController
{
    public function show(): void
    {
        $user = AuthMiddleware::authenticate();
        $userData = User::findById($user->id);

        if (!$userData) {
            Response::notFound('Usuario no encontrado');
        }

        Response::success(['profile' => $userData]);
    }

    public function update(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $rules = [
            'name' => 'min:3|max:100',
            'phone' => 'max:20',
            'address' => 'max:255'
        ];

        $errors = ValidationMiddleware::validate($rules, $input);

        if (!empty($errors)) {
            Response::validationError($errors);
        }

        $updateData = [];
        
        if (isset($input['name'])) {
            $updateData['name'] = $input['name'];
        }
        
        if (isset($input['phone'])) {
            $updateData['phone'] = $input['phone'];
        }
        
        if (isset($input['address'])) {
            $updateData['address'] = $input['address'];
        }

        if (empty($updateData)) {
            Response::error('No se proporcionaron datos para actualizar', 400);
        }

        if (!User::update($user->id, $updateData)) {
            Response::serverError('No se pudo actualizar el perfil');
        }

        $updatedUser = User::findById($user->id);
        
        Response::success(['profile' => $updatedUser], 'Perfil actualizado exitosamente');
    }

    public function orders(): void
    {
        $user = AuthMiddleware::authenticate();
        
        $sql = "SELECT o.*, b.name as branch_name 
                FROM orders o
                LEFT JOIN branches b ON o.branch_id = b.id
                WHERE o.user_id = :user_id
                ORDER BY o.created_at DESC
                LIMIT 50";
        
        $orders = \Amare\Api\Config\Database::query($sql, [':user_id' => $user->id]);
        
        foreach ($orders as &$order) {
            $order['items'] = $this->getOrderItems($order['id']);
        }
        
        Response::success(['orders' => $orders]);
    }

    private function getOrderItems(int $orderId): array
    {
        $sql = "SELECT oi.*, p.name as product_name 
                FROM order_items oi
                LEFT JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = :order_id";
        
        return \Amare\Api\Config\Database::query($sql, [':order_id' => $orderId]);
    }
}