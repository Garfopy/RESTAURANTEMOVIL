<?php

declare(strict_types=1);

namespace Amare\Api\Controllers;

use Amare\Api\Helpers\Response;
use Amare\Api\Middleware\AuthMiddleware;
use Amare\Api\Middleware\ValidationMiddleware;
use Amare\Api\Models\PushToken;

class PushTokenController
{
    public function store(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $token = trim((string)($input['fcm_token'] ?? $input['token'] ?? ''));
        $platform = isset($input['platform']) ? (string)$input['platform'] : null;
        $deviceId = isset($input['device_id']) ? (string)$input['device_id'] : null;

        try {
            $registration = PushToken::upsert((int)$user->id, $token, $platform, $deviceId);
        } catch (\InvalidArgumentException $exception) {
            Response::validationError(['fcm_token' => [$exception->getMessage()]]);
        } catch (\Throwable $exception) {
            error_log('PushTokenController ERROR: user_id=' . (int)$user->id . ' message=' . $exception->getMessage());
            Response::serverError('No se pudo registrar el token push');
        }

        $unchanged = !empty($registration['_unchanged']);
        if (!$unchanged) {
            error_log(sprintf(
                'PushTokenController INFO: user_id=%d registration_id=%d platform=%s device_id=%s token=%s enabled=%d registered=1',
                (int)$user->id,
                (int)($registration['id'] ?? 0),
                (string)($registration['platform'] ?? 'unknown'),
                self::preview((string)($registration['device_id'] ?? 'unknown')),
                self::preview($token),
                (int)($registration['enabled'] ?? 0)
            ));
        }

        Response::success(['registered' => true, 'unchanged' => $unchanged], 'Token push registrado');
    }

    public function destroy(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $token = trim((string)($input['fcm_token'] ?? $input['token'] ?? ''));
        PushToken::disable((int)$user->id, $token);

        Response::success(['registered' => false], 'Token push desactivado');
    }

    private static function preview(string $value): string
    {
        $value = trim($value);
        return strlen($value) > 20
            ? substr($value, 0, 12) . '...' . substr($value, -4)
            : $value;
    }
}
