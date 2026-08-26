<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\RestaurantConfig;
use Amare\Api\Helpers\BranchConfigEvents;
use Amare\Api\Models\DishModifier;

class ConfigController
{
    /**
     * GET /branches/:id/config
     * Obtiene la configuración de un restaurante (público, lo consume la app móvil).
     */
    public function show(int $restauranteId): void
    {
        $branchId = $restauranteId;
        $restauranteId = DishModifier::resolveRestaurantId($branchId) ?? 0;
        if ($restauranteId <= 0) Response::notFound('Sucursal no encontrada.');
        $config = RestaurantConfig::getByRestaurant($restauranteId);
        $etag = '"branch-' . $branchId . '-v' . (int)($config['version'] ?? 0) . '"';
        self::noCache();
        header('ETag: ' . $etag);
        if (trim((string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
            http_response_code(304);
            exit;
        }
        Response::success(['config' => $config] + $config);
    }

    /**
     * PUT /branches/:id/config
     * Actualiza la configuración de un restaurante (requiere autenticación).
     * Solo el dueño o admin del restaurante puede modificar.
     */
    public function update(int $restauranteId): void
    {
        $user = AuthMiddleware::authenticate();
        $branchId = $restauranteId;
        $restauranteId = DishModifier::resolveRestaurantId($branchId) ?? 0;
        if ($restauranteId <= 0) Response::notFound('Sucursal no encontrada.');
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
            $allowed = ['pickup'];
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

        if (isset($input['modificadores'])) {
            if (!is_array($input['modificadores'])) {
                Response::validationError(['modificadores' => ['La configuración de modificadores no es válida']]);
            }
            if (array_key_exists('exclusiones_habilitadas', $input['modificadores'])) {
                $data['exclusiones_habilitadas'] = (bool)$input['modificadores']['exclusiones_habilitadas'];
            }
            if (array_key_exists('extras_habilitados', $input['modificadores'])) {
                $data['extras_habilitados'] = (bool)$input['modificadores']['extras_habilitados'];
            }
        }

        if (isset($input['facturacion'])) {
            if (!is_array($input['facturacion'])) {
                Response::validationError(['facturacion' => ['La configuracion de facturacion no es valida']]);
            }

            if (array_key_exists('habilitada', $input['facturacion'])) {
                $data['facturacion_habilitada'] = (bool)$input['facturacion']['habilitada'];
            }

            if (array_key_exists('emisor', $input['facturacion'])) {
                $emisor = $input['facturacion']['emisor'];
                if ($emisor !== null && !is_array($emisor)) {
                    Response::validationError(['facturacion.emisor' => ['Los datos del emisor no son validos']]);
                }
                $data['facturacion_emisor'] = $emisor !== null ? [
                    'rfc' => strtoupper(trim((string)($emisor['rfc'] ?? ''))),
                    'nombre_fiscal' => trim((string)($emisor['nombre_fiscal'] ?? $emisor['razon_social'] ?? '')),
                    'regimen_fiscal' => strtoupper(trim((string)($emisor['regimen_fiscal'] ?? ''))),
                    'codigo_postal' => trim((string)($emisor['codigo_postal'] ?? $emisor['cp'] ?? '')),
                ] : null;
            }

            if (array_key_exists('email_notificacion', $input['facturacion'])) {
                $email = strtolower(trim((string)($input['facturacion']['email_notificacion'] ?? '')));
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    Response::validationError(['facturacion.email_notificacion' => ['Ingresa un email valido']]);
                }
                $data['facturacion_email_notificacion'] = $email !== '' ? $email : null;
            }
        }

        $success = RestaurantConfig::upsert($restauranteId, $data);

        if (!$success && empty($data)) {
            Response::validationError(['config' => ['No se enviaron campos para actualizar']]);
        }

        $config = RestaurantConfig::getByRestaurant($restauranteId);
        BranchConfigEvents::publish($branchId, (int)($config['version'] ?? 0));
        self::noCache();
        Response::success(['config' => $config] + $config, 'Configuración actualizada exitosamente');
    }

    private static function noCache(): void
    {
        header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}
