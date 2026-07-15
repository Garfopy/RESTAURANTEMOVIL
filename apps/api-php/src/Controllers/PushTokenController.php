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
            PushToken::upsert((int)$user->id, $token, $platform, $deviceId);
        } catch (\InvalidArgumentException $exception) {
            Response::validationError(['fcm_token' => [$exception->getMessage()]]);
        }

        error_log(sprintf(
            'PushTokenController INFO: user_id=%d platform=%s token=%s registered=1',
            (int)$user->id,
            $platform !== null && $platform !== '' ? $platform : 'unknown',
            strlen($token) > 16 ? substr($token, 0, 12) . '...' . substr($token, -4) : $token
        ));

        Response::success(['registered' => true], 'Token push registrado');
    }

    public function destroy(): void
    {
        $user = AuthMiddleware::authenticate();
        $input = ValidationMiddleware::getAllInput();

        $token = trim((string)($input['fcm_token'] ?? $input['token'] ?? ''));
        PushToken::disable((int)$user->id, $token);

        Response::success(['registered' => false], 'Token push desactivado');
    }
}
