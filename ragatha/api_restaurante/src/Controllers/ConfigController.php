<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\RestaurantConfig;

class ConfigController
{
    /**
     * GET /branches/:id/config
     * Obtiene la configuración de un restaurante (público, lo consume la app móvil).
     */
    public function show(int $restauranteId): void
    {
        $config = RestaurantConfig::getByRestaurant($restauranteId);
        Response::success(['config' => $config]);
    }

    /**
     * PUT /branches/:id/config
     * Actualiza la configuración de un restaurante (requiere autenticación).
     * Solo el dueño o admin del restaurante puede modificar.
     */
    public function update(int $restauranteId): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        // Validar que los arrays sean válidos si vienen
        if (isset($input['metodos_pago'])) {
            $allowed = ['card', 'cash', 'apple_pay', 'google_pay'];
            if (is_array($input['metodos_pago'])) {
                foreach ($input['metodos_pago'] as $m) {
                    if (!in_array($m, $allowed, true)) {
                        Response::validationError([
                            'metodos_pago' => ["Método de pago no válido: {$m}. Permitidos: " . implode(', ', $allowed)]
                        ]);
                    }
                }
            }
        }

        if (isset($input['tipos_entrega'])) {
            $allowed = ['delivery', 'pickup', 'eat_in'];
            if (is_array($input['tipos_entrega'])) {
                foreach ($input['tipos_entrega'] as $t) {
                    if (!in_array($t, $allowed, true)) {
                        Response::validationError([
                            'tipos_entrega' => ["Tipo de entrega no válido: {$t}. Permitidos: " . implode(', ', $allowed)]
                        ]);
                    }
                }
            }
        }

        $data = [];

        if (isset($input['metodos_pago'])) {
            $data['metodos_pago'] = $input['metodos_pago'];
        }

        if (isset($input['tipos_entrega'])) {
            $data['tipos_entrega'] = $input['tipos_entrega'];
        }

        if (isset($input['costo_envio'])) {
            $data['costo_envio'] = (float) $input['costo_envio'];
        }

        if (isset($input['pedido_minimo'])) {
            $data['pedido_minimo'] = (float) $input['pedido_minimo'];
        }

        if (isset($input['activo'])) {
            $data['activo'] = (bool) $input['activo'];
        }

        $success = RestaurantConfig::upsert($restauranteId, $data);

        if (!$success && empty($data)) {
            Response::validationError(['config' => ['No se enviaron campos para actualizar']]);
        }

        $config = RestaurantConfig::getByRestaurant($restauranteId);
        Response::success(['config' => $config], 'Configuración actualizada exitosamente');
    }
}